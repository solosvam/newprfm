{{-- İstehsal → tədarükçü: borc, alışlar, ödənişlər, "Borcu ödə" --}}
@use('App\Models\Production\MaterialPurchase')
@use('App\Services\Production\OwnProduction')
@php
    $title = $supplier->name;
    $breadcrumbs = ['/admin' => 'ParfumShop', route('admin.production.suppliers') => 'Tədarükçülər', '#' => $title];
@endphp
@extends('backend.layout', ['title' => $title])

@section('content')
<div class="container">
    <div class="page-title-container">
        <div class="row">
            <div class="col-12 col-sm-6">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                <button type="button" class="btn btn-outline-primary w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#payModal" @disabled($debt <= 0)>Borcu ödə</button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    <div class="row g-3 mb-5">
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body">
                <div class="text-muted text-small">Borcumuz</div>
                <div class="cta-3 {{ $debt > 0 ? 'text-danger' : 'text-primary' }}">{{ number_format($debt / 100, 2) }} ₼</div>
                <div class="form-text">Borca alışlar ({{ number_format($purchases->where('payment', 'debt')->sum(fn ($p) => (float) $p->total), 2) }} ₼) − sonradan ödənilən.</div>
            </div></div>
            <div class="card"><div class="card-body">
                <form method="POST" action="{{ route('admin.production.supplier.update', $supplier) }}" data-once>
                    @csrf
                    <div class="mb-3"><label class="form-label" for="sName">Ad</label>
                        <input type="text" id="sName" name="name" class="form-control" maxlength="150" value="{{ old('name', $supplier->name) }}" required></div>
                    <div class="mb-3"><label class="form-label" for="sDesc">Əlaqə / qeyd</label>
                        <input type="text" id="sDesc" name="description" class="form-control" maxlength="255" value="{{ old('description', $supplier->description) }}"></div>
                    <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="sActive" name="active" value="1" @checked($supplier->active)>
                        <label class="form-check-label" for="sActive">Aktiv</label></div>
                    <button class="btn btn-primary">Yenilə</button>
                </form>
            </div></div>
        </div>
        <div class="col-lg-8">
            <h2 class="small-title">Alışlar</h2>
            <div class="card mb-3"><div class="card-body">
                @if($purchases->isEmpty())
                    <div class="text-muted text-center py-3">Alış yoxdur.</div>
                @else
                    <div class="table-responsive"><table class="table mb-0">
                        <thead><tr>
                            <th class="text-muted text-small text-uppercase">Tarix</th>
                            <th class="text-muted text-small text-uppercase">Xammal</th>
                            <th class="text-muted text-small text-uppercase text-end">Məbləğ</th>
                            <th class="text-muted text-small text-uppercase">Ödəniş</th>
                        </tr></thead>
                        <tbody>
                        @foreach($purchases as $p)
                            <tr>
                                <td><a href="{{ route('admin.production.purchase', $p) }}">{{ $p->purchased_at->format('d.m.Y') }}</a></td>
                                <td class="text-alternate">{{ $p->lines->map(fn ($l) => ($l->material?->name ?? '—').' '.OwnProduction::qty((float) $l->quantity).' '.$l->material?->unitLabel())->implode(', ') }}</td>
                                <td class="text-end">{{ number_format((float) $p->total, 2) }} ₼</td>
                                <td>{{ MaterialPurchase::PAYMENTS[$p->payment] ?? $p->payment }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div></div>
            <h2 class="small-title">Ödənişlər</h2>
            <div class="card"><div class="card-body">
                @if($payments->isEmpty())
                    <div class="text-muted text-center py-3">Ödəniş yoxdur.</div>
                @else
                    <div class="table-responsive"><table class="table mb-0">
                        <thead><tr>
                            <th class="text-muted text-small text-uppercase">Tarix</th>
                            <th class="text-muted text-small text-uppercase">Hesabdan</th>
                            <th class="text-muted text-small text-uppercase">Qeyd</th>
                            <th class="text-muted text-small text-uppercase text-end">Məbləğ</th>
                        </tr></thead>
                        <tbody>
                        @foreach($payments as $m)
                            <tr class="{{ $m->reversedBy ? 'text-decoration-line-through text-muted' : '' }}">
                                <td>{{ $m->occurred_at->format('d.m.Y H:i') }}<div class="text-alternate text-small">{{ trim((string) $m->user?->full_name) ?: '—' }}</div></td>
                                <td>{{ $m->from?->name }}</td>
                                <td class="text-alternate">{{ $m->note }}</td>
                                <td class="text-end">{{ number_format((float) $m->amount, 2) }} ₼</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div></div>
        </div>
    </div>
</div>

<div class="modal modal-right fade" id="payModal" tabindex="-1" aria-labelledby="payTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.production.supplier.pay', $supplier) }}" data-once>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="payTitle">Borcu ödə</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label" for="payAccount">Pul hansı hesabdan çıxır</label>
                <select id="payAccount" name="account_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->name }} · {{ number_format(($balances[$a->id] ?? 0) / 100, 2) }} ₼</option>@endforeach
                </select></div>
            <div class="mb-3"><label class="form-label" for="payAmount">Məbləğ, ₼</label>
                <input type="number" step="0.01" min="0.01" max="{{ max(0, $debt) / 100 }}" id="payAmount" name="amount" class="form-control" value="{{ old('amount', number_format(max(0, $debt) / 100, 2, '.', '')) }}" required>
                <div class="form-text">Borc: {{ number_format($debt / 100, 2) }} ₼. Maliyyədə "Tədarükçüyə ödəniş", kateqoriya "Xammal".</div></div>
            <div class="mb-3"><label class="form-label" for="payNote">Qeyd</label>
                <input type="text" id="payNote" name="note" class="form-control" maxlength="255" value="{{ old('note') }}"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Ödə</button></div>
    </form></div>
</div>
@endsection

@section('js_page')
@if($errors->hasAny(['amount', 'account_id']))
<script>new bootstrap.Modal(document.getElementById('payModal')).show();</script>
@endif
@endsection
