/**
 * Folder Access OTP Verification (shared)
 * Injects an OTP modal (design matches verify-otp.php) and exposes:
 *   window.folderOTP.guard(url[, callback])
 * which requires the current user to enter a 6-digit code (emailed via
 * api/send-folder-otp.php, validated by api/verify-folder-otp.php) before
 * the browser is redirected to the target folder URL — or, when a callback
 * is supplied instead of a URL, the callback is invoked after verification
 * (used to gate downloads without navigating away).
 */
(function () {
    if (window.folderOTP) return;

    var pendingUrl = null;
    var pendingCallback = null;
    var verifying = false;
    var otpEnd = 0;
    var timerInt = null;
    var customTitle = null;
    var customPurpose = null;

    var modal = null;
    var backdrop = null, closeBtn = null, cancelBtn = null;
    var resendBtn = null, verifyBtn = null;
    var sendWrap = null, formWrap = null;
    var statusEl = null, timerEl = null, maskedEl = null;
    var digits = [], hidden = null;

    function syncHidden() {
        if (hidden) hidden.value = digits.map(function (d) { return d.value.replace(/[^0-9]/g, ''); }).join('');
    }

    function clearStatus() {
        if (!statusEl) return;
        statusEl.className = 'hidden mb-4';
        statusEl.innerHTML = '';
    }

    function setStatus(msg, type) {
        if (!statusEl) return;
        var isErr = type === 'error';
        statusEl.className = 'mb-4 p-3 rounded-lg text-sm ' + (isErr
            ? 'bg-red-100 border border-red-400 text-red-700 dark:bg-red-900/30 dark:border-red-600 dark:text-red-300'
            : 'bg-green-100 border border-green-400 text-green-700 dark:bg-green-900/30 dark:border-green-600 dark:text-green-300');
        statusEl.innerHTML = msg;
    }

    function shake() {
        if (!modal) return;
        var card = modal.querySelector('.folder-otp-card');
        if (!card) return;
        card.classList.remove('folder-otp-shake');
        void card.offsetWidth;
        card.classList.add('folder-otp-shake');
    }

    function stopTimer() {
        if (timerInt) { clearInterval(timerInt); timerInt = null; }
    }

    function startTimer() {
        stopTimer();
        function tick() {
            var remain = Math.max(0, otpEnd - Math.floor(Date.now() / 1000));
            if (timerEl) timerEl.textContent = String(remain);
            if (remain <= 0) {
                stopTimer();
                if (formWrap) formWrap.classList.add('hidden');
                if (sendWrap) sendWrap.classList.remove('hidden');
                setStatus('OTP expired. Click "Resend Code" to get a new one.', 'error');
            } else {
                timerInt = setTimeout(tick, 1000);
            }
        }
        tick();
    }

    function resetModal() {
        stopTimer();
        digits.forEach(function (d) { d.value = ''; });
        syncHidden();
        clearStatus();
        if (maskedEl) maskedEl.textContent = 'your email';
        if (sendWrap) sendWrap.classList.remove('hidden');
        if (formWrap) formWrap.classList.add('hidden');
        if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'Verify Code'; }
    }

    function requestOtp() {
        resetModal();
        clearStatus();
        fetch('api/send-folder-otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ purpose: customPurpose || '' })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (sendWrap) sendWrap.classList.add('hidden');
                if (!data || !data.success) {
                    if (formWrap) formWrap.classList.add('hidden');
                    setStatus((data && data.error) ? data.error : 'Could not send the code. Please try again.', 'error');
                    return;
                }
                if (maskedEl && data.masked_email) maskedEl.textContent = data.masked_email;
                otpEnd = Math.floor(Date.now() / 1000) + (data.expires_in || 180);
                if (formWrap) formWrap.classList.remove('hidden');
                if (data.sent) {
                    setStatus('✓ Code sent to your email', 'success');
                } else if (data.fallback_otp) {
                    setStatus('⚠️ Email send failed. Use code: <strong>' + data.fallback_otp + '</strong>', 'error');
                }
                startTimer();
                if (digits[0]) digits[0].focus();
            })
            .catch(function () {
                if (sendWrap) sendWrap.classList.add('hidden');
                if (formWrap) formWrap.classList.add('hidden');
                setStatus('Could not reach the server. Please try again.', 'error');
            });
    }

    function openModal() {
        if (!modal) build();
        var titleEl = document.getElementById('folder-otp-title');
        if (titleEl && customTitle) titleEl.textContent = customTitle;
        resetModal();
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        requestOtp();
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        stopTimer();
        clearStatus();
        pendingCallback = null;
        customTitle = null;
        customPurpose = null;
    }

    function verify() {
        if (verifying) return;
        var code = hidden ? hidden.value : '';
        if (!code || code.length !== 6) {
            setStatus('Please enter the 6-digit code.', 'error');
            shake();
            return;
        }
        verifying = true;
        if (verifyBtn) { verifyBtn.disabled = true; verifyBtn.textContent = 'Verifying…'; }
        fetch('api/verify-folder-otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ otp: code })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                verifying = false;
                if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'Verify Code'; }
                if (data && data.success) {
                    stopTimer();
                    var url = pendingUrl;
                    var cb = pendingCallback;
                    pendingCallback = null;
                    closeModal();
                    if (cb) cb();
                    else if (url) window.location.href = url;
                } else {
                    setStatus((data && data.error) ? data.error : 'Invalid code. Please try again.', 'error');
                    shake();
                    digits.forEach(function (d) { d.value = ''; });
                    syncHidden();
                    if (digits[0]) digits[0].focus();
                }
            })
            .catch(function () {
                verifying = false;
                if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'Verify Code'; }
                setStatus('Could not reach the server. Please try again.', 'error');
            });
    }

    var DIGIT_CLS = 'folder-otp-digit w-12 sm:w-14 h-12 sm:h-14 text-center text-2xl font-bold border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 transition-colors';

    function build() {
        var holder = document.createElement('div');
        holder.innerHTML =
            '<style>' +
            '@keyframes folder-otp-spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}' +
            '@keyframes folder-otp-shake{0%,100%{transform:translateX(0)}10%,30%,50%,70%,90%{transform:translateX(-5px)}20%,40%,60%,80%{transform:translateX(5px)}}' +
            '.folder-otp-spinner{border:2px solid rgba(220,38,38,.25);border-top:2px solid #dc2626;border-radius:50%;width:24px;height:24px;animation:folder-otp-spin .8s linear infinite}' +
            '.folder-otp-shake{animation:folder-otp-shake .5s ease-in-out}' +
            '</style>' +
            '<div id="folder-otp-modal" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="folder-otp-title">' +
                '<div id="folder-otp-backdrop" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>' +
                '<div class="folder-otp-card relative w-full max-w-md bg-white/95 dark:bg-slate-800/95 backdrop-blur-lg rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 p-8">' +
                    '<button type="button" id="folder-otp-close" aria-label="Close" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl leading-none">&times;</button>' +
                    '<div class="text-center mb-6">' +
                        '<div class="mx-auto w-16 h-16 rounded-full shadow-lg bg-white flex items-center justify-center mb-3 ring-4 ring-white dark:ring-slate-900">' +
                            '<img src="Images/Val-logo/valenzuela logo.webp" alt="City Government of Valenzuela" class="w-11 h-11 object-contain">' +
                        '</div>' +
                        '<div class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-gray-100">LAS</div>' +
                        '<div class="text-sm text-gray-700 dark:text-gray-300">Legislative Archive System</div>' +
                        '<div class="text-xs font-semibold text-red-600 dark:text-red-400">City Government of Valenzuela</div>' +
                    '</div>' +
                    '<div class="mb-6">' +
                        '<div id="folder-otp-title" class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Verify Folder Access</div>' +
                        '<div class="text-sm text-gray-600 dark:text-gray-400">Enter the 6-digit code sent to</div>' +
                        '<div id="folder-otp-masked" class="text-sm font-semibold text-gray-800 dark:text-gray-200">your email</div>' +
                    '</div>' +
                    '<div id="folder-otp-status" class="hidden mb-4"></div>' +
                    '<div id="folder-otp-send-wrap" class="text-center py-6">' +
                        '<div class="folder-otp-spinner mx-auto mb-3"></div>' +
                        '<div class="text-sm text-gray-600 dark:text-gray-400">Sending code to your email…</div>' +
                    '</div>' +
                    '<div id="folder-otp-form-wrap" class="hidden">' +
                        '<div class="text-xs text-amber-600 dark:text-amber-400 mb-3">⏱️ Expires in <span id="folder-otp-timer" class="font-bold">--</span>s</div>' +
                        '<div class="flex items-center justify-between gap-2 sm:gap-3">' +
                            '<input type="text" class="' + DIGIT_CLS + '" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="OTP digit 1" placeholder="0">' +
                            '<input type="text" class="' + DIGIT_CLS + '" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="OTP digit 2" placeholder="0">' +
                            '<input type="text" class="' + DIGIT_CLS + '" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="OTP digit 3" placeholder="0">' +
                            '<input type="text" class="' + DIGIT_CLS + '" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="OTP digit 4" placeholder="0">' +
                            '<input type="text" class="' + DIGIT_CLS + '" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="OTP digit 5" placeholder="0">' +
                            '<input type="text" class="' + DIGIT_CLS + '" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="OTP digit 6" placeholder="0">' +
                        '</div>' +
                        '<input type="hidden" id="folder-otp-hidden" minlength="6" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code">' +
                        '<div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Tip: paste the full code, it will fill automatically.</div>' +
                        '<button type="button" id="folder-otp-verify-btn" class="w-full bg-red-600 hover:bg-red-700 text-white py-3.5 px-6 rounded-xl font-bold text-lg transition-all duration-200 shadow-lg hover:shadow-2xl mt-5">Verify Code</button>' +
                        '<button type="button" id="folder-otp-resend" class="block w-full text-center text-sm text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 font-semibold py-2 mt-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors">↻ Resend Code</button>' +
                    '</div>' +
                    '<button type="button" id="folder-otp-cancel" class="block w-full text-center text-sm text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 font-semibold py-2 mt-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors">← Cancel</button>' +
                '</div>' +
            '</div>';

        // Append style + modal to body
        var styleEl = holder.querySelector('style');
        document.head.appendChild(styleEl);
        modal = holder.querySelector('#folder-otp-modal');
        document.body.appendChild(modal);

        backdrop = document.getElementById('folder-otp-backdrop');
        closeBtn = document.getElementById('folder-otp-close');
        cancelBtn = document.getElementById('folder-otp-cancel');
        resendBtn = document.getElementById('folder-otp-resend');
        verifyBtn = document.getElementById('folder-otp-verify-btn');
        sendWrap = document.getElementById('folder-otp-send-wrap');
        formWrap = document.getElementById('folder-otp-form-wrap');
        statusEl = document.getElementById('folder-otp-status');
        timerEl = document.getElementById('folder-otp-timer');
        maskedEl = document.getElementById('folder-otp-masked');
        hidden = document.getElementById('folder-otp-hidden');
        digits = Array.prototype.slice.call(modal.querySelectorAll('.folder-otp-digit'));

        digits.forEach(function (input, idx) {
            input.addEventListener('input', function () {
                var val = input.value.replace(/[^0-9]/g, '');
                input.value = val.slice(0, 1);
                if (val.length > 1) {
                    var chars = val.split('');
                    for (var i = 0; i < chars.length && (idx + i) < digits.length; i++) {
                        digits[idx + i].value = chars[i];
                    }
                    var nextIdx = Math.min(idx + chars.length, digits.length - 1);
                    digits[nextIdx].focus();
                } else if (val && digits[idx + 1]) {
                    digits[idx + 1].focus();
                }
                syncHidden();
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !input.value && digits[idx - 1]) {
                    digits[idx - 1].focus();
                }
                if (e.key === 'Enter') { e.preventDefault(); verify(); }
            });
            input.addEventListener('paste', function (e) {
                var text = (e.clipboardData || window.clipboardData).getData('text');
                if (!text) return;
                var cleaned = text.replace(/[^0-9]/g, '').slice(0, digits.length);
                if (!cleaned) return;
                e.preventDefault();
                cleaned.split('').forEach(function (ch, i) {
                    if (digits[i]) digits[i].value = ch;
                });
                digits[Math.min(cleaned.length, digits.length) - 1].focus();
                syncHidden();
            });
        });
        syncHidden();

        if (verifyBtn) verifyBtn.addEventListener('click', verify);
        if (resendBtn) resendBtn.addEventListener('click', function () { resetModal(); requestOtp(); });
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (backdrop) backdrop.addEventListener('click', closeModal);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });
    }

    window.folderOTP = {
        guard: function (url, callback, options) {
            pendingUrl = url;
            pendingCallback = callback;
            customTitle = (options && options.title) || null;
            customPurpose = (options && options.purpose) || null;
            openModal();
        }
    };
})();
