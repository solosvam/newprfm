            <div class="toolbar">
                <span>{{ $products->total() }} {{ __('home_results') }}</span>

                @if(isset($selectedBrand))
                    <span class="toolbar-brand">{{ $selectedBrand->name }}</span>
                @endif

                @if(isset($selectedCategory))
                    <span class="toolbar-brand">{{ $selectedCategory->{'name_' . app()->getLocale()} ?: $selectedCategory->name_az }}</span>
                @endif

                <form method="GET" action="{{ url()->current() }}">
                    @foreach(request()->except('sort', 'page') as $key => $value)
                        @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <select name="sort" class="sort-select" aria-label="{{ __('home_sort') }}" onchange="this.form.submit()">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>{{ __('home_newest') }}</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('home_oldest') }}</option>
                        <option value="price_asc" @selected(request('sort') === 'price_asc')>{{ __('home_price_asc') }}</option>
                        <option value="price_desc" @selected(request('sort') === 'price_desc')>{{ __('home_price_desc') }}</option>
                    </select>
                </form>
            </div>
