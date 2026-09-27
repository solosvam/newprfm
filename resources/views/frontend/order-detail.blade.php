@extends('frontend.layouts.app')

@section('content')
    @php
        $statusKey = $order->status?->code ?? 'received';

        // Kart: sütun adlarını öz cədvəlinizə uyğunlaşdırın
        $cardMask = $order->card_mask;
        $cardBrand = $order->card_brand ?? match (true) {
            str_starts_with((string) $cardMask, '4') => 'Visa',
            preg_match('/^(5[1-5]|2[2-7])/', (string) $cardMask) === 1 => 'Mastercard',
            default => null,
        };
        $cardFormatted = $cardMask ? trim(chunk_split(str_replace('*', '•', $cardMask), 4, ' ')) : null;

        $credit = $order->creditApplication;

        $addressExtra = collect([
            $order->address?->building  ? __('orders_building') . ' ' . $order->address->building : null,
            $order->address?->entrance  ? __('orders_entrance') . ' ' . $order->address->entrance : null,
            $order->address?->floor     ? __('orders_floor') . ' ' . $order->address->floor : null,
            $order->address?->apartment ? __('orders_apartment') . ' ' . $order->address->apartment : null,
        ])->filter()->implode(' · ');
    @endphp

    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('orders_order_details')])

            <div class="order-detail">
                {{-- Header --}}
                <header class="od-header">
                    <div class="od-header__title">
                        <h1>{{ __('orders_order') }} № {{ $order->order_no }}</h1>
                        <span class="order-status order-status--{{ $statusKey }}">
                            {{ $order->status?->localized_name ?? __('orders_order_received') }}
                        </span>
                    </div>
                    <p class="od-header__meta">
                        <time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('d.m.Y, H:i') }}</time>
                        <span>{{ $order->items->sum('quantity') }} {{ __('orders_products') }}</span>
                        @if($order->paymentMethod)<span>{{ $order->paymentMethod->localized_name }}</span>@endif
                    </p>
                </header>

                <div class="od-grid">
                    <div class="od-main">
                        {{-- Products --}}
                        <section class="od-card od-products">
                            <div class="od-card__head">
                                <h2 class="od-card__title">{{ __('orders_items') }}</h2>
                                <span class="od-card__aside">{{ $order->items->count() }}</span>
                            </div>
                            <ul class="od-items">
                                @foreach($order->items as $item)
                                    @php
                                        $image = $item->product?->images?->first();
                                        $size = $item->variant?->size?->{'name_' . app()->getLocale()} ?: $item->variant?->size?->name_az;
                                    @endphp
                                    <li class="od-item">
                                        <div class="od-item__img">
                                            @if($image)
                                                <img src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="{{ $item->product?->name }}" loading="lazy">
                                            @endif
                                        </div>
                                        <div class="od-item__info">
                                            <div class="od-item__name">{{ $item->product?->name ?? __('orders_product') }}</div>
                                            <div class="od-item__meta">
                                                @if($item->product?->brand)<span>{{ $item->product->brand->name }}</span>@endif
                                                @if($size)<span>{{ $size }}</span>@endif
                                            </div>
                                        </div>
                                        <div class="od-item__price">
                                            <div class="od-item__total">{{ number_format($item->total, 2) }} ₼</div>
                                            <div class="od-item__unit">
                                                {{ $item->quantity }} × {{ number_format($item->unit_price, 2) }} ₼
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>

                        {{-- Delivery --}}
                        <section class="od-card od-delivery">
                            <div class="od-card__head">
                                <h2 class="od-card__title">{{ __('orders_delivery_details') }}</h2>
                            </div>
                            <div class="od-card__body">
                                <div class="od-address">{{ $order->address?->label ?? '-' }}</div>
                                @if($addressExtra)<div class="od-address__extra">{{ $addressExtra }}</div>@endif
                                @if($order->customer_note)
                                    <div class="od-note"><strong>{{ __('orders_note') }}</strong> {{ $order->customer_note }}</div>
                                @endif
                            </div>
                        </section>
                    </div>

                    <aside class="od-aside">
                        {{-- Timeline --}}
                        <section class="od-card od-timeline-card">
                            <div class="od-card__head">
                                <h2 class="od-card__title">{{ __('orders_timeline') }}</h2>
                            </div>
                            <div class="od-card__body">
                                <ol class="od-timeline">
                                    @forelse($order->statusLogs as $log)
                                        <li class="od-timeline__item order-status--{{ $log->status?->code }} {{ $loop->first ? 'is-current' : '' }}">
                                            <span class="od-timeline__dot"></span>
                                            <div class="od-timeline__title">{{ $log->status?->localized_name }}</div>
                                            <time class="od-timeline__time" datetime="{{ $log->created_at->toIso8601String() }}">
                                                {{ $log->created_at->format('d.m.Y, H:i') }}
                                            </time>
                                            @if($log->note)<p class="od-timeline__note">{{ $log->note }}</p>@endif
                                        </li>
                                    @empty
                                        <li class="od-timeline__item order-status--received is-current">
                                            <span class="od-timeline__dot"></span>
                                            <div class="od-timeline__title">{{ __('orders_order_received') }}</div>
                                            <time class="od-timeline__time">{{ $order->created_at->format('d.m.Y, H:i') }}</time>
                                        </li>
                                    @endforelse
                                </ol>
                            </div>
                        </section>

                        {{-- Payment --}}
                        <section class="od-card od-payment">
                            <div class="od-card__head">
                                <h2 class="od-card__title">{{ __('orders_payment_details') }}</h2>
                            </div>
                            <div class="od-card__body">
                                <div class="od-pay-method">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
                                    {{ $order->paymentMethod?->localized_name ?? '-' }}
                                </div>

                                @if($order->paymentMethod?->code === 'card_online'
                                    && in_array($order->payment_status, ['failed', 'cancelled'], true))
                                    <div class="od-payment-retry">
                                        <p>{{ match(app()->getLocale()) {
                                            'ru' => 'Оплата не прошла. Вы можете повторить попытку без нового заказа.',
                                            'en' => 'Payment was unsuccessful. You can retry without placing a new order.',
                                            default => 'Ödəniş alınmadı. Yeni sifariş yaratmadan yenidən cəhd edə bilərsiniz.',
                                        } }}</p>
                                        <form method="POST" action="{{ route('payment.birbank.start', $order) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-dark">
                                                {{ match(app()->getLocale()) {
                                                    'ru' => 'Оплатить повторно',
                                                    'en' => 'Retry payment',
                                                    default => 'Yenidən ödə',
                                                } }}
                                            </button>
                                        </form>
                                    </div>
                                @endif

                                @if($cardFormatted)
                                    <div class="od-paycard">
                                        <span class="od-paycard__brand">{{ $cardBrand ?? __('orders_card') }}</span>
                                        <span class="od-paycard__no">{{ $cardFormatted }}</span>
                                    </div>
                                @endif

                                @if($credit)
                                    <div class="od-credit">
                                        <div class="od-credit__grid">
                                            <div>
                                                <span>{{ __('orders_installment_period') }}</span>
                                                <strong>{{ $credit->credit_period }} {{ __('orders_months') }}</strong>
                                            </div>
                                            <div>
                                                <span>{{ __('orders_monthly_payment') }}</span>
                                                <strong>{{ number_format($credit->monthly, 2) }} ₼</strong>
                                            </div>
                                            <div>
                                                <span>{{ __('orders_installment_total') }}</span>
                                                <strong>{{ number_format($credit->total, 2) }} ₼</strong>
                                            </div>
                                        </div>
                                        @if(Route::has('cabinet.installments'))
                                            <a class="od-credit__link" href="{{ route('cabinet.installments') }}">
                                                {{ __('orders_installment_details') }} →
                                            </a>
                                        @endif
                                    </div>
                                @endif

                                <div class="od-totals">
                                    <div class="od-row"><span>{{ __('orders_subtotal') }}</span><span>{{ number_format($order->subtotal, 2) }} ₼</span></div>
                                    @if($order->discount > 0)
                                        <div class="od-row od-row--discount"><span>{{ __('orders_discount') }}</span><span>−{{ number_format($order->discount, 2) }} ₼</span></div>
                                    @endif
                                    @if($order->bonus_used > 0)
                                        <div class="od-row od-row--discount"><span>{{ __('orders_paid_with_bonuses') }}</span><span>−{{ number_format($order->bonus_used, 2) }} ₼</span></div>
                                    @endif
                                    <div class="od-row od-row--total"><span>{{ __('orders_total') }}</span><span>{{ number_format($order->total, 2) }} ₼</span></div>
                                </div>

                                @if($order->bonus_earned > 0)
                                    <div class="od-bonus">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>
                                        +{{ number_format($order->bonus_earned, 2) }} ₼ {{ __('orders_bonus_earned_note') }}
                                    </div>
                                @endif
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </div>
    </main>
@endsection
