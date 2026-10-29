@php
    $html_tag_data = [];
    $title = 'Sifarişlər';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Satışlar', '#' => $title];
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
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
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
                            <th class="text-muted text-small text-uppercase">No</th>
                            <th class="text-muted text-small text-uppercase">Tarix</th>
                            <th class="text-muted text-small text-uppercase">Müştəri</th>
                            <th class="text-muted text-small text-uppercase">Telefon</th>
                            <th class="text-muted text-small text-uppercase">Müştəri tipi</th>
                            <th class="text-muted text-small text-uppercase">Mənbə</th>
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
                                // Telefon gizli: 994****7575 (tam nömrə CRM-də)
                                $phone = preg_replace('/\D+/', '', (string) ($order->customer?->mobile ?? $order->guest_mobile));
                                $maskedPhone = strlen($phone) > 7 ? substr($phone, 0, 3).'****'.substr($phone, -4) : ($phone ?: '—');
                            @endphp
                            {{-- Sətrin istənilən yerinə klik sifarişi açır (keçidlər öz işini görür) --}}
                            <tr @if($url) data-href="{{ $url }}" style="cursor: pointer" @endif>
                                <td class="text-alternate">{{ $orders->firstItem() + $loop->index }}</td>
                                <td class="text-nowrap">
                                    {{ $order->created_at?->format('d.m.Y H:i') }}
                                    {{-- Uzun PS nömrəsi yer tutmasın: ⓘ-yə klik göstərir (üstünə gələndə də görünür) --}}
                                    <button type="button" class="btn btn-link p-0 ms-1 align-baseline" data-order-no="{{ $order->order_no }}"
                                            title="{{ $order->order_no }}" aria-label="Sifariş nömrəsi: {{ $order->order_no }}">ⓘ</button>
                                    <span class="d-none fw-bold" data-order-no-text>{{ $order->order_no }}</span>
                                </td>
                                <td>
                                    @if($order->customer)
                                        <a href="{{ route('admin.crm.customer', $order->customer_id) }}" class="body-link fw-bold">{{ $order->customer->full_name }}</a>
                                    @else
                                        <span class="fw-bold">Qonaq</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">{{ $maskedPhone }}</td>
                                <td>
                                    {{-- Sifariş tarixçəsinə görə; qonağın telefonu da yoxdursa — bilinmir, təxmin edilmir --}}
                                    @if(!$order->customer_id && !$order->guest_mobile)
                                        <span class="text-muted">—</span>
                                    @elseif($order->previous_orders > 0)
                                        <span class="badge bg-outline-primary">Təkrar müştəri</span>
                                    @else
                                        <span class="badge bg-outline-success">Yeni müştəri</span>
                                    @endif
                                </td>
                                <td>{{ $sources[$src] ?? 'Digər' }}</td>
                                <td class="text-end text-nowrap fw-bold">
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
                                    {{-- Kuryer təyin olunubsa — adı statusun altında --}}
                                    @if($order->courier)
                                        <div class="text-small text-nowrap mt-1" title="Kuryer">🚚 {{ $order->courier->full_name }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($url)<a href="{{ $url }}" class="btn btn-primary btn-sm">Aç</a>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-5">Sifariş tapılmadı</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $orders->links('backend.pagination') }}</div>
            </div>
        </div>
    </div>
@endsection

@section('js_page')
    <script>
        // ⓘ — PS nömrəsini göstərir / gizlədir
        document.querySelectorAll('[data-order-no]').forEach(button => button.addEventListener('click', () => {
            const text = button.nextElementSibling;
            text.classList.toggle('d-none');
            button.classList.toggle('d-none', !text.classList.contains('d-none'));
        }));
        document.querySelectorAll('[data-order-no-text]').forEach(text => text.addEventListener('click', event => {
            event.stopPropagation();
            text.classList.add('d-none');
            text.previousElementSibling.classList.remove('d-none');
        }));
        // Sətrə klik — sifariş açılır; keçid, düymə, PS nömrəsi və ya mətn seçimi olanda yox
        document.querySelectorAll('tr[data-href]').forEach(row => row.addEventListener('click', event => {
            if (event.target.closest('a, button, [data-order-no-text]') || window.getSelection()?.toString()) return;
            location.href = row.dataset.href;
        }));
    </script>
@endsection
