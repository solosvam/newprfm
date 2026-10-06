@php
    $html_tag_data = [];
    $title = 'Endirim gözləyənlər';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Satış', '#' => $title];
    $money = fn ($v) => number_format((float) $v, 2, '.', ' ');
    $date = fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('d.m.Y');
    $link = fn (array $params) => route('admin.price-alerts.index', array_filter($params + ['view' => $view === 'customers' ? 'customers' : null, 'notified' => $notified ? 1 : null]));
    $canDiscount = auth('admin')->user()?->can('product.discount');
    $variantName = fn ($variant) => trim(($variant?->product?->brand?->name ?? '').' '.($variant?->product?->name ?? '—'));
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <style>
        .alert-thumb { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 8px; border: 1px solid var(--separator-light); background: #fff center / contain no-repeat; }
    </style>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
            @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
        </div>

        {{-- izah: adi ölçüdə, oxunaqlı (əvvəl text-small idi) --}}
        <div class="alert alert-info d-flex gap-3 align-items-start mb-4" role="note" style="font-size: 15px; line-height: 1.55;">
            <i data-acorn-icon="notification" class="flex-shrink-0 mt-1"></i>
            <div>
                Bunlar məhsul səhifəsində <strong>"Qiymət enəndə xəbər ver"</strong> düyməsini basan müştərilərdir.
                Ölçünün qiyməti onların abunə olduğu qiymətdən aşağı düşəndə (qiymət dəyişikliyi və ya endirim) hər birinə
                <strong>avtomatik push bildirişi</strong> gedir. Çox gözlənilən ətirə <strong>"Endirim qoy"</strong> ilə endirim qoya bilərsiniz.
            </div>
        </div>

        {{-- Görünüş: məhsul üzrə / müştəri üzrə; gözləyənlər / xəbər verilənlər --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <ul class="nav nav-tabs nav-tabs-line border-0">
                <li class="nav-item">
                    <a class="nav-link {{ $view === 'products' ? 'active' : '' }}" href="{{ route('admin.price-alerts.index', array_filter(['notified' => $notified ? 1 : null])) }}">Məhsullar üzrə</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $view === 'customers' ? 'active' : '' }}" href="{{ route('admin.price-alerts.index', array_filter(['view' => 'customers', 'notified' => $notified ? 1 : null])) }}">Müştərilər üzrə</a>
                </li>
            </ul>
            <div class="d-flex gap-2">
                <a href="{{ $link(['notified' => null]) }}" class="btn btn-sm {{ !$notified ? 'btn-primary' : 'btn-outline-primary' }}">
                    Gözləyir <span class="badge bg-light text-dark ms-1">{{ $counts['waiting'] }}</span>
                </a>
                <a href="{{ $link(['notified' => 1]) }}" class="btn btn-sm {{ $notified ? 'btn-success' : 'btn-outline-success' }}">
                    Xəbər verilib <span class="badge bg-light text-dark ms-1">{{ $counts['notified'] }}</span>
                </a>
            </div>
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
                                <th class="text-muted text-small text-uppercase text-end">Müştəri</th>
                                <th class="text-muted text-small text-uppercase text-end">Abunə qiyməti</th>
                                <th class="text-muted text-small text-uppercase text-end">İndiki qiymət</th>
                                <th class="text-muted text-small text-uppercase text-end">İlk abunə</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($rows as $row)
                                @php
                                    $variant = $variants[$row->product_variant_id] ?? null;
                                    $product = $variant?->product;
                                    $image = $product?->images->first();
                                    $current = $variant?->salePrice();
                                @endphp
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="alert-thumb" @if($image) style="background-image: url('{{ asset('frontend/uploads/products/'.$image->image) }}')" @endif></span>
                                            <div>
                                                <div class="fw-bold">{{ $product?->name ?? 'Silinmiş məhsul' }}</div>
                                                <div class="text-muted">{{ $product?->brand?->name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-alternate">{{ $variant?->size?->name_az ?? '—' }}</td>
                                    <td class="text-end fw-bold">{{ $row->customers }}</td>
                                    <td class="text-end text-nowrap">
                                        {{ $money($row->min_price) }}@if((float) $row->max_price !== (float) $row->min_price) – {{ $money($row->max_price) }}@endif ₼
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if($variant)
                                            @if($current < (float) $variant->price)
                                                <s class="text-muted">{{ $money($variant->price) }}</s>
                                                <span class="text-danger fw-bold">{{ $money($current) }} ₼</span>
                                            @else
                                                {{ $money($variant->price) }} ₼
                                            @endif
                                        @else — @endif
                                    </td>
                                    <td class="text-end text-alternate text-nowrap">{{ $date($row->since) }}</td>
                                    <td class="text-end">
                                        @if($product && $canDiscount && !$notified)
                                            {{-- çox gözlənilən ətirə endirim qoymaq --}}
                                            <a href="{{ route('admin.product.edit', $product->id) }}#product-discount" class="btn btn-sm btn-outline-danger text-nowrap">
                                                {{ $product->activeDiscount ? '−'.$product->activeDiscount->percentLabel().'% endirimdə' : 'Endirim qoy' }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-5">{{ $notified ? 'Hələ heç kimə xəbər verilməyib' : 'Endirim gözləyən müştəri yoxdur' }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $rows->links('backend.pagination') }}</div>
                </div>
            </div>
        @else
            @forelse($rows as $customer)
                <div class="card mb-2">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-12 col-md">
                                <a href="{{ route('admin.crm.customer', $customer->id) }}" class="body-link fw-bold">{{ trim($customer->name.' '.$customer->surname) }}</a>
                                <span class="text-muted ms-2">{{ $customer->mobile }}</span>
                            </div>
                            <div class="col-auto text-muted">{{ $customer->alerts }} ətir · son abunə {{ $date($customer->last_at) }}</div>
                        </div>
                        @foreach($alerts[$customer->id] ?? [] as $alert)
                            @php $variant = $alert->variant; $current = $variant?->salePrice(); @endphp
                            <div class="d-flex justify-content-between py-1 {{ $loop->first ? 'border-top border-separator-light pt-2' : '' }}">
                                <div class="text-truncate pe-3">
                                    {{ $variantName($variant) }}{{ $variant?->size ? ' · '.$variant->size->name_az : '' }}
                                    <span class="text-muted ms-1">{{ $alert->created_at->format('d.m.Y') }}</span>
                                </div>
                                <div class="text-nowrap text-alternate">
                                    abunə {{ $money($alert->price) }} ₼
                                    @if($variant)
                                        · indi <span @class(['text-danger fw-bold' => $current < (float) $alert->price])>{{ $money($current) }} ₼</span>
                                    @endif
                                    @if($alert->notified_at)<span class="badge bg-outline-success ms-1">{{ $alert->notified_at->format('d.m.Y') }} xəbər verilib</span>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card mb-5"><div class="card-body text-center text-muted py-5">
                    {{ $notified ? 'Hələ heç kimə xəbər verilməyib' : 'Endirim gözləyən müştəri yoxdur' }}
                </div></div>
            @endforelse
            <div class="mt-3 mb-5">{{ $rows->links('backend.pagination') }}</div>
        @endif
    </div>
@endsection
