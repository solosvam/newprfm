@extends('frontend.layout')

@section('content')
<main>
    <div class="container">
        <div class="cabinet">
            <div class="cabinet__aside">
                <div class="cabinet__aside__top">
                    <ul>
                        <li>
                            <a href="{{ url()->previous() }}">
                                <img src="{{ asset('frontend/images/arrow-left.svg') }}" alt="">
                                <span>Geri qayıt</span>
                            </a>
                        </li>
                        <li>
                            <ul>
                                <li><a href="{{ route('home') }}"><img src="{{ asset('frontend/images/home.svg') }}" alt=""></a></li>
                                <li><img src="{{ asset('frontend/images/arrow-right.svg') }}" alt=""></li>
                                <li><a href="{{ route('profile') }}">Şəxsi kabinet</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="cabinet__aside__nav">
                    <h1>Salam, {{ auth()->user()->name }} {{ auth()->user()->surname }}</h1>
                    <ul>
                        <li><a href="{{ route('profile') }}" style="border-bottom:1px solid #000;color:#000">Hesab məlumatları</a></li>
                        <li><a href="#">Şəxsi məlumatlar</a></li>
                        <li><a href="#">Sifarişlərimin tarixçəsi</a></li>
                        <li><a href="#">Bəyəndiyim ətirlər <span>0</span></a></li>
                        <li><a href="#">Rəylərim <span>0</span></a></li>
                    </ul>
                    <form method="POST" action="{{ route('front.logout') }}">
                        @csrf
                        <button type="submit" class="cabinet-logout">
                            <img src="{{ asset('frontend/images/signin.svg') }}" alt="">
                            <span>Hesabdan çıx</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="cabinet__sections">
                <div class="cabinet__sections__elem bonus">
                    <h2>Hesabınızdakı bonus pul</h2>
                    <p>0,00 AZN</p>
                    <a href="#">
                        <img src="{{ asset('frontend/images/arrow-bonus.svg') }}" alt="">
                        <span>Bonus pul tarixçənizə baxın</span>
                    </a>
                </div>

                <div class="cabinet__sections__elem last-order">
                    <h2>Son sifarişiniz</h2>
                    <p>Hələ sifarişiniz yoxdur.</p>
                </div>

                <div class="cabinet__sections__elem order-location">
                    <h2>Sifarişim haradadır?</h2>
                    <p>Aktiv sifariş yoxdur.</p>
                </div>

                <div class="cabinet__sections__elem get-birkart">
                    <div class="info">
                        <h3>BirKart əldə edin
                            <svg width="22" height="9" viewBox="0 0 22 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M0.648804 4.5L20.6488 4.5" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M17.3931 1.24609L20.6489 4.50191L17.3931 7.75772" stroke="black" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </h3>
                        <span>Kreditlə sifarişdə çətinlik çəkirsinizsə, Birkart sifariş edin.</span>
                    </div>
                    <img src="{{ asset('frontend/images/birbank.png') }}" alt="">
                </div>

                <div class="cabinet__sections__elem invite-friend">
                    <ul>
                        <li>
                            <h1>Dostunu dəvət et və bonus qazan!</h1>
                            <button type="button" id="inviteButton">Dəvət göndər</button>
                        </li>
                        <li><img src="{{ asset('frontend/images/invite.png') }}" alt=""></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="inviteModal" class="invite-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-header-wrap">
                <h1>Dostunu dəvət et və bonus qazan!</h1>
                <div>
                    <img src="{{ asset('frontend/images/black-info.svg') }}" alt="">
                    <span>Dəvət sisteminin şərtləri burada göstəriləcək.</span>
                </div>
            </div>
            <span class="close"><img src="{{ asset('frontend/images/close.svg') }}" alt="" class="close-icon"></span>
        </div>
    </div>
</div>
@endsection

@section('page-scripts')
<script src="{{ asset('frontend/js/cabinet.js?v=' . filemtime(public_path('frontend/js/cabinet.js'))) }}"></script>
@endsection
