document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value;
    const form = document.getElementById('customerLoginForm');
    if (!form) return;

    // Serverdən gələn məlumat: login.blade.php → #login-config
    let auth = { messages: {} };
    try { auth = JSON.parse(document.getElementById('login-config')?.textContent || '{}'); } catch (e) {}

    const mobile = document.getElementById('loginMobile');
    const passwordArea = document.getElementById('passwordArea');
    const password = document.getElementById('loginPassword');
    const loginError = document.getElementById('loginError');
    const submit = document.getElementById('loginSubmitBtn');
    const passwordActions = document.getElementById('passwordActions');
    const loginBox = document.getElementById('loginBox');
    const otpSection = document.getElementById('otpSection');
    const otpInput = document.getElementById('otpInput');
    const otpError = document.getElementById('otpError');
    const otpMessage = document.getElementById('otpMessage');
    const setPasswordSection = document.getElementById('setPasswordSection');
    const inactiveArea = document.getElementById('inactiveOtpArea');
    const inactiveOtp = document.getElementById('inactiveOtpInput');
    const inactiveError = document.getElementById('inactiveOtpError');
    const inactiveResend = document.getElementById('inactiveResendBtn');
    let mode = 'check';
    let currentMobile = '';
    let timer = null;
    let inactiveResendTimer = null;
    let checkingMobile = false;
    let lastCheckedMobile = '';

    Inputmask({
        mask: '\\9\\9\\4 99 999 99 99',
        placeholder: '_',
        showMaskOnHover: false,
        showMaskOnFocus: true,
        clearIncomplete: false
    }).mask(mobile);

    function normalizeForInput(value) {
        let digits = String(value || '').replace(/\D/g, '');

        if (digits.startsWith('994')) {
            digits = digits.substring(3);
        }

        if (digits.startsWith('0')) {
            digits = digits.substring(1);
        }

        return digits.substring(0, 9);
    }

    function fullMobile() {
        const digits = String(mobile.value || '').replace(/\D/g, '');
        if (digits.length === 12 && digits.startsWith('994')) {
            return digits;
        }

        const local = normalizeForInput(digits);
        return local.length === 9 ? '994' + local : '';
    }

    mobile.addEventListener('paste', function (e) {
        e.preventDefault();
        const local = normalizeForInput((e.clipboardData || window.clipboardData).getData('text'));
        mobile.inputmask.setValue('994' + local);
        setTimeout(checkMobileAutomatically, 0);
    });

    function mobileCompleted() {
        const digits = String(mobile.value || '').replace(/\D/g, '');
        return digits.length === 12 && digits.startsWith('994');
    }

    mobile.addEventListener('input', function () {
        if (mode === 'inactive') {
            mode = 'check';
            inactiveArea.classList.add('hide-form');
            clearInterval(inactiveResendTimer);
            inactiveResend.disabled = false;
            inactiveResend.textContent = auth.messages.resend;
            document.getElementById('inactiveResendMessage').textContent = '';
            lastCheckedMobile = '';
        }
        if (mobileCompleted()) checkMobileAutomatically();
    });

    mobile.addEventListener('keyup', function () {
        if (mobileCompleted()) checkMobileAutomatically();
    });

    mobile.addEventListener('blur', function () {
        if (mobileCompleted()) checkMobileAutomatically();
    });

    if (mobile.inputmask) {
        mobile.inputmask.opts.oncomplete = checkMobileAutomatically;
    }

    async function checkMobileAutomatically() {
        if (mode === 'password' || checkingMobile) return;

        const normalized = fullMobile();

        if (normalized.length !== 12) {
            lastCheckedMobile = '';
            error(loginError, '');
            return;
        }

        if (normalized === lastCheckedMobile) return;

        checkingMobile = true;
        lastCheckedMobile = normalized;
        currentMobile = normalized;
        error(loginError, '');

        try {
            const data = await post(auth.checkUrl, {mobile: currentMobile});

            if (data.status === 'not_found') {
                error(loginError, auth.messages.notFound);
            } else if (data.status === 'inactive') {
                mode = 'inactive';
                inactiveArea.classList.remove('hide-form');
                document.getElementById('inactiveOtpMessage').textContent = auth.messages.inactiveHint.replace(':mobile', data.mobile);
                document.getElementById('inactiveResendMessage').textContent = '';
                inactiveResend.disabled = false;
                inactiveResend.textContent = auth.messages.resend;
                document.getElementById('inactiveOtpMessage').dataset.maskedMobile = data.mobile;
                inactiveOtp.focus();
            } else if (data.status === 'password') {
                mode = 'password';
                mobile.disabled = true;
                passwordArea.classList.remove('hide-form');
                passwordActions.classList.remove('hide-form');
                password.focus();
            } else if (data.status === 'otp') {
                loginBox.style.display = 'none';
                otpSection.classList.remove('hide-form');
                otpSection.style.display = 'block';
                otpMessage.textContent = auth.messages.otpSent.replace(':mobile', data.mobile);
                startTimer();
                requestAnimationFrame(() => otpInput.focus());
            }
        } catch (e) {
            lastCheckedMobile = '';
            error(loginError, e.message);
        } finally {
            checkingMobile = false;
        }
    }

    async function post(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: JSON.stringify(data)
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = json.errors || {};
            throw new Error(Object.values(errors)[0]?.[0] || json.message || auth.messages.error);
        }
        return json;
    }

    function error(el, message) {
        el.textContent = message;
        el.classList.toggle('hide-form', !message);
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (mode !== 'password') return;

        error(loginError, '');
        submit.disabled = true;

        try {
            const data = await post(auth.passwordUrl, {
                mobile: currentMobile,
                password: password.value
            });
            location.href = data.redirect;
        } catch (e) {
            error(loginError, e.message);
        } finally {
            submit.disabled = false;
        }
    });

    document.getElementById('inactiveVerifyBtn').addEventListener('click', async function () {
        error(inactiveError, '');
        this.disabled = true;
        try {
            const data = await post(auth.inactiveVerifyUrl, {
                mobile: currentMobile,
                otp: inactiveOtp.value
            });
            location.href = data.redirect;
        } catch (e) {
            error(inactiveError, e.message);
        } finally {
            this.disabled = false;
        }
    });

    function startInactiveResendTimer(seconds = 60) {
        clearInterval(inactiveResendTimer);
        const deadline = Date.now() + seconds * 1000;
        inactiveResend.disabled = true;

        function update() {
            const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            if (remaining === 0) {
                clearInterval(inactiveResendTimer);
                inactiveResend.disabled = false;
                inactiveResend.textContent = auth.messages.resend;
            } else {
                inactiveResend.textContent = auth.messages.resend + ' (' + remaining + 's)';
            }
        }

        update();
        inactiveResendTimer = setInterval(update, 250);
    }

    inactiveResend.addEventListener('click', async function () {
        this.disabled = true;
        error(inactiveError, '');
        const notice = document.getElementById('inactiveResendMessage');
        notice.textContent = '';
        try {
            await post(auth.inactiveResendUrl, {mobile: currentMobile});
            notice.textContent = auth.messages.otpSent.replace(':mobile', document.getElementById('inactiveOtpMessage').dataset.maskedMobile || currentMobile);
            notice.style.color = '#25834b';
            startInactiveResendTimer();
        } catch (e) {
            error(inactiveError, e.message);
            this.disabled = false;
        }
    });

    document.getElementById('otpSubmitBtn').addEventListener('click', async function () {
        error(otpError, '');
        try {
            const data = await post(auth.otpUrl, {mobile: currentMobile, otp: otpInput.value});
            if (data.status === 'verified') {
                otpSection.classList.add('hide-form');
                setPasswordSection.classList.remove('hide-form');
                document.getElementById('newPassword').focus();
            }
        } catch (e) {
            error(otpError, e.message);
        }
    });

    document.getElementById('setPasswordBtn').addEventListener('click', async function () {
        const passwordError = document.getElementById('passwordError');
        error(passwordError, '');
        try {
            const data = await post(auth.setPasswordUrl, {
                mobile: currentMobile,
                password: document.getElementById('newPassword').value,
                password_confirmation: document.getElementById('newPasswordConfirmation').value
            });
            location.href = data.redirect;
        } catch (e) {
            error(passwordError, e.message);
        }
    });

    document.getElementById('backBtn').addEventListener('click', function () {
        otpSection.classList.add('hide-form');
        otpSection.style.display = 'none';
        loginBox.classList.remove('hide-form');
        loginBox.style.display = '';
        mode = 'check';
        mobile.disabled = false;
        passwordArea.classList.add('hide-form');
        passwordActions.classList.add('hide-form');
        password.value = '';
        lastCheckedMobile = '';
        mobile.focus();
        clearInterval(timer);
    });

    document.getElementById('resendOtp').addEventListener('click', async function () {
        this.disabled = true;
        error(otpError, '');
        try {
            await post(auth.resendUrl, {mobile: currentMobile});
            startTimer();
        } catch (e) {
            error(otpError, e.message);
        }
    });


    function startTimer() {
        clearInterval(timer);
        let seconds = 60;
        const timerEl = document.getElementById('otpTimer');
        const resend = document.getElementById('resendOtp');
        resend.disabled = true;
        timerEl.textContent = '01:00';
        timer = setInterval(function () {
            seconds--;
            timerEl.textContent = '00:' + String(seconds).padStart(2, '0');
            if (seconds <= 0) {
                clearInterval(timer);
                resend.disabled = false;
                timerEl.textContent = '00:00';
            }
        }, 1000);
    }
});
