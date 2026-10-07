{{-- Məlumat səhifələrinin yan menyusu (PageController::nav). Mobildə üfüqi pill-lər + ox (main.js initScrollPills) --}}
<nav class="info-nav pill-scroll-wrap" aria-label="{{ __('page_info') }}">
    <div class="info-nav__title">{{ __('page_info') }}</div>
    <ul data-pill-scroll>
        @foreach($nav as $item)
            <li><a href="{{ $item['url'] }}" @class(['is-active' => $item['key'] === $active]) @if($item['key'] === $active) aria-current="page" @endif>{{ $item['title'] }}</a></li>
        @endforeach
    </ul>
    <button type="button" class="cats-hint" data-pill-hint hidden aria-label="{{ __('page_info') }} →" tabindex="-1">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    </button>
</nav>
