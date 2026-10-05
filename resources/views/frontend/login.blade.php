@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/auth.css') }}">
@endsection

@section('content')
    <main>
        <div class="auth-page login-container">
            <div class="login-page auth-card" id="loginBox">
                <div class="login-page__wrap">
                    <h1 class="auth-title">{{ __('auth_my_account') }}</h1>

                    <form class="login-page__wrap__form auth-form" id="customerLoginForm">
                        @csrf
                        <input type="tel" id="loginMobile" class="auth-input" placeholder="994 __ ___ __ __" autocomplete="tel" inputmode="numeric" />

                        <div id="passwordArea" class="hide-form">
                            <input type="password" id="loginPassword" class="auth-input" placeholder="{{ __('auth_password') }}" autocomplete="current-password" />
                        </div>

                        <div id="inactiveOtpArea" class="auth-inactive-otp hide-form">
                            <p class="auth-subtext auth-inactive-otp__message" id="inactiveOtpMessage"></p>
                            <label for="inactiveOtpInput" class="auth-inactive-otp__label">{{ __('auth_otp_code') }}</label>
                            <input type="text" id="inactiveOtpInput" class="auth-input auth-otp-input"
                                   inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6"
                                   autocomplete="one-time-code" placeholder="000000" />
                            <p class="error-message auth-error hide-form" id="inactiveOtpError"></p>
                            <button type="button" id="inactiveVerifyBtn" class="btn btn-dark auth-submit auth-inactive-otp__verify">{{ __('auth_confirm') }}</button>
                            <button type="button" id="inactiveResendBtn" class="btn btn-outline auth-submit auth-inactive-otp__resend">{{ __('auth_resend_code') }}</button>
                            <p id="inactiveResendMessage" class="auth-subtext auth-inactive-otp__resend-message"></p>
                        </div>

                        <p class="error-message auth-error hide-form" id="loginError"></p>

                        <div class="actions hide-form" id="passwordActions">
                            <button type="submit" id="loginSubmitBtn" class="btn btn-dark auth-submit">{{ __('auth_sign_in') }}</button>
                        </div>
                    </form>

                    <div class="actions login-register-link auth-register">
                        <span class="auth-register-hint">{{ __('auth_dont_have_account_yet') }}</span>
                        <a href="{{ route('front.register') }}" id="registerBtn" class="auth-register-btn">{{ __('auth_register') }}</a>
                    </div>
                </div>
            </div>

            <div id="otpSection" class="hide-form auth-card">
                <div class="otpSection-wrapper">
                    <div class="headers">
                        <h1 class="auth-title">{{ __('auth_verify_account') }}</h1>
                        <span class="otp-sent-message auth-subtext" id="otpMessage"></span>
                        <input type="text" id="otpInput" class="auth-input auth-otp-input" inputmode="numeric" maxlength="6" placeholder="{{ __('auth_otp_code') }}" />
                        <p class="error-message auth-error hide-form" id="otpError"></p>
                    </div>

                    <div class="otp-section-form-label auth-otp-meta">
                        <span class="seconds" id="otpTimer">01:00</span>
                        <button type="button" class="send-again-text auth-link" id="resendOtp" disabled>{{ __('auth_resend_code') }}</button>
                    </div>

                    <div class="actions auth-actions-row">
                        <button type="button" id="backBtn" class="btn btn-outline">{{ __('auth_back') }}</button>
                        <button type="button" id="otpSubmitBtn" class="btn btn-dark">{{ __('auth_confirm') }}</button>
                    </div>
                </div>
            </div>

            <div id="setPasswordSection" class="hide-form auth-card">
                <div class="otpSection-wrapper">
                    <div class="headers">
                        <h1 class="auth-title">{{ __('auth_set_password') }}</h1>
                        <input type="password" id="newPassword" class="auth-input" placeholder="{{ __('auth_new_password') }}" autocomplete="new-password" />
                        <input type="password" id="newPasswordConfirmation" class="auth-input" placeholder="{{ __('auth_confirm_password') }}" autocomplete="new-password" />
                        <p class="error-message auth-error hide-form" id="passwordError"></p>
                    </div>

                    <div class="actions">
                        <button type="button" id="setPasswordBtn" class="btn btn-dark auth-submit">{{ __('auth_save_password') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('page-scripts')
    @php
        $loginConfig = [
            'checkUrl' => route('front.login.check'),
            'passwordUrl' => route('front.login.password'),
            'inactiveVerifyUrl' => route('front.login.inactive.verify'),
            'inactiveResendUrl' => route('front.login.inactive.resend'),
            'otpUrl' => route('front.login.otp'),
            'resendUrl' => route('front.login.otp.resend'),
            'setPasswordUrl' => route('front.login.set-password'),
            'registerUrl' => '#',
            // Qeydiyyat formundan yönləndirmə: nömrə hazır gəlir
            'prefillMobile' => preg_match('/^994\\d{9}$/', (string) request('mobile')) ? request('mobile') : null,
            'messages' => [
                'resend' => __('auth_resend_code'),
                'inactiveHint' => __('auth_an_otp_was_sent_to_mobile'),
                'notFound' => __('auth_no_account_found_for_this_number_please_register'),
                'otpSent' => __('auth_an_otp_was_sent_to_mobile'),
                'error' => __('auth_something_went_wrong'),
                'registration' => __('auth_registration_will_be_available_soon'),
            ],
        ];
    @endphp
    <script type="application/json" id="login-config">@json($loginConfig)</script>
    <script src="{{ asset_v('frontend/js/vendor/jquery.inputmask.min.js') }}" defer></script>
    <script src="{{ asset_v('frontend/js/login.js') }}"></script>
@endsection
