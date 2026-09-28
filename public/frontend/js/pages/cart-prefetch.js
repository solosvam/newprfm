// cart.blade.php-də #cartPage-dən dərhal sonra, defer-siz yüklənir.
// Skelet sətirləri göstərir və məhsul sorğusunu erkən başladır;
// nəticəni cart.js window.__cartPrefetch-dən götürür.
(() => {
    const root = document.getElementById('cartPage');
    const items = document.getElementById('cartItems');
    let cart = [];
    try { cart = JSON.parse(localStorage.getItem('parfumshop_cart') || '[]'); } catch {}
    if (!Array.isArray(cart) || !cart.length) {
        root.classList.remove('is-loading');
        root.classList.add('is-empty');
        return;
    }

    // Skelet sətirlər: layout yerini dərhal tutur
    const row = '<div class="cart-row cart-row--skeleton" aria-hidden="true">'
        + '<div class="cart-row__image sk"></div>'
        + '<div class="cart-row__body"><div class="cart-row__head"><div class="cart-row__info">'
        + '<div class="sk sk--line" style="width:55%"></div><div class="sk sk--line sk--sm" style="width:35%"></div>'
        + '</div><div class="sk sk--line" style="width:80px"></div></div>'
        + '<div class="cart-row__actions"><div class="sk sk--pill"></div></div></div></div>';
    items.innerHTML = row.repeat(Math.min(cart.length, 6));

    // Məhsul sorğusunu skriptləri gözləmədən başladırıq
    const url = new URL(root.dataset.productsUrl, location.origin);
    url.searchParams.set('variants', cart.map(i => i.variant_id).join(','));
    window.__cartPrefetch = {
        key: url.search,
        promise: fetch(url, { headers: { Accept: 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject(new Error('Cart products request failed'))),
    };
})();
