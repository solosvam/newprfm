@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/auth.css') }}">
    <link rel="stylesheet" href="{{ asset_v('frontend/css/components/gender-pills.css') }}">
@endsection

@section('title', __('auth_register') . ' | ParfumSHop')

@if(request()->filled('ref') && ($referralSettings = app(\App\Services\Referral\ReferralSettings::class))->enabled())
    @section('og_title', __('referral_og_title'))
    @section('meta_description', $referralSettings->shareText(app()->getLocale()))
    @section('meta_robots', 'noindex, follow')
    @if($referralSettings->ogImageUrl())
        @section('og_image', $referralSettings->ogImageUrl())
        {{-- şəkil yüklənəndə 1200×630-a kəsilir (ReferralOgImage) --}}
        @section('og_image_width', (string) \App\Services\Referral\ReferralOgImage::WIDTH)
        @section('og_image_height', (string) \App\Services\Referral\ReferralOgImage::HEIGHT)
    @endif
@endif

@section('content')
    <main>
        <div class="auth-page">
            <div class="auth-card auth-card--wide">
                <h1 class="auth-title">{{ __('auth_register') }}</h1>

                <ul class="auth-benefits">
                    @foreach(['auth_benefit_bonus', 'auth_benefit_orders', 'auth_benefit_price_alert'] as $benefit)
                        <li>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            <span>{{ __($benefit) }}</span>
                        </li>
                    @endforeach
                </ul>

                @if(session()->has('register.customer_id'))
                    <p class="auth-subtext"><a href="{{ route('front.register.verify') }}">{{ __('auth_verify_account') }}</a></p>
                @endif

                @if($errors->has('otp'))
                    <p class="auth-error">{{ $errors->first('otp') }}</p>
                @endif

                <form method="POST" action="{{ route('front.register.store') }}" class="auth-form auth-form--grid">
                    @csrf

                    <div class="form-field">
                        <label>{{ __('profile_your_phone_number') }}</label>
                        <input type="tel" name="mobile" id="registerMobile" class="auth-input @error('mobile') is-invalid @enderror" value="{{ old('mobile') }}" placeholder="994 __ ___ __ __" inputmode="numeric" required>
                        <p class="auth-account-exists" id="registerMobileExists" hidden role="status">
                            <span>{{ __('auth_mobile_already_registered') }}</span>
                            <a href="{{ route('front.login') }}" id="registerLoginLink">{{ __('auth_sign_in') }}</a>
                        </p>
                        <small class="auth-field-hint">{{ __('auth_phone_sms_hint') }}</small>
                        <div class="invalid-feedback">{{ $errors->first('mobile') }}</div>
                    </div>

                    <div class="form-field">
                        <label>{{ __('profile_your_email') }}</label>
                        <input type="email" name="email" class="auth-input @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="{{ __('profile_your_email') }}" required>
                        <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                    </div>

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
                        <label>{{ __('auth_password') }}</label>
                        <input type="password" name="password" class="auth-input @error('password') is-invalid @enderror" placeholder="{{ __('auth_password') }}" autocomplete="new-password" minlength="6" required>
                        <div class="invalid-feedback">{{ $errors->first('password') }}</div>
                    </div>

                    <div class="form-field">
                        <label>{{ __('auth_confirm_password') }}</label>
                        <input type="password" name="password_confirmation" class="auth-input" placeholder="{{ __('auth_confirm_password') }}" autocomplete="new-password" minlength="6" required>
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

                    @if(app(\App\Services\Referral\ReferralSettings::class)->enabled())
                        <div class="form-field">
                            <label for="registerReferral">{{ __('auth_referral_code') }} <span class="form-field__optional">({{ __('auth_optional') }})</span></label>
                            <input type="text" name="referral_code" id="registerReferral" class="auth-input @error('referral_code') is-invalid @enderror" value="{{ old('referral_code', request('ref', request()->cookie('referral_code'))) }}" placeholder="{{ __('auth_referral_code_placeholder') }}" autocomplete="off" autocapitalize="characters" maxlength="20">
                            <div class="invalid-feedback">{{ $errors->first('referral_code') }}</div>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-dark auth-submit">{{ __('auth_register') }}</button>

                    @php
                        $termsUrl = Route::has('front.page.terms') ? route('front.page.terms') : '#';
                        $privacyUrl = Route::has('front.page.privacy') ? route('front.page.privacy') : '#';
                    @endphp
                    <p class="auth-legal">
                        {!! __('auth_register_legal', [
                            'terms' => '<a href="'.e($termsUrl).'" target="_blank" rel="noopener">'.e(__('auth_terms_of_use')).'</a>',
                            'privacy' => '<a href="'.e($privacyUrl).'" target="_blank" rel="noopener">'.e(__('auth_privacy_policy')).'</a>',
                        ]) !!}
                    </p>
                    <p class="auth-secure">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <span>{{ __('auth_data_secure') }}</span>
                    </p>
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
    <script type="application/json" id="register-config">@json(['checkUrl' => route('front.register.check-mobile')])</script>
    <script src="{{ asset_v('frontend/js/pages/register.js') }}" defer></script>
@endsection
