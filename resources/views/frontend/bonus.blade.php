@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/bonus.css') }}">
@endsection

@section('content')
    @php
        $balance = (float) (auth()->user()->bonus_balance ?? 0);
    @endphp

    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('bonus_bonus_history')])

            <div class="account-panel account-panel--flush">
                <h1 class="account-panel-title">{{ __('bonus_bonus_history') }}</h1>

                <div class="bonus-balance-card">
                    <span class="bonus-balance-card__label">{{ __('bonus_bonus_balance') }}</span>
                    <strong class="bonus-balance-card__amount">{{ number_format($balance, 2) }} ₼</strong>
                </div>

                @if($transactions->isEmpty())
                    <p class="account-card-empty">{{ __('bonus_no_bonus_transactions_yet') }}</p>
                @else
                    <ul class="bonus-list">
                        @foreach($transactions as $transaction)
                            @php
                                $amount = (float) $transaction->amount;
                                $isEarn = $amount >= 0;
                                $title = match ($transaction->type) {
                                    'earn'  => __('orders_bonus_earned'),
                                    'spend' => __('bonus_bonus_spent'),
                                    'register' => __('bonus_registration'),
                                    default => $transaction->note ?: __('bonus_bonus_transaction'),
                                };
                            @endphp
                            <li @class(['bonus-row', 'bonus-row--earn' => $isEarn, 'bonus-row--spend' => ! $isEarn])>
                                <span class="bonus-row__icon" aria-hidden="true">{{ $isEarn ? '+' : '−' }}</span>

                                <div class="bonus-row__info">
                                    <div class="bonus-row__title">{{ $title }}</div>
                                    <div class="bonus-row__meta">
                                        @if($transaction->order)
                                            <a href="{{ route('order.details', $transaction->order) }}">№ {{ $transaction->order->order_no }}</a>
                                        @endif
                                        <time datetime="{{ $transaction->created_at->toIso8601String() }}">{{ $transaction->created_at->format('d.m.Y, H:i') }}</time>
                                    </div>
                                </div>

                                <div class="bonus-row__amount">
                                    {{ $isEarn ? '+' : '−' }}{{ number_format(abs($amount), 2) }} ₼
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    @if($transactions->hasPages())
                        <div class="main-products__pagination">{{ $transactions->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </main>
@endsection
