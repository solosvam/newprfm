<div class="wrap">
    <nav class="cats" aria-label="Kateqoriyalar">
        <a href="{{ route('newhome') }}" class="{{ empty($selectedCategory) && !request('category') ? 'active' : '' }}">Hamısı</a>
        @foreach($categories ?? [] as $category)
            @php $name = $category->{'name_' . app()->getLocale()} ?: $category->name_az; @endphp
            <a href="{{ route('newhome', ['category' => $category->id]) }}"
               class="{{ (int) request('category') === (int) $category->id ? 'active' : '' }}">{{ $name }}</a>
        @endforeach
    </nav>
</div>
