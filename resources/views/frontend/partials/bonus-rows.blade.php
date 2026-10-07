{{-- Bonus tarixçəsinin sətirləri (frontend/bonus + sonsuz scroll AJAX cavabı) --}}
@foreach($transactions as $transaction)
    @php
        $amount = (float) $transaction->amount;
        $isEarn = $amount >= 0;
        $title = match ($transaction->type) {
            'earn'  => __('orders_bonus_earned'),
            'spend' => __('bonus_bonus_spent'),
            'register' => __('bonus_registration'),
            'expire' => __('bonus_expired'),
            'refund' => __('bonus_refund'),
            'referral' => __('bonus_referral'),
            default => $transaction->note ?: __('bonus_bonus_transaction'),
        };
    @endphp
    <li @class(['bonus-row', 'bonus-row--earn' => $isEarn, 'bonus-row--spend' => ! $isEarn])>
        <span class="bonus-row__icon" aria-hidden="true">{{ $isEarn ? '+' : '−' }}</span>

        <div class="bonus-row__info">
            <div class="bonus-row__title">{{ $title }}</div>
            <div class="bonus-row__meta">
                @if($transaction->order && $transaction->order->customer_id === $transaction->customer_id)
                    <a href="{{ route('order.details', $transaction->order) }}">№ {{ $transaction->order->order_no }}</a>
                @elseif($transaction->order)
                    {{-- Dəvət bonusu: sifariş dəvət olunan dostundur — nömrə yox, dostun adı --}}
                    <span>{{ $transaction->order->customer?->full_name }}</span>
                @endif
                <time datetime="{{ $transaction->created_at->toIso8601String() }}">{{ $transaction->created_at->format('d.m.Y, H:i') }}</time>
                @if($transaction->expires_at && !$transaction->expired_at && $transaction->expires_at->isFuture())
                    <span class="bonus-row__expiry">{{ __('bonus_valid_until', ['date' => $transaction->expires_at->format('d.m.Y')]) }}</span>
                @endif
            </div>
        </div>

        <div class="bonus-row__amount">
            {{ $isEarn ? '+' : '−' }}{{ number_format(abs($amount), 2) }} ₼
        </div>
    </li>
@endforeach
