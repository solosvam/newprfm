(() => {
    'use strict';

    const config = window.checkoutConfig || {};
    const { storeUrl, cartProductsUrl, cartUrl, csrf, messages = {} } = config;

    function getCheckoutCart() {
        try {
            return JSON.parse(localStorage.getItem('parfumshop_cart') || '[]');
        } catch {
            return [];
        }
    }

    const addressSelect = document.getElementById('addressSelect');
    const newAddressBox = document.getElementById('newAddress');

    function syncAddressForm() {
        if (!addressSelect || !newAddressBox) return;
        newAddressBox.classList.toggle('checkout-hidden', addressSelect.value !== 'new');
    }

    if (addressSelect) {
        addressSelect.addEventListener('change', syncAddressForm);
        addressSelect.addEventListener('input', syncAddressForm);

        // Select2 native "change" hadisəsini bəzən bloklayır, ona görə
        // jQuery-nin özünə də ayrıca qoşuluruq (jQuery gec yüklənə bilər deyə gözləyirik).
        function bindSelect2Sync() {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                window.jQuery(addressSelect).on('select2:select select2:unselect select2:clear change', syncAddressForm);
                return true;
            }
            return false;
        }

        if (!bindSelect2Sync()) {
            let attempts = 0;
            const waitForSelect2 = setInterval(() => {
                attempts++;
                if (bindSelect2Sync() || attempts > 20) clearInterval(waitForSelect2);
            }, 100);
        }

        syncAddressForm();
    }

    let checkoutTotal = 0;
    let checkoutSubtotal = 0;
    function updateCheckoutTotals() {
        const discount = Math.min(checkoutSubtotal, Number(window.ParfumPromo?.discount) || 0);
        const goods = Math.max(0, checkoutSubtotal - discount);
        const delivery = config.delivery || {};
        const fee = delivery.mode === 'free' || (Number(delivery.free_from) > 0 && goods >= Number(delivery.free_from)) ? 0 : Number(delivery.fee) || 0;
        checkoutTotal = Math.round((goods + fee) * 100) / 100;
        document.getElementById('checkoutDiscount').textContent = '−' + discount.toFixed(2) + ' ₼';
        document.getElementById('checkoutDelivery').textContent = fee ? fee.toFixed(2) + ' ₼' : 'Pulsuz';
        document.getElementById('checkoutTotal').textContent = checkoutTotal.toFixed(2) + ' ₼';
        refreshPayment();
    }
    window.addEventListener('parfumshop:promo-updated', updateCheckoutTotals);
    const selectedMethod = () => document.querySelector('[name=payment_method]:checked')?.dataset.code;
    function refreshPayment() {
        const code = selectedMethod();
        const bonusWarning = document.getElementById('bonusWarning');
        const installment = document.getElementById('installmentDetails');
        const button = document.getElementById('placeOrder');
        if (installment) installment.hidden = code !== 'installment';
        const insufficient = code === 'bonus_balance' && checkoutTotal > Number(config.bonusBalance || 0);
        if (bonusWarning) {
            bonusWarning.hidden = !insufficient;
            bonusWarning.textContent = 'Bonus balansınız sifariş məbləğindən azdır. Mövcud balans: ' + Number(config.bonusBalance || 0).toFixed(2) + ' ₼';
        }
        if (button) button.disabled = insufficient || (code === 'installment' && !config.creditProfileComplete);
        const period = document.getElementById('creditPeriod')?.selectedOptions[0];
        const estimate = document.getElementById('creditEstimate');
        if (estimate && period?.value) {
            const months = Number(period.dataset.months);
            const total = checkoutTotal * (1 + Number(period.dataset.rate) / 100);
            estimate.textContent = 'Ümumi: ' + total.toFixed(2) + ' ₼ · Aylıq: ' + (total / months).toFixed(2) + ' ₼';
        }
    }
    document.querySelectorAll('[name=payment_method]').forEach(input => input.addEventListener('change', refreshPayment));
    document.getElementById('creditPeriod')?.addEventListener('change', refreshPayment);

    (async () => {
        const cart = getCheckoutCart();
        if (!cart.length) {
            location.href = cartUrl;
            return;
        }

        const response = await fetch(cartProductsUrl + '?variants=' + cart.map(x => x.variant_id).join(','));
        const products = await response.json();
        let total = 0;

        products.forEach(product => {
            const item = cart.find(i => i.variant_id === product.variant_id);
            const line = product.price * item.quantity;
            total += line;

            const productTitle = (product.brand ? product.brand + ' ' : '') + product.name;
            const productMeta = (product.size ? product.size + ' · ' : '') + '× ' + item.quantity;

            document.getElementById('checkoutItems').insertAdjacentHTML('beforeend',
                '<div class="checkout-item"><span><b>' + productTitle + '</b><small>' + productMeta + '</small></span><strong>' + line.toFixed(2) + ' ₼</strong></div>'
            );
        });

        checkoutSubtotal = total;
        updateCheckoutTotals();
    })();

    document.getElementById('placeOrder').onclick = async function () {
        const button = this;
        button.disabled = true;

        const isNew = !addressSelect || addressSelect.value === 'new';
        const val = id => document.getElementById(id)?.value || null;

        const body = {
            cart: getCheckoutCart(),
            address_mode: isNew ? 'new' : 'existing',
            address_id: isNew ? null : Number(addressSelect.value),
            title: val('addressTitle'),
            city: val('city'),
            district: val('district'),
            address: val('address'),
            building: val('building'),
            entrance: val('entrance'),
            floor: val('floor'),
            apartment: val('apartment'),
            address_note: val('addressNote'),
            payment_method_id: Number(document.querySelector('[name=payment_method]:checked')?.value),
            credit_period_id: Number(document.getElementById('creditPeriod')?.value) || null,
            accept_terms: document.getElementById('creditTerms')?.checked ? 1 : 0,
            gift_wrap: document.getElementById('giftWrap').checked ? 1 : 0,
            customer_note: val('customerNote'),
        };

        try {
            const response = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(body),
            });
            const data = await response.json();

            if (!response.ok) {
                document.querySelectorAll('.checkout-field.is-invalid').forEach(el => el.classList.remove('is-invalid'));

                if (data.errors) {
                    const fieldMap = { title: 'addressTitle', city: 'city', address: 'address' };
                    Object.keys(data.errors).forEach(key => {
                        const input = document.getElementById(fieldMap[key] || key);
                        if (input) input.closest('.checkout-field')?.classList.add('is-invalid');
                    });
                }

                const errors = data.errors ? Object.values(data.errors).flat() : [];
                throw new Error(errors.length ? errors.join(' | ') : (data.message || messages.error));
            }

            document.querySelectorAll('.checkout-field.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            if (data.clear_cart) localStorage.removeItem('parfumshop_cart');
            location.href = data.redirect;
        } catch (error) {
            const message = error.message || messages.error;
            const errorBox = document.getElementById('checkoutError');
            errorBox.hidden = true;

            if (window.jQuery && typeof jQuery.notify === 'function') {
                jQuery.notify(message, { className: 'error', position: 'top right', autoHideDelay: 5000 });
            } else {
                errorBox.textContent = message;
                errorBox.hidden = false;
            }

            button.disabled = false;
        }
    };
})();
