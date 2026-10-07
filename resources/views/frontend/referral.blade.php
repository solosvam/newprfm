@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/referral.css') }}">
@endsection

@section('title', __('referral_title') . ' | parfumshop')
@section('meta_robots', 'noindex, nofollow')

@section('content')
    @php
        // 10.00 → 10, 12.50 → 12.5
        $money = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $referrerAmount = $money($settings->referrerAmount());
        $inviteeAmount = $money($settings->inviteeAmount());
        $isDiscount = $settings->inviteeMode() === \App\Services\Referral\ReferralSettings::MODE_DISCOUNT;
        $limit = $settings->inviteLimit();
        $minOrder = $settings->minOrderAmount();
    @endphp

    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('referral_title')])

            <div class="account-panel account-panel--flush referral-page">
                <h1 class="account-panel-title">{{ __('referral_title') }}</h1>

                @if($blockReason !== 'disabled')
                {{-- Hero --}}
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
                    <p class="referral-hero__text">{{ __('referral_hero_text', ['invitee' => $inviteeAmount, 'referrer' => $referrerAmount]) }}</p>
                </section>
                @endif

                {{-- Link və paylaşma --}}
                <section class="referral-box">
                    @if($blockReason === 'disabled')
                        <div class="referral-locked">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                            <div>
                                <strong>{{ __('referral_disabled_title') }}</strong>
                                <p>{{ __('referral_disabled_text') }}</p>
                            </div>
                        </div>
                    @elseif($blockReason === 'requires_order')
                        <div class="referral-locked">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                            <div>
                                <strong>{{ __('referral_locked_title') }}</strong>
                                <p>{{ __('referral_locked_requires_order') }}</p>
                                <a href="{{ route('home') }}" class="btn btn-dark referral-locked__btn">{{ __('referral_go_shopping') }}</a>
                            </div>
                        </div>
                    @elseif($blockReason === 'limit')
                        <div class="referral-locked">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                            <div>
                                <strong>{{ __('referral_limit_title') }}</strong>
                                <p>{{ __('referral_limit_block_text', ['count' => $limit]) }}</p>
                            </div>
                        </div>
                    @else
                        <h2 class="referral-box__title">{{ __('referral_your_link') }}</h2>

                        <div class="referral-link">
                            <span class="referral-link__text">{{ $link }}</span>
                            <input type="text" id="referralLink" class="referral-link__input" value="{{ $link }}" readonly aria-label="{{ __('referral_your_link') }}">
                            <button type="button" class="btn btn-dark referral-link__share" id="referralShare"
                                    data-title="Parfumshop.az" data-text="{{ $shareText }}" data-url="{{ $link }}" data-copied="{{ __('referral_copied') }}">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
                                <span>{{ __('referral_share') }}</span>
                            </button>
                        </div>

                        <div class="referral-code">
                            <span>{{ __('referral_or_code') }}</span>
                            <button type="button" class="referral-code__value" data-copy="{{ $code }}" data-copied="{{ __('referral_code_copied') }}" title="{{ __('referral_copy') }}">{{ $code }}</button>
                        </div>

                        @if($limitReached)
                            <p class="referral-note referral-note--warning">{{ __('referral_limit_no_reward_text', ['count' => $limit]) }}</p>
                        @endif
                    @endif
                </section>

                {{-- Statistika: ilk dəvətə qədər göstərilmir --}}
                @if($stats['invited'] > 0)
                    <section class="referral-summary">
                        <span>{{ __('referral_stat_invited') }}: <strong>{{ $stats['invited'] }}@if($limit)/{{ $limit }}@endif</strong></span>
                        <span>{{ __('referral_stat_rewarded') }}: <strong>{{ $stats['rewarded'] }}</strong></span>
                        <span>{{ __('referral_stat_earned') }}: <strong>{{ $money($stats['earned']) }} ₼</strong></span>
                    </section>
                @endif

                @if($blockReason !== 'disabled')
                {{-- Necə işləyir --}}
                <section class="referral-section">
                    <h2 class="referral-section__title">{{ __('referral_how_it_works') }}</h2>
                    <ol class="referral-steps">
                        <li>
                            <span class="referral-steps__num">1</span>
                            <div>
                                <strong>{{ __('referral_step1_title') }}</strong>
                                <p>{{ __('referral_step1_text') }}</p>
                            </div>
                        </li>
                        <li>
                            <span class="referral-steps__num">2</span>
                            <div>
                                <strong>{{ __('referral_step2_title') }}</strong>
                                <p>{{ __($isDiscount ? 'referral_step2_text_discount' : 'referral_step2_text_balance', ['amount' => $inviteeAmount]) }}</p>
                            </div>
                        </li>
                        <li>
                            <span class="referral-steps__num">3</span>
                            <div>
                                <strong>{{ __('referral_step3_title') }}</strong>
                                <p>{{ __('referral_step3_text', ['amount' => $referrerAmount]) }}</p>
                            </div>
                        </li>
                    </ol>
                </section>

                @endif

                {{-- Dəvət etdiklərim --}}
                <section class="referral-section">
                    <h2 class="referral-section__title">{{ __('referral_my_invites') }}</h2>

                    @if($invites->isEmpty())
                        <p class="account-card-empty referral-empty">{{ __('referral_no_invites_yet') }}</p>
                    @else
                        <ul class="referral-list">
                            @foreach($invites as $invite)
                                @php
                                    $person = $invite->invitee;
                                    $display = $person ? trim($person->name.' '.mb_substr((string) $person->surname, 0, 1).($person->surname ? '.' : '')) : '—';
                                    [$statusKey, $statusClass] = match (true) {
                                        $invite->status === \App\Models\Customer\CustomerReferral::STATUS_REWARDED => ['referral_status_rewarded', 'success'],
                                        $invite->status === \App\Models\Customer\CustomerReferral::STATUS_REJECTED => ['referral_status_rejected', 'muted'],
                                        ($person?->orders_count ?? 0) > 0 => ['referral_status_ordered', 'warning'],
                                        default => ['referral_status_registered', 'muted'],
                                    };
                                @endphp
                                <li class="referral-row">
                                    <span class="referral-row__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($person?->name ?? '?', 0, 1)) }}</span>
                                    <div class="referral-row__info">
                                        <div class="referral-row__name">{{ $display }}</div>
                                        <time class="referral-row__date" datetime="{{ $invite->created_at->toIso8601String() }}">{{ __('referral_registered_at', ['date' => $invite->created_at->format('d.m.Y')]) }}</time>
                                    </div>
                                    <div class="referral-row__status">
                                        <span class="referral-badge referral-badge--{{ $statusClass }}">{{ __($statusKey) }}</span>
                                        @if($invite->status === \App\Models\Customer\CustomerReferral::STATUS_REWARDED && $invite->referrer_amount)
                                            <span class="referral-row__amount">+{{ $money($invite->referrer_amount) }} ₼</span>
                                        @elseif(!$invite->referrer_rewardable)
                                            <span class="referral-row__hint">{{ __('referral_over_limit') }}</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        @if($invites->hasPages())
                            <div class="main-products__pagination">{{ $invites->links() }}</div>
                        @endif
                    @endif
                </section>

                @if($blockReason !== 'disabled')
                {{-- Şərtlər --}}
                <details class="referral-terms">
                    <summary>{{ __('referral_terms_title') }}</summary>
                    @include('frontend.partials.referral-terms')
                </details>
                @endif
            </div>
        </div>
    </main>
@endsection

@section('page-scripts')
    <script src="{{ asset_v('frontend/js/pages/referral.js') }}" defer></script>
@endsection
