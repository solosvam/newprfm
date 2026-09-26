<form class="filter-card" method="GET" action="{{ route('home') }}">
    <button type="button" class="filter-card__head" data-filter-toggle aria-expanded="false" aria-controls="filterBody">
        <h3>Filter</h3>
        <svg class="filter-card__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="filter-card__body" id="filterBody" hidden>
        <div class="filter-section">
            <p class="filter-section__label">Qiymət aralığı</p>

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
                <p class="filter-section__label">Cinsiyyət</p>
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
            <p class="filter-section__label">Ətrin növü</p>
            @foreach($types as $type)
                <label class="filter-radio-row">
                    <input type="radio" name="type" value="{{ $type->id }}" @checked((int)request('type') === $type->id)>
                    <span>{{ $type->{'name_' . app()->getLocale()} ?: $type->name_az }}</span>
                </label>
            @endforeach
        </div>

        @if(isset($volumeRanges))
            <div class="filter-section">
                <p class="filter-section__label">Ətrin həcmi</p>
                <div class="filter-volume-grid">
                    @foreach($volumeRanges as $range)
                        <label class="filter-radio-row">
                            <input type="radio" name="volume" value="{{ $range['value'] }}" @checked(request('volume') === $range['value'])>
                            <span>{{ $range['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="filter-hint">Burada bütün ölçülər deyil, ölçü aralıqlarını göstərmək kifayətdir.</p>
            </div>
        @endif

        @if(isset($scentFamilies) && $scentFamilies->count())
            <div class="filter-section">
                <p class="filter-section__label">Qoxu ailəsi</p>
                @foreach($scentFamilies->take(6) as $family)
                    <label class="filter-checkbox-row">
                        <input type="checkbox" name="scent[]" value="{{ $family->id }}" @checked(in_array($family->id, (array) request('scent', [])))>
                        <span>{{ $family->{'name_' . app()->getLocale()} ?: $family->name_az }}</span>
                    </label>
                @endforeach

                @if($scentFamilies->count() > 6)
                    <button type="button" class="filter-expand" data-filter-expand>Bütün qoxu ailələri</button>
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
            <a class="btn btn-outline filter-clear" href="{{ route('home') }}">Təmizlə</a>
            <button type="submit" class="btn btn-dark filter-apply">Filter</button>
        </div>
    </div>
</form>
