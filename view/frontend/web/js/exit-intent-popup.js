define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/model/quote',
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Magento_SalesRule/js/action/set-coupon-code',
    'Magento_SalesRule/js/model/coupon'
], function (Component, ko, $, stepNavigator, quote, storage, urlBuilder, setCouponCodeAction, nativeCoupon) {
    'use strict';

    var MOBILE_BREAKPOINT = 768;
    var THROTTLE_MS       = 200;
    var COPY_RESET_MS     = 3000;
    var APPLY_RESET_MS    = 1000;
    var PAYMENT_STEP_CODE = 'payment';
    var EMAIL_REGEX       = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function sessionShownKey(ruleId) {
        return 'ictExitPopupSessionShown_' + ruleId;
    }

    function loginPromptSessionShownKey(ruleId) {
        return 'ictExitPopupLoginPromptShown_' + ruleId;
    }

    function throttle(fn, wait) {
        var last = 0;
        return function () {
            var now = Date.now();
            if (now - last >= wait) {
                last = now;
                fn.apply(this, arguments);
            }
        };
    }

    return Component.extend({
        defaults: {
            template: 'Ict_ExitIntentDiscountPopup/popup',
            closeOnOverlayClick: true
        },

        config:             {},
        popupEnabled:       null,
        isVisible:          null,
        isCopied:           null,
        isApplied:          null,
        applyError:         null,
        popupMode:          null,
        _isPaymentStep:     null,
        _listenersAttached: false,

        initialize: function () {
            this._super();

            this.config         = window.checkoutConfig.exitIntentPopup || {};
            this.popupEnabled   = ko.observable(!!this.config.enabled);
            this.isVisible      = ko.observable(false);
            this.isCopied       = ko.observable(false);
            this.isApplied      = ko.observable(false);
            this.applyError     = ko.observable('');
            this.popupMode      = ko.observable('discount');
            this._isPaymentStep = ko.observable(false);

            // ruleId must exist for any functionality to make sense.
            if (!this.config.ruleId) {
                return this;
            }

            // Email blur check runs always (even if server said enabled:false at
            // page-load time due to missing quote email) so we can do a runtime
            // re-evaluation once the guest types their email.
            this._attachEmailBlurCheck();

            // Exit-intent listeners and keyboard handler only arm when enabled.
            if (this.config.enabled) {
                this._watchPaymentStep();
                document.addEventListener('keydown', this._onKeyDown.bind(this));
            }

            return this;
        },

        // ================================================================== //
        // Runtime guest-usage check via email blur
        // ================================================================== //

        _attachEmailBlurCheck: function () {
            var self = this;

            // Delegated — #customer-email is rendered asynchronously by Knockout.
            // Both blur and change cover tab-away and autofill scenarios.
            $(document).on('blur change', '#customer-email', function () {
                var email = $.trim($(this).val());

                if (!email || !EMAIL_REGEX.test(email)) {
                    return;
                }

                $.ajax({
                    url:         window.BASE_URL + 'ict-exitintent/index/checkguestusage',
                    type:        'POST',
                    contentType: 'application/json',
                    data:        JSON.stringify({ email: email }),
                    success: function (response) {
                        if (response && response.used === true) {
                            self.popupEnabled(false);
                            if (self.isVisible()) {
                                self.closePopup();
                            }
                            // If a coupon is already applied on the cart, remove it
                            // now so the discount disappears from the order summary
                            // immediately without waiting for the server backstop.
                            self._removeCouponIfApplied();
                        } else {
                            if (self.config.enabled && !self.popupEnabled()) {
                                self.popupEnabled(true);
                                self._watchPaymentStep();
                                document.addEventListener('keydown', self._onKeyDown.bind(self));
                            }
                        }
                    }
                    // No error handler — fail-open.
                });
            });
        },

        /**
         * Calls Magento's REST DELETE /V1/guest-carts/:cartId/coupons endpoint
         * if the current quote already has a coupon applied.
         * This is a best-effort UX call — the server-side observer is the hard
         * enforcement backstop and will strip the coupon even if this fails.
         */
        _removeCouponIfApplied: function () {
            var totalsData = quote.getTotals()();
            if (!totalsData || !totalsData.coupon_code) {
                return;
            }

            var quoteId = quote.getQuoteId();
            if (!quoteId) {
                return;
            }

            // Guest cart coupon removal: DELETE /V1/guest-carts/:cartId/coupons
            storage.delete(
                urlBuilder.createUrl('/guest-carts/:cartId/coupons', { cartId: quoteId })
            ).done(function () {
                // Force totals re-load so the discount line disappears from the UI.
                var totalsService = require('Magento_Checkout/js/model/totals');
                if (totalsService && typeof totalsService.isLoading === 'function') {
                    // Trigger a totals reload by dispatching a cart update.
                    require('Magento_Checkout/js/action/get-totals')(['']);
                }
            }).fail(function () { /* fail-open — server backstop will enforce */ });
        },


        // ================================================================== //
        // Session-frequency gate
        // ================================================================== //

        _shownThisSession: function () {
            return this.config.popupFrequency !== 'always' &&
                   !!sessionStorage.getItem(sessionShownKey(this.config.ruleId));
        },

        _markShownThisSession: function () {
            if (this.config.popupFrequency === 'always') {
                return;
            }
            sessionStorage.setItem(sessionShownKey(this.config.ruleId), '1');
        },

        // ================================================================== //
        // Guest login-prompt session gate — its own independently
        // configurable Login Prompt Frequency setting, separate from the
        // Popup Frequency setting (which governs only the discount popup's
        // cadence for logged-in/eligible shoppers).
        // ================================================================== //

        _loginPromptShownThisSession: function () {
            return this.config.loginPrompt.frequency !== 'always' &&
                   !!sessionStorage.getItem(loginPromptSessionShownKey(this.config.ruleId));
        },

        _markLoginPromptShownThisSession: function () {
            if (this.config.loginPrompt.frequency === 'always') {
                return;
            }
            sessionStorage.setItem(loginPromptSessionShownKey(this.config.ruleId), '1');
        },

        // ================================================================== //
        // Step-navigator subscription
        // ================================================================== //

        _watchPaymentStep: function () {
            var self = this;
            self._bindToPaymentStep();
            stepNavigator.steps.subscribe(function () {
                self._bindToPaymentStep();
            });
        },

        _bindToPaymentStep: function () {
            var self = this;
            stepNavigator.steps().forEach(function (step) {
                if (step.code === PAYMENT_STEP_CODE) {
                    var isVisibleNow = step.isVisible();
                    self._isPaymentStep(isVisibleNow);

                    // Arm immediately if payment is already the active step at bind
                    // time (e.g. a reload while already on #payment) — subscribe()
                    // below only fires on a future transition, not the current value.
                    if (isVisibleNow) {
                        self._armListeners();
                    }

                    step.isVisible.subscribe(function (visible) {
                        self._isPaymentStep(visible);
                        if (visible) {
                            self._armListeners();
                        }
                    });
                }
            });
        },

        // ================================================================== //
        // Listener setup
        // ================================================================== //

        _armListeners: function () {
            if (this._listenersAttached) {
                return;
            }
            this._listenersAttached = true;

            if (this._isMobile()) {
                this._attachMobileListeners();
            } else {
                this._attachDesktopListeners();
            }
        },

        _attachDesktopListeners: function () {
            var self    = this;
            var onLeave = throttle(function (e) {
                if (e.clientY <= 5) {
                    self._trigger();
                }
            }, THROTTLE_MS);
            document.addEventListener('mouseleave', onLeave);
        },

        _attachMobileListeners: function () {
            var self    = this;
            var delay   = this.config.mobileDelay || 30000;
            var timerId = null;

            var reset = throttle(function () {
                clearTimeout(timerId);
                timerId = setTimeout(function () {
                    self._trigger();
                }, delay);
            }, THROTTLE_MS);

            reset();
            document.addEventListener('touchstart', reset, { passive: true });
            document.addEventListener('scroll',     reset, { passive: true });
            document.addEventListener('keypress',   reset);
        },

        // ================================================================== //
        // Trigger — reads popupEnabled() observable
        // ================================================================== //

        _trigger: function () {
            if (!this.popupEnabled()) {
                return;
            }
            if (!this._isPaymentStep()) {
                return;
            }

            // Guests are shown a login prompt instead of the discount, once
            // per session — they only reach the discount popup (below) after
            // logging in, on a later trigger.
            if (!this.config.isLoggedIn) {
                this._triggerLoginPrompt();
                return;
            }

            if (this._shownThisSession()) {
                return;
            }
            this.popupMode('discount');
            this.isVisible(true);
        },

        _triggerLoginPrompt: function () {
            if (this._loginPromptShownThisSession()) {
                return;
            }
            this._markLoginPromptShownThisSession();
            this.popupMode('loginPrompt');
            this.isVisible(true);
        },

        _isMobile: function () {
            var isNarrowViewport = window.innerWidth < MOBILE_BREAKPOINT;

            // Width alone misclassifies tablets (e.g. a 768px-wide iPad lands
            // exactly on the desktop side of the breakpoint) as mouse-driven,
            // but touch-only devices essentially never fire mouseleave — so
            // any touch-capable device is routed to the idle-timer path too.
            var isTouchDevice = ('ontouchstart' in window) ||
                (navigator.maxTouchPoints > 0) ||
                (window.matchMedia && window.matchMedia('(pointer: coarse)').matches);

            return isNarrowViewport || isTouchDevice;
        },

        // ================================================================== //
        // Keyboard / overlay
        // ================================================================== //

        _onKeyDown: function (e) {
            if (e.key === 'Escape' && this.isVisible()) {
                this.closePopup();
            }
        },

        onOverlayClick: function (data, event) {
            if (this.closeOnOverlayClick &&
                event.target === event.currentTarget) {
                this.closePopup();
            }
        },

        // ================================================================== //
        // copyCode — clipboard only
        // ================================================================== //

        copyCode: function () {
            var self = this;
            var code = this.config.couponCode;

            if (!code) {
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code)
                    .then(function () { self._showCopiedConfirmation(); })
                    .catch(function () { self._fallbackCopy(); });
            } else {
                this._fallbackCopy();
            }
        },

        _fallbackCopy: function () {
            var el = document.querySelector('.exit-intent-coupon-code');
            if (!el) {
                return;
            }
            el.select();
            try {
                document.execCommand('copy');
                this._showCopiedConfirmation();
            } catch (e) { /* silent */ }
        },

        _showCopiedConfirmation: function () {
            var self = this;
            this.isCopied(true);
            setTimeout(function () {
                self.isCopied(false);
            }, COPY_RESET_MS);
        },

        // ================================================================== //
        // applyCode — applies the coupon to the cart/quote via Magento's own
        // coupon-apply action (Magento_SalesRule/js/action/set-coupon-code),
        // the same mechanism used by the checkout order summary's own
        // "Apply Discount Code" widget. This goes through the standard
        // guest/customer coupon REST endpoints, so it is still subject to
        // BlockReusedGuestCoupon (guests) and native Uses-Per-Customer
        // (logged-in) validation — an already-redeemed coupon is rejected.
        //
        // The applied flag is passed through on the shared
        // Magento_SalesRule/js/model/coupon singleton (not a throwaway local
        // observable) — that is the exact observable the native "Apply
        // Discount Code" widget itself is bound to, so setting it here
        // updates that widget's own UI (input, Apply/Cancel toggle) instantly
        // as well, without waiting for a page refresh.
        // ================================================================== //

        applyCode: function () {
            var self = this;
            var code = this.config.couponCode;

            if (!code) {
                return;
            }

            this.applyError('');

            setCouponCodeAction(code, nativeCoupon.getIsApplied())
                .done(function () {
                    nativeCoupon.setCouponCode(code);
                    self._showAppliedConfirmation();
                })
                .fail(function (response) {
                    self._showApplyError(response);
                });
        },

        _showAppliedConfirmation: function () {
            var self = this;
            this.isApplied(true);
            setTimeout(function () {
                self.isApplied(false);
                 self.closePopup();
            }, APPLY_RESET_MS);
        },

        _showApplyError: function (response) {
            var message = '';

            try {
                message = JSON.parse(response.responseText).message;
            } catch (e) { /* fall back to the generic message below */ }

            this.applyError(message || this.config.i18n.applyGenericErrorText);
        },

        // ================================================================== //
        // closePopup
        // ================================================================== //

        closePopup: function () {
            this.isVisible(false);
            this.isCopied(false);
            this.isApplied(false);
            this.applyError('');

            // The login prompt is already marked shown-this-session at
            // trigger time (see _triggerLoginPrompt) — only the discount
            // popup's Popup Frequency gate is marked here, on close.
            if (this.popupMode() === 'discount') {
                this._markShownThisSession();
            }
        },

        // ================================================================== //
        // Login prompt actions — full-page navigation; the target URLs
        // already carry an encoded referer back to checkout (see
        // ConfigProvider::buildAccountUrl() and Observer\SetLoginReferrerUrl).
        // ================================================================== //

        goToLogin: function () {
            window.location.href = this.config.loginPrompt.loginUrl;
        },

        goToRegister: function () {
            window.location.href = this.config.loginPrompt.registerUrl;
        }
    });
});
