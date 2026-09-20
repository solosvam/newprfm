@extends('frontend.layout')
@section('content')
<main><div class="container"><div class="cabinet">
@include('frontend.partials.cabinet-sidebar',['pageTitle'=>'Şəxsi məlumatlarım'])
<div class="cabinet__personal">
@if(session('success'))<div class="profile-success">{{ session('success') }}</div>@endif
<form method="POST" action="{{ route('profile.personal.update') }}" class="form">@csrf
<div class="form__non-credit"><div class="form__non-credit__container"><input name="name" value="{{ old('name',auth()->user()->name) }}" placeholder="Adınız"></div>
<div class="form__non-credit__container"><input name="surname" value="{{ old('surname',auth()->user()->surname) }}" placeholder="Soyadınız"></div>
<div class="form__non-credit__container"><input type="email" name="email" value="{{ old('email',auth()->user()->email) }}" placeholder="E-poçtunuz"></div>
<div class="form__non-credit__container"><input value="{{ auth()->user()->mobile }}" placeholder="Telefon nömrəniz" disabled></div>
<div class="form__non-credit__container"><input type="password" name="password" placeholder="Cari şifrəniz"></div>
<div class="form__non-credit__container"><input type="password" name="new_password" placeholder="Yeni şifrəniz"></div>
<div class="form__non-credit__container"><input type="password" name="new_password_confirmation" placeholder="Yeni şifrə təkrar"></div></div>
@if($errors->any())<div class="profile-errors">{{ $errors->first() }}</div>@endif
<div class="form__submit"><button type="submit">Məlumatları yenilə</button></div>
</form></div></div></div></main>
@endsection