{{--
  Hesablar: şirkətin hesabları (Nağd / Kart / Bank / Onlayn) və şəxslər. Sətrə klik — hesabın səhifəsi (redaktə, hərəkətlər).
  Cəm yalnız şirkətin hesablarıdır: şəxsin qalığı şirkətin pulu deyil.
--}}
@php
    $html_tag_data = [];
    $title = 'Hesablar';
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.finance.index') => 'Maliyyə', '' => 'Hesablar'];
    $money = fn ($cents) => ($cents < 0 ? '−' : '').number_format(abs($cents) / 100, 2).' AZN';
    $ownTotal = $accounts->where('active', true)->whereIn('type', \App\Models\Finance\FinanceAccount::OWN)->sum(fn ($a) => $balances[$a->id] ?? 0);
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
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }} <span class="text-muted fs-5">{{ $accounts->count() }}</span></h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                <button type="button" class="btn btn-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#accountModal">
                    <span>Yarat</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    <form method="GET" class="mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="inactive" value="1" id="accInactive" @checked($inactive) onchange="this.form.submit()">
            <label class="form-check-label" for="accInactive">Deaktiv hesabları göstər</label>
        </div>
    </form>

    <div class="card mb-5"><div class="card-body">
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr>
                <th class="text-muted text-small text-uppercase">ID</th>
                <th class="text-muted text-small text-uppercase">Ad</th>
                <th class="text-muted text-small text-uppercase">Açıqlama</th>
                <th class="text-muted text-small text-uppercase">Növ</th>
                <th class="text-muted text-small text-uppercase text-end">Qalıq<span class="d-block text-primary">{{ $money($ownTotal) }}</span></th>
            </tr></thead>
            <tbody>
            @foreach($accounts as $a)
                @php $b = $balances[$a->id] ?? 0; @endphp
                <tr class="{{ $a->active ? '' : 'text-muted' }}">
                    <td>{{ $a->id }}</td>
                    <td><a href="{{ route('admin.finance.account', $a) }}">{{ $a->name }}</a>
                        @unless($a->active)<span class="badge bg-outline-muted ms-1">deaktiv</span>@endunless</td>
                    <td class="text-alternate finance-note">{{ $a->description ?: '—' }}</td>
                    <td>{{ $a->typeLabel() }}</td>
                    <td class="text-end text-nowrap fw-bold">
                        @if($a->type === 'person')
                            <span class="{{ $b < 0 ? 'text-danger' : ($b > 0 ? 'text-warning' : 'text-muted') }}">{{ number_format(abs($b) / 100, 2) }} AZN</span>
                            <span class="d-block small fw-normal text-muted">{{ $b < 0 ? 'borcumuz' : ($b > 0 ? 'bizə borcludur' : 'hesablaşılıb') }}</span>
                        @else
                            <span class="{{ $b < 0 ? 'text-danger' : ($b === 0 ? 'text-muted' : '') }}">{{ $money($b) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div></div>
</div>

@include('backend.finance.partials.account-modal', ['editAccount' => null, 'editBalance' => null])
@endsection
