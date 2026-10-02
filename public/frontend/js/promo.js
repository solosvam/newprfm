(() => {
    'use strict';

    const root = document.querySelector('[data-promo]');
    if (!root) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const details = root.querySelector('.promo__details');
    const form = root.querySelector('.promo__form');
    const input = root.querySelector('.promo__input');
    const applied = root.querySelector('.promo__applied');
    const codeEl = root.querySelector('.promo__code');
    const amountEl = root.querySelector('.promo__amount');
    const removeBtn = root.querySelector('.promo__remove');
    const errorEl = root.querySelector('.promo__error');
    const lockedEl = root.querySelector('.promo__locked');


    const state = { code: root.dataset.code || null, discount: 0 };
    window.ParfumPromo = state;
    let version = 0;

    const formatter = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = value => formatter.format(Number(value) || 0).replace(/,/g, '\u00A0') + '\u00A0₼';

    function cartItems() {
        return window.parfumshopCart.get().map(i => ({ variant_id: Number(i.variant_id), quantity: Number(i.quantity) }));
    }

    async function request(url, method, body) {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Request failed');
        return data;
    }

    function render() {
        if (state.locked) {
            details.hidden = true;
            applied.hidden = true;
            if (lockedEl) lockedEl.hidden = false;
            return;
        }
        if (lockedEl) lockedEl.hidden = true;

        const hasCode = Boolean(state.code);
        applied.hidden = !hasCode;
        details.hidden = hasCode;
        codeEl.textContent = state.code || '';
        amountEl.textContent = state.discount > 0 ? '−' + money(state.discount) : '';
    }

    function showError(message) {
        errorEl.textContent = message || '';
        errorEl.hidden = !message;
    }

    function emit() {
        window.dispatchEvent(new CustomEvent('parfumshop:promo-updated', { detail: { ...state } }));
    }

    function clear() {
        state.code = null;
        state.discount = 0;
    }

    async function apply(code) {
        if (state.locked) return;
        const current = ++version;
        try { await window.parfumshopCart.ready; } catch (error) { showError(error.message); return; }
        if (current !== version || state.locked) return;
        const items = cartItems();

        if (!items.length) {
            try {
                await request(root.dataset.removeUrl, 'DELETE');
                if (current !== version) return;
                clear();
                showError('');
                render();
                emit();
            } catch (error) {
                if (current === version) showError(error.message);
            }
            return;
        }

        form.classList.add('is-loading');
        try {
            const data = await request(root.dataset.applyUrl, 'POST', { code, items });
            if (current !== version) return;
            state.code = data.code;
            state.discount = Number(data.discount) || 0;
            input.value = '';
            showError('');
        } catch (error) {
            if (current !== version) return;
            clear();
            showError(error.message);
            details.open = true;
        } finally {
            if (current === version) {
                form.classList.remove('is-loading');
                render();
                emit();
            }
        }
    }

    form.addEventListener('submit', event => {
        event.preventDefault();
        const code = input.value.trim();
        if (code) apply(code);
    });

    removeBtn.addEventListener('click', async () => {
        const current = ++version;
        removeBtn.disabled = true;
        showError('');
        try {
            await request(root.dataset.removeUrl, 'DELETE');
            if (current !== version) return;
            clear();
            render();
            emit();
        } catch (error) {
            if (current === version) showError(error.message);
        } finally {
            if (current === version) removeBtn.disabled = false;
        }
    });

    // Səbət dəyişəndə endirim yenidən hesablanır (min. məbləğ, faiz və s.)
    const revalidate = () => { if (state.code) apply(state.code); };
    window.addEventListener('parfumshop:promo-lock', event => {
        const locked = Boolean(event.detail?.locked);
        if (locked === state.locked) return;

        state.locked = locked;
        version++;

        if (locked) {
            state.discount = 0;
            showError('');
            render();
            emit();
        } else if (state.code) {
            render();
            apply(state.code);
        } else {
            render();
            emit();
        }
    });
    window.addEventListener('parfumshop:cart-updated', revalidate);

    if (state.code) apply(state.code);
    else { render(); emit(); }
})();
