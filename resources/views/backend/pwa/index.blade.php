@use('App\Http\Controllers\Backend\PwaStatsController')
@php
    $html_tag_data = [];
    $title = 'Ana ekran (tətbiq)';
    $breadcrumbs = ['/admin' => 'ParfumShop', '#' => $title];
    $number = fn ($v) => number_format((float) $v, 0, '.', ' ');
    $kpis = [
        ['title' => 'Quraşdırma', 'value' => $installs['total'], 'icon' => 'download', 'note' => 'cəmi: '.$number($installs['allTime'])],
        ['title' => 'Android', 'value' => $installs['android'], 'icon' => 'mobile', 'note' => 'quraşdırma'],
        ['title' => 'iPhone / iPad', 'value' => $installs['ios'], 'icon' => 'mobile', 'note' => 'ana ekrandan ilk açılış'],
        ['title' => 'Açılış', 'value' => $opens, 'icon' => 'screen', 'note' => $number($activeDevices).' cihaz · '.$number($customers).' müştəri'],
    ];
    $platformIcon = ['android' => 'Android', 'ios' => 'iOS', 'other' => 'Digər'];
    $blocks = [
        ['title' => 'Cihazlar', 'rows' => $models],
        ['title' => 'Sistem versiyası', 'rows' => $systems],
        ['title' => 'Brauzer', 'rows' => $browsers],
        ['title' => 'Telefonun rejimi', 'rows' => $themes],
    ];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/dashboard.css') }}">
@endsection

@section('content')
<div class="container">
    <div class="page-title-container">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-md-auto">
                <div class="btn-group stat-period" role="group" aria-label="Dövr">
                    @foreach(PwaStatsController::PERIODS as $key => $label)
                        <a href="{{ route('admin.pwa', ['period' => $key]) }}" class="btn {{ (string) $key === $period ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-5">
        @foreach($kpis as $kpi)
            <div class="col-6 col-xl-3">
                <div class="card h-100 stat-kpi">
                    <div class="card-body py-4 px-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="heading mb-0">{{ $kpi['title'] }}</div>
                            <div class="stat-kpi-icon"><i data-acorn-icon="{{ $kpi['icon'] }}"></i></div>
                        </div>
                        <div class="stat-kpi-value">{{ $number($kpi['value']) }}</div>
                        <div class="text-muted dash-label mt-2">{{ $kpi['note'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        @foreach($blocks as $block)
            @php $max = max(1, $block['rows']->max('count') ?? 1); @endphp
            <div class="col-12 col-xl-6 mb-5">
                <h2 class="small-title">{{ $block['title'] }}</h2>
                <div class="card h-100-card">
                    <div class="card-body">
                        @forelse($block['rows'] as $row)
                            <div class="{{ $loop->last ? '' : 'mb-3' }}">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <span class="badge bg-outline-{{ $row['platform'] === 'ios' ? 'secondary' : 'primary' }} flex-shrink-0">{{ $platformIcon[$row['platform']] ?? $row['platform'] }}</span>
                                        <span class="text-truncate">{{ $row['name'] }}</span>
                                    </div>
                                    <span class="fw-bold">{{ $number($row['count']) }}</span>
                                </div>
                                <div class="progress dash-progress mt-1">
                                    <div class="progress-bar bg-{{ $row['platform'] === 'ios' ? 'secondary' : 'primary' }}" style="width: {{ round($row['count'] / $max * 100) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted text-center py-4">Bu dövrdə məlumat yoxdur</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="small-title">Günlər üzrə{{ $period === 'all' ? ' — son 90 gün' : '' }}</h2>
    <div class="card mb-3">
        <div class="card-body">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="text-muted text-small text-uppercase">Tarix</th>
                        <th class="text-muted text-small text-uppercase text-end">Quraşdırma</th>
                        <th class="text-muted text-small text-uppercase text-end">Açılış</th>
                        <th class="text-muted text-small text-uppercase text-end">Açan cihaz</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($daily as $day)
                        <tr class="{{ $day['installs'] || $day['opens'] ? '' : 'text-muted' }}">
                            <td>{{ $day['date']->format('d.m.Y') }}</td>
                            <td class="text-end {{ $day['installs'] ? 'fw-bold text-primary' : '' }}">{{ $day['installs'] }}</td>
                            <td class="text-end">{{ $day['opens'] }}</td>
                            <td class="text-end">{{ $day['devices'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-muted text-small mb-5">
        Sayma bu səhifə proddə işə düşəndən başlayır; ondan əvvəl əlavə edənlər ana ekrandan ilk açanda quraşdırma kimi sayılır.
        iPhone modelini vermir — model qrupu ekran ölçüsünə görədir. Ana ekrandan silmək bilinmir.
    </p>
</div>
@endsection
