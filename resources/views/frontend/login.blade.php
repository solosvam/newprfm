@extends('frontend.layout')
@section('content')
<main>
    <div class="login-container">
        <div class="login-page" id="loginBox">
            <div class="login-page__wrap">
                <h1>Şəxsi kabinet</h1>
                <form class="login-page__wrap__form" id="customerLoginForm">
                    @csrf
                    <input type="tel" id="loginMobile" placeholder="994 __ ___ __ __" autocomplete="tel" inputmode="numeric" />
                    <div id="passwordArea" class="hide-form">
                        <input type="password" id="loginPassword" placeholder="Şifrə" autocomplete="current-password" />
                    </div>
                    <p class="error-message hide-form" id="loginError"></p>
                    <div class="actions hide-form" id="passwordActions">
                        <button type="submit" id="loginSubmitBtn">Daxil ol</button>
                    </div>
                </form>
                <div class="actions login-register-link">
                    <button type="button" id="registerBtn">Qeydiyyat</button>
                </div>
            </div>
        </div>

        <div id="otpSection" class="hide-form">
            <div class="otpSection-wrapper">
                <div class="headers">
                    <h1>Hesabı təsdiqlə</h1>
                    <span class="otp-sent-message" id="otpMessage"></span>
                    <input type="text" id="otpInput" inputmode="numeric" maxlength="6" placeholder="OTP kodu" />
                    <p class="error-message hide-form" id="otpError"></p>
                </div>
                <div class="otp-section-form-label">
                    <span class="seconds" id="otpTimer">01:00</span>
                    <button type="button" class="send-again-text" id="resendOtp" disabled>Yenidən göndər</button>
                </div>
                <div class="actions">
                    <button type="button" id="backBtn">Geriyə</button>
                    <button type="button" id="otpSubmitBtn">Təsdiqlə</button>
                </div>
            </div>
        </div>

        <div id="setPasswordSection" class="hide-form">
            <div class="otpSection-wrapper">
                <div class="headers">
                    <h1>Şifrə təyin et</h1>
                    <input type="password" id="newPassword" placeholder="Yeni şifrə" autocomplete="new-password" />
                    <input type="password" id="newPasswordConfirmation" placeholder="Şifrənin təkrarı" autocomplete="new-password" />
                    <p class="error-message hide-form" id="passwordError"></p>
                </div>
                <div class="actions">
                    <button type="button" id="setPasswordBtn">Şifrəni yadda saxla</button>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
@section('page-scripts')
<script>
window.customerAuth = {
    checkUrl: @json(route('front.login.check')),
    passwordUrl: @json(route('front.login.password')),
    otpUrl: @json(route('front.login.otp')),
    resendUrl: @json(route('front.login.otp.resend')),
    setPasswordUrl: @json(route('front.login.set-password')),
    registerUrl: '#'
};
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.9/jquery.inputmask.min.js"></script>
<script src="{{ asset('frontend/js/login.js?v=' . filemtime(public_path('frontend/js/login.js'))) }}"></script>
@endsection
