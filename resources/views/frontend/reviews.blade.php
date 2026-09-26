@extends('frontend.layouts.app')

@section('content')
    <main>
        <div class="wrap">
            <div class="account-layout">
                @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('reviews_my_reviews')])

                <section class="account-panel" aria-label="{{ __('reviews_my_reviews') }}">
                    <h1 class="account-panel-title">{{ __('reviews_my_reviews') }}</h1>

                    @if(session('success'))
                        <div class="form-alert form-alert-success" role="status">{{ session('success') }}</div>
                    @endif

                    @if($reviews->isEmpty())
                        <p class="account-card-empty">{{ __('reviews_you_haven_t_written_any_reviews_yet') }}</p>
                    @else
                        <ul class="review-list">
                            @foreach($reviews as $review)
                                @php
                                    $product = $review->product;
                                    $image = $product?->images?->first();
                                    $rating = max(0, min(5, (int) $review->rating));
                                    $productUrl = $product ? route('product', $product->slug) : null;
                                @endphp

                                <li class="review-entry">
                                    <div class="review-entry__head">
                                        <time class="review-entry__date" datetime="{{ $review->created_at->toDateString() }}">
                                            {{ $review->created_at->locale(app()->getLocale())->translatedFormat('j F Y') }}
                                        </time>

                                        <div class="review-entry__actions">
                                            @if($review->active)
                                                <span class="review-entry__status review-entry__status--approved">{{ __('reviews_approved') }}</span>
                                            @else
                                                <span class="review-entry__status review-entry__status--pending">{{ __('reviews_pending_approval') }}</span>
                                                <form method="POST" action="{{ route('profile.reviews.destroy', $review) }}"
                                                      onsubmit="return confirm('{{ __('reviews_confirm_delete') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="review-entry__delete">{{ __('reviews_delete') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

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
