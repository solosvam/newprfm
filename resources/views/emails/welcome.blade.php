@extends('emails.layout')
@section('body')
@php
    $copy = [
        'az' => ['title' => 'Xoş gəlmisiniz!', 'hello' => 'Salam', 'body' => 'ParfumShop.az hesabınız uğurla təsdiqləndi. Artıq məhsullarımızı kəşf edə və sifariş verə bilərsiniz.', 'button' => 'Alış-verişə başla'],
        'ru' => ['title' => 'Добро пожаловать!', 'hello' => 'Здравствуйте', 'body' => 'Ваш аккаунт ParfumShop.az успешно подтвержден. Теперь вы можете выбирать товары и оформлять заказы.', 'button' => 'Перейти к покупкам'],
        'en' => ['title' => 'Welcome!', 'hello' => 'Hello', 'body' => 'Your ParfumShop.az account has been verified. You can now explore our products and place orders.', 'button' => 'Start shopping'],
    ][$locale];
@endphp
<h1 style="font-family:Georgia,serif;font-size:26px;font-weight:normal;margin:0 0 20px">{{ $copy['title'] }}</h1>
<p style="line-height:1.7">{{ $copy['hello'] }}, {{ $customerName }}!</p>
<p style="line-height:1.7">{{ $copy['body'] }}</p>
<p style="margin:30px 0"><a href="{{ config('app.url') }}" style="background:#28221e;color:white;text-decoration:none;padding:14px 24px;display:inline-block">{{ $copy['button'] }}</a></p>
@endsection
