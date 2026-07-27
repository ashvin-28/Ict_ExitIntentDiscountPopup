define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/step-navigator'
], function (Component, ko, stepNavigator) {
    'use strict';

    var MOBILE_BREAKPOINT = 768;
    var THROTTLE_MS       = 200;
    var COPY_RESET_MS     = 3000;
    var PAYMENT_STEP_CODE = 'payment';

    // =========================================================================
    // Single suppression mechanism: sessionStorage, scoped to ruleId.
    //
    // Set inside closePopup() when the popup is dismissed by ANY method
    // (X, ESC, overlay click, Place Order Now, or after Copy Code).
    //
    // Only active when popupFrequency === 'once_per_session'.
    // Cleared automatically when the browser tab closes.
    // Never written on show/trigger — only on close.
    //
    // When popupFrequency === 'always': flag is never read or written.
    // =========================================================================

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
        isVisible:          null,
        isCopied:           null,
        _isPaymentStep:     null,
        _listenersAttached: false,

        initialize: function () {
            this._super();

            this.config         = window.checkoutConfig.exitIntentPopup || {};
            this.isVisible      = ko.observable(false);
            this.isCopied       = ko.observable(false);
            this._isPaymentStep = ko.observable(false);

            if (this.config.enabled) {
                this._watchPaymentStep();
                document.addEventListener('keydown', this._onKeyDown.bind(this));
            }

            return this;
        },

        // ================================================================== //
        // Session-frequency gate
        // ================================================================== //

        /** True only when frequency = once_per_session AND already shown this tab. */
        _shownThisSession: function () {
            return this.config.popupFrequency !== 'always' &&
                   !!sessionStorage.getItem(sessionShownKey(this.config.ruleId));
        },

        /**
         * Called ONLY from closePopup().
         * Marks this session so the popup does not re-trigger while the tab is open.
         * No-op when popupFrequency === 'always'.
         */
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
        // Trigger — no storage writes
        // ================================================================== //

        _trigger: function () {
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
        // Server notification for email (unchanged)
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
        // Keyboard / overlay — no storage writes
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
        // copyCode — clipboard write + confirmation only, nothing else
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
        // closePopup — sets session-frequency flag, nothing permanent
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
