<form class="filter-card" method="GET" action="{{ url()->current() }}">
    <button type="button" class="filter-card__head" data-filter-toggle aria-expanded="false" aria-controls="filterBody">
        <h3>{{ __('catalog_filter_title') }}</h3>
        <svg class="filter-card__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="filter-card__body" id="filterBody" hidden>
        <div class="filter-section">
            <p class="filter-section__label">{{ __('catalog_price_range') }}</p>

            <div class="price-slider" data-price-slider data-min="0" data-max="2400" data-step="10">
                <div class="price-slider__track">
                    <div class="price-slider__range" data-price-range></div>
                </div>
                <input type="range" class="price-slider__input price-slider__input--min" min="0" max="2400" step="10"
                       value="{{ request('min_price', 0) }}" data-price-min-range>
                <input type="range" class="price-slider__input price-slider__input--max" min="0" max="2400" step="10"
                       value="{{ request('max_price', 2400) }}" data-price-max-range>
            </div>

            <div class="price-slider__values">
                <span data-price-min-label>{{ request('min_price', 0) }} ₼</span>
                <span data-price-max-label>{{ request('max_price', 2400) }} ₼</span>
            </div>

            <input type="hidden" name="min_price" value="{{ request('min_price', 0) }}" data-price-min-hidden>
            <input type="hidden" name="max_price" value="{{ request('max_price', 2400) }}" data-price-max-hidden>
        </div>

        @if(isset($genders) && $genders->count())
            <div class="filter-section">
                <p class="filter-section__label">{{ __('catalog_gender') }}</p>
                <div class="filter-pills">
                    @foreach($genders as $gender)
                        <label class="filter-pill">
                            <input type="radio" name="gender" value="{{ $gender->id }}" @checked((int)request('gender') === $gender->id)>
                            <span>{{ $gender->{'name_' . app()->getLocale()} ?: $gender->name_az }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="filter-section">
            <p class="filter-section__label">{{ __('catalog_perfume_type') }}</p>
            @foreach($types as $type)
                <label class="filter-radio-row">
                    <input type="radio" name="type" value="{{ $type->id }}" @checked((int)request('type') === $type->id)>
                    <span>{{ $type->{'name_' . app()->getLocale()} ?: $type->name_az }}</span>
                </label>
            @endforeach
        </div>

        @if(isset($filterSizes) && $filterSizes->isNotEmpty())
            <div class="filter-section">
                <p class="filter-section__label">{{ __('catalog_volume') }}</p>
                <input type="search" class="filter-size-search" data-filter-size-search
                       placeholder="{{ __('catalog_search_size') }}"
                       aria-label="{{ __('catalog_search_size') }}">
                <div class="filter-size-scroll" data-filter-size-list>
                    @foreach($filterSizes as $size)
                        @php
                            $sizeName = $size->{'name_' . app()->getLocale()} ?: ($size->name_az ?: $size->name_en);
                        @endphp
                        <label class="filter-checkbox-row" data-filter-size-option>
                            <input type="checkbox" name="size[]" value="{{ $size->id }}"
                                   @checked(in_array((string) $size->id, array_map('strval', (array) request('size', [])), true))>
                            <span>{{ $sizeName }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        @if(isset($scentFamilies) && $scentFamilies->count())
            <div class="filter-section">
                <p class="filter-section__label">{{ __('catalog_scent_family') }}</p>
                @foreach($scentFamilies->take(6) as $family)
                    <label class="filter-checkbox-row">
                        <input type="checkbox" name="scent[]" value="{{ $family->id }}" @checked(in_array($family->id, (array) request('scent', [])))>
                        <span>{{ $family->{'name_' . app()->getLocale()} ?: $family->name_az }}</span>
                    </label>
                @endforeach

                @if($scentFamilies->count() > 6)
                    <button type="button" class="filter-expand" data-filter-expand>{{ __('catalog_all_scent_families') }}</button>
                    <div class="filter-checkbox-more" hidden>
                        @foreach($scentFamilies->skip(6) as $family)
                            <label class="filter-checkbox-row">
                                <input type="checkbox" name="scent[]" value="{{ $family->id }}" @checked(in_array($family->id, (array) request('scent', [])))>
                                <span>{{ $family->{'name_' . app()->getLocale()} ?: $family->name_az }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <div class="filter-card__footer">
            <a class="btn btn-outline filter-clear" href="{{ url()->current() }}">{{ __('catalog_clear') }}</a>
            <button type="submit" class="btn btn-dark filter-apply">{{ __('catalog_apply') }}</button>
        </div>
    </div>
</form>

<style>
    .filter-size-search {
        width: 100%;
        box-sizing: border-box;
        padding: 11px 13px;
        border: 1px solid #e3dfeb;
        border-radius: 8px;
        background: transparent;
        color: inherit;
        font: inherit;
        margin: 10px 0 12px;
    }
    .filter-size-search:focus { outline: 2px solid #c4a7e5; outline-offset: 1px; }
    .filter-size-scroll {
        max-height: 245px;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        padding-right: 7px;
    }
    .filter-size-scroll .filter-checkbox-row { display: flex; align-items: center; margin-bottom: 9px; }
</style>
<script>
document.addEventListener('input', function (event) {
    if (!event.target.matches('[data-filter-size-search]')) return;
    const query = event.target.value.trim().toLocaleLowerCase();
    const list = event.target.closest('.filter-section').querySelector('[data-filter-size-list]');
    list.querySelectorAll('[data-filter-size-option]').forEach(function (option) {
        option.hidden = !option.textContent.trim().toLocaleLowerCase().includes(query);
    });
});
</script>
