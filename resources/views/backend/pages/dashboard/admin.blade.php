{{--
  Əsas səhifə: operatorun əməliyyat paneli — indi nəyə baxmaq lazımdır.
  Ətraflı analitika (dövriyyə dinamikası, mənfəət, ən çox satılanlar, maliyyə...) — Statistika səhifəsində.
  $dashboard = ['demo', 'attention', 'active', 'carts', 'today' (AdminDashboard::stats('today')), 'recent' (son sifarişlər)]
--}}
@php
    $user = auth('admin')->user();
    $money = fn ($v, $d = 0) => number_format((float) $v, $d, '.', ' ');
    $canCrm = (bool) $user?->can('crm');
    $canStats = (bool) $user?->can('statistics');
    $today = $dashboard['today'];
    $carts = $dashboard['carts'];
    $activeTotal = array_sum(array_column($dashboard['active'], 'count'));

    $weekdays = ['Bazar', 'Bazar ertəsi', 'Çərşənbə axşamı', 'Çərşənbə', 'Cümə axşamı', 'Cümə', 'Şənbə'];
    $months = ['', 'yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avqust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'];
    $now = now();
    $hour = (int) $now->format('G');
    $greeting = $hour < 6 ? 'Salam' : ($hour < 12 ? 'Sabahınız xeyir' : ($hour < 18 ? 'Günortanız xeyir' : 'Axşamınız xeyir'));

    // Bu gün: qısa zolaq (dövriyyə yalnız Statistika icazəsi olana)
    $todayCards = array_values(array_filter([
        ['title' => 'Bu günkü sifariş', 'icon' => 'cart', 'value' => $money($today['orders']['value']), 'metric' => $today['orders'],
            'url' => $canCrm ? route('admin.orders.index') : null],
        $canStats ? ['title' => 'Bu günkü dövriyyə', 'icon' => 'money', 'value' => $money($today['revenue']['value']).' ₼', 'metric' => $today['revenue'],
            'url' => route('admin.statistics')] : null,
        ['title' => 'Yeni müştəri', 'icon' => 'user', 'value' => $money($today['customers']['value']), 'metric' => $today['customers'], 'url' => null],
        ['title' => 'Aktiv sifariş', 'icon' => 'send', 'value' => $money($activeTotal), 'metric' => null,
            'url' => $canCrm ? route('admin.orders.index', ['status' => 'active']) : null],
    ]));

    // Diqqət tələb edənlər: operatorun hərəkət etməli olduğu işlər
    $attentionItems = [
        ['key' => 'easy_orders', 'title' => 'Təsdiqlənməmiş asan sifariş', 'hint' => 'Müştəri bağlanmayıb', 'icon' => 'cart', 'tone' => 'danger',
            'url' => $canCrm ? route('admin.easy-orders.index') : null],
        ['key' => 'warehouse', 'title' => 'Anbar cavabı gözləyir', 'hint' => '2 saatdan çox', 'icon' => 'hourglass', 'tone' => 'warning', 'url' => null],
        ['key' => 'courier', 'title' => 'Kuryerdə ləngiyən', 'hint' => '1 gündən çox', 'icon' => 'delivery-truck', 'tone' => 'warning',
            'url' => $canCrm ? route('admin.orders.index', ['status' => 'courier_late']) : null],
        ['key' => 'refunds', 'title' => 'Gözləyən refund', 'hint' => 'Karta qaytarılmalıdır', 'icon' => 'credit-card', 'tone' => 'danger', 'url' => null],
        ['key' => 'credit', 'title' => 'Kredit müraciəti', 'hint' => 'Gözləmədə / yoxlanılır', 'icon' => 'file-text', 'tone' => 'primary',
            'url' => $user?->can('credit.menu') ? route('admin.credit.applications') : null],
        ['key' => 'carts', 'title' => 'Səbətdə gözləyən', 'hint' => '24 saatdır səbətinə toxunmayıb', 'icon' => 'basket', 'tone' => 'primary',
            'url' => $canCrm ? route('admin.carts.index', ['stale' => 1]) : null],
        ['key' => 'price_alerts', 'title' => 'Endirim gözləyən', 'hint' => 'Qiymət enəndə xəbər istəyib', 'icon' => 'sale-tag', 'tone' => 'primary',
            'url' => $canCrm ? route('admin.price-alerts.index') : null],
        ['key' => 'reviews', 'title' => 'Təsdiq gözləyən rəy', 'hint' => 'Saytda görünmür', 'icon' => 'message', 'tone' => 'primary',
            'url' => $user?->can('product.reviews') ? route('admin.product.review.list') : null],
    ];
    $attentionTotal = collect($attentionItems)->sum(fn ($i) => $dashboard['attention'][$i['key']] ?? 0);

    $statusColor = ['new' => 'primary', 'delivered' => 'success', 'cancelled' => 'danger', 'sent' => 'info', 'at_address' => 'info'];
@endphp

{{-- Başlıq: salam + tarix, sağda Statistika --}}
<div class="page-title-container">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-sm">
            <div class="text-muted dash-date">{{ $weekdays[$now->dayOfWeek] }}, {{ $now->day }} {{ $months[$now->month] }} {{ $now->year }}</div>
            <h1 class="mb-0 pb-0 display-4" id="title">{{ $greeting }}{{ $user?->name ? ', '.$user->name : '' }}</h1>
        </div>
        <div class="col-12 col-sm-auto d-flex gap-2 align-items-center">
            @if($dashboard['demo'])
                <span class="badge bg-outline-warning">Demo məlumat</span>
            @endif
            @if($canStats)
                <a href="{{ route('admin.statistics', array_filter(['demo' => $dashboard['demo'] ? 1 : null])) }}" class="btn btn-outline-primary btn-icon btn-icon-start w-100 w-sm-auto">
                    <i data-acorn-icon="chart-4"></i>
                    <span>Statistika</span>
                </a>
            @endif
        </div>
    </div>
</div>

{{-- Bu gün --}}
<div class="row g-2 mb-5">
    @foreach($todayCards as $card)
        <div class="col-6 col-lg">
            <div class="card h-100 dash-today {{ $card['url'] ? 'hover-border-primary' : '' }}">
                <div class="card-body py-3 px-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted dash-label">{{ $card['title'] }}</span>
                        <span class="dash-today-icon"><i data-acorn-icon="{{ $card['icon'] }}" data-acorn-size="16"></i></span>
                    </div>
                    <div class="d-flex align-items-baseline flex-wrap gap-2">
                        <div class="cta-1 text-primary lh-1">{{ $card['value'] }}</div>
                        @if($card['metric'] && $card['metric']['change'] !== null)
                            <span class="dash-change {{ $card['metric']['change'] >= 0 ? 'dash-change--up' : 'dash-change--down' }}"
                                  title="Dünən bu vaxta qədər: {{ $money($card['metric']['previous']) }}">
                                {{ $card['metric']['change'] >= 0 ? '▲' : '▼' }} {{ abs($card['metric']['change']) }}%
                            </span>
                        @elseif($card['metric'])
                            <span class="text-muted dash-label">dünən: {{ $money($card['metric']['previous']) }}</span>
                        @endif
                    </div>
                    @if($card['url'])
                        <a href="{{ $card['url'] }}" class="stretched-link" aria-label="{{ $card['title'] }}"></a>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Diqqət tələb edənlər --}}
<div class="mb-5">
    <div class="d-flex align-items-center justify-content-between">
        <h2 class="small-title">Diqqət tələb edənlər</h2>
        @if($attentionTotal === 0)
            <span class="badge bg-outline-success mb-2">Hər şey qaydasındadır</span>
        @endif
    </div>
    <div class="row g-2 row-cols-1 row-cols-sm-2 row-cols-xl-4">
        @foreach($attentionItems as $item)
            @php $count = $dashboard['attention'][$item['key']] ?? 0; @endphp
            <div class="col">
                <div class="card h-100 dash-alert {{ $count ? 'dash-alert--on dash-tone-'.$item['tone'] : 'dash-alert--off' }}">
                    <div class="card-body d-flex align-items-center gap-3 py-3 px-4">
                        <div class="dash-alert-icon flex-shrink-0">
                            <i data-acorn-icon="{{ $item['icon'] }}" data-acorn-size="20"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="heading mb-0 lh-1-25">{{ $item['title'] }}</div>
                            <div class="text-muted dash-label mt-1">{{ $item['hint'] }}</div>
                        </div>
                        <div class="dash-alert-count flex-shrink-0">{{ $count }}</div>
                        @if($item['url'] && $count)
                            <a href="{{ $item['url'] }}" class="stretched-link" aria-label="{{ $item['title'] }}"></a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Aktiv sifarişlər mərhələlər üzrə (axın) --}}
<div class="mb-5">
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="small-title">Aktiv sifarişlər</h2>
        @if($canCrm)
            <a href="{{ route('admin.orders.index', ['status' => 'active']) }}" class="btn btn-icon btn-icon-end btn-xs btn-background-alternate p-0 mb-2 dash-link">
                <span class="align-bottom">Cəmi {{ $activeTotal }} · Hamısına bax</span>
                <i data-acorn-icon="chevron-right" class="align-middle" data-acorn-size="12"></i>
            </a>
        @else
            <span class="text-muted mb-2">Cəmi: {{ $activeTotal }}</span>
        @endif
    </div>
    <div class="card">
        <div class="card-body py-4">
            <div class="dash-flow">
                @foreach($dashboard['active'] as $step)
                    <div class="dash-flow-step {{ $step['count'] ? 'is-on' : '' }}">
                        <div class="dash-flow-icon">
                            <i data-acorn-icon="{{ $step['icon'] }}" data-acorn-size="18"></i>
                        </div>
                        <div class="dash-flow-count">{{ $step['count'] }}</div>
                        <div class="dash-flow-name">{{ $step['name'] }}</div>
                        @if($step['count'] && $canCrm)
                            <a href="{{ route('admin.orders.index', ['status' => $step['code']]) }}" class="stretched-link" aria-label="{{ $step['name'] }}"></a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Son sifarişlər --}}
    <div class="col-12 col-xl-7 mb-5">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="small-title">Son sifarişlər</h2>
            @if($canCrm)
                <a href="{{ route('admin.orders.index') }}" class="btn btn-icon btn-icon-end btn-xs btn-background-alternate p-0 mb-2 dash-link">
                    <span class="align-bottom">Bütün sifarişlər</span>
                    <i data-acorn-icon="chevron-right" class="align-middle" data-acorn-size="12"></i>
                </a>
            @endif
        </div>
        <div class="card h-100-card">
            <div class="card-body py-2">
                @forelse($dashboard['recent'] as $order)
                    @php
                        $url = !$canCrm ? null : ($order->customer_id
                            ? route('admin.crm.order', [$order->customer_id, $order->id])
                            : ($order->one_click ? route('admin.easy-orders.show', $order) : null));
                        $name = $order->customer?->full_name ?: ($order->guest_mobile ?: 'Qonaq');
                    @endphp
                    <div class="dash-order position-relative {{ $loop->last ? '' : 'border-bottom border-separator-light' }}">
                        <div class="dash-order-avatar">{{ mb_strtoupper(mb_substr(trim($name), 0, 1)) }}</div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-truncate">{{ $name }}</span>
                                @if($order->one_click)<span class="badge bg-outline-secondary">Asan</span>@endif
                            </div>
                            <div class="text-muted dash-label text-truncate">
                                {{ $order->order_no }} · {{ $order->paymentMethod?->name_az ?? '—' }} · {{ $order->created_at?->diffForHumans() }}
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            {{-- tam ləğv: yekun 0-dır — sifarişin ilkin məbləği göstərilir (məbləğin statusa aidiyyəti yoxdur) --}}
                            <div class="fw-bold text-nowrap">{{ $money($order->isFullyCancelled() ? $order->originalTotal() : $order->total, 2) }} ₼</div>
                            <span class="badge bg-outline-{{ $statusColor[$order->status?->code] ?? 'secondary' }}">{{ $order->status?->name_az ?? '—' }}</span>
                        </div>
                        @if($url)
                            <a href="{{ $url }}" class="stretched-link" aria-label="{{ $order->order_no }}"></a>
                        @endif
                    </div>
                @empty
                    <div class="text-muted text-center py-5">Hələ sifariş yoxdur</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Səbətlər: indiki an --}}
    <div class="col-12 col-xl-5 mb-5">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="small-title">Səbətlərdə</h2>
            @if($canCrm)
                <a href="{{ route('admin.carts.index') }}" class="btn btn-icon btn-icon-end btn-xs btn-background-alternate p-0 mb-2 dash-link">
                    <span class="align-bottom">Səbətdəki mallar</span>
                    <i data-acorn-icon="chevron-right" class="align-middle" data-acorn-size="12"></i>
                </a>
            @endif
        </div>
        <div class="card h-100-card">
            <div class="card-body">
                <div class="row g-0 text-center dash-cart-stats mb-3">
                    <div class="col">
                        <div class="cta-3 text-primary">{{ $money($carts['customers']) }}</div>
                        <div class="text-muted dash-label">müştəri</div>
                    </div>
                    <div class="col">
                        <div class="cta-3 text-primary">{{ $money($carts['quantity']) }}</div>
                        <div class="text-muted dash-label">məhsul</div>
                    </div>
                    <div class="col">
                        <div class="cta-3 text-success text-nowrap">{{ $money($carts['value']) }} ₼</div>
                        <div class="text-muted dash-label">təxmini dəyər</div>
                    </div>
                </div>
                <div class="text-muted dash-label text-uppercase mb-1">Ən çox səbətə atılanlar</div>
                @forelse($carts['top'] as $item)
                    <div class="d-flex align-items-center justify-content-between py-2 {{ $loop->last ? '' : 'border-bottom border-separator-light' }}">
                        <div class="min-w-0 pe-3">
                            @if($item['id'] && $user?->can('products.menu'))
                                <a href="{{ route('admin.product.edit', $item['id']) }}" class="body-link d-block text-truncate">{{ $item['name'] }}</a>
                            @else
                                <div class="text-truncate">{{ $item['name'] }}</div>
                            @endif
                            <div class="text-muted dash-label text-truncate">{{ $item['brand'] }}{{ $item['size'] ? ' · '.$item['size'] : '' }} · {{ $money($item['price'], 2) }} ₼</div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="fw-bold text-primary text-nowrap">{{ $item['customers'] }} müştəri</div>
                            <div class="text-muted dash-label">{{ $item['quantity'] }} ədəd</div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted text-center py-4">Səbətlərdə məhsul yoxdur</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
