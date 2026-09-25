@extends('frontend.layout')
@section('content')
<main>
    <div class="login-container">
        <div class="login-page" id="loginBox">
            <div class="login-page__wrap">
                <h1>{{ __('Şəxsi kabinet') }}</h1>
                <form class="login-page__wrap__form" id="customerLoginForm">
                    @csrf
                    <input type="tel" id="loginMobile" placeholder="994 __ ___ __ __" autocomplete="tel" inputmode="numeric" />
                    <div id="passwordArea" class="hide-form">
                        <input type="password" id="loginPassword" placeholder="{{ __('Şifrə') }}" autocomplete="current-password" />
                    </div>
                    <p class="error-message hide-form" id="loginError"></p>
                    <div class="actions hide-form" id="passwordActions">
                        <button type="submit" id="loginSubmitBtn">{{ __('Daxil ol') }}</button>
                    </div>
                </form>
                <div class="actions login-register-link">
                    <button type="button" id="registerBtn">{{ __('Qeydiyyat') }}</button>
                </div>
            </div>
        </div>

        <div id="otpSection" class="hide-form">
            <div class="otpSection-wrapper">
                <div class="headers">
                    <h1>{{ __('Hesabı təsdiqlə') }}</h1>
                    <span class="otp-sent-message" id="otpMessage"></span>
                    <input type="text" id="otpInput" inputmode="numeric" maxlength="6" placeholder="{{ __('OTP kodu') }}" />
                    <p class="error-message hide-form" id="otpError"></p>
                </div>
                <div class="otp-section-form-label">
                    <span class="seconds" id="otpTimer">01:00</span>
                    <button type="button" class="send-again-text" id="resendOtp" disabled>{{ __('Yenidən göndər') }}</button>
                </div>
                <div class="actions">
                    <button type="button" id="backBtn">{{ __('Geriyə') }}</button>
                    <button type="button" id="otpSubmitBtn">{{ __('Təsdiqlə') }}</button>
                </div>
            </div>
        </div>

        <div id="setPasswordSection" class="hide-form">
            <div class="otpSection-wrapper">
                <div class="headers">
                    <h1>{{ __('Şifrə təyin et') }}</h1>
                    <input type="password" id="newPassword" placeholder="{{ __('Yeni şifrə') }}" autocomplete="new-password" />
                    <input type="password" id="newPasswordConfirmation" placeholder="{{ __('Şifrənin təkrarı') }}" autocomplete="new-password" />
                    <p class="error-message hide-form" id="passwordError"></p>
                </div>
                <div class="actions">
                    <button type="button" id="setPasswordBtn">{{ __('Şifrəni yadda saxla') }}</button>
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
    registerUrl: '#',
    messages: {
        notFound: @json(__('Bu nömrə ilə hesab tapılmadı. Qeydiyyatdan keçin.')),
        otpSent: @json(__('OTP kod :mobile nömrəsinə göndərildi.')),
        error: @json(__('Xəta baş verdi.')),
        registration: @json(__('Qeydiyyat səhifəsini növbəti mərhələdə quracağıq.'))
    }
};
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.9/jquery.inputmask.min.js"></script>
<script src="{{ asset('frontend/js/login.js?v=' . filemtime(public_path('frontend/js/login.js'))) }}"></script>
@endsection
