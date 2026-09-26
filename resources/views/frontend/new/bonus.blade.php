@extends('frontend.new.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="account-layout">
                @include('frontend.new.partials.cabinet-sidebar', ['pageTitle' => __('bonus_bonus_history')])

                <div class="account-panel">
                    <div class="bonus-head">
                        <h1 class="account-panel-title" style="margin:0;">{{ __('bonus_bonus_history') }}</h1>
                        <div class="bonus-balance">
                            {{ __('bonus_bonus_balance') }}
                            <strong>{{ number_format(auth()->user()->bonus_balance ?? 0, 2) }} ₼</strong>
                        </div>
                    </div>

                    @if($transactions->isEmpty())
                        <p class="account-card-empty">{{ __('bonus_no_bonus_transactions_yet') }}</p>
                    @else
                        <div class="bonus-table-wrap">
                            <table class="bonus-table">
                                <thead>
                                <tr>
                                    <th>{{ __('orders_order_no') }}</th>
                                    <th>{{ __('bonus_date') }}</th>
                                    <th>{{ __('bonus_transaction') }}</th>
                                    <th>{{ __('bonus_bonus_amount') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($transactions as $transaction)
                                    <tr>
                                        <td>
                                            @if($transaction->order)
                                                <a href="{{ route('profile.orders.show', $transaction->order) }}">{{ $transaction->order->order_no }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $transaction->created_at->format('d.m.Y, H:i') }}</td>
                                        <td>
                                            {{ $transaction->type === 'earn' ? __('orders_bonus_earned') : ($transaction->type === 'spend' ? __('bonus_bonus_spent') : ($transaction->note ?: __('bonus_bonus_transaction'))) }}
                                        </td>
                                        <td class="bonus-amount {{ $transaction->amount >= 0 ? 'earn' : 'spend' }}">
                                            {{ $transaction->amount >= 0 ? '+' : '' }}{{ number_format($transaction->amount, 2) }} ₼
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($transactions->hasPages())
                            <div class="main-products__pagination">{{ $transactions->links() }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection
