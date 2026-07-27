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
    // Storage keys — two completely separate concerns, two separate stores.
    //
    // SESSION key  (sessionStorage) — "once per session" display-frequency only.
    //   Set inside closePopup() after the popup is dismissed (with OR without copy).
    //   Cleared automatically when the browser tab closes.
    //   Purpose: prevent the popup re-triggering multiple times in one sitting.
    //
    // USED key (localStorage for guests / DB for logged-in) — permanent suppression.
    //   Set ONLY inside _persistCouponUsed(), called ONLY from copyCode().
    //   Never set on show, never set on close without copy.
    //   Purpose: "customer has already used this coupon, never show again".
    // =========================================================================

    /** sessionStorage — display-frequency gate, scoped to ruleId */
    var SESSION_SHOWN_KEY = 'ictExitPopupSessionShown';

    /** localStorage — permanent used gate for guests, scoped to ruleId */
    function localUsedKey(ruleId) {
        return 'ictExitIntentCouponUsed_' + ruleId;
    }

    // -------------------------------------------------------------------------
    // Debug logger — logs every flag write with the calling function name.
    // Remove or set DEBUG = false before going to production.
    // -------------------------------------------------------------------------
    var DEBUG = true;

    function dbg(fn, message, data) {
        if (!DEBUG) {
            return;
        }
        console.log(
            '[ExitIntentPopup][' + fn + '] ' + message,
            data !== undefined ? data : ''
        );
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

            dbg('initialize', 'config loaded', {
                enabled:        this.config.enabled,
                ruleId:         this.config.ruleId,
                popupFrequency: this.config.popupFrequency,
                isLoggedIn:     this.config.isLoggedIn
            });

            if (this.config.enabled && !this._couponAlreadyUsed()) {
                this._watchPaymentStep();
                document.addEventListener('keydown', this._onKeyDown.bind(this));
            } else {
                dbg('initialize', 'listeners NOT attached — enabled:false or coupon already used');
            }

            return this;
        },

        // ================================================================== //
        // PRIMARY gate — permanent coupon-used suppression
        // Set ONLY by copyCode() → _persistCouponUsed(). Never set elsewhere.
        // ================================================================== //

        _couponAlreadyUsed: function () {
            if (this.config.isLoggedIn) {
                // Server-side: ConfigProvider returns enabled:false when the DB
                // record exists. If we reach here, enabled is true → not used yet.
                dbg('_couponAlreadyUsed', 'logged-in customer — server says not used');
                return false;
            }
            var used = !!localStorage.getItem(localUsedKey(this.config.ruleId));
            dbg('_couponAlreadyUsed', 'guest localStorage check', {
                key:    localUsedKey(this.config.ruleId),
                result: used
            });
            return used;
        },

        // ================================================================== //
        // SECONDARY gate — once-per-session display frequency
        // Set ONLY by closePopup() after the popup is dismissed.
        // Uses sessionStorage so it clears when the tab closes.
        // ================================================================== //

        _shownThisSession: function () {
            if (this.config.popupFrequency === 'always') {
                return false;
            }
            var key    = SESSION_SHOWN_KEY + '_' + this.config.ruleId;
            var result = !!sessionStorage.getItem(key);
            dbg('_shownThisSession', 'sessionStorage check', { key: key, result: result });
            return result;
        },

        /**
         * Called ONLY from closePopup() — marks that the popup was shown and
         * dismissed this session so it does not re-trigger while the tab is open.
         * This is NOT a "coupon used" flag — it resets when the tab closes.
         */
        _markShownThisSession: function () {
            if (this.config.popupFrequency === 'always') {
                dbg('_markShownThisSession', 'popupFrequency=always — NOT setting session flag');
                return;
            }
            var key = SESSION_SHOWN_KEY + '_' + this.config.ruleId;
            sessionStorage.setItem(key, '1');
            dbg('_markShownThisSession', '✅ sessionStorage SET (called from closePopup)', { key: key });
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
                        dbg('_bindToPaymentStep', 'payment step visibility changed', { visible: visible });
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
                dbg('_armListeners', 'already attached — skipping');
                return;
            }
            if (this._couponAlreadyUsed()) {
                dbg('_armListeners', 'coupon already used — NOT arming');
                return;
            }
            this._listenersAttached = true;
            dbg('_armListeners', 'attaching listeners', { mobile: this._isMobile() });

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
        // Trigger — NO storage writes here
        // ================================================================== //

        _trigger: function () {
            dbg('_trigger', 'fired');

            if (!this._isPaymentStep()) {
                dbg('_trigger', 'blocked — not on payment step');
                return;
            }
            if (this._couponAlreadyUsed()) {
                dbg('_trigger', 'blocked — coupon already used (primary gate)');
                return;
            }
            if (this._shownThisSession()) {
                dbg('_trigger', 'blocked — already shown this session (secondary gate)');
                return;
            }

            // ✅ No storage writes here — show the popup only.
            dbg('_trigger', '✅ showing popup — no flags set yet');
            this.isVisible(true);
            this._notifyServer();
        },

        _isMobile: function () {
            return window.innerWidth < MOBILE_BREAKPOINT;
        },

        // ================================================================== //
        // Server notification for email
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
        // Keyboard / overlay — NO storage writes here
        // ================================================================== //

        _onKeyDown: function (e) {
            if (e.key === 'Escape' && this.isVisible()) {
                dbg('_onKeyDown', 'ESC pressed — calling closePopup()');
                this.closePopup();
            }
        },

        onOverlayClick: function (data, event) {
            if (this.closeOnOverlayClick &&
                event.target === event.currentTarget) {
                dbg('onOverlayClick', 'overlay clicked — calling closePopup()');
                this.closePopup();
            }
        },

        // ================================================================== //
        // copyCode — the ONLY place that sets permanent suppression
        // ================================================================== //

        copyCode: function () {
            var self = this;
            var code = this.config.couponCode;

            dbg('copyCode', 'called', { code: code });

            if (!code) {
                return;
            }

            var onCopied = function () {
                self._showCopiedConfirmation();
                self._persistCouponUsed(); // ← permanent flag set HERE only
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code)
                    .then(onCopied)
                    .catch(function () { self._fallbackCopy(onCopied); });
            } else {
                this._fallbackCopy(onCopied);
            }
        },

        _fallbackCopy: function (onCopied) {
            var el = document.querySelector('.exit-intent-coupon-code');
            if (!el) {
                return;
            }
            el.select();
            try {
                document.execCommand('copy');
                onCopied();
            } catch (e) {
                dbg('_fallbackCopy', 'execCommand failed', e);
            }
        },

        /**
         * Permanent suppression — called ONLY from copyCode() after a
         * successful clipboard write.
         *
         * Logged-in : POST to DB via MarkUsed controller.
         * Guest      : localStorage key scoped to ruleId.
         *
         * This is the ONLY function that writes a permanent suppression flag.
         */
        _persistCouponUsed: function () {
            if (this.config.isLoggedIn) {
                dbg('_persistCouponUsed', '✅ logged-in: POSTing to MarkUsed controller');
                fetch(window.BASE_URL + 'ict-exitintentdiscountpopup/index/markused', {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type':     'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ form_key: window.FORM_KEY || '' })
                }).catch(function () { /* silent */ });
            } else {
                var key = localUsedKey(this.config.ruleId);
                localStorage.setItem(key, 'true');
                dbg('_persistCouponUsed', '✅ guest: localStorage SET', { key: key });
            }
        },

        _showCopiedConfirmation: function () {
            var self = this;
            this.isCopied(true);
            setTimeout(function () {
                self.isCopied(false);
            }, COPY_RESET_MS);
        },

        // ================================================================== //
        // closePopup — sets ONLY the session-frequency flag, nothing permanent
        // ================================================================== //

        /**
         * Called by: X button, ESC key, overlay click, placeOrderNow().
         *
         * Sets the session-frequency flag (secondary gate) so the popup does
         * not re-trigger while this tab is open — but ONLY when
         * popupFrequency = 'once_per_session'. If popupFrequency = 'always',
         * no flag is set at all and the popup can re-trigger immediately.
         *
         * Does NOT set any permanent/localStorage/DB flag.
         */
        closePopup: function () {
            dbg('closePopup', 'called — setting session flag only (no permanent flag)');
            this.isVisible(false);
            this.isCopied(false);
            this._markShownThisSession(); // sessionStorage only, clears on tab close
        },

        placeOrderNow: function () {
            dbg('placeOrderNow', 'called — delegating to closePopup()');
            this.closePopup();
        }
    });
});
