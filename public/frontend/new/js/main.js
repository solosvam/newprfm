(() => {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const loggedIn = document.body.dataset.auth === '1';
    const favoritesKey = 'parfumshop_favorites';

    function readArray(key) {
        try {
            const value = JSON.parse(localStorage.getItem(key) || '[]');
            return Array.isArray(value) ? value : [];
        } catch { return []; }
    }

    function updateCounts() {
        const cart = readArray('parfumshop_cart');
        const cartCount = cart.reduce((sum, item) => sum + (Number(item.quantity) || 1), 0);
        const cartBadge = document.getElementById('headerCartCount');
        if (cartBadge) {
            cartBadge.textContent = String(cartCount);
            cartBadge.classList.toggle('is-empty', cartCount === 0);
        }
        const wishlistBadge = document.getElementById('headerWishlistCount');
        if (wishlistBadge) {
            const count = readArray(favoritesKey).length;
            wishlistBadge.textContent = String(count);
            wishlistBadge.classList.toggle('is-empty', count === 0);
        }
    }

    async function syncFavorites() {
        if (!loggedIn) {
            const ids = readArray(favoritesKey).map(Number);
            document.querySelectorAll('.fav-btn').forEach(btn => btn.classList.toggle('active', ids.includes(Number(btn.dataset.productId))));
            updateCounts();
            return;
        }
        try {
            const response = await fetch('/favorites/ids', {headers: {'Accept':'application/json'}});
            if (!response.ok) return;
            const ids = (await response.json()).ids.map(Number);
            localStorage.setItem(favoritesKey, JSON.stringify(ids));
            document.querySelectorAll('.fav-btn').forEach(btn => btn.classList.toggle('active', ids.includes(Number(btn.dataset.productId))));
            updateCounts();
        } catch (error) { console.warn('Seçilmişlər yüklənmədi', error); }
    }

    document.addEventListener('click', async event => {
        const fav = event.target.closest('.fav-btn');
        if (fav) {
            event.preventDefault();
            const id = Number(fav.dataset.productId);
            const ids = readArray(favoritesKey).map(Number);
            const active = ids.includes(id);
            fav.classList.toggle('active', !active);
            localStorage.setItem(favoritesKey, JSON.stringify(active ? ids.filter(v => v !== id) : [...ids, id]));
            updateCounts();
            if (loggedIn) {
                try {
                    const response = await fetch('/favorites/' + id, {
                        method: active ? 'DELETE' : 'POST',
                        headers: {'X-CSRF-TOKEN': csrf || '', 'Accept':'application/json'}
                    });
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                } catch {
                    localStorage.setItem(favoritesKey, JSON.stringify(ids));
                    fav.classList.toggle('active', active);
                    updateCounts();
                }
            }
            return;
        }

        const share = event.target.closest('.share-btn');
        if (share) {
            event.preventDefault();
            const url = share.dataset.url || window.location.href;
            if (navigator.share) {
                try { await navigator.share({title: share.dataset.title || document.title, url}); } catch {}
            } else {
                try { await navigator.clipboard.writeText(url); share.setAttribute('title', 'Kopyalandı'); }
                catch { window.prompt('Linki kopyalayın:', url); }
            }
            return;
        }

        const thumbnail = event.target.closest('.thumbs .t img');
        if (thumbnail) {
            const main = document.querySelector('.main-image > img');
            if (main) { main.src = thumbnail.src; main.alt = thumbnail.alt || main.alt; }
            return;
        }

        const size = event.target.closest('.size-pill');
        if (size) {
            document.querySelectorAll('.size-pill').forEach(el => el.classList.remove('active-size-amount'));
            size.classList.add('active-size-amount');
            const price = document.querySelector('.price-row');
            if (price) price.textContent = Number(size.dataset.price).toFixed(2) + ' ₼';
            return;
        }

        const qtyButton = event.target.closest('[data-qty-action]');
        if (qtyButton) {
            const display = document.querySelector('[data-qty-value]');
            if (display) display.textContent = String(Math.max(1, Number(display.textContent || 1) + (qtyButton.dataset.qtyAction === 'plus' ? 1 : -1)));
            return;
        }

        const add = event.target.closest('[data-add-to-cart]');
        if (add) {
            const selected = document.querySelector('.size-pill.active-size-amount');
            if (!selected) return;
            const cart = readArray('parfumshop_cart');
            const id = Number(selected.dataset.variantId);
            const quantity = Number(document.querySelector('[data-qty-value]')?.textContent || 1);
            const item = cart.find(v => Number(v.variant_id) === id);
            if (item) item.quantity = (Number(item.quantity) || 1) + quantity;
            else cart.push({variant_id:id, quantity});
            localStorage.setItem('parfumshop_cart', JSON.stringify(cart));
            window.dispatchEvent(new CustomEvent('parfumshop:cart-updated', {detail:cart}));
            updateCounts();
            add.textContent = 'Səbətə əlavə edildi ✓';
            setTimeout(() => add.textContent = 'Səbətə əlavə et', 1600);
            return;
        }

        const card = event.target.closest('.card[data-href]');
        if (card && !event.target.closest('a, button, input, select')) window.location.href = card.dataset.href;
    });

    document.addEventListener('auxclick', event => {
        const card = event.target.closest('.card[data-href]');
        if (event.button === 1 && card && !event.target.closest('button, a')) window.open(card.dataset.href, '_blank', 'noopener');
    });
    window.addEventListener('storage', updateCounts);
    window.addEventListener('parfumshop:cart-updated', updateCounts);
    updateCounts();
    syncFavorites();
})();
