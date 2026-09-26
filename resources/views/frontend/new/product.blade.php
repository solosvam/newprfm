@extends('frontend.new.layouts.app')

@section('title', $product->name . ' | parfumshop')

@section('subnav')
    @include('frontend.new.partials.subnav')
@endsection

@section('content')
    @php
        $locale = app()->getLocale();
        $gender = $product->genders->first();
        $firstImage = $product->images->first();
        $variants = $product->variants->where('active', 1)->sortBy('price')->values();
        $firstVariant = $variants->first();
        $genderName = $gender ? ($gender->{'name_' . $locale} ?? $gender->name_az) : null;
        $typeName = $product->type ? ($product->type->{'name_' . $locale} ?? $product->type->name_az) : null;
        $initialPrice = (float) ($firstVariant?->price ?? 0);
        $birbankMonth = 6;
        $birbankTotal = $initialPrice;
    @endphp
    <p class="crumb"><a href="{{ route('home') }}">Ana səhifə</a> / <a href="#">{{ $product->brand?->name }}</a> / {{ $product->name }}</p>

    <div class="layout">
        <div>
            <div class="side-panel">
                <h3>Sizə tövsiyə olunur</h3>
                @foreach ($similarProducts as $item)
                    <div class="mini-card">
                        <div class="mini-thumb"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg></div>
                        <div class="mini-info"><p class="n">{{ $item->name }}</p><p class="p">{{ number_format((float) ($item->variants->first()?->price ?? 0), 2) }} ₼</p></div>
                    </div>
                @endforeach
            </div>

            <div class="side-panel">
                <h3>Ən çox satılanlar</h3>
                @foreach ($similarProducts as $item)
                    <div class="mini-card">
                        <div class="mini-thumb"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg></div>
                        <div class="mini-info"><p class="n">{{ $item->name }}</p><p class="p">{{ $item->price }} ₼</p></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <p class="subtitle" style="margin-bottom:0;color:var(--text-muted);font-size:13px;">{{ $product->brand->name }}</p>
            <h1 class="title">{{ $product->name }}</h1>
            <p class="subtitle">{{ $genderName }} · {{ $typeName }}</p>

            <div class="product-grid">
                <div class="thumbs">
                    @foreach($product->images as $image)
                    <div class="t">
                        <img src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="" />
                    </div>
                    @endforeach
                </div>

                <div class="main-image">
                    @if($firstImage)
                        <img src="{{ asset('frontend/uploads/products/' . $firstImage->image) }}" alt="{{ $product->brand?->name }} {{ $product->name }}"/>
                    @endif
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
                </div>

                <div class="buybox">
                    <div class="stars">★★★☆☆ <span style="color:var(--text-muted);font-size:12px;">({{ $product->reviews_count }} rəy)</span></div>
                    <p class="price-row">{{ number_format($firstVariant?->price ?? 0, 2) }} ₼</p>
                    @foreach($variants as $variant)
                        <span class="size-pill {{ $loop->first ? 'active-size-amount' : '' }}"
                              data-variant-id="{{ $variant->id }}"
                              data-price="{{ $variant->price }}"
                        >{{ $variant->size?->{'name_' . $locale} ?? $variant->size?->name_az }}</span>
                    @endforeach
                    <div class="qty"><button type="button" data-qty-action="minus">−</button><span data-qty-value>1</span><button type="button" data-qty-action="plus">+</button></div>
                    <button type="button" class="btn btn-dark" data-add-to-cart @disabled(!$firstVariant)>Səbətə əlavə et</button>
                    <a class="btn btn-outline" href="{{ auth()->check() ? route('checkout') : route('front.login', ['redirect' => route('checkout')]) }}">Sifarişi rəsmiləşdir</a>

                    <div class="installment">
                        <p class="headline">{{ number_format($initialPrice / 6, 2) }} ₼ x 6 ay</p>
                        <p class="sub">Birbank taksit kartı ilə faizsiz ödəniş</p>
                        @foreach ($creditPeriods as $period)
                            @php
                                $rate = (float) $period->interest_rate;
                                $installmentTotal = $initialPrice + (($initialPrice * $rate) / 100);
                                $installmentMonthly = $installmentTotal / $period->month;
                            @endphp
                            <div class="term-row ">
                                <span class="t">{{ $period->month }}</span><span>{{ number_format($installmentMonthly, 2) }} ₼/ay</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="desc-section">
                <h2>Haqqında</h2>
                <p>{{ $product->{'content_' . $locale} ?: $product->content_az }}</p>
                @if($product->ingredients->isNotEmpty())
                    <div class="notes">
                        <div><p class="k">Ətir notları</p><p class="v">{{ $product->ingredients->pluck('name')->filter()->join(', ') }}</p></div>
                    </div>
                @endif
            </div>
        </div>
    </div>

@endsection
