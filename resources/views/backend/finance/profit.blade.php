{{--
  Mənfəət (ProfitReport::summary): seçilən günlər (açılanda — bu gün) — təhvil verilmiş sifarişlər, dövriyyə, maya, xərclər, mənfəət.
--}}
@php
    $html_tag_data = [];
    $title = 'Mənfəət';
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.finance.index') => 'Maliyyə', '' => 'Mənfəət'];
    $money = fn (int $cents) => number_format($cents / 100, 2, '.', ' ').' ₼';
    $months = ['', 'yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avqust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'];
    $date = fn ($d) => $d->day.' '.$months[$d->month].' '.$d->year;
    $period = $from->isSameDay($to) ? $date($from) : $date($from).' — '.$date($to);
    // Tez seçim: [ad, başlanğıc, son]
    $presets = [
        ['Dünən', today()->subDay(), today()->subDay()],
        ['Bu gün', today(), today()],
        ['Bu həftə', today()->startOfWeek(), today()],
        ['Bu ay', today()->startOfMonth(), today()],
        ['Keçən ay', today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
    ];
    $cards = array_values(array_filter([
        ['Sifariş', (string) $orders, 'text-primary', 'Təhvil verilmiş'],
        ['Dövriyyə', $money($sales), 'text-primary', 'Satış qiyməti ilə'],
        $breakage ? ['Hədiyyə kartı qalığı', $money($breakage), 'text-primary', 'İstifadə olunmayan'] : null,
        ['Maya', $money($cost), 'text-danger', 'Depolardan alış'],
        ['Xərclər', $money($expense), 'text-danger', 'Məxaric, kuryer, xammal'],
        ['Mənfəət', $money($net), $net >= 0 ? 'text-success' : 'text-danger', 'Dövriyyə − maya − xərclər'],
    ]));
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
        @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
    </div>

    <form method="GET" class="card mb-3"><div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($presets as [$label, $pFrom, $pTo])
                @php $active = $from->isSameDay($pFrom) && $to->isSameDay($pTo); @endphp
                <a href="{{ route('admin.finance.profit', ['from' => $pFrom->toDateString(), 'to' => $pTo->toDateString()]) }}"
                   class="btn btn-sm {{ $active ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-4 col-xl-2"><label class="form-label" for="pFrom">Başlanğıc</label>
                <input id="pFrom" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}"></div>
            <div class="col-6 col-md-4 col-xl-2"><label class="form-label" for="pTo">Son</label>
                <input id="pTo" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}"></div>
            <div class="col-12 col-md-4 col-xl-2"><button class="btn btn-primary w-100">Filter</button></div>
        </div>
    </div></form>

    <h2 class="small-title">{{ $period }}</h2>
    <div class="row g-2 mb-4">
        @foreach($cards as [$label, $value, $tone, $hint])
            <div class="col-6 col-md-4 col-xl">
                <div class="card h-100"><div class="card-body py-3 px-4">
                    <div class="text-muted text-small mb-1">{{ $label }}</div>
                    <div class="cta-2 {{ $tone }} lh-1 mb-1">{{ $value }}</div>
                    <div class="text-muted text-small">{{ $hint }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-5">
        <div class="col-lg-7">
            <h2 class="small-title">Sifarişlər</h2>
            <div class="card"><div class="card-body">
                @if($list->isEmpty())
                    <div class="text-muted text-center py-4">Bu dövrdə təhvil verilmiş sifariş yoxdur.</div>
                @else
                    <div class="table-responsive"><table class="table mb-0">
                        <thead><tr>
                            <th class="text-muted text-small text-uppercase">Sifariş</th>
                            <th class="text-muted text-small text-uppercase text-end">Dövriyyə</th>
                            <th class="text-muted text-small text-uppercase text-end">Maya</th>
                            <th class="text-muted text-small text-uppercase text-end">Fərq</th>
                        </tr></thead>
                        <tbody>
                        @foreach($list->sortByDesc(fn ($r) => $r['order']->delivered_at) as $row)
                            @php $o = $row['order']; $diff = $row['sale'] - $row['cost']; @endphp
                            <tr>
                                <td>
                                    @if($o->customer_id)<a href="{{ route('admin.crm.order', [$o->customer_id, $o->id]) }}">{{ $o->order_no }}</a>@else{{ $o->order_no }}@endif
                                    <div class="text-alternate text-small">{{ \Illuminate\Support\Carbon::parse($o->delivered_at)->format($from->isSameDay($to) ? 'H:i' : 'd.m H:i') }}@if($o->paymentMethod) · {{ $o->paymentMethod->name_az ?? $o->paymentMethod->code }}@endif</div>
                                </td>
                                <td class="text-end text-nowrap">{{ $money($row['sale']) }}</td>
                                <td class="text-end text-nowrap">{{ $row['cost'] ? $money($row['cost']) : '—' }}</td>
                                <td class="text-end text-nowrap {{ $diff >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($diff) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div></div>
        </div>
        <div class="col-lg-5">
            <h2 class="small-title">Xərclər</h2>
            <div class="card"><div class="card-body">
                @if(!$expenses)
                    <div class="text-muted text-center py-4">Bu dövrdə xərc yazılmayıb.</div>
                @else
                    <table class="table mb-0">
                        <tbody>
                        @foreach($expenses as $row)
                            <tr><td>{{ $row['category']?->name ?? 'Kateqoriyasız' }}</td><td class="text-end text-nowrap">{{ $money($row['total']) }}</td></tr>
                        @endforeach
                        <tr><td class="fw-bold">Cəmi</td><td class="text-end text-nowrap fw-bold">{{ $money($expense) }}</td></tr>
                        </tbody>
                    </table>
                @endif
            </div></div>
            <div class="form-text mt-3">
                Dövriyyə — seçilən günlərdə təhvil verilmiş sifarişlər, hədiyyə kartı ilə ödənilən hissə daxil (əlavə çatdırılma — kuryerin qazancı çıxılıb; hissə-hissədə Ferrum-un köçürdüyü xalis məbləğ; bonusla ödənilən — 0).
                Maya — bu sifarişlər üçün depolardan alınan mal (öz istehsalımızın xammalı ödəniləndə Xərclərdədir).
                Xərclər — seçilən günlərdə ödənilən Məxaric, kuryer xərcləri, xammal. Hədiyyə kartının alışı satış deyil — satış kartla mal veriləndə yaranır; müddəti bitən kart və sifarişdən artıq qalıb yanan hissə "Hədiyyə kartı qalığı"dır.
            </div>
        </div>
    </div>
</div>
@endsection
