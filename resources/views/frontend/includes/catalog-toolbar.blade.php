            <div class="toolbar">
                <span>{{ $products->total() }} {{ __('home_results') }}</span>

                @if(isset($selectedBrand))
                    <span class="toolbar-brand">{{ $selectedBrand->name }}</span>
                @endif

                @if(isset($selectedCategory))
                    <span class="toolbar-brand">{{ $selectedCategory->{'name_' . app()->getLocale()} ?: $selectedCategory->name_az }}</span>
                @endif

                <form method="GET" action="{{ url()->current() }}">
                    {{-- cari filtrlər saxlanır; siyahı tipli olanlar da (size[]=1&size[]=3) — əvvəl ölçü filtri itirdi --}}
                    @foreach(request()->except('sort', 'page') as $key => $value)
                        @if(is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @elseif(is_array($value))
                            @foreach($value as $item)
                                @if(is_scalar($item))<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endif
                            @endforeach
                        @endif
                    @endforeach
                    {{-- "Populyar" (vitrin) yalnız ana səhifədə və orada standartdır --}}
                    @php $defaultSort = !empty($popularSort) ? 'popular' : 'newest'; @endphp
                    <select name="sort" class="sort-select" aria-label="{{ __('home_sort') }}" data-submit-on-change>
                        @if(!empty($popularSort))
                            <option value="popular" @selected(request('sort', $defaultSort) === 'popular')>{{ __('home_popular') }}</option>
                        @endif
                        <option value="newest" @selected(request('sort', $defaultSort) === 'newest')>{{ __('home_newest') }}</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('home_oldest') }}</option>
                        <option value="price_asc" @selected(request('sort') === 'price_asc')>{{ __('home_price_asc') }}</option>
                        <option value="price_desc" @selected(request('sort') === 'price_desc')>{{ __('home_price_desc') }}</option>
                    </select>
                </form>
            </div>
