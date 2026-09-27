@extends('frontend.layouts.app')

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

                        <div id="inactiveOtpArea" class="hide-form" style="margin-top:18px;">
                            <p class="auth-subtext" id="inactiveOtpMessage" style="margin-bottom:16px;"></p>
                            <label for="inactiveOtpInput" style="display:block;margin-bottom:10px;">{{ __('auth_otp_code') }}</label>
                            <input type="text" id="inactiveOtpInput" class="auth-input auth-otp-input"
                                   inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6"
                                   autocomplete="one-time-code" placeholder="000000" />
                            <p class="error-message auth-error hide-form" id="inactiveOtpError"></p>
                            <button type="button" id="inactiveVerifyBtn" class="btn btn-dark auth-submit" style="width:100%;margin-top:18px;">{{ __('auth_confirm') }}</button>
                            <button type="button" id="inactiveResendBtn" class="btn btn-outline auth-submit" style="width:100%;margin-top:12px;">{{ __('auth_resend_code') }}</button>
                            <p id="inactiveResendMessage" class="auth-subtext" style="margin-top:10px;"></p>
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
    <script>
        window.customerAuth = {
            checkUrl: @json(route('front.login.check')),
            passwordUrl: @json(route('front.login.password')),
            inactiveVerifyUrl: @json(route('front.login.inactive.verify')),
            inactiveResendUrl: @json(route('front.login.inactive.resend')),
            otpUrl: @json(route('front.login.otp')),
            resendUrl: @json(route('front.login.otp.resend')),
            setPasswordUrl: @json(route('front.login.set-password')),
            registerUrl: '#',
            messages: {
                resend: @json(__('auth_resend_code')),
                inactiveHint: @json(['az' => 'Hesabınızı təsdiqləmək üçün SMS kodunu daxil edin. Kodunuz yoxdursa, yenisini göndərin.', 'ru' => 'Введите SMS-код для подтверждения аккаунта. Если кода нет, запросите новый.', 'en' => 'Enter the SMS code to verify your account. If you do not have a code, request a new one.'][app()->getLocale()] ?? 'Hesabınızı təsdiqləmək üçün SMS kodunu daxil edin.'),
                notFound: @json(__('auth_no_account_found_for_this_number_please_register')),
                otpSent: @json(__('auth_an_otp_was_sent_to_mobile')),
                error: @json(__('auth_something_went_wrong')),
                registration: @json(__('auth_registration_will_be_available_soon'))
            }
        };
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.9/jquery.inputmask.min.js"></script>
    <script src="{{ asset('frontend/js/login.js') }}?v=20260927-2"></script>
@endsection
