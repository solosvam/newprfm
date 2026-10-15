@use('App\Models\Finance\MoneyMovement')
@php
    $html_tag_data = [];
    $title = 'Kassa və hesablar';
    $breadcrumbs = ['/' => 'ParfumShop', '' => 'Kassa'];
    $money = fn ($cents) => number_format(abs($cents) / 100, 2).' AZN';
    // Qalığın mənası hesab tipinə görə
    $describe = function ($account) use ($balances, $debts, $money) {
        $b = $balances[$account->id] ?? 0;
        return match ($account->type) {
            'courier' => $b > 0 ? ['Təhvil verməlidir', $money($b), 'text-warning'] : ($b < 0 ? ['Kuryerə borcumuz', $money($b), 'text-danger'] : ['Hesablaşılıb', '0.00 AZN', 'text-muted']),
            // Şəxs → Kassa köçürməsi qalığı mənfi edir: şəxsə borcluyuq; Kassa → Şəxs — əksinə
            'person' => $b < 0 ? ['Borcumuz', $money($b), 'text-danger'] : ($b > 0 ? ['Bizə borcludur', $money($b), 'text-warning'] : ['Hesablaşılıb', '0.00 AZN', 'text-muted']),
            'warehouse' => ($d = $debts[$account->warehouse_id] ?? 0) > 0 ? ['Anbara borc', $money($d), 'text-danger'] : ($d < 0 ? ['Avans', $money($d), 'text-success'] : ['Borc yoxdur', '0.00 AZN', 'text-muted']),
            default => [$account->typeLabel().' · qalıq', ($b < 0 ? '−' : '').$money($b), $b < 0 ? 'text-danger' : ''],
        };
    };
    $groups = [
        'Hesablar' => \App\Models\Finance\FinanceAccount::OWN,
        'Şəxslər' => ['person'],
        'Kuryerlər' => ['courier'],
        'Anbarlar' => ['warehouse'],
    ];
    $ownTotal = $accounts->whereIn('type', \App\Models\Finance\FinanceAccount::OWN)->sum(fn ($a) => $balances[$a->id] ?? 0);
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/datatables.min.css') }}"/>
    <link rel="stylesheet" href="{{ asset_v('backend/css/finance.css') }}"/>
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/vendor/datatables.min.js') }}"></script>
    <script src="{{ asset('backend/js/cs/scrollspy.js') }}"></script>
    <script src="{{ asset('backend/js/cs/datatable.extend.js') }}"></script>
    <script src="{{ asset('backend/js/plugins/datatable.boxedvariations.js') }}"></script>
    <script src="{{ asset_v('backend/js/finance.js') }}"></script>
@endsection

@section('content')
<div class="container finance-page">
    <div class="page-title-container">
        <div class="row">
            <div class="col-12 col-sm-6">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end gap-2 flex-wrap">
                <a href="{{ route('admin.finance.categories') }}" class="btn btn-outline-primary w-100 w-sm-auto">Kateqoriyalar</a>
                <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#accountModal">
                    <span>Yeni hesab</span><i data-acorn-icon="plus"></i>
                </button>
                <button type="button" class="btn btn-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#newMovement">
                    <span>Yeni əməliyyat</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    {{-- Hesablar: kliklə → yalnız o hesabın hərəkətləri --}}
    @foreach($groups as $groupTitle => $types)
        @php
            // Anbarlar: yalnız haqq-hesabı açıq olanlar (borc və ya avans) — sıfır qalıqlı anbar kartı göstərilmir
            $groupAccounts = $accounts->whereIn('type', $types)->reject(fn ($a) => $a->type === 'warehouse' && ($debts[$a->warehouse_id] ?? 0) === 0)
                ->sortBy(fn ($a) => array_search($a->type, $types, true));
        @endphp
        @continue($groupAccounts->isEmpty())
        <h2 class="small-title">{{ $groupTitle }}@if($groupTitle === 'Hesablar') <span class="text-muted">· cəmi {{ $ownTotal < 0 ? '−' : '' }}{{ $money($ownTotal) }}</span>@endif</h2>
        <div class="finance-accounts mb-4">
            @foreach($groupAccounts as $account)
                @php [$label, $value, $tone] = $describe($account); @endphp
                <a href="{{ route('admin.finance.account', $account) }}" class="card finance-account">
                    <div class="card-body">
                        <div class="finance-account__name">{{ $account->name }}</div>
                        <div class="finance-account__label">{{ $label }}</div>
                        <div class="finance-account__value {{ $tone }}">{{ $value }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    @endforeach

    <section class="scroll-section">
        <h2 class="small-title">Pul hərəkətləri</h2>
        <div class="card mb-5"><div class="card-body">
            @include('backend.finance.partials.movements')
        </div></div>
    </section>
</div>

@include('backend.finance.partials.movement-modal')
@include('backend.finance.partials.account-modal', ['editAccount' => null, 'editBalance' => null])
@include('backend.finance.partials.reverse-modal')
@endsection
