{{--
  Satılan mallar (köhnə hesabat proqramındakı kimi): seçilən günlərdə təhvil verilmiş sifarişlərin malları (SoldItemsReport).
  Mənsəb — ödəniş üsulu, Qiy. — satış qiyməti (bonusla — 0), Maya — alış qiyməti, Mənbə — götürüldüyü depo.
--}}
@php
    $html_tag_data = [];
    $title = 'Satılan mallar';
    $breadcrumbs = ['/' => 'ParfumShop', '' => 'Satılan mallar'];
    $money = fn ($v) => number_format((float) $v, 2);
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/finance.css') }}"/>
@endsection

@section('js_page')
    <script src="{{ asset_v('backend/js/finance.js') }}"></script>
@endsection

@section('content')
<div class="container finance-page">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }} <span class="text-muted fs-5">{{ $totals['count'] }}</span></h1>
        @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
    </div>

    {{-- Hazır aralıqlar: digər filtrlər saxlanılır --}}
    <div class="d-flex flex-wrap gap-2 mb-2">
        @foreach($presets as $label => [$pFrom, $pTo])
            @php $on = $from->isSameDay($pFrom) && $to->isSameDay($pTo); @endphp
            <a href="{{ route('admin.sold-items', array_merge(request()->except(['from', 'to', 'page']), ['from' => $pFrom->format('Y-m-d'), 'to' => $pTo->format('Y-m-d')])) }}"
               class="btn btn-sm {{ $on ? 'btn-primary' : 'btn-outline-primary' }}" @if($on) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3" data-ops-filter>
        <input type="date" name="from" class="form-control form-control-sm w-auto" value="{{ $from->format('Y-m-d') }}" aria-label="Başlanğıc tarix" data-auto-submit>
        <span class="text-muted">—</span>
        <input type="date" name="to" class="form-control form-control-sm w-auto" value="{{ $to->format('Y-m-d') }}" aria-label="Son tarix" data-auto-submit>
        <select name="payment" class="form-select form-select-sm w-auto" aria-label="Ödəniş üsulu" data-auto-submit>
            <option value="">Bütün ödəniş üsulları</option>
            @foreach($paymentMethods as $method)
                <option value="{{ $method->code }}" @selected(($f['payment'] ?? '') === $method->code)>{{ $method->name_az }}</option>
            @endforeach
        </select>
        <select name="warehouse" class="form-select form-select-sm w-auto" aria-label="Depo" data-auto-submit>
            <option value="">Bütün depolar</option>
            @foreach($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected((int) ($f['warehouse'] ?? 0) === $warehouse->id)>{{ $warehouse->name_az }}</option>
            @endforeach
        </select>
        <input type="search" name="q" class="form-control form-control-sm w-auto" value="{{ $f['q'] ?? '' }}" placeholder="Brend və ya model" aria-label="Brend və ya model">
        <button class="btn btn-sm btn-primary">Filter</button>
        @if(array_filter(request()->only(['payment', 'warehouse', 'q'])))
            <a href="{{ route('admin.sold-items', request()->only(['from', 'to'])) }}" class="btn btn-sm btn-outline-secondary">Təmizlə</a>
        @endif
    </form>

    {{-- Cəmlər (köhnədəki kimi): say, məbləğ, maya, qazanc --}}
    <div class="row g-2 mb-3">
        @foreach([['Say', $totals['count'], ''], ['Məbləğ', $money($totals['sales']).' AZN', ''], ['Maya', $money($totals['cost']).' AZN', ''],
                  ['Qazanc', $money($totals['profit']).' AZN', $totals['profit'] < 0 ? 'text-danger' : 'text-success']] as [$label, $value, $tone])
            <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-3">
                <div class="text-muted text-small">{{ $label }}</div>
                <div class="fs-5 fw-bold {{ $tone }}">{{ $value }}</div>
            </div></div></div>
        @endforeach
    </div>
    @if($totals['unknown'] > 0)
        <div class="text-warning text-small mb-2">{{ $totals['unknown'] }} ədədin mayası bilinmir (depo hissəsi yoxdur) — maya və qazanca daxil deyil.</div>
    @endif

    <div class="card mb-5"><div class="card-body">
        <div class="table-responsive"><table class="table align-middle mb-3">
            <thead><tr>
                <th class="text-muted text-small text-uppercase">Tarix</th>
                <th class="text-muted text-small text-uppercase">Mənsəb</th>
                <th class="text-muted text-small text-uppercase">Mal</th>
                <th class="text-muted text-small text-uppercase text-end">Miq.</th>
                <th class="text-muted text-small text-uppercase text-end">Qiy.</th>
                <th class="text-muted text-small text-uppercase text-end">Maya</th>
                <th class="text-muted text-small text-uppercase">Mənbə</th>
                <th class="text-muted text-small text-uppercase">Sifariş</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="text-nowrap">{{ $row['delivered_at']->format('d.m.Y') }}<span class="d-block small text-muted">{{ $row['delivered_at']->format('H:i') }}</span></td>
                    <td>{{ $row['order']->paymentMethod?->name_az ?? '—' }}</td>
                    <td>{{ \App\Services\Finance\SoldItemsReport::title($row['item']) }}</td>
                    <td class="text-end">{{ $row['quantity'] }}</td>
                    <td class="text-end text-nowrap">{{ $money($row['price']) }}</td>
                    <td class="text-end text-nowrap">{!! $row['cost'] === null ? '<span class="text-warning">—</span>' : e($money($row['cost'])) !!}</td>
                    <td>{{ $row['warehouse']?->name_az ?? '—' }}</td>
                    <td class="text-nowrap"><a href="{{ route('admin.crm.order', [$row['order']->customer_id, $row['order']->id]) }}">{{ $row['order']->order_no }}</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Bu günlərdə təhvil verilmiş mal yoxdur.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @include('backend._layout.simple-pagination', ['paginator' => $rows])
    </div></div>
</div>
@endsection
