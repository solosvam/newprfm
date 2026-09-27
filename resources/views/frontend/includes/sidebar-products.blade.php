@if($items->isNotEmpty())
                    <div class="side-panel filter-card" data-collapsible-panel>
    <button type="button" class="side-panel__toggle" data-panel-toggle aria-expanded="false" aria-controls="{{ $panelId }}">
    <h3>{{ $title }}</h3>
    <svg class="side-panel__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
    </button>

    <div class="side-panel__body" id="{{ $panelId }}">
    @foreach($items as $item)
        @php
            $itemVariant = $item->variants->where('active', 1)->first();
        @endphp
        <a href="{{ route('product', $item->slug) }}" class="mini-card">
            <div class="mini-thumb">
                @if($item->images->first())
                    <img src="{{ asset('frontend/uploads/products/' . $item->images->first()->image) }}" alt="{{ $item->name }}" style="width:100%;height:100%;object-fit:contain;">
                @else
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                @endif
            </div>
            <div class="mini-info">
                <p class="n">{{ $item->name }}</p>
                <p class="p">{{ $itemVariant ? number_format((float) $itemVariant->price, 2) : '' }} ₼</p>
            </div>
        </a>
    @endforeach
    </div>
                    </div>
                @endif

