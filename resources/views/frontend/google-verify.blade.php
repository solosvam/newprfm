@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/auth.css') }}">
@endsection

@section('title', __('auth_verify_account') . ' | ParfumShop')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <main>
        <div class="auth-page">
            <div class="auth-card">
                <h1 class="auth-title">{{ __('auth_verify_account') }}</h1>
                @if($reason === 'email')
                    <p class="auth-subtext">{{ __('auth_google_link_by_email') }}</p>
                @elseif($reason === 'mobile')
                    <p class="auth-subtext">{{ __('auth_google_link_by_mobile') }}</p>
                @endif
                <p class="auth-subtext">{{ __('auth_an_otp_was_sent_to_mobile', ['mobile' => $maskedMobile]) }}</p>

                @if(session('success'))
                    <p class="auth-subtext">{{ session('success') }}</p>
                @endif

                <form method="POST" action="{{ route('auth.google.verify.store') }}" class="auth-form">
                    @csrf
                    <div class="form-field">
                        <label for="googleOtp">{{ __('auth_otp_code') }}</label>
                        <input id="googleOtp" type="text" name="otp"
                               class="auth-input auth-otp-input @error('otp') is-invalid @enderror"
                               inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6"
                               autocomplete="one-time-code" required autofocus>
                        <div class="invalid-feedback">{{ $errors->first('otp') }}</div>
                    </div>
                    <button type="submit" class="btn btn-dark auth-submit">{{ __('auth_confirm') }}</button>
                </form>

                <form method="POST" action="{{ route('auth.google.resend') }}" class="auth-resend-form">
                    @csrf
                    <button type="submit" class="btn btn-outline auth-submit">{{ __('auth_resend_code') }}</button>
                </form>
            </div>
        </div>
    </main>
@endsection
