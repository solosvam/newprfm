{{-- Baş kuryer → Kuryerlər (telefon üçün): hər kuryerin balansı, aktiv sifarişləri, bu gün təhvil verdikləri --}}
@php
    $html_tag_data = [];
    $title = 'Kuryerlər';
    $breadcrumbs = [route('admin.dashboard') => 'Tapşırıqlarım', '' => $title];
    $money = fn ($cents) => number_format(abs($cents) / 100, 2).' AZN';
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset_v('backend/css/courier.css') }}">
@endsection

@section('content')
<div class="container courier-page">
    <div class="page-title-container">
        <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
        @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
    </div>
    @include('backend.procurement.feedback')

    <div class="courier-summary mb-4">
        <div class="card"><div class="card-body">
            <div class="courier-summary__label">Kuryerlərdə olan pul</div>
            <div class="courier-summary__value text-warning">{{ $money($couriers->sum(fn ($c) => max(0, $c['balance']))) }}</div>
        </div></div>
        <div class="card"><div class="card-body">
            <div class="courier-summary__label">Aktiv sifariş</div>
            <div class="courier-summary__value">{{ $couriers->sum('active') }}</div>
        </div></div>
    </div>

    @forelse($couriers as $row)
        @php $user = $row['user']; @endphp
        <a href="{{ route('admin.courier.team.show', $user) }}" class="card courier-order mb-3">
            <div class="card-body">
                <div class="courier-order__top">
                    <strong>{{ trim($user->full_name) }}@if($user->id === $me) <span class="text-muted fw-normal">(siz)</span>@endif</strong>
                    @if(!$user->active)<span class="badge bg-outline-muted">Deaktiv</span>@endif
                    @if($row['balance'] > 0)
                        <span class="text-warning fw-bold">{{ $money($row['balance']) }}</span>
                    @elseif($row['balance'] < 0)
                        <span class="text-success fw-bold">−{{ $money($row['balance']) }}</span>
                    @else
                        <span class="text-muted">0.00 AZN</span>
                    @endif
                </div>
                <div class="courier-order__meta">
                    <span>Aktiv sifariş: <b>{{ $row['active'] }}</b></span>
                    <span>Bu gün təhvil: <b>{{ $row['deliveredToday'] }}</b></span>
                    @if(!$user->telegram_id)<span class="text-muted">Telegram qoşulmayıb</span>@endif
                </div>
                <div class="courier-order__meta">@include('backend.courier._location', ['user' => $user])</div>
            </div>
        </a>
    @empty
        <div class="card"><div class="card-body text-center text-muted py-5">Kuryer yoxdur.</div></div>
    @endforelse
    <p class="text-muted text-small mt-3">Balans müsbətdirsə kuryer şirkətə təhvil verməlidir (sarı), mənfidirsə şirkət ona borcludur (yaşıl).</p>
</div>
@endsection
