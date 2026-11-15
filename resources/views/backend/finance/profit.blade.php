{{--
  Mənfəət: aylar sütunda (ProfitReport). Satış (təhvil verilmiş sifarişlər) − malın mayası (depolar) = ümumi mənfəət;
  − xərclər (Məxaric, kuryer xərcləri, xammal) = xalis mənfəət.
--}}
@php
    $html_tag_data = [];
    $title = 'Mənfəət';
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.finance.index') => 'Maliyyə', '' => 'Mənfəət'];
    $monthNames = ['', 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'İyun', 'İyul', 'Avqust', 'Sentyabr', 'Oktyabr', 'Noyabr', 'Dekabr'];
    $keys = array_map(fn ($m) => $m->format('Y-m'), $months);
    $multi = count($keys) > 1;
    $cols = count($keys) + ($multi ? 2 : 1);
    $cell = function (int $cents, int $sign = 1) {
        $v = $sign * $cents;
        $tone = $v > 0 ? 'text-success' : ($v < 0 ? 'text-danger' : 'text-muted');
        return '<span class="'.$tone.'">'.($v < 0 ? '−' : '').number_format(abs($v) / 100, 2).' AZN</span>';
    };
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/finance.css') }}"/>
@endsection

@section('content')
<div class="container finance-page">
    <div class="page-title-container">
        <div class="row">
            <div class="col-12 col-sm-6">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
        </div>
    </div>

    <form method="GET" class="card mb-3"><div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-4 col-xl-2"><label class="form-label" for="pFrom">Ay (başlanğıc)</label>
                <input id="pFrom" type="month" name="from" class="form-control" value="{{ $from->format('Y-m') }}"></div>
            <div class="col-6 col-md-4 col-xl-2"><label class="form-label" for="pTo">Ay (son)</label>
                <input id="pTo" type="month" name="to" class="form-control" value="{{ $to->format('Y-m') }}"></div>
            <div class="col-12 col-md-4 col-xl-2"><button class="btn btn-primary w-100">Filter</button></div>
        </div>
        <div class="form-text mt-2">
            Satış — həmin ay təhvil verilmiş sifarişlər (əlavə çatdırılma — kuryerin qazancı çıxılıb; hissə-hissədə Ferrum-un köçürdüyü xalis məbləğ; bonusla ödənilən — 0).
            Malın mayası — bu sifarişlər üçün depolardan alınan mal. Öz istehsalımızın xammalı və xərcləri ödəniləndə "Xərclər"dədir.
            Xərclər — Məxaric, kuryer xərcləri, xammal (ödəniş günü). Ən çox 24 ay.
        </div>
    </div></form>

    <div class="card mb-5"><div class="card-body">
        <div class="table-responsive"><table class="table align-middle mb-0 finance-report">
            <thead><tr>
                <th class="text-muted text-small text-uppercase"></th>
                @foreach($months as $m)<th class="text-muted text-small text-uppercase text-end">{{ $monthNames[$m->month] }} {{ $m->year }}</th>@endforeach
                @if($multi)<th class="text-muted text-small text-uppercase text-end">Cəmi</th>@endif
            </tr></thead>
            <tbody>
            <tr>
                <td>Satış <span class="text-muted text-small">· təhvil verilmiş sifariş</span></td>
                @foreach($keys as $k)<td class="text-end text-nowrap">{!! $cell($sales[$k]) !!}<div class="text-muted text-small">{{ $orders[$k] }} sifariş</div></td>@endforeach
                @if($multi)<td class="text-end text-nowrap fw-bold">{!! $cell(array_sum($sales)) !!}<div class="text-muted text-small fw-normal">{{ array_sum($orders) }} sifariş</div></td>@endif
            </tr>
            <tr>
                <td>Malın mayası <span class="text-muted text-small">· depolardan</span></td>
                @foreach($keys as $k)<td class="text-end text-nowrap">{!! $cell($cost[$k], -1) !!}</td>@endforeach
                @if($multi)<td class="text-end text-nowrap fw-bold">{!! $cell(array_sum($cost), -1) !!}</td>@endif
            </tr>
            <tr class="finance-report__total">
                <td class="fw-bold">Ümumi mənfəət</td>
                @foreach($keys as $k)<td class="text-end text-nowrap fw-bold">{!! $cell($gross[$k]) !!}</td>@endforeach
                @if($multi)<td class="text-end text-nowrap fw-bold">{!! $cell(array_sum($gross)) !!}</td>@endif
            </tr>
            <tr><td colspan="{{ $cols }}" class="finance-report__gap"></td></tr>
            @forelse($expenses as $row)
                <tr>
                    <td>{{ $row['category']?->name ?? 'Kateqoriyasız' }}</td>
                    @foreach($keys as $k)<td class="text-end text-nowrap">{!! $cell($row['values'][$k], -1) !!}</td>@endforeach
                    @if($multi)<td class="text-end text-nowrap fw-bold">{!! $cell($row['total'], -1) !!}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $cols }}" class="text-muted">Bu dövrdə xərc yoxdur.</td></tr>
            @endforelse
            <tr class="finance-report__total">
                <td class="fw-bold">Xərclər</td>
                @foreach($keys as $k)<td class="text-end text-nowrap fw-bold">{!! $cell($expense[$k], -1) !!}</td>@endforeach
                @if($multi)<td class="text-end text-nowrap fw-bold">{!! $cell(array_sum($expense), -1) !!}</td>@endif
            </tr>
            <tr><td colspan="{{ $cols }}" class="finance-report__gap"></td></tr>
            <tr class="finance-report__total">
                <td class="fw-bold">Xalis mənfəət</td>
                @foreach($keys as $k)<td class="text-end text-nowrap fw-bold">{!! $cell($net[$k]) !!}</td>@endforeach
                @if($multi)<td class="text-end text-nowrap fw-bold">{!! $cell(array_sum($net)) !!}</td>@endif
            </tr>
            </tbody>
        </table></div>
    </div></div>
</div>
@endsection
