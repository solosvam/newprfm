(() => {
    'use strict';

    const root = document.getElementById('cartPage');
    if (!root) return;

    const itemsElement = document.getElementById('cartItems');
    const emptyElement = document.getElementById('cartEmpty');
    const layoutElement = root.querySelector('.cart-layout');
    const summaryElement = document.getElementById('cartSummaryLines');
    const subtotalElement = document.getElementById('cartSubtotal');
    const totalElement = document.getElementById('cartTotal');
    const productUrl = root.dataset.productsUrl;
    const removeLabel = root.dataset.removeLabel;
    let renderVersion = 0;

    function getCart() {
        try {
            const cart = JSON.parse(localStorage.getItem('parfumshop_cart') || '[]');
            return Array.isArray(cart) ? cart : [];
        } catch {
            return [];
        }
    }

    function saveCart(cart) {
        localStorage.setItem('parfumshop_cart', JSON.stringify(cart));
        window.dispatchEvent(new CustomEvent('parfumshop:cart-updated', { detail: cart }));
        renderCart();
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    function money(value) {
        return Number(value).toFixed(2) + ' ₼';
    }

    function addItem(item, product) {
        const quantity = Math.max(1, Number(item.quantity) || 1);
        const price = Number(product.price) || 0;
        const row = element('div', 'cart-row');
        const image = element('div', 'cart-row__image');

        if (product.image) {
            const img = element('img');
            img.src = product.image;
            img.alt = product.name || '';
            image.appendChild(img);
        }

        const info = element('div', 'cart-row__info');
        info.append(
            element('div', 'cart-row__name', product.name),
            element('div', 'cart-row__brand', product.brand || ''),
            element('div', 'cart-row__meta', [product.gender, product.type].filter(Boolean).join(' | ')),
            element('div', 'cart-row__price', money(price))
        );

        const bottom = element('div', 'cart-row__bottom');
        bottom.appendChild(element('span', 'cart-row__size', product.size || ''));

        const quantityControl = element('div', 'cart-row__qty');
        const minus = element('button', '', '−');
        const plus = element('button', '', '+');
        for (const [button, action] of [[minus, 'minus'], [plus, 'plus']]) {
            button.type = 'button';
            button.dataset.action = action;
            button.dataset.id = String(item.variant_id);
        }
        quantityControl.append(minus, element('span', '', quantity), plus);

        const remove = element('button', 'cart-remove', removeLabel);
        remove.type = 'button';
        remove.dataset.action = 'remove';
        remove.dataset.id = String(item.variant_id);
        bottom.append(quantityControl, remove);
        info.appendChild(bottom);
        row.append(image, info);
        itemsElement.appendChild(row);

        const line = element('div', 'cart-summary__line');
        line.append(element('span', '', product.name), element('strong', '', money(price * quantity)));
        summaryElement.appendChild(line);
        return price * quantity;
    }

    async function renderCart() {
        const version = ++renderVersion;
        const cart = getCart();
        if (!cart.length) {
            layoutElement.style.display = 'none';
            emptyElement.style.display = 'block';
            itemsElement.replaceChildren();
            summaryElement.replaceChildren();
            subtotalElement.textContent = money(0);
            totalElement.textContent = money(0);
            return;
        }

        layoutElement.style.display = 'grid';
        emptyElement.style.display = 'none';

        try {
            const url = new URL(productUrl, window.location.origin);
            url.searchParams.set('variants', cart.map(item => item.variant_id).join(','));
            const response = await fetch(url);
            if (!response.ok) throw new Error('Cart products request failed');
            const products = await response.json();
            if (version !== renderVersion) return;

            const productMap = new Map(products.map(product => [Number(product.variant_id), product]));
            itemsElement.replaceChildren();
            summaryElement.replaceChildren();

            let total = 0;
            for (const item of cart) {
                const product = productMap.get(Number(item.variant_id));
                if (product) total += addItem(item, product);
            }

            subtotalElement.textContent = money(total);
            totalElement.textContent = money(total);
        } catch (error) {
            if (version === renderVersion) console.error('Səbət məlumatları yüklənmədi:', error);
        }
    }

    root.addEventListener('click', event => {
        const button = event.target.closest('button[data-action]');
        if (!button || !root.contains(button)) return;

        let cart = getCart();
        const id = Number(button.dataset.id);
        const item = cart.find(entry => Number(entry.variant_id) === id);
        if (!item) return;

        switch (button.dataset.action) {
            case 'plus':
                item.quantity = Math.max(1, Number(item.quantity) || 1) + 1;
                break;
            case 'minus':
                item.quantity = Math.max(1, (Number(item.quantity) || 1) - 1);
                break;
            case 'remove':
                cart = cart.filter(entry => Number(entry.variant_id) !== id);
                break;
            default:
                return;
        }

        saveCart(cart);
    });

    window.addEventListener('storage', event => {
        if (event.key === 'parfumshop_cart') renderCart();
    });

    renderCart();
})();
