<div class="wrap cats-wrap">
    <nav class="cats" aria-label="{{ __('common_categories') }}">
        <a href="{{ route('home') }}" class="{{ request()->routeIs('home') && empty($selectedCategory) && !request()->filled('category') ? 'active' : '' }}">{{ __('common_all') }}</a>
        @foreach($categories ?? [] as $category)
            @php $name = $category->{'name_' . app()->getLocale()} ?: $category->name_az; @endphp
            <a href="{{ route('category', ['slug' => $category->slug]) }}"
               class="{{ ((isset($selectedCategory) && $selectedCategory?->id === $category->id) || (request()->routeIs('category') && request()->route('slug') === $category->slug)) ? 'active' : '' }}">{{ $name }}</a>
        @endforeach
    </nav>
    {{-- Sağda davamı olduğunu göstərən ox (main.js idarə edir) --}}
    <button type="button" class="cats-hint" data-cats-hint hidden aria-label="{{ __('common_categories') }} →" tabindex="-1">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    </button>
</div>
