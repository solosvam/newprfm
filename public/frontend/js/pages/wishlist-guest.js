(() => {
    'use strict';

    const grid = document.getElementById('guestWishlist');
    if (!grid) return;

    const empty = document.getElementById('guestWishlistEmpty');
    const countEl = document.getElementById('guestWishlistCount');
    const t = JSON.parse(grid.dataset.i18n || '{}');

    const HEART = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>';

    const formatter = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = value => formatter.format(Number(value) || 0).replace(/,/g, '\u00A0') + '\u00A0₼';

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    function link(href, child) {
        const a = el('a');
        a.href = href || '#';
        if (typeof child === 'string') a.textContent = child;
        else if (child) a.appendChild(child);
        return a;
    }

    function readIds() {
        try {
            const raw = JSON.parse(localStorage.getItem('parfumshop_favorites') || '[]');
            return [...new Set(raw.map(Number).filter(Boolean))];
        } catch {
            return [];
        }
    }

    function showEmpty() {
        grid.replaceChildren();
        empty.hidden = false;
        countEl.hidden = true;
    }

    function buildCard(p) {
        const variants = Array.isArray(p.variants) ? p.variants : [];
        const first = variants[0];

        const card = el('article', 'card wishlist-card product-item');
        card.dataset.productId = p.id;

        // Şəkil + ürək
        const thumb = el('div', 'thumb');
        const actions = el('div', 'thumb-actions');
        const fav = el('button', 'icon-btn fav-btn active is-favorite');
        fav.type = 'button';
        fav.dataset.productId = p.id;
        fav.setAttribute('aria-label', t.remove || '');
        fav.setAttribute('aria-pressed', 'true');
        fav.innerHTML = HEART;
        actions.appendChild(fav);

        let img = null;
        if (p.image) {
            img = el('img', 'product-main-image');
            img.src = p.image;
            img.alt = [p.brand, p.name].filter(Boolean).join(' ');
            img.loading = 'lazy';
        }
        thumb.append(actions, link(p.url, img));

        // Brend + ad
        const name = el('p', 'pname');
        name.appendChild(link(p.url, p.name || ''));
        card.append(thumb, el('p', 'brandname', p.brand || ''), name);

        if (!first) {
            card.appendChild(el('p', 'wishlist-out', t.outOfStock));
            return card;
        }

        // Ölçü
        const variantBox = el('div', 'wishlist-variant');
        if (variants.length > 1) {
            const select = el('select', 'wishlist-select');
            select.dataset.wishlistSize = '';
            select.setAttribute('aria-label', t.chooseSize || '');
            variants.forEach(v => {
                const option = el('option', '', v.size || '');
                option.value = v.id;
                option.dataset.price = v.price;
                select.appendChild(option);
            });
            variantBox.appendChild(select);
        } else {
            variantBox.appendChild(el('span', 'wishlist-select is-single', first.size || '—'));
        }

        // Qiymət + düymə
        const buy = el('div', 'wishlist-buy');
        const price = el('span', 'wishlist-price', money(first.price));
        price.dataset.wishlistPrice = '';

        const add = el('button', 'wishlist-add');
        add.type = 'button';
        add.dataset.wishlistAdd = '';
        add.dataset.productId = p.id;
        add.dataset.variantId = first.id;
        add.appendChild(el('span', '', t.addToCart));

        buy.append(price, add);
        card.append(variantBox, buy);
        return card;
    }

    async function render() {
        const ids = readIds();
        if (!ids.length) return showEmpty();

        try {
            const url = new URL(grid.dataset.productsUrl, window.location.origin);
            url.searchParams.set('ids', ids.join(','));
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Wishlist request failed');

            const data = await response.json();
            const products = Array.isArray(data.products) ? data.products : (Array.isArray(data) ? data : []);
            if (!products.length) return showEmpty();

            const fragment = document.createDocumentFragment();
            products.forEach(p => fragment.appendChild(buildCard(p)));
            grid.replaceChildren(fragment);

            empty.hidden = true;
            countEl.textContent = String(products.length);
            countEl.hidden = false;

            if (typeof window.paintFavorites === 'function') window.paintFavorites(ids);
        } catch (error) {
            console.error('Sevimlilər yüklənmədi:', error);
            showEmpty();
        }
    }

    render();
})();
