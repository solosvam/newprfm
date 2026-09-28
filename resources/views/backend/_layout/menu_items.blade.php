@foreach($items as $item)
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
