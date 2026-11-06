@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
    <link rel="stylesheet" href="{{ asset_v('frontend/css/components/gender-pills.css') }}">
@endsection

@section('content')
    @php
        $user = auth()->user();
        $selectedGender = old('gender', $user->gender);
        $passwordHasErrors = $errors->hasAny(['password', 'new_password', 'new_password_confirmation']);
    @endphp

    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('profile_personal_details')])

            <div class="account-panel account-panel--flush">
                <h1 class="account-panel-title">{{ __('profile_personal_details') }}</h1>

                <form method="POST" action="{{ route('profile.personal.update') }}" class="account-form">
                    @csrf

                    <div class="account-form-grid">
                        <div class="form-field">
                            <label for="personal-name">{{ __('profile_first_name') }}</label>
                            <input id="personal-name" type="text" name="name" autocomplete="given-name"
                                   @class(['is-invalid' => $errors->has('name')])
                                   aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                                   aria-describedby="personal-name-error"
                                   value="{{ old('name', $user->name) }}" required>
                            <div class="invalid-feedback" id="personal-name-error" data-error="name">{{ $errors->first('name') }}</div>
                        </div>

                        <div class="form-field">
                            <label for="personal-surname">{{ __('profile_last_name') }}</label>
                            <input id="personal-surname" type="text" name="surname" autocomplete="family-name"
                                   @class(['is-invalid' => $errors->has('surname')])
                                   aria-invalid="{{ $errors->has('surname') ? 'true' : 'false' }}"
                                   aria-describedby="personal-surname-error"
                                   value="{{ old('surname', $user->surname) }}" required>
                            <div class="invalid-feedback" id="personal-surname-error" data-error="surname">{{ $errors->first('surname') }}</div>
                        </div>

                        <div class="form-field">
                            <label for="personal-email">{{ __('profile_your_email') }}</label>
                            <input id="personal-email" type="email" name="email" autocomplete="email" inputmode="email"
                                   @class(['is-invalid' => $errors->has('email')])
                                   aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                   aria-describedby="personal-email-error"
                                   value="{{ old('email', $user->email) }}" required>
                            <div class="invalid-feedback" id="personal-email-error" data-error="email">{{ $errors->first('email') }}</div>
                        </div>

                        <div class="form-field">
                            <label for="personal-mobile">{{ __('profile_your_phone_number') }}</label>
                            <input id="personal-mobile" type="text" value="{{ $user->mobile }}" disabled>
                        </div>

                        <div class="form-field form-field--full">
                            <span class="form-field__label">{{ __('profile_gender') }}</span>
                            <div class="gender-pills" role="radiogroup" aria-label="{{ __('profile_gender') }}">
                                @foreach([1 => __('profile_male'), 0 => __('profile_female')] as $value => $label)
                                    <label class="gender-pill">
                                        <input type="radio" name="gender" value="{{ $value }}" @checked((int) $selectedGender === $value)>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="invalid-feedback" data-error="gender">{{ $errors->first('gender') }}</div>
                        </div>
                    </div>

                    {{-- Şifrə — yalnız dəyişmək istəyənlər üçün açılır --}}
                    <details class="account-password" @if($passwordHasErrors) open @endif>
                        <summary class="account-password__toggle">{{ __('profile_change_password') }}</summary>

                        <div class="account-form-grid account-password__body">
                            <div class="form-field form-field--full">
                                <label for="personal-password">{{ __('profile_current_password') }}</label>
                                <input id="personal-password" type="password" name="password" autocomplete="current-password"
                                       @class(['is-invalid' => $errors->has('password')])
                                       aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                       aria-describedby="personal-password-error">
                                <div class="invalid-feedback" id="personal-password-error" data-error="password">{{ $errors->first('password') }}</div>
                            </div>

                            <div class="form-field">
                                <label for="personal-new_password">{{ __('profile_new_password') }}</label>
                                <input id="personal-new_password" type="password" name="new_password" autocomplete="new-password"
                                       @class(['is-invalid' => $errors->has('new_password')])
                                       aria-invalid="{{ $errors->has('new_password') ? 'true' : 'false' }}"
                                       aria-describedby="personal-new_password-error">
                                <div class="invalid-feedback" id="personal-new_password-error" data-error="new_password">{{ $errors->first('new_password') }}</div>
                            </div>

                            <div class="form-field">
                                <label for="personal-new_password_confirmation">{{ __('profile_repeat_new_password') }}</label>
                                <input id="personal-new_password_confirmation" type="password" name="new_password_confirmation" autocomplete="new-password"
                                       @class(['is-invalid' => $errors->has('new_password_confirmation')])
                                       aria-invalid="{{ $errors->has('new_password_confirmation') ? 'true' : 'false' }}"
                                       aria-describedby="personal-new_password_confirmation-error">
                                <div class="invalid-feedback" id="personal-new_password_confirmation-error" data-error="new_password_confirmation">{{ $errors->first('new_password_confirmation') }}</div>
                            </div>
                        </div>
                    </details>

                    <button type="submit" class="btn btn-dark account-form-submit">{{ __('profile_update') }}</button>
                </form>

                {{-- Google ilə giriş: bağlıdırsa — "Ayır", deyilsə (düymə görünürsə) — "Bağla" --}}
                @if($user->google_id || app(\App\Services\Auth\GoogleLogin::class)->visible())
                    <div class="account-google">
                        <div class="account-google__info">
                            <svg viewBox="0 0 48 48" width="22" height="22" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 8 3l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 8 3l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>
                            <div>
                                <div class="account-google__title">Google</div>
                                <div class="account-google__status">{{ $user->google_id ? __('profile_google_connected') : __('profile_google_not_connected') }}</div>
                            </div>
                        </div>
                        @if($user->google_id)
                            <form method="POST" action="{{ route('profile.google.disconnect') }}" onsubmit="return confirm(@js(__('profile_google_disconnect_confirm')))">
                                @csrf
                                <button type="submit" class="btn btn-outline">{{ __('profile_google_disconnect') }}</button>
                            </form>
                        @else
                            <a href="{{ route('auth.google') }}" class="btn btn-outline" rel="nofollow">{{ __('profile_google_connect') }}</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </main>
@endsection
