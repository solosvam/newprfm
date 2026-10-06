@php
    $html_tag_data = [];
    $title = 'Sifarişlər';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Satış', '#' => $title];
    $sources = ['customer' => 'Sayt — səbət', 'one_click' => 'Bir kliklə', 'operator' => 'Operator (CRM)'];
    $statusColor = ['new' => 'primary', 'delivered' => 'success', 'cancelled' => 'danger', 'sent' => 'info', 'at_address' => 'info'];
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
            </div>
        </div>

        {{-- Süzgəclər (GET) --}}
        <form method="GET" action="{{ route('admin.orders.index') }}" class="card mb-2">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4 col-xl-3">
                        <label class="form-label text-small text-muted mb-1">Axtarış</label>
                        <input type="search" name="q" value="{{ $filters['search'] }}" class="form-control form-control-sm" placeholder="Sifariş №, telefon, ad">
                    </div>
                    <div class="col-6 col-md-4 col-xl-3">
                        <label class="form-label text-small text-muted mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Hamısı</option>
                            <option value="active" @selected($filters['status'] === 'active')>Aktiv (bitməmiş)</option>
                            <option value="courier_late" @selected($filters['status'] === 'courier_late')>Kuryerdə 1 gündən çox</option>
                            @foreach($statuses as $s)
                                <option value="{{ $s->code }}" @selected($filters['status'] === $s->code)>{{ $s->name_az }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label text-small text-muted mb-1">Mənbə</label>
                        <select name="source" class="form-select form-select-sm">
                            <option value="">Hamısı</option>
                            @foreach($sources as $code => $label)
                                <option value="{{ $code }}" @selected($filters['source'] === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <label class="form-label text-small text-muted mb-1">Ödəniş üsulu</label>
                        <select name="payment" class="form-select form-select-sm">
                            <option value="">Hamısı</option>
                            @foreach($paymentMethods as $m)
                                <option value="{{ $m->code }}" @selected($filters['payment'] === $m->code)>{{ $m->name_az ?? $m->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Süz</button>
                        @if(array_filter($filters))
                            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-muted btn-sm">Sıfırla</a>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        <div class="card mb-5">
            <div class="card-body">
                <div class="text-small text-muted mb-3">Cəmi: {{ $orders->total() }}</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th class="text-muted text-small text-uppercase">Sifariş</th>
                            <th class="text-muted text-small text-uppercase">Tarix</th>
                            <th class="text-muted text-small text-uppercase">Müştəri</th>
                            <th class="text-muted text-small text-uppercase">Mənbə</th>
                            <th class="text-muted text-small text-uppercase">Ödəniş</th>
                            <th class="text-muted text-small text-uppercase text-end">Məbləğ</th>
                            <th class="text-muted text-small text-uppercase">Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($orders as $order)
                            @php
                                $url = $order->customer_id
                                    ? route('admin.crm.order', [$order->customer_id, $order->id])
                                    : ($order->one_click ? route('admin.easy-orders.show', $order) : null);
                                $src = $order->one_click ? 'one_click' : $order->source;
                            @endphp
                            <tr>
                                <td class="fw-bold">{{ $order->order_no }}</td>
                                <td class="text-alternate text-nowrap">{{ $order->created_at?->format('d.m.Y H:i') }}</td>
                                <td>
                                    @if($order->customer)
                                        <a href="{{ route('admin.crm.customer', $order->customer_id) }}" class="body-link">{{ $order->customer->full_name }}</a>
                                        <div class="text-small text-muted">{{ $order->customer->mobile }}</div>
                                    @else
                                        <span class="text-muted">Qonaq</span>
                                        <div class="text-small text-muted">{{ $order->guest_mobile }}</div>
                                    @endif
                                </td>
                                <td class="text-alternate">{{ $sources[$src] ?? 'Digər' }}</td>
                                <td class="text-alternate">
                                    {{ $order->paymentMethod?->name_az ?? $order->paymentMethod?->code }}
                                    @if($order->payment_status === 'paid')
                                        <span class="badge bg-outline-success ms-1">Ödənilib</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if($order->isCancelled() && $order->cancelledAmount() > 0)
                                        {{-- tam ləğv: yekun 0-dır — sifarişin ilkin məbləği göstərilir --}}
                                        {{ number_format($order->originalTotal(), 2, '.', ' ') }} ₼
                                    @else
                                        {{ number_format((float) $order->total, 2, '.', ' ') }} ₼
                                        @if($order->cancelledAmount() > 0)
                                            <div class="text-small text-muted">ilkin {{ number_format($order->originalTotal(), 2, '.', ' ') }} ₼</div>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-outline-{{ $statusColor[$order->status?->code] ?? 'secondary' }}">{{ $order->status?->name_az ?? '—' }}</span>
                                </td>
                                <td class="text-end">
                                    @if($url)
                                        <a href="{{ $url }}" class="btn btn-primary btn-sm">Aç</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-5">Sifariş tapılmadı</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $orders->links('backend.pagination') }}</div>
            </div>
        </div>
    </div>
@endsection
