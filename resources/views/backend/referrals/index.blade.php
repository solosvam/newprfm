@php
    $html_tag_data = [];
    $title = 'Dəvətlər (referal)';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Satış', '#' => $title];
    $money = fn ($v) => number_format((float) $v, 2, '.', ' ');
    $person = fn ($customer) => $customer ? trim($customer->name.' '.$customer->surname) : '—';
    $statuses = [
        \App\Models\Customer\CustomerReferral::STATUS_REGISTERED => ['Gözləyir', 'bg-outline-warning', 'Dəvət olunanın ilk uyğun sifarişi hələ təhvil verilməyib'],
        \App\Models\Customer\CustomerReferral::STATUS_REWARDED => ['Bonus yazılıb', 'bg-outline-success', null],
        \App\Models\Customer\CustomerReferral::STATUS_REJECTED => ['Rədd edilib', 'bg-outline-danger', null],
    ];
    $link = fn (array $params) => route('admin.referrals.index', array_filter($params + ['view' => $view === 'referrers' ? 'referrers' : null, 'status' => $status, 'q' => $q ?: null], fn ($v) => $v !== null && $v !== ''));
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('content')
    <div class="container">
        <div class="page-title-container">
            <div class="row">
                <div class="col-12 col-sm-6">
                    <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                    @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
                </div>
                @can('system.settings')
                    <div class="col-12 col-sm-6 d-flex align-items-start justify-content-end">
                        <a href="{{ route('admin.settings.referral') }}" class="btn btn-outline-primary btn-sm">Referal ayarları</a>
                    </div>
                @endcan
            </div>
        </div>

        {{-- Xülasə --}}
        <div class="row g-2 mb-4">
            @foreach([
                ['Dəvət olunub', (int) $totals->invites, null],
                ['Gözləyir', (int) $totals->pending, null],
                ['Bonus yazılıb', (int) $totals->rewarded, null],
                ['Dəvət edənlərə', $money($totals->referrer_paid).' ₼', 'bonus'],
                ['Dəvət olunanlara', $money($totals->invitee_paid).' ₼', 'bonus və ya endirim'],
            ] as [$label, $value, $hint])
                <div class="col-6 col-md">
                    <div class="card h-100"><div class="card-body py-3">
                        <div class="text-muted">{{ $label }}@if($hint) <span class="text-muted">· {{ $hint }}</span>@endif</div>
                        <div class="cta-3 text-primary">{{ $value }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>

        {{-- Görünüş, süzgəc, axtarış --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <ul class="nav nav-tabs nav-tabs-line border-0">
                <li class="nav-item">
                    <a class="nav-link {{ $view === 'invites' ? 'active' : '' }}" href="{{ route('admin.referrals.index', array_filter(['q' => $q ?: null])) }}">Dəvətlər</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $view === 'referrers' ? 'active' : '' }}" href="{{ route('admin.referrals.index', array_filter(['view' => 'referrers', 'q' => $q ?: null])) }}">Dəvət edənlər üzrə</a>
                </li>
            </ul>
            <div class="d-flex flex-wrap gap-2">
                @if($view === 'invites')
                    <a href="{{ $link(['status' => null]) }}" class="btn btn-sm {{ !$status ? 'btn-primary' : 'btn-outline-primary' }}">Hamısı</a>
                    <a href="{{ $link(['status' => \App\Models\Customer\CustomerReferral::STATUS_REGISTERED]) }}" class="btn btn-sm {{ $status === \App\Models\Customer\CustomerReferral::STATUS_REGISTERED ? 'btn-warning' : 'btn-outline-warning' }}">Gözləyir</a>
                    <a href="{{ $link(['status' => \App\Models\Customer\CustomerReferral::STATUS_REWARDED]) }}" class="btn btn-sm {{ $status === \App\Models\Customer\CustomerReferral::STATUS_REWARDED ? 'btn-success' : 'btn-outline-success' }}">Bonus yazılıb</a>
                @endif
                <form method="GET" class="d-flex gap-2">
                    @if($view === 'referrers')<input type="hidden" name="view" value="referrers">@endif
                    @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                    <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Ad və ya telefon" aria-label="Ad və ya telefon">
                    <button class="btn btn-sm btn-outline-primary" type="submit">Axtar</button>
                </form>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-body">
                <div class="table-responsive">
                    @if($view === 'referrers')
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th class="text-muted text-small text-uppercase">Dəvət edən</th>
                                <th class="text-muted text-small text-uppercase text-end">Dəvət etdiyi</th>
                                <th class="text-muted text-small text-uppercase text-end">Bonus yazılıb</th>
                                <th class="text-muted text-small text-uppercase text-end">Qazandığı bonus</th>
                                <th class="text-muted text-small text-uppercase text-end">Son dəvət</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.crm.customer', $row->id) }}" class="body-link fw-bold">{{ $person($row) }}</a>
                                        <div class="text-muted">{{ $row->mobile }}</div>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <a href="{{ route('admin.referrals.index', ['q' => $row->mobile ?: $person($row)]) }}" class="body-link" title="Dəvətlərini göstər">{{ $row->invites }}</a>
                                    </td>
                                    <td class="text-end">{{ (int) $row->rewarded }}</td>
                                    <td class="text-end text-nowrap">{{ $money($row->earned) }} ₼</td>
                                    <td class="text-end text-alternate text-nowrap">{{ \Illuminate\Support\Carbon::parse($row->last_invite)->format('d.m.Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-5">{{ $q !== '' ? 'Axtarışa uyğun dəvət edən yoxdur' : 'Hələ dəvət yoxdur' }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    @else
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th class="text-muted text-small text-uppercase">Tarix</th>
                                <th class="text-muted text-small text-uppercase">Dəvət edən</th>
                                <th class="text-muted text-small text-uppercase">Dəvət olunan</th>
                                <th class="text-muted text-small text-uppercase">Status</th>
                                <th class="text-muted text-small text-uppercase">Sifariş</th>
                                <th class="text-muted text-small text-uppercase text-end">Dəvət edənə</th>
                                <th class="text-muted text-small text-uppercase text-end">Dəvət olunana</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($rows as $referral)
                                @php [$statusLabel, $statusClass, $statusHint] = $statuses[$referral->status] ?? [$referral->status, 'bg-outline-muted', null]; @endphp
                                <tr>
                                    <td class="text-alternate text-nowrap">{{ $referral->created_at?->format('d.m.Y H:i') }}</td>
                                    <td>
                                        @if($referral->referrer)
                                            <a href="{{ route('admin.crm.customer', $referral->referrer_id) }}" class="body-link">{{ $person($referral->referrer) }}</a>
                                            <div class="text-muted">{{ $referral->referrer->mobile }}</div>
                                        @else — @endif
                                    </td>
                                    <td>
                                        @if($referral->invitee)
                                            <a href="{{ route('admin.crm.customer', $referral->invitee_id) }}" class="body-link">{{ $person($referral->invitee) }}</a>
                                            <div class="text-muted">{{ $referral->invitee->mobile }}</div>
                                        @else — @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusClass }}" @if($statusHint) title="{{ $statusHint }}" @endif>{{ $statusLabel }}</span>
                                        @unless($referral->referrer_rewardable)
                                            <div class="text-muted mt-1" title="Dəvət olunanda dəvət edənin limiti dolmuşdu">limit dolub — dəvət edənə bonus yoxdur</div>
                                        @endunless
                                    </td>
                                    <td class="text-nowrap">
                                        @if($referral->order)
                                            <a href="{{ route('admin.crm.order', [$referral->order->customer_id, $referral->order]) }}" class="body-link">{{ $referral->order->order_no }}</a>
                                            <div class="text-muted">{{ $referral->rewarded_at?->format('d.m.Y') }}</div>
                                        @else — @endif
                                    </td>
                                    <td class="text-end text-nowrap">{{ $referral->referrer_amount !== null ? $money($referral->referrer_amount).' ₼' : '—' }}</td>
                                    <td class="text-end text-nowrap">
                                        @if($referral->invitee_amount !== null)
                                            {{ $money($referral->invitee_amount) }} ₼
                                            {{-- endirim rejimində dəvət olunan məbləği checkout-da endirim kimi alıb --}}
                                            <div class="text-muted">{{ (float) $referral->order?->referral_discount > 0 ? 'endirim' : 'bonus' }}</div>
                                        @else — @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-5">{{ $q !== '' || $status ? 'Süzgəcə uyğun dəvət yoxdur' : 'Hələ dəvət yoxdur' }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>
                <div class="mt-3">{{ $rows->links('backend.pagination') }}</div>
            </div>
        </div>
    </div>
@endsection
