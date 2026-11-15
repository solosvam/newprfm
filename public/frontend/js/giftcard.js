/*
 * Səbət və checkout: hədiyyə kartının tətbiqi (partials/gift-card-code).
 * window.ParfumGiftCard = { code, amount } — kartın məbləği; sifarişdən nə qədərinin çıxılacağını cart.js / checkout.js hesablayır
 * (min(kart, yekun)). Dəyişəndə "parfumshop:giftcard-updated" göndərilir. Server checkout-da kartı yenidən yoxlayır.
 */
(() => {
    'use strict';

    const root = document.querySelector('[data-giftcard]');
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
    const noteEl = root.querySelector('[data-giftcard-note]');

    const state = { code: root.dataset.code || null, amount: Number(root.dataset.amount) || 0 };
    window.ParfumGiftCard = state;

    const formatter = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = value => formatter.format(Number(value) || 0).replace(/,/g, ' ') + ' ₼';

    /** cart.js / checkout.js çağırır: kartdan çıxılan məbləğ və artıq qalan (yanan) hissənin qeydi */
    state.applyTo = total => {
        const used = state.code ? Math.round(Math.min(state.amount, Math.max(0, total)) * 100) / 100 : 0;
        const burn = state.code ? Math.round((state.amount - used) * 100) / 100 : 0;
        amountEl.textContent = used > 0 ? '−' + money(used) : '';
        noteEl.textContent = burn > 0 && total > 0 ? String(root.dataset.burn || '').replace(':amount', money(burn)) : '';
        noteEl.hidden = !(burn > 0 && total > 0);
        return used;
    };

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
        const has = Boolean(state.code);
        applied.hidden = !has;
        details.hidden = has;
        codeEl.textContent = state.code || '';
        if (!has) {
            amountEl.textContent = '';
            noteEl.hidden = true;
        }
    }

    function showError(message) {
        errorEl.textContent = message || '';
        errorEl.hidden = !message;
    }

    const emit = () => window.dispatchEvent(new CustomEvent('parfumshop:giftcard-updated', { detail: { ...state } }));

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const code = input.value.trim();
        if (!code) return;
        form.classList.add('is-loading');
        try {
            const data = await request(root.dataset.applyUrl, 'POST', { code });
            state.code = data.code;
            state.amount = Number(data.amount) || 0;
            input.value = '';
            showError('');
        } catch (error) {
            state.code = null;
            state.amount = 0;
            showError(error.message);
            details.open = true;
        } finally {
            form.classList.remove('is-loading');
            render();
            emit();
        }
    });

    removeBtn.addEventListener('click', async () => {
        removeBtn.disabled = true;
        showError('');
        try {
            await request(root.dataset.removeUrl, 'DELETE');
            state.code = null;
            state.amount = 0;
            render();
            emit();
        } catch (error) {
            showError(error.message);
        } finally {
            removeBtn.disabled = false;
        }
    });

    render();
    emit();
})();
