@extends('frontend.layouts.app')

@section('page-css')
<link rel="stylesheet" href="{{ asset('frontend/css/pages/wishlist.css') }}">
@endsection

@section('content')
    <main>
        <div class="wrap">
            <section class="wishlist-page wishlist-page--guest">
                <div class="section-head">
                    <h1>{{ __('wishlist_my_favorites') }}</h1>
                </div>

                <div id="guestWishlist" class="grid wishlist-grid"></div>
                <p id="guestWishlistEmpty" class="account-card-empty" style="display:none">{{ __('wishlist_you_have_no_favorites_yet') }}</p>
            </section>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', async function () {
            let ids = [];
            try { ids = [...new Set(JSON.parse(localStorage.getItem('parfumshop_favorites') || '[]').map(Number).filter(Boolean))]; } catch(e) {}
            const grid = document.getElementById('guestWishlist'), empty = document.getElementById('guestWishlistEmpty');
            if (!ids.length) { empty.style.display = 'block'; return; }
            try {
                const res = await fetch('{{ route('wishlist.products') }}?ids=' + encodeURIComponent(ids.join(',')), {headers:{'Accept':'application/json'}});
                const data = await res.json();
                const products = data.products || data || [];
                grid.innerHTML = products.map(p => {
                    const image = p.image || '';
                    const url = p.url || '#';
                    const brand = p.brand || '';
                    return '<article class="card wishlist-card product-item" data-product-id="'+p.id+'"><div class="thumb"><div class="thumb-actions"><button type="button" class="icon-btn fav-btn active is-favorite" data-product-id="'+p.id+'" aria-label="{{ __('wishlist_remove_from_favorites') }}" aria-pressed="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg></button></div><a href="'+url+'">'+(image?'<img class="product-main-image" src="'+image+'" alt="">':'')+'</a></div><p class="brandname">'+brand+'</p><p class="pname"><a href="'+url+'">'+(p.name||'')+'</a></p></article>';
                }).join('');
                if (!products.length) empty.style.display = 'block';
                if (window.paintFavorites) window.paintFavorites(ids);
            } catch(e) { empty.style.display = 'block'; }
        });
    </script>
@endsection
