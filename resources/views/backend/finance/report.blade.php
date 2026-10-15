{{--
  Pul axını: aylar sütunda, kateqoriyalar sətirdə (CashFlowReport). Məxaric mənfi, mədaxil müsbət.
  Şəxslərlə hesablaşma (borc alıb-vermək) maliyyə nəticəsinə qarışmır — ayrıca blokdadır.
--}}
@php
    $html_tag_data = [];
    $title = 'Pul axını';
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.finance.index') => 'Kassa', '' => 'Pul axını'];
    $monthNames = ['', 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'İyun', 'İyul', 'Avqust', 'Sentyabr', 'Oktyabr', 'Noyabr', 'Dekabr'];
    $keys = array_map(fn ($m) => $m->format('Y-m'), $months);
    $multi = count($keys) > 1;
    // Məbləğ: işarə ilə; sıfır — solğun
    $cell = function (int $cents, int $sign = 1) {
        $v = $sign * $cents;
        $tone = $v > 0 ? 'text-success' : ($v < 0 ? 'text-danger' : 'text-muted');
        return '<span class="'.$tone.'">'.($v < 0 ? '−' : '').number_format(abs($v) / 100, 2).' AZN</span>';
    };
    $sections = [['Məxaric', $expense, 'expense', -1, 'Ümumi məxaric'], ['Mədaxil', $income, 'income', 1, 'Ümumi mədaxil']];
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
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                <a href="{{ route('admin.finance.categories') }}" class="btn btn-outline-primary w-100 w-sm-auto">Kateqoriyalar</a>
            </div>
        </div>
    </div>

    <form method="GET" class="card mb-3"><div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4 col-xl-3"><label class="form-label" for="repAccount">Balans</label>
                <select id="repAccount" name="account" class="form-select">
                    <option value="">Bütün balanslar</option>
                    @foreach($accounts as $a)<option value="{{ $a->id }}" @selected($account?->id === $a->id)>{{ $a->name }} · {{ $a->typeLabel() }}</option>@endforeach
                </select></div>
            <div class="col-6 col-md-4 col-xl-2"><label class="form-label" for="repFrom">Ay (başlanğıc)</label>
                <input id="repFrom" type="month" name="from" class="form-control" value="{{ $from->format('Y-m') }}"></div>
            <div class="col-6 col-md-4 col-xl-2"><label class="form-label" for="repTo">Ay (son)</label>
                <input id="repTo" type="month" name="to" class="form-control" value="{{ $to->format('Y-m') }}"></div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="form-check"><input class="form-check-input" type="checkbox" name="hide_empty" value="1" id="repEmpty" @checked($hideEmpty)>
                    <label class="form-check-label" for="repEmpty">Boş sətirləri gizlət</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="hide_transfers" value="1" id="repTransfers" @checked($hideTransfers)>
                    <label class="form-check-label" for="repTransfers">Köçürmələri gizlət</label></div>
            </div>
            <div class="col-12 col-md-6 col-xl-2"><button class="btn btn-primary w-100">Filter</button></div>
        </div>
        <div class="form-text mt-2">Ən çox 24 ay. Bütün balanslar: şirkətin hesabları və kuryerlər; şəxs öz cibindən ödədiyi xərc də xərcdir.</div>
    </div></form>

    <div class="card mb-5"><div class="card-body">
        <div class="table-responsive"><table class="table align-middle mb-0 finance-report">
            <thead><tr>
                <th class="text-muted text-small text-uppercase">Kateqoriya</th>
                @foreach($months as $m)<th class="text-muted text-small text-uppercase text-end">{{ $monthNames[$m->month] }} {{ $m->year }}</th>@endforeach
                @if($multi)<th class="text-muted text-small text-uppercase text-end">Cəmi</th>@endif
            </tr></thead>
            <tbody>
            @foreach($sections as [$label, $rows, $key, $sign, $totalLabel])
                @foreach($rows as $row)
                    <tr>
                        <td class="{{ $row['child'] ? 'ps-5' : '' }}">{{ $row['child'] ? '— ' : '' }}{{ $row['category']?->name ?? 'Kateqoriyasız' }}</td>
                        @foreach($keys as $k)<td class="text-end text-nowrap">{!! $cell($row['values'][$k], $sign) !!}</td>@endforeach
                        @if($multi)<td class="text-end text-nowrap fw-bold">{!! $cell($row['total'], $sign) !!}</td>@endif
                    </tr>
                @endforeach
                <tr class="finance-report__total">
                    <td class="fw-bold">{{ $totalLabel }}</td>
                    @foreach($keys as $k)<td class="text-end text-nowrap fw-bold">{!! $cell($totals[$key][$k], $sign) !!}</td>@endforeach
                    @if($multi)<td class="text-end text-nowrap fw-bold">{!! $cell(array_sum($totals[$key]), $sign) !!}</td>@endif
                </tr>
                <tr><td colspan="{{ count($keys) + ($multi ? 2 : 1) }}" class="finance-report__gap"></td></tr>
            @endforeach
            <tr class="finance-report__total">
                <td class="fw-bold">Maliyyə nəticəsi</td>
                @foreach($keys as $k)<td class="text-end text-nowrap fw-bold">{!! $cell($totals['result'][$k]) !!}</td>@endforeach
                @if($multi)<td class="text-end text-nowrap fw-bold">{!! $cell(array_sum($totals['result'])) !!}</td>@endif
            </tr>
            @if(array_filter($persons['in']) || array_filter($persons['out']))
                <tr><td colspan="{{ count($keys) + ($multi ? 2 : 1) }}" class="finance-report__gap"></td></tr>
                <tr><td colspan="{{ count($keys) + ($multi ? 2 : 1) }}" class="text-muted text-small text-uppercase">Şəxslərlə hesablaşma (nəticəyə daxil deyil)</td></tr>
                @foreach([['Şəxslərdən alınıb', 'in', 1], ['Şəxslərə verilib', 'out', -1], ['Fərq', 'net', 1]] as [$rowLabel, $rowKey, $rowSign])
                    <tr class="{{ $rowKey === 'net' ? 'finance-report__total' : '' }}">
                        <td class="{{ $rowKey === 'net' ? 'fw-bold' : '' }}">{{ $rowLabel }}</td>
                        @foreach($keys as $k)<td class="text-end text-nowrap">{!! $cell($persons[$rowKey][$k], $rowSign) !!}</td>@endforeach
                        @if($multi)<td class="text-end text-nowrap fw-bold">{!! $cell(array_sum($persons[$rowKey]), $rowSign) !!}</td>@endif
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table></div>
    </div></div>
</div>
@endsection
