(() => {
    'use strict';

    const root = document.getElementById('cartPage');
    if (!root) return;

    const PRODUCT_CACHE_KEY = 'parfumshop_cart_products';
    const $ = id => document.getElementById(id);
    const els = {
        items: $('cartItems'),
        count: $('cartCount'),
        itemsLabel: $('cartItemsLabel'),
        subtotal: $('cartSubtotal'),
        discountRow: $('cartDiscountRow'),
        discount: $('cartDiscount'),
        discountCode: $('cartDiscountCode'),
        referralRow: $('cartReferralRow'),
        referral: $('cartReferral'),
        delivery: $('cartDelivery'),
        total: $('cartTotal'),
        mobileTotal: $('cartMobileTotal'),
        installment: $('cartInstallment'),
        installmentText: $('cartInstallmentText'),
        bonus: $('cartBonus'),
        bonusText: $('cartBonusText'),
        freeship: $('cartFreeship'),
        freeshipText: $('cartFreeshipText'),
        freeshipBar: $('cartFreeshipBar'),
        toast: $('cartToast'),
        toastText: $('cartToastText'),
        toastUndo: $('cartToastUndo'),
        mobilebar: $('cartMobilebar'),
        checkout: $('cartCheckout'),
    };

    const d = root.dataset;
    const config = {
        productsUrl: d.productsUrl,
        deliveryFee: Number(d.deliveryFee) || 0,
        freeFrom: Number(d.freeDeliveryFrom) || 0,
        bonusRate: Number(d.bonusRate) || 0,
        installmentMonths: parseInt(d.installmentMonths, 10) || 0,
        installmentMin: Number(d.installmentMin) || 0,
        installmentMarkup: Number(d.installmentMarkup) || 0,
    };
    const t = JSON.parse(d.i18n || '{}');

    const TRASH_ICON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>';

    const state = { subtotal: 0, count: 0 };
    let renderVersion = 0;
    let lastSignature = '';
    let undoEntry = null;
    let toastTimer = null;

    /* ---------- helpers ---------- */
    const formatter = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = value => formatter.format(Number(value) || 0).replace(/,/g, '\u00A0') + '\u00A0₼';
    const round2 = value => Math.round((value + Number.EPSILON) * 100) / 100;

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    const strong = text => el('strong', '', text);

    /** ":amount" kimi placeholder-ləri mətn və ya DOM node ilə əvəz edir */
    function fill(template, values) {
        const fragment = document.createDocumentFragment();
        String(template || '').split(/(:[a-z_]+)/i).forEach(part => {
            const key = part.startsWith(':') ? part.slice(1) : null;
            if (key && key in values) {
                const value = values[key];
                fragment.append(value instanceof Node ? value : String(value));
            } else if (part) {
                fragment.append(part);
            }
        });
        return fragment;
    }

    function getCart() {
        return window.parfumshopCart.get();
    }

    function cartError(error) {
        window.parfumshopNotify?.(error.message, 'error');
    }

    function actionButton(label, action, id, ariaLabel) {
        const button = el('button', '', label);
        button.type = 'button';
        button.dataset.action = action;
        button.dataset.id = id;
        button.setAttribute('aria-label', ariaLabel);
        return button;
    }

    /* ---------- product cache (sessionStorage) ---------- */
    function readProductCache() {
        try {
            const cache = JSON.parse(sessionStorage.getItem(PRODUCT_CACHE_KEY) || '{}');
            return cache && typeof cache === 'object' ? cache : {};
        } catch {
            return {};
        }
    }

    function writeProductCache(products) {
        try {
            const cache = readProductCache();
            products.forEach(p => { cache[Number(p.variant_id)] = p; });
            sessionStorage.setItem(PRODUCT_CACHE_KEY, JSON.stringify(cache));
        } catch { /* storage dolu və ya bloklanıb — keşsiz davam edirik */ }
    }

    async function fetchProducts(cart) {
        const url = new URL(config.productsUrl, window.location.origin);
        url.searchParams.set('variants', cart.map(item => item.variant_id).join(','));

        // Inline skriptin erkən başlatdığı sorğu eyni səbət üçündürsə, onu istifadə edirik (bir dəfə)
        const prefetch = window.__cartPrefetch;
        window.__cartPrefetch = null;
        if (prefetch && prefetch.key === url.search) return prefetch.promise;

        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Cart products request failed');
        return response.json();
    }

    /* ---------- row ---------- */
    function buildRow(item, product) {
        const quantity = Math.max(1, Number(item.quantity) || 1);
        const price = Number(product.price) || 0;
        const lineTotal = price * quantity;
        const id = String(item.variant_id);
        const isGiftCard = Boolean(product.is_gift_card);

        const row = el('article', 'cart-row');

        const image = el('div', 'cart-row__image');
        if (product.image) {
            const img = el('img');
            img.src = product.image;
            img.alt = product.name || '';
            img.loading = 'lazy';
            image.appendChild(img);
        }

        const name = el('h3', 'cart-row__name');
        if (product.url) {
            const link = el('a', '', product.name);
            link.href = product.url;
            name.appendChild(link);
        } else {
            name.textContent = product.name || '';
        }

        const meta = el('div', 'cart-row__meta');
        if (product.brand) meta.appendChild(el('span', 'cart-row__brand', product.brand));
        if (!isGiftCard) {
            [product.gender, product.type, product.size]
                .filter(Boolean)
                .forEach(value => meta.appendChild(el('span', '', value)));
        }

        const info = el('div', 'cart-row__info');
        info.append(name, meta);

        const priceBox = el('div', 'cart-row__price');
        priceBox.appendChild(el('div', 'cart-row__total', money(lineTotal)));
        if (quantity > 1) priceBox.appendChild(el('div', 'cart-row__unit', `${quantity} × ${money(price)}`));

        const head = el('div', 'cart-row__head');
        head.append(info, priceBox);

        const minus = actionButton('−', 'minus', id, t.decrease);
        minus.disabled = quantity <= 1;
        const plus = actionButton('+', 'plus', id, t.increase);

        const qty = el('div', 'cart-qty');
        qty.append(minus, el('span', '', quantity), plus);

        const remove = el('button', 'cart-remove');
        remove.type = 'button';
        remove.dataset.action = 'remove';
        remove.dataset.id = id;
        remove.setAttribute('aria-label', `${t.remove}: ${product.name || ''}`);
        remove.insertAdjacentHTML('afterbegin', TRASH_ICON);
        remove.appendChild(el('span', '', t.remove));

        const actions = el('div', 'cart-row__actions');
        actions.append(qty, remove);

        const body = el('div', 'cart-row__body');
        body.append(head, actions);

        row.append(image, body);
        return { row, lineTotal, quantity };
    }

    /* ---------- totals ---------- */
    function updateTotals() {
        const promo = window.ParfumPromo || {};
        const discount = Math.min(Number(promo.discount) || 0, state.subtotal);
        // Səbətdə ödəniş üsulu hələ seçilmir — hissə-hissə şərti checkout-da yoxlanır
        const referral = window.ParfumReferral ? window.ParfumReferral.discount(state.subtotal, discount, false) : 0;
        const goods = round2(state.subtotal - discount - referral);
        const reachedFree = config.freeFrom > 0 && goods >= config.freeFrom;
        const delivery = reachedFree ? 0 : config.deliveryFee;
        const total = round2(goods + delivery);

        els.count.textContent = state.count || '';
        els.itemsLabel.textContent = String(t.itemsCount || '').replace(':count', state.count);
        els.subtotal.textContent = money(state.subtotal);

        els.discountRow.hidden = discount <= 0;
        els.discount.textContent = '−' + money(discount);
        els.discountCode.textContent = promo.code || '';
        if (els.referralRow) {
            els.referralRow.hidden = referral <= 0;
            els.referral.textContent = '−' + money(referral);
        }

        els.delivery.textContent = delivery > 0 ? money(delivery) : t.free;
        els.delivery.classList.toggle('is-free', delivery === 0);

        els.total.textContent = money(total);
        els.mobileTotal.textContent = money(total);

        // Pulsuz çatdırılma proqresi
        const showFreeship = config.freeFrom > 0 && config.deliveryFee > 0 && state.count > 0;
        els.freeship.hidden = !showFreeship;
        if (showFreeship) {
            els.freeship.classList.toggle('is-done', reachedFree);
            els.freeshipText.replaceChildren(
                reachedFree
                    ? document.createTextNode(t.freeshipDone)
                    : fill(t.freeshipLeft, { amount: strong(money(config.freeFrom - goods)) })
            );
            els.freeshipBar.style.width = Math.min(100, (goods / config.freeFrom) * 100) + '%';
        }

        // Bonus (çatdırılmasız, endirimdən sonrakı məbləğdən)
        const bonus = round2(goods * config.bonusRate);
        els.bonus.hidden = bonus <= 0;
        els.bonusText.replaceChildren(fill(t.bonus, { amount: strong(money(bonus)) }));

        // Hissəli ödəniş
        const canInstall = config.installmentMonths > 0 && total > 0 && total >= config.installmentMin;
        els.installment.hidden = !canInstall;
        if (canInstall) {
            const monthly = Math.ceil((total * (1 + config.installmentMarkup / 100) / config.installmentMonths) * 100) / 100;
            els.installmentText.replaceChildren(
                fill(config.installmentMarkup === 0 ? t.installment : t.installmentWithInterest,
                    { amount: strong(money(monthly)), months: config.installmentMonths })
            );
            els.installmentText.append(document.createElement('br'), el('small', 'cart-installment__how', t.installmentHow));
        }
    }

    /* ---------- render ---------- */
    function paint(cart, productMap) {
        // Məlumat dəyişməyibsə DOM-a toxunmuruq — şəkillər yanıb-sönməsin
        const signature = JSON.stringify(cart.map(item => [
            Number(item.variant_id),
            Number(item.quantity) || 1,
            productMap.get(Number(item.variant_id)) || null,
        ]));
        if (signature === lastSignature) return;
        lastSignature = signature;

        const fragment = document.createDocumentFragment();
        let subtotal = 0;
        let count = 0;

        for (const item of cart) {
            const product = productMap.get(Number(item.variant_id));
            if (!product) continue;
            const { row, lineTotal, quantity } = buildRow(item, product);
            fragment.appendChild(row);
            subtotal += lineTotal;
            count += quantity;
        }

        els.items.replaceChildren(fragment);
        state.subtotal = round2(subtotal);
        state.count = count;
        updateTotals();
    }

    async function renderCart() {
        const version = ++renderVersion;
        const cart = getCart();

        if (!cart.length) {
            root.classList.add('is-empty');
            root.classList.remove('is-loading');
            els.items.replaceChildren();
            lastSignature = '';
            state.subtotal = 0;
            state.count = 0;
            updateTotals();
            return;
        }

        root.classList.remove('is-empty');

        // 1) Keşdə bütün məhsullar varsa — şəbəkəni gözləmədən dərhal göstəririk
        const cache = readProductCache();
        if (cart.every(item => cache[Number(item.variant_id)])) {
            paint(cart, new Map(Object.values(cache).map(p => [Number(p.variant_id), p])));
            root.classList.remove('is-loading');
        }

        // 2) Serverdən təzə məlumat — qiymət və ya məhsul dəyişibsə yenidən çəkilir
        try {
            const products = await fetchProducts(cart);
            if (version !== renderVersion) return;
            writeProductCache(products);
            paint(cart, new Map(products.map(p => [Number(p.variant_id), p])));
        } catch (error) {
            if (version !== renderVersion) return;
            console.error('Səbət məlumatları yüklənmədi:', error);
            // Heç nə göstərilməyibsə, skelet sətirlər sonsuz qalmasın
            if (!lastSignature) els.items.replaceChildren();
        } finally {
            if (version === renderVersion) root.classList.remove('is-loading');
        }
    }

    /* ---------- undo toast ---------- */
    function hideToast() {
        els.toast.classList.remove('is-visible');
        undoEntry = null;
    }

    function showUndo(entry) {
        undoEntry = entry;
        els.toastText.textContent = t.removed;
        els.toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(hideToast, 5000);
    }

    els.toastUndo.addEventListener('click', async () => {
        if (!undoEntry || els.toastUndo.disabled) return;
        els.toastUndo.disabled = true;
        try {
            const entry = undoEntry;
            await window.parfumshopCart.change(Number(entry.item.variant_id), 'add', Number(entry.item.quantity), entry.item.product_id);
            clearTimeout(toastTimer);
            hideToast();
        } catch (error) {
            cartError(error);
        } finally {
            els.toastUndo.disabled = false;
        }
    });

    /* ---------- actions ---------- */
    root.addEventListener('click', async event => {
        const button = event.target.closest('button[data-action]');
        if (!button || !root.contains(button) || button.disabled) return;
        const action = { plus: 'add', minus: 'decrease', remove: 'remove' }[button.dataset.action];
        if (!action) return;
        const id = Number(button.dataset.id);
        const cart = getCart();
        const index = cart.findIndex(entry => Number(entry.variant_id) === id);
        if (index === -1) return;
        const item = cart[index];
        button.disabled = true;
        try {
            await window.parfumshopCart.change(id, action);
            if (action === 'remove') showUndo({ item, index });
        } catch (error) {
            cartError(error);
        } finally {
            button.disabled = false;
        }
    });

    window.addEventListener('parfumshop:promo-updated', updateTotals);
    window.addEventListener('parfumshop:cart-updated', () => {
        window.parfumshopCart.ready.then(renderCart).catch(cartError);
    });

    // Mobil bar: səhifədəki əsas CTA görünəndə gizlənir
    if ('IntersectionObserver' in window && els.checkout) {
        new IntersectionObserver(([entry]) => {
            els.mobilebar.classList.toggle('is-hidden', entry.isIntersecting);
        }).observe(els.checkout);
    }

    window.parfumshopCart.ready.then(renderCart).catch(error => {
        root.classList.remove('is-loading');
        cartError(error);
    });
})();
