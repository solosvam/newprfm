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
            'owner' => $b < 0 ? ['Sahibkara borcumuz', $money($b), 'text-danger'] : ['Qalıq', $money($b), ''],
            'warehouse' => ($d = $debts[$account->warehouse_id] ?? 0) > 0 ? ['Anbara borc', $money($d), 'text-danger'] : ($d < 0 ? ['Avans', $money($d), 'text-success'] : ['Borc yoxdur', '0.00 AZN', 'text-muted']),
            'expense' => ['Xərclənib', $money($b), ''],
            default => ['Qalıq', ($b < 0 ? '−' : '').$money($b), $b < 0 ? 'text-danger' : ''],
        };
    };
    $groups = [
        'Şirkətin pulu' => ['cash', 'bank', 'online'],
        'Kuryerlər' => ['courier'],
        'Anbarlar' => ['warehouse'],
        'Sahibkar və xərclər' => ['owner', 'expense'],
    ];
    $selectable = $accounts->where('type', '!=', 'customer');
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
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#newMovement">
                    <span>Yeni əməliyyat</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    {{-- Hesablar: kliklə → yalnız o hesabın hərəkətləri --}}
    @foreach($groups as $groupTitle => $types)
        @php $groupAccounts = $accounts->whereIn('type', $types); @endphp
        @continue($groupAccounts->isEmpty())
        <h2 class="small-title">{{ $groupTitle }}</h2>
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

{{-- Yeni əməliyyat: növ seçiləndə hesab siyahıları uyğun tiplərlə süzülür (finance.js) --}}
<div class="modal modal-right fade" id="newMovement" tabindex="-1" aria-labelledby="newMovementTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.finance.store') }}" data-movement-form>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="newMovementTitle">Yeni əməliyyat</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label" for="mvKind">Əməliyyat</label>
                <select id="mvKind" name="kind" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach(MoneyMovement::MANUAL as $kind)
                        <option value="{{ $kind }}" data-from="{{ implode(',', MoneyMovement::KINDS[$kind][1]) }}" data-to="{{ implode(',', MoneyMovement::KINDS[$kind][2]) }}" @selected(old('kind') === $kind)>{{ MoneyMovement::KINDS[$kind][0] }}</option>
                    @endforeach
                </select></div>
            <div class="mb-3"><label class="form-label" for="mvFrom">Pul haradan çıxdı</label>
                <select id="mvFrom" name="from_account_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach($selectable as $a)<option value="{{ $a->id }}" data-type="{{ $a->type }}" @selected((int) old('from_account_id') === $a->id)>{{ $a->name }} · {{ $a->typeLabel() }}</option>@endforeach
                </select></div>
            <div class="mb-3"><label class="form-label" for="mvTo">Pul haraya getdi</label>
                <select id="mvTo" name="to_account_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach($selectable as $a)<option value="{{ $a->id }}" data-type="{{ $a->type }}" @selected((int) old('to_account_id') === $a->id)>{{ $a->name }} · {{ $a->typeLabel() }}</option>@endforeach
                </select></div>
            <div class="mb-3"><label class="form-label" for="mvAmount">Məbləğ, AZN</label><input id="mvAmount" type="number" name="amount" min="0.01" step="0.01" class="form-control" value="{{ old('amount') }}" required></div>
                <div class="mb-3"><label class="form-label" for="mvAt">Real tarix <small class="text-muted">(boş — indi)</small></label><input id="mvAt" type="datetime-local" name="occurred_at" class="form-control" value="{{ old('occurred_at') }}"></div>
            <label class="form-label" for="mvNote" data-note-label>Qeyd</label>
            <textarea id="mvNote" name="note" rows="2" maxlength="2000" class="form-control">{{ old('note') }}</textarea>
            <div class="form-text mt-2">Anbara ödəniş — anbarın kartına klikləyin. Kuryerdən pulu təhvil almaq — kuryerin kartına klikləyin.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Qeydə al</button></div>
    </form></div>
</div>

@include('backend.finance.partials.reverse-modal')
@endsection
