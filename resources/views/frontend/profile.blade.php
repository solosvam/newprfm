@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
@endsection

@section('content')
    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar')

            <div class="account-cards">
                @php
                    // Müştəri statusu (daxili mərhələlər → "Hazırlanır") → badge rəngi və izləmə addımı
                    $statusClass = fn ($code) => match ($code) {
                        'delivered' => 'delivered',
                        'cancelled' => 'cancelled',
                        'sent', 'courier', 'at_address' => 'shipped',
                        'preparing' => 'processing',
                        default => 'received',
                    };
                    $trackSteps = [
                        1 => __('profile_track_placed'),
                        2 => __('profile_track_preparing'),
                        3 => __('profile_track_on_the_way'),
                        4 => __('profile_track_delivered'),
                    ];
                    $trackStep = fn ($code) => match ($code) {
                        'delivered' => 4,
                        'sent', 'courier', 'at_address' => 3,
                        'preparing' => 2,
                        default => 1,
                    };
                    $bonusBalance = (float) (auth()->user()->bonus_balance ?? 0);
                @endphp

                {{-- Bonus balansı --}}
                <div class="account-card account-card-bonus">
                    <h2>{{ __('profile_your_bonus_balance') }}</h2>
                    <p class="account-card-amount">{{ number_format($bonusBalance, 2) }} ₼</p>
                    <a href="{{ route('profile.bonus') }}" class="account-card-link">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        <span>{{ __('profile_view_your_bonus_history') }}</span>
                    </a>
                </div>

                {{-- Son sifariş --}}
                @if($latestOrder)
                    @php
                        $latestStatus = $latestOrder->status?->forCustomer();
                        $latestItems = $latestOrder->items->take(3);
                        $latestMore = $latestOrder->items->count() - $latestItems->count();
                    @endphp
                    <a href="{{ route('order.details', $latestOrder) }}" class="account-card account-link-card">
                        <div class="account-link-card__head">
                            <h2>{{ __('profile_your_latest_order') }}</h2>
                            <svg class="account-link-card__arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </div>
                        <div class="account-order-mini__top">
                            <span class="account-order-mini__no">№ {{ $latestOrder->order_no }}</span>
                            <span class="order-status order-status--{{ $statusClass($latestStatus?->code) }}">{{ $latestStatus?->localized_name ?? __('orders_order_received') }}</span>
                        </div>
                        <div class="account-order-mini__bottom">
                            <div class="account-order-mini__products">
                                @foreach($latestItems as $item)
                                    @php $image = $item->product?->images?->first(); @endphp
                                    <span class="account-order-mini__product" title="{{ $item->product?->name }}">
                                        @if($image)
                                            <img src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="{{ $item->product?->name }}" loading="lazy">
                                        @else
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="7" y="8" width="10" height="13" rx="2"/><path d="M10 8V5h4v3"/></svg>
                                        @endif
                                    </span>
                                @endforeach
                                @if($latestMore > 0)
                                    <span class="account-order-mini__product account-order-mini__product--more">+{{ $latestMore }}</span>
                                @endif
                            </div>
                            <div class="account-order-mini__side">
                                <strong>{{ number_format($latestOrder->total, 2) }} ₼</strong>
                                <time datetime="{{ $latestOrder->created_at->toIso8601String() }}">{{ $latestOrder->created_at->format('d.m.Y') }}</time>
                            </div>
                        </div>
                    </a>
                @else
                    <div class="account-card account-empty-card">
                        <h2>{{ __('profile_your_latest_order') }}</h2>
                        <p class="account-card-empty">{{ __('profile_you_have_no_orders_yet') }}</p>
                        <a href="{{ route('home') }}" class="account-inline-link">{{ __('referral_go_shopping') }}</a>
                    </div>
                @endif

                {{-- Sifarişim haradadır? --}}
                @if($activeOrder)
                    @php
                        $activeStatus = $activeOrder->status?->forCustomer();
                        $step = $trackStep($activeStatus?->code);
                    @endphp
                    <a href="{{ route('order.details', $activeOrder) }}" class="account-card account-link-card">
                        <div class="account-link-card__head">
                            <h2>{{ __('profile_where_is_my_order') }}</h2>
                            <svg class="account-link-card__arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </div>
                        <div class="account-order-mini__top">
                            <span class="account-order-mini__no">№ {{ $activeOrder->order_no }}</span>
                            <span class="order-status order-status--{{ $statusClass($activeStatus?->code) }}">{{ $activeStatus?->localized_name ?? __('orders_order_received') }}</span>
                        </div>
                        <ol class="account-track" aria-label="{{ __('profile_where_is_my_order') }}">
                            @foreach($trackSteps as $n => $label)
                                <li @class(['is-done' => $n < $step, 'is-current' => $n === $step])>
                                    <span class="account-track__dot" aria-hidden="true"></span>
                                    <span class="account-track__label">{{ $label }}</span>
                                </li>
                            @endforeach
                        </ol>
                        @if($activeCount > 1)
                            <span class="account-link-card__text">{{ __('profile_more_active_orders', ['count' => $activeCount - 1]) }}</span>
                        @endif
                    </a>
                @else
                    <div class="account-card account-empty-card">
                        <h2>{{ __('profile_where_is_my_order') }}</h2>
                        <p class="account-card-empty">{{ __('profile_no_active_orders') }}</p>
                        @if($latestOrder)
                            <a href="{{ route('orders') }}" class="account-inline-link">{{ __('profile_view_all_orders') }}</a>
                        @endif
                    </div>
                @endif

                @php
                    $customer = auth()->user();
                    $creditComplete = (bool) $customer->creditProfile?->isComplete();
                    $favoritesCount = $customer->favoriteProducts()->count();
                    $referralSettings = app(\App\Services\Referral\ReferralSettings::class);
                    $money = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
                    $arrow = '<svg class="account-link-card__arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
                @endphp

                {{-- Hissəli ödəniş məlumatları --}}
                <a href="{{ route('profile.credit') }}" class="account-card account-link-card">
                    <div class="account-link-card__head">
                        <h2>{{ __('credit_title') }}</h2>
                        {!! $arrow !!}
                    </div>
                    @if($creditComplete)
                        <span class="account-status account-status--ok">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            {{ __('profile_credit_complete') }}
                        </span>
                        <p class="account-link-card__text">{{ __('profile_credit_complete_hint') }}</p>
                    @else
                        <span class="account-status account-status--warn">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 7v6M12 17h.01"/></svg>
                            {{ __('profile_credit_incomplete') }}
                        </span>
                        <p class="account-link-card__text">{{ __('profile_credit_incomplete_hint') }}</p>
                    @endif
                </a>

                {{-- Bəyəndiyim ətirlər --}}
                <a href="{{ route('profile.wishlist') }}" class="account-card account-link-card">
                    <div class="account-link-card__head">
                        <h2>{{ __('wishlist_my_favorites') }}</h2>
                        {!! $arrow !!}
                    </div>
                    <p class="account-link-card__value">{{ $favoritesCount }}</p>
                    <p class="account-link-card__text">{{ $favoritesCount > 0 ? __('profile_favorites_hint') : __('profile_favorites_empty') }}</p>
                </a>

                {{-- Dostunu dəvət et (yalnız proqram aktivdirsə) --}}
                @if($referralSettings->enabled())
                    <a href="{{ route('profile.referral') }}" class="account-card account-link-card">
                        <div class="account-link-card__head">
                            <h2>{{ __('referral_title') }}</h2>
                            {!! $arrow !!}
                        </div>
                        <p class="account-link-card__value">+{{ $money($referralSettings->referrerAmount()) }} ₼</p>
                        <p class="account-link-card__text">{{ __('profile_referral_hint', ['invitee' => $money($referralSettings->inviteeAmount()), 'referrer' => $money($referralSettings->referrerAmount())]) }}</p>
                    </a>
                @endif
            </div>
        </div>
    </main>
@endsection
