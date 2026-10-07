@foreach($items as $item)
    @if(!empty($item['separator']))
        <li class="menu-separator" aria-hidden="true">
            <div class="col-12 px-1 py-2">
                <div class="separator-light"></div>
            </div>
        </li>
        @continue
    @endif
    <li>
        @if(!empty($item['children']))
            <a href="#{{ $item['id'] }}" data-href="/{{ $item['id'] }}">
                @if(!empty($item['icon']))
                    <i data-acorn-icon="{{ $item['icon'] }}" class="icon" data-acorn-size="18"></i>
                @endif
                <span class="label">{{ $item['title'] }}</span>
            </a>
            <ul id="{{ $item['id'] }}">
                @include('backend._layout.menu_items', ['items' => $item['children']])
            </ul>
        @else
            <a href="{{ route($item['route']) }}">
                @if(!empty($item['icon']))
                    <i data-acorn-icon="{{ $item['icon'] }}" class="icon" data-acorn-size="18"></i>
                @endif
                <span class="label">{{ $item['title'] }}</span>
            </a>
        @endif
    </li>
@endforeach
