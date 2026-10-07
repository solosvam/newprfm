@php
    $html_tag_data = [];
    $title = 'Endirimdəki məhsullar';
    $breadcrumbs = ['/admin' => 'ParfumShop', '' => 'Kataloq', '#' => $title];
    $image = fn ($product) => ($img = $product?->images->first()) ? asset('frontend/uploads/products/'.$img->image) : null;
    $tabs = ['active' => 'Aktiv', 'scheduled' => 'Planlaşdırılıb', 'ended' => 'Bitib'];
@endphp
@extends('backend.layout', ['html_tag_data' => $html_tag_data, 'title' => $title])

@section('css')
    <style>
        .discount-thumb { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 8px; border: 1px solid var(--separator-light); background: var(--background) center / contain no-repeat; }
        .discount-left { font-variant-numeric: tabular-nums; white-space: nowrap; }
    </style>
@endsection

@section('js_page')
    <script>
        // Bitməyə / başlamağa qalan vaxt: "2 gün 05:14:33"
        (() => {
            const cells = [...document.querySelectorAll('[data-countdown]')];
            if (!cells.length) return;
            const pad = (n) => String(n).padStart(2, '0');
            const tick = () => cells.forEach((cell) => {
                let s = Math.max(0, Math.floor((Number(cell.dataset.countdown) - Date.now()) / 1000));
                if (s === 0) { cell.textContent = cell.dataset.doneText; return; }
                const d = Math.floor(s / 86400); s %= 86400;
                cell.textContent = (d ? d + ' gün ' : '') + pad(Math.floor(s / 3600)) + ':' + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60);
            });
            tick();
            setInterval(tick, 1000);
        })();
    </script>
@endsection

@section('content')
    <div class="container">
        <div class="page-title-container">
            <h1 class="mb-0 pb-0 display-4" id="title">{{ $title }}</h1>
            @include('backend._layout.breadcrumb', ['breadcrumbs' => $breadcrumbs])
        </div>

        <ul class="nav nav-tabs nav-tabs-line border-0 mb-3">
            @foreach($tabs as $key => $label)
                <li class="nav-item">
                    <a class="nav-link {{ $status === $key ? 'active' : '' }}" href="{{ route('admin.product-discounts.index', ['status' => $key]) }}">
                        {{ $label }}@isset($counts[$key]) <span class="badge bg-outline-primary ms-1">{{ $counts[$key] }}</span>@endisset
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="card mb-5">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th class="text-muted text-small text-uppercase">Məhsul</th>
                            <th class="text-muted text-small text-uppercase">Endirim</th>
                            <th class="text-muted text-small text-uppercase">Qiymət</th>
                            <th class="text-muted text-small text-uppercase">Dövr</th>
                            <th class="text-muted text-small text-uppercase text-end">{{ $status === 'scheduled' ? 'Başlamağa' : ($status === 'active' ? 'Bitməyə' : 'Bitib') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($discounts as $discount)
                            @php $product = $discount->product; $cheapest = $product?->variants->first(); @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="discount-thumb" @if($image($product)) style="background-image: url('{{ $image($product) }}')" @endif></span>
                                        <div>
                                            <a href="{{ route('admin.product.edit', $discount->product_id) }}#product-discount" class="body-link fw-bold">{{ $product?->name }}</a>
                                            <div class="text-muted">{{ $product?->brand?->name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-bold text-primary">−{{ $discount->percentLabel() }}%</td>
                                <td class="text-nowrap">
                                    @if($cheapest)
                                        <s class="text-muted">{{ number_format((float) $cheapest->price, 2) }}</s> {{ number_format($discount->apply((float) $cheapest->price), 2) }} ₼-dən
                                    @else — @endif
                                </td>
                                <td class="text-nowrap">{{ $discount->starts_at->format('d.m.Y H:i') }} — {{ $discount->ends_at->format('d.m.Y H:i') }}</td>
                                <td class="text-end discount-left">
                                    @if($status === 'ended')
                                        <span class="text-muted">{{ $discount->ends_at->format('d.m.Y') }}</span>
                                    @else
                                        <span data-countdown="{{ ($status === 'scheduled' ? $discount->starts_at : $discount->ends_at)->getTimestampMs() }}"
                                              data-done-text="{{ $status === 'scheduled' ? 'başladı' : 'bitdi' }}"></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">
                                {{ ['active' => 'Hazırda endirimdə məhsul yoxdur', 'scheduled' => 'Planlaşdırılmış endirim yoxdur', 'ended' => 'Bitmiş endirim yoxdur'][$status] }}
                                <div class="mt-1">Endirim məhsulun redaktə səhifəsində"Endirim" tabından əlavə olunur.</div>
                            </td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $discounts->links('backend.pagination') }}</div>
            </div>
        </div>
    </div>
@endsection
