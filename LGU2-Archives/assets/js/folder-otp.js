/**
 * Folder Access Password Verification (shared)
 * Injects a password modal and exposes:
 *   window.folderOTP.guard(url[, callback])
 * which requires the current user to enter their account password before
 * the browser is redirected to the target folder URL — or, when a callback
 * is supplied instead of a URL, the callback is invoked after verification.
 */
(function () {
    if (window.folderOTP) return;

    var pendingUrl = null;
    var pendingCallback = null;
    var verifying = false;

    var modal = null;
    var backdrop = null, closeBtn = null, cancelBtn = null;
    var verifyBtn = null;
    var statusEl = null, passwordInput = null, toggleBtn = null;

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

    function resetModal() {
        if (passwordInput) { passwordInput.value = ''; passwordInput.type = 'password'; }
        clearStatus();
        if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'Verify'; }
        if (toggleBtn) {
            var eyeOpen = toggleBtn.querySelector('.eye-open');
            var eyeClosed = toggleBtn.querySelector('.eye-closed');
            if (eyeOpen) eyeOpen.classList.remove('hidden');
            if (eyeClosed) eyeClosed.classList.add('hidden');
        }
    }

    function openModal() {
        if (!modal) build();
        resetModal();
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        if (passwordInput) passwordInput.focus();
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        clearStatus();
        pendingCallback = null;
    }

    function verify() {
        if (verifying) return;
        var pwd = passwordInput ? passwordInput.value : '';
        if (!pwd) {
            setStatus('Please enter your password.', 'error');
            shake();
            return;
        }
        verifying = true;
        if (verifyBtn) { verifyBtn.disabled = true; verifyBtn.textContent = 'Verifying…'; }
        fetch('api/send-folder-otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: pwd })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                verifying = false;
                if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'Verify'; }
                if (data && data.success) {
                    var url = pendingUrl;
                    var cb = pendingCallback;
                    pendingCallback = null;
                    closeModal();
                    if (cb) cb();
                    else if (url) window.location.href = url;
                } else {
                    setStatus((data && data.error) ? data.error : 'Incorrect password. Please try again.', 'error');
                    shake();
                    if (passwordInput) { passwordInput.value = ''; passwordInput.focus(); }
                }
            })
            .catch(function () {
                verifying = false;
                if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'Verify'; }
                setStatus('Could not reach the server. Please try again.', 'error');
            });
    }

    function build() {
        var holder = document.createElement('div');
        holder.innerHTML =
            '<style>' +
            '@keyframes folder-otp-shake{0%,100%{transform:translateX(0)}10%,30%,50%,70%,90%{transform:translateX(-5px)}20%,40%,60%,80%{transform:translateX(5px)}}' +
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
                        '<div id="folder-otp-title" class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Folder Access</div>' +
                        '<div class="text-sm text-gray-600 dark:text-gray-400">Enter your account password to access this folder</div>' +
                    '</div>' +
                    '<div id="folder-otp-status" class="hidden mb-4"></div>' +
                    '<div class="space-y-4">' +
                        '<div>' +
                            '<label for="folder-otp-password" class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Password</label>' +
                            '<div class="relative">' +
                                '<span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">' +
                                    '<i class="bi bi-lock text-lg"></i>' +
                                '</span>' +
                                '<input type="password" id="folder-otp-password" placeholder="Enter your password" autocomplete="current-password" class="w-full pl-11 pr-12 py-3 border border-gray-300 dark:border-slate-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 transition-colors">' +
                                '<button type="button" id="folder-otp-toggle-pwd" class="absolute right-0 top-0 h-full flex items-center px-4 text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 focus:outline-none transition-colors" aria-label="Toggle password visibility">' +
                                    '<i class="bi bi-eye text-lg eye-open"></i>' +
                                    '<i class="bi bi-eye-slash text-lg eye-closed hidden"></i>' +
                                '</button>' +
                            '</div>' +
                        '</div>' +
                        '<button type="button" id="folder-otp-verify-btn" class="w-full bg-red-600 hover:bg-red-700 text-white py-3.5 px-6 rounded-xl font-bold text-lg transition-all duration-200 shadow-lg hover:shadow-2xl">Verify</button>' +
                    '</div>' +
                    '<button type="button" id="folder-otp-cancel" class="block w-full text-center text-sm text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 font-semibold py-2 mt-4 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors">&larr; Cancel</button>' +
                '</div>' +
            '</div>';

        var styleEl = holder.querySelector('style');
        document.head.appendChild(styleEl);
        modal = holder.querySelector('#folder-otp-modal');
        document.body.appendChild(modal);

        backdrop = document.getElementById('folder-otp-backdrop');
        closeBtn = document.getElementById('folder-otp-close');
        cancelBtn = document.getElementById('folder-otp-cancel');
        verifyBtn = document.getElementById('folder-otp-verify-btn');
        statusEl = document.getElementById('folder-otp-status');
        passwordInput = document.getElementById('folder-otp-password');
        toggleBtn = document.getElementById('folder-otp-toggle-pwd');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                if (!passwordInput) return;
                var isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                var eyeOpen = toggleBtn.querySelector('.eye-open');
                var eyeClosed = toggleBtn.querySelector('.eye-closed');
                if (eyeOpen) eyeOpen.classList.toggle('hidden', !isPassword);
                if (eyeClosed) eyeClosed.classList.toggle('hidden', isPassword);
            });
        }

        if (passwordInput) {
            passwordInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); verify(); }
            });
        }

        if (verifyBtn) verifyBtn.addEventListener('click', verify);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (backdrop) backdrop.addEventListener('click', closeModal);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });
    }

    window.folderOTP = {
        guard: function (url, callback) {
            pendingUrl = url;
            pendingCallback = callback;
            openModal();
        }
    };
})();
