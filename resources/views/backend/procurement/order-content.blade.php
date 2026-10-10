{{--
  Anbar təminatı — məhsul mərkəzli: məhsullar qruplara bölünür (cavab gəlib → cavab gözləyir → anbar seçilib),
  hər məhsul yığılan kartdır (Acorn "Accordion Cards"): cavablar açılır/yığılır, seçimlər həmişə görünür.
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
    // Mənbə: cavabı kim daxil edib — operator (hansı yolla) və ya anbar özü (link ilə)
    $sources = ['phone' => 'Operator - Telefon', 'whatsapp' => 'Operator - WhatsApp', 'telegram' => 'Operator - Telegram', 'manual' => 'Operator - Digər', 'link' => 'Anbar - link'];
    $itemName = fn ($item) => ($item?->product?->name ?? 'Silinmiş məhsul');
    $itemSize = fn ($item) => $item?->variant?->size?->name_az;
    // order_item_id => [sorğu sətri + onun sorğusu]
    $requestRows = $requests->flatMap(fn ($req) => $req->items->map(fn ($ri) => ['req' => $req, 'ri' => $ri]))->groupBy(fn ($row) => $row['ri']->order_item_id);
@endphp
<link rel="stylesheet" href="{{ asset_v('backend/css/procurement-order.css') }}">

@php
    // Məhsulların qrupu və sırası (cavab gəlib → cavab gözləyir → anbar seçilib) — OrderProcessSummary
    $process = $process ?? app(\App\Services\OrderProcessSummary::class)->for($order, $requests, $staff);
    $itemsById = $order->items->keyBy('id');
    // CRM sifariş səhifəsində məhsulun "⋯" menyusunda ləğv əməliyyatları da olur
    $crm = isset($customer);
    $smsProblems = $requests->filter(fn ($r) => !$r->warehouse->phone || !($smsLogs['request'][$r->id] ?? null)?->isSent())->count();
    $phoneError = fn ($log) => $log && !$log->isSent() && \Illuminate\Support\Str::contains(mb_strtolower((string) $log->error), 'nömrə');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="text-muted text-small">
        @if($order->status?->code === 'new') Anbar sorğusu sifariş icraya götürüləndən sonra göndərilir.
        @elseif(!$editable) Bu mərhələdə anbar seçimi dəyişdirilmir{{ $flowEditable ? ' — yalnız götürmə mərhələləri qeyd olunur' : '' }}.
        @else Anbar seçimi rezervasiya təsdiqi deyil. Müştərinin satış qiyməti dəyişmir. @endif
    </div>
    @if($editable)
        <button type="button" class="btn btn-primary btn-icon btn-icon-start" data-bs-toggle="modal" data-bs-target="#procRequestModal" @disabled($warehouses->isEmpty())>
            <i data-acorn-icon="plus" data-acorn-size="16"></i><span>Yeni sorğu</span>
        </button>
    @endif
</div>

@foreach(\App\Services\OrderProcessSummary::GROUPS as $group => $groupLabel)
    @php $groupIds = array_values(array_filter($process['order'], fn ($id) => $process['items'][$id]['group'] === $group)); @endphp
    @continue(!$groupIds)
    <h2 class="small-title">{{ $groupLabel }} <span class="text-muted">· {{ count($groupIds) }}</span></h2>
    <div class="mb-4">
    @foreach($groupIds as $itemId)
        @php
            $item = $itemsById[$itemId];
            $state = $process['items'][$itemId];
            $need = $item->activeQuantity(); // ləğv olunan miqdar təmin edilmir
            $active = $item->allocations->whereNotIn('status', \App\Models\Procurement\OrderItemAllocation::SUPPLY_INACTIVE);
            $selected = (int) $active->sum('quantity');
            $missing = max(0, $need - $selected);
            $rows = ($requestRows[$item->id] ?? collect())->map(function ($row) use ($active) {
                $offer = $row['ri']->offers->sortByDesc('id')->first();
                $taken = $offer ? (int) $active->where('warehouse_offer_id', $offer->id)->sum('quantity') : 0;
                return $row + ['offer' => $offer, 'taken' => $taken, 'answers' => $row['ri']->offers->count()];
            });
            $cheapest = $rows->filter(fn ($r) => $r['offer']?->available_quantity && $r['offer']->unit_cost !== null)->min(fn ($r) => (float) $r['offer']->unit_cost);
            // Təklif verənlər ucuzdan bahaya; cavab verməyən / "yoxdur" deyənlər ayrıca (yığılmış)
            $offered = $rows->filter(fn ($r) => $r['offer']?->available_quantity)->sortBy(fn ($r) => (float) $r['offer']->unit_cost)->values();
            // Təklifsizlər: cavab gözləyən əvvəl (hələ cavab daxil etmək olar), "yoxdur" deyən sonra
            $silent = $rows->reject(fn ($r) => $r['offer']?->available_quantity)->sortBy(fn ($r) => $r['offer'] ? 1 : 0)->values();
            $silentWaiting = $silent->filter(fn ($r) => !$r['offer'])->count();
            $silentNone = $silent->count() - $silentWaiting;
            $open = $group !== 'selected';
            $alert = $state['problem'] || $state['sms_failed'];
            $cancelledParts = $item->allocations->where('status', \App\Models\Procurement\OrderItemAllocation::CANCELLED);
            $liveParts = $item->allocations->where('status', '!=', \App\Models\Procurement\OrderItemAllocation::CANCELLED);
            $itemTitle = $itemName($item).($itemSize($item) ? ' · '.$itemSize($item) : '');
        @endphp
        {{-- Acorn "Accordion Cards": başlığa klik cavabları açır/yığır; seçimlər (mərhələ xətti, növbəti düymə) həmişə görünür --}}
        <div class="card d-flex mb-2 {{ $alert ? 'border border-danger' : '' }}">
            <div class="d-flex align-items-center">
                <div class="d-flex flex-grow-1" role="button" data-bs-toggle="collapse" data-bs-target="#procItem{{ $item->id }}"
                     aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="procItem{{ $item->id }}">
                    <div class="card-body py-3">
                        <div class="list-item-heading d-flex flex-wrap align-items-center gap-2">
                            <span>{{ $itemName($item) }}@if($itemSize($item))<span class="text-muted fw-normal"> · {{ $itemSize($item) }}</span>@endif</span>
                            {{-- Xülasə: məhsul hansı vəziyyətdədir (kart yığılı olanda da görünür) --}}
                            <span class="badge rounded-pill bg-outline-info">
                                <i data-acorn-icon="info-circle" data-acorn-size="14"></i>
                                @if($group === 'selected')
                                    @php
                                        $parts = $active->map(fn ($a) => $a->warehouse->name_az.' — təklif '.$a->quantity.' × '.number_format((float) $a->unit_cost, 2).' AZN');
                                        $isCheapest = $active->count() === 1 && $cheapest !== null && (float) $active->first()->unit_cost <= (float) $cheapest;
                                    @endphp
                                    @if($active->count() === 1)
                                        {{ $rows->count() }} anbardan {{ $isCheapest && $rows->count() > 1 ? 'ən ucuz təklif verən ' : '' }}{{ $active->first()->warehouse->name_az }} seçildi — təklif {{ $active->first()->quantity }} × {{ number_format((float) $active->first()->unit_cost, 2) }} AZN
                                    @else
                                        {{ $rows->count() }} anbardan {{ $active->count() }}-i seçildi: {{ $parts->implode('; ') }}
                                    @endif
                                @elseif($group === 'choose')
                                    {{ $offered->count() }} anbar təklif verib, ən ucuzu {{ number_format((float) $cheapest, 2) }} AZN
                                    @if($selected) · {{ $selected }} / {{ $need }} seçilib, {{ $missing }} çatışmır @endif
                                @else
                                    {{ $rows->isEmpty() ? 'Hələ sorğu göndərilməyib' : $rows->count().' anbara sorğu göndərilib, təklif yoxdur' }}
                                @endif
                            </span>
                        </div>
                        @if($crm)
                            @foreach(($cancellations[$item->id] ?? []) as $c)
                                <div class="text-danger text-small">{{ $c->quantity }} ədəd ləğv edildi · {{ $c->reasonLabel() }}@if($c->note) — {{ $c->note }}@endif · {{ $c->created_at->format('d.m H:i') }}</div>
                            @endforeach
                        @endif
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 pe-3 text-nowrap">
                    <span class="text-alternate">{{ $need }} ədəd @if($crm)· <strong>{{ number_format((float) $item->total, 2) }} AZN</strong>@endif</span>
                    @if($crm && ((!($doorBlock ?? 'x') && $need > 0 && $needTotal > 1) || isset($cancelPreviews[$item->id])))
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-icon btn-icon-only btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Məhsul əməliyyatları">
                                <i data-acorn-icon="more-horizontal" data-acorn-size="16"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                @if(!($doorBlock ?? 'x') && $need > 0 && $needTotal > 1)
                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#doorRefuseModal"
                                            data-action="{{ route('admin.crm.order.item.refuse', [$customer, $order, $item]) }}"
                                            data-title="{{ $itemTitle }}" data-active="{{ $need }}">Qapıda imtina</button>
                                @endif
                                @if(isset($cancelPreviews[$item->id]))
                                    <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelItemModal"
                                            data-action="{{ route('admin.crm.order.item.cancel', [$customer, $order, $item]) }}"
                                            data-title="{{ $itemTitle }}" data-active="{{ $need }}"
                                            data-previews='@json($cancelPreviews[$item->id])'>Məhsulu ləğv et</button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if($liveParts->isNotEmpty())
                <div class="card-body pt-0 pb-3">
                    @foreach($liveParts as $allocation)
                        @include('backend.procurement.partials.allocation')
                    @endforeach
                    {{-- Anbarların cavabları aşağıda yığılıb — açıldığı bilinsin deyə görünən keçid --}}
                    @if($rows->isNotEmpty())
                        <a class="d-inline-block mt-3" data-bs-toggle="collapse" href="#procItem{{ $item->id }}" role="button"
                           aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="procItem{{ $item->id }}">
                            <i data-acorn-icon="chevron-bottom" data-acorn-size="16"></i> Sorğu detalları ({{ $rows->count() }} anbar)
                        </a>
                    @endif
                </div>
            @endif

            <div id="procItem{{ $item->id }}" class="collapse {{ $open ? 'show' : '' }}">
                <div class="card-body accordion-content pt-0">
                    @if($rows->isEmpty())
                        <p class="text-muted mb-0">Bu məhsul üçün hələ sorğu yoxdur — "Yeni sorğu" ilə anbarlardan soruşun.</p>
                    @else
                        @if($offered->isNotEmpty())
                            <div class="table-responsive"><table class="table proc-table" style="table-layout: fixed; min-width: 760px">
                                <thead><tr><th>Anbar</th><th>Cavab</th><th class="text-end">Vahid alış</th><th>Mənbə</th><th class="text-end">Seçim</th></tr></thead>
                                <tbody>
                                @foreach($offered as $row)
                                    @include('backend.procurement.partials.offer-row')
                                @endforeach
                                </tbody>
                            </table></div>
                        @endif
                        @if($silent->isNotEmpty())
                            {{-- Təklifi olmayan anbarlar: təklif verən varsa yığılır (uzun siyahı səhifəni doldurmasın) --}}
                            @if($offered->isNotEmpty())
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                                    <span class="text-alternate">{{ collect([$silentWaiting ? $silentWaiting.' — cavab gözlənilir' : null, $silentNone ? $silentNone.' — yoxdur seçilib' : null])->filter()->implode(', ') }}</span>
                                    <a data-bs-toggle="collapse" href="#procSilent{{ $item->id }}" role="button" aria-expanded="false" aria-controls="procSilent{{ $item->id }}">
                                        <i data-acorn-icon="chevron-bottom" data-acorn-size="16"></i> Göstər
                                    </a>
                                </div>
                            @endif
                            <div id="procSilent{{ $item->id }}" class="collapse {{ $offered->isEmpty() ? 'show' : '' }}">
                                {{-- Eyni sütun enləri: yuxarıdakı təkliflər cədvəli ilə üst-üstə düşsün --}}
                                <div class="table-responsive"><table class="table proc-table" style="table-layout: fixed; min-width: 760px">
                                    @if($offered->isEmpty())
                                        <thead><tr><th>Anbar</th><th>Cavab</th><th class="text-end">Vahid alış</th><th>Mənbə</th><th class="text-end">Seçim</th></tr></thead>
                                    @endif
                                    <tbody>
                                    @foreach($silent as $row)
                                        @include('backend.procurement.partials.offer-row')
                                    @endforeach
                                    </tbody>
                                </table></div>
                            </div>
                        @endif
                    @endif
                    @foreach($cancelledParts as $allocation)
                        @include('backend.procurement.partials.allocation')
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
    </div>
@endforeach

{{-- Sorğular və anbar SMS-ləri: ikinci dərəcəli — yığılmış açılır, operatorun seçimi yadda qalır (procurement-order.js) --}}
@if($requests->isNotEmpty())
    <div class="card d-flex mb-2">
        <div class="d-flex flex-grow-1" role="button" data-bs-toggle="collapse" data-bs-target="#procMore" aria-expanded="false" aria-controls="procMore">
            <div class="card-body py-3">
                <div class="list-item-heading">Sorğular və anbar SMS-ləri
                    @if($smsProblems)<span class="badge bg-danger ms-2">{{ $smsProblems }} xəta</span>@endif
                </div>
                <div class="text-muted text-small">{{ $requests->count() }} sorğu · anbar linkləri və SMS vəziyyəti</div>
            </div>
        </div>
        <div id="procMore" class="collapse" data-remember-collapse="proc-more">
            <div class="card-body accordion-content pt-0">
                <div class="table-responsive"><table class="table proc-table">
                    <thead><tr><th>Sorğu</th><th>Anbar</th><th>Məhsullar</th><th>Yaradılıb</th><th>Cavab</th><th>SMS</th><th></th></tr></thead>
                    <tbody>
                    @foreach($requests as $req)
                        @php
                            $answered = $req->items->filter(fn ($ri) => $ri->offers->isNotEmpty())->count();
                            $log = $smsLogs['request'][$req->id] ?? null;
                        @endphp
                        <tr>
                            <td>#{{ $req->id }}</td>
                            <td>{{ $req->warehouse->name_az }}@if($req->warehouse->phone)<span class="proc-sub"><a href="tel:{{ $req->warehouse->phone }}">{{ $req->warehouse->phone }}</a></span>@endif</td>
                            <td>{{ $req->items->map(fn ($ri) => $itemName($ri->orderItem).' ×'.$ri->requested_quantity)->implode(', ') }}</td>
                            <td>{{ $req->created_at->format('d.m.Y H:i') }}@if($who($req->created_by))<span class="proc-sub">{{ $who($req->created_by) }}</span>@endif</td>
                            <td><span class="badge {{ $answered === $req->items->count() ? 'bg-outline-success' : 'bg-outline-warning' }}">{{ $answered }}/{{ $req->items->count() }}</span></td>
                            <td class="{{ $log?->isSent() ? 'text-success' : ($log || !$req->warehouse->phone ? 'text-danger' : 'text-muted') }}">
                                @if(!$req->warehouse->phone) Telefon yazılmayıb
                                @elseif(!$log) Göndərilməyib
                                @elseif($log->isSent()) ✓ SMS {{ $log->created_at->format('d.m H:i') }}
                                @else ✕ {{ $log->error }} @endif
                            </td>
                            <td class="text-end">
                                <div class="proc-actions">
                                    @if($editable && (!$req->warehouse->phone || $phoneError($log)))
                                        <a class="btn btn-sm btn-primary text-nowrap" href="{{ route('admin.procurement.warehouses.edit', $req->warehouse) }}">Nömrəni düzəlt</a>
                                    @elseif($editable && $req->warehouse->phone)
                                        <form method="POST" action="{{ route('admin.procurement.requests.sms', [$order, $req->id]) }}" data-once>@csrf
                                            <button class="btn btn-sm btn-outline-primary text-nowrap">{{ $log ? 'SMS-i təkrar göndər' : 'SMS göndər' }}</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.procurement.warehouses.link', $req->warehouse) }}">@csrf<button class="btn btn-sm btn-outline-primary text-nowrap" @disabled(!$req->warehouse->active)>Link yarat</button></form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
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
