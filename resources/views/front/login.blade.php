@extends('front.layout')
@section('content')
<main>
    <div class="login-container">
        <!-- Login Section (show initially) -->
        <div class="login-page">
            <div class="login-page-close-icon">
                <img src="{{asset('frontend/images/filter-close.svg')}}" alt="" />
            </div>
            <div class="login-page__wrap">
                <h1>Şəxsi kabinet</h1>
                <form class="login-page__wrap__form" id="loginForm">
                    <input type="tel" id="phoneInput" placeholder="Telefon nömrəsi" />
                    <div class="actions">
                        <button type="button" id="submitBtn">Daxil olun</button>
                        <button class="gmail-btn">
                            <img src="{{asset('frontend/images/gmail.svg')}}" alt="gmail icon" />
                            <span>Gmail ilə daxil olun</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- OTP Section (hidden initially) -->
        <div id="otpSection" class="hide-form">
            <div class="otpSection-close-icon">
                <img src="{{asset('frontend/images/filter-close.svg')}}" alt="" />
            </div>
            <div class="otpSection-wrapper">
                <div class="headers">
                    <h1>Şəxsi kabinet</h1>
                    <span class="otp-sent-message">OTP kod +994 50 345 54 67 nömrəsinə göndərilmişdir</span>
                    <input type="text" id="otpInput" class="regular-otp-input" placeholder="OTP kodu" />

                    <div class="field-with-error">
                        <input type="text" id="otpInput" placeholder="OTP kodu" />
                        <p class="error-message">Yanlış kod</p>
                    </div>
                </div>

                <div class="otp-section-form-label">
                    <span class="seconds">00:58</span>
                    <span class="send-again-text">Yenidən göndər</span>
                </div>

                <div class="actions">
                    <button type="button" id="backBtn">Geriyə</button>
                    <button type="button" id="otpSubmitBtn">Daxil olun</button>
                </div>
            </div>
        </div>

        <!-- SUCCESS-LOGIN -->
        <div id="success-login" class="hide-form">
            <div class="success-login-close-icon">
                <img src="{{asset('frontend/images/filter-close.svg')}}" alt="" />
            </div>

            <img src="{{asset('frontend/images/success.svg')}}" alt="" />
            <h3>Təşəkkür edirik!</h3>

            <h4>Sizin qeydiyyatınız uğurla tamamlandı.</h4>

            <span>Məlumatlarınızı
          <a href="./cabinet.html" class="link-personal-cabinet">şəxsi kabinetdə</a>
          əlavə edə bilərsiniz</span>
        </div>
    </div>
</main>
<main class="login-page-mobile">
    <!-- LOGIN-PAGE-LAYOUT -->
    <div class="login-page">
        <span><img src="{{asset('frontend/images/close.svg')}}" alt="" /></span>
        <h1>Şəxsi kabinet</h1>

        <form action="" class="login-page__wrap__form" id="loginForm">
            <input type="tel" id="phoneInput" placeholder="Telefon nömrəsi" />
        </form>

        <div class="actions">
            <button type="button" id="submitBtn">Daxil olun</button>
            <button class="gmail-btn">
                <img src="{{asset('frontend/images/gmail.svg')}}" alt="gmail icon" />
                <span>Gmail ilə daxil olun</span>
            </button>
        </div>
    </div>

    <!-- OTP-SECTION-LAYOUT -->
    <div id="otp-section-mobile">
        <span><img src="{{asset('frontend/images/close.svg')}}" alt="" /></span>

        <h1>Şəxsi kabinet</h1>

        <div class="headers">
            <span class="otp-sent-message">OTP kod +994 50 345 54 67 nömrəsinə göndərilmişdir</span>
            <input type="text" id="otpInput" class="regular-otp-input" placeholder="OTP kodu" />

            <div class="field-with-error">
                <input type="text" id="otpInput" placeholder="OTP kodu" />
                <p class="error-message">Yanlış kod</p>
            </div>
        </div>

        <div class="otp-section-form-label">
            <span class="seconds">00:58</span>
            <span class="send-again-text">Yenidən göndər</span>
        </div>

        <div class="actions">
            <button type="button" id="submitBtn">Daxil olun</button>
        </div>
    </div>
</main>
@endsection
@section('page-scripts')
    <script src="{{asset('frontend/js/login.js')}}"></script>
@endsection
