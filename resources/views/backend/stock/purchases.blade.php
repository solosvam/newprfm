{{-- Stok → Alışlar: siyahı və "Yeni alış" (hədiyyə kartları — nominal + barkod aralığı + bir kartın qiyməti) --}}
@use('App\Models\Stock\GiftCard')
@php
    $title = 'Alışlar';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Stok', '#' => $title];
    $oldLines = old('lines', [['denomination' => 100, 'barcode_from' => GiftCard::barcodeFor(100, 1), 'barcode_to' => '', 'unit_cost' => '']]);
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
                <button type="button" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#purchaseModal">
                    <span>Yeni alış</span><i data-acorn-icon="plus"></i>
                </button>
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')

    <div class="card mb-5"><div class="card-body">
        @if($purchases->isEmpty())
            <div class="text-muted text-center py-5">Hələ alış yoxdur. "Yeni alış" ilə hədiyyə kartlarını stoka daxil edin.</div>
        @else
            <table class="table mb-0">
                <thead><tr>
                    <th class="text-muted text-small text-uppercase">Tarix</th>
                    <th class="text-muted text-small text-uppercase">Təchizatçı</th>
                    <th class="text-muted text-small text-uppercase">Mal</th>
                    <th class="text-muted text-small text-uppercase text-end">Say</th>
                    <th class="text-muted text-small text-uppercase text-end">Məbləğ</th>
                    <th class="text-muted text-small text-uppercase">Kim</th>
                </tr></thead>
                <tbody>
                @foreach($purchases as $purchase)
                    <tr class="cursor-pointer" onclick="location.href='{{ route('admin.stock.purchases.show', $purchase) }}'">
                        <td><a href="{{ route('admin.stock.purchases.show', $purchase) }}">{{ $purchase->purchased_at->format('d.m.Y') }}</a></td>
                        <td>{{ $purchase->supplier ?: '—' }}</td>
                        <td>{{ $purchase->items->map->label()->implode(', ') }}</td>
                        <td class="text-end">{{ $purchase->items->sum('quantity') }}</td>
                        <td class="text-end">{{ number_format((float) $purchase->total_cost, 2) }} ₼</td>
                        <td class="text-alternate">{{ trim((string) $purchase->creator?->full_name) ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div></div>
</div>

<div class="modal modal-right fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg"><form class="modal-content" method="POST" action="{{ route('admin.stock.purchases.store') }}" data-once>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="purchaseModalTitle">Yeni alış — hədiyyə kartları</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            @if($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <div class="row g-3 mb-3">
                <div class="col-sm-5"><label class="form-label" for="purchasedAt">Tarix</label>
                    <input type="date" id="purchasedAt" name="purchased_at" class="form-control" value="{{ old('purchased_at', now()->toDateString()) }}" required></div>
                <div class="col-sm-7"><label class="form-label" for="supplier">Təchizatçı</label>
                    <input type="text" id="supplier" name="supplier" class="form-control" maxlength="120" value="{{ old('supplier') }}" placeholder="Məs. çap evi"></div>
            </div>

            <div id="purchaseLines">
                @foreach($oldLines as $i => $line)
                    <div class="border rounded-md p-3 mb-3" data-line>
                        <div class="row g-2">
                            <div class="col-sm-3"><label class="form-label">Nominal</label>
                                <select name="lines[{{ $i }}][denomination]" class="form-select" data-denomination>
                                    @foreach(GiftCard::DENOMINATIONS as $d)<option value="{{ $d }}" @selected((int) ($line['denomination'] ?? 0) === $d)>{{ GiftCard::label($d) }}</option>@endforeach
                                </select></div>
                            <div class="col-sm-3"><label class="form-label">İlk barkod</label>
                                <input type="text" name="lines[{{ $i }}][barcode_from]" class="form-control" inputmode="numeric" maxlength="13" value="{{ $line['barcode_from'] ?? '' }}" data-from required></div>
                            <div class="col-sm-3"><label class="form-label">Son barkod</label>
                                <input type="text" name="lines[{{ $i }}][barcode_to]" class="form-control" inputmode="numeric" maxlength="13" value="{{ $line['barcode_to'] ?? '' }}" data-to required></div>
                            <div class="col-sm-3"><label class="form-label">Bir kartın qiyməti, ₼</label>
                                <input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_cost]" class="form-control" value="{{ $line['unit_cost'] ?? '' }}" required></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="text-muted text-small" data-count></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove>Sətri sil</button>
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="addPurchaseLine">+ Sətir əlavə et</button>

            <label class="form-label" for="purchaseNote">Qeyd</label>
            <textarea id="purchaseNote" name="note" class="form-control" rows="2" maxlength="500">{{ old('note') }}</textarea>
            <div class="form-text mt-3">
                Qiymət — çap xərcidir (kartın nominalı bizim mal deyil). Barkod: 13 rəqəm, ilk 7-si nominalı göstərir —
                HK 100: 1000100…, HK 200: 1000200…, HK 500: 1000500…, HK 1000: 1001000…. Ödənişi Maliyyədə "Məxaric" kimi yazın.
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Stoka daxil et</button></div>
    </form></div>
</div>
@endsection

@section('js_page')
<script>
    (() => {
        const box = document.getElementById('purchaseLines');
        const prefix = (d) => '1' + String(d).padStart(6, '0');
        const refresh = () => box.querySelectorAll('[data-line]').forEach((line, i) => {
            line.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace(/lines\[\d+]/, 'lines[' + i + ']'); });
            const from = line.querySelector('[data-from]').value, to = line.querySelector('[data-to]').value;
            const ok = /^\d{13}$/.test(from) && /^\d{13}$/.test(to) && to >= from;
            line.querySelector('[data-count]').textContent = ok ? (Number(to.slice(7)) - Number(from.slice(7)) + 1) + ' kart' : '';
            line.querySelector('[data-remove]').hidden = box.children.length < 2;
        });
        box.addEventListener('input', refresh);
        box.addEventListener('change', (e) => {
            if (!e.target.matches('[data-denomination]')) return;
            // Nominal dəyişəndə ilk barkod həmin nominalın 1-ci kartı ilə doldurulur
            const line = e.target.closest('[data-line]');
            line.querySelector('[data-from]').value = prefix(e.target.value) + '000001';
            line.querySelector('[data-to]').value = '';
            refresh();
        });
        box.addEventListener('click', (e) => {
            if (!e.target.matches('[data-remove]')) return;
            e.target.closest('[data-line]').remove();
            refresh();
        });
        document.getElementById('addPurchaseLine').addEventListener('click', () => {
            const copy = box.lastElementChild.cloneNode(true);
            copy.querySelectorAll('input').forEach((el) => { el.value = ''; });
            box.appendChild(copy);
            copy.querySelector('[data-denomination]').dispatchEvent(new Event('change', { bubbles: true }));
        });
        refresh();
        @if($errors->any())
            new bootstrap.Modal(document.getElementById('purchaseModal')).show();
        @endif
    })();
</script>
@endsection
