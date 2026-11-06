@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/auth.css') }}">
    <link rel="stylesheet" href="{{ asset_v('frontend/css/components/gender-pills.css') }}">
@endsection

@section('title', __('auth_google_complete_title') . ' | ParfumShop')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    <main>
        <div class="auth-page">
            <div class="auth-card">
                <h1 class="auth-title">{{ __('auth_google_complete_title') }}</h1>
                <p class="auth-subtext">{{ __('auth_google_complete_hint') }}</p>
                @if($profile['email'])
                    <p class="auth-google-account">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <span>{{ $profile['email'] }}</span>
                    </p>
                @endif

                <form method="POST" action="{{ route('auth.google.complete.store') }}" class="auth-form">
                    @csrf
                    <div class="form-field">
                        <label for="googleMobile">{{ __('profile_your_phone_number') }}</label>
                        <input type="tel" name="mobile" id="googleMobile" class="auth-input @error('mobile') is-invalid @enderror" value="{{ old('mobile') }}" placeholder="994 __ ___ __ __" inputmode="numeric" autocomplete="tel" required autofocus>
                        <small class="auth-field-hint">{{ __('auth_phone_sms_hint') }}</small>
                        <div class="invalid-feedback">{{ $errors->first('mobile') }}</div>
                    </div>

                    <div class="form-field">
                        <label for="googleName">{{ __('profile_first_name') }}</label>
                        <input type="text" name="name" id="googleName" class="auth-input @error('name') is-invalid @enderror" value="{{ old('name', $profile['name']) }}" maxlength="30" autocomplete="given-name" required>
                        <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                    </div>

                    <div class="form-field">
                        <label for="googleSurname">{{ __('profile_last_name') }}</label>
                        <input type="text" name="surname" id="googleSurname" class="auth-input @error('surname') is-invalid @enderror" value="{{ old('surname', $profile['surname']) }}" maxlength="30" autocomplete="family-name" required>
                        <div class="invalid-feedback">{{ $errors->first('surname') }}</div>
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

                    @if($errors->has('otp'))
                        <p class="auth-error">{{ $errors->first('otp') }}</p>
                    @endif

                    <button type="submit" class="btn btn-dark auth-submit">{{ __('auth_continue') }}</button>

                    <p class="auth-legal">
                        {!! __('auth_register_legal', [
                            'terms' => '<a href="'.e(route('front.page.terms')).'" target="_blank" rel="noopener">'.e(__('auth_terms_of_use')).'</a>',
                            'privacy' => '<a href="'.e(route('front.page.privacy')).'" target="_blank" rel="noopener">'.e(__('auth_privacy_policy')).'</a>',
                        ]) !!}
                    </p>
                </form>
            </div>
        </div>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/vendor/jquery.inputmask.min.js') }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.Inputmask) {
                Inputmask({ mask: '\\9\\9\\4 N9 999 99 99', definitions: { N: { validator: '[1-9]' } }, placeholder: '_', showMaskOnHover: false })
                    .mask(document.getElementById('googleMobile'));
            }
        });
    </script>
@endsection
