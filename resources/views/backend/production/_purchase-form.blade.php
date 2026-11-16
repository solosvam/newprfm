{{--
  Xammal alışının forması (modal #purchaseModal): yeni alış ($purchase = null) və ya düzəliş ($purchase).
  Düzəlişdə ödəniş növü və hesab dəyişmir — göstərilir.
--}}
@use('App\Models\Production\MaterialPurchase')
@php
    $oldLines = old('lines', $purchase
        ? $purchase->lines->map(fn ($l) => ['raw_material_id' => $l->raw_material_id, 'quantity' => App\Services\Production\OwnProduction::qty((float) $l->quantity), 'unit_cost' => rtrim(rtrim(number_format((float) $l->unit_cost, 4, '.', ''), '0'), '.')])->all()
        : [['raw_material_id' => '', 'quantity' => '', 'unit_cost' => '']]);
    $paymentMode = old('payment', $purchase?->payment ?? 'paid');
    $payment ??= null;
@endphp
<div class="modal fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" method="POST" action="{{ $purchase ? route('admin.production.purchase.update', $purchase) : route('admin.production.purchases.store') }}" data-once>
        @csrf
        <div class="modal-header"><h5 class="modal-title" id="purchaseModalTitle">{{ $purchase ? 'Alışa düzəliş #'.$purchase->id : 'Yeni xammal alışı' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="row g-3 mb-3">
                <div class="col-sm-4"><label class="form-label" for="pDate">Tarix</label>
                    <input type="date" id="pDate" name="purchased_at" class="form-control" max="{{ today()->toDateString() }}" value="{{ old('purchased_at', $purchase?->purchased_at->toDateString() ?? today()->toDateString()) }}" required></div>
                <div class="col-sm-8"><label class="form-label d-block">Ödəniş</label>
                    @if($purchase)
                        <input type="hidden" name="payment" value="{{ $purchase->payment }}">
                        <div class="mt-2">{{ MaterialPurchase::PAYMENTS[$purchase->payment] ?? $purchase->payment }}@if($payment) — {{ $payment->from?->name }}@endif <span class="text-muted text-small">(dəyişmir)</span></div>
                    @else
                    @foreach(MaterialPurchase::PAYMENTS as $code => $label)
                        <div class="form-check form-check-inline mt-2">
                            <input class="form-check-input" type="radio" name="payment" id="pay_{{ $code }}" value="{{ $code }}" @checked($paymentMode === $code)>
                            <label class="form-check-label" for="pay_{{ $code }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    @endif</div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6"><label class="form-label" for="pSupplier">Tədarükçü <span class="text-muted text-small" data-show="opening">(istəyə görə)</span></label>
                    <select id="pSupplier" name="supplier_account_id" class="form-select">
                        <option value="">Seçin</option>
                        @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_account_id', $purchase?->supplier_account_id) === $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                    <div class="form-text">Siyahıda yoxdursa — <a href="{{ route('admin.production.suppliers') }}">Tədarükçülər</a>.</div></div>
                @unless($purchase)<div class="col-sm-6" data-show="paid"><label class="form-label" for="pAccount">Pul hansı hesabdan çıxır</label>
                    <select id="pAccount" name="account_id" class="form-select">
                        <option value="">Seçin</option>
                        @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->name }} · {{ number_format(($balances[$a->id] ?? 0) / 100, 2) }} ₼</option>@endforeach
                    </select></div>@endunless
            </div>
            @if($purchase)
                <div class="form-text mb-3" data-show="paid">Məbləğ, tədarükçü və ya tarix dəyişsə, Maliyyədə köhnə ödəniş əks olunur və yeni məbləğ eyni hesabdan yazılır.</div>
                <div class="form-text mb-3" data-show="debt">Tədarükçüyə borc yeni məbləğlə hesablanır.</div>
                <div class="form-text mb-3">Xammalın orta mayası yenidən hesablanır. Bu xammaldan artıq olunmuş partiyaların mayası dəyişmir. İstehsala sərf olunan hissədən az miqdar yazmaq olmaz.</div>
            @else
            <div class="form-text mb-3" data-show="paid">Məbləğ seçilən hesabdan çıxır — Maliyyədə "Tədarükçüyə ödəniş", kateqoriya "Xammal" (xərc).</div>
            <div class="form-text mb-3" data-show="debt">Pul çıxmır — məbləğ tədarükçüyə borc yazılır, sonra tədarükçünün səhifəsindən ödənilir.</div>
            <div class="form-text mb-3" data-show="opening">Artıq əlinizdə olan xammal (pulu əvvəl ödənilib) — yalnız qalığa və mayaya düşür, pul hərəkəti və borc yoxdur.</div>
            @endif

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
                        <div class="col-md-1 text-end pb-2"><button type="button" class="btn btn-sm btn-link text-danger p-0 cursor-pointer" data-remove aria-label="Sətri sil">✕</button></div>
                        <div class="col-12 text-muted text-small" data-unit-price></div>
                    </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button type="button" class="btn btn-outline-primary btn-sm cursor-pointer" id="addLine">+ Sətir</button>
                <span class="fw-bold" id="purchaseTotal"></span>
            </div>
            <label class="form-label" for="pNote">Qeyd</label>
            <input type="text" id="pNote" name="note" class="form-control" maxlength="500" value="{{ old('note', $purchase?->note) }}">
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Yadda saxla</button></div>
    </form></div>
</div>
