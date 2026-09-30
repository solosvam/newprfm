{{--
  Anbar təminatı — məhsul mərkəzli: hər məhsul bir kart (tələb/seçilib/çatışmır, anbar cavabları, seçimlər).
  İstifadə: backend/crm/order.blade.php və backend/procurement/order.blade.php.
  Formalar ProcurementController-ə gedir; "Yeni sorğu", "Cavab daxil et", "Seçimi ləğv et" — yan modallar.
--}}
@php
    // Sorğu/cavab/seçim — "İcraya götür"dən sonra; bildirildi/ayrıldı/götürüldü — kuryer təyinindən sonra da
    $editable = $order->customer_id && in_array($order->status?->code, \App\Services\OrderStatusService::PROCUREMENT, true);
    $flowEditable = $order->customer_id && in_array($order->status?->code, \App\Services\OrderStatusService::SUPPLY_FLOW, true);
    $staff = $staff ?? collect();
    // user_id 0 — anbarın özü (portal linki ilə)
    $who = fn ($id) => $id === 0 || $id === '0' ? 'Anbar (link)' : ($id ? ($staff[$id] ?? 'Əməkdaş #'.$id) : null);
    $smsLogs = app(\App\Services\WarehouseNotifier::class)->lastLogs($requests->pluck('id'), $order->items->flatMap->allocations->pluck('id'));
    $sources = ['phone' => 'Telefon', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'manual' => 'Digər', 'link' => 'Anbar linki'];
    $itemName = fn ($item) => ($item?->product?->name ?? 'Silinmiş məhsul');
    $itemSize = fn ($item) => $item?->variant?->size?->name_az;
    // order_item_id => [sorğu sətri + onun sorğusu]
    $requestRows = $requests->flatMap(fn ($req) => $req->items->map(fn ($ri) => ['req' => $req, 'ri' => $ri]))->groupBy(fn ($row) => $row['ri']->order_item_id);
@endphp
<link rel="stylesheet" href="{{ asset_v('backend/css/procurement-order.css') }}">

@if($order->status?->code === 'new')
    <div class="alert alert-warning">Anbar sorğusu göndərmək üçün əvvəlcə sifarişi icraya götürün — yuxarıdakı <strong>"İcraya götür"</strong> düyməsi.</div>
@elseif(!$editable)
    <div class="alert alert-info">Bu mərhələdə anbar seçimi dəyişdirilmir{{ $flowEditable ? ' — yalnız götürmə mərhələləri qeyd olunur' : '' }}.</div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="text-muted small">Anbar seçimi rezervasiya təsdiqi deyil. Müştərinin satış qiyməti dəyişmir.</div>
    @if($editable)
        <button type="button" class="btn btn-primary btn-icon btn-icon-start" data-bs-toggle="modal" data-bs-target="#procRequestModal" @disabled($warehouses->isEmpty())>
            <i data-acorn-icon="plus" data-acorn-size="16"></i><span>Yeni sorğu</span>
        </button>
    @endif
</div>

@if($editable && $requests->isNotEmpty())
    <details class="proc-sms" @if($requests->contains(fn ($r) => !$r->warehouse->phone || !($smsLogs['request'][$r->id] ?? null)?->isSent())) open @endif>
        <summary>Anbar SMS bildirişləri</summary>
        @foreach($requests as $req)
            @php
                $log = $smsLogs['request'][$req->id] ?? null;
            @endphp
            <div class="proc-sms__row">
                <span><b>{{ $req->warehouse->name_az }}</b> · Sorğu #{{ $req->id }}</span>
                <span class="proc-sms__state {{ $log?->isSent() ? 'is-ok' : ($log || !$req->warehouse->phone ? 'is-bad' : '') }}">
                    @if(!$req->warehouse->phone) Telefon yazılmayıb — linki əllə göndərin
                    @elseif(!$log) SMS göndərilməyib
                    @elseif($log->isSent()) ✓ SMS {{ $log->created_at->format('d.m H:i') }}
                    @else ✕ {{ $log->error }} @endif
                </span>
                @if($req->warehouse->phone)
                    <form method="POST" action="{{ route('admin.procurement.requests.sms', [$order, $req->id]) }}" data-once>@csrf
                        <button class="btn btn-sm btn-link p-0">{{ $log ? 'Təkrar göndər' : 'SMS göndər' }}</button>
                    </form>
                @endif
            </div>
        @endforeach
    </details>
@endif

@foreach($order->items->filter(fn ($i) => $i->activeQuantity() > 0) as $item)
    @php
        $need = $item->activeQuantity(); // ləğv olunan miqdar təmin edilmir
        $active = $item->allocations->whereNotIn('status', \App\Models\Procurement\OrderItemAllocation::SUPPLY_INACTIVE);
        $selected = (int) $active->sum('quantity');
        $missing = max(0, $need - $selected);
        $percent = $need ? min(100, round($selected / $need * 100)) : 0;
        $rows = ($requestRows[$item->id] ?? collect())->map(function ($row) use ($active) {
            $offer = $row['ri']->offers->sortByDesc('id')->first();
            $taken = $offer ? (int) $active->where('warehouse_offer_id', $offer->id)->sum('quantity') : 0;
            return $row + ['offer' => $offer, 'taken' => $taken, 'answers' => $row['ri']->offers->count()];
        });
        $cheapest = $rows->filter(fn ($r) => $r['offer']?->available_quantity && $r['offer']->unit_cost !== null)->min(fn ($r) => (float) $r['offer']->unit_cost);
    @endphp
    <section class="proc-item">
        <header class="proc-item__head">
            <div class="proc-item__title">{{ $itemName($item) }}@if($itemSize($item))<span> · {{ $itemSize($item) }}</span>@endif</div>
            <div class="proc-item__need">
                Tələb <b>{{ $need }}</b>@if($item->cancelled_quantity)<span class="text-danger"> ({{ $item->cancelled_quantity }} ləğv)</span>@endif · Seçilib <b>{{ $selected }}</b> · Çatışmır <b class="{{ $missing ? 'text-danger' : '' }}">{{ $missing }}</b>
                <div class="progress"><div class="progress-bar {{ $missing ? 'bg-warning' : 'bg-success' }}" style="width: {{ $percent }}%"></div></div>
            </div>
        </header>
        <div class="proc-item__body">
            @if($rows->isEmpty())
                <p class="text-muted mb-0 py-2">Bu məhsul üçün hələ sorğu yoxdur.</p>
            @else
                <div class="table-responsive"><table class="table proc-table">
                    <thead><tr><th>Anbar</th><th>Cavab</th><th class="text-end">Vahid alış</th><th>Mənbə</th><th class="text-end">Seçim</th></tr></thead>
                    <tbody>
                    @foreach($rows as $row)
                        @php
                            $offer = $row['offer'];
                            $canTake = $offer ? min($offer->available_quantity - $row['taken'], $missing) : 0;
                            $offerUrl = route('admin.procurement.offers.store', [$order, $row['ri']]);
                            $offerTitle = $itemName($item).($itemSize($item) ? ' · '.$itemSize($item) : '').' — '.$row['req']->warehouse->name_az;
                        @endphp
                        <tr>
                            <td>{{ $row['req']->warehouse->name_az }}<span class="proc-sub">Sorğu #{{ $row['req']->id }} · {{ $row['req']->created_at->format('d.m H:i') }}</span></td>
                            <td>
                                @if(!$offer)<span class="badge bg-outline-muted">Cavab gözlənilir</span>
                                @elseif(!$offer->available_quantity)<span class="badge bg-outline-danger">Yoxdur</span>
                                @elseif($offer->available_quantity >= $row['ri']->requested_quantity)<span class="badge bg-outline-success">Tam var · {{ $offer->available_quantity }} ədəd</span>
                                @else<span class="badge bg-outline-warning">Qismən · {{ $offer->available_quantity }} ədəd</span>@endif
                                @if($row['answers'] > 1)<span class="proc-sub">{{ $row['answers'] }} cavab — sonuncu göstərilir</span>@endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($offer?->unit_cost !== null && $offer->available_quantity)
                                    {{ number_format((float) $offer->unit_cost, 2) }} AZN
                                    @if($rows->count() > 1 && (float) $offer->unit_cost === $cheapest)<span class="badge bg-success proc-best">ən ucuz</span>@endif
                                @else <span class="text-muted">—</span> @endif
                            </td>
                            <td>@if($offer){{ $sources[$offer->source] ?? $offer->source }}<span class="proc-sub">{{ $offer->created_at->format('d.m H:i') }}@if($who($offer->recorded_by)) · {{ $who($offer->recorded_by) }}@endif</span>@else<span class="text-muted">—</span>@endif</td>
                            <td class="text-end">
                                <div class="proc-actions">
                                    @if($row['taken'])<span class="text-success text-nowrap">{{ $row['taken'] }} seçilib</span>@endif
                                    @if($editable && $canTake > 0)
                                        <form method="POST" action="{{ route('admin.procurement.allocations.store', $order) }}" class="proc-pick">
                                            @csrf
                                            <input type="hidden" name="offer_id" value="{{ $offer->id }}">
                                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            <input type="number" name="quantity" min="1" max="{{ $canTake }}" value="{{ $canTake }}" class="form-control form-control-sm" aria-label="Seçilən miqdar" required>
                                            <button class="btn btn-sm btn-primary">Seç</button>
                                        </form>
                                    @endif
                                    @if($editable)
                                        <button type="button" class="btn btn-sm {{ $offer ? 'btn-link px-1' : 'btn-outline-primary' }}" data-bs-toggle="modal" data-bs-target="#procOfferModal"
                                                data-action="{{ $offerUrl }}" data-title="{{ $offerTitle }}" data-requested="{{ $row['ri']->requested_quantity }}">
                                            {{ $offer ? 'Yenilə' : 'Cavab daxil et' }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif

            @foreach($item->allocations->sortBy(fn ($a) => $a->status === 'cancelled') as $allocation)
                @php
                    $status = $allocation->status;
                    $isCancelled = $status === \App\Models\Procurement\OrderItemAllocation::CANCELLED;
                    $flow = \App\Models\Procurement\OrderItemAllocation::FLOW;
                    $reached = array_search($status === 'problem' ? ($allocation->logs->where('to_status', 'problem')->last()?->from_status ?? 'selected') : $status, $flow, true);
                    $lastLog = $allocation->logs->last();
                    $statusUrl = route('admin.procurement.allocations.status', [$order, $allocation]);
                    $allocTitle = $itemName($item).' — '.$allocation->warehouse->name_az.', '.$allocation->quantity.' ədəd';
                    // Növbəti addım: bir düymə (əsas), qalanları menyuda
                    $next = ['selected' => ['notified', 'Anbara bildirildi'], 'notified' => ['reserved', 'Anbar ayırdı'], 'reserved' => ['picked', 'Götürüldü']][$status] ?? null;
                    // Telefonu olan anbara "bildirmək" = SMS (status özü "Anbara bildirildi" olur)
                    $hasPhone = (bool) $allocation->warehouse->phone;
                    $smsUrl = route('admin.procurement.allocations.sms', [$order, $allocation]);
                    $allocSms = $smsLogs['allocation'][$allocation->id] ?? null;
                @endphp
                <div class="proc-alloc proc-alloc--{{ $status }}">
                    <div class="proc-alloc__main">
                        <strong>{{ $allocation->warehouse->name_az }}</strong>
                        <span>{{ $allocation->quantity }} ədəd × {{ number_format((float) $allocation->unit_cost, 2) }} AZN</span>
                        @if($allocation->warehouse->phone)<a href="tel:{{ $allocation->warehouse->phone }}" class="text-muted">{{ $allocation->warehouse->phone }}</a>@endif
                    </div>
                    @unless($isCancelled)
                        <ol class="proc-steps" aria-label="Təminat mərhələləri">
                            @foreach($flow as $i => $step)
                                <li class="{{ $reached !== false && $i <= $reached ? 'is-done' : '' }}">{{ \App\Models\Procurement\OrderItemAllocation::LABELS[$step] }}</li>
                            @endforeach
                        </ol>
                    @endunless
                    @if($status === 'problem')
                        <div class="proc-alloc__problem">⚠ {{ $lastLog?->note ?? 'Problem' }}</div>
                    @endif
                    @if($allocSms && !$isCancelled)
                        <div class="proc-alloc__sms {{ $allocSms->isSent() ? 'is-ok' : 'is-bad' }}">{{ $allocSms->isSent() ? '✓ SMS '.$allocSms->created_at->format('d.m H:i') : '✕ SMS getmədi: '.$allocSms->error }}</div>
                    @endif
                    <div class="proc-alloc__foot">
                        <span class="badge {{ ['cancelled' => 'bg-outline-muted', 'problem' => 'bg-danger', 'picked' => 'bg-success', 'reserved' => 'bg-outline-success', 'returning' => 'bg-warning', 'returned' => 'bg-outline-muted'][$status] ?? 'bg-outline-primary' }}">{{ $allocation->label() }}</span>
                        @if($lastLog)<span class="text-muted small">{{ $lastLog->created_at->format('d.m H:i') }}@if($who($lastLog->user_id)) · {{ $who($lastLog->user_id) }}@endif @if($isCancelled && $lastLog->note) · {{ $lastLog->note }}@endif</span>@endif

                        @if($flowEditable && !$isCancelled && $status !== 'picked')
                            <div class="proc-alloc__actions">
                                @if($status === 'selected' && $hasPhone)
                                    <form method="POST" action="{{ $smsUrl }}" data-once>@csrf<button class="btn btn-sm btn-primary">{{ $allocSms ? 'SMS-i təkrar göndər' : 'Anbara SMS göndər' }}</button></form>
                                @elseif($next)
                                    <form method="POST" action="{{ $statusUrl }}">@csrf<input type="hidden" name="action" value="{{ $next[0] }}"><button class="btn btn-sm btn-primary">{{ $next[1] }}</button></form>
                                @endif
                                @if($status === 'problem')
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#procResumeModal"
                                            data-action="{{ $statusUrl }}" data-title="{{ $allocTitle }}" data-price="{{ $allocation->problem_type === 'price_changed' ? number_format((float) $allocation->unit_cost, 2, '.', '') : '' }}">Həll edildi, davam et</button>
                                @endif
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Digər</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if($status === 'notified' && $hasPhone)
                                            <form method="POST" action="{{ $smsUrl }}" data-once>@csrf<button class="dropdown-item">SMS-i təkrar göndər</button></form>
                                        @endif
                                        @if($status === 'selected' && $hasPhone)
                                            <form method="POST" action="{{ $statusUrl }}">@csrf<input type="hidden" name="action" value="notified"><button class="dropdown-item">Bildirildi (SMS-siz, telefonla)</button></form>
                                        @endif
                                        @foreach(['reserved' => 'Anbar ayırdı (telefonla)', 'picked' => 'Götürüldü'] as $to => $label)
                                            @if($status !== 'problem' && array_search($to, $flow, true) > array_search($status, $flow, true) && ($next[0] ?? null) !== $to)
                                                <form method="POST" action="{{ $statusUrl }}">@csrf<input type="hidden" name="action" value="{{ $to }}"><button class="dropdown-item">{{ $label }}</button></form>
                                            @endif
                                        @endforeach
                                        @if($status !== 'problem')
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#procProblemModal" data-action="{{ $statusUrl }}" data-title="{{ $allocTitle }}">Problem bildir</button>
                                        @endif
@if($editable)
                                        <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#procCancelModal"
                                                data-action="{{ route('admin.procurement.allocations.cancel', [$order, $allocation]) }}" data-title="{{ $allocTitle }}">Seçimi ləğv et</button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endforeach

<h2 class="small-title mt-5">Sorğular</h2>
@if($requests->isEmpty())
    <p class="text-muted">Hələ sorğu yaradılmayıb.</p>
@else
    <div class="table-responsive"><table class="table proc-table">
        <thead><tr><th>Sorğu</th><th>Anbar</th><th>Məhsullar</th><th>Yaradılıb</th><th>Cavab</th><th>Giriş linki</th></tr></thead>
        <tbody>
        @foreach($requests as $req)
            @php $answered = $req->items->filter(fn ($ri) => $ri->offers->isNotEmpty())->count(); @endphp
            <tr>
                <td>#{{ $req->id }}</td>
                <td>{{ $req->warehouse->name_az }}@if($req->warehouse->phone)<span class="proc-sub"><a href="tel:{{ $req->warehouse->phone }}">{{ $req->warehouse->phone }}</a></span>@endif</td>
                <td>{{ $req->items->map(fn ($ri) => $itemName($ri->orderItem).' ×'.$ri->requested_quantity)->implode(', ') }}</td>
                <td>{{ $req->created_at->format('d.m.Y H:i') }}@if($who($req->created_by))<span class="proc-sub">{{ $who($req->created_by) }}</span>@endif</td>
                <td><span class="badge {{ $answered === $req->items->count() ? 'bg-outline-success' : 'bg-outline-warning' }}">{{ $answered }}/{{ $req->items->count() }}</span></td>
                <td><form method="POST" action="{{ route('admin.procurement.warehouses.link', $req->warehouse) }}">@csrf<button class="btn btn-sm btn-outline-primary text-nowrap" @disabled(!$req->warehouse->active)>Link yarat</button></form></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif

@if($editable)
    {{-- Yeni sorğu: seçilən məhsullar hər seçilən anbara ayrıca sorğu kimi gedir --}}
    <div class="modal modal-right fade" id="procRequestModal" tabindex="-1" aria-labelledby="procRequestTitle" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.procurement.requests.store', $order) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="procRequestTitle">Yeni anbar sorğusu</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
            <div class="modal-body">
                <p class="text-muted small">Seçilən məhsullar hər seçilən anbar üçün ayrıca sorğuya düşür. Anbarlara fərqli siyahı lazımdırsa, ayrı-ayrı sorğu yaradın.</p>
                <div class="mb-4">
                    <div class="form-label fw-bold">Məhsullar</div>
                    @foreach($order->items->filter(fn ($i) => $i->activeQuantity() > 0) as $item)
                        @php $missing = max(0, $item->activeQuantity() - (int) $item->allocations->whereNotIn('status', \App\Models\Procurement\OrderItemAllocation::SUPPLY_INACTIVE)->sum('quantity')); @endphp
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="item_ids[]" value="{{ $item->id }}" @checked($missing > 0)>
                            <span class="form-check-label">{{ $itemName($item) }}@if($itemSize($item)) · {{ $itemSize($item) }}@endif × {{ $item->activeQuantity() }}
                                @unless($missing)<span class="text-success small"> · təmin edilib</span>@endunless</span>
                        </label>
                    @endforeach
                </div>
                <div>
                    <div class="form-label fw-bold">Anbarlar</div>
                    @foreach($warehouses as $warehouse)
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="warehouse_ids[]" value="{{ $warehouse->id }}">
                            <span class="form-check-label">{{ $warehouse->name_az }}@if($warehouse->phone) <span class="text-muted small">· {{ $warehouse->phone }}</span>@endif</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Sorğunu yarat</button></div>
        </form></div>
    </div>

    {{-- Anbarın cavabı: "Tam var / Qismən / Yoxdur" miqdarı doldurur --}}
    <div class="modal modal-right fade" id="procOfferModal" tabindex="-1" aria-labelledby="procOfferTitle" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="procOfferTitle">Anbarın cavabı</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
            <div class="modal-body">
                <div class="mb-3"><div class="fw-bold" data-offer-title></div><div class="text-muted small">Tələb: <span data-offer-requested></span> ədəd</div></div>
                <div class="btn-group w-100 mb-3" role="group" aria-label="Cavab növü">
                    <input type="radio" class="btn-check" name="answer" id="procAnsFull" value="full" checked><label class="btn btn-outline-primary" for="procAnsFull">Tam var</label>
                    <input type="radio" class="btn-check" name="answer" id="procAnsPart" value="part"><label class="btn btn-outline-primary" for="procAnsPart">Qismən</label>
                    <input type="radio" class="btn-check" name="answer" id="procAnsNone" value="none"><label class="btn btn-outline-primary" for="procAnsNone">Yoxdur</label>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6"><label class="form-label" for="procQty">Təmin edilən say</label><input id="procQty" type="number" name="available_quantity" min="0" class="form-control" required></div>
                    <div class="col-6" data-offer-cost><label class="form-label" for="procCost">1 ədəd, AZN</label><input id="procCost" type="number" name="unit_cost" min="0.01" step="0.01" class="form-control"></div>
                </div>
                <div class="mb-3"><label class="form-label" for="procSource">Cavabın mənbəyi</label>
                    <select id="procSource" name="source" class="form-select">@foreach(array_diff_key($sources, ['link' => true]) as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div><label class="form-label" for="procNote">Qeyd</label><input id="procNote" name="note" maxlength="2000" class="form-control" placeholder="İstəyə bağlı"></div>
                <div class="form-text mt-3">Əvvəlki cavab silinmir — tarixçəyə yeni cavab kimi əlavə olunur.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Yadda saxla</button></div>
        </form></div>
    </div>

    {{-- Seçimin ləğvi: səbəb məcburidir --}}
    <div class="modal modal-right fade" id="procCancelModal" tabindex="-1" aria-labelledby="procCancelTitle" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="procCancelTitle">Anbar seçimini ləğv et</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
            <div class="modal-body">
                <div class="fw-bold mb-3" data-cancel-title></div>
                <label class="form-label" for="procCancelNote">Səbəb</label>
                <textarea id="procCancelNote" name="note" maxlength="2000" rows="3" class="form-control" required></textarea>
                <div class="form-text">Məhsul sifarişdə qalır, yalnız bu anbar seçimi bağlanır.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-danger">Seçimi ləğv et</button></div>
        </form></div>
    </div>
@endif
@if($flowEditable)
    {{-- Problem bildir: növ + qeyd --}}
    <div class="modal modal-right fade" id="procProblemModal" tabindex="-1" aria-labelledby="procProblemTitle" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="">
            @csrf <input type="hidden" name="action" value="problem">
            <div class="modal-header"><h5 class="modal-title" id="procProblemTitle">Problem bildir</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
            <div class="modal-body">
                <div class="fw-bold mb-3" data-modal-title></div>
                <div class="mb-3"><label class="form-label" for="procProblemType">Nə baş verdi?</label>
                    <select id="procProblemType" name="problem_type" class="form-select" required>
                        @foreach(\App\Models\Procurement\OrderItemAllocation::PROBLEM_TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select></div>
                <label class="form-label" for="procProblemNote">Qeyd</label>
                <textarea id="procProblemNote" name="note" rows="3" maxlength="2000" class="form-control" placeholder="Məs.: anbar 55 AZN istəyir"></textarea>
                <div class="form-text mt-2">Problem həll olunana qədər bu hissə təmin edilmiş sayılmır.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-danger">Qeyd et</button></div>
        </form></div>
    </div>

    {{-- Problem həll edildi: əvvəlki mərhələyə qayıdır; qiymət dəyişibsə yeni alış qiyməti --}}
    <div class="modal modal-right fade" id="procResumeModal" tabindex="-1" aria-labelledby="procResumeTitle" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="">
            @csrf <input type="hidden" name="action" value="resume">
            <div class="modal-header"><h5 class="modal-title" id="procResumeTitle">Davam et</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
            <div class="modal-body">
                <div class="fw-bold mb-3" data-modal-title></div>
                <div class="mb-3" data-resume-price hidden><label class="form-label" for="procResumeCost">Razılaşdırılmış yeni alış qiyməti, AZN</label>
                    <input id="procResumeCost" type="number" name="unit_cost" min="0.01" step="0.01" class="form-control"></div>
                <label class="form-label" for="procResumeNote">Qeyd</label>
                <textarea id="procResumeNote" name="note" rows="2" maxlength="2000" class="form-control" placeholder="Necə həll olundu"></textarea>
                <div class="form-text mt-2">Seçim problemdən əvvəlki mərhələyə qayıdır. Başqa anbar lazımdırsa, "Seçimi ləğv et" istifadə edin.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Davam et</button></div>
        </form></div>
    </div>
@endif
<script src="{{ asset_v('backend/js/procurement-order.js') }}" defer></script>
