@extends('frontend.layouts.app')

@section('page-css')
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/account.css') }}">
    <link rel="stylesheet" href="{{ asset_v('frontend/css/pages/wishlist.css') }}">
@endsection

@section('content')
    @php
        $locale = app()->getLocale();
    @endphp
    <main>
        <div class="account-layout">
            @include('frontend.partials.cabinet-sidebar', ['pageTitle' => __('wishlist_my_favorites')])

            <div class="account-panel account-panel--flush">
                <div class="wishlist-head">
                    <h1 class="account-panel-title">{{ __('wishlist_my_favorites') }}</h1>
                    @if($products->isNotEmpty())
                        <span class="wishlist-count">{{ $products->count() }}</span>
                    @endif
                </div>

                @if($products->isEmpty())
                    <p class="account-card-empty">{{ __('wishlist_you_have_no_favorites_yet') }}</p>
                @else
                    <div class="grid wishlist-grid"
                         data-added-label="{{ __('wishlist_added') }}"
                         data-added-message="{{ __('wishlist_added_message') }}">
                        @foreach($products as $product)
                            @php
                                $image = $product->images->first();
                                $variants = $product->variants->where('active', 1)->sortBy('price')->values();
                                $firstVariant = $variants->first();
                                $sizeName = fn ($variant) => $variant->size?->{'name_' . $locale} ?: $variant->size?->name_az;
                            @endphp

                            <article class="card wishlist-card product-item" data-product-id="{{ $product->id }}">
                                <div class="thumb">
                                    <div class="thumb-actions">
                                        @include('frontend.includes.favorite-button', ['product' => $product, 'selected' => true])
                                    </div>
                                    <a href="{{ route('product', $product->slug) }}">
                                        @if($image)
                                            <img class="product-main-image" src="{{ asset('frontend/uploads/products/' . $image->image) }}" alt="{{ $product->brand?->name }} {{ $product->name }}" loading="lazy">
                                        @endif
                                    </a>
                                </div>

                                <p class="brandname">{{ $product->brand?->name }}</p>
                                <p class="pname"><a href="{{ route('product', $product->slug) }}">{{ $product->name }}</a></p>

                                @if($firstVariant)
                                    @if($variants->count() > 1)
                                        <div class="wishlist-sizes" role="group" aria-label="{{ __('wishlist_choose_size') }}">
                                            @foreach($variants as $variant)
                                                <button type="button"
                                                        class="wishlist-size {{ $loop->first ? 'is-active' : '' }}"
                                                        data-wishlist-size
                                                        data-variant-id="{{ $variant->id }}"
                                                        data-price="{{ $variant->price }}"
                                                        aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $sizeName($variant) }}</button>
                                            @endforeach
                                        </div>
                                    @elseif($sizeName($firstVariant))
                                        <p class="wishlist-size-single">{{ $sizeName($firstVariant) }}</p>
                                    @endif

                                    <div class="wishlist-buy">
                                        <span class="wishlist-price" data-wishlist-price>{{ number_format((float) $firstVariant->price, 2) }} ₼</span>
                                        <button type="button"
                                                class="wishlist-add"
                                                data-wishlist-add
                                                data-product-id="{{ $product->id }}"
                                                data-variant-id="{{ $firstVariant->id }}">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
                                            <span>{{ __('product_add_to_cart') }}</span>
                                        </button>
                                    </div>
                                @else
                                    <p class="wishlist-out">{{ __('wishlist_out_of_stock') }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </main>
@endsection

@section('page-scripts')
    <script>
        (() => {
            const grid = document.querySelector('.wishlist-grid');
            if (!grid) return;

            const CART_KEY = 'parfumshop_cart';
            const addedLabel = grid.dataset.addedLabel;
            const addedMessage = grid.dataset.addedMessage;

            const readCart = () => {
                try {
                    const cart = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
                    return Array.isArray(cart) ? cart : [];
                } catch { return []; }
            };

            const updateHeaderCount = cart => {
                const badge = document.getElementById('headerCartCount');
                if (!badge) return;
                const count = cart.reduce((sum, item) => sum + (Number(item.quantity) || 1), 0);
                badge.textContent = String(count);
                badge.classList.toggle('is-empty', count === 0);
            };

            grid.addEventListener('click', event => {
                // Ölçü seçimi
                const size = event.target.closest('[data-wishlist-size]');
                if (size) {
                    const card = size.closest('.wishlist-card');
                    card.querySelectorAll('[data-wishlist-size]').forEach(btn => {
                        const active = btn === size;
                        btn.classList.toggle('is-active', active);
                        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });
                    card.querySelector('[data-wishlist-price]').textContent = Number(size.dataset.price).toFixed(2) + ' ₼';
                    card.querySelector('[data-wishlist-add]').dataset.variantId = size.dataset.variantId;
                    return;
                }

                // Səbətə əlavə
                const add = event.target.closest('[data-wishlist-add]');
                if (!add || add.classList.contains('is-added')) return;

                const variantId = Number(add.dataset.variantId);
                const productId = Number(add.dataset.productId);
                const cart = readCart();
                const existing = cart.find(item => Number(item.variant_id) === variantId);

                if (existing) existing.quantity = (Number(existing.quantity) || 1) + 1;
                else cart.push({ product_id: productId, variant_id: variantId, quantity: 1 });

                localStorage.setItem(CART_KEY, JSON.stringify(cart));
                window.dispatchEvent(new CustomEvent('parfumshop:cart-updated', { detail: cart }));
                updateHeaderCount(cart);

                if (window.jQuery && typeof window.jQuery.notify === 'function') {
                    window.jQuery.notify(addedMessage, { className: 'success', position: 'top right', autoHideDelay: 2500 });
                }

                const label = add.querySelector('span');
                const original = label.textContent;
                add.classList.add('is-added');
                label.textContent = addedLabel;
                setTimeout(() => {
                    add.classList.remove('is-added');
                    label.textContent = original;
                }, 1800);
            });
        })();
    </script>
@endsection
