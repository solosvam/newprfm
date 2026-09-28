@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/auth.css') }}">
@endsection

@section('title', __('auth_register') . ' | parfumshop')

@section('content')
    <main>
        <div class="auth-page">
            <div class="auth-card">
                <h1 class="auth-title">{{ __('auth_register') }}</h1>

                @if(session()->has('register.customer_id'))
                    <p class="auth-subtext"><a href="{{ route('front.register.verify') }}">{{ __('auth_verify_account') }}</a></p>
                @endif

                @if($errors->has('otp'))
                    <p class="auth-error">{{ $errors->first('otp') }}</p>
                @endif

                <form method="POST" action="{{ route('front.register.store') }}" class="auth-form">
                    @csrf

                    <div class="form-field">
                        <label>{{ __('profile_first_name') }}</label>
                        <input type="text" name="name" class="auth-input @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="{{ __('profile_first_name') }}" required>
                        <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                    </div>

                    <div class="form-field">
                        <label>{{ __('profile_last_name') }}</label>
                        <input type="text" name="surname" class="auth-input @error('surname') is-invalid @enderror" value="{{ old('surname') }}" placeholder="{{ __('profile_last_name') }}" required>
                        <div class="invalid-feedback">{{ $errors->first('surname') }}</div>
                    </div>

                    <div class="form-field">
                        <label>{{ __('profile_your_email') }}</label>
                        <input type="email" name="email" class="auth-input @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="{{ __('profile_your_email') }}" required>
                        <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                    </div>

                    <div class="form-field">
                        <label>{{ __('profile_your_phone_number') }}</label>
                        <input type="tel" name="mobile" id="registerMobile" class="auth-input @error('mobile') is-invalid @enderror" value="{{ old('mobile') }}" placeholder="994 __ ___ __ __" inputmode="numeric" required>
                        <div class="invalid-feedback">{{ $errors->first('mobile') }}</div>
                    </div>

                    <div class="form-field">
                        <label>{{ __('profile_gender') }}</label>
                        <div class="gender-pills">
                            @foreach([1 => __('profile_male'), 0 => __('profile_female')] as $value => $label)
                                <label class="gender-pill">
                                    <input type="radio" name="gender" value="{{ $value }}" @checked(old('gender') !== null && (int) old('gender') === $value)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="invalid-feedback">{{ $errors->first('gender') }}</div>
                    </div>

                    <div class="form-field">
                        <label>{{ __('auth_password') }}</label>
                        <input type="password" name="password" class="auth-input @error('password') is-invalid @enderror" placeholder="{{ __('auth_password') }}" autocomplete="new-password" minlength="6" required>
                        <div class="invalid-feedback">{{ $errors->first('password') }}</div>
                    </div>

                    <div class="form-field">
                        <label>{{ __('auth_confirm_password') }}</label>
                        <input type="password" name="password_confirmation" class="auth-input" placeholder="{{ __('auth_confirm_password') }}" autocomplete="new-password" minlength="6" required>
                    </div>

                    <button type="submit" class="btn btn-dark auth-submit">{{ __('auth_register') }}</button>
                </form>

                <div class="auth-register">
                    <span class="auth-register-hint">{{ __('auth_already_have_account') }}</span>
                    <a href="{{ route('front.login') }}" class="auth-register-btn">{{ __('auth_sign_in') }}</a>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/vendor/jquery.inputmask.min.js') }}" defer></script>
    <script src="{{ asset_v('frontend/js/pages/register.js') }}" defer></script>
@endsection
