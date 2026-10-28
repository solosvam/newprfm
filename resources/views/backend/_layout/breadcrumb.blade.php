{{--
    $breadcrumbs: [url => ad]. "/" və "/admin" — admin panelin ana səhifəsi (sayt yox);
    "" və "#" — keçidsiz (bölmə adı və ya cari səhifə). Sonuncu element həmişə keçidsizdir və oxsuz.
--}}
@isset($breadcrumbs)
    <nav class="breadcrumb-container d-inline-block" aria-label="breadcrumb">
        <ul class="breadcrumb pt-0">
            @foreach ($breadcrumbs as $key => $value)
                @php
                    $last = $loop->last;
                    $href = $last || in_array($key, ['', '#'], true) ? null
                        : (in_array($key, ['/', '/admin'], true) ? route('admin.dashboard') : url($key));
                @endphp
                <li class="list-inline-item" @if($last) aria-current="page" @endif>
                    @if($href)
                        <a href="{{ $href }}" class="muted-link">
                            <span class="align-middle me-1">{{ $value }}</span>
                            <i data-acorn-icon="chevron-right" data-acorn-size="13"></i>
                        </a>
                    @else
                        <span class="text-muted">
                            <span class="align-middle me-1">{{ $value }}</span>
                            @unless($last)<i data-acorn-icon="chevron-right" data-acorn-size="13"></i>@endunless
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endisset
