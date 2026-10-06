@use('App\Models\Popup')
@php
    $html_tag_data = [];
    $title = 'Popup-lar';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Sayt', '#' => $title];
    $statusBadge = ['active' => ['Aktiv', 'success'], 'scheduled' => ['Planlaşdırılıb', 'primary'], 'ended' => ['Bitib', 'muted'], 'off' => ['Deaktiv', 'muted']];
    $positionShort = ['center' => 'mərkəz', 'corner' => 'künc', 'bar' => 'zolaq'];
    $num = fn ($v) => number_format((int) $v, 0, '.', ' ');
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <style>
        .popup-thumb { width: 56px; height: 42px; flex: 0 0 56px; border-radius: 8px; border: 1px solid var(--separator-light); background: var(--background) center / cover no-repeat; display: flex; align-items: center; justify-content: center; color: var(--muted); }
        .popup-stat { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .popup-stat small { font-size: 12px; }
        .popup-meta { font-size: 13px; line-height: 1.5; }
    </style>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                    <a href="{{ route('admin.popups.create') }}" class="btn btn-outline-primary btn-icon btn-icon-end w-100 w-sm-auto">
                        <span>Yeni popup</span>
                        <i data-acorn-icon="plus"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="alert alert-info d-flex gap-3 align-items-start mb-4" role="note" style="font-size: 15px; line-height: 1.55;">
            <i data-acorn-icon="info-hexagon" class="flex-shrink-0 mt-1"></i>
            <div>
                Saytda bir səhifədə <strong>ən çox bir popup</strong> çıxır — siyahıda yuxarıda olan və ziyarətçiyə uyğun gələn.
                "Bir daha göstərmə" seçən qonağa həmin brauzerdə, daxil olmuş müştəriyə isə heç bir cihazda o popup yenidən göstərilmir.
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th class="text-muted text-small text-uppercase">Popup</th>
                            <th class="text-muted text-small text-uppercase">Harada / kimə</th>
                            <th class="text-muted text-small text-uppercase">Dövr</th>
                            <th class="text-muted text-small text-uppercase text-end">Göstərilib</th>
                            <th class="text-muted text-small text-uppercase text-end">Klik</th>
                            <th class="text-muted text-small text-uppercase text-end">Bağlayıb</th>
                            <th class="text-muted text-small text-uppercase text-end">Bir daha göstərmə</th>
                            <th class="text-muted text-small text-uppercase">Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($popups as $popup)
                            @php
                                [$statusLabel, $statusColor] = $statusBadge[$popup->status()];
                                $shown = (int) $popup->shown;
                                $rate = fn ($v) => $shown ? round($v / $shown * 100, 1).'%' : '—';
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="popup-thumb" @if($popup->imageUrl()) style="background-image: url('{{ $popup->imageUrl() }}')" @endif>
                                            @unless($popup->imageUrl())<i data-acorn-icon="message" data-acorn-size="16"></i>@endunless
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.popups.edit', $popup) }}" class="body-link fw-bold">{{ $popup->name }}</a>
                                            <div class="text-muted popup-meta text-truncate" style="max-width: 260px">{{ $popup->title_az ?: '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="popup-meta">
                                    <div>{{ $popup->pagesLabel() }} · {{ Popup::AUDIENCES[$popup->audience] }}</div>
                                    <div class="text-muted">
                                        Desktop: {{ $positionShort[$popup->position_desktop] }} ·
                                        Mobil: {{ $positionShort[$popup->position_mobile] }} ·
                                        {{ mb_strtolower(Popup::FREQUENCIES[$popup->frequency]) }}
                                    </div>
                                </td>
                                <td class="popup-meta text-nowrap">
                                    @if($popup->starts_at || $popup->ends_at)
                                        {{ $popup->starts_at?->format('d.m.Y H:i') ?? 'indi' }} —<br>{{ $popup->ends_at?->format('d.m.Y H:i') ?? 'müddətsiz' }}
                                    @else
                                        <span class="text-muted">müddətsiz</span>
                                    @endif
                                </td>
                                <td class="text-end popup-stat fw-bold">{{ $num($shown) }}</td>
                                <td class="text-end popup-stat">{{ $num($popup->clicked) }} <small class="text-success">{{ $rate($popup->clicked) }}</small></td>
                                <td class="text-end popup-stat">{{ $num($popup->closed) }} <small class="text-muted">{{ $rate($popup->closed) }}</small></td>
                                <td class="text-end popup-stat">{{ $num($popup->dismissed) }} <small class="text-danger">{{ $rate($popup->dismissed) }}</small></td>
                                <td><span class="badge bg-outline-{{ $statusColor }}">{{ $statusLabel }}</span></td>
                                <td class="text-end"><a href="{{ route('admin.popups.edit', $popup) }}" class="btn btn-primary btn-sm">Redaktə et</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-5">
                                Hələ popup yoxdur.
                                <div class="mt-2"><a href="{{ route('admin.popups.create') }}" class="btn btn-outline-primary btn-sm">İlk popup-u yarat</a></div>
                            </td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
