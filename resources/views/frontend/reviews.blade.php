@extends('frontend.layout')

@section('page-styles')
    <link rel="stylesheet" href="{{ asset('frontend/css/pages/reviews.css?v=' . filemtime(public_path('frontend/css/pages/reviews.css'))) }}">
@endsection

@section('content')
    <main>
        <div class="container">
            <div class="cabinet">
                @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('reviews_my_reviews')])

                <section class="cabinet__comments" aria-label="{{ __('reviews_my_reviews') }}">
                    @if($reviews->isEmpty())
                        <div class="cabinet-content-empty">
                            <h2>{{ __('reviews_my_reviews') }}</h2>
                            <p>{{ __('reviews_you_haven_t_written_any_reviews_yet') }}</p>
                        </div>
                    @else
                        <ul class="cabinet__comments__list">
                            @foreach($reviews as $review)
                                @php
                                    $product = $review->product;
                                    $image = $product?->images?->first();
                                    $rating = max(0, min(5, (int) $review->rating));
                                    $productUrl = $product ? route('product', $product->slug) : null;
                                @endphp

                                <li class="review-entry">
                                    <time class="review-entry__date" datetime="{{ $review->created_at->toDateString() }}">
                                        {{ $review->created_at->locale(app()->getLocale())->translatedFormat('j F Y') }}
                                    </time>

                                    <article class="review-entry__card">
                                        <div class="review-entry__product">
                                            @if($productUrl)
                                                <a class="review-entry__image" href="{{ $productUrl }}">
                                                    @if($image)
                                                        <img src="{{ asset('frontend/uploads/products/' . $image->image) }}"
                                                             alt="{{ $product->name }}" loading="lazy">
                                                    @endif
                                                </a>
                                            @else
                                                <div class="review-entry__image">
                                                    @if($image)
                                                        <img src="{{ asset('frontend/uploads/products/' . $image->image) }}"
                                                             alt="{{ $product->name }}" loading="lazy">
                                                    @endif
                                                </div>
                                            @endif

                                            <div class="review-entry__details">
                                                @if($productUrl)
                                                    <a class="review-entry__name" href="{{ $productUrl }}">{{ $product->name }}</a>
                                                @else
                                                    <span class="review-entry__name">{{ $product?->name ?? '—' }}</span>
                                                @endif

                                                <span class="review-entry__brand">{{ $product?->brand?->name }}</span>

                                                <div class="review-entry__rating"
                                                     role="img"
                                                     aria-label="{{ $rating }} / 5">
                                                    @for($star = 1; $star <= 5; $star++)
                                                        <img src="{{ asset('frontend/images/' . ($star <= $rating ? 'star-filled.svg' : 'star-outlined.svg')) }}"
                                                             alt="">
                                                    @endfor
                                                </div>
                                            </div>
                                        </div>

                                        <div class="review-entry__comment">
                                            <p>{{ $review->comment }}</p>
                                        </div>
                                    </article>
                                </li>
                            @endforeach
                        </ul>

                        @if($reviews->hasPages())
                            <div class="main-products__pagination">
                                {{ $reviews->links() }}
                            </div>
                        @endif
                    @endif
                </section>
            </div>
        </div>
    </main>
@endsection
