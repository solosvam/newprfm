document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value;
    const form = document.getElementById('customerLoginForm');
    if (!form) return;

    const mobile = document.getElementById('loginMobile');
    const passwordArea = document.getElementById('passwordArea');
    const password = document.getElementById('loginPassword');
    const loginError = document.getElementById('loginError');
    const submit = document.getElementById('loginSubmitBtn');
    const loginBox = document.getElementById('loginBox');
    const otpSection = document.getElementById('otpSection');
    const otpInput = document.getElementById('otpInput');
    const otpError = document.getElementById('otpError');
    const otpMessage = document.getElementById('otpMessage');
    const setPasswordSection = document.getElementById('setPasswordSection');
    let mode = 'check';
    let currentMobile = '';
    let timer = null;

    async function post(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: JSON.stringify(data)
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = json.errors || {};
            throw new Error(Object.values(errors)[0]?.[0] || json.message || 'Xəta baş verdi.');
        }
        return json;
    }

    function error(el, message) {
        el.textContent = message;
        el.classList.toggle('hide-form', !message);
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        error(loginError, '');
        submit.disabled = true;

        try {
            if (mode === 'password') {
                const data = await post(window.customerAuth.passwordUrl, {mobile: currentMobile, password: password.value});
                location.href = data.redirect;
                return;
            }

            currentMobile = mobile.value;
            const data = await post(window.customerAuth.checkUrl, {mobile: currentMobile});

            if (data.status === 'not_found') {
                error(loginError, 'Bu nömrə ilə hesab tapılmadı. Qeydiyyatdan keçin.');
            } else if (data.status === 'password') {
                mode = 'password';
                passwordArea.classList.remove('hide-form');
                password.focus();
                submit.textContent = 'Daxil ol';
            } else if (data.status === 'otp') {
                loginBox.classList.add('hide-form');
                otpSection.classList.remove('hide-form');
                otpMessage.textContent = 'OTP kod ' + data.mobile + ' nömrəsinə göndərildi.';
                startTimer();
                otpInput.focus();
            }
        } catch (e) {
            error(loginError, e.message);
        } finally {
            submit.disabled = false;
        }
    });

    document.getElementById('otpSubmitBtn').addEventListener('click', async function () {
        error(otpError, '');
        try {
            const data = await post(window.customerAuth.otpUrl, {mobile: currentMobile, otp: otpInput.value});
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
            const data = await post(window.customerAuth.setPasswordUrl, {
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
        loginBox.classList.remove('hide-form');
        mode = 'check';
        passwordArea.classList.add('hide-form');
        password.value = '';
        clearInterval(timer);
    });

    document.getElementById('resendOtp').addEventListener('click', async function () {
        this.disabled = true;
        error(otpError, '');
        try {
            await post(window.customerAuth.resendUrl, {mobile: currentMobile});
            startTimer();
        } catch (e) {
            error(otpError, e.message);
        }
    });

    document.getElementById('registerBtn').addEventListener('click', function (e) {
        e.preventDefault();
        error(loginError, 'Qeydiyyat səhifəsini növbəti mərhələdə quracağıq.');
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
