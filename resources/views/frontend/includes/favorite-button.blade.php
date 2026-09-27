<button type="button" class="icon-btn fav-btn{{ !empty($selected) ? ' active is-favorite' : '' }}" data-product-id="{{ $product->id }}" aria-label="{{ !empty($selected) ? __('wishlist_remove_from_favorites') : __('common_favorite') }}" aria-pressed="{{ !empty($selected) ? 'true' : 'false' }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
</button>
