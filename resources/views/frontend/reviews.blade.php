@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/reviews.css') }}">
@endsection

@section('content')
    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('reviews_my_reviews')])

            <section class="account-panel account-panel--flush" aria-label="{{ __('reviews_my_reviews') }}">
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
                                $imageUrl = $image ? asset('frontend/uploads/products/' . $image->image) : null;
                            @endphp

                            <li @class(['review-card', 'is-pending' => ! $review->active])>
                                <div class="review-card__top">
                                    @if($productUrl)
                                        <a class="review-card__image" href="{{ $productUrl }}" tabindex="-1" aria-hidden="true">
                                            @if($imageUrl)<img src="{{ $imageUrl }}" alt="" loading="lazy">@endif
                                        </a>
                                    @else
                                        <div class="review-card__image">
                                            @if($imageUrl)<img src="{{ $imageUrl }}" alt="" loading="lazy">@endif
                                        </div>
                                    @endif

                                    <div class="review-card__product">
                                        @if($productUrl)
                                            <a class="review-card__name" href="{{ $productUrl }}">{{ $product->name }}</a>
                                        @else
                                            <span class="review-card__name">{{ $product?->name ?? '—' }}</span>
                                        @endif

                                        @if($product?->brand)
                                            <span class="review-card__brand">{{ $product->brand->name }}</span>
                                        @endif

                                        <div class="review-card__meta">
                                            <span class="review-card__stars" role="img" aria-label="{{ $rating }} / 5">
                                                @for($star = 1; $star <= 5; $star++)
                                                    <svg class="review-card__star {{ $star <= $rating ? 'is-filled' : '' }}" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M12 2.75l2.85 5.77 6.37.93-4.61 4.49 1.09 6.34L12 17.28l-5.7 3 1.09-6.34-4.61-4.49 6.37-.93L12 2.75z"/>
                                                    </svg>
                                                @endfor
                                            </span>
                                            <time datetime="{{ $review->created_at->toDateString() }}">
                                                {{ $review->created_at->locale(app()->getLocale())->translatedFormat('j F Y') }}
                                            </time>
                                        </div>
                                    </div>

                                    <span @class([
                                        'review-card__status',
                                        'review-card__status--approved' => $review->active,
                                        'review-card__status--pending' => ! $review->active,
                                    ])>
                                        {{ $review->active ? __('reviews_approved') : __('reviews_pending_approval') }}
                                    </span>
                                </div>

                                @if(filled($review->comment))
                                    <p class="review-card__comment">{{ $review->comment }}</p>
                                @endif

                                @unless($review->active)
                                    <div class="review-card__footer">
                                        <span>{{ __('reviews_pending_note') }}</span>
                                        <form method="POST" action="{{ route('profile.reviews.destroy', $review) }}"
                                              onsubmit="return confirm(@js(__('reviews_confirm_delete')))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="review-card__delete">{{ __('reviews_delete') }}</button>
                                        </form>
                                    </div>
                                @endunless
                            </li>
                        @endforeach
                    </ul>

                    @if($reviews->hasPages())
                        <div class="main-products__pagination">{{ $reviews->links() }}</div>
                    @endif
                @endif
            </section>
        </div>
    </main>
@endsection
