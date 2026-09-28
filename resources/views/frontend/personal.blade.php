@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
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
            </div>
        </div>
    </main>
@endsection
