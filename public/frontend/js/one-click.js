(() => {
    'use strict';
    const button = document.querySelector('[data-one-click]');
    const dialog = document.getElementById('oneClickDialog');
    const form = document.getElementById('oneClickForm');
    const input = document.getElementById('oneClickMobile');
    const error = document.getElementById('oneClickError');
    if (!button || !dialog || !form || !input) return;

    // Prefix 994 is fixed; the user supplies the remaining nine digits.
    const format = value => {
        let digits = String(value || '').replace(/\D/g, '');
        if (digits.startsWith('994')) digits = digits.slice(3);
        if (digits.startsWith('0')) digits = digits.slice(1);
        digits = digits.slice(0, 9);
        const groups = [digits.slice(0, 2), digits.slice(2, 5), digits.slice(5, 7), digits.slice(7, 9)].filter(Boolean);
        return '994 ' + groups.join(' ');
    };
    input.value = '994 ';
    input.addEventListener('input', () => { input.value = format(input.value); });

    function selectedVariant() {
        return Number(document.querySelector('[data-buybox] .size-pill.active-size-amount')?.dataset.variantId || 0);
    }
    function selectedQuantity() {
        return Number(document.querySelector('[data-buybox] [data-qty-value]')?.textContent || 1);
    }
    function showError(message) {
        error.textContent = message;
        error.style.display = message ? 'block' : 'none';
    }
    async function submit(mobile) {
        const variantId = selectedVariant();
        if (!variantId) return;
        button.disabled = true;
        const submitButton = form.querySelector('[type=submit]');
        submitButton.disabled = true;
        showError('');
        try {
            const response = await fetch(button.dataset.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                },
                body: JSON.stringify({variant_id: variantId, quantity: selectedQuantity(), ...(mobile ? {mobile} : {})})
            });
            const result = await response.json();
            if (!response.ok || !result.ok || !result.redirect) {
                throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'Sifariş yaradılmadı.');
            }
            // Do not touch the existing cart: this is a separate, single-product order.
            window.location.assign(result.redirect);
        } catch (e) {
            showError(e.message || 'Sifariş yaradılmadı.');
            if (!dialog.open) dialog.showModal();
        } finally {
            button.disabled = false;
            submitButton.disabled = false;
        }
    }
    button.addEventListener('click', () => {
        if (button.dataset.auth === '1') submit();
        else { showError(''); dialog.showModal(); input.focus(); }
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        const digits = input.value.replace(/\D/g, '');
        if (!/^994\d{9}$/.test(digits)) {
            showError('Mobil nömrəni 994XXXXXXXXX formatında daxil edin.');
            return;
        }
        submit(digits);
    });
    dialog.querySelector('[data-one-click-close]')?.addEventListener('click', () => dialog.close());
})();