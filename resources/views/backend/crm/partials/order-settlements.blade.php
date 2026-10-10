{{--
  Sifariş → Hesablaşmalar (finance icazəsi). Anbara borc hissə "Götürülüb" olanda yaranır.
  Ödəniş konkret təminat hissəsinə bağlanır; kim ödədi: kuryer / kassa / bank / sahibkar.
  Parametrlər: $order, $settlement = ['accounts', 'paid', 'movements']
--}}
@php
    $parts = $order->items->flatMap(fn ($item) => $item->allocations->map(fn ($a) => ['item' => $item, 'a' => $a]))
        ->filter(fn ($row) => $row['a']->status !== 'cancelled' || ($settlement['paid'][$row['a']->id] ?? 0) !== 0);
    $cost = fn ($a) => (int) round($a->quantity * (float) $a->unit_cost * 100);
    $money = fn ($cents) => number_format($cents / 100, 2).' AZN';
    $pickedCost = $parts->filter(fn ($r) => in_array($r['a']->status, \App\Models\Procurement\OrderItemAllocation::DEBT_STATUSES, true))->sum(fn ($r) => $cost($r['a'])); // götürülüb + hələ qaytarılmayıb
    // Ləğv olunan və anbara qaytarılan hissələr alış dəyərinə daxil deyil
    $allCost = $parts->filter(fn ($r) => !in_array($r['a']->status, \App\Models\Procurement\OrderItemAllocation::SUPPLY_INACTIVE, true))->sum(fn ($r) => $cost($r['a']));
    $paidTotal = $parts->sum(fn ($r) => $settlement['paid'][$r['a']->id] ?? 0);
    $goods = (int) round(((float) $order->subtotal - (float) $order->discount) * 100);
@endphp

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="settle-stat"><span>Alış dəyəri (seçilən)</span><b>{{ $money($allCost) }}</b></div></div>
    <div class="col-6 col-lg-3"><div class="settle-stat"><span>Anbarlara ödənilib</span><b class="text-success">{{ $money($paidTotal) }}</b></div></div>
    <div class="col-6 col-lg-3"><div class="settle-stat"><span>Anbarlara borc (götürülən)</span><b class="{{ $pickedCost - $paidTotal > 0 ? 'text-danger' : '' }}">{{ $money(max(0, $pickedCost - $paidTotal)) }}</b></div></div>
    <div class="col-6 col-lg-3"><div class="settle-stat"><span>Təxmini qazanc (məhsul)</span><b>{{ $allCost ? $money($goods - $allCost) : '—' }}</b></div></div>
</div>

<h2 class="small-title">Anbarlarla hesablaşma</h2>
<div class="card"><div class="card-body">
@if($parts->isEmpty())
    <p class="text-muted mb-0">Hələ anbar seçimi yoxdur.</p>
@else
    <div class="table-responsive"><table class="table od-table">
        <thead><tr><th>Məhsul</th><th>Anbar</th><th class="od-num">Say × alış</th><th>Mərhələ</th><th class="od-num">Dəyər</th><th class="od-num">Ödənilib</th><th class="od-num">Qalıq</th><th></th></tr></thead>
        <tbody>
        @foreach($parts as ['item' => $item, 'a' => $a])
            @php
                $paid = $settlement['paid'][$a->id] ?? 0;
                // Ləğv olunub / anbara qaytarılıb: ödənilən pul anbardan geri alınmalıdır (mənfi qalıq)
                $back = in_array($a->status, \App\Models\Procurement\OrderItemAllocation::SUPPLY_INACTIVE, true);
                $left = $back ? -$paid : $cost($a) - $paid;
            @endphp
            <tr>
                <td>{{ $item->product?->name ?? 'Məhsul' }}<span class="od-sub">{{ $item->variant?->size?->name_az }}</span></td>
                <td>{{ $a->warehouse->name_az }}</td>
                <td class="od-num">{{ $a->quantity }} × {{ number_format((float) $a->unit_cost, 2) }}</td>
                <td>{{ $a->label() }}@if($a->status === 'returned')<span class="od-sub">anbara qaytarılıb{{ $paid > 0 ? '' : ' — borc yoxdur' }}</span>@elseif(!in_array($a->status, \App\Models\Procurement\OrderItemAllocation::DEBT_STATUSES, true) && $a->status !== 'cancelled')<span class="od-sub">borc götürüləndə yaranır</span>@endif</td>
                <td class="od-num">{{ $money($cost($a)) }}</td>
                <td class="od-num text-success">{{ $paid ? $money($paid) : '—' }}</td>
                <td class="od-num {{ $left > 0 && in_array($a->status, \App\Models\Procurement\OrderItemAllocation::DEBT_STATUSES, true) ? 'text-danger fw-bold' : ($left < 0 ? 'text-warning' : '') }}">
                    {{ $left === 0 ? 'Ödənilib' : $money(abs($left)) }}@if($left < 0)<span class="od-sub">anbardan geri alınmalıdır</span>@endif
                </td>
                <td class="text-end">
                    @if($back && $left < 0)
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#warehouseRefundModal"
                                data-allocation="{{ $a->id }}" data-left="{{ number_format(-$left / 100, 2, '.', '') }}"
                                data-title="{{ $a->warehouse->name_az }} · {{ $item->product?->name }} × {{ $a->quantity }} — geri alınacaq {{ $money(-$left) }}">Geri aldım</button>
                    @elseif(!$back && $left > 0)
                        <button type="button" class="btn btn-sm btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#warehousePayModal"
                                data-allocation="{{ $a->id }}" data-left="{{ number_format($left / 100, 2, '.', '') }}"
                                data-title="{{ $a->warehouse->name_az }} · {{ $item->product?->name }} × {{ $a->quantity }} — qalıq {{ $money($left) }}">Ödəniş</button>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
</div></div>

<h2 class="small-title mt-5">Sifarişə aid pul hərəkətləri</h2>
<div class="card"><div class="card-body">
@if($settlement['movements']->isEmpty())
    <p class="text-muted mb-0">Hələ pul hərəkəti yoxdur.</p>
@else
    <div class="table-responsive"><table class="table od-table mb-0">
        <thead><tr><th>Real tarix</th><th>Növ</th><th>Haradan</th><th>Haraya</th><th class="od-num">Məbləğ</th><th>İcraçı</th><th></th></tr></thead>
        <tbody>
        @foreach($settlement['movements'] as $m)
            <tr class="{{ $m->kind === 'reversal' || $m->reversedBy ? 'finance-row--reversed' : '' }}">
                <td>{{ $m->occurred_at->format('d.m.Y H:i') }}<span class="od-sub">qeydə alınıb {{ $m->created_at?->format('d.m.Y H:i') }}@if($m->isBackdated()) · <span class="text-warning">sonradan yazılıb</span>@endif</span></td>
                <td>{{ $m->kindLabel() }}@if($m->note)<span class="od-sub">{{ $m->note }}</span>@endif</td>
                <td>{{ $m->from?->name }}</td>
                <td>{{ $m->to?->name }}</td>
                <td class="od-num fw-bold">{{ number_format((float) $m->amount, 2) }} AZN</td>
                <td>{{ $m->user?->full_name ?? 'Sistem' }}</td>
                <td class="text-end">
                    @if($m->kind !== 'reversal' && !$m->reversedBy)
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseMovement"
                                data-action="{{ route('admin.finance.reverse', $m) }}" data-title="#{{ $m->id }} · {{ $m->kindLabel() }} · {{ number_format((float) $m->amount, 2) }} AZN">Əks et</button>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
</div></div>

{{-- Anbara ödəniş: alan hesab serverdə təminat hissəsinin anbarıdır --}}
<div class="modal modal-right fade" id="warehousePayModal" tabindex="-1" aria-labelledby="warehousePayTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.finance.store') }}" data-movement-form>
        @csrf
        <input type="hidden" name="kind" value="warehouse_payment">
        <input type="hidden" name="order_item_allocation_id" value="">
        <div class="modal-header"><h5 class="modal-title" id="warehousePayTitle">Anbara ödəniş</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            <div class="fw-bold mb-3" data-pay-title></div>
            <div class="mb-3"><label class="form-label" for="payFrom">Kim ödədi</label>
                <select id="payFrom" name="from_account_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach($settlement['accounts'] as $acc)<option value="{{ $acc->id }}">{{ $acc->name }} · {{ $acc->typeLabel() }}</option>@endforeach
                </select>
                <div class="form-text">Kuryer ödəyibsə — kuryerin qalığı azalır. Sahibkar şəxsi kartından ödəyibsə — şirkət sahibkara borclu qalır.</div>
            </div>
            <div class="mb-3"><label class="form-label" for="payAmount">Məbləğ, AZN</label><input id="payAmount" type="number" name="amount" min="0.01" step="0.01" class="form-control" required></div>
                <div class="mb-3"><label class="form-label" for="payAt">Real tarix <small class="text-muted">(boş — indi)</small></label><input id="payAt" type="datetime-local" name="occurred_at" class="form-control"></div>
            <label class="form-label" for="payNote">Qeyd</label>
            <textarea id="payNote" name="note" rows="2" maxlength="2000" class="form-control" placeholder="Məs.: qəbz №"></textarea>
            <div class="form-text mt-2">"Sonra hesablaşarıq" — heç nə qeyd etməyin, borc qalır.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Qeydə al</button></div>
    </form></div>
</div>
{{-- Anbar pulu qaytardı (mal qaytarılıb / seçim ləğv olunub): göndərən serverdə həmin anbardır --}}
<div class="modal modal-right fade" id="warehouseRefundModal" tabindex="-1" aria-labelledby="warehouseRefundTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.finance.store') }}" data-movement-form>
        @csrf
        <input type="hidden" name="kind" value="warehouse_refund">
        <input type="hidden" name="order_item_allocation_id" value="">
        <div class="modal-header"><h5 class="modal-title" id="warehouseRefundTitle">Anbar pulu qaytardı</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
        <div class="modal-body">
            <div class="fw-bold mb-3" data-pay-title></div>
            <div class="mb-3"><label class="form-label" for="refundTo">Pulu kim aldı</label>
                <select id="refundTo" name="from_account_id" class="form-select" required>
                    <option value="">Seçin</option>
                    @foreach($settlement['accounts'] as $acc)<option value="{{ $acc->id }}">{{ $acc->name }} · {{ $acc->typeLabel() }}</option>@endforeach
                </select>
                <div class="form-text">Kuryer alıbsa — kuryerin qalığı artır (şirkətə təhvil verməlidir).</div>
            </div>
            <div class="mb-3"><label class="form-label" for="refundAmount">Məbləğ, AZN</label><input id="refundAmount" type="number" name="amount" min="0.01" step="0.01" class="form-control" required></div>
            <div class="mb-3"><label class="form-label" for="refundAt">Real tarix <small class="text-muted">(boş — indi)</small></label><input id="refundAt" type="datetime-local" name="occurred_at" class="form-control"></div>
            <label class="form-label" for="refundNote">Qeyd</label>
            <textarea id="refundNote" name="note" rows="2" maxlength="2000" class="form-control"></textarea>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Qeydə al</button></div>
    </form></div>
</div>
@include('backend.finance.partials.reverse-modal')
