{{--
  Əməliyyatlar (Clopos kimi): bütün pul hərəkətləri, filtrlərlə. Növ / Balans / Kateqoriya — açılan siyahılar (bağlananda tətbiq olunur),
  qalan filtrlər — "Filtrlər" panelində. İstiqamət: ↓ şirkətə gəldi, ↑ şirkətdən çıxdı, ⇄ köçürmə (cəmə daxil deyil).
--}}
@php
    $html_tag_data = [];
    $title = 'Əməliyyatlar';
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.finance.index') => 'Maliyyə', '' => 'Əməliyyatlar'];
    $types = ['in' => 'Mədaxil', 'out' => 'Məxaric', 'transfer' => 'Köçürmə'];
    $groups = ['Hesablar' => \App\Models\Finance\FinanceAccount::OWN, 'Şəxslər' => ['person'], 'Kuryerlər' => ['courier']];
    $count = fn ($n) => $n ? ' ('.$n.')' : '';
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
        <div class="row">
            <div class="col-12 col-sm-6">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }} <span class="text-muted fs-5">{{ $movements->total() }}</span></h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                <button type="button" class="btn btn-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#newMovement">
                    <span>Yeni əməliyyat</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3" data-ops-filter>
        <input type="date" name="from" class="form-control form-control-sm w-auto" value="{{ $from?->format('Y-m-d') }}" aria-label="Başlanğıc tarix" data-auto-submit>
        <span class="text-muted">—</span>
        <input type="date" name="to" class="form-control form-control-sm w-auto" value="{{ $to?->format('Y-m-d') }}" aria-label="Son tarix" data-auto-submit>

        {{-- Növ --}}
        <div class="dropdown" data-apply-on-close>
            <button class="btn btn-sm {{ $f['types'] ? 'btn-primary' : 'btn-outline-primary' }} dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">Növ{{ $count(count($f['types'])) }}</button>
            <div class="dropdown-menu p-3">
                @foreach($types as $value => $label)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="type[]" value="{{ $value }}" id="opType-{{ $value }}" @checked(in_array($value, $f['types'], true))>
                        <label class="form-check-label" for="opType-{{ $value }}">{{ $label }}</label></div>
                @endforeach
            </div>
        </div>

        {{-- Balans --}}
        <div class="dropdown" data-apply-on-close>
            <button class="btn btn-sm {{ $f['accounts'] ? 'btn-primary' : 'btn-outline-primary' }} dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">Balans{{ $count(count($f['accounts'])) }}</button>
            <div class="dropdown-menu p-3 finance-filter-menu">
                <input type="search" class="form-control form-control-sm mb-2" placeholder="Axtar" data-filter-list aria-label="Balans axtar">
                @foreach($groups as $groupTitle => $groupTypes)
                    @php $list = $all->whereIn('type', $groupTypes); @endphp
                    @continue($list->isEmpty())
                    <div class="text-muted text-small text-uppercase mt-2 mb-1" data-filter-group>{{ $groupTitle }}</div>
                    @foreach($list as $a)
                        <div class="form-check" data-filter-item><input class="form-check-input" type="checkbox" name="account[]" value="{{ $a->id }}" id="opAcc-{{ $a->id }}" @checked(in_array($a->id, $f['accounts'], true))>
                            <label class="form-check-label" for="opAcc-{{ $a->id }}">{{ $a->name }}</label></div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- Kateqoriya: üst kateqoriya seçiləndə alt kateqoriyaları da daxildir --}}
        <div class="dropdown" data-apply-on-close>
            <button class="btn btn-sm {{ $f['categories'] ? 'btn-primary' : 'btn-outline-primary' }} dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">Kateqoriya{{ $count(count($f['categories'])) }}</button>
            <div class="dropdown-menu p-3 finance-filter-menu">
                <input type="search" class="form-control form-control-sm mb-2" placeholder="Axtar" data-filter-list aria-label="Kateqoriya axtar">
                @foreach($categories as $c)
                    <div class="form-check {{ $c->parent_id ? 'ms-4' : '' }}" data-filter-item><input class="form-check-input" type="checkbox" name="category[]" value="{{ $c->id }}" id="opCat-{{ $c->id }}" @checked(in_array($c->id, $f['categories'], true))>
                        <label class="form-check-label" for="opCat-{{ $c->id }}">{{ $c->name }}</label></div>
                @endforeach
            </div>
        </div>

        @php $extra = ($f['q'] !== '') + ($f['order'] !== '') + ($f['user'] !== '') + count($f['counterparty']) + $f['reversed']; @endphp
        <button type="button" class="btn btn-sm {{ $extra ? 'btn-primary' : 'btn-outline-primary' }} btn-icon btn-icon-start" data-bs-toggle="offcanvas" data-bs-target="#opsMore" aria-controls="opsMore">
            <i data-acorn-icon="filter" data-acorn-size="14"></i><span>Filtrlər{{ $count($extra) }}</span>
        </button>
        @if($active)<a href="{{ route('admin.finance.operations') }}" class="btn btn-sm btn-link">Sıfırla</a>@endif

        {{-- Əlavə filtrlər --}}
        <div class="offcanvas offcanvas-end" tabindex="-1" id="opsMore" aria-labelledby="opsMoreTitle">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="opsMoreTitle">Filtrlər</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Bağla"></button>
            </div>
            <div class="offcanvas-body">
                <div class="mb-3"><label class="form-label" for="opQ">Açıqlama</label>
                    <input id="opQ" type="search" name="q" class="form-control" value="{{ $f['q'] }}" maxlength="100"></div>
                <div class="mb-3"><label class="form-label" for="opOrder">Sifariş №</label>
                    <input id="opOrder" type="search" name="order" class="form-control" value="{{ $f['order'] }}" maxlength="40"></div>
                <div class="mb-3"><label class="form-label" for="opUser">İcraçı</label>
                    <select id="opUser" name="user" class="form-select">
                        <option value="">Hamısı</option>
                        <option value="system" @selected($f['user'] === 'system')>Sistem (avtomatik)</option>
                        @foreach($users as $u)<option value="{{ $u->id }}" @selected($f['user'] === (string) $u->id)>{{ $u->full_name }}</option>@endforeach
                    </select></div>
                @foreach(['warehouse' => ['Anbar', 'warehouse'], 'courier' => ['Kuryer', 'courier']] as $name => [$label, $type])
                    @php $selected = (int) request()->query($name); @endphp
                    <div class="mb-3"><label class="form-label" for="op-{{ $name }}">{{ $label }}</label>
                        <select id="op-{{ $name }}" name="{{ $name }}" class="form-select">
                            <option value="">Hamısı</option>
                            @foreach($all->where('type', $type) as $a)<option value="{{ $a->id }}" @selected($selected === $a->id)>{{ $a->name }}</option>@endforeach
                        </select></div>
                @endforeach
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" name="reversed" value="1" id="opReversed" @checked($f['reversed'])>
                    <label class="form-check-label" for="opReversed">Əks olunanları göstər</label>
                </div>
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </div>
    </form>

    <div class="card mb-5"><div class="card-body">
        <div class="table-responsive"><table class="table align-middle mb-3 finance-ops">
            <thead><tr>
                <th class="text-muted text-small text-uppercase">ID</th>
                <th class="text-muted text-small text-uppercase text-end">Məbləğ
                    <span class="d-block {{ $total < 0 ? 'text-danger' : 'text-success' }}">{{ $total < 0 ? '−' : '' }}{{ number_format(abs($total), 2) }} AZN</span></th>
                <th class="text-muted text-small text-uppercase">Əməliyyat vaxtı</th>
                <th class="text-muted text-small text-uppercase">Yaradılıb</th>
                <th class="text-muted text-small text-uppercase">Balans</th>
                <th class="text-muted text-small text-uppercase">Kateqoriya</th>
                <th class="text-muted text-small text-uppercase">İstinad</th>
                <th class="text-muted text-small text-uppercase">Açıqlama</th>
                <th class="text-muted text-small text-uppercase">İcraçı</th>
                <th></th>
            </tr></thead>
            <tbody>
            @forelse($movements as $m)
                @php
                    $dir = $m->direction();
                    [$arrow, $tone] = ['in' => ['↓', 'text-success'], 'out' => ['↑', 'text-danger'], 'transfer' => ['⇄', 'text-muted']][$dir];
                    $balance = match ($dir) { 'in' => $m->to, 'out' => $m->from, default => null };
                    $warehouse = collect([$m->from, $m->to])->first(fn ($a) => $a?->type === 'warehouse');
                    $link = fn ($a) => '<a href="'.route('admin.finance.account', $a).'">'.e($a->name).'</a>';
                @endphp
                <tr class="{{ $m->kind === 'reversal' || $m->reversedBy ? 'finance-row--reversed' : '' }}">
                    <td class="text-nowrap">{{ $m->id }} <span class="{{ $tone }} fw-bold" title="{{ ['in' => 'Mədaxil', 'out' => 'Məxaric', 'transfer' => 'Köçürmə'][$dir] }}">{{ $arrow }}</span></td>
                    <td class="text-end text-nowrap fw-bold {{ $dir === 'transfer' ? '' : $tone }}">{{ number_format((float) $m->amount, 2) }}</td>
                    <td class="text-nowrap">{{ $m->occurred_at->format('d.m.Y') }}<span class="d-block small text-muted">{{ $m->occurred_at->format('H:i') }}</span></td>
                    <td class="text-nowrap text-alternate">{{ $m->created_at?->format('d.m.Y') }}<span class="d-block small text-muted">{{ $m->created_at?->format('H:i') }}</span>
                        @if($m->isBackdated())<span class="d-block small text-warning">sonradan yazılıb</span>@endif</td>
                    <td>@if($balance){!! $link($balance) !!}@else{!! $m->from ? $link($m->from) : '—' !!} → {!! $m->to ? $link($m->to) : '—' !!}@endif</td>
                    <td>{{ $m->category?->name ?? $m->kindLabel() }}
                        @if($m->kind === 'reversal')<span class="d-block small text-danger">əks əməliyyat #{{ $m->reversal_of_id }}</span>@endif
                        @if($m->reversedBy)<span class="d-block small text-danger">əks olunub (#{{ $m->reversedBy->id }})</span>@endif</td>
                    <td>@if($m->order)<a href="{{ route('admin.crm.order', [$m->order->customer_id, $m->order->id]) }}#settlements">{{ $m->order->order_no }}</a>@elseif($warehouse && $warehouse->id !== $balance?->id){!! $link($warehouse) !!}@else — @endif</td>
                    <td class="text-alternate finance-note">{{ $m->note ?: '—' }}</td>
                    <td class="text-alternate">{{ $m->user?->full_name ?? 'Sistem' }}</td>
                    <td class="text-end">
                        @if($m->kind !== 'reversal' && !$m->reversedBy)
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseMovement"
                                    data-action="{{ route('admin.finance.reverse', $m) }}" data-title="#{{ $m->id }} · {{ $m->label() }} · {{ number_format((float) $m->amount, 2) }} AZN">Əks et</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">Bu filtrlərlə əməliyyat yoxdur.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @include('backend._layout.simple-pagination', ['paginator' => $movements])
    </div></div>
</div>

@include('backend.finance.partials.movement-modal')
@include('backend.finance.partials.reverse-modal')
@endsection
