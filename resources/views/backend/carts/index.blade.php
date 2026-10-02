@php
    $html_tag_data = [];
    $title = 'Səbətdəki mallar';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Satış', '#' => $title];
    $money = fn ($v, $d = 2) => number_format((float) $v, $d, '.', ' ');
    $days = fn ($date) => $date ? (int) \Illuminate\Support\Carbon::parse($date)->diffInDays(now()) : 0;
    $canProducts = auth('admin')->user()?->can('products.menu');
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

        {{-- Görünüş: müştəri üzrə / məhsul üzrə; müştəri görünüşündə süzgəc və sıralama --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <ul class="nav nav-tabs nav-tabs-line border-0">
                <li class="nav-item">
                    <a class="nav-link {{ $view === 'customers' ? 'active' : '' }}" href="{{ route('admin.carts.index') }}">Müştərilər üzrə</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $view === 'products' ? 'active' : '' }}" href="{{ route('admin.carts.index', ['view' => 'products']) }}">Məhsullar üzrə</a>
                </li>
            </ul>
            @if($view === 'customers')
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.carts.index', array_filter(['stale' => $stale ? null : 1, 'sort' => $sort === 'value' ? 'value' : null])) }}"
                       class="btn btn-sm {{ $stale ? 'btn-warning' : 'btn-outline-warning' }}">24 saatdan çox gözləyənlər</a>
                    <a href="{{ route('admin.carts.index', array_filter(['stale' => $stale ? 1 : null, 'sort' => $sort === 'value' ? null : 'value'])) }}"
                       class="btn btn-sm {{ $sort === 'value' ? 'btn-primary' : 'btn-outline-primary' }}">Dəyərə görə sırala</a>
                </div>
            @endif
        </div>

        @if($view === 'products')
            <div class="card mb-5">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th class="text-muted text-small text-uppercase">Məhsul</th>
                                <th class="text-muted text-small text-uppercase">Ölçü</th>
                                <th class="text-muted text-small text-uppercase text-end">Qiymət</th>
                                <th class="text-muted text-small text-uppercase text-end">Müştəri</th>
                                <th class="text-muted text-small text-uppercase text-end">Ədəd</th>
                                <th class="text-muted text-small text-uppercase text-end">İlk əlavə</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <td>
                                        @if($canProducts)
                                            <a href="{{ route('admin.product.edit', $row->product_id) }}" class="body-link">{{ $row->name }}</a>
                                        @else
                                            {{ $row->name }}
                                        @endif
                                        <div class="text-small text-muted">{{ $row->brand }}</div>
                                    </td>
                                    <td class="text-alternate">{{ $row->size }}</td>
                                    <td class="text-end text-nowrap">{{ $money($row->price) }} ₼</td>
                                    <td class="text-end fw-bold">{{ $row->customers }}</td>
                                    <td class="text-end">{{ $row->qty }}</td>
                                    <td class="text-end text-alternate text-nowrap">{{ $days($row->since) }} gün əvvəl</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-5">Səbətlərdə məhsul yoxdur</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $rows->links('backend.pagination') }}</div>
                </div>
            </div>
        @else
            @forelse($rows as $customer)
                @php $waiting = $days($customer->touched); @endphp
                <div class="card mb-2">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-12 col-md">
                                <a href="{{ route('admin.crm.customer', $customer->id) }}" class="body-link fw-bold">{{ trim($customer->name.' '.$customer->surname) }}</a>
                                <span class="text-small text-muted ms-2">{{ $customer->mobile }}</span>
                            </div>
                            <div class="col-auto text-small text-muted">
                                Səbətdə {{ $days($customer->since) }} gündür
                                @if($waiting >= 1)
                                    <span class="badge bg-outline-warning ms-1">{{ $waiting }} gündür toxunmayıb</span>
                                @endif
                            </div>
                            <div class="col-auto">
                                <span class="cta-3 text-primary">{{ $money($customer->value) }} ₼</span>
                                <span class="text-small text-muted ms-1">{{ $customer->qty }} ədəd</span>
                            </div>
                        </div>
                        @foreach($lines[$customer->id] ?? [] as $line)
                            <div class="d-flex justify-content-between text-small py-1 {{ $loop->first ? 'border-top border-separator-light pt-2' : '' }}">
                                <div class="text-truncate pe-3">
                                    {{ $line->brand }} — {{ $line->name }}{{ $line->size ? ' · '.$line->size : '' }}
                                    <span class="text-muted ms-1">{{ \Illuminate\Support\Carbon::parse($line->created_at)->format('d.m.Y') }}</span>
                                </div>
                                <div class="text-nowrap text-alternate">{{ $line->quantity }} × {{ $money($line->price) }} ₼</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card mb-5"><div class="card-body text-center text-muted py-5">
                    {{ $stale ? '24 saatdan çox gözləyən səbət yoxdur' : 'Səbətlərdə məhsul yoxdur' }}
                </div></div>
            @endforelse
            <div class="mt-3 mb-5">{{ $rows->links('backend.pagination') }}</div>
        @endif
    </div>
@endsection
