@extends('frontend.layout')

@section('content')
<main>
    <div class="container">
        <div class="cabinet">
            @include('frontend.partials.cabinet-sidebar')
            <div class="cabinet__sections">
                <div class="cabinet__sections__elem bonus">
                    <h2>{{ __('Hesabınızdakı bonus pul') }}</h2>
                    <p>0,00 AZN</p>
                    <a href="#">
                        <img src="{{ asset('frontend/images/arrow-bonus.svg') }}" alt="">
                        <span>{{ __('Bonus pul tarixçənizə baxın') }}</span>
                    </a>
                </div>

                <div class="cabinet__sections__elem last-order">
                    <h2>{{ __('Son sifarişiniz') }}</h2>
                    <p>{{ __('Hələ sifarişiniz yoxdur.') }}</p>
                </div>

                <div class="cabinet__sections__elem order-location">
                    <h2>{{ __('Sifarişim haradadır?') }}</h2>
                    <p>{{ __('Aktiv sifariş yoxdur.') }}</p>
                </div>

                <div class="cabinet__sections__elem get-birkart">
                    <div class="info">
                        <h3>{{ __('BirKart əldə edin') }}
                            <svg width="22" height="9" viewBox="0 0 22 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M0.648804 4.5L20.6488 4.5" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M17.3931 1.24609L20.6489 4.50191L17.3931 7.75772" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </h3>
                        <span>{{ __('Kreditlə sifarişdə çətinlik çəkirsinizsə, Birkart sifariş edin.') }}</span>
                    </div>
                    <img src="{{ asset('frontend/images/birbank.png') }}" alt="">
                </div>

                <div class="cabinet__sections__elem invite-friend">
                    <ul>
                        <li>
                            <h1>{{ __('Dostunu dəvət et və bonus qazan!') }}</h1>
                            <button type="button" id="inviteButton">{{ __('Dəvət göndər') }}</button>
                        </li>
                        <li><img src="{{ asset('frontend/images/invite.png') }}" alt=""></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

@endsection

