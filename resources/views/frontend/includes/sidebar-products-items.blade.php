{{-- Sidebar siyahısının sətirləri. Parametrlər: $items, $ranked. /recommendations da bunu qaytarır. --}}
@foreach($items as $item)
    @php
        $itemVariant = $item->variants->first(); // ən ucuz aktiv variant
        $itemImage = $item->images->first();
        $itemSize = $itemVariant?->size?->name_az;
    @endphp
    <li>
        <a href="{{ route('product', $item->slug) }}" class="mini-card">
            <span class="mini-thumb">
                @if($ranked ?? false)<span class="mini-rank" aria-hidden="true">{{ $loop->iteration }}</span>@endif
                @if($itemImage)
                    <img src="{{ route('product.image', ['size' => 200, 'image' => $itemImage->image]) }}" alt="" width="200" height="200" loading="lazy" decoding="async">
                @else
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                @endif
            </span>
            <span class="mini-info">
                @if($item->brand)<span class="b">{{ $item->brand->name }}</span>@endif
                <span class="n">{{ $item->name }}</span>
                @if($itemVariant)
                    <span class="p">{{ number_format((float) $itemVariant->price, 2) }} ₼@if($itemSize)<small>{{ $itemSize }}</small>@endif</span>
                @endif
            </span>
        </a>
    </li>
@endforeach
