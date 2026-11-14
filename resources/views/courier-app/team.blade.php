{{-- Baş kuryer → kuryerlər: hər kuryerin balansı, aktiv sifarişləri, bu gün təhvil verdikləri --}}
@extends('courier-app.layout', ['tab' => 'team', 'refresh' => true])
@section('title', 'Kuryerlər')
@php $money = fn ($cents) => number_format(abs($cents) / 100, 2).' AZN'; @endphp

@section('content')
    <div class="stats">
        <div class="stat">
            <div class="stat__label">Kuryerlərdə olan pul</div>
            <div class="stat__value warn">{{ $money($couriers->sum(fn ($c) => max(0, $c['balance']))) }}</div>
        </div>
        <div class="stat">
            <div class="stat__label">Aktiv sifariş</div>
            <div class="stat__value">{{ $couriers->sum('active') }}</div>
        </div>
        <div class="stat" style="grid-column: 1 / -1">
            <div class="stat__label">Bu gün əlavə çatdırılma qazancı (hamısı)</div>
            <div class="stat__value ok">{{ number_format($couriers->sum('feeToday'), 2) }} AZN</div>
        </div>
    </div>

    @forelse($couriers as $row)
        @php $user = $row['user']; @endphp
        <a href="{{ route('kuryer.team.show', $user) }}" class="card order">
            <div class="order__top">
                <strong class="order__no">{{ trim($user->full_name) }}@if($user->id === $me) <span class="muted">(siz)</span>@endif</strong>
                @if(!$user->active)<span class="badge badge--muted">Deaktiv</span>@endif
                <span class="order__sum {{ $row['balance'] > 0 ? 'warn' : ($row['balance'] < 0 ? 'ok' : 'muted') }}">{{ $row['balance'] < 0 ? '−' : '' }}{{ $money($row['balance']) }}</span>
                <span class="order__go">@include('courier-app._icon', ['name' => 'chevron'])</span>
            </div>
            <div class="order__meta">
                <span>Aktiv: <b>{{ $row['active'] }}</b></span>
                <span>Bu gün təhvil: <b>{{ $row['deliveredToday'] }}</b></span>
                @if($row['feeToday'] > 0)<span>Əlavə qazanc: <b class="ok">{{ number_format($row['feeToday'], 2) }} AZN</b></span>@endif
                @if(!$user->telegram_id)<span class="muted">Telegram qoşulmayıb</span>@endif
            </div>
        </a>
    @empty
        <div class="empty">Kuryer yoxdur.</div>
    @endforelse
    <p class="hint">Balans müsbətdirsə kuryer şirkətə təhvil verməlidir (sarı), mənfidirsə şirkət ona borcludur (yaşıl).</p>
@endsection
