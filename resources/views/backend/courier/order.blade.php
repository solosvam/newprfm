{{--
  Kuryer → sifariş (telefon üçün). Anbarlardan götürmə (Götürdüm / Ödədim / Problem),
  hədiyyəlik qablaşdırma, müştəri, çatdırılma (Çatdırılmaya başladım → Ünvandayam → Təhvil verdim).
--}}
@use('App\Models\Procurement\OrderItemAllocation', 'Part')
@php
    $html_tag_data = [];
    $title = $order->order_no;
    $breadcrumbs = [route('admin.dashboard') => 'Tapşırıqlarım', '' => $order->order_no];
    $code = $order->status?->code;
    $money = fn ($cents) => number_format($cents / 100, 2).' AZN';
    $steps = ['courier_assigned' => 'Toplama', 'sent' => 'Yoldayam', 'at_address' => 'Ünvandayam', 'delivered' => 'Təhvil verildi'];
    $reached = array_search($code, array_keys($steps), true);
    $a = $order->address;
    $addressLine = collect([$a?->city, $a?->address])->filter()->implode(', ');
    $addressExtra = collect([
        $a?->building ? 'Bina '.$a->building : null, $a?->entrance ? 'Blok '.$a->entrance : null,
        $a?->floor ? 'Mərtəbə '.$a->floor : null, $a?->apartment ? 'Mənzil '.$a->apartment : null,
    ])->filter()->implode(' · ');
    $phone = $order->customer?->mobile;
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/courier.css') }}">
@endsection

@section('content')
<div class="container courier-page">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
        @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
    </div>
    @include('backend.procurement.feedback')

    <ol class="courier-steps mb-4" aria-label="Mərhələlər">
        @foreach($steps as $stepCode => $label)
            <li class="{{ $reached !== false && $loop->index <= $reached ? 'is-done' : '' }} {{ $stepCode === $code ? 'is-current' : '' }}">{{ $label }}</li>
        @endforeach
    </ol>

    {{-- 1. Anbarlardan götürmə --}}
    <h2 class="small-title">Anbarlardan götürmə</h2>
    @foreach($byWarehouse as $rows)
        @php $w = $rows->first()['a']->warehouse; @endphp
        <div class="card mb-3 courier-wh"><div class="card-body">
            <div class="courier-wh__head">
                <div>
                    <div class="courier-wh__name">{{ $w->name_az }}</div>
                    @if($w->address)<a class="courier-wh__addr" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($w->address) }}" target="_blank" rel="noopener">{{ $w->address }}</a>@endif
                    @if($w->contact_name)<div class="text-muted small">{{ $w->contact_name }}</div>@endif
                </div>
                @if($w->phone)<a href="tel:{{ $w->phone }}" class="btn btn-outline-primary btn-icon btn-icon-only" aria-label="Zəng et"><i data-acorn-icon="phone" data-acorn-size="18"></i></a>@endif
            </div>

            @foreach($rows as ['item' => $item, 'a' => $part])
                @php
                    $cost = (int) round($part->quantity * (float) $part->unit_cost * 100);
                    $left = $cost - ($paid[$part->id] ?? 0);
                    $canAct = $code === 'courier_assigned';                               // götürmə, problem
                    $canPay = in_array($code, ['courier_assigned', 'sent', 'at_address'], true); // ödəniş sonra da
                @endphp
                <div class="courier-part courier-part--{{ $part->status }}">
                    <div class="courier-part__top">
                        <div>
                            <div class="courier-part__name">{{ $item->product?->name }}</div>
                            <div class="text-muted small">{{ collect([$item->product?->brand?->name, $item->variant?->size?->name_az])->filter()->implode(' · ') }}</div>
                        </div>
                        <div class="courier-part__qty">× {{ $part->quantity }}</div>
                    </div>
                    <div class="courier-part__info">
                        <span class="badge {{ ['picked' => 'bg-success', 'problem' => 'bg-danger'][$part->status] ?? 'bg-outline-primary' }}">{{ $part->label() }}</span>
                        @if($left > 0)
                            <span>Anbara ödəniş: <b>{{ $money($left) }}</b></span>
                        @else
                            <span class="text-success">Anbara ödənilib</span>
                        @endif
                    </div>
                    @if($part->status === Part::PROBLEM)
                        <div class="courier-part__problem">⚠ {{ $part->logs->last()?->note }} — operator həll edir.</div>
                    @endif

                    @if(($canAct || $canPay) && $part->status !== Part::PROBLEM)
                        <div class="courier-part__actions">
                            @if($canAct && $part->status !== Part::PICKED)
                                <form method="POST" action="{{ route('admin.courier.pick', [$order, $part->id]) }}" data-once>@csrf<button class="btn btn-primary w-100">Götürdüm</button></form>
                            @endif
                            @if($canPay && $left > 0)
                                <details class="courier-more">
                                    <summary class="btn btn-outline-primary w-100">Ödədim</summary>
                                    <form method="POST" action="{{ route('admin.courier.pay', [$order, $part->id]) }}" data-once class="courier-more__form">
                                        @csrf
                                        <label class="form-label">Anbara ödədiyiniz məbləğ, AZN</label>
                                        <input type="number" name="amount" min="0.01" max="{{ number_format($left / 100, 2, '.', '') }}" step="0.01" inputmode="decimal" class="form-control" value="{{ number_format($left / 100, 2, '.', '') }}" required>
                                        <button class="btn btn-primary w-100 mt-2">Təsdiqlə</button>
                                    </form>
                                </details>
                            @endif
                            @if($canAct && $part->status !== Part::PICKED)
                                <details class="courier-more">
                                    <summary class="btn btn-outline-danger w-100">Problem</summary>
                                    <form method="POST" action="{{ route('admin.courier.problem', [$order, $part->id]) }}" data-once class="courier-more__form">
                                        @csrf
                                        @foreach(Part::PROBLEM_TYPES as $value => $label)
                                            <label class="form-check"><input class="form-check-input" type="radio" name="problem_type" value="{{ $value }}" required><span class="form-check-label">{{ $label }}</span></label>
                                        @endforeach
                                        <textarea name="note" rows="2" maxlength="2000" class="form-control mt-2" placeholder="Qısa izah"></textarea>
                                        <button class="btn btn-danger w-100 mt-2">Operatora bildir</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div></div>
    @endforeach

    @if($order->gift_wrap)
        <div class="alert alert-info">🎁 <strong>Hədiyyəlik qablaşdırma:</strong> məhsulları bükün və loqolu çantaya qoyun.</div>
    @endif

    {{-- 2. Müştəri --}}
    <h2 class="small-title mt-4">Müştəri</h2>
    <div class="card mb-3"><div class="card-body">
        <div class="courier-client">
            <div>
                <div class="courier-wh__name">{{ trim($order->customer?->name.' '.$order->customer?->surname) }}</div>
                @if($addressLine)<a class="courier-wh__addr" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($addressLine) }}" target="_blank" rel="noopener">{{ $addressLine }}</a>@endif
                @if($addressExtra)<div class="small">{{ $addressExtra }}</div>@endif
                @if($a?->address_note)<div class="small text-muted">{{ $a->address_note }}</div>@endif
                @if($order->customer_note)<div class="small mt-1"><b>Qeyd:</b> {{ $order->customer_note }}</div>@endif
            </div>
            @if($phone)<a href="tel:+{{ preg_replace('/\D+/', '', $phone) }}" class="btn btn-outline-primary btn-icon btn-icon-only" aria-label="Müştəriyə zəng"><i data-acorn-icon="phone" data-acorn-size="18"></i></a>@endif
        </div>
        <div class="courier-collect {{ $collect > 0 ? 'is-cash' : 'is-paid' }}">
            @if($collect > 0)
                <span>Müştəridən alınacaq (nağd)</span><b>{{ number_format($collect, 2) }} AZN</b>
            @else
                <span>Ödəniş</span><b>Ödənilib — pul alınmır</b>
            @endif
        </div>
    </div></div>

    {{-- 3. Çatdırılma --}}
    <div class="courier-actions">
        @if($code === 'courier_assigned')
            @if($deliveryBlock)<div class="text-muted small mb-2 text-center">{{ $deliveryBlock }}</div>@endif
            <form method="POST" action="{{ route('admin.courier.start', $order) }}" data-once>@csrf
                <button class="btn btn-primary btn-lg w-100" @disabled($deliveryBlock)>Çatdırılmaya başladım</button>
            </form>
        @elseif(in_array($code, ['sent', 'at_address'], true))
            @if($code === 'sent')
                <form method="POST" action="{{ route('admin.courier.arrive', $order) }}" data-once>@csrf<button class="btn btn-outline-primary btn-lg w-100 mb-2">Ünvandayam</button></form>
            @endif
            <form method="POST" action="{{ route('admin.courier.deliver', $order) }}" data-once class="courier-deliver">
                @csrf
                {{-- Məbləğ yazılmır: göstərilən məbləğ göndərilir, server cari məbləğlə tutuşdurur (arada dəyişibsə — xəta) --}}
                @if($collect > 0)
                    <input type="hidden" name="collected" value="{{ number_format($collect, 2, '.', '') }}">
                    <button class="btn btn-success btn-lg w-100">{{ number_format($collect, 2) }} AZN nağd aldım — təhvil verdim</button>
                    <div class="text-muted small text-center mt-1">Məbləğ fərqlidirsə, aşağıdan "Problem" bildirin.</div>
                @else
                    <button class="btn btn-success btn-lg w-100">Təhvil verdim</button>
                @endif
            </form>
            <details class="courier-more mt-2">
                <summary class="btn btn-outline-danger w-100">Problem (müştəri yoxdur, qəbul etmir...)</summary>
                <form method="POST" action="{{ route('admin.courier.delivery-problem', $order) }}" data-once class="courier-more__form">
                    @csrf
                    <textarea name="note" rows="3" maxlength="2000" class="form-control" placeholder="Nə baş verdi?" required></textarea>
                    <button class="btn btn-danger w-100 mt-2">Operatora bildir</button>
                </form>
            </details>
        @elseif($code === 'delivered')
            <div class="alert alert-success text-center mb-0">Sifariş təhvil verilib.</div>
        @endif
    </div>
</div>
@endsection

@section('js_page')
    <script src="{{ asset_v('backend/js/courier.js') }}"></script>
@endsection
