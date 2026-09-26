@extends('frontend.new.layouts.app')

@section('title', 'parfumshop — Ana səhifə')

@section('subnav')
    @include('frontend.new.partials.subnav')
@endsection

@section('content')
    @include('frontend.new.includes.top-banners')

    <div class="brands">
        @foreach ($brands as $brand)
            <a class="brand-card {{ (isset($selectedBrand) && $selectedBrand?->id === $brand->id) ? 'active' : '' }}"
               href="{{ route('brand.products', ['slug' => $brand->slug]) }}">
                {{ $brand->name }}
            </a>
        @endforeach
        <a class="brand-card more" href="{{ route('brands') }}">{{ __('brands') }} →</a>
    </div>

    <div class="layout">

        <div class="sidebar-stack">
            <select class="brand-select select2" aria-label="Brend seç" onchange="if(this.value) window.location.href=this.value">
                <option value="">Brend Seç</option>
                @foreach($allBrands as $brand)
                    <option value="{{ route('brand.products', ['slug' => $brand->slug]) }}">{{ $brand->name }}</option>
                @endforeach
            </select>

            @include('frontend.new.includes.filter-form')

            <div id="sidebarExtras">
                @if(isset($recommendedProducts) && $recommendedProducts->count())
                    <div class="side-panel filter-card" data-collapsible-panel>
                        <button type="button" class="side-panel__toggle" data-panel-toggle aria-expanded="false" aria-controls="recommendedPanelBody">
                            <h3>Tövsiyə olunanlar</h3>
                            <svg class="side-panel__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div class="side-panel__body" id="recommendedPanelBody">
                            @foreach($recommendedProducts as $item)
                                @php
                                    $itemVariant = $item->variants->where('active', 1)->first();
                                @endphp
                                <a href="{{ route('newproduct', $item->slug) }}" class="mini-card">
                                    <div class="mini-thumb">
                                        @if($item->images->first())
                                            <img src="{{ asset('frontend/uploads/products/' . $item->images->first()->image) }}" alt="{{ $item->name }}" style="width:100%;height:100%;object-fit:contain;">
                                        @else
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                                        @endif
                                    </div>
                                    <div class="mini-info">
                                        <p class="n">{{ $item->name }}</p>
                                        <p class="p">{{ $itemVariant ? number_format((float) $itemVariant->price, 2) : '' }} ₼</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(isset($bestSellers) && $bestSellers->count())
                <div class="side-panel filter-card" data-collapsible-panel>
                    <button type="button" class="side-panel__toggle" data-panel-toggle aria-expanded="false" aria-controls="bestSellersPanelBody">
                        <h3>Ən çox satılanlar</h3>
                        <svg class="side-panel__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>

                    <div class="side-panel__body" id="bestSellersPanelBody">
                        @foreach($bestSellers as $item)
                            @php
                                $itemVariant = $item->variants->where('active', 1)->first();
                            @endphp
                            <a href="{{ route('newproduct', $item->slug) }}" class="mini-card">
                                <div class="mini-thumb">
                                    @if($item->images->first())
                                        <img src="{{ asset('frontend/uploads/products/' . $item->images->first()->image) }}" alt="{{ $item->name }}" style="width:100%;height:100%;object-fit:contain;">
                                    @else
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                                    @endif
                                </div>
                                <div class="mini-info">
                                    <p class="n">{{ $item->name }}</p>
                                    <p class="p">{{ $itemVariant ? number_format((float) $itemVariant->price, 2) : '' }} ₼</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
            </div>
        </div>

        <div>
            <div class="toolbar">
                <span>{{ $products->total() }} nəticə</span>

                @if(isset($selectedBrand))
                    <span class="toolbar-brand">{{ $selectedBrand->name }}</span>
                @endif

                @if(isset($selectedCategory))
                    <span class="toolbar-brand">{{ $selectedCategory->{'name_' . app()->getLocale()} ?: $selectedCategory->name_az }}</span>
                @endif

                <form method="GET" action="{{ route('newhome') }}">
                    @foreach(request()->except('sort', 'page') as $key => $value)
                        @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <select name="sort" class="sort-select" aria-label="Sırala" onchange="this.form.submit()">
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
                            <div class="thumb-actions">
                                <button
                                    type="button"
                                    class="icon-btn fav-btn"
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
    {{ $products->links('frontend.include.pagination') }}

    <div id="sidebarExtrasMobileSlot"></div>

    @include('frontend.new.includes.bottom-banners')
@endsection
