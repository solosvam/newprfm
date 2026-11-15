@php
    $title = 'Hədiyyə kartı HK'.$card->id;
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.gift-cards.index') => 'Hədiyyə kartları', '' => 'HK'.$card->id];
    $unused = in_array($card->status, [\App\Models\GiftCard\GiftCard::ACTIVE, \App\Models\GiftCard\GiftCard::EXPIRED], true);
@endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container d-flex align-items-start justify-content-between">
        <div>
            <h1 class="mb-0 pb-0 display-4">{{ $title }}</h1>
            @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('admin.gift-cards.index') }}">Geri</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card"><div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">Kod</dt><dd class="col-7 font-monospace">{{ $card->formattedCode() ?? '— (ödəniş gözlənilir)' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Məbləğ</dt><dd class="col-7">{{ number_format((float) $card->amount, 2) }} AZN</dd>
                    <dt class="col-5 text-muted fw-normal">Status</dt><dd class="col-7">{{ $card->statusLabel() }}</dd>
                    <dt class="col-5 text-muted fw-normal">Alan müştəri</dt>
                    <dd class="col-7">
                        @if($card->customer)
                            <a href="{{ route('admin.crm.customer', $card->customer_id) }}">{{ trim($card->customer->name.' '.$card->customer->surname) }}</a> · {{ $card->customer->mobile }}
                        @else — @endif
                    </dd>
                    <dt class="col-5 text-muted fw-normal">Ödənilib</dt><dd class="col-7">{{ $card->paid_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Etibarlıdır</dt><dd class="col-7">{{ $card->expires_at?->format('d.m.Y') ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">İstifadə olunduğu sifariş</dt>
                    <dd class="col-7">
                        @if($card->usedOrder)
                            <a href="{{ route('admin.crm.order', [$card->usedOrder->customer_id, $card->usedOrder->id]) }}">{{ $card->usedOrder->order_no }}</a>
                            · {{ number_format((float) $card->usedOrder->gift_card_amount, 2) }} AZN
                        @else — @endif
                    </dd>
                    <dt class="col-5 text-muted fw-normal">Mətn</dt><dd class="col-7">{{ $card->message ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Kimdən</dt><dd class="col-7">{{ $card->sender_name ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Müştəri səhifəsi</dt><dd class="col-7"><a href="{{ route('gift-card.show', $card->share_token) }}" target="_blank">Aç</a></dd>
                </dl>
            </div></div>

            @can('giftcard.manage')
                @if($unused)
                    <div class="card mt-4"><div class="card-body">
                        <h5>Müddəti uzat</h5>
                        <form method="POST" action="{{ route('admin.gift-cards.extend', $card) }}" class="d-flex gap-2">
                            @csrf
                            <input type="date" name="expires_at" class="form-control" min="{{ now()->addDay()->toDateString() }}"
                                   value="{{ ($card->expires_at && $card->expires_at->isFuture() ? $card->expires_at : now())->copy()->addMonths(3)->toDateString() }}" required>
                            <button class="btn btn-outline-primary text-nowrap">Uzat</button>
                        </form>
                    </div></div>

                    @if($card->payments->firstWhere('status', \App\Models\Payment\Payment::PAID))
                        <div class="card mt-4"><div class="card-body">
                            <h5>Pulu qaytar</h5>
                            <p class="small text-muted">Kart istifadə olunmayıb. {{ number_format((float) $card->amount, 2) }} AZN müştərinin ödədiyi karta (Birbank) qaytarılır, hədiyyə kartı bağlanır.</p>
                            <form method="POST" action="{{ route('admin.gift-cards.refund', $card) }}"
                                  onsubmit="return confirm('{{ number_format((float) $card->amount, 2) }} AZN karta qaytarılsın və hədiyyə kartı bağlansın? Bunu geri qaytarmaq olmur.')">
                                @csrf
                                <button class="btn btn-outline-danger">Pulu karta qaytar</button>
                            </form>
                        </div></div>
                    @endif
                @endif
            @endcan
        </div>

        <div class="col-lg-6">
            <div class="card"><div class="card-body">
                <h5>Tarixçə</h5>
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                    @foreach($card->events as $event)
                        <tr>
                            <td class="text-nowrap small text-muted">{{ $event->created_at?->format('d.m.Y H:i') }}</td>
                            <td>
                                {{ $event->label() }}
                                @if($event->note)<div class="small text-muted">{{ $event->note }}</div>@endif
                            </td>
                            <td class="small text-muted">{{ $event->user?->name }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div></div>

            <div class="card mt-4"><div class="card-body">
                <h5>Ödənişlər</h5>
                <table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th>Məbləğ</th><th>Status</th><th>Kart</th><th>Tarix</th></tr></thead>
                    <tbody>
                    @forelse($card->payments as $payment)
                        <tr>
                            <td>{{ $payment->id }}</td>
                            <td>{{ number_format((float) $payment->amount, 2) }}</td>
                            <td>{{ $payment->status }}</td>
                            <td>{{ $payment->card_pan ?? '—' }}</td>
                            <td class="small">{{ $payment->created_at?->format('d.m.Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">Ödəniş cəhdi yoxdur.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
</div>
@endsection
