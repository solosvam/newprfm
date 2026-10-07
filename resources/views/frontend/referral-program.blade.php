{{-- İctimai referal səhifəsi: necə işləyir + şərtlər (ayarlardan, ReferralSettings). Şəxsi link — /profile/referral --}}
@extends('frontend.layouts.app')

@php
    $money = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $referrerAmount = $money($settings->referrerAmount());
    $inviteeAmount = $money($settings->inviteeAmount());
    $isDiscount = $settings->inviteeMode() === \App\Services\Referral\ReferralSettings::MODE_DISCOUNT;
    $limit = $settings->inviteLimit();
    $minOrder = $settings->minOrderAmount();
    $active = 'referral';
@endphp

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/info.css') }}">
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/referral.css') }}">
@endsection

@section('title', __('referral_title').' | Parfumshop.az')
@section('meta_description', __('referral_page_meta'))
@section('og_title', __('referral_title').' | Parfumshop.az')

@section('content')
    <main class="info-page">
        <div class="info-layout">
            @include('frontend.partials.info-nav')

            <article class="info-card referral-page">
                <h1 class="info-card__title">{{ __('referral_title') }}</h1>

                @if(!$settings->enabled())
                    <div class="referral-locked">
                        <div>
                            <strong>{{ __('referral_disabled_title') }}</strong>
                        </div>
                    </div>
                @else
                    <section class="referral-hero">
                        <div class="referral-hero__amounts">
                            <div class="referral-hero__amount">
                                <span class="referral-hero__value">{{ $inviteeAmount }} ₼</span>
                                <span class="referral-hero__label">{{ __($isDiscount ? 'referral_friend_gets_discount' : 'referral_friend_gets_bonus') }}</span>
                            </div>
                            <span class="referral-hero__plus" aria-hidden="true">+</span>
                            <div class="referral-hero__amount">
                                <span class="referral-hero__value">{{ $referrerAmount }} ₼</span>
                                <span class="referral-hero__label">{{ __('referral_you_get_bonus') }}</span>
                            </div>
                        </div>
                        <p class="referral-hero__text">{{ __('referral_hero_text') }}</p>
                    </section>

                    <section class="referral-section">
                        <h2 class="info-subtitle">{{ __('referral_how_it_works') }}</h2>
                        <ol class="referral-steps">
                            <li>
                                <span class="referral-steps__num">1</span>
                                <div><strong>{{ __('referral_step1_title') }}</strong><p>{{ __('referral_step1_text') }}</p></div>
                            </li>
                            <li>
                                <span class="referral-steps__num">2</span>
                                <div><strong>{{ __('referral_step2_title') }}</strong><p>{{ __($isDiscount ? 'referral_step2_text_discount' : 'referral_step2_text_balance', ['amount' => $inviteeAmount]) }}</p></div>
                            </li>
                            <li>
                                <span class="referral-steps__num">3</span>
                                <div><strong>{{ __('referral_step3_title') }}</strong><p>{{ __('referral_step3_text', ['amount' => $referrerAmount]) }}</p></div>
                            </li>
                        </ol>
                    </section>

                    <section class="referral-section">
                        <h2 class="info-subtitle">{{ __('referral_terms_title') }}</h2>
                        <div class="info-content">
                            @include('frontend.partials.referral-terms')
                        </div>
                    </section>

                    <div class="faq-more">
                        <span>{{ __('referral_hero_text') }}</span>
                        @auth
                            <a href="{{ route('profile.referral') }}" class="btn btn-dark faq-more__btn">{{ __('referral_cta_get_link') }}</a>
                        @else
                            <a href="{{ route('front.login') }}" class="btn btn-dark faq-more__btn">{{ __('referral_cta_login') }}</a>
                        @endauth
                    </div>
                @endif
            </article>
        </div>
    </main>
@endsection
