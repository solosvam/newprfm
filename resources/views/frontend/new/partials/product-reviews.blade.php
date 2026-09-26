@php
    $avg = $product->reviews_avg ?? 0;
    $breakdown = $product->rating_breakdown ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $max = max($breakdown) ?: 1;
@endphp

<h2>Rəylər {{ $product->name }}</h2>

<div class="review-summary">
    <div class="review-score">
        <span class="score-num">{{ number_format($avg, 1) }}</span>
        <span class="score-stars">
            @for ($i = 1; $i <= 5; $i++)
                <svg viewBox="0 0 24 24" width="16" height="16" fill="{{ $i <= round($avg) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5"><path d="M12 3l2.7 5.9 6.3.6-4.8 4.3 1.4 6.2L12 17l-5.6 3 1.4-6.2-4.8-4.3 6.3-.6L12 3Z"/></svg>
            @endfor
        </span>
    </div>

    <div class="review-bars">
        @for ($i = 5; $i >= 1; $i--)
            <div class="review-bar-row">
                <div class="bar-track"><div class="bar-fill" style="width: {{ ($breakdown[$i] / $max) * 100 }}%"></div></div>
                <span class="bar-count">{{ $breakdown[$i] }}</span>
                <span class="bar-stars">
                    @for ($s = 1; $s <= 5; $s++)
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="{{ $s <= $i ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5"><path d="M12 3l2.7 5.9 6.3.6-4.8 4.3 1.4 6.2L12 17l-5.6 3 1.4-6.2-4.8-4.3 6.3-.6L12 3Z"/></svg>
                    @endfor
                </span>
            </div>
        @endfor
    </div>

    <button type="button" class="btn-outline review-write-btn">Rəy yaz</button>
</div>

<div class="review-accordion" data-accordion>
    <button type="button" class="accordion-toggle" data-accordion-toggle>
        <span>Son rəylər</span>
        <svg class="chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"/></svg>
    </button>

    <div class="accordion-body" data-accordion-body>
        @forelse ($product->reviews ?? [] as $review)
            <div class="review-item">
                <p class="review-author">{{ $review->user_name }}</p>
                <p class="review-text">{{ $review->comment }}</p>
            </div>
        @empty
            <p class="review-empty">Bu məhsula hələ rəy yazılmayıb.</p>
        @endforelse
    </div>
</div>
