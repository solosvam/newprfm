{{--
  Hesabın səhifəsi (Kassa → karta klik).
  Anbar: borc, ödənilməmiş hissələr + "Ödə", ümumi ödəniş. Kuryer: haqq-hesab + "Pulu təhvil al" (yalnız nağd kassaya).
--}}
@php
    $html_tag_data = [];
    $title = $account->name;
    $breadcrumbs = ['/' => 'ParfumShop', route('admin.finance.index') => 'Kassa', '' => $account->name];
    $money = fn ($cents) => number_format(abs($cents) / 100, 2).' AZN';
    $editable = in_array($account->type, array_merge(\App\Models\Finance\FinanceAccount::CREATABLE, ['online']), true);
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <link rel="stylesheet" href="{{ asset('backend/css/vendor/datatables.min.css') }}"/>
    <link rel="stylesheet" href="{{ asset_v('backend/css/finance.css') }}"/>
@endsection

@section('js_page')
    <script src="{{ asset('backend/js/vendor/datatables.min.js') }}"></script>
    <script src="{{ asset('backend/js/cs/scrollspy.js') }}"></script>
    <script src="{{ asset('backend/js/cs/datatable.extend.js') }}"></script>
    <script src="{{ asset('backend/js/plugins/datatable.boxedvariations.js') }}"></script>
    <script src="{{ asset_v('backend/js/finance.js') }}"></script>
@endsection

@section('content')
<div class="container finance-page">
    <div class="page-title-container">
        <div class="row">
            <div class="col-12 col-sm-6">
                <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
                @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
            </div>
            <div class="col-12 col-sm-6 d-flex flex-wrap gap-2 align-items-start justify-content-sm-end">
                @if($account->type === 'courier')
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#handoverModal" @disabled($balance <= 0)>Pulu təhvil al</button>
                @elseif($account->type === 'warehouse')
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#warehousePayModal"
                            data-allocation="" data-left="" data-title="{{ $account->name }} — ümumi ödəniş (sifarişə bağlı olmadan)">Ümumi ödəniş</button>
                @endif
                @if($editable)
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#accountModal">Redaktə et</button>
                @endif
            </div>
        </div>
    </div>
    @include('backend.procurement.feedback')
    @if($account->description)<p class="text-muted mb-4">{{ $account->description }}</p>@endif

    {{-- Xülasə --}}
    <div class="row g-3 mb-4">
        @if($account->type === 'courier')
            <div class="col-12 col-md-6 col-xl-4"><div class="card"><div class="card-body">
                @if($balance > 0)
                    <div class="finance-account__label">Şirkətə təhvil verməlidir</div><div class="finance-account__value text-warning">{{ $money($balance) }}</div>
                @elseif($balance < 0)
                    <div class="finance-account__label">Şirkətin kuryerə borcu</div><div class="finance-account__value text-danger">{{ $money($balance) }}</div>
                    <div class="small text-muted mt-1">Kuryer öz pulundan ödəyib. Qaytarmaq üçün "Yeni əməliyyat → Köçürmə": Kassa → kuryer.</div>
                @else
                    <div class="finance-account__label">Hesablaşılıb</div><div class="finance-account__value">0.00 AZN</div>
                @endif
            </div></div></div>
            <div class="col-12 col-md-6 col-xl-8"><div class="card h-100"><div class="card-body small text-muted">
                Qalıq artır: müştəridən nağd aldıqda, avans veriləndə. Azalır: anbara ödəyəndə, pulu kassaya təhvil verəndə.
            </div></div></div>
        @elseif($account->type === 'warehouse')
            <div class="col-12 col-md-6 col-xl-4"><div class="card"><div class="card-body">
                <div class="finance-account__label">{{ $debt > 0 ? 'Anbara borc (götürülən mal)' : ($debt < 0 ? 'Avans (artıq ödənilib)' : 'Borc yoxdur') }}</div>
                <div class="finance-account__value {{ $debt > 0 ? 'text-danger' : '' }}">{{ $money($debt) }}</div>
            </div></div></div>
        @else
            <div class="col-12 col-md-6 col-xl-4"><div class="card"><div class="card-body">
                @if($account->type === 'person')
                    <div class="finance-account__label">{{ $balance < 0 ? 'Borcumuz' : ($balance > 0 ? 'Bizə borcludur' : 'Hesablaşılıb') }}</div>
                    <div class="finance-account__value {{ $balance < 0 ? 'text-danger' : '' }}">{{ $money($balance) }}</div>
                @else
                    <div class="finance-account__label">{{ $account->type === 'expense' ? 'Xərclənib' : 'Qalıq' }}</div>
                    <div class="finance-account__value">{{ $balance < 0 ? '−' : '' }}{{ $money($balance) }}</div>
                @endif
            </div></div></div>
        @endif
    </div>

    @if($account->type === 'warehouse' && $byDay && $byDay['days'])
        {{-- Borc günlər üzrə: hansı gün nə qədər mal götürülüb, nə qədəri ödənilib, nə qədəri qalıb --}}
        <h2 class="small-title">Borc günlər üzrə</h2>
        <div class="card mb-4"><div class="card-body">
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr>@foreach(['Gün', 'Götürülən mal', 'Ödənilib', 'Qalıq borc'] as $th)<th class="text-muted text-small text-uppercase">{{ $th }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($byDay['days'] as $day)
                    <tr>
                        <td class="text-nowrap">{{ $day['date']->isToday() ? 'Bu gün' : ($day['date']->isYesterday() ? 'Dünən' : $day['date']->format('d.m.Y')) }}
                            <span class="d-block small text-muted">{{ $day['parts'] }} hissə</span></td>
                        <td class="text-nowrap">{{ $money($day['cost']) }}</td>
                        <td class="text-nowrap text-success">{{ $day['paid'] ? $money($day['paid']) : '—' }}</td>
                        <td class="text-nowrap fw-bold {{ $day['left'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $day['left'] > 0 ? $money($day['left']) : 'ödənilib' }}</td>
                    </tr>
                @endforeach
                @if($byDay['unlinked'] !== 0)
                    <tr>
                        <td colspan="3" class="text-muted">{{ $byDay['unlinked'] > 0 ? 'Ümumi ödəniş (sifarişə bağlı olmadan) — borcdan çıxılır' : 'Anbarın qaytardığı pul (sifarişə bağlı olmadan)' }}</td>
                        <td class="text-nowrap fw-bold {{ $byDay['unlinked'] > 0 ? 'text-success' : 'text-danger' }}">{{ $byDay['unlinked'] > 0 ? '−' : '+' }}{{ $money($byDay['unlinked']) }}</td>
                    </tr>
                @endif
                </tbody>
            </table></div>
        </div></div>
    @endif

    @if($account->type === 'warehouse')
        <h2 class="small-title">Ödənilməmiş hissələr</h2>
        <div class="card mb-5"><div class="card-body">
            @if($parts->isEmpty())
                <p class="text-muted mb-0">Ödənilməmiş hissə yoxdur.</p>
            @else
                <div class="table-responsive"><table class="table align-middle mb-0">
                    <thead><tr>@foreach(['Sifariş', 'Məhsul', 'Say × alış', 'Mərhələ', 'Dəyər', 'Ödənilib', 'Qalıq', ''] as $th)<th class="text-muted text-small text-uppercase">{{ $th }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach($parts as ['a' => $a, 'cost' => $cost, 'paid' => $paid])
                        @php $order = $a->orderItem?->order; $left = $cost - $paid; @endphp
                        <tr>
                            <td>@if($order)<a href="{{ route('admin.crm.order', [$order->customer_id, $order->id]) }}#settlements">{{ $order->order_no }}</a>@else — @endif</td>
                            <td>{{ $a->orderItem?->product?->name }}<span class="d-block small text-muted">{{ $a->orderItem?->variant?->size?->name_az }}</span></td>
                            <td class="text-nowrap">{{ $a->quantity }} × {{ number_format((float) $a->unit_cost, 2) }}</td>
                            <td>{{ $a->label() }}@if($a->status === 'returned')<span class="d-block small text-muted">anbara qaytarılıb — borc yoxdur</span>@elseif(!in_array($a->status, \App\Models\Procurement\OrderItemAllocation::DEBT_STATUSES, true))<span class="d-block small text-muted">borc götürüləndə yaranır</span>@endif</td>
                            <td class="text-nowrap">{{ $money($cost) }}</td>
                            <td class="text-nowrap text-success">{{ $paid ? $money($paid) : '—' }}</td>
                            <td class="text-nowrap fw-bold {{ in_array($a->status, \App\Models\Procurement\OrderItemAllocation::DEBT_STATUSES, true) ? 'text-danger' : '' }}">{{ $money($left) }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#warehousePayModal"
                                        data-allocation="{{ $a->id }}" data-left="{{ number_format($left / 100, 2, '.', '') }}"
                                        data-title="{{ $order?->order_no }} · {{ $a->orderItem?->product?->name }} × {{ $a->quantity }} — qalıq {{ $money($left) }}">Ödə</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div></div>
    @endif

    <h2 class="small-title">Pul hərəkətləri</h2>
    <div class="card mb-5"><div class="card-body">
        @include('backend.finance.partials.movements')
    </div></div>
</div>

@if($editable)
    @include('backend.finance.partials.account-modal', ['editAccount' => $account, 'editBalance' => $balance])
@endif

@if($account->type === 'courier')
    {{-- Pulu təhvil al: kuryer → mərkəzi nağd kassa (bank olmaz — kuryer nağd verir) --}}
    <div class="modal modal-right fade" id="handoverModal" tabindex="-1" aria-labelledby="handoverTitle" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.finance.store') }}" data-movement-form>
            @csrf
            <input type="hidden" name="kind" value="courier_handover">
            <input type="hidden" name="from_account_id" value="{{ $account->id }}">
            <input type="hidden" name="to_account_id" value="{{ $cash->id }}">
            <div class="modal-header"><h5 class="modal-title" id="handoverTitle">Pulu təhvil al</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
            <div class="modal-body">
                <p class="mb-3">{{ $account->name }} → <strong>{{ $cash->name }}</strong></p>
                <div class="mb-3"><label class="form-label" for="hoAmount">Məbləğ, AZN</label>
                        <input id="hoAmount" type="number" name="amount" min="0.01" step="0.01" class="form-control" value="{{ $balance > 0 ? number_format($balance / 100, 2, '.', '') : '' }}" required></div>
                <div class="mb-3"><label class="form-label" for="hoAt">Real tarix <small class="text-muted">(boş — indi)</small></label>
                        <input id="hoAt" type="datetime-local" name="occurred_at" class="form-control"></div>
                <label class="form-label" for="hoNote">Qeyd</label>
                <textarea id="hoNote" name="note" rows="2" maxlength="2000" class="form-control"></textarea>
                <div class="form-text mt-2">Kuryerin qalığı bu məbləğ qədər azalır, kassa artır.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Təhvil aldım</button></div>
        </form></div>
    </div>
@endif

@if($account->type === 'warehouse')
    {{-- Anbara ödəniş: hissəyə bağlı (data-allocation) və ya ümumi --}}
    <div class="modal modal-right fade" id="warehousePayModal" tabindex="-1" aria-labelledby="warehousePayTitle" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.finance.store') }}" data-movement-form>
            @csrf
            <input type="hidden" name="kind" value="warehouse_payment">
            <input type="hidden" name="order_item_allocation_id" value="">
            <input type="hidden" name="to_account_id" value="{{ $account->id }}">
            <div class="modal-header"><h5 class="modal-title" id="warehousePayTitle">Anbara ödəniş</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bağla"></button></div>
            <div class="modal-body">
                <div class="fw-bold mb-3" data-pay-title></div>
                <div class="mb-3"><label class="form-label" for="payFrom">Kim ödədi</label>
                    <select id="payFrom" name="from_account_id" class="form-select" required>
                        <option value="">Seçin</option>
                        @foreach($payers as $acc)<option value="{{ $acc->id }}">{{ $acc->name }} · {{ $acc->typeLabel() }}</option>@endforeach
                    </select>
                    <div class="form-text">Kuryer ödəyibsə — kuryerin qalığı azalır. Şəxs öz pulundan ödəyibsə — şirkət ona borclu qalır.</div>
                </div>
                <div class="mb-3"><label class="form-label" for="payAmount">Məbləğ, AZN</label><input id="payAmount" type="number" name="amount" min="0.01" step="0.01" class="form-control" required></div>
                <div class="mb-3"><label class="form-label" for="payAt">Real tarix <small class="text-muted">(boş — indi)</small></label><input id="payAt" type="datetime-local" name="occurred_at" class="form-control"></div>
                <label class="form-label" for="payNote">Qeyd</label>
                <textarea id="payNote" name="note" rows="2" maxlength="2000" class="form-control" placeholder="Məs.: qəbz №"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Bağla</button><button class="btn btn-primary">Qeydə al</button></div>
        </form></div>
    </div>
@endif

@include('backend.finance.partials.reverse-modal')
@endsection
