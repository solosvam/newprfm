@extends('frontend.layout')
@section('content')
<main>
    <div class="container">
        <section class="wishlist-page wishlist-page--guest">
            <h2 class="wishlist-page__title">Bəyəndiyim ətirlər</h2>
            <div id="guestWishlist" class="wishlist-grid"></div>
            <div id="guestWishlistEmpty" class="wishlist-empty" style="display:none">Hələ bəyəndiyiniz ətir yoxdur.</div>
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
        const res = await fetch('{{ route('cart.products') }}?ids=' + encodeURIComponent(ids.join(',')), {headers:{'Accept':'application/json'}});
        const data = await res.json();
        const products = data.products || data || [];
        grid.innerHTML = products.map(p => {
            const image = p.image_url || p.image || '';
            const url = p.url || ('/' + (p.slug || ''));
            const brand = p.brand_name || (p.brand && p.brand.name) || '';
            return '<article class="wishlist-card product-item" data-product-id="'+p.id+'"><div class="wishlist-card__image"><button type="button" class="favorite-toggle is-favorite" data-product-id="'+p.id+'" aria-pressed="true"><img src="{{ asset('frontend/images/product-card-wishlist.svg') }}" alt=""></button><a href="'+url+'">'+(image?'<img class="product-main-image" src="'+image+'" alt="">':'')+'</a></div><div class="wishlist-card__info"><h3><a href="'+url+'">'+(p.name||'')+'</a></h3><p>'+brand+'</p></div></article>';
        }).join('');
        if (!products.length) empty.style.display = 'block';
        if (window.paintFavorites) window.paintFavorites(ids);
    } catch(e) { empty.style.display = 'block'; }
});
</script>
@endsection
