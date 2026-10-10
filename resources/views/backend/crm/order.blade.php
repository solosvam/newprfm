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
    $inactive = \App\Models\Procurement\OrderItemAllocation::SUPPLY_INACTIVE; // ləğv / anbara qaytarılır / qaytarıldı
    $supply = $order->items->mapWithKeys(fn ($item) => [$item->id => min($item->activeQuantity(), (int) $item->allocations->whereNotIn('status', $inactive)->sum('quantity'))]);
    $needTotal = (int) $order->items->sum(fn ($item) => $item->activeQuantity());
    $cancelPreviews = $cancelPreviews ?? [];
    $cancelBlock = $cancelBlock ?? null;
    // Sifarişin tam ləğvi: önizləmə verilməyibsə düymə göstərilmir
    $orderCancelPreview = $orderCancelPreview ?? null;
    $orderCancelBlock = $orderCancelBlock ?? null;
    $canCancelOrder = $orderCancelPreview !== null && !$order->isCancelled() && $order->status?->code !== 'delivered';
    $cancellations = $order->itemCancellations->groupBy('order_item_id');
    $pendingRefund = (float) $order->itemCancellations->whereIn('refund_status', [\App\Models\Order\OrderItemCancellation::REFUND_PENDING, \App\Models\Order\OrderItemCancellation::REFUND_PROCESSING])->sum('amount');
    $refundedToCard = (float) $order->itemCancellations->where('refund_status', \App\Models\Order\OrderItemCancellation::REFUND_DONE)->sum('amount');
    $supplyTotal = (int) $supply->sum();
    $supplyPercent = $needTotal ? round($supplyTotal / $needTotal * 100) : 0;
    $activeParts = $order->items->flatMap->allocations->whereNotIn('status', $inactive);
    $returningParts = $order->items->flatMap(fn ($item) => $item->allocations->where('status', 'returning')->map(fn ($a) => ['item' => $item, 'a' => $a]));
    $doorBlock = $doorBlock ?? 'x';

    // Kuryer bildirişləri: problem, qapıda imtina, anbara qaytarma, kuryerin ötürməsi (yenisi yuxarıda)
    $noticeLabels = ['delivery_problem' => ['Çatdırılma problemi', 'danger'], 'door_refusal' => ['Qapıda imtina', 'warning'],
        'warehouse_return' => ['Anbara qaytarıldı', 'success'], 'courier_change' => ['Kuryer dəyişdi', 'info']];
    $notices = $order->statusLogs->whereIn('kind', array_keys($noticeLabels))->sortByDesc('id')->values();
    $openProblems = $activeParts->where('status', 'problem');
    $pickedTotal = (int) $activeParts->where('status', 'picked')->sum('quantity');
    $problemCount = $activeParts->where('status', 'problem')->count();
    $requestItems = $requests->flatMap->items;
    $awaitingAnswers = $requestItems->filter(fn ($ri) => $ri->offers->isEmpty())->count();

    // Status tarixçəsi: "Ləğv edildi" yalnız baş verəndə göstərilir
    $timelineLogs = $order->statusLogs->sortBy('id')->keyBy('status_id');
    $logsByStatus = $order->statusLogs->sortBy('id')->groupBy('status_id'); // bir statusda bir neçə qeyd ola bilər
    $currentStatusId = $order->statusLogs->sortBy('id')->last()?->status_id ?? $order->order_status_id;
    $steps = $timelineStatuses->filter(fn ($s) => $s->code !== 'cancelled' || $timelineLogs->has($s->id));

    // "Proses" tabı: məhsulların qrupu/sırası və "növbəti addım" zolağı (OrderProcessSummary)
    $process = app(\App\Services\OrderProcessSummary::class)->for($order, $requests, $staff, $courierBlock ?? null, $order->status?->code === 'new' ? ($startBlock ?? null) : null);
    $processTones = ['warning' => 'alert-warning', 'danger' => 'alert-danger', 'success' => 'alert-success', 'info' => 'alert-info', 'muted' => 'alert-secondary'];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/crm-order-detail.css') }}">
    <link rel="stylesheet" href="{{ asset_v('backend/css/finance.css') }}">
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/cs/responsivetab.js') }}"></script>
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
                {{-- İkinci dərəcəli əməliyyatlar: müştəri kartı, bank yoxlaması, sifarişin tam ləğvi --}}
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Digər</button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="{{ route('admin.crm.customer', $customer) }}">Müştərinin səhifəsi</a>
                        @if($order->payments->contains('status', \App\Models\Payment\Payment::PENDING))
                            <button type="button" class="dropdown-item" data-payment-check
                                    data-url="{{ route('admin.crm.order.payment-check', [$customer, $order]) }}">Ödənişi bankdan yoxla</button>
                        @endif
                        {{-- Sifarişin tam ləğvi (ləğv edilmiş / təhvil verilmiş sifarişdə görünmür) --}}
                        @if($canCancelOrder)
                            <div class="dropdown-divider"></div>
                            @if($orderCancelBlock)
                                <span class="dropdown-item disabled" aria-disabled="true">Sifarişi ləğv et</span>
                                <span class="dropdown-item-text text-muted text-small">{{ $orderCancelBlock }}</span>
                            @else
                                <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">Sifarişi ləğv et</button>
                            @endif
                        @endif
                    </div>
                </div>
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
        </div>
    </div>
    @include('backend.procurement.feedback')
    @include('backend.procurement.access-link')

    {{-- Kuryer bildirişləri + açıq problemlər --}}
    @if($notices->isNotEmpty() || $openProblems->isNotEmpty() || $returningParts->isNotEmpty())
        <div class="card mb-3 od-notices"><div class="card-body">
            <h2 class="small-title mb-2">Kuryer bildirişləri</h2>
            @foreach($openProblems as $part)
                <div class="od-notice is-danger">
                    <span class="badge bg-danger">Toplama problemi</span>
                    <span>{{ $part->warehouse?->name_az }} — {{ \App\Models\Procurement\OrderItemAllocation::PROBLEM_TYPES[$part->problem_type] ?? 'Problem' }}@if($part->logs->last()?->note): {{ $part->logs->last()->note }}@endif</span>
                    <span class="od-notice__meta">{{ $part->logs->last()?->created_at?->format('d.m.Y H:i') }}</span>
                </div>
            @endforeach
            @foreach($returningParts as ['item' => $item, 'a' => $part])
                <div class="od-notice is-warning">
                    <span class="badge bg-warning">Anbara qaytarılır</span>
                    <span>{{ $item->product?->name }} ×{{ $part->quantity }} → {{ $part->warehouse?->name_az }} (kuryer "Qaytardım" seçəndə bağlanır)</span>
                </div>
            @endforeach
            @foreach($notices as $log)
                @php [$label, $tone] = $noticeLabels[$log->kind]; @endphp
                <div class="od-notice is-{{ $tone }}">
                    <span class="badge bg-{{ $tone }}">{{ $label }}</span>
                    <span>{{ $log->note }}</span>
                    <span class="od-notice__meta">{{ $log->created_at?->format('d.m.Y H:i') }}@if($log->user) · {{ $log->user->full_name }}@endif</span>
                </div>
            @endforeach
        </div></div>
    @endif

    {{-- Nazik zolaq: operatorun iş zamanı daim baxdığı məlumat (qalanı "Məlumat" tabındadır) --}}
    <div class="card mb-2">
        <div class="card-body py-3">
            <div class="row g-3 align-items-center">
                <div class="col-6 col-md-3">
                    <div class="text-small text-muted mb-1">STATUS</div>
                    <span class="badge bg-outline-primary">{{ $order->status?->name_az ?? '—' }}</span>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-small text-muted mb-1">MÜŞTƏRİ</div>
                    <a href="{{ route('admin.crm.customer', $customer) }}">{{ $customer->name }} {{ $customer->surname }}</a>
                    @if($customer->mobile)<a class="d-block text-small" href="tel:+{{ preg_replace('/\D+/', '', $customer->mobile) }}">{{ $customer->mobile }}</a>@endif
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-small text-muted mb-1">YEKUN</div>
                    <div class="cta-3 text-primary">{{ number_format((float) $order->total, 2) }} AZN</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-small text-muted mb-1">ÖDƏNİŞ</div>
                    <div>{{ $order->paymentMethod?->name_az ?? '—' }}</div>
                    {{-- Qapıda ödənişdə vəziyyət üsulun adını təkrarlayır ("Qapıda ödəniş") — nişan yalnız fərqli olanda --}}
                    @if(($paymentLabels[$order->payment_status] ?? null) && $paymentLabels[$order->payment_status] !== $order->paymentMethod?->name_az)
                        <span class="badge {{ $paymentBadges[$order->payment_status] ?? 'bg-outline-secondary' }}">{{ $paymentLabels[$order->payment_status] }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Növbəti addım: sifariş hansı mərhələdədir, indi nə etmək lazımdır, son əməliyyatı kim edib --}}
    <div class="alert {{ $processTones[$process['tone']] ?? 'alert-info' }} mb-3" role="status">
        <strong class="d-block">{{ $process['headline'] }}</strong>
        @if($process['detail'])<div class="text-small">{{ $process['detail'] }}</div>@endif
        @if($process['no_margin'] && !$order->isCancelled())<div class="text-small">{{ $process['no_margin'] }} məhsulda alış qiyməti satışdan aşağı deyil — qazanc yoxdur və ya zərərlədir.</div>@endif
        @if($process['last'])
            <div class="text-small mt-1">Son əməliyyat: {{ $process['last']['by'] ?? 'Sistem' }}, {{ $process['last']['at']->format('d.m H:i') }} — {{ $process['last']['text'] }}</div>
        @endif
    </div>

    {{-- Tablar: kartdan kənarda (fonun üstündə); hər tabın məzmunu öz kartlarındadır — kart içində kart yoxdur --}}
            @php
                $tabs = ['process' => ['Proses', $process['counts']['choose'] ?: null, $process['counts']['choose'] > 0],
                    'payments' => ['Ödənişlər', $order->payments->count() ?: null, false]];
                if (!empty($settlement)) $tabs['settlements'] = ['Hesablaşmalar', null, false];
                $tabs['item-history'] = ['Tarixçə', null, false];
                $tabs['info'] = ['Məlumat', null, false];
            @endphp
            {{-- Acorn "Responsive Tabs with Line Title": link kimi tablar; sığmayanlar "…" menyusuna düşür (responsivetab.js) --}}
            <ul class="nav nav-tabs nav-tabs-title nav-tabs-line-title responsive-tabs order-detail-tabs" role="tablist" aria-label="Sifariş bölmələri">
                @foreach($tabs as $key => [$label, $count, $warn])
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $loop->first ? 'active' : '' }}" id="{{ $key }}-tab" data-bs-toggle="tab" href="#{{ $key }}" data-bs-target="#{{ $key }}" role="tab" aria-controls="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}@if($count)<span class="od-tab-count {{ $warn ? 'is-warn' : '' }}">{{ $count }}</span>@endif</a>
                    </li>
                @endforeach
                <li class="nav-item dropdown ms-auto pe-0 d-none responsive-tab-dropdown">
                    <a class="btn btn-icon btn-icon-only btn-background pt-0 bg-transparent pe-0" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i data-acorn-icon="more-horizontal"></i></a>
                    <ul class="dropdown-menu mt-2 dropdown-menu-end"></ul>
                </li>
            </ul>
        <div class="tab-content mb-5">
            <div class="tab-pane fade show active" id="process" role="tabpanel" aria-labelledby="process-tab" tabindex="0">
                @if($cancelBlock && $order->items->contains(fn ($i) => $i->activeQuantity() > 0))
                    <div class="text-muted text-small mb-2">Məhsul ləğvi: {{ $cancelBlock }}</div>
                @endif
                @include('backend.procurement.order-content')
                {{-- Tam ləğv olunmuş məhsullar: proses yoxdur, yalnız qeyd --}}
                @php $cancelledItems = $order->items->filter(fn ($i) => $i->activeQuantity() === 0); @endphp
                @if($cancelledItems->isNotEmpty())
                    <h2 class="small-title mt-4">Ləğv edilən məhsullar <span class="text-muted">· {{ $cancelledItems->count() }}</span></h2>
                    <div class="card"><div class="card-body py-3">
                        @foreach($cancelledItems as $item)
                            <div class="{{ $loop->last ? '' : 'border-bottom pb-2 mb-2' }}">
                                <span class="text-muted text-decoration-line-through">{{ collect([$item->product?->brand?->name, $item->product?->name ?? 'Silinmiş məhsul', $item->variant?->size?->name_az])->filter()->implode(' · ') }}</span>
                                @foreach($cancellations[$item->id] ?? [] as $c)
                                    <div class="text-danger text-small">{{ $c->quantity }} ədəd ləğv edildi · {{ $c->reasonLabel() }}@if($c->note) — {{ $c->note }}@endif · {{ $c->created_at->format('d.m H:i') }}@if($c->user) · {{ $c->user->full_name }}@endif</div>
                                @endforeach
                            </div>
                        @endforeach
                    </div></div>
                @endif
                @include('backend.crm.partials.order-confirm')
            </div>
            <div class="tab-pane fade" id="item-history" role="tabpanel" aria-labelledby="item-history-tab" tabindex="0">
                <div class="card"><div class="card-body">@include('backend.crm.partials.order-item-history')</div></div>
            </div>
            <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab" tabindex="0">
                {{-- Sol: ödəniş linki (yalnız lazım olanda); sağ: ödəniş cəhdləri və geri qaytarmalar --}}
                <div class="row g-4">
                    @if($payLinkUrl)
                        <div class="col-xl-4">@include('backend.crm.partials.order-payment-link')</div>
                    @endif
                    <div class="{{ $payLinkUrl ? 'col-xl-8' : 'col-12' }}">
                        @include('backend.crm.partials.order-payments')
                        @include('backend.crm.partials.order-refunds')
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="info" role="tabpanel" aria-labelledby="info-tab" tabindex="0">
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
                        {{-- Yekun hesab: ayrıca kart deyil, sifariş məlumatlarının davamı --}}
                    <div class="od-summary border-top pt-3 mt-auto">
                        {{-- Məhsullar − Endirim + Çatdırılma + Qablaşdırma − Ləğv olunan = Yekun (Order::totalsBreakdown) --}}
                        @php $sum = $order->totalsBreakdown(); @endphp
                        <div><span>Məhsullar</span><span>{{ number_format($sum['goods'], 2) }} AZN</span></div>
                        @if($sum['discount'] > 0)<div><span>Endirim</span><span class="text-success">−{{ number_format($sum['discount'], 2) }} AZN</span></div>@endif
                        @if($sum['referral'] > 0)<div><span>Dəvət endirimi</span><span class="text-success">−{{ number_format($sum['referral'], 2) }} AZN</span></div>@endif
                        <div><span>Çatdırılma</span><span>{{ (float) $order->delivery_fee > 0 ? number_format((float) $order->delivery_fee, 2).' AZN' : 'Pulsuz' }}</span></div>
                        @if($order->gift_wrap)<div><span>Hədiyyəlik qablaşdırma</span><span>{{ (float) $order->gift_wrap_fee > 0 ? number_format((float) $order->gift_wrap_fee, 2).' AZN' : 'Pulsuz' }}</span></div>@endif
                        @if($sum['cancelled'] > 0)<div><span>Ləğv olunan</span><span class="text-danger">−{{ number_format($sum['cancelled'], 2) }} AZN</span></div>@endif
                        <div class="od-summary__grand"><span>Yekun</span><span>{{ number_format((float) $order->total, 2) }} AZN</span></div>
                        @if((float) $order->bonus_earned > 0)<div><span>Qazandığı bonus</span><span class="text-success">+{{ number_format((float) $order->bonus_earned, 2) }} AZN</span></div>
                        @elseif(($pendingBonus = app(\App\Services\BonusService::class)->pendingFor($order)) > 0)<div><span>Təhvil veriləndə bonus</span><span class="text-muted">+{{ number_format($pendingBonus, 2) }} AZN</span></div>@endif
                        @if($pendingRefund > 0)
                            <div><span>Karta qaytarılacaq</span><a href="#payments" data-order-tab-link class="text-warning">{{ number_format($pendingRefund, 2) }} AZN · gözləyir</a></div>
                        @endif
                        @if($refundedToCard > 0)
                            <div><span>Karta qaytarılıb</span><span>{{ number_format($refundedToCard, 2) }} AZN</span></div>
                        @endif
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
                            @if($customer->mobile_2)
                                <div><dt>Ehtiyat telefon</dt><dd><a href="tel:+{{ preg_replace('/\D+/', '', $customer->mobile_2) }}">{{ $customer->mobile_2 }}</a></dd></div>
                            @endif
                            <div class="od-facts__wide"><dt>Ünvan</dt><dd>
                                {{ $order->address?->label ?: 'Ünvan dəqiqləşdirilməyib' }}
                                @if($order->address)
                                    <a href="{{ $order->address->mapsUrl() }}" target="_blank" rel="noopener" class="ms-1 {{ $order->address->hasLocation() ? 'text-success' : 'text-muted' }}" title="{{ $order->address->hasLocation() ? 'Müştəri xəritədə nöqtə seçib' : 'Nöqtə seçilməyib — ünvan mətni ilə axtarış' }}">
                                        <i data-acorn-icon="pin" data-acorn-size="14"></i> xəritə
                                    </a>
                                @endif
                            </dd></div>
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
                        <div class="od-steps-box">
                        <ol class="od-steps scroll-out" aria-label="Sifarişin status tarixçəsi">
                            @foreach($steps as $step)
                                @php $log = $timelineLogs->get($step->id); @endphp
                                <li class="{{ $log ? 'is-done' : '' }} {{ $step->id === $currentStatusId ? 'is-current' : '' }} {{ $step->code === 'cancelled' ? 'is-cancel' : '' }}">
                                    <span class="od-steps__dot"></span>
                                    <div class="od-steps__title">{{ $step->name_az }}</div>
                                    @if($log)
                                        <div class="od-steps__meta">{{ $log->created_at?->format('d.m.Y H:i') }}@if($log->user) · {{ $log->user->full_name }}@endif</div>
                                        {{-- bu statusdakı bütün qeydlər (kuryer bildirişləri yuxarıda ayrıca da görünür) --}}
                                        @foreach($logsByStatus->get($step->id, collect())->filter(fn ($l) => $l->note) as $noteLog)
                                            <div class="od-steps__note">@if($logsByStatus->get($step->id)->count() > 1)<span class="text-muted">{{ $noteLog->created_at?->format('H:i') }}</span> @endif{{ $noteLog->note }}</div>
                                        @endforeach
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            </div>
            @if(!empty($settlement))
                <div class="tab-pane fade" id="settlements" role="tabpanel" aria-labelledby="settlements-tab" tabindex="0">@include('backend.crm.partials.order-settlements')</div>
            @endif
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

    @if($canCancelOrder && !$orderCancelBlock)
        {{-- Sifarişin tam ləğvi: nəticə serverdə hesablanıb (orderPreview) --}}
        @php
            $op = $orderCancelPreview;
            $refundLabel = [\App\Models\Order\OrderItemCancellation::REFUND_PENDING => 'Müştərinin kartına qaytarılmalıdır', \App\Models\Order\OrderItemCancellation::REFUND_BONUS => 'Bonus balansına qaytarılır'][$op['refund']] ?? null;
        @endphp
        <div class="modal modal-right fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderTitle" aria-hidden="true">
            <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.crm.order.cancel', [$customer, $order]) }}" data-once>
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="cancelOrderTitle">Sifarişi ləğv et — {{ $order->order_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label" for="cancelOrderReason">Səbəb</label>
                        <select id="cancelOrderReason" name="reason" class="form-select" required>
                            @foreach(\App\Models\Order\OrderItemCancellation::REASONS as $value => $label)@continue($value === 'door_refused')<option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>@endforeach
                        </select></div>
                    <div class="mb-3"><label class="form-label" for="cancelOrderNote">Qeyd</label><textarea id="cancelOrderNote" name="note" rows="2" maxlength="2000" class="form-control" placeholder="Müştəri ilə nə razılaşdırıldı (müştəri sifarişin tarixçəsində görür)">{{ old('note') }}</textarea></div>
                    <label class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="customer_agreed" value="1" required>
                        <span class="form-check-label">Müştəri ilə danışılıb, razıdır</span>
                    </label>
                    <div class="od-cancel-result">
                        <div><span>Məhsullar</span><b>{{ number_format($op['items'], 2) }} AZN</b></div>
                        @if($op['fees'] > 0)<div><span>Çatdırılma / qablaşdırma</span><b>{{ number_format($op['fees'], 2) }} AZN</b></div>@endif
                        @if($refundLabel)<div><span>{{ $refundLabel }}</span><b>{{ number_format($op['total'], 2) }} AZN</b></div>@endif
                        @if($op['bonus'] > 0)<div><span>Qazanılmış bonus geri alınır</span><b>{{ number_format($op['bonus'], 2) }} AZN</b></div>@endif
                        @if($op['returning'] > 0)<div><span>Kuryerdən anbara qaytarılır</span><b>{{ $op['returning'] }} ədəd</b></div>@endif
                        @if($op['notify'] > 0)<div><span>Anbarlara "rezerv lazım deyil" SMS-i</span><b>{{ $op['notify'] }}</b></div>@endif
                    </div>
                    <div class="form-text mt-2">Məhsullar sifarişdən silinmir — tarixçədə qalır. Anbar seçimləri bağlanır, status "Ləğv edildi" olur. Bu əməliyyat geri qaytarılmır.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-danger">Sifarişi ləğv et</button></div>
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
                            @foreach(\App\Models\Order\OrderItemCancellation::REASONS as $value => $label)@continue($value === 'door_refused')<option value="{{ $value }}">{{ $label }}</option>@endforeach
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

    {{-- Qapıda imtina (kuryer zəng edib bildirir): məbləğ azalır, məhsul anbara qaytarılır --}}
    @if(!$doorBlock)
        <div class="modal modal-right fade" id="doorRefuseModal" tabindex="-1" aria-labelledby="doorRefuseTitle" aria-hidden="true">
            <div class="modal-dialog"><form class="modal-content" method="POST" action="">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="doorRefuseTitle">Qapıda imtina</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
                <div class="modal-body">
                    <div class="fw-bold mb-1" data-door-item></div>
                    <div class="mb-3"><label class="form-label" for="doorQty">Müştəri götürmədi</label><select id="doorQty" name="quantity" class="form-select"></select></div>
                    <div class="mb-3"><label class="form-label" for="doorNote">Qeyd</label><textarea id="doorNote" name="note" rows="2" maxlength="2000" class="form-control" placeholder="Məs.: ətri bəyənmədi"></textarea></div>
                    <div class="form-text">Sifarişin yekunu azalır, kuryer yeni məbləği alır. Məhsul kuryerdə "Anbara qaytarılır" olur — kuryer anbara verəndə "Qaytardım" seçir.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-warning">Qeyd et</button></div>
            </form></div>
        </div>
    @endif
</div>
@endsection
