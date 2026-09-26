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
                                <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" placeholder="{{ __('profile_first_name') }}">
                            </div>
                            <div class="form-field">
                                <label>{{ __('profile_last_name') }}</label>
                                <input type="text" name="surname" value="{{ old('surname', auth()->user()->surname) }}" placeholder="{{ __('profile_last_name') }}">
                            </div>
                            <div class="form-field">
                                <label>{{ __('profile_your_email') }}</label>
                                <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" placeholder="{{ __('profile_your_email') }}">
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
                                <input type="password" name="password" placeholder="{{ __('profile_current_password') }}" autocomplete="current-password">
                            </div>
                            <div class="form-field">
                                <label>{{ __('profile_new_password') }}</label>
                                <input type="password" name="new_password" placeholder="{{ __('profile_new_password') }}" autocomplete="new-password">
                            </div>
                            <div class="form-field">
                                <label>{{ __('profile_repeat_new_password') }}</label>
                                <input type="password" name="new_password_confirmation" placeholder="{{ __('profile_repeat_new_password') }}" autocomplete="new-password">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-dark account-form-submit">{{ __('profile_update') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
@endsection
