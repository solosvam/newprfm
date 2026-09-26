@extends('frontend.new.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="account-layout">
                @include('frontend.new.partials.cabinet-sidebar', ['pageTitle' => __('profile_personal_details')])

                <div class="account-panel">
                    <h1 class="account-panel-title">{{ __('profile_personal_details') }}</h1>

                    <form method="POST" action="{{ route('profile.personal.update') }}" class="account-form">
                        @csrf

                        <div class="account-form-grid">
                            <div class="form-field">
                                <label>{{ __('profile_first_name') }}</label>
                                <input id="personal-name" type="text" name="name" @class(['is-invalid' => $errors->has('name')]) aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="personal-name-error" value="{{ old('name', auth()->user()->name) }}" placeholder="{{ __('profile_first_name') }}">
                                <div class="invalid-feedback" id="personal-name-error" data-error="name">{{ $errors->first('name') }}</div>
                            </div>
                            <div class="form-field">
                                <label>{{ __('profile_last_name') }}</label>
                                <input id="personal-surname" type="text" name="surname" @class(['is-invalid' => $errors->has('surname')]) aria-invalid="{{ $errors->has('surname') ? 'true' : 'false' }}" aria-describedby="personal-surname-error" value="{{ old('surname', auth()->user()->surname) }}" placeholder="{{ __('profile_last_name') }}">
                                <div class="invalid-feedback" id="personal-surname-error" data-error="surname">{{ $errors->first('surname') }}</div>
                            </div>
                            <div class="form-field">
                                <label>{{ __('profile_your_email') }}</label>
                                <input id="personal-email" type="email" name="email" @class(['is-invalid' => $errors->has('email')]) aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="personal-email-error" value="{{ old('email', auth()->user()->email) }}" placeholder="{{ __('profile_your_email') }}">
                                <div class="invalid-feedback" id="personal-email-error" data-error="email">{{ $errors->first('email') }}</div>
                            </div>
                            <div class="form-field">
                                <label>{{ __('profile_your_phone_number') }}</label>
                                <input type="text" value="{{ auth()->user()->mobile }}" placeholder="{{ __('profile_your_phone_number') }}" disabled>
                            </div>
                        </div>

                        <div class="account-form-divider">
                            <span>{{ __('profile_current_password') }}</span>
                        </div>

                        <div class="account-form-grid">
                            <div class="form-field">
                                <label>{{ __('profile_current_password') }}</label>
                                <input id="personal-password" type="password" name="password" @class(['is-invalid' => $errors->has('password')]) aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="personal-password-error" placeholder="{{ __('profile_current_password') }}" autocomplete="current-password">
                                <div class="invalid-feedback" id="personal-password-error" data-error="password">{{ $errors->first('password') }}</div>
                            </div>
                            <div class="form-field">
                                <label>{{ __('profile_new_password') }}</label>
                                <input id="personal-new_password" type="password" name="new_password" @class(['is-invalid' => $errors->has('new_password')]) aria-invalid="{{ $errors->has('new_password') ? 'true' : 'false' }}" aria-describedby="personal-new_password-error" placeholder="{{ __('profile_new_password') }}" autocomplete="new-password">
                                <div class="invalid-feedback" id="personal-new_password-error" data-error="new_password">{{ $errors->first('new_password') }}</div>
                            </div>
                            <div class="form-field">
                                <label>{{ __('profile_repeat_new_password') }}</label>
                                <input id="personal-new_password_confirmation" type="password" name="new_password_confirmation" @class(['is-invalid' => $errors->has('new_password_confirmation')]) aria-invalid="{{ $errors->has('new_password_confirmation') ? 'true' : 'false' }}" aria-describedby="personal-new_password_confirmation-error" placeholder="{{ __('profile_repeat_new_password') }}" autocomplete="new-password">
                                <div class="invalid-feedback" id="personal-new_password_confirmation-error" data-error="new_password_confirmation">{{ $errors->first('new_password_confirmation') }}</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-dark account-form-submit">{{ __('profile_update') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection
