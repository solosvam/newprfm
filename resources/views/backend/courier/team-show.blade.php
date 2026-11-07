{{--
  Baş kuryer → kuryer: balans və "Pulu özümə köçür" (courier.collect), aktiv sifarişlər depolar üzrə vəziyyətlə,
  sifariş arxivi və haqq-hesab tarixçəsi.
--}}
@use('App\Models\Procurement\OrderItemAllocation', 'Part')
@php
    $html_tag_data = [];
    $title = trim($courier->full_name);
    $breadcrumbs = [route('admin.dashboard') => 'Tapşırıqlarım', route('admin.courier.team') => 'Kuryerlər', '' => $title];
    $money = fn ($cents) => number_format(abs($cents) / 100, 2).' AZN';
    $stepLabels = ['courier_assigned' => 'Toplanır', 'sent' => 'Yoldadır'];
    $partClass = [Part::PICKED => 'text-success', Part::PROBLEM => 'text-danger', Part::RETURNING => 'text-warning'];
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

    <div class="card mb-4"><div class="card-body">
        @if($balance > 0)
            <div class="courier-summary__label">Şirkətə təhvil verməlidir</div>
            <div class="courier-summary__value text-warning">{{ $money($balance) }}</div>
        @elseif($balance < 0)
            <div class="courier-summary__label">Şirkətin ona borcu</div>
            <div class="courier-summary__value text-success">{{ $money($balance) }}</div>
        @else
            <div class="courier-summary__label">Hesablaşma</div>
            <div class="courier-summary__value">0.00 AZN</div>
        @endif
        <div class="mt-2">@include('backend.courier._location', ['user' => $courier, 'link' => true])</div>
        @if($canCollect)
            <form method="post" action="{{ route('admin.courier.team.collect', $courier) }}" class="courier-deliver mt-3"
                  onsubmit="return confirm('Kuryerin balansından ' + this.amount.value + ' AZN hesabınıza köçürülsün?')">
                @csrf
                <label class="form-label" for="collectAmount">Pulu özümə köçür (AZN)</label>
                <input type="number" step="0.01" min="0.01" max="{{ number_format($balance / 100, 2, '.', '') }}" name="amount" id="collectAmount"
                       class="form-control mb-2" value="{{ old('amount', number_format($balance / 100, 2, '.', '')) }}" inputmode="decimal" required>
                <input type="text" name="note" class="form-control mb-2" maxlength="200" placeholder="Qeyd (istəyə görə)" value="{{ old('note') }}">
                <button type="submit" class="btn btn-primary w-100">Hesabıma köçür</button>
            </form>
        @endif
    </div></div>

    <h2 class="small-title">Aktiv sifarişlər</h2>
    @forelse($active as $order)
        @php
            $code = $order->status?->code;
            $collect = $statuses->collectAmount($order);
            $parts = $order->items->flatMap(fn ($item) => $item->allocations->where('status', '!=', Part::CANCELLED)->map(fn ($a) => ['item' => $item, 'a' => $a]));
        @endphp
        <div class="card courier-order mb-3">
            <div class="card-body">
                <div class="courier-order__top">
                    <strong>{{ $order->order_no }}</strong>
                    <span class="badge {{ $code === 'courier_assigned' ? 'bg-outline-primary' : 'bg-primary' }}">{{ $stepLabels[$code] ?? $order->status?->name_az }}</span>
                </div>
                <div class="courier-order__who">{{ trim($order->customer?->name.' '.$order->customer?->surname) }}</div>
                <div class="courier-order__where">{{ $order->address?->shortLine() }}@if($order->meeting_place) · <b>{{ $order->meeting_place }}</b>@endif</div>
                <div class="courier-order__meta">
                    <span>{!! $collect > 0 ? 'Alınacaq: <b>'.number_format($collect, 2).' AZN</b>' : '<span class="text-success">Ödənilib</span>' !!}</span>
                </div>
                @foreach($parts->groupBy(fn ($row) => $row['a']->warehouse_id) as $rows)
                    <div class="courier-part">
                        <div class="courier-part__name">{{ $rows->first()['a']->warehouse?->name_az ?? 'Depo' }}</div>
                        @foreach($rows as $row)
                            <div class="courier-part__info">
                                <span>{{ $row['item']->product?->name ?? 'Məhsul' }} × {{ $row['a']->quantity }}</span>
                                <span class="{{ $partClass[$row['a']->status] ?? 'text-muted' }}">{{ $row['a']->label() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
                {{-- Başqa kuryerə ötür — heç nə götürülməyibsə (kuryerin özündəki qayda ilə eyni) --}}
                @if($code === 'courier_assigned')
                    @php $transferBlock = $statuses->transferBlock($order); @endphp
                    @if(!$transferBlock && $couriers->isNotEmpty())
                        <details class="courier-more mt-3 mb-0">
                            <summary class="btn btn-outline-secondary w-100">Başqa kuryerə ötür</summary>
                            <form method="POST" action="{{ route('admin.courier.team.transfer', [$courier, $order]) }}" data-once class="courier-more__form"
                                  onsubmit="return confirm('Sifariş {{ $order->order_no }} seçilən kuryerə ötürülsün?')">
                                @csrf
                                <label class="form-label" for="transfer-{{ $order->id }}">Kuryer</label>
                                <select id="transfer-{{ $order->id }}" name="courier_id" class="form-select" required>
                                    <option value="">— seçin —</option>
                                    @foreach($couriers as $c)<option value="{{ $c->id }}">{{ trim($c->full_name) }}</option>@endforeach
                                </select>
                                <button class="btn btn-primary w-100 mt-2">Ötür</button>
                                <div class="text-muted small mt-1">Hər iki kuryerə Telegram bildirişi gedir.</div>
                            </form>
                        </details>
                    @elseif($transferBlock)
                        <div class="text-muted small mt-2">{{ $transferBlock }}</div>
                    @endif
                @endif
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-center text-muted py-4">Aktiv sifariş yoxdur.</div></div>
    @endforelse

    {{-- Yer tarixçəsi: kuryer tətbiqi hər açılanda göndərir; gün seçilir, günün marşrutu xəritədə --}}
    <h2 class="small-title mt-5" id="locations">Yer tarixçəsi</h2>
    <form method="get" action="{{ route('admin.courier.team.show', $courier) }}#locations" class="d-flex flex-wrap gap-2 align-items-center mb-2">
        @foreach(request()->except(['l_date', 'page', 'hpage']) as $key => $value)@if(is_string($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
        <input type="date" name="l_date" class="form-control w-auto" value="{{ $locationDate->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" aria-label="Gün">
        <button type="submit" class="btn btn-outline-primary">Göstər</button>
        @if($routeUrl)<a href="{{ $routeUrl }}" target="_blank" rel="noopener" class="btn btn-primary">Günün marşrutu xəritədə</a>@endif
    </form>
    @if($locations->isEmpty())
        <div class="card"><div class="card-body text-center text-muted py-4">Bu gün üçün yer məlumatı yoxdur.</div></div>
    @else
        <div class="text-muted text-small mb-2">{{ $locations->count() }} nöqtə</div>
        <div class="card"><div class="card-body courier-history">
            @foreach($locations->reverse() as $point)
                <div class="courier-history__row">
                    <div class="courier-history__main">
                        <div class="courier-history__what">{{ $point->recorded_at->format('H:i') }}</div>
                        <div class="courier-history__meta">{{ $point->lat }}, {{ $point->lng }}@if($point->accuracy) · ±{{ $point->accuracy }} m @endif</div>
                    </div>
                    <div class="courier-history__sum"><a href="{{ $point->mapsUrl() }}" target="_blank" rel="noopener">Xəritədə aç</a></div>
                </div>
            @endforeach
        </div></div>
    @endif

    {{-- Sifariş arxivi --}}
    <h2 class="small-title mt-5" id="archive">Sifariş arxivi</h2>
    @php $aActive = collect(['a_from', 'a_to', 'a_status', 'a_q'])->contains(fn ($k) => filled($filters[$k] ?? null)); @endphp
    <details class="courier-more mb-3" @if($aActive) open @endif>
        <summary class="btn btn-outline-primary w-100">Filter @if($aActive)<span class="badge bg-primary ms-2">aktiv</span>@endif</summary>
        <form method="get" action="{{ route('admin.courier.team.show', $courier) }}#archive" class="courier-more__form">
            @foreach(request()->only(['h_from', 'h_to', 'h_kind']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label text-small mb-1">Tarixdən</label><input type="date" name="a_from" class="form-control" value="{{ $filters['a_from'] ?? '' }}" @if($minDate) min="{{ $minDate }}" @endif></div>
                <div class="col-6"><label class="form-label text-small mb-1">Tarixədək</label><input type="date" name="a_to" class="form-control" value="{{ $filters['a_to'] ?? '' }}" @if($minDate) min="{{ $minDate }}" @endif></div>
            </div>
            <label class="form-label text-small mb-1">Status</label>
            <select name="a_status" class="form-select mb-2">
                <option value="">Hamısı</option>
                @foreach($archiveStatuses as $code => $name)<option value="{{ $code }}" @selected(($filters['a_status'] ?? '') === $code)>{{ $name }}</option>@endforeach
            </select>
            <input type="search" name="a_q" class="form-control mb-2" maxlength="60" placeholder="Sifariş nömrəsi və ya müştəri" value="{{ $filters['a_q'] ?? '' }}">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                @if($aActive)<a href="{{ route('admin.courier.team.show', array_merge(['courier' => $courier], request()->only(['h_from', 'h_to', 'h_kind']))) }}#archive" class="btn btn-outline-secondary">Təmizlə</a>@endif
            </div>
        </form>
    </details>
    @if($minDate)<div class="text-muted text-small mb-1">{{ filled($filters['a_from'] ?? null) ? 'Ən çox '.\App\Http\Controllers\Backend\CourierTeamController::COURIER_DAYS_BACK.' gün geriyə baxmaq olar.' : 'Bu günün siyahısı. Əvvəlki günlər — filtrdən (ən çox '.\App\Http\Controllers\Backend\CourierTeamController::COURIER_DAYS_BACK.' gün).' }}</div>@endif
    <div class="text-muted text-small mb-2">{{ $archiveTotals['count'] }} sifariş · {{ number_format($archiveTotals['sum'], 2) }} AZN</div>
    @if($archive->isEmpty())
        <div class="card"><div class="card-body text-center text-muted py-4">{{ $aActive ? 'Filtrə uyğun sifariş yoxdur.' : 'Arxiv boşdur.' }}</div></div>
    @else
        <div class="card"><div class="card-body courier-history">
            @foreach($archive as $order)
                <div class="courier-history__row">
                    <div class="courier-history__main">
                        <div class="courier-history__what">{{ $order->order_no }} · {{ trim($order->customer?->name.' '.$order->customer?->surname) }}</div>
                        <div class="courier-history__meta">{{ $order->updated_at?->format('d.m.Y H:i') }} · {{ $order->status?->name_az }}</div>
                    </div>
                    <div class="courier-history__sum">{{ number_format((float) $order->total, 2) }} AZN</div>
                </div>
            @endforeach
        </div></div>
        <div class="mt-3">@include('backend._layout.simple-pagination', ['paginator' => $archive])</div>
    @endif

    {{-- Haqq-hesab tarixçəsi: qalıq bütün tarixçə üzrədir, filtr yalnız sətirləri seçir --}}
    <h2 class="small-title mt-5" id="history">Haqq-hesab tarixçəsi</h2>
    @php $hActive = collect(['h_from', 'h_to', 'h_kind'])->contains(fn ($k) => filled($filters[$k] ?? null)); @endphp
    <details class="courier-more mb-3" @if($hActive) open @endif>
        <summary class="btn btn-outline-primary w-100">Filter @if($hActive)<span class="badge bg-primary ms-2">aktiv</span>@endif</summary>
        <form method="get" action="{{ route('admin.courier.team.show', $courier) }}#history" class="courier-more__form">
            @foreach(request()->only(['a_from', 'a_to', 'a_status', 'a_q']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label text-small mb-1">Tarixdən</label><input type="date" name="h_from" class="form-control" value="{{ $filters['h_from'] ?? '' }}" @if($minDate) min="{{ $minDate }}" @endif></div>
                <div class="col-6"><label class="form-label text-small mb-1">Tarixədək</label><input type="date" name="h_to" class="form-control" value="{{ $filters['h_to'] ?? '' }}" @if($minDate) min="{{ $minDate }}" @endif></div>
            </div>
            <label class="form-label text-small mb-1">Növ</label>
            <select name="h_kind" class="form-select mb-2">
                <option value="">Hamısı</option>
                @foreach($kinds as $code => $name)<option value="{{ $code }}" @selected(($filters['h_kind'] ?? '') === $code)>{{ $name }}</option>@endforeach
            </select>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                @if($hActive)<a href="{{ route('admin.courier.team.show', array_merge(['courier' => $courier], request()->only(['a_from', 'a_to', 'a_status', 'a_q']))) }}#history" class="btn btn-outline-secondary">Təmizlə</a>@endif
            </div>
        </form>
    </details>
    @if($minDate)<div class="text-muted text-small mb-1">{{ filled($filters['h_from'] ?? null) ? 'Ən çox '.\App\Http\Controllers\Backend\CourierTeamController::COURIER_DAYS_BACK.' gün geriyə baxmaq olar.' : 'Bu günün siyahısı. Əvvəlki günlər — filtrdən (ən çox '.\App\Http\Controllers\Backend\CourierTeamController::COURIER_DAYS_BACK.' gün).' }}</div>@endif
    <div class="text-muted text-small mb-2">{{ $history->total() }} hərəkət · <span class="text-warning">+{{ $money($historyTotals['in']) }}</span> · <span class="text-success">−{{ $money($historyTotals['out']) }}</span></div>
    @include('backend.courier._history', ['history' => $history, 'self' => false])
    <div class="mt-3">@include('backend._layout.simple-pagination', ['paginator' => $history])</div>
</div>
@endsection
