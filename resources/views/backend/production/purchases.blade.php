{{-- İstehsal → Xammal alışları: siyahı və "Yeni alış" (sətirlər: xammal, miqdar, məbləğ; ödəniş — balansdan / borca / başlanğıc qalıq) --}}
@use('App\Models\Production\MaterialPurchase')
@use('App\Services\Production\OwnProduction')
@php
    $title = 'Xammal alışları';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'İstehsal', '#' => $title];
    $oldLines = old('lines', [['raw_material_id' => '', 'quantity' => '', 'unit_cost' => '']]);
    $payment = old('payment', 'paid');
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
                <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#purchaseModal" @disabled($materials->isEmpty())>
                    <span>Yeni alış</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')
    @if($materials->isEmpty())
        <div class="alert alert-info">Alış yazmaq üçün əvvəlcə <a href="{{ route('admin.production.materials') }}">Xammal</a> bölməsində xammalı əlavə edin.</div>
    @endif

    <div class="card mb-5"><div class="card-body">
        @if($purchases->isEmpty())
            <div class="text-muted text-center py-5">Hələ xammal alışı yoxdur.</div>
        @else
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr>
                    <th class="text-muted text-small text-uppercase">Tarix</th>
                    <th class="text-muted text-small text-uppercase">Tədarükçü</th>
                    <th class="text-muted text-small text-uppercase">Xammal</th>
                    <th class="text-muted text-small text-uppercase text-end">Məbləğ</th>
                    <th class="text-muted text-small text-uppercase">Ödəniş</th>
                </tr></thead>
                <tbody>
                @foreach($purchases as $p)
                    <tr class="cursor-pointer" onclick="location.href='{{ route('admin.production.purchase', $p) }}'">
                        <td><a href="{{ route('admin.production.purchase', $p) }}">{{ $p->purchased_at->format('d.m.Y') }}</a></td>
                        <td>{{ $p->supplier?->name ?? '—' }}</td>
                        <td class="text-alternate">{{ $p->lines->map(fn ($l) => ($l->material?->name ?? '—').' '.OwnProduction::qty((float) $l->quantity).' '.$l->material?->unitLabel())->implode(', ') }}</td>
                        <td class="text-end">{{ number_format((float) $p->total, 2) }} ₼</td>
                        <td>@if($p->payment === 'debt')<span class="badge bg-outline-danger">Borca</span>@elseif($p->payment === 'paid')<span class="badge bg-outline-success">Ödənilib</span>@else<span class="badge bg-outline-secondary">Başlanğıc qalıq</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="mt-3">{{ $purchases->links() }}</div>
        @endif
    </div></div>
</div>

<div class="modal fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" method="POST" action="{{ route('admin.production.purchases.store') }}" data-once>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="purchaseModalTitle">Yeni xammal alışı</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="row g-3 mb-3">
                <div class="col-sm-4"><label class="form-label" for="pDate">Tarix</label>
                    <input type="date" id="pDate" name="purchased_at" class="form-control" max="{{ today()->toDateString() }}" value="{{ old('purchased_at', today()->toDateString()) }}" required></div>
                <div class="col-sm-8"><label class="form-label d-block">Ödəniş</label>
                    @foreach(MaterialPurchase::PAYMENTS as $code => $label)
                        <div class="form-check form-check-inline mt-2">
                            <input class="form-check-input" type="radio" name="payment" id="pay_{{ $code }}" value="{{ $code }}" @checked($payment === $code)>
                            <label class="form-check-label" for="pay_{{ $code }}">{{ $label }}</label>
                        </div>
                    @endforeach</div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6" data-show="paid debt"><label class="form-label" for="pSupplier">Tədarükçü</label>
                    <select id="pSupplier" name="supplier_account_id" class="form-select">
                        <option value="">Seçin</option>
                        @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_account_id') === $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                    <div class="form-text">Siyahıda yoxdursa — <a href="{{ route('admin.production.suppliers') }}">Tədarükçülər</a>.</div></div>
                <div class="col-sm-6" data-show="paid"><label class="form-label" for="pAccount">Pul hansı hesabdan çıxır</label>
                    <select id="pAccount" name="account_id" class="form-select">
                        <option value="">Seçin</option>
                        @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->name }} · {{ number_format(($balances[$a->id] ?? 0) / 100, 2) }} ₼</option>@endforeach
                    </select></div>
            </div>
            <div class="form-text mb-3" data-show="paid">Məbləğ seçilən hesabdan çıxır — Maliyyədə "Tədarükçüyə ödəniş", kateqoriya "Xammal" (xərc).</div>
            <div class="form-text mb-3" data-show="debt">Pul çıxmır — məbləğ tədarükçüyə borc yazılır, sonra tədarükçünün səhifəsindən ödənilir.</div>
            <div class="form-text mb-3" data-show="opening">Artıq əlinizdə olan xammal (pulu əvvəl ödənilib) — yalnız qalığa və mayaya düşür, pul hərəkəti və borc yoxdur.</div>

            <div id="purchaseLines">
                @foreach($oldLines as $i => $line)
                    <div class="row g-2 mb-2 align-items-end" data-line>
                        <div class="col-md-5"><label class="form-label">Xammal</label>
                            <select name="lines[{{ $i }}][raw_material_id]" class="form-select" required data-material>
                                <option value="">Seçin</option>
                                @foreach($materials as $m)<option value="{{ $m->id }}" data-unit="{{ $m->unitLabel() }}" @selected((int) ($line['raw_material_id'] ?? 0) === $m->id)>{{ $m->name }} ({{ $m->unitLabel() }})</option>@endforeach
                            </select></div>
                        <div class="col-md-3"><label class="form-label">Miqdar</label>
                            <input type="number" step="0.001" min="0.001" name="lines[{{ $i }}][quantity]" class="form-control" value="{{ $line['quantity'] ?? '' }}" required data-qty></div>
                        <div class="col-md-3"><label class="form-label" data-price-label>1 vahidin qiyməti, ₼</label>
                            <input type="number" step="0.0001" min="0" name="lines[{{ $i }}][unit_cost]" class="form-control" value="{{ $line['unit_cost'] ?? '' }}" required data-price></div>
                        <div class="col-md-1 text-end pb-2"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove aria-label="Sətri sil">✕</button></div>
                        <div class="col-12 text-muted text-small" data-unit-price></div>
                    </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button type="button" class="btn btn-outline-primary btn-sm" id="addLine">+ Sətir</button>
                <span class="fw-bold" id="purchaseTotal"></span>
            </div>
            <label class="form-label" for="pNote">Qeyd</label>
            <input type="text" id="pNote" name="note" class="form-control" maxlength="500" value="{{ old('note') }}">
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Yadda saxla</button></div>
    </form></div>
</div>
@endsection

@section('js_page')
<script>
    (() => {
        const modal = document.getElementById('purchaseModal'), box = document.getElementById('purchaseLines');
        const togglePayment = () => {
            const mode = modal.querySelector('[name="payment"]:checked')?.value;
            modal.querySelectorAll('[data-show]').forEach((el) => { el.hidden = !el.dataset.show.split(' ').includes(mode); });
        };
        const refresh = () => {
            let total = 0;
            box.querySelectorAll('[data-line]').forEach((line, i) => {
                line.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace(/lines\[\d+]/, 'lines[' + i + ']'); });
                const q = Number(line.querySelector('[data-qty]').value) || 0, price = Number(line.querySelector('[data-price]').value) || 0;
                const unit = line.querySelector('[data-material]').selectedOptions[0]?.dataset.unit;
                const a = Math.round(q * price * 100) / 100;
                total += a;
                line.querySelector('[data-price-label]').textContent = '1 ' + (unit || 'vahidin') + ' qiyməti, ₼';
                line.querySelector('[data-unit-price]').textContent = q > 0 && price > 0 ? q + ' × ' + price + ' = ' + a.toFixed(2) + ' ₼' : '';
                line.querySelector('[data-remove]').hidden = box.children.length < 2;
            });
            document.getElementById('purchaseTotal').textContent = 'Cəmi: ' + total.toFixed(2) + ' ₼';
        };
        modal.addEventListener('change', (e) => { if (e.target.name === 'payment') togglePayment(); refresh(); });
        box.addEventListener('input', refresh);
        box.addEventListener('click', (e) => { if (e.target.matches('[data-remove]')) { e.target.closest('[data-line]').remove(); refresh(); } });
        document.getElementById('addLine').addEventListener('click', () => {
            const copy = box.lastElementChild.cloneNode(true);
            copy.querySelectorAll('input').forEach((el) => { el.value = ''; });
            copy.querySelector('select').value = '';
            box.appendChild(copy);
            refresh();
        });
        togglePayment();
        refresh();
        @if($errors->any())
            new bootstrap.Modal(modal).show();
        @endif
    })();
</script>
@endsection
