{{--
  Baş kuryer → kuryer: balans və "Pulu özümə köçür" (courier.collect), aktiv sifarişlər (depolar üzrə vəziyyət, başqa kuryerə ötürmə),
  sifariş arxivi və haqq-hesab tarixçəsi. Baş kuryerə: filtrsiz — bu gün, filtrdə ən çox COURIER_DAYS_BACK gün geriyə.
--}}
@use('App\Models\Procurement\OrderItemAllocation', 'Part')
@use('App\Http\Controllers\Backend\CourierTeamController', 'Team')
@php
    $money = fn ($cents) => number_format(abs($cents) / 100, 2).' AZN';
    $partClass = [Part::PICKED => 'ok', Part::PROBLEM => 'danger', Part::RETURNING => 'warn'];
    $limitHint = fn (string $key) => $minDate ? (filled($filters[$key] ?? null)
        ? 'Ən çox '.Team::COURIER_DAYS_BACK.' gün geriyə baxmaq olar.'
        : 'Bu günün siyahısı. Əvvəlki günlər — filtrdən (ən çox '.Team::COURIER_DAYS_BACK.' gün).') : null;
    $aActive = collect(['a_from', 'a_to', 'a_status', 'a_q'])->contains(fn ($k) => filled($filters[$k] ?? null));
    $hActive = collect(['h_from', 'h_to', 'h_kind'])->contains(fn ($k) => filled($filters[$k] ?? null));
@endphp
@extends('courier-app.layout', ['tab' => 'team'])
@section('title', trim($courier->full_name))
@section('back', route('kuryer.team'))

@section('content')
    <div class="card">
        @include('courier-app._money', ['balance' => $balance, 'self' => false])
        @if($canCollect)
            <form method="post" action="{{ route('kuryer.team.collect', $courier) }}" class="stack" data-once data-confirm="Kuryerin balansından {amount} AZN hesabınıza köçürülsün?">
                @csrf
                <label class="label" for="collectAmount">Pulu özümə köçür (AZN)</label>
                <input type="number" step="0.01" min="0.01" max="{{ number_format($balance / 100, 2, '.', '') }}" name="amount" id="collectAmount"
                       class="field" value="{{ old('amount', number_format($balance / 100, 2, '.', '')) }}" inputmode="decimal" required>
                <input type="text" name="note" class="field" maxlength="200" placeholder="Qeyd (istəyə görə)" value="{{ old('note') }}">
                <button type="submit" class="btn btn--primary btn--block">Hesabıma köçür</button>
            </form>
        @endif
    </div>

    <h2 class="section">Aktiv sifarişlər</h2>
    @forelse($active as $order)
        @php
            $code = $order->status?->code;
            $parts = $order->items->flatMap(fn ($item) => $item->allocations->where('status', '!=', Part::CANCELLED)->map(fn ($a) => ['item' => $item, 'a' => $a]));
        @endphp
        <div class="card">
            @include('courier-app._order-card', ['order' => $order, 'statuses' => $statuses, 'href' => null, 'plain' => true])
            @foreach($parts->groupBy(fn ($row) => $row['a']->warehouse_id) as $rows)
                <div class="part">
                    <div class="wh__name">{{ $rows->first()['a']->warehouse?->name_az ?? 'Depo' }}</div>
                    @foreach($rows as $row)
                        <div class="part__info">
                            <span>{{ $row['item']->product?->name ?? 'Məhsul' }} × {{ $row['a']->quantity }}</span>
                            <span class="{{ $partClass[$row['a']->status] ?? 'muted' }}">{{ $row['a']->label() }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
            {{-- Başqa kuryerə ötür — heç nə götürülməyibsə (kuryerin özündəki qayda ilə eyni) --}}
            @if($code === 'courier_assigned')
                @php $transferBlock = $statuses->transferBlock($order); @endphp
                @if(!$transferBlock && $couriers->isNotEmpty())
                    <details class="more">
                        <summary class="btn btn--ghost btn--block">Başqa kuryerə ötür</summary>
                        <form method="POST" action="{{ route('kuryer.team.transfer', [$courier, $order]) }}" data-once data-confirm="Sifariş {{ $order->order_no }} seçilən kuryerə ötürülsün?" class="more__body">
                            @csrf
                            <label class="label" for="transfer-{{ $order->id }}">Kuryer</label>
                            <select id="transfer-{{ $order->id }}" name="courier_id" class="field" required>
                                <option value="">— seçin —</option>
                                @foreach($couriers as $c)<option value="{{ $c->id }}">{{ trim($c->full_name) }}</option>@endforeach
                            </select>
                            <button class="btn btn--primary btn--block">Ötür</button>
                            <div class="muted">Hər iki kuryerə Telegram bildirişi gedir.</div>
                        </form>
                    </details>
                @elseif($transferBlock)
                    <p class="muted">{{ $transferBlock }}</p>
                @endif
            @endif
        </div>
    @empty
        <div class="empty">Aktiv sifariş yoxdur.</div>
    @endforelse

    {{-- Sifariş arxivi --}}
    <h2 class="section" id="archive">Sifariş arxivi</h2>
    <details class="more" @if($aActive) open @endif>
        <summary class="btn btn--outline btn--block">Filter @if($aActive)<span class="badge">aktiv</span>@endif</summary>
        <form method="get" action="{{ route('kuryer.team.show', $courier) }}#archive" class="more__body">
            @foreach(request()->only(['h_from', 'h_to', 'h_kind']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <div class="two">
                <label class="label">Tarixdən<input type="date" name="a_from" class="field" value="{{ $filters['a_from'] ?? '' }}" @if($minDate) min="{{ $minDate }}" @endif></label>
                <label class="label">Tarixədək<input type="date" name="a_to" class="field" value="{{ $filters['a_to'] ?? '' }}" @if($minDate) min="{{ $minDate }}" @endif></label>
            </div>
            <label class="label">Status
                <select name="a_status" class="field">
                    <option value="">Hamısı</option>
                    @foreach($archiveStatuses as $code => $name)<option value="{{ $code }}" @selected(($filters['a_status'] ?? '') === $code)>{{ $name }}</option>@endforeach
                </select>
            </label>
            <input type="search" name="a_q" class="field" maxlength="60" placeholder="Sifariş nömrəsi və ya müştəri" value="{{ $filters['a_q'] ?? '' }}">
            <div class="row-btns">
                <button type="submit" class="btn btn--primary grow">Filter</button>
                @if($aActive)<a href="{{ route('kuryer.team.show', array_merge(['courier' => $courier], request()->only(['h_from', 'h_to', 'h_kind']))) }}#archive" class="btn btn--ghost">Təmizlə</a>@endif
            </div>
        </form>
    </details>
    @if($hint = $limitHint('a_from'))<p class="muted">{{ $hint }}</p>@endif
    <p class="muted">{{ $archiveTotals['count'] }} sifariş · {{ number_format($archiveTotals['sum'], 2) }} AZN</p>
    @if($archive->isEmpty())
        <div class="empty">{{ $aActive ? 'Filtrə uyğun sifariş yoxdur.' : 'Arxiv boşdur.' }}</div>
    @else
        <div class="card list">
            @foreach($archive as $order)
                <div class="list__row">
                    <div class="list__main">
                        <div class="list__what">{{ $order->order_no }} · {{ trim($order->customer?->name.' '.$order->customer?->surname) }}</div>
                        <div class="list__meta">{{ $order->updated_at?->format('d.m.Y H:i') }} · {{ $order->status?->name_az }}</div>
                    </div>
                    <div class="list__sum">{{ number_format((float) $order->total, 2) }} AZN</div>
                </div>
            @endforeach
        </div>
        @include('courier-app._pager', ['paginator' => $archive])
    @endif

    {{-- Haqq-hesab tarixçəsi: qalıq bütün tarixçə üzrədir, filtr yalnız sətirləri seçir --}}
    <h2 class="section" id="history">Haqq-hesab tarixçəsi</h2>
    <details class="more" @if($hActive) open @endif>
        <summary class="btn btn--outline btn--block">Filter @if($hActive)<span class="badge">aktiv</span>@endif</summary>
        <form method="get" action="{{ route('kuryer.team.show', $courier) }}#history" class="more__body">
            @foreach(request()->only(['a_from', 'a_to', 'a_status', 'a_q']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <div class="two">
                <label class="label">Tarixdən<input type="date" name="h_from" class="field" value="{{ $filters['h_from'] ?? '' }}" @if($minDate) min="{{ $minDate }}" @endif></label>
                <label class="label">Tarixədək<input type="date" name="h_to" class="field" value="{{ $filters['h_to'] ?? '' }}" @if($minDate) min="{{ $minDate }}" @endif></label>
            </div>
            <label class="label">Növ
                <select name="h_kind" class="field">
                    <option value="">Hamısı</option>
                    @foreach($kinds as $code => $name)<option value="{{ $code }}" @selected(($filters['h_kind'] ?? '') === $code)>{{ $name }}</option>@endforeach
                </select>
            </label>
            <div class="row-btns">
                <button type="submit" class="btn btn--primary grow">Filter</button>
                @if($hActive)<a href="{{ route('kuryer.team.show', array_merge(['courier' => $courier], request()->only(['a_from', 'a_to', 'a_status', 'a_q']))) }}#history" class="btn btn--ghost">Təmizlə</a>@endif
            </div>
        </form>
    </details>
    @if($hint = $limitHint('h_from'))<p class="muted">{{ $hint }}</p>@endif
    <p class="muted">{{ $history->total() }} hərəkət · <span class="warn">+{{ $money($historyTotals['in']) }}</span> · <span class="ok">−{{ $money($historyTotals['out']) }}</span></p>
    @include('courier-app._history', ['history' => $history, 'self' => false, 'empty' => 'Hərəkət yoxdur.'])
    @include('courier-app._pager', ['paginator' => $history])
@endsection
