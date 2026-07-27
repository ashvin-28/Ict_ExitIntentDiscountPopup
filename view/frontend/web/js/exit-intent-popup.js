define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Checkout/js/model/step-navigator'
], function (Component, ko, $, stepNavigator) {
    'use strict';

    var MOBILE_BREAKPOINT = 768;
    var THROTTLE_MS       = 200;
    var COPY_RESET_MS     = 3000;
    var PAYMENT_STEP_CODE = 'payment';
    var EMAIL_REGEX       = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function sessionShownKey(ruleId) {
        return 'ictExitPopupSessionShown_' + ruleId;
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
        _isPaymentStep:     null,
        _listenersAttached: false,

        initialize: function () {
            this._super();

            this.config         = window.checkoutConfig.exitIntentPopup || {};
            this.popupEnabled   = ko.observable(!!this.config.enabled);
            this.isVisible      = ko.observable(false);
            this.isCopied       = ko.observable(false);
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
            $(document).on('blur', '#customer-email', function () {
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
                            // Coupon already used by this email — disable permanently
                            // for this page session and close popup if open.
                            self.popupEnabled(false);
                            if (self.isVisible()) {
                                self.closePopup();
                            }
                        } else {
                            // Not used — re-enable if server had disabled it only
                            // because quote email was unknown at page load.
                            // Only re-enable when the original config says the
                            // coupon is otherwise valid (enabled was true before
                            // the guest-email gate could run).
                            if (self.config.enabled && !self.popupEnabled()) {
                                self.popupEnabled(true);
                                // Arm exit-intent listeners now if not already done.
                                self._watchPaymentStep();
                                document.addEventListener('keydown', self._onKeyDown.bind(self));
                            }
                        }
                    }
                    // No error handler — fail-open.
                });
            });
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
                    self._isPaymentStep(step.isVisible());
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
            if (this._shownThisSession()) {
                return;
            }
            this.isVisible(true);
            this._notifyServer();
        },

        _isMobile: function () {
            return window.innerWidth < MOBILE_BREAKPOINT;
        },

        // ================================================================== //
        // Server notification
        // ================================================================== //

        _notifyServer: function () {
            fetch(window.BASE_URL + 'ict-exitintent/popup/shown', {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':     'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ form_key: window.FORM_KEY || '' })
            }).catch(function () { /* silent */ });
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
        // closePopup
        // ================================================================== //

        closePopup: function () {
            this.isVisible(false);
            this.isCopied(false);
            this._markShownThisSession();
        },

        placeOrderNow: function () {
            this.closePopup();
        }
    });
});
