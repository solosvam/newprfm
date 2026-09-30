(() => {
    'use strict';
    const button = document.querySelector('[data-one-click]');
    const dialog = document.getElementById('oneClickDialog');
    const form = document.getElementById('oneClickForm');
    const input = document.getElementById('oneClickMobile');
    const error = document.getElementById('oneClickError');
    if (!button || !dialog || !form || !input) return;

    // Reuse the exact 994 mask used on the login page.
    if (typeof Inputmask !== 'undefined') {
        Inputmask({
            // 994-dən sonra birinci rəqəm 0 ola bilməz (0 basılsa qəbul olunmur, növbəti rəqəm gözlənilir)
            mask: '\\9\\9\\4 N9 999 99 99',
            definitions: { N: { validator: '[1-9]' } },
            showMaskOnHover: false,
            clearIncomplete: false
        }).mask(input);
    }

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