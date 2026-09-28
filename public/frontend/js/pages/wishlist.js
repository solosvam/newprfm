(() => {
    'use strict';

    const grid = document.querySelector('.wishlist-grid');
    if (!grid) return;

    const CART_KEY = 'parfumshop_cart';
    const addedLabel = grid.dataset.addedLabel;
    const addedMessage = grid.dataset.addedMessage;

    const formatter = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = value => formatter.format(Number(value) || 0).replace(/,/g, '\u00A0') + '\u00A0₼';

    function readCart() {
        try {
            const cart = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
            return Array.isArray(cart) ? cart : [];
        } catch {
            return [];
        }
    }

    function updateHeaderCount(cart) {
        const badge = document.getElementById('headerCartCount');
        if (!badge) return;
        const count = cart.reduce((sum, item) => sum + (Number(item.quantity) || 1), 0);
        badge.textContent = String(count);
        badge.classList.toggle('is-empty', count === 0);
    }

    function notify(message) {
        if (message && window.jQuery && typeof window.jQuery.notify === 'function') {
            window.jQuery.notify(message, { className: 'success', position: 'top right', autoHideDelay: 2500 });
        }
    }

    /* ---------- Ölçü seçimi ---------- */
    grid.addEventListener('change', event => {
        const select = event.target.closest('[data-wishlist-size]');
        if (!select) return;

        const card = select.closest('.wishlist-card');
        const option = select.selectedOptions[0];
        if (!card || !option) return;

        card.querySelector('[data-wishlist-price]').textContent = money(option.dataset.price);
        card.querySelector('[data-wishlist-add]').dataset.variantId = select.value;
    });

    /* ---------- Səbətə əlavə ---------- */
    grid.addEventListener('click', event => {
        const button = event.target.closest('[data-wishlist-add]');
        if (!button || button.classList.contains('is-added')) return;

        const variantId = Number(button.dataset.variantId);
        const productId = Number(button.dataset.productId);
        if (!variantId) return;

        const cart = readCart();
        const existing = cart.find(item => Number(item.variant_id) === variantId);

        if (existing) {
            existing.quantity = (Number(existing.quantity) || 1) + 1;
        } else {
            cart.push({ product_id: productId, variant_id: variantId, quantity: 1 });
        }

        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        window.dispatchEvent(new CustomEvent('parfumshop:cart-updated', { detail: cart }));
        updateHeaderCount(cart);
        notify(addedMessage);

        // Qısa "Əlavə edildi ✓" vəziyyəti
        const label = button.querySelector('span') || button;
        const original = label.textContent;
        button.classList.add('is-added');
        label.textContent = addedLabel;

        setTimeout(() => {
            button.classList.remove('is-added');
            label.textContent = original;
        }, 1800);
    });
})();
