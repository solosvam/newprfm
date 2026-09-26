@php
    $approvedReviews = $product->reviews;
    $average = (float) ($ratingAverage ?? 0);
    $counts = $ratingCounts ?? collect();
    $maxCount = max(1, (int) $counts->max());
@endphp

<h2>{{ __('product_reviews') }} — {{ $product->name }}</h2>

@if(session('review_success'))
    <p class="review-notice" role="status">{{ session('review_success') }}</p>
@endif

<div class="review-summary">
    <div class="review-score">
        <span class="score-num">{{ number_format($average, 1) }}</span>
        <span class="score-stars" aria-label="{{ $average }} / 5">
            @for($star = 1; $star <= 5; $star++)
                <span>{{ $star <= round($average) ? '★' : '☆' }}</span>
            @endfor
        </span>
        <small>{{ $approvedReviews->count() }} rəy</small>
    </div>
    <div class="review-bars">
        @for($rating = 5; $rating >= 1; $rating--)
            @php $count = (int) ($counts[$rating] ?? 0); @endphp
            <div class="review-bar-row">
                <span>{{ $rating }} ★</span>
                <div class="bar-track"><div class="bar-fill" style="width: {{ $count / $maxCount * 100 }}%"></div></div>
                <span class="bar-count">{{ $count }}</span>
            </div>
        @endfor
    </div>
    <button type="button" class="btn btn-outline review-write-btn" data-review-open>{{ __('product_write_a_review') }}</button>
</div>

<div class="review-accordion open" data-accordion>
    <button type="button" class="accordion-toggle" data-accordion-toggle aria-expanded="true">
        <span>Son rəylər</span><span class="chevron">⌃</span>
    </button>
    <div class="accordion-body" data-accordion-body>
        @forelse($approvedReviews as $review)
            <article class="review-item">
                <p class="review-author">{{ $review->customer?->name ?? 'Müştəri' }}</p>
                <p class="review-item-stars" aria-label="{{ $review->rating }} / 5">
                    @for($star = 1; $star <= 5; $star++)
                        {{ $star <= $review->rating ? '★' : '☆' }}
                    @endfor
                </p>
                <p class="review-text">{{ $review->comment }}</p>
                @if($review->created_at)
                    <small>{{ $review->created_at->format('d.m.Y') }}</small>
                @endif
            </article>
        @empty
            <p class="review-empty">Bu məhsula hələ təsdiqlənmiş rəy yazılmayıb.</p>
        @endforelse
    </div>
</div>

<dialog class="review-dialog" data-review-modal aria-label="{{ __('product_write_a_review') }}">
    <div class="review-dialog-head">
        <h2>{{ __('product_write_a_review') }}</h2>
        <button type="button" data-review-close aria-label="Bağla">×</button>
    </div>
    @auth
        <form method="POST" action="{{ route('product.review', $product->id) }}" class="review-form">
            @csrf
            @if($errors->has('rating') || $errors->has('comment'))
                <div data-review-errors class="review-notice review-notice--error">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            <fieldset class="review-rating">
                <legend>Qiymətləndirmə</legend>
                @for($star = 5; $star >= 1; $star--)
                    <input type="radio" name="rating" id="new-rating-{{ $star }}" value="{{ $star }}" @checked(old('rating') == $star) required>
                    <label for="new-rating-{{ $star }}" title="{{ $star }} / 5">★</label>
                @endfor
            </fieldset>
            <label for="new-review-comment">Rəyiniz</label>
            <textarea id="new-review-comment" name="comment" rows="5" minlength="3" maxlength="2000" required>{{ old('comment') }}</textarea>
            <p class="review-hint">Rəyiniz administrator təsdiq etdikdən sonra görünəcək.</p>
            <button type="submit" class="btn btn-dark">{{ __('product_submit_review') }}</button>
        </form>
    @else
        <p>{{ __('product_sign_in_to_leave_a_review') }}</p>
        <a class="btn btn-dark" href="{{ route('front.login', ['redirect' => route('newproduct', $product->slug) . '#reviews']) }}">{{ __('auth_sign_in') }}</a>
    @endauth
</dialog>
