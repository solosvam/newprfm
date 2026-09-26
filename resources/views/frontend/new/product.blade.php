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

        // İlk (ən qısa) taksit müddətini defolt aktiv qəbul edirik
        $firstPeriod = $creditPeriods->first();
    @endphp

    <p class="crumb"><a href="{{ route('home') }}">Ana səhifə</a> / <a href="#">{{ $product->brand?->name }}</a> / {{ $product->name }}</p>

    <div class="product-top">
        <div class="product-header">
            <p class="subtitle" style="margin-bottom:0;color:var(--text-muted);font-size:13px;">{{ $product->brand?->name }}</p>
            <h1 class="title">{{ $product->name }}</h1>
            <p class="subtitle">{{ $genderName }} · {{ $typeName }}</p>
        </div>

        <div class="thumbs">
            @foreach ($product->images as $image)
                <div class="t {{ $loop->first ? 'active' : '' }}" data-thumb data-full="{{ asset('frontend/uploads/products/' . $image->image) }}">
                    <img src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="">
                </div>
            @endforeach
        </div>

        <div class="main-image">
            @if ($firstImage)
                <img data-main-image src="{{ asset('frontend/uploads/products/' . $firstImage->image) }}" alt="{{ $product->brand?->name }} {{ $product->name }}">
            @endif

            <div class="thumb-actions">
                <button type="button" class="icon-btn fav-btn" data-product-id="{{ $product->id }}" aria-label="Seçilmişlərə əlavə et">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                </button>
                <button type="button" class="icon-btn share-btn" data-url="{{ route('newproduct', $product->slug) }}" data-title="{{ $product->name }}" aria-label="Paylaş">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5 15.4 17.5M15.4 6.5 8.6 10.5"/></svg>
                </button>
            </div>
        </div>

        <div class="buy-core" data-buybox data-base-price="{{ $initialPrice }}" data-product-id="{{ $product->id }}">
            <div class="stars" aria-label="{{ $ratingAverage }} / 5">
                @for($star = 1; $star <= 5; $star++)
                    <span>{{ $star <= round($ratingAverage) ? '★' : '☆' }}</span>
                @endfor
                <span style="color:var(--text-muted);font-size:12px;">({{ $product->reviews->count() }} rəy)</span>
            </div>

            <p class="price-row" data-price-display>{{ number_format($initialPrice, 2) }} ₼</p>

            <div class="size-pills">
                @foreach ($variants as $variant)
                    <span
                        class="size-pill {{ $loop->first ? 'active-size-amount' : '' }}"
                        data-variant-id="{{ $variant->id }}"
                        data-price="{{ $variant->price }}"
                    >{{ $variant->size?->{'name_' . $locale} ?? $variant->size?->name_az }}</span>
                @endforeach
            </div>

            <div class="qty">
                <button type="button" data-qty-action="minus">−</button>
                <span data-qty-value>1</span>
                <button type="button" data-qty-action="plus">+</button>
            </div>

            <button type="button" class="btn btn-dark" data-add-to-cart data-variant-id="{{ $firstVariant?->id }}" data-product-id="{{ $product->id }}" @disabled(!$firstVariant)>Səbətə əlavə et</button>
            <a class="btn btn-outline" href="{{ auth()->check() ? route('checkout') : route('front.login', ['redirect' => route('checkout')]) }}">Sifarişi rəsmiləşdir</a>
        </div>

        <div class="installment-full" data-installment>
            <div class="installment-hero">
                <img src="{{ asset('frontend/images/birbank-card.png') }}" alt="Birbank taksit kartı">
                <div>
                    <p class="headline" data-installment-headline>
                        {{ number_format($initialPrice / 6, 2) }} ₼ x 6 ay
                    </p>
                    <p class="sub">Birbank taksit kartı ilə aktiv kredit müddətlərindən birini seçərək ödə!</p>
                </div>
            </div>

            <table class="installment-table">
                <thead>
                <tr><th></th><th>Müddət</th><th>Ayda</th><th>Qiymət</th></tr>
                </thead>
                <tbody>
                @foreach ($creditPeriods as $period)
                    @php
                        $rate = (float) $period->interest_rate;
                        $installmentTotal = $initialPrice + (($initialPrice * $rate) / 100);
                        $installmentMonthly = $installmentTotal / $period->month;
                    @endphp
                    <tr class="{{ $loop->first ? 'active' : '' }}" data-month="{{ $period->month }}" data-rate="{{ $rate }}">
                        <td>
                            <input
                                type="radio"
                                name="installment"
                                value="{{ $period->month }}"
                                data-monthly="{{ $installmentMonthly }}"
                                data-total="{{ $installmentTotal }}"
                                {{ $loop->first ? 'checked' : '' }}
                            >
                        </td>
                        <td>{{ $period->month }} ay{{ $rate == 0 ? ' Faizsiz' : '' }}</td>
                        <td data-installment-monthly>{{ number_format($installmentMonthly, 2) }} ₼</td>
                        <td data-installment-total>{{ number_format($installmentTotal, 2) }} ₼</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <a class="btn btn-dark" href="{{ auth()->check() ? route('profile.credit') : route('front.login', ['redirect' => route('profile.credit')]) }}">Müraciət et</a>
        </div>
    </div>

    <div class="tabs" data-tabs>
        <div class="tab-list">
            <button type="button" class="tab-btn active" data-tab="about">Ətir haqqında</button>
            <button type="button" class="tab-btn" data-tab="reviews">
                Rəylər <span class="tab-count">{{ $product->reviews->count() }}</span>
            </button>
        </div>

        <div class="tab-panel" data-tab-panel="about">
            @php
                $description = $product->{'content_' . $locale} ?: $product->content_az;
                $ingredientNames = $product->ingredients
                    ->map(fn ($ingredient) => $ingredient->{'name_' . $locale} ?: $ingredient->name_az)
                    ->filter();
            @endphp
            @if($description)
                <div class="product-description">{!! nl2br(e($description)) !!}</div>
            @else
                <p>Bu ətir haqqında təsvir hələ əlavə edilməyib.</p>
            @endif
            @if($ingredientNames->isNotEmpty())
                <div class="notes">
                    <div><p class="k">Ətir notları</p><p class="v">{{ $ingredientNames->join(', ') }}</p></div>
                </div>
            @endif
        </div>

        <div class="tab-panel" data-tab-panel="reviews" hidden>
            @include('frontend.new.partials.product-reviews', ['product' => $product])
        </div>
    </div>

    <div class="section-head" style="margin-top:40px;">
        <h2>Bənzər məhsullar</h2>
    </div>
    <div class="grid">
        @foreach ($similarProducts ?? [] as $item)
            <div class="card" data-href="{{ route('newproduct', $item->slug) }}">
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

                    @if ($item->images->first())
                        <img src="{{ asset('frontend/uploads/products/' . $item->images->first()->image) }}" alt="{{ $item->name }}" loading="lazy">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="34" height="34"><path d="M9 3h6l1 4H8l1-4Z"/><path d="M8 7h8l1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L8 7Z"/></svg>
                    @endif
                </div>
                <p class="brandname">{{ $item->brand?->name }}</p>
                <p class="pname">{{ $item->name }}</p>
                <p class="price">{{ number_format((float) ($item->variants->first()?->price ?? 0), 2) }} ₼</p>
            </div>
        @endforeach
    </div>
@endsection
