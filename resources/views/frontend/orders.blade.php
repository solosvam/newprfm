@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
@endsection

@section('content')
    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('orders_history')])

            @if($orders->isEmpty())
                <div class="account-panel">
                    <h1 class="account-panel-title">{{ __('orders_history') }}</h1>
                    <p class="account-card-empty">{{ __('profile_you_have_no_orders_yet') }}</p>
                </div>
            @else
                <div class="order-history">
                    <div class="order-history__header">
                        <h1 class="account-panel-title">{{ __('orders_history') }}</h1>
                        <span class="order-history__count">{{ $orders->total() }}</span>
                    </div>

                    {{-- Sonsuz scroll: siyahının sonuna çatanda növbəti səhifə yüklənir (frontend/js/infinite-list.js) --}}
                    <div class="order-history__list" data-infinite-list data-next="{{ $orders->nextPageUrl() }}">
                        @include('frontend.partials.order-cards')
                    </div>
                    @include('frontend.partials.infinite-sentinel', ['paginator' => $orders, 'links' => $orders->links('frontend.includes.pagination')])

                </div>
            @endif
        </div>
    </main>
@endsection
