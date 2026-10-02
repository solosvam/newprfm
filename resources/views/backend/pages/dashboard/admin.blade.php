{{--
  Əsas səhifə: admin/operator paneli.
  $dashboard = ['period', 'stats' (AdminDashboard::stats), 'payments' (ödəniş üsulları), 'sales' (son 7 gün)]
--}}
@use('App\Services\Dashboard\AdminDashboard')
@php
    $period = $dashboard['period'];
    $money = fn ($v, $d = 0) => number_format((float) $v, $d, '.', ' ');
    $stats = $dashboard['stats'];
    $cards = [
        ['key' => 'revenue', 'title' => 'Dövriyyə', 'icon' => 'money', 'format' => fn ($v) => $money($v).' ₼'],
        ['key' => 'profit', 'title' => 'Mənfəət', 'icon' => 'trend-up', 'format' => fn ($v) => $money($v).' ₼'],
        ['key' => 'orders', 'title' => 'Sifarişlər', 'icon' => 'cart', 'format' => fn ($v) => $money($v)],
        ['key' => 'average', 'title' => 'Orta sifariş', 'icon' => 'basket', 'format' => fn ($v) => $money($v, 2).' ₼'],
    ];
    $sales = $dashboard['sales'];

    // Ödəniş üsulları: 4 rəng — çox olarsa, ən kiçikləri "Digər"də birləşir
    $colors = ['primary', 'secondary', 'tertiary', 'quaternary'];
    $payments = collect($dashboard['payments']);
    if ($payments->count() > count($colors)) {
        $rest = $payments->slice(count($colors) - 1);
        $payments = $payments->take(count($colors) - 1)->push([
            'name' => 'Digər', 'orders' => $rest->sum('orders'), 'sum' => $rest->sum('sum'), 'share' => round($rest->sum('share'), 1),
        ]);
    }
    $paymentTotal = $payments->sum('sum');
@endphp

{{-- Diqqət tələb edənlər: operatorun hərəkət etməli olduğu işlər --}}
@php
    $user = auth('admin')->user();
    $attentionItems = [
        ['key' => 'warehouse', 'title' => 'Anbar cavabı gözləyir', 'hint' => '2 saatdan çox', 'icon' => 'hourglass', 'url' => null],
        ['key' => 'easy_orders', 'title' => 'Təsdiqlənməmiş asan sifariş', 'hint' => 'Müştəri bağlanmayıb', 'icon' => 'cart',
            'url' => $user?->can('crm') ? route('admin.easy-orders.index') : null],
        ['key' => 'credit', 'title' => 'Kredit müraciəti', 'hint' => 'Gözləmədə / yoxlanılır', 'icon' => 'file-text',
            'url' => $user?->can('credit.menu') ? route('admin.credit.applications') : null],
        ['key' => 'reviews', 'title' => 'Təsdiq gözləyən rəy', 'hint' => 'Saytda görünmür', 'icon' => 'message',
            'url' => $user?->can('product.reviews') ? route('admin.product.review.list') : null],
        ['key' => 'refunds', 'title' => 'Gözləyən refund', 'hint' => 'Karta qaytarılmalıdır', 'icon' => 'credit-card', 'url' => null],
        ['key' => 'courier', 'title' => 'Kuryerdə ləngiyən', 'hint' => '1 gündən çox', 'icon' => 'delivery-truck',
            'url' => $user?->can('crm') ? route('admin.orders.index', ['status' => 'courier_late']) : null],
    ];
@endphp
<div class="mb-5">
    <h2 class="small-title">Diqqət tələb edənlər</h2>
    <div class="row g-2 row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-6">
        @foreach($attentionItems as $item)
            @php $count = $dashboard['attention'][$item['key']] ?? 0; @endphp
            <div class="col">
                <div class="card h-100 {{ $count ? 'hover-border-primary' : 'opacity-50' }}">
                    <div class="card-body py-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="heading mb-0 lh-1-25 pe-2">{{ $item['title'] }}</div>
                            <i data-acorn-icon="{{ $item['icon'] }}" class="{{ $count ? 'text-primary' : 'text-muted' }} flex-shrink-0"></i>
                        </div>
                        <div class="text-small text-muted mb-1">{{ $item['hint'] }}</div>
                        <div class="cta-1 {{ $count ? 'text-primary' : 'text-muted' }}">{{ $count }}</div>
                        @if($item['url'] && $count)
                            <a href="{{ $item['url'] }}" class="stretched-link" aria-label="{{ $item['title'] }}"></a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="row">
    {{-- Statistika (dövr seçimi ilə) --}}
    <div class="col-12 col-xl-6 mb-5">
        <div class="d-flex align-items-center">
            <div class="dropdown me-3">
                <a class="pe-0 pt-0 align-top lh-1 dropdown-toggle small-title" href="#" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">
                    {{ AdminDashboard::PERIODS[$period] }}
                </a>
                <div class="dropdown-menu font-standard">
                    @foreach(AdminDashboard::PERIODS as $key => $label)
                        <a class="dropdown-item text-medium {{ $key === $period ? 'active' : '' }}"
                           href="{{ route('admin.main', array_filter(['period' => $key, 'demo' => $dashboard['demo'] ? 1 : null])) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <h2 class="small-title">Statistika</h2>
            @if($dashboard['demo'])
                <span class="badge bg-outline-warning ms-2 mb-2">Demo məlumat</span>
            @endif
        </div>
        <div class="row g-2">
            @foreach($cards as $card)
                @php $m = $stats[$card['key']]; @endphp
                <div class="col-12 col-sm-6">
                    <div class="card h-100 sh-xl-24">
                        <div class="h-100 row g-0 card-body align-items-center py-4">
                            <div class="col-auto">
                                <div class="bg-gradient-light sw-6 sh-6 rounded-md d-flex justify-content-center align-items-center">
                                    <i data-acorn-icon="{{ $card['icon'] }}" class="text-white"></i>
                                </div>
                            </div>
                            <div class="col ps-3 d-flex flex-column justify-content-center">
                                <div class="heading mb-1 lh-1-25">
                                    {{ $card['title'] }}
                                    @if($card['key'] === 'profit' && $m['margin'] !== null)
                                        <span class="badge bg-outline-primary ms-1" title="Mal satışına nisbətən">{{ $m['margin'] }}%</span>
                                    @endif
                                </div>
                                <div class="row g-0 align-items-center">
                                    <div class="col-auto">
                                        <div class="cta-2 {{ $card['key'] === 'profit' && $m['value'] < 0 ? 'text-danger' : 'text-primary' }}">{{ $card['format']($m['value']) }}</div>
                                    </div>
                                    @if($m['change'] !== null)
                                        <div class="col {{ $m['change'] >= 0 ? 'text-success' : 'text-danger' }} d-flex align-items-center ps-3">
                                            <i data-acorn-icon="{{ $m['change'] >= 0 ? 'arrow-top' : 'arrow-bottom' }}" class="me-1" data-acorn-size="13"></i>
                                            <span class="text-medium">{{ ($m['change'] > 0 ? '+' : '').$m['change'] }}%</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="text-small text-muted mt-1">
                                    @if($card['key'] === 'profit')
                                        {{ $m['orders'] }} təhvil verilmiş sifariş üzrə
                                    @else
                                        {{ AdminDashboard::PREVIOUS[$period] }}: {{ $card['format']($m['previous']) }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Satış dinamikası: son 7 gün --}}
    <div class="col-12 col-xl-6 mb-5">
        <h2 class="small-title">Satış — son 7 gün</h2>
        @foreach([
            ['id' => 'dashRevenueChart', 'label' => 'Dövriyyə, ₼', 'data' => $sales['revenue'], 'total' => $money(array_sum($sales['revenue'])).' ₼', 'avg' => $money(array_sum($sales['revenue']) / 7).' ₼', 'color' => 'primary'],
            ['id' => 'dashOrdersChart', 'label' => 'Sifarişlər', 'data' => $sales['orders'], 'total' => array_sum($sales['orders']), 'avg' => $money(array_sum($sales['orders']) / 7, 1), 'color' => 'secondary'],
        ] as $chart)
            <div class="card h-auto sh-xl-24 {{ $loop->first ? 'mb-2' : '' }}">
                <div class="card-body">
                    <div class="row g-0 h-100 chart-container">
                        {{-- Yuxarı hissəni (seçilmiş gün) charts.extend.js doldurur --}}
                        <div class="col-12 col-sm-auto d-flex flex-column justify-content-between custom-tooltip pe-0 pe-sm-4 sw-sm-19">
                            <p class="heading title mb-1"></p>
                            <div>
                                <div>
                                    <div class="cta-2 text-{{ $chart['color'] }} value d-inline-block align-middle"></div>
                                    <i class="icon d-inline-block align-middle text-{{ $chart['color'] }}" data-acorn-size="15"></i>
                                </div>
                                <div class="text-small text-muted mb-1 text"></div>
                            </div>
                            <div class="row g-3 flex-nowrap">
                                <div class="col-auto">
                                    <div class="cta-4 text-alternate text-nowrap">{{ $chart['total'] }}</div>
                                    <div class="text-small text-muted mb-1">7 GÜN</div>
                                </div>
                                <div class="col-auto">
                                    <div class="cta-4 text-alternate text-nowrap">{{ $chart['avg'] }}</div>
                                    <div class="text-small text-muted mb-1">GÜNDƏ ORTA</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm sh-17">
                            <canvas id="{{ $chart['id'] }}" class="dashboard-line-chart"
                                    data-label="{{ $chart['label'] }}"
                                    data-color="{{ $chart['color'] }}"
                                    data-labels='@json($sales['labels'])'
                                    data-values='@json($chart['data'])'></canvas>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Ödəniş üsulları üzrə bölgü (seçilmiş dövr) --}}
<div class="row">
    <div class="col-12 col-xl-6 mb-5">
        <h2 class="small-title">Ödəniş üsulları — {{ mb_strtolower(AdminDashboard::PERIODS[$period]) }}</h2>
        <div class="card h-100-card">
            <div class="card-body">
                @if($payments->isEmpty())
                    <div class="text-muted text-center py-5">Bu dövrdə sifariş yoxdur</div>
                @else
                    <div class="row g-0 align-items-center">
                        <div class="col-12 col-sm-5 mb-4 mb-sm-0">
                            <div class="position-relative sh-25">
                                <canvas id="dashPaymentsChart"
                                        data-labels='@json($payments->pluck('name'))'
                                        data-values='@json($payments->pluck('sum'))'
                                        data-colors='@json(array_slice($colors, 0, $payments->count()))'></canvas>
                                <div class="position-absolute top-50 start-50 translate-middle text-center pe-none">
                                    <div class="text-small text-muted text-uppercase">Cəmi</div>
                                    <div class="cta-3 text-primary">{{ $money($paymentTotal) }} ₼</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-7 ps-sm-5">
                            @foreach($payments->values() as $i => $method)
                                <div class="row g-0 align-items-center {{ $loop->last ? '' : 'mb-4' }}">
                                    <div class="col-auto">
                                        <div class="sw-5 sh-5 rounded-xl border border-{{ $colors[$i] }} d-flex justify-content-center align-items-center">
                                            <i data-acorn-icon="{{ ['cash' => 'money', 'card_online' => 'credit-card', 'bonus_balance' => 'gift'][$method['code'] ?? ''] ?? 'wallet' }}" class="text-{{ $colors[$i] }}" data-acorn-size="18"></i>
                                        </div>
                                    </div>
                                    <div class="col ps-3">
                                        <div class="d-flex justify-content-between align-items-end">
                                            <div class="lh-1-25">
                                                {{ $method['name'] }}
                                                <div class="text-small text-muted">{{ $method['orders'] }} sifariş · {{ $money($method['sum']) }} ₼</div>
                                            </div>
                                            <div class="cta-3 text-{{ $colors[$i] }}">{{ $method['share'] }}%</div>
                                        </div>
                                        <div class="progress dashboard-progress mt-2">
                                            <div class="progress-bar bg-{{ $colors[$i] }}" role="progressbar" style="width: {{ $method['share'] }}%"
                                                 aria-valuenow="{{ $method['share'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Ən çox satılan məhsullar (seçilmiş dövr) --}}
    <div class="col-12 col-xl-6 mb-5">
        <h2 class="small-title">Ən çox satılanlar — {{ mb_strtolower(AdminDashboard::PERIODS[$period]) }}</h2>
        @forelse($dashboard['top'] as $i => $product)
            <div class="card {{ $loop->last ? '' : 'mb-2' }} sh-10">
                <div class="row g-0 h-100">
                    <div class="col-auto h-100">
                        @if($product['image'])
                            <img src="{{ asset('frontend/uploads/products/'.$product['image']) }}" alt="" class="card-img card-img-horizontal sw-10 h-100 dashboard-top-img">
                        @else
                            <div class="sw-10 h-100 d-flex justify-content-center align-items-center bg-separator-light rounded-md-start">
                                <span class="cta-3 text-primary">{{ $i + 1 }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="col">
                        <div class="card-body d-flex align-items-center h-100 py-0">
                            <div class="flex-grow-1 min-w-0 pe-3">
                                @if($product['id'] && auth('admin')->user()?->can('products.menu'))
                                    <a href="{{ route('admin.product.edit', $product['id']) }}" class="body-link d-block text-truncate">{{ $product['name'] }}</a>
                                @else
                                    <div class="text-truncate">{{ $product['name'] }}</div>
                                @endif
                                <div class="text-small text-muted text-truncate">{{ $product['brand'] }}</div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="cta-3 text-primary">{{ $product['quantity'] }} <span class="text-small text-muted">ədəd</span></div>
                                <div class="text-small text-muted">{{ $money($product['sum']) }} ₼</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body text-muted text-center py-5">Bu dövrdə satış yoxdur</div></div>
        @endforelse
    </div>
</div>

{{-- Axtarış, onlayn ödənişlər, sifariş mənbələri (seçilmiş dövr) --}}
@php
    $search = $dashboard['searches'];
    $online = $dashboard['online'];
    $canAlias = auth('admin')->user()?->can('product.search');
    $periodLower = mb_strtolower(AdminDashboard::PERIODS[$period]);
@endphp
<div class="row">
    <div class="col-12 col-xl-6 mb-5">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="small-title">Saytda axtarış — {{ $periodLower }}</h2>
            @if($canAlias)
                <a href="{{ route('admin.product.search-aliases.index') }}" class="btn btn-icon btn-icon-end btn-xs btn-background-alternate p-0 text-small mb-2">
                    <span class="align-bottom">Axtarış idarəetməsi</span>
                    <i data-acorn-icon="chevron-right" class="align-middle" data-acorn-size="12"></i>
                </a>
            @endif
        </div>
        <div class="card h-100-card">
            <div class="card-body">
                <div class="row g-0 text-center mb-3">
                    <div class="col">
                        <div class="cta-2 text-primary">{{ $money($search['total']) }}</div>
                        <div class="text-small text-muted">AXTARIŞ</div>
                    </div>
                    <div class="col">
                        <div class="cta-2 text-success">{{ $money($search['found']) }}</div>
                        <div class="text-small text-muted">NƏTİCƏ TAPILDI</div>
                    </div>
                    <div class="col">
                        <div class="cta-2 text-danger">{{ $money($search['empty']) }}</div>
                        <div class="text-small text-muted">NƏTİCƏSİZ</div>
                    </div>
                </div>
                @if($search['total'])
                    <div class="progress dashboard-progress mb-4" title="Uğurlu axtarış faizi">
                        <div class="progress-bar bg-success" style="width: {{ round($search['found'] / $search['total'] * 100, 1) }}%"></div>
                        <div class="progress-bar bg-danger" style="width: {{ round($search['empty'] / $search['total'] * 100, 1) }}%"></div>
                    </div>
                @endif
                <div class="text-small text-muted text-uppercase mb-2">Ən çox nəticəsiz qalan sorğular</div>
                @forelse($search['top_empty'] as $row)
                    <div class="d-flex align-items-center justify-content-between py-2 {{ $loop->last ? '' : 'border-bottom border-separator-light' }}">
                        <div class="text-truncate pe-3">
                            {{ $row['query'] }}
                            <span class="text-small text-muted ms-1">× {{ $row['count'] }}</span>
                        </div>
                        @if($canAlias)
                            <a href="{{ route('admin.product.search-aliases.index', ['alias' => $row['query']]) }}" class="btn btn-sm btn-outline-primary flex-shrink-0">Alias əlavə et</a>
                        @endif
                    </div>
                @empty
                    <div class="text-muted py-2">Nəticəsiz axtarış yoxdur</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6 mb-5">
        <h2 class="small-title">Onlayn ödənişlər — {{ $periodLower }}</h2>
        @php
            $onlineCards = [
                ['title' => 'Uğurlu', 'icon' => 'check-circle', 'count' => $online['paid'], 'sum' => $online['paid_sum'], 'color' => 'success'],
                ['title' => 'Uğursuz / ləğv', 'icon' => 'close-circle', 'count' => $online['failed'], 'sum' => $online['failed_sum'], 'color' => 'danger'],
                ['title' => 'Gözləyir', 'icon' => 'hourglass', 'count' => $online['pending'], 'sum' => $online['pending_sum'], 'color' => 'warning'],
            ];
        @endphp
        <div class="row g-2 mb-5">
            @foreach($onlineCards as $card)
                <div class="col-12 col-sm-6">
                    <div class="card h-100">
                        <div class="h-100 row g-0 card-body align-items-center py-3">
                            <div class="col-auto">
                                <div class="sw-6 sh-6 rounded-md d-flex justify-content-center align-items-center border border-{{ $card['color'] }}">
                                    <i data-acorn-icon="{{ $card['icon'] }}" class="text-{{ $card['color'] }}"></i>
                                </div>
                            </div>
                            <div class="col ps-3">
                                <div class="heading mb-1 lh-1-25">{{ $card['title'] }}</div>
                                <div class="d-flex align-items-baseline flex-wrap">
                                    <div class="cta-2 text-{{ $card['color'] }} me-2">{{ $card['count'] }}</div>
                                    <div class="text-medium text-alternate">{{ $money($card['sum'], 2) }} ₼</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="col-12 col-sm-6">
                <div class="card h-100">
                    <div class="h-100 row g-0 card-body align-items-center py-3">
                        <div class="col-auto">
                            <div class="bg-gradient-light sw-6 sh-6 rounded-md d-flex justify-content-center align-items-center">
                                <i data-acorn-icon="trend-up" class="text-white"></i>
                            </div>
                        </div>
                        <div class="col ps-3">
                            <div class="heading mb-1 lh-1-25">Uğur faizi</div>
                            <div class="cta-2 text-primary">{{ $online['rate'] !== null ? $online['rate'].'%' : '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="small-title">Sifariş mənbələri — {{ $periodLower }}</h2>
        <div class="card">
            <div class="card-body">
                @forelse($dashboard['sources'] as $i => $src)
                    @php $color = $colors[$i % count($colors)]; @endphp
                    <div class="row g-0 align-items-center {{ $loop->last ? '' : 'mb-4' }}">
                        <div class="col-auto">
                            <div class="sw-5 sh-5 rounded-xl border border-{{ $color }} d-flex justify-content-center align-items-center">
                                <i data-acorn-icon="{{ ['customer' => 'cart', 'one_click' => 'mouse', 'operator' => 'headset'][$src['code']] ?? 'more-horizontal' }}" class="text-{{ $color }}" data-acorn-size="18"></i>
                            </div>
                        </div>
                        <div class="col ps-3">
                            <div class="d-flex justify-content-between align-items-end">
                                <div class="lh-1-25">
                                    {{ $src['name'] }}
                                    <div class="text-small text-muted">{{ $src['orders'] }} sifariş · {{ $money($src['sum']) }} ₼</div>
                                </div>
                                <div class="cta-3 text-{{ $color }}">{{ $src['share'] }}%</div>
                            </div>
                            <div class="progress dashboard-progress mt-2">
                                <div class="progress-bar bg-{{ $color }}" style="width: {{ $src['share'] }}%"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted text-center py-4">Bu dövrdə sifariş yoxdur</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Yeni müştərilər (mənbəyə görə) və maliyyə vəziyyəti --}}
@php
    $newCustomers = $stats['customers'];
    $fin = $dashboard['finance'];
    $customerIcons = ['website' => 'screen', 'crm' => 'headset', 'assistant' => 'message', 'easy_order' => 'mouse'];
@endphp
<div class="row">
    <div class="col-12 col-xl-6 mb-5">
        <h2 class="small-title">Yeni müştərilər — {{ $periodLower }}</h2>
        <div class="card h-100-card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-gradient-light sw-6 sh-6 rounded-md d-flex justify-content-center align-items-center flex-shrink-0">
                        <i data-acorn-icon="user" class="text-white"></i>
                    </div>
                    <div class="ps-3">
                        <div class="d-flex align-items-center">
                            <div class="cta-1 text-primary me-3">{{ $newCustomers['value'] }}</div>
                            @if($newCustomers['change'] !== null)
                                <div class="{{ $newCustomers['change'] >= 0 ? 'text-success' : 'text-danger' }} d-flex align-items-center">
                                    <i data-acorn-icon="{{ $newCustomers['change'] >= 0 ? 'arrow-top' : 'arrow-bottom' }}" class="me-1" data-acorn-size="13"></i>
                                    <span class="text-medium">{{ ($newCustomers['change'] > 0 ? '+' : '').$newCustomers['change'] }}%</span>
                                </div>
                            @endif
                        </div>
                        <div class="text-small text-muted">{{ AdminDashboard::PREVIOUS[$period] }}: {{ $newCustomers['previous'] }}</div>
                    </div>
                </div>
                @forelse($dashboard['customerSources'] as $i => $src)
                    @php $color = $colors[$i % count($colors)]; $share = $newCustomers['value'] ? round($src['count'] / $newCustomers['value'] * 100, 1) : 0; @endphp
                    <div class="row g-0 align-items-center {{ $loop->last ? '' : 'mb-4' }}">
                        <div class="col-auto">
                            <div class="sw-5 sh-5 rounded-xl border border-{{ $color }} d-flex justify-content-center align-items-center">
                                <i data-acorn-icon="{{ $customerIcons[$src['code']] ?? 'question-hexagon' }}" class="text-{{ $color }}" data-acorn-size="18"></i>
                            </div>
                        </div>
                        <div class="col ps-3">
                            <div class="d-flex justify-content-between align-items-end">
                                <div class="lh-1-25">{{ $src['name'] }}</div>
                                <div class="cta-3 text-{{ $color }}">{{ $src['count'] }}</div>
                            </div>
                            <div class="progress dashboard-progress mt-2">
                                <div class="progress-bar bg-{{ $color }}" style="width: {{ $share }}%"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted">Bu dövrdə yeni müştəri yoxdur</div>
                @endforelse
            </div>
        </div>
    </div>

    @if($fin)
        <div class="col-12 col-xl-6 mb-5">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="small-title">Maliyyə — hazırki vəziyyət</h2>
                @if(auth('admin')->user()?->can('finance'))
                    <a href="{{ route('admin.finance.index') }}" class="btn btn-icon btn-icon-end btn-xs btn-background-alternate p-0 text-small mb-2">
                        <span class="align-bottom">Kassa</span>
                        <i data-acorn-icon="chevron-right" class="align-middle" data-acorn-size="12"></i>
                    </a>
                @endif
            </div>
            @php
                $finCards = [
                    ['title' => 'Kassa (nağd)', 'icon' => 'wallet', 'value' => $fin['cash'], 'color' => 'primary', 'hint' => null],
                    ['title' => 'Bank', 'icon' => 'credit-card', 'value' => $fin['bank'], 'color' => 'primary', 'hint' => null],
                    ['title' => 'Kuryerlərdən alınacaq', 'icon' => 'delivery-truck', 'value' => $fin['couriers_owe'], 'color' => 'warning',
                        'hint' => $fin['owe_couriers'] > 0 ? 'Kuryerlərə borc: '.$money($fin['owe_couriers'], 2).' ₼' : null],
                    ['title' => 'Anbarlara borc', 'icon' => 'boxes', 'value' => $fin['warehouses_debt'], 'color' => 'danger',
                        'hint' => $fin['warehouses'] ? $fin['warehouses'].' anbar' : null],
                ];
            @endphp
            <div class="row g-2 mb-2">
                @foreach($finCards as $card)
                    <div class="col-12 col-sm-6">
                        <div class="card h-100">
                            <div class="h-100 row g-0 card-body align-items-center py-3">
                                <div class="col-auto">
                                    <div class="sw-6 sh-6 rounded-md d-flex justify-content-center align-items-center border border-{{ $card['color'] }}">
                                        <i data-acorn-icon="{{ $card['icon'] }}" class="text-{{ $card['color'] }}"></i>
                                    </div>
                                </div>
                                <div class="col ps-3">
                                    <div class="heading mb-1 lh-1-25">{{ $card['title'] }}</div>
                                    <div class="cta-2 text-{{ $card['color'] }}">{{ $money($card['value'], 2) }} ₼</div>
                                    @if($card['hint'])
                                        <div class="text-small text-muted">{{ $card['hint'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center {{ $fin['couriers'] ? 'mb-3 pb-3 border-bottom border-separator-light' : '' }}">
                        <div>
                            <div class="heading mb-0">Bonus öhdəliyi</div>
                            <div class="text-small text-muted">Müştərilərin hesabındakı istifadə olunmamış bonus</div>
                        </div>
                        <div class="cta-3 text-primary text-nowrap">{{ $money($fin['bonus'], 2) }} ₼</div>
                    </div>
                    @foreach($fin['couriers'] as $courier)
                        <div class="d-flex justify-content-between align-items-center {{ $loop->last ? '' : 'mb-2' }}">
                            <div>
                                @if($courier['id'])
                                    <a href="{{ route('admin.finance.account', $courier['id']) }}" class="body-link">{{ $courier['name'] }}</a>
                                @else
                                    {{ $courier['name'] }}
                                @endif
                                <span class="text-small text-muted ms-1">{{ $courier['balance'] > 0 ? 'təhvil verməlidir' : 'şirkət borcludur' }}</span>
                            </div>
                            <div class="{{ $courier['balance'] > 0 ? 'text-warning' : 'text-success' }}">{{ $money(abs($courier['balance']), 2) }} ₼</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Səbətlərdə qalan məhsullar (indiki an) --}}
@php
    $carts = $dashboard['carts'];
    $canCrm = auth('admin')->user()?->can('crm');
    $cartUrl = fn (array $params = []) => $canCrm ? route('admin.carts.index', $params) : null;
    $cartCards = [
        ['title' => 'Səbəti dolu müştəri', 'icon' => 'user', 'value' => $money($carts['customers']), 'color' => 'primary', 'hint' => null, 'url' => $cartUrl()],
        ['title' => 'Səbətdəki məhsul', 'icon' => 'cart', 'value' => $money($carts['quantity']).' ədəd', 'color' => 'primary', 'hint' => null, 'url' => $cartUrl(['view' => 'products'])],
        ['title' => 'Təxmini dəyər', 'icon' => 'money', 'value' => $money($carts['value']).' ₼', 'color' => 'success', 'hint' => 'Hazırkı qiymətlə', 'url' => $cartUrl(['sort' => 'value'])],
        ['title' => '1 gündən çox gözləyən', 'icon' => 'clock', 'value' => $money($carts['stale']), 'color' => 'warning', 'hint' => 'Səbətinə 24 saatdır toxunmayan müştəri', 'url' => $cartUrl(['stale' => 1])],
    ];
@endphp
<div class="row">
    <div class="col-12 col-xl-6 mb-5">
        <h2 class="small-title">Səbətlərdə — hazırki vəziyyət</h2>
        <div class="row g-2">
            @foreach($cartCards as $card)
                <div class="col-12 col-sm-6">
                    <div class="card h-100 {{ $card['url'] ? 'hover-border-primary' : '' }}">
                        <div class="h-100 row g-0 card-body align-items-center py-3">
                            <div class="col-auto">
                                <div class="sw-6 sh-6 rounded-md d-flex justify-content-center align-items-center border border-{{ $card['color'] }}">
                                    <i data-acorn-icon="{{ $card['icon'] }}" class="text-{{ $card['color'] }}"></i>
                                </div>
                            </div>
                            <div class="col ps-3">
                                <div class="heading mb-1 lh-1-25">{{ $card['title'] }}</div>
                                <div class="cta-2 text-{{ $card['color'] }}">{{ $card['value'] }}</div>
                                @if($card['hint'])
                                    <div class="text-small text-muted">{{ $card['hint'] }}</div>
                                @endif
                                @if($card['url'])
                                    <a href="{{ $card['url'] }}" class="stretched-link" aria-label="{{ $card['title'] }}"></a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-12 col-xl-6 mb-5">
        <h2 class="small-title">Səbətlərdə ən çox olanlar</h2>
        <div class="card h-100-card">
            <div class="card-body py-3">
                @forelse($carts['top'] as $item)
                    <div class="d-flex align-items-center justify-content-between py-2 {{ $loop->last ? '' : 'border-bottom border-separator-light' }}">
                        <div class="min-w-0 pe-3">
                            @if($item['id'] && auth('admin')->user()?->can('products.menu'))
                                <a href="{{ route('admin.product.edit', $item['id']) }}" class="body-link d-block text-truncate">{{ $item['name'] }}</a>
                            @else
                                <div class="text-truncate">{{ $item['name'] }}</div>
                            @endif
                            <div class="text-small text-muted text-truncate">{{ $item['brand'] }}{{ $item['size'] ? ' · '.$item['size'] : '' }} · {{ $money($item['price'], 2) }} ₼</div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="cta-3 text-primary">{{ $item['customers'] }} <span class="text-small text-muted">müştəri</span></div>
                            <div class="text-small text-muted">{{ $item['quantity'] }} ədəd</div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted text-center py-4">Səbətlərdə məhsul yoxdur</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Aktiv sifarişlər mərhələlər üzrə --}}
@php $activeTotal = array_sum(array_column($dashboard['active'], 'count')); @endphp
<div class="mb-5">
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="small-title">Aktiv sifarişlər</h2>
        @if(auth('admin')->user()?->can('crm'))
            <a href="{{ route('admin.orders.index', ['status' => 'active']) }}" class="btn btn-icon btn-icon-end btn-xs btn-background-alternate p-0 text-small mb-2">
                <span class="align-bottom">Cəmi {{ $activeTotal }} · Hamısına bax</span>
                <i data-acorn-icon="chevron-right" class="align-middle" data-acorn-size="12"></i>
            </a>
        @else
            <span class="text-small text-muted mb-2">Cəmi: {{ $activeTotal }}</span>
        @endif
    </div>
    <div class="row g-2 row-cols-2 row-cols-md-4 dashboard-steps">
        @foreach($dashboard['active'] as $step)
            <div class="col">
                <div class="card h-100 {{ $step['count'] ? 'hover-border-primary' : 'opacity-50' }}">
                    <div class="card-body text-center d-flex flex-column align-items-center py-4">
                        <div class="{{ $step['count'] ? 'bg-gradient-light' : 'border border-primary' }} sw-6 sh-6 rounded-xl d-flex justify-content-center align-items-center mb-3">
                            <i data-acorn-icon="{{ $step['icon'] }}" class="{{ $step['count'] ? 'text-white' : 'text-primary' }}"></i>
                        </div>
                        <div class="heading lh-1-25 mb-2 sh-5 d-flex align-items-center">{{ $step['name'] }}</div>
                        <div class="display-6 text-primary">{{ $step['count'] }}</div>
                        @if($step['count'] && auth('admin')->user()?->can('crm'))
                            <a href="{{ route('admin.orders.index', ['status' => $step['code']]) }}" class="stretched-link" aria-label="{{ $step['name'] }}"></a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
