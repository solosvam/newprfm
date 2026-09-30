@php
    $html_tag_data = [];
    $title = 'Sifariş '.$order->order_no;
    $breadcrumbs = [route('admin.crm.index') => 'CRM', route('admin.crm.customer', $customer) => trim($customer->name.' '.$customer->surname), '' => $order->order_no];
    $paymentLabels = ['paid' => 'Ödənilib', 'pending' => 'Gözləyir', 'failed' => 'Uğursuz', 'cancelled' => 'Ləğv edilib', 'cod' => 'Qapıda ödəniş'];
    $paymentBadges = ['paid' => 'bg-success', 'pending' => 'bg-outline-warning', 'failed' => 'bg-outline-danger', 'cancelled' => 'bg-outline-danger', 'cod' => 'bg-outline-secondary'];
    $operationLabels = ['refund' => 'Geri ödəniş', 'reversal' => 'Ödənişin ləğvi', 'clearing' => 'Ödənişin tamamlanması', 'recurring' => 'Təkrar ödəniş'];
    $operationStatuses = ['pending' => 'Gözləyir', 'succeeded' => 'Tamamlanıb', 'failed' => 'Uğursuz'];
    $sourceLabels = ['website' => 'Sayt', 'operator' => 'Operator', 'customer' => 'Asan sifariş', 'manual' => 'Əl ilə', 'one_click' => 'Bir kliklə al'];
    $staff = $staff ?? collect();

    // Təminat: hər məhsul üzrə aktiv (ləğv olunmamış) anbar seçimləri
    $supply = $order->items->mapWithKeys(fn ($item) => [$item->id => min($item->activeQuantity(), (int) $item->allocations->where('status', '!=', 'cancelled')->sum('quantity'))]);
    $needTotal = (int) $order->items->sum(fn ($item) => $item->activeQuantity());
    $cancelPreviews = $cancelPreviews ?? [];
    $cancelBlock = $cancelBlock ?? null;
    $cancellations = $order->itemCancellations->groupBy('order_item_id');
    $pendingRefund = (float) $order->itemCancellations->whereIn('refund_status', [\App\Models\Order\OrderItemCancellation::REFUND_PENDING, \App\Models\Order\OrderItemCancellation::REFUND_PROCESSING])->sum('amount');
    $refundedToCard = (float) $order->itemCancellations->where('refund_status', \App\Models\Order\OrderItemCancellation::REFUND_DONE)->sum('amount');
    $supplyTotal = (int) $supply->sum();
    $supplyPercent = $needTotal ? round($supplyTotal / $needTotal * 100) : 0;
    $activeParts = $order->items->flatMap->allocations->where('status', '!=', 'cancelled');
    $pickedTotal = (int) $activeParts->where('status', 'picked')->sum('quantity');
    $problemCount = $activeParts->where('status', 'problem')->count();
    $requestItems = $requests->flatMap->items;
    $awaitingAnswers = $requestItems->filter(fn ($ri) => $ri->offers->isEmpty())->count();

    // Status tarixçəsi: "Ləğv edildi" yalnız baş verəndə göstərilir
    $timelineLogs = $order->statusLogs->sortBy('id')->keyBy('status_id');
    $currentStatusId = $order->statusLogs->sortBy('id')->last()?->status_id ?? $order->order_status_id;
    $steps = $timelineStatuses->filter(fn ($s) => $s->code !== 'cancelled' || $timelineLogs->has($s->id));
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/crm-order-detail.css') }}">
    <link rel="stylesheet" href="{{ asset_v('backend/css/finance.css') }}">
@endsection

@section('js_page')
    <script src="{{ asset_v('backend/js/crm-order-detail.js') }}"></script>
    <script src="{{ asset_v('backend/js/finance.js') }}"></script>
@endsection

@section('content')
<div class="container order-workspace" data-order-workspace>
    <div class="page-title-container">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-md-6 d-flex flex-wrap gap-2 align-items-start justify-content-md-end">
                <a class="btn btn-outline-primary btn-icon btn-icon-start" href="{{ route('admin.crm.customer', $customer) }}"><i data-acorn-icon="user" data-acorn-size="16"></i><span>Müştəri</span></a>
                @if($payLinkUrl)
                    <a class="btn btn-outline-primary btn-icon btn-icon-start" href="#payments" data-order-tab-link><i data-acorn-icon="link" data-acorn-size="16"></i><span>Ödəniş linki</span></a>
                @endif
                {{-- Növbəti addım: İcraya götür → (anbarlar) → Kuryer təyin et --}}
                @if($order->status?->code === 'new')
                    @if($startBlock ?? null)
                        <span class="d-inline-block" tabindex="0" title="{{ $startBlock }}" data-bs-toggle="tooltip">
                            <button type="button" class="btn btn-primary" disabled>İcraya götür</button>
                        </span>
                    @else
                        <form method="POST" action="{{ route('admin.crm.order.start', [$customer, $order]) }}" data-once>
                            @csrf<button class="btn btn-primary btn-icon btn-icon-start"><i data-acorn-icon="arrow-right" data-acorn-size="16"></i><span>İcraya götür</span></button>
                        </form>
                    @endif
                @elseif(in_array($order->status?->code, [...\App\Services\OrderStatusService::PROCUREMENT, 'courier_assigned'], true))
                    {{-- İcraya götürüləndən sonra həmişə görünür; hələ mümkün deyilsə bağlıdır, səbəbi altda --}}
                    <button type="button" class="btn btn-primary btn-icon btn-icon-start" data-bs-toggle="modal" data-bs-target="#courierModal" @disabled($courierBlock ?? false)>
                        <i data-acorn-icon="shipping" data-acorn-size="16"></i><span>{{ $order->courier_id ? 'Kuryeri dəyiş' : 'Kuryer təyin et' }}</span>
                    </button>
                @endif
            </div>
            @if(($courierBlock ?? null) && $order->status?->code !== 'new' && in_array($order->status?->code, [...\App\Services\OrderStatusService::PROCUREMENT, 'courier_assigned'], true))
                <div class="col-12 text-md-end small text-muted">{{ $courierBlock }}</div>
            @endif
            @if($order->status?->code === 'new' && ($startBlock ?? null))
                <div class="col-12"><div class="alert alert-warning mb-0">{{ $startBlock }}</div></div>
            @endif
        </div>
    </div>
    @include('backend.procurement.feedback')
    @include('backend.procurement.access-link')

    {{-- Üst kartlar --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-4 d-flex flex-column">
            <h2 class="small-title">Sifariş məlumatları</h2>
            <div class="card flex-grow-1">
                <div class="card-body d-flex flex-column">
                    <dl class="od-facts">
                        <div><dt>Nömrə</dt><dd>{{ $order->order_no }}</dd></div>
                        <div><dt>Tarix</dt><dd>{{ $order->created_at?->format('d.m.Y H:i') ?? '—' }}</dd></div>
                        <div><dt>Status</dt><dd><span class="badge bg-outline-primary">{{ $order->status?->name_az ?? '—' }}</span></dd></div>
                        <div><dt>Mənbə</dt><dd>{{ $sourceLabels[$order->source] ?? ($order->source ?: '—') }}@if($order->created_by && $staff->has($order->created_by)) · {{ $staff[$order->created_by] }}@endif</dd></div>
                        <div><dt>Ödəniş üsulu</dt><dd>{{ $order->paymentMethod?->name_az ?? '—' }}@if($order->birbank_installment_months) · {{ $order->birbank_installment_months }} ay @endif</dd></div>
                        <div><dt>Ödəniş vəziyyəti</dt><dd><span class="badge {{ $paymentBadges[$order->payment_status] ?? 'bg-outline-secondary' }}">{{ $paymentLabels[$order->payment_status] ?? '—' }}</span></dd></div>
                    </dl>
                    <div class="od-total mt-auto">
                        <span class="text-muted">Yekun məbləğ</span>
                        <strong class="h3 mb-0">{{ number_format((float) $order->total, 2) }} <small>AZN</small></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4 d-flex flex-column">
            <h2 class="small-title">Müştəri və çatdırılma</h2>
            <div class="card flex-grow-1">
                <div class="card-body d-flex flex-column">
                    <dl class="od-facts">
                        <div><dt>Müştəri</dt><dd><a href="{{ route('admin.crm.customer', $customer) }}">#{{ $customer->id }} {{ $customer->name }} {{ $customer->surname }}</a></dd></div>
                        <div><dt>Telefon</dt><dd>@if($customer->mobile)<a href="tel:+{{ preg_replace('/\D+/', '', $customer->mobile) }}">{{ $customer->mobile }}</a>@else — @endif</dd></div>
                        <div class="od-facts__wide"><dt>Ünvan</dt><dd>{{ $order->address?->label ?: 'Ünvan dəqiqləşdirilməyib' }}</dd></div>
                        <div><dt>Qablaşdırma</dt><dd>@if($order->gift_wrap)<span class="badge bg-outline-primary">Hədiyyəlik</span>@else Standart @endif</dd></div>
                        <div><dt>Kuryer</dt><dd>
                            @if($order->courier)
                                {{ $order->courier->full_name }}@if($order->courier->mobile)<a class="d-block small" href="tel:{{ $order->courier->mobile }}">{{ $order->courier->mobile }}</a>@endif
                            @else<span class="text-muted">Təyin edilməyib</span>@endif
                        </dd></div>
                        <div class="od-facts__wide"><dt>Müştərinin qeydi</dt><dd>{{ $order->customer_note ?: '—' }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4 d-flex flex-column">
            <h2 class="small-title">Status tarixçəsi</h2>
            <div class="card flex-grow-1">
                <div class="card-body d-flex flex-column">
                    <ol class="od-steps scroll-out" aria-label="Sifarişin status tarixçəsi">
                        @foreach($steps as $step)
                            @php $log = $timelineLogs->get($step->id); @endphp
                            <li class="{{ $log ? 'is-done' : '' }} {{ $step->id === $currentStatusId ? 'is-current' : '' }} {{ $step->code === 'cancelled' ? 'is-cancel' : '' }}">
                                <span class="od-steps__dot"></span>
                                <div class="od-steps__title">{{ $step->name_az }}</div>
                                @if($log)
                                    <div class="od-steps__meta">{{ $log->created_at?->format('d.m.Y H:i') }}@if($log->user) · {{ $log->user->full_name }}@endif</div>
                                    @if($log->note)<div class="od-steps__note">{{ $log->note }}</div>@endif
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- Xülasə: klik → müvafiq tab --}}
    <div class="od-kpis mb-3">
        <a class="od-kpi" href="#procurement" data-order-tab-link>
            <div class="od-kpi__label">Təminat</div>
            <div class="od-kpi__value">{{ $supplyTotal }} / {{ $needTotal }} ədəd seçilib</div>
            <div class="progress"><div class="progress-bar {{ $supplyPercent >= 100 ? 'bg-success' : 'bg-warning' }}" style="width: {{ $supplyPercent }}%"></div></div>
        </a>
        <a class="od-kpi" href="#procurement" data-order-tab-link>
            <div class="od-kpi__label">Anbar sorğuları</div>
            <div class="od-kpi__value">
                @if($requests->isEmpty()) Sorğu yoxdur
                @else {{ $requests->count() }} sorğu @if($awaitingAnswers)· <span class="text-warning">{{ $awaitingAnswers }} cavab gözlənilir</span>@else· hamısı cavablanıb @endif
                @endif
            </div>
        </a>
        <a class="od-kpi" href="#procurement" data-order-tab-link>
            <div class="od-kpi__label">Toplama</div>
            <div class="od-kpi__value">{{ $pickedTotal }} / {{ $needTotal }} ədəd götürülüb @if($problemCount)· <span class="text-danger">{{ $problemCount }} problem</span>@endif</div>
            <div class="progress"><div class="progress-bar bg-success" style="width: {{ $needTotal ? round($pickedTotal / $needTotal * 100) : 0 }}%"></div></div>
        </a>
        <a class="od-kpi" href="#payments" data-order-tab-link>
            <div class="od-kpi__label">Ödəniş</div>
            <div class="od-kpi__value">{{ $paymentLabels[$order->payment_status] ?? '—' }} · {{ number_format((float) $order->total, 2) }} AZN</div>
        </a>
    </div>

    {{-- Tablar --}}
    <div class="card mb-5">
        <div class="card-header border-0 pb-0">
            @php
                $tabs = [
                'products' => ['Məhsullar', $order->items->count(), false],
                'item-history' => ['Məhsulların tarixçəsi', null, false],
                'procurement' => ['Anbar sorğuları', $awaitingAnswers ?: null, $awaitingAnswers > 0],
                'payments' => ['Ödənişlər', $order->payments->count() ?: null, false],
            ];
            if (!empty($settlement)) $tabs['settlements'] = ['Hesablaşmalar', null, false];
            @endphp
            <ul class="nav nav-tabs nav-tabs-line card-header-tabs order-detail-tabs" role="tablist" aria-label="Sifariş bölmələri">
                @foreach($tabs as $key => [$label, $count, $warn])
                    <li class="nav-item" role="presentation"><button class="nav-link {{ $loop->first ? 'active' : '' }}" id="{{ $key }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $key }}" type="button" role="tab" aria-controls="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}@if($count)<span class="od-tab-count {{ $warn ? 'is-warn' : '' }}">{{ $count }}</span>@endif</button></li>
                @endforeach
            </ul>
        </div>
        <div class="card-body tab-content p-3">
            <div class="tab-pane fade show active" id="products" role="tabpanel" aria-labelledby="products-tab" tabindex="0">
                @if($cancelBlock && $order->items->contains(fn ($i) => $i->activeQuantity() > 0))
                    <div class="text-muted small mb-2">Məhsul ləğvi: {{ $cancelBlock }}</div>
                @endif
                <div class="table-responsive"><table class="table od-table">
                    <thead><tr><th>Məhsul</th><th class="od-num">Sifariş</th><th class="od-num">Ləğv</th><th class="od-num">Qalan</th><th class="od-num">Satış qiyməti</th><th class="od-num">Məbləğ</th><th>Təminat</th><th></th></tr></thead>
                    <tbody>
                    @foreach($order->items as $item)
                        @php
                            $got = $supply[$item->id];
                            $active = $item->activeQuantity();
                            $itemTitle = ($item->product?->name ?? 'Silinmiş məhsul').($item->variant?->size ? ' · '.$item->variant->size->name_az : '');
                        @endphp
                        <tr class="{{ $active === 0 ? 'od-row-cancelled' : '' }}">
                            <td>
                                <span class="od-name">{{ $item->product?->name ?? 'Silinmiş məhsul' }}</span>
                                <span class="od-sub">{{ collect([$item->product?->brand?->name, $item->variant?->size?->name_az])->filter()->implode(' · ') }}</span>
                                @foreach($cancellations[$item->id] ?? [] as $c)
                                    <span class="od-sub text-danger">{{ $c->quantity }} ədəd ləğv edildi · {{ $c->reasonLabel() }}@if($c->note) — {{ $c->note }}@endif · {{ $c->created_at->format('d.m H:i') }}@if($c->user) · {{ $c->user->full_name }}@endif</span>
                                @endforeach
                            </td>
                            <td class="od-num">{{ $item->quantity }}</td>
                            <td class="od-num {{ $item->cancelled_quantity ? 'text-danger' : 'text-muted' }}">{{ $item->cancelled_quantity ?: '—' }}</td>
                            <td class="od-num">{{ $active }}</td>
                            <td class="od-num">@if($item->list_price > $item->unit_price)<s class="text-muted small">{{ number_format((float) $item->list_price, 2) }}</s> @endif{{ number_format((float) $item->unit_price, 2) }} AZN</td>
                            <td class="od-num">{{ number_format((float) $item->total, 2) }} AZN</td>
                            <td>
                                @php
                                    $supplyStatus = $item->supplyStatus();
                                    $supplyBadge = ['pending' => 'bg-outline-muted', 'cancelled' => 'bg-outline-muted', 'problem' => 'bg-danger',
                                        'partly_allocated' => 'bg-outline-warning', 'partly_picked' => 'bg-outline-warning',
                                        'allocated' => 'bg-outline-primary', 'reserved' => 'bg-outline-success', 'picked' => 'bg-success'][$supplyStatus];
                                @endphp
                                <span class="badge {{ $supplyBadge }}">{{ $item->supplyLabel() }}@if(in_array($supplyStatus, ['partly_allocated'], true)) · {{ $got }}/{{ $active }}@endif</span>
                            </td>
                            <td class="text-end">
                                @if(isset($cancelPreviews[$item->id]))
                                    <button type="button" class="btn btn-sm btn-outline-danger text-nowrap" data-bs-toggle="modal" data-bs-target="#cancelItemModal"
                                            data-action="{{ route('admin.crm.order.item.cancel', [$customer, $order, $item]) }}"
                                            data-title="{{ $itemTitle }}" data-active="{{ $active }}"
                                            data-previews='@json($cancelPreviews[$item->id])'>Ləğv et</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="od-summary mt-3">
                    <div><span>Ara cəm</span><span>{{ number_format((float) $order->subtotal, 2) }} AZN</span></div>
                    @if((float) $order->discount > 0)<div><span>Endirim</span><span class="text-success">−{{ number_format((float) $order->discount, 2) }} AZN</span></div>@endif
                    <div><span>Çatdırılma</span><span>{{ (float) $order->delivery_fee > 0 ? number_format((float) $order->delivery_fee, 2).' AZN' : 'Pulsuz' }}</span></div>
                    @if($order->gift_wrap)<div><span>Hədiyyəlik qablaşdırma</span><span>{{ (float) $order->gift_wrap_fee > 0 ? number_format((float) $order->gift_wrap_fee, 2).' AZN' : 'Pulsuz' }}</span></div>@endif
                    <div class="od-summary__grand"><span>Yekun</span><span>{{ number_format((float) $order->total, 2) }} AZN</span></div>
                    @if((float) $order->bonus_earned > 0)<div><span>Qazandığı bonus</span><span class="text-success">+{{ number_format((float) $order->bonus_earned, 2) }} AZN</span></div>@endif
                    @if($order->itemCancellations->isNotEmpty())
                        <div><span>Ləğv edilən məhsullar</span><span class="text-danger">{{ number_format((float) $order->itemCancellations->sum('amount'), 2) }} AZN</span></div>
                    @endif
                    @if($pendingRefund > 0)
                        <div><span>Karta qaytarılacaq</span><a href="#payments" data-order-tab-link class="text-warning">{{ number_format($pendingRefund, 2) }} AZN · gözləyir</a></div>
                    @endif
                    @if($refundedToCard > 0)
                        <div><span>Karta qaytarılıb</span><span>{{ number_format($refundedToCard, 2) }} AZN</span></div>
                    @endif
                </div>
                @include('backend.crm.partials.order-confirm')
            </div>
            <div class="tab-pane fade" id="item-history" role="tabpanel" aria-labelledby="item-history-tab" tabindex="0">@include('backend.crm.partials.order-item-history')</div>
            <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab" tabindex="0">
                @if($payLinkUrl)<div class="border rounded p-3 mb-4">@include('backend.crm.partials.order-payment-link')</div>@endif
                @include('backend.crm.partials.order-payments')
                @include('backend.crm.partials.order-refunds')
            </div>
            <div class="tab-pane fade" id="procurement" role="tabpanel" aria-labelledby="procurement-tab" tabindex="0">@include('backend.procurement.order-content')</div>
            @if(!empty($settlement))
                <div class="tab-pane fade" id="settlements" role="tabpanel" aria-labelledby="settlements-tab" tabindex="0">@include('backend.crm.partials.order-settlements')</div>
            @endif
        </div>
    </div>

    @if(empty($courierBlock) && in_array($order->status?->code, ['warehouses_assigned', 'courier_assigned'], true))
        {{-- Kuryer təyini: "Kuryer" rolundakı aktiv əməkdaşlar --}}
        <div class="modal modal-right fade" id="courierModal" tabindex="-1" aria-labelledby="courierModalTitle" aria-hidden="true">
            <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.crm.order.courier', [$customer, $order]) }}" data-once>
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="courierModalTitle">{{ $order->courier_id ? 'Kuryeri dəyiş' : 'Kuryer təyin et' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
                <div class="modal-body">
                    @forelse($couriers ?? [] as $c)
                        <label class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="courier_id" value="{{ $c->id }}" required @checked($order->courier_id === $c->id)>
                            <span class="form-check-label">{{ $c->full_name }}@if($c->mobile) <span class="text-muted small">· {{ $c->mobile }}</span>@endif</span>
                        </label>
                    @empty
                        <p class="text-muted">Aktiv kuryer yoxdur. Əməkdaşa "Kuryer" rolunu verin.</p>
                    @endforelse
                    <div class="form-text mt-3">Sifarişin əvvəlindən sonuna eyni kuryer işləyir. Kuryer məhsul götürəndən sonra dəyişmək olmaz.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary" @disabled(empty($couriers) || $couriers->isEmpty())>Təyin et</button></div>
            </form></div>
        </div>
    @endif

    @if($cancelPreviews)
        {{-- Məhsul ləğvi: nəticə (yekun, qaytarma, bonus) serverdə hesablanıb, JS say dəyişdikcə göstərir --}}
        <div class="modal modal-right fade" id="cancelItemModal" tabindex="-1" aria-labelledby="cancelItemTitle" aria-hidden="true">
            <div class="modal-dialog"><form class="modal-content" method="POST" action="">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="cancelItemTitle">Məhsulu ləğv et</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
                <div class="modal-body">
                    <div class="fw-bold mb-1" data-cancel-item></div>
                    <div class="text-muted small mb-3">Qalan: <span data-cancel-active></span> ədəd</div>
                    <div class="mb-3"><label class="form-label" for="cancelQty">Ləğv edilən say</label><select id="cancelQty" name="quantity" class="form-select"></select></div>
                    <div class="mb-3"><label class="form-label" for="cancelReason">Səbəb</label>
                        <select id="cancelReason" name="reason" class="form-select" required>
                            @foreach(\App\Models\Order\OrderItemCancellation::REASONS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select></div>
                    <div class="mb-3"><label class="form-label" for="cancelNote">Qeyd</label><textarea id="cancelNote" name="note" rows="2" maxlength="2000" class="form-control" placeholder="Müştəri ilə nə razılaşdırıldı"></textarea></div>
                    <label class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="customer_agreed" value="1" required>
                        <span class="form-check-label">Müştəri ilə danışılıb, razıdır</span>
                    </label>
                    <div class="od-cancel-result">
                        <div><span>Yekundan çıxılır</span><b data-r="amount"></b></div>
                        <div><span>Yeni yekun</span><b data-r="total"></b></div>
                        <div data-r-row="refund"><span data-r="refund-label"></span><b data-r="refund"></b></div>
                        <div data-r-row="bonus"><span>Qazanılmış bonusdan geri alınır</span><b data-r="bonus"></b></div>
                    </div>
                    <div class="form-text mt-2">Məhsul sifarişdən silinmir — tarixçədə qalır. Artıq qalan anbar seçimləri bağlanır.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-danger">Ləğv et</button></div>
            </form></div>
        </div>
    @endif
</div>
@endsection
