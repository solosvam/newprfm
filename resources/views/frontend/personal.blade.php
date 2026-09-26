@extends('frontend.layout')
@section('content')
<main>
<div class="container">
<div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>__('profile_personal_details')])

<div class="cabinet__personal">
@if(session('success'))<div class="profile-success">{{ session('success') }}</div>@endif

<form method="POST" action="{{ route('profile.personal.update') }}" class="form">
@csrf
<div class="form__non-credit">
    <div class="form__non-credit__container">
        <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" placeholder="{{ __('profile_first_name') }}">
    </div>
    <div class="form__non-credit__container">
        <input type="text" name="surname" value="{{ old('surname', auth()->user()->surname) }}" placeholder="{{ __('profile_last_name') }}">
    </div>
    <div class="form__non-credit__container">
        <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" placeholder="{{ __('profile_your_email') }}">
    </div>
    <div class="form__non-credit__container">
        <input type="text" value="{{ auth()->user()->mobile }}" placeholder="{{ __('profile_your_phone_number') }}" disabled>
    </div>
    <div class="form__non-credit__container">
        <input type="password" name="password" placeholder="{{ __('profile_current_password') }}" autocomplete="current-password">
    </div>
    <div class="form__non-credit__container">
        <input type="password" name="new_password" placeholder="{{ __('profile_new_password') }}" autocomplete="new-password">
    </div>
    <div class="form__non-credit__container">
        <input type="password" name="new_password_confirmation" placeholder="{{ __('profile_repeat_new_password') }}" autocomplete="new-password">
    </div>
</div>

@if($errors->any())<div class="profile-errors">{{ $errors->first() }}</div>@endif
<div class="form__submit"><button type="submit">{{ __('profile_update') }}</button></div>
</form>
</div>
</div>
</div>
</main>
@endsection
