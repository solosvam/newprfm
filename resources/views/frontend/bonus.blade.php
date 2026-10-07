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
                    {{-- Sonsuz scroll: siyahının sonuna çatanda növbəti səhifə yüklənir (frontend/js/infinite-list.js) --}}
                    <ul class="bonus-list" data-infinite-list data-next="{{ $transactions->nextPageUrl() }}">
                        @include('frontend.partials.bonus-rows')
                    </ul>

                    @include('frontend.partials.infinite-sentinel', ['paginator' => $transactions, 'links' => $transactions->links()])
                @endif
            </div>
        </div>
    </main>
@endsection
