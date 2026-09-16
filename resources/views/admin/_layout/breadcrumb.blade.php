@isset($breadcrumbs)
    <nav class="breadcrumb-container d-inline-block" aria-label="breadcrumb">
        <ul class="breadcrumb pt-0">
            @foreach ($breadcrumbs as $key => $value)
                <li class="list-inline-item">
                    <a href="{{ url($key) }}" class="muted-link">
                        <span class=" align-middle me-1">{{$value}}</span>
                        <i data-acorn-icon="chevron-right" data-acorn-size="13"></i>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endisset
