/**
 * Google Identity Services (GIS) SDK integration.
 *
 * Flow (in order of preference):
 *   1. PRIMARY - An invisible copy of the official "Sign in with Google"
 *      button (rendered via google.accounts.id.renderButton) is overlaid on
 *      top of each .js-google-sso-btn element. Clicking anywhere on the
 *      button hits the official button's iframe, which ALWAYS opens the
 *      Google account chooser popup - unlike google.accounts.id.prompt()
 *      (One Tap), which Google silently suppresses in many contexts.
 *   2. FALLBACK - Keyboard activation (Enter) on the custom button calls
 *      google.accounts.id.prompt(); if One Tap is not displayed we surface
 *      a hint about Authorized JavaScript origins.
 *
 * The id_token (credential) returned by Google is POSTed to google_auth.php
 * for server-side verification.
 *
 * Usage:
 *   <script src="sso_config.php"></script>        (defines window.LAS_GOOGLE_CONFIG)
 *   <script src="assets/js/google-sso.js"></script>
 *   <button class="js-google-sso-btn">Sign in with Google</button>
 *       with an optional spinner: <span class="js-google-sso-spinner hidden">...</span>
 */
(function (global) {
    'use strict';

    var config = (global.LAS_GOOGLE_CONFIG) || { clientId: '', appUrl: '' };
    var gsiInitialized = false;
    var overlayCssInjected = false;

    function showError(msg) {
        if (global.console && global.console.error) {
            global.console.error('[Google SSO]', msg);
        }
        try {
            var banner = document.getElementById('sso-error-banner');
            if (banner) {
                banner.textContent = msg;
                banner.classList.remove('hidden');
                return;
            }
        } catch (e) { /* ignore */ }
        global.alert(msg || 'Google Sign-In failed.');
    }

    function hideError() {
        try {
            var banner = document.getElementById('sso-error-banner');
            if (banner) { banner.classList.add('hidden'); }
        } catch (e) { /* ignore */ }
    }

    function setBusy(busy) {
        var btns = document.querySelectorAll('.js-google-sso-btn');
        var i;
        for (i = 0; i < btns.length; i++) {
            btns[i].style.pointerEvents = busy ? 'none' : '';
            btns[i].style.opacity = busy ? '0.7' : '';
        }
        var spinners = document.querySelectorAll('.js-google-sso-spinner');
        for (i = 0; i < spinners.length; i++) {
            if (busy) {
                spinners[i].classList.remove('hidden');
                spinners[i].classList.add('flex');
            } else {
                spinners[i].classList.add('hidden');
                spinners[i].classList.remove('flex');
            }
        }
    }

    /**
     * Endpoint for google_auth.php.
     * Prefers APP_URL when it is empty or on the same origin as the current
     * page; otherwise falls back to a same-origin relative URL so localhost
     * port mismatches (e.g. APP_URL=http://localhost vs page served on
     * http://localhost:8000) never cause cross-origin 404s.
     */
    function endpointUrl() {
        var pageOrigin = global.location && global.location.origin ? global.location.origin : '';
        var base = (config.appUrl || '').replace(/\/+$/, '');
        if (base !== '' && (pageOrigin === '' || base.indexOf(pageOrigin) === 0)) {
            return base + '/google_auth.php';
        }
        return 'google_auth.php';
    }

    function originHint() {
        try {
            var pageOrigin = global.location.origin || '';
            var base = (config.appUrl || '').replace(/\/+$/, '');
            var origins = [];
            if (base !== '' && origins.indexOf(base) === -1) { origins.push(base); }
            if (pageOrigin !== '' && origins.indexOf(pageOrigin) === -1) { origins.push(pageOrigin); }
            return origins.join(', ');
        } catch (e) {
            return 'this origin';
        }
    }

    function handleCredentialResponse(response) {
        if (!response || !response.credential) {
            showError('Google Sign-In did not return a valid credential.');
            return;
        }
        hideError();
        setBusy(true);

        fetch(endpointUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'credential=' + encodeURIComponent(response.credential)
        })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('Server returned ' + res.status);
            }
            return res.json();
        })
        .then(function (data) {
            if (data && data.success && data.redirect) {
                global.location.href = data.redirect;
                return;
            }
            showError((data && data.error) ? data.error : 'Google Sign-In failed.');
            setBusy(false);
        })
        .catch(function () {
            showError('Network error while contacting the Google Sign-In handler.');
            setBusy(false);
        });
    }

    function loadSdk(cb) {
        if (global.google && global.google.accounts) {
            cb();
            return;
        }
        var s = document.createElement('script');
        s.src = 'https://accounts.google.com/gsi/client';
        s.async = true;
        s.defer = true;
        s.onload = cb;
        s.onerror = function () {
            showError('Failed to load the Google Sign-In SDK. Please try again.');
        };
        document.head.appendChild(s);
    }

    function initializeGsi() {
        if (gsiInitialized) { return; }
        gsiInitialized = true;
        global.google.accounts.id.initialize({
            client_id: config.clientId,
            callback: handleCredentialResponse,
            auto_select: false,
            cancel_on_tap_outside: false,
            ux_mode: 'popup'
        });
    }

    function injectOverlayCss() {
        if (overlayCssInjected) { return; }
        overlayCssInjected = true;
        var css = [
            '.gsi-holder{position:relative;display:block;margin:0;}',
            '.gsi-overlay{position:absolute;top:0;left:0;right:0;bottom:0;z-index:20;overflow:hidden;}',
            '.gsi-overlay iframe{width:100% !important;height:100% !important;min-height:40px;opacity:0;border:0;padding:0;margin:0;}',
            '.js-google-sso-btn.gsi-hover{background-color:rgba(248,250,252,0.9);}',
            '.js-google-sso-btn.gsi-hover{border-color:#ef4444;}'
        ].join('\n');
        var style = document.createElement('style');
        style.type = 'text/css';
        style.id = 'gsi-overlay-css';
        if (style.styleSheet) {
            style.styleSheet.cssText = css;
        } else {
            style.appendChild(document.createTextNode(css));
        }
        document.head.appendChild(style);
    }

    /**
     * Wraps each .js-google-sso-btn in a relative holder and renders the
     * official Google button invisibly on top of it.
     */
    function enableButtonOverlays() {
        var btns = document.querySelectorAll('.js-google-sso-btn');
        if (btns.length === 0) { return; }
        injectOverlayCss();
        initializeGsi();

        var i, btn, holder, overlay;
        for (i = 0; i < btns.length; i++) {
            btn = btns[i];
            if (btn.getAttribute('data-gsi-bound')) { continue; }
            btn.setAttribute('data-gsi-bound', '1');

            holder = document.createElement('div');
            holder.className = 'gsi-holder';
            btn.parentNode.replaceChild(holder, btn);
            holder.appendChild(btn);

            overlay = document.createElement('div');
            overlay.className = 'gsi-overlay';
            holder.appendChild(overlay);

            // Keep the custom button's hover feedback alive even though the
            // transparent overlay sits on top and swallows pointer events.
            (function (b, ov) {
                ov.addEventListener('mouseenter', function () { b.classList.add('gsi-hover'); });
                ov.addEventListener('mouseleave', function () { b.classList.remove('gsi-hover'); });
            })(btn, overlay);
        }

        // Render the official button over the first custom button.
        // (Each page currently has exactly one .js-google-sso-btn.)
        var first = btns[0];
        var firstHolder = first.parentNode;
        var ov = firstHolder.querySelector('.gsi-overlay');
        if (ov) {
            var btnWidth = first.offsetWidth || 300;
            var width = Math.max(120, Math.min(btnWidth, 400));
            global.google.accounts.id.renderButton(ov, {
                type: 'standard',
                theme: 'outline',
                size: 'large',
                text: 'signin_with',
                width: width,
                locale: 'en'
            });
        }
    }

    function promptSignIn() {
        if (!config.clientId) {
            showError('Google Sign-In is not configured yet. Please contact the administrator.');
            return;
        }
        loadSdk(function () {
            initializeGsi();
            try {
                global.google.accounts.id.prompt(function (notification) {
                    if (notification && typeof notification.isNotDisplayed === 'function' && notification.isNotDisplayed()) {
                        showError('Google could not display the sign-in window. Ensure this origin is authorized in the Google Cloud Console (Authorized JavaScript origins): ' + originHint());
                    }
                });
            } catch (e) {
                // If One Tap fails, rely on the overlay button (already rendered).
            }
        });
    }

    // ---------------------------------------------------------------- boot
    function boot() {
        var btns = document.querySelectorAll('.js-google-sso-btn');
        var i;

        if (!config.clientId) {
            // Not configured: keep the custom button usable so the user gets
            // a clear message instead of a silent no-op.
            for (i = 0; i < btns.length; i++) {
                (function (b) {
                    b.addEventListener('click', function (e) {
                        e.preventDefault();
                        showError('Google Sign-In is not configured yet. Please contact the administrator.');
                    });
                })(btns[i]);
            }
            return;
        }

        // Attach the keyboard/accessibility path (mouse clicks are captured by
        // the overlay iframe which always opens the popup).
        for (i = 0; i < btns.length; i++) {
            (function (b) {
                b.addEventListener('click', function (e) {
                    e.preventDefault();
                    promptSignIn();
                });
            })(btns[i]);
        }

        loadSdk(enableButtonOverlays);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    global.LAS_GoogleSSO = {
        init: function (opts) {
            if (opts) { config = opts; }
        },
        signIn: promptSignIn
    };
})(window);
