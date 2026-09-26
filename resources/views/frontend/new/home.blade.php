@extends('frontend.new.layouts.app')

@section('title', 'parfumshop — Ana səhifə')

@section('subnav')
    @include('frontend.new.partials.subnav')
@endsection

@section('content')

    <div class="hero-banner">
        @if(!empty($banners['topweb']))
            <img class="hero-banner__desktop" src="{{ asset('frontend/uploads/banners/' . $banners['topweb']) }}" alt="Parfumshop banner">
        @endif
        @if(!empty($banners['topmobile']))
            <img class="hero-banner__mobile" src="{{ asset('frontend/uploads/banners/' . $banners['topmobile']) }}" alt="Parfumshop mobil banner">
        @endif
    </div>

    <div class="brands">
        @foreach ($brands as $brand)
            <a class="brand-card {{ request('brand') == $brand->id ? 'active' : '' }}"
               href="{{ route('newhome', array_filter(['brand' => $brand->id, 'q' => request('q')])) }}">
                {{ $brand->name }}
            </a>
        @endforeach
        <a class="brand-card more" href="{{ route('brands') }}">{{ __('brands') }} →</a>
    </div>

    <div class="section-head">
        <h2>Ətirlər</h2>
        <a href="{{ route('newhome') }}">Hamısına bax</a>
    </div>

    <div class="layout">
        <form class="filters" method="GET" action="{{ route('newhome') }}">
            @if(request('category')) <input type="hidden" name="category" value="{{ request('category') }}"> @endif
            @if(request('brand')) <input type="hidden" name="brand" value="{{ request('brand') }}"> @endif
            @if(request('q')) <input type="hidden" name="q" value="{{ request('q') }}"> @endif
            @if(request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
            <div class="filter-group">
                <p class="label">Qiymət</p>
                <label class="filter-row">Minimum <input type="number" name="min_price" min="0" value="{{ request('min_price') }}" placeholder="0 ₼"></label>
                <label class="filter-row">Maksimum <input type="number" name="max_price" min="0" value="{{ request('max_price') }}" placeholder="2400 ₼"></label>
            </div>
            <div class="filter-group">
                <p class="label">Ətrin növü</p>
                @foreach($types as $type)
                    <label class="filter-row"><input type="radio" name="type" value="{{ $type->id }}" @checked((int)request('type') === $type->id)>
                        {{ $type->{'name_' . app()->getLocale()} ?: $type->name_az }}</label>
                @endforeach
            </div>
            <button type="submit" class="btn btn-dark">Filtrlə</button>
            <a class="filter-clear" href="{{ route('newhome') }}">Sıfırla</a>
        </form>

        <div>
            <div class="toolbar">
                <span>{{ $products->total() }} nəticə</span>
                <form method="GET" action="{{ route('newhome') }}">
                    @foreach(request()->except('sort', 'page') as $key => $value)
                        @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <select name="sort" aria-label="Sırala" onchange="this.form.submit()">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>Ən yenilər</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>Ən köhnələr</option>
                        <option value="price_asc" @selected(request('sort') === 'price_asc')>Ucuzdan bahaya</option>
                        <option value="price_desc" @selected(request('sort') === 'price_desc')>Bahadan ucuza</option>
                    </select>
                </form>
            </div>
            <div class="grid">
                @foreach ($products ?? [] as $product)
                    @php
                        $image = $product->images->first();
                        $gender = $product->genders->first();
                        $variants = $product->variants->where('active', 1);
                        $firstVariant = $variants->first();
                        $locale = app()->getLocale();

                        $genderName = $gender
                            ? ($gender->{'name_' . $locale} ?? $gender->name_az)
                            : null;

                        $typeName = $product->type
                            ? ($product->type->{'name_' . $locale} ?? $product->type->name_az)
                            : null;
                    @endphp
                    <div class="card" data-href="{{ route('newproduct', $product->slug) }}">
                        <div class="thumb">
                            @if($firstVariant)<span class="badge">{{ $firstVariant->size?->{'name_' . $locale} ?? $firstVariant->size?->name_az }}</span>@endif

                            <div class="thumb-actions">
                                <button
                                    type="button"
                                    class="icon-btn fav-btn {{ auth()->check() && auth()->user()->favoriteProducts()->where('products.id', $product->id)->exists() ? 'active' : '' }}"
                                    data-product-id="{{ $product->id }}"
                                    aria-label="Seçilmişlərə əlavə et"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                                </button>

                                <button
                                    type="button"
                                    class="icon-btn share-btn"
                                    data-url="{{ route('newproduct', $product->slug) }}"
                                    data-title="{{ $product->name }}"
                                    aria-label="Paylaş"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5 15.4 17.5M15.4 6.5 8.6 10.5"/></svg>
                                </button>
                            </div>

                            @if ($image)
                                <img
                                    src="{{ asset('frontend/uploads/products/' . $image->image) }}"
                                    alt="{{ $product->name }}"
                                    loading="lazy"
                                >
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="34" height="34"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                            @endif
                        </div>

                        <p class="brandname">{{ $product->brand?->name }} ·
                            @if($genderName || $typeName)
                                <span class="product-type">
                                    @if($genderName)
                                        {{ $genderName }}
                                    @endif

                                    @if($genderName && $typeName)
                                        |
                                    @endif

                                    @if($typeName)
                                        {{ $typeName }}
                                    @endif
                                </span>
                            @endif
                        </p>
                        <p class="pname">{{ $product->name }}</p>
                        <p class="price">@if($firstVariant){{ $firstVariant->size?->{'name_' . $locale} ?? $firstVariant->size?->name_az }} / {{ number_format((float) $firstVariant->price, 2) }} ₼@endif</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="catalog-pagination">{{ $products->links() }}</div>

@endsection
