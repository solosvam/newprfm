<div class="wrap">
    <nav class="cats" aria-label="Kateqoriyalar">
        <a href="{{ route('newhome') }}" class="{{ request()->routeIs('newhome') && empty($selectedCategory) && !request()->filled('category') ? 'active' : '' }}">Hamısı</a>
        @foreach($categories ?? [] as $category)
            @php $name = $category->{'name_' . app()->getLocale()} ?: $category->name_az; @endphp
            <a href="{{ route('category', ['slug' => $category->slug]) }}"
               class="{{ ((isset($selectedCategory) && $selectedCategory?->id === $category->id) || (request()->routeIs('category') && request()->route('slug') === $category->slug)) ? 'active' : '' }}">{{ $name }}</a>
        @endforeach
    </nav>
</div>
