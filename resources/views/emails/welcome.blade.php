@extends('emails.layout')

@section('body')
    @php
        $copy = [
            'az' => [
                'eyebrow'  => 'Hesab Təsdiqi',
                'title'    => 'Xoş gəlmisiniz',
                'hello'    => 'Salam',
                'body'     => 'ParfumShop.az hesabınız uğurla təsdiqləndi. Artıq dünya brendlərinin ən seçilmiş ətirlərini kəşf edə və sifariş verə bilərsiniz.',
                'button'   => 'Alış-verişə başla',
                'sub'      => 'Hər zaman yüksək keyfiyyət, orijinal məhsullar.',
            ],
            'ru' => [
                'eyebrow'  => 'Подтверждение аккаунта',
                'title'    => 'Добро пожаловать',
                'hello'    => 'Здравствуйте',
                'body'     => 'Ваш аккаунт ParfumShop.az успешно подтверждён. Теперь вам доступна коллекция мировых брендов — выбирайте и оформляйте заказы.',
                'button'   => 'Перейти к покупкам',
                'sub'      => 'Только оригинальная парфюмерия. Только высокое качество.',
            ],
            'en' => [
                'eyebrow'  => 'Account Verified',
                'title'    => 'Welcome',
                'hello'    => 'Hello',
                'body'     => 'Your ParfumShop.az account has been successfully verified. You can now explore our curated collection of world-class fragrances and place orders.',
                'button'   => 'Start shopping',
                'sub'      => 'Only originals. Always exceptional.',
            ],
        ][$locale ?? 'az'];
    @endphp

    {{-- Eyebrow --}}
    <p style="margin:0 0 18px;font-size:11px;letter-spacing:2.5px;text-transform:uppercase;color:#8b7ba8;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif">
        {{ $copy['eyebrow'] }}
    </p>

    {{-- Title --}}
    <h1 style="margin:0 0 24px;font-family:Georgia,'Times New Roman',serif;font-size:30px;font-weight:normal;color:#1a1520;line-height:1.2">
        {{ $copy['title'] }},<br>{{ $customerName }}.
    </h1>

    {{-- Divider --}}
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 28px">
        <tr><td style="width:40px;height:2px;background:#bda4e5"></td></tr>
    </table>

    {{-- Body text --}}
    <p style="margin:0 0 32px;font-size:15px;line-height:1.8;color:#3d3549">
        {{ $copy['body'] }}
    </p>

    {{-- CTA Button --}}
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 32px">
        <tr>
            <td style="background:#2b1f4a;border-radius:999px">
                <a href="{{ config('app.url') }}"
                   style="display:inline-block;padding:15px 36px;
                      color:#ffffff;text-decoration:none;
                      font-size:12px;letter-spacing:2px;text-transform:uppercase;
                      font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif">
                    {{ $copy['button'] }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Sub-line --}}
    <p style="margin:0;font-size:12px;color:#9a8fae;font-style:italic;line-height:1.6">
        {{ $copy['sub'] }}
    </p>

@endsection
