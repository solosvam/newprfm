@use('App\Services\Dashboard\AdminDashboard')
@php
    $html_tag_data = [];
    $title = 'Statistika';
    $breadcrumbs = ['/admin' => 'ParfumShop', '#' => $title];
    $money = fn ($v, $d = 0) => number_format((float) $v, $d, '.', ' ');
    $user = auth('admin')->user();
    $periodLower = mb_strtolower(AdminDashboard::PERIODS[$period]);
    $previousLabel = AdminDashboard::PREVIOUS[$period];
    $link = fn (string $p) => route('admin.statistics', array_filter(['period' => $p, 'demo' => $demo ? 1 : null]));

    $kpis = [
        ['key' => 'revenue', 'title' => 'Dövriyyə', 'icon' => 'money', 'format' => fn ($v) => $money($v).' ₼'],
        ['key' => 'profit', 'title' => 'Mənfəət', 'icon' => 'trend-up', 'format' => fn ($v) => $money($v).' ₼'],
        ['key' => 'orders', 'title' => 'Sifarişlər', 'icon' => 'cart', 'format' => fn ($v) => $money($v)],
        ['key' => 'average', 'title' => 'Orta sifariş', 'icon' => 'basket', 'format' => fn ($v) => $money($v, 2).' ₼'],
    ];

    // Ödəniş üsulları: 4 rəng — çox olarsa, ən kiçikləri "Digər"də birləşir
    $colors = ['primary', 'tertiary', 'quaternary', 'secondary'];
    $payments = collect($payments);
    if ($payments->count() > count($colors)) {
        $rest = $payments->slice(count($colors) - 1);
        $payments = $payments->take(count($colors) - 1)->push([
            'code' => null, 'name' => 'Digər', 'orders' => $rest->sum('orders'), 'sum' => $rest->sum('sum'), 'share' => round($rest->sum('share'), 1),
        ]);
    }
    $payments = $payments->values();
    $paymentIcons = ['cash' => 'money', 'card_online' => 'credit-card', 'bonus_balance' => 'gift'];

    $salesRevenue = array_sum($sales['revenue']);
    $salesOrders = array_sum($sales['orders']);
    $topMax = max(1, collect($top)->max('quantity') ?? 1);
    $canProducts = (bool) $user?->can('products.menu');
    $canAlias = (bool) $user?->can('product.search');
    $newCustomers = $stats['customers'];
    $customerIcons = ['website' => 'screen', 'crm' => 'headset', 'assistant' => 'message', 'easy_order' => 'mouse'];
    $sourceIcons = ['customer' => 'cart', 'one_click' => 'mouse', 'operator' => 'headset'];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/dashboard.css') }}">
@endsection

@section('js_vendor')
    <script src="{{ asset('backend/js/vendor/Chart.bundle.min.js') }}"></script>
@endsection

@section('js_page')
    <script src="{{ asset_v('backend/js/cs/charts.extend.js') }}"></script>
    <script src="{{ asset_v('backend/js/statistics.js') }}"></script>
@endsection

@section('content')
<div class="container">
    <div class="page-title-container">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-md-auto d-flex align-items-center gap-2">
                @if($demo)<span class="badge bg-outline-warning">Demo məlumat</span>@endif
                <div class="btn-group stat-period" role="group" aria-label="Dövr">
                    @foreach(AdminDashboard::PERIODS as $key => $label)
                        <a href="{{ $link($key) }}" class="btn {{ $key === $period ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Əsas göstəricilər --}}
    <div class="row g-2 mb-5">
        @foreach($kpis as $kpi)
            @php $m = $stats[$kpi['key']]; $negative = $kpi['key'] === 'profit' && $m['value'] < 0; @endphp
            <div class="col-6 col-xl-3">
                <div class="card h-100 stat-kpi">
                    <div class="card-body py-4 px-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="heading mb-0">{{ $kpi['title'] }}</div>
                                @if($kpi['key'] === 'profit' && $m['margin'] !== null)
                                    <div class="text-muted dash-label">marja {{ $m['margin'] }}%</div>
                                @else
                                    <div class="text-muted dash-label">{{ $periodLower }}</div>
                                @endif
                            </div>
                            <div class="stat-kpi-icon"><i data-acorn-icon="{{ $kpi['icon'] }}"></i></div>
                        </div>
                        <div class="stat-kpi-value {{ $negative ? 'text-danger' : '' }}">{{ $kpi['format']($m['value']) }}</div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                            @if($m['change'] !== null)
                                <span class="dash-change {{ $m['change'] >= 0 ? 'dash-change--up' : 'dash-change--down' }}">
                                    {{ $m['change'] >= 0 ? '▲' : '▼' }} {{ abs($m['change']) }}%
                                </span>
                            @endif
                            <span class="text-muted dash-label">
                                @if($kpi['key'] === 'profit')
                                    {{ $m['orders'] }} təhvil verilmiş sifariş
                                @else
                                    {{ $previousLabel }}: {{ $kpi['format']($m['previous']) }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        {{-- Satış dinamikası --}}
        <div class="col-12 col-xl-8 mb-5">
            <h2 class="small-title">Satış dinamikası — son {{ $days }} gün</h2>
            <div class="card h-100-card">
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-auto pe-4">
                            <div class="d-flex align-items-center gap-2 dash-label text-muted"><span class="stat-legend-dot bg-primary"></span>Dövriyyə</div>
                            <div class="cta-3 text-primary">{{ $money($salesRevenue) }} ₼</div>
                        </div>
                        <div class="col-auto pe-4">
                            <div class="d-flex align-items-center gap-2 dash-label text-muted"><span class="stat-legend-dot bg-tertiary"></span>Sifariş</div>
                            <div class="cta-3 text-tertiary">{{ $money($salesOrders) }}</div>
                        </div>
                        <div class="col-auto">
                            <div class="dash-label text-muted">Gündə orta</div>
                            <div class="cta-3 text-alternate">{{ $money($salesRevenue / max(1, $days)) }} ₼ · {{ $money($salesOrders / max(1, $days), 1) }}</div>
                        </div>
                    </div>
                    <div class="position-relative" style="height: 300px;">
                        <canvas id="statSalesChart"
                                data-labels='@json($sales['labels'])'
                                data-revenue='@json($sales['revenue'])'
                                data-orders='@json($sales['orders'])'></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ödəniş üsulları --}}
        <div class="col-12 col-xl-4 mb-5">
            <h2 class="small-title">Ödəniş üsulları</h2>
            <div class="card h-100-card">
                <div class="card-body">
                    @if($payments->isEmpty())
                        <div class="text-muted text-center py-5">Bu dövrdə sifariş yoxdur</div>
                    @else
                        <div class="position-relative mx-auto mb-4" style="height: 190px; max-width: 220px;">
                            <canvas id="statPaymentsChart"
                                    data-labels='@json($payments->pluck('name'))'
                                    data-values='@json($payments->pluck('sum'))'
                                    data-colors='@json(array_slice($colors, 0, $payments->count()))'></canvas>
                            <div class="position-absolute top-50 start-50 translate-middle text-center pe-none">
                                <div class="text-muted dash-label">Cəmi</div>
                                <div class="cta-4 text-primary text-nowrap">{{ $money($payments->sum('sum')) }} ₼</div>
                            </div>
                        </div>
                        @foreach($payments as $i => $method)
                            <div class="{{ $loop->last ? '' : 'mb-3' }}">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <i data-acorn-icon="{{ $paymentIcons[$method['code'] ?? ''] ?? 'wallet' }}" class="text-{{ $colors[$i] }} flex-shrink-0" data-acorn-size="16"></i>
                                        <span class="text-truncate">{{ $method['name'] }}</span>
                                    </div>
                                    <span class="fw-bold text-{{ $colors[$i] }}">{{ $method['share'] }}%</span>
                                </div>
                                <div class="progress dash-progress my-1">
                                    <div class="progress-bar bg-{{ $colors[$i] }}" style="width: {{ $method['share'] }}%"></div>
                                </div>
                                <div class="text-muted dash-label">{{ $method['orders'] }} sifariş · {{ $money($method['sum']) }} ₼</div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Ən çox satılanlar: sabit top 10 --}}
        <div class="col-12 col-xl-7 mb-5">
            <h2 class="small-title">Ən çox satılanlar — top {{ \App\Http\Controllers\Backend\StatisticsController::TOP_LIMIT }}</h2>
            <div class="card h-100-card">
                <div class="card-body py-2">
                    @forelse($top as $i => $product)
                        <div class="stat-row {{ $loop->last ? '' : 'border-bottom border-separator-light' }}">
                            <span class="stat-rank {{ $i < 3 ? 'stat-rank--'.($i + 1) : '' }}">{{ $i + 1 }}</span>
                            <span class="stat-thumb" @if($product['image']) style="background-image: url('{{ asset('frontend/uploads/products/'.$product['image']) }}')" @endif></span>
                            <div class="min-w-0 flex-grow-1">
                                @if($product['id'] && $canProducts)
                                    <a href="{{ route('admin.product.edit', $product['id']) }}" class="body-link d-block text-truncate fw-bold">{{ $product['name'] }}</a>
                                @else
                                    <div class="text-truncate fw-bold">{{ $product['name'] }}</div>
                                @endif
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted dash-label text-truncate">{{ $product['brand'] }}</span>
                                    <div class="progress dash-progress flex-grow-1 d-none d-sm-flex" style="max-width: 160px;">
                                        <div class="progress-bar bg-primary" style="width: {{ round($product['quantity'] / $topMax * 100) }}%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="fw-bold text-primary text-nowrap">{{ $product['quantity'] }} ədəd</div>
                                <div class="text-muted dash-label text-nowrap">{{ $money($product['sum']) }} ₼</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted text-center py-5">Bu dövrdə satış yoxdur</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5 mb-5">
            {{-- Sifariş mənbələri --}}
            <h2 class="small-title">Sifariş mənbələri</h2>
            <div class="card mb-5">
                <div class="card-body">
                    @forelse($sources as $i => $src)
                        @php $color = $colors[$i % count($colors)]; @endphp
                        <div class="d-flex align-items-center gap-3 {{ $loop->last ? '' : 'mb-3' }}">
                            <div class="stat-kpi-icon flex-shrink-0" style="width:38px;height:38px">
                                <i data-acorn-icon="{{ $sourceIcons[$src['code']] ?? 'more-horizontal' }}" data-acorn-size="16"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between">
                                    <span>{{ $src['name'] }}</span>
                                    <span class="fw-bold text-{{ $color }}">{{ $src['share'] }}%</span>
                                </div>
                                <div class="progress dash-progress my-1">
                                    <div class="progress-bar bg-{{ $color }}" style="width: {{ $src['share'] }}%"></div>
                                </div>
                                <div class="text-muted dash-label">{{ $src['orders'] }} sifariş · {{ $money($src['sum']) }} ₼</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted text-center py-4">Bu dövrdə sifariş yoxdur</div>
                    @endforelse
                </div>
            </div>

            {{-- Yeni müştərilər --}}
            <h2 class="small-title">Yeni müştərilər</h2>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-kpi-value">{{ $newCustomers['value'] }}</div>
                        @if($newCustomers['change'] !== null)
                            <span class="dash-change {{ $newCustomers['change'] >= 0 ? 'dash-change--up' : 'dash-change--down' }}">
                                {{ $newCustomers['change'] >= 0 ? '▲' : '▼' }} {{ abs($newCustomers['change']) }}%
                            </span>
                        @endif
                        <span class="text-muted dash-label">{{ $previousLabel }}: {{ $newCustomers['previous'] }}</span>
                    </div>
                    @if($newCustomers['value'] && $customerSources)
                        {{-- mənbələr üzrə bölgü: tək üfüqi zolaq + legend --}}
                        <div class="progress mb-3" style="height: 10px; border-radius: 6px;">
                            @foreach($customerSources as $i => $src)
                                <div class="progress-bar bg-{{ $colors[$i % count($colors)] }}" style="width: {{ round($src['count'] / $newCustomers['value'] * 100, 1) }}%" title="{{ $src['name'] }}: {{ $src['count'] }}"></div>
                            @endforeach
                        </div>
                        <div class="row g-2">
                            @foreach($customerSources as $i => $src)
                                <div class="col-6 d-flex align-items-center gap-2">
                                    <span class="stat-legend-dot bg-{{ $colors[$i % count($colors)] }}"></span>
                                    <span class="text-truncate">{{ $src['name'] }}</span>
                                    <span class="fw-bold ms-auto">{{ $src['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted">Bu dövrdə yeni müştəri yoxdur</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Onlayn ödənişlər --}}
        <div class="col-12 col-xl-6 mb-5">
            <h2 class="small-title">Onlayn ödənişlər</h2>
            @php
                $onlineTotal = $online['paid'] + $online['failed'] + $online['pending'];
                $onlineRows = [
                    ['title' => 'Uğurlu', 'icon' => 'check-circle', 'count' => $online['paid'], 'sum' => $online['paid_sum'], 'color' => 'success'],
                    ['title' => 'Uğursuz / ləğv', 'icon' => 'close-circle', 'count' => $online['failed'], 'sum' => $online['failed_sum'], 'color' => 'danger'],
                    ['title' => 'Gözləyir', 'icon' => 'hourglass', 'count' => $online['pending'], 'sum' => $online['pending_sum'], 'color' => 'warning'],
                ];
            @endphp
            <div class="card h-100-card">
                <div class="card-body">
                    <div class="d-flex align-items-end justify-content-between mb-2">
                        <div>
                            <div class="text-muted dash-label">Uğur faizi</div>
                            <div class="stat-kpi-value">{{ $online['rate'] !== null ? $online['rate'].'%' : '—' }}</div>
                        </div>
                        <div class="text-muted dash-label">{{ $onlineTotal }} cəhd</div>
                    </div>
                    @if($onlineTotal)
                        <div class="progress mb-4" style="height: 10px; border-radius: 6px;">
                            @foreach($onlineRows as $row)
                                <div class="progress-bar bg-{{ $row['color'] }}" style="width: {{ round($row['count'] / $onlineTotal * 100, 1) }}%"></div>
                            @endforeach
                        </div>
                    @endif
                    @foreach($onlineRows as $row)
                        <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom border-separator-light' }}">
                            <i data-acorn-icon="{{ $row['icon'] }}" class="text-{{ $row['color'] }}" data-acorn-size="18"></i>
                            <span class="flex-grow-1">{{ $row['title'] }}</span>
                            <span class="fw-bold text-{{ $row['color'] }}">{{ $row['count'] }}</span>
                            <span class="text-muted text-nowrap text-end" style="min-width: 110px">{{ $money($row['sum'], 2) }} ₼</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Saytda axtarış --}}
        <div class="col-12 col-xl-6 mb-5">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="small-title">Saytda axtarış</h2>
                @if($canAlias)
                    <a href="{{ route('admin.product.search-aliases.index') }}" class="btn btn-icon btn-icon-end btn-xs btn-background-alternate p-0 mb-2 dash-link">
                        <span class="align-bottom">Axtarış idarəetməsi</span>
                        <i data-acorn-icon="chevron-right" class="align-middle" data-acorn-size="12"></i>
                    </a>
                @endif
            </div>
            <div class="card h-100-card">
                <div class="card-body">
                    <div class="row g-0 text-center dash-cart-stats mb-3">
                        <div class="col">
                            <div class="cta-3 text-primary">{{ $money($searches['total']) }}</div>
                            <div class="text-muted dash-label">axtarış</div>
                        </div>
                        <div class="col">
                            <div class="cta-3 text-success">{{ $money($searches['found']) }}</div>
                            <div class="text-muted dash-label">nəticə tapıldı</div>
                        </div>
                        <div class="col">
                            <div class="cta-3 text-danger">{{ $money($searches['empty']) }}</div>
                            <div class="text-muted dash-label">nəticəsiz</div>
                        </div>
                    </div>
                    @if($searches['total'])
                        <div class="progress mb-4" style="height: 10px; border-radius: 6px;" title="Uğurlu axtarış faizi">
                            <div class="progress-bar bg-success" style="width: {{ round($searches['found'] / $searches['total'] * 100, 1) }}%"></div>
                            <div class="progress-bar bg-danger" style="width: {{ round($searches['empty'] / $searches['total'] * 100, 1) }}%"></div>
                        </div>
                    @endif
                    <div class="text-muted dash-label text-uppercase mb-1">Ən çox nəticəsiz qalan sorğular</div>
                    @forelse($searches['top_empty'] as $row)
                        <div class="d-flex align-items-center justify-content-between py-2 {{ $loop->last ? '' : 'border-bottom border-separator-light' }}">
                            <div class="text-truncate pe-3">
                                {{ $row['query'] }}
                                <span class="badge bg-outline-danger ms-1">× {{ $row['count'] }}</span>
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
    </div>

    {{-- Maliyyə: hazırkı vəziyyət (yalnız "finance" icazəsi) --}}
    @if($finance)
        @php
            $finCards = [
                ['title' => 'Kassa (nağd)', 'icon' => 'wallet', 'value' => $finance['cash'], 'tone' => 'primary', 'hint' => null],
                ['title' => 'Bank', 'icon' => 'credit-card', 'value' => $finance['bank'], 'tone' => 'primary', 'hint' => null],
                ['title' => 'Kuryerlərdən alınacaq', 'icon' => 'delivery-truck', 'value' => $finance['couriers_owe'], 'tone' => 'warning',
                    'hint' => $finance['owe_couriers'] > 0 ? 'Kuryerlərə borc: '.$money($finance['owe_couriers'], 2).' ₼' : null],
                ['title' => 'Anbarlara borc', 'icon' => 'boxes', 'value' => $finance['warehouses_debt'], 'tone' => 'danger',
                    'hint' => $finance['warehouses'] ? $finance['warehouses'].' anbar' : null],
                ['title' => 'Bonus öhdəliyi', 'icon' => 'gift', 'value' => $finance['bonus'], 'tone' => 'primary', 'hint' => 'İstifadə olunmamış bonus'],
            ];
        @endphp
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="small-title">Maliyyə — hazırkı vəziyyət</h2>
                <a href="{{ route('admin.finance.index') }}" class="btn btn-icon btn-icon-end btn-xs btn-background-alternate p-0 mb-2 dash-link">
                    <span class="align-bottom">Kassa</span>
                    <i data-acorn-icon="chevron-right" class="align-middle" data-acorn-size="12"></i>
                </a>
            </div>
            <div class="row g-2 mb-2">
                @foreach($finCards as $card)
                    <div class="col-6 col-lg">
                        <div class="card h-100 dash-alert dash-tone-{{ $card['tone'] }}">
                            <div class="card-body py-3 px-4">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="dash-alert-icon" style="width:34px;height:34px;border-radius:10px"><i data-acorn-icon="{{ $card['icon'] }}" data-acorn-size="16"></i></span>
                                    <span class="heading mb-0 lh-1-25">{{ $card['title'] }}</span>
                                </div>
                                <div class="cta-3 text-nowrap" style="color: var(--tone)">{{ $money($card['value'], 2) }} ₼</div>
                                @if($card['hint'])<div class="text-muted dash-label">{{ $card['hint'] }}</div>@endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($finance['couriers'])
                <div class="card">
                    <div class="card-body py-2">
                        @foreach($finance['couriers'] as $courier)
                            <div class="d-flex justify-content-between align-items-center py-2 {{ $loop->last ? '' : 'border-bottom border-separator-light' }}">
                                <div class="d-flex align-items-center gap-2">
                                    <i data-acorn-icon="user" class="text-muted" data-acorn-size="16"></i>
                                    @if($courier['id'])
                                        <a href="{{ route('admin.finance.account', $courier['id']) }}" class="body-link">{{ $courier['name'] }}</a>
                                    @else
                                        {{ $courier['name'] }}
                                    @endif
                                    <span class="text-muted dash-label">{{ $courier['balance'] > 0 ? 'təhvil verməlidir' : 'şirkət borcludur' }}</span>
                                </div>
                                <div class="fw-bold {{ $courier['balance'] > 0 ? 'text-warning' : 'text-success' }}">{{ $money(abs($courier['balance']), 2) }} ₼</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
