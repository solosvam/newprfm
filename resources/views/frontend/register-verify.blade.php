@extends('frontend.layouts.app')

@section('title', __('auth_verify_account') . ' | parfumshop')

@section('content')
    <main>
        <div class="auth-page">
            <div class="auth-card">
                <h1 class="auth-title">{{ __('auth_verify_account') }}</h1>
                <p class="auth-subtext">{{ __('auth_an_otp_was_sent_to_mobile') }} {{ $maskedMobile }}</p>

                @if(session('success'))
                    <p class="auth-subtext">{{ session('success') }}</p>
                @endif

                @if($errors->has('otp'))
                    <p class="auth-error">{{ $errors->first('otp') }}</p>
                @endif

                <form method="POST" action="{{ route('front.register.verify.store') }}" class="auth-form">
                    @csrf
                    <div class="form-field">
                        <label for="registerOtp">{{ __('auth_otp_code') }}</label>
                        <input id="registerOtp" type="text" name="otp"
                               class="auth-input auth-otp-input @error('otp') is-invalid @enderror"
                               inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6"
                               autocomplete="one-time-code" required autofocus>
                        @error('otp')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-dark auth-submit">{{ __('auth_confirm') }}</button>
                </form>

                <form method="POST" action="{{ route('front.register.resend') }}" class="auth-form">
                    @csrf
                    <button type="submit" class="auth-register-btn">{{ __('auth_resend_code') }}</button>
                </form>
            </div>
        </div>
    </main>
@endsection
