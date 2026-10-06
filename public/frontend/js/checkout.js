(() => {
    'use strict';

    const page = document.querySelector('.checkout-page');
    if (!page) return;

    let displayedCart = [];
    let loadVersion = 0;
    let submitting = false;
    const BONUS = 'bonus_balance';
    const INSTALLMENT = 'installment';
    const BIRBANK_INSTALLMENT = 'birbank_installment';

    // Serverdən gələn məlumat: checkout.blade.php → #checkout-config
    let config = {};
    try { config = JSON.parse(document.getElementById('checkout-config')?.textContent || '{}'); } catch (e) {}
    const { storeUrl, cartProductsUrl, cartUrl, csrf, messages = {} } = config;
    const t = JSON.parse(page.dataset.i18n || '{}');
    const bonusBalance = Number(config.bonusBalance) || 0;

    const $ = id => document.getElementById(id);
    const els = {
        items: $('checkoutItems'),
        discount: $('checkoutDiscount'),
        discountRow: $('checkoutDiscountRow'),
        referralRow: $('checkoutReferralRow'),
        creditNote: $('checkoutCreditNote'),
        referral: $('checkoutReferral'),
        delivery: $('checkoutDelivery'),
        giftWrapRow: $('checkoutGiftWrapRow'),
        giftWrapFee: $('checkoutGiftWrapFee'),
        total: $('checkoutTotal'),
        earnedBonus: $('checkoutEarnedBonus'),
        earnedBonusText: $('checkoutEarnedBonusText'),
        placeOrder: $('placeOrder'),
        error: $('checkoutError'),
        installment: $('installmentDetails'),
        birbankInstallment: $('birbankInstallmentDetails'),
        periodInput: $('creditPeriod'),
        creditTerms: $('creditTerms'),
        bonusHint: page.querySelector('[data-bonus-hint]'),
        creditSummary: page.querySelector('.installment__summary'),
        mobilebar: $('checkoutMobilebar'),
        mobileTotal: $('checkoutMobileTotal'),
        mobileSubmit: $('checkoutMobileSubmit'),
    };

    const paymentRadios = [...page.querySelectorAll('input[name="payment_method"]')];
    const bonusRadio = paymentRadios.find(r => r.dataset.code === BONUS);
    const periodRadios = [...page.querySelectorAll('input[name="credit_period_choice"]')];
    const birbankMonthRadios = [...page.querySelectorAll('input[name="birbank_installment_months"]')];

    const state = { subtotal: 0, total: 0 };

    /* =========================================================
       Helpers
       ========================================================= */
    const formatter = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = value => formatter.format(Number(value) || 0).replace(/,/g, '\u00A0') + '\u00A0₼';
    const round2 = value => Math.round((value + Number.EPSILON) * 100) / 100;

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    function getCart() {
        return window.parfumshopCart.get();
    }

    function showError(message) {
        const text = message || messages.error;

        els.error.hidden = true;
        els.error.textContent = '';

        if (typeof window.parfumshopNotify === 'function') {
            window.parfumshopNotify(text, 'error');
        } else {
            els.error.textContent = text;
            els.error.hidden = false;
            els.error.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    }


    function clearError() {
        els.error.hidden = true;
        els.error.textContent = '';
    }

    /* =========================================================
       Address
       ========================================================= */
    const addressSelect = $('addressSelect');
    const newAddressBox = $('newAddress');

    function syncAddressForm() {
        if (!addressSelect || !newAddressBox) return;
        newAddressBox.classList.toggle('checkout-hidden', addressSelect.value !== 'new');
    }

    if (addressSelect) {
        addressSelect.addEventListener('change', syncAddressForm);
        addressSelect.addEventListener('input', syncAddressForm);

        // Select2 native "change" hadisəsini bəzən bloklayır, ona görə jQuery-yə də qoşuluruq
        const bindSelect2Sync = () => {
            if (window.jQuery?.fn?.select2) {
                window.jQuery(addressSelect).on('select2:select select2:unselect select2:clear change', syncAddressForm);
                return true;
            }
            return false;
        };

        if (!bindSelect2Sync()) {
            let attempts = 0;
            const timer = setInterval(() => {
                attempts++;
                if (bindSelect2Sync() || attempts > 20) clearInterval(timer);
            }, 100);
        }

        syncAddressForm();
    }

    /* =========================================================
       Payment
       ========================================================= */
    const selectedCode = () => paymentRadios.find(r => r.checked)?.dataset.code;

    function selectFirstEnabled() {
        const radio = paymentRadios.find(r => !r.disabled);
        if (!radio) return;
        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function syncBonus() {
        if (!bonusRadio) return;

        const insufficient = bonusBalance <= 0 || (state.total > 0 && bonusBalance < state.total);
        bonusRadio.disabled = insufficient;

        if (els.bonusHint) {
            const showShortage = insufficient && state.total > 0;
            els.bonusHint.textContent = showShortage
                ? String(t.bonusInsufficient || '').replace(':amount', money(state.total - bonusBalance))
                : String(t.bonusBalance || '').replace(':amount', money(bonusBalance));
            els.bonusHint.classList.toggle('is-warning', showShortage);
        }

        if (insufficient && bonusRadio.checked) selectFirstEnabled();
    }

    function creditFor(radio) {
        const months = Number(radio.dataset.months) || 1;
        const rate = Number(radio.dataset.rate) || 0;
        const sum = round2(state.total * (1 + rate / 100));
        return {
            sum,
            monthly: Math.ceil((sum / months) * 100) / 100,
            overpay: Math.max(0, round2(sum - state.total)),
        };
    }

    // müddət yalnız məbləğ onun minimumundan yuxarı olanda (CreditPeriod::availableFor) — məbləğ dəyişəndə yenilənir
    let creditRule = null;
    try { creditRule = JSON.parse(document.getElementById('app-data')?.textContent || '{}').creditRule || null; } catch (e) {}

    function filterPeriods() {
        periodRadios.forEach(radio => {
            const min = creditRule?.mins?.[Number(radio.dataset.months)];
            const hide = min != null && state.total <= min;
            radio.closest('label').hidden = hide;
            if (hide && radio.checked) {
                radio.checked = false;
                if (els.periodInput) els.periodInput.value = '';
            }
        });
    }

    function updateCredit() {
        if (!periodRadios.length) return;
        filterPeriods();

        periodRadios.forEach(radio => {
            const target = radio.parentElement.querySelector('[data-monthly]');
            if (target) target.textContent = state.total > 0 ? money(creditFor(radio).monthly) + (t.perMonth || '') : '—';
        });

        if (!els.creditSummary) return;
        const selected = periodRadios.find(r => r.checked);
        els.creditSummary.hidden = !selected || state.total <= 0;

        if (selected && state.total > 0) {
            const credit = creditFor(selected);
            els.creditSummary.querySelector('[data-credit-monthly]').textContent = money(credit.monthly);
            els.creditSummary.querySelector('[data-credit-total]').textContent = money(credit.sum);
            els.creditSummary.querySelector('[data-credit-overpay]').textContent = '+' + money(credit.overpay);
        }
    }

    function syncPayment() {
        const isInstallment = selectedCode() === INSTALLMENT;

        if (els.installment) els.installment.hidden = !isInstallment;
        if (els.birbankInstallment) els.birbankInstallment.hidden = selectedCode() !== BIRBANK_INSTALLMENT;
        if (els.placeOrder) els.placeOrder.disabled = isInstallment && !config.creditProfileComplete;
        clearError();

        // promo.js endirimi 0 edir və yenidən promo-updated göndərir → updateTotals
        window.dispatchEvent(new CustomEvent('parfumshop:promo-lock', { detail: { locked: isInstallment } }));
        updateCredit();
        updateTotals();
    }

    paymentRadios.forEach(radio => radio.addEventListener('change', () => {
        syncPayment();
        scrollToPeriods();
    }));

    // Taksit / hissə-hissə seçiləndə müddət seçiminə sürüşdür
    function scrollToPeriods() {
        const code = selectedCode();
        const target = code === BIRBANK_INSTALLMENT ? els.birbankInstallment
            : code === INSTALLMENT ? els.installment : null;
        if (!target || target.hidden) return;
        const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        target.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'center' });
    }

    periodRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            if (els.periodInput) els.periodInput.value = radio.value;
            clearError();
            updateCredit();
        });
    });

    els.creditTerms?.addEventListener('change', clearError);

    /* =========================================================
       Totals
       ========================================================= */
    function updateTotals() {
        // Hissə-hissə ödəniş: məhsul endirimi tətbiq olunmur — yekun adi qiymətlərlə (server də belə hesablayır)
        const isCredit = selectedCode() === INSTALLMENT;
        const base = isCredit && state.regularSubtotal > state.subtotal ? state.regularSubtotal : state.subtotal;
        if (els.creditNote) els.creditNote.hidden = !(isCredit && state.regularSubtotal > state.subtotal);
        const promo = window.ParfumPromo || {};
        const discount = promo.locked ? 0 : Math.min(base, Number(promo.discount) || 0);
        const referral = window.ParfumReferral
            ? window.ParfumReferral.discount(base, discount, selectedCode() === INSTALLMENT)
            : 0;
        const goods = Math.max(0, round2(base - discount - referral));

        const delivery = config.delivery || {};
        const freeFrom = Number(delivery.free_from) || 0;
        const fee = delivery.mode === 'free' || (freeFrom > 0 && goods >= freeFrom) ? 0 : Number(delivery.fee) || 0;

        const giftWrapSelected = Boolean($('giftWrap')?.checked);
        const giftWrapFee = giftWrapSelected && config.giftWrap?.mode === 'paid'
            ? Math.max(0, Number(config.giftWrap.fee) || 0) : 0;
        state.total = round2(goods + fee + giftWrapFee);
        if (els.giftWrapRow && els.giftWrapFee) {
            els.giftWrapRow.hidden = !giftWrapSelected;
            els.giftWrapFee.textContent = giftWrapFee > 0 ? money(giftWrapFee) : (t.free || '0.00 ₼');
            els.giftWrapFee.classList.toggle('is-free', giftWrapFee === 0);
            els.giftWrapFee.style.color = giftWrapFee === 0 ? '#16803c' : '';
        }

        els.discountRow.hidden = discount <= 0;
        els.discount.textContent = '−' + money(discount);
        if (els.referralRow) {
            els.referralRow.hidden = referral <= 0;
            els.referral.textContent = '−' + money(referral);
        }
        els.delivery.textContent = fee > 0 ? money(fee) : (t.free || '0.00 ₼');
        els.delivery.classList.toggle('is-free', fee === 0);
        els.delivery.style.color = fee === 0 ? '#16803c' : '';
        els.total.textContent = money(state.total);

        if (els.mobileTotal) els.mobileTotal.textContent = money(state.total);
        // Bonus is earned on paid/cash orders, not when paying with existing bonus
        // or submitting an internal credit application.
        if (els.earnedBonus && els.earnedBonusText) {
            const eligible = ![BONUS, INSTALLMENT].includes(selectedCode());
            const earned = round2(goods * (Number(config.bonusRate) || 0));
            els.earnedBonus.hidden = !eligible || earned <= 0;
            els.earnedBonusText.textContent = String(t.earnedBonus || '').replace(':amount', money(earned));
        }

        syncBonus();
        updateCredit();
    }

    window.addEventListener('parfumshop:promo-updated', updateTotals);
    $('giftWrap')?.addEventListener('change', updateTotals);

    /* =========================================================
       Items
       ========================================================= */
    function buildItem(product, quantity) {
        const line = (Number(product.price) || 0) * quantity;
        const row = el('div', 'checkout-item');

        const image = el('div', 'checkout-item__img');
        if (product.image) {
            const img = el('img');
            img.src = product.image;
            img.alt = product.name || '';
            img.loading = 'lazy';
            image.appendChild(img);
        }

        const info = el('div', 'checkout-item__info');
        const meta = el('div', 'checkout-item__meta');
        [product.brand, product.is_gift_card ? null : product.size, '× ' + quantity]
            .filter(Boolean)
            .forEach(value => meta.appendChild(el('span', '', value)));
        info.append(el('div', 'checkout-item__name', product.name), meta);

        const priceCell = el('strong', 'checkout-item__price', money(line));
        // endirimli məhsul: köhnə məbləğ üstündən xətt
        const regular = (Number(product.regular_price) || 0) * quantity;
        if (regular > line) {
            priceCell.textContent = '';
            priceCell.append(el('s', 'price-old', money(regular)), ' ', el('span', 'price-sale', money(line)));
        }
        row.append(image, info, priceCell);
        return { row, line };
    }

    async function loadItems() {
        const version = ++loadVersion;
        els.placeOrder.disabled = true;
        const cart = getCart();
        if (!cart.length) {
            location.href = cartUrl;
            return;
        }

        try {
            const url = new URL(cartProductsUrl, window.location.origin);
            url.searchParams.set('variants', cart.map(item => item.variant_id).join(','));
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Cart products request failed');

            const products = await response.json();
            if (version !== loadVersion || submitting) return;
            const productMap = new Map(products.map(p => [Number(p.variant_id), p]));
            const fragment = document.createDocumentFragment();
            let subtotal = 0;
            let regularSubtotal = 0; // hissə-hissə ödəniş — endirimsiz

            for (const item of cart) {
                const product = productMap.get(Number(item.variant_id));
                if (!product) continue;
                const { row, line } = buildItem(product, Math.max(1, Number(item.quantity) || 1));
                fragment.appendChild(row);
                subtotal += line;
                regularSubtotal += (Number(product.regular_price) || Number(product.price) || 0) * Math.max(1, Number(item.quantity) || 1);
            }

            if (!fragment.childNodes.length) {
                location.href = cartUrl;
                return;
            }

            displayedCart = cart;
            els.items.replaceChildren(fragment);
            els.placeOrder.disabled = false;
            state.subtotal = round2(subtotal);
            state.regularSubtotal = round2(regularSubtotal);
            updateTotals();
        } catch (error) {
            console.error('Checkout məhsulları yüklənmədi:', error);
            showError(messages.error);
        }
    }

    /* =========================================================
       Submit
       ========================================================= */
    function validateBeforeSubmit() {
        const code = selectedCode();

        if (!code) return t.errPayment || messages.error;

        if (code === BONUS && bonusBalance < state.total) {
            return String(t.bonusInsufficient || '').replace(':amount', money(state.total - bonusBalance));
        }

        if (code === BIRBANK_INSTALLMENT && !birbankMonthRadios.some(r => r.checked)) {
            return t.errPeriod || messages.error;
        }

        if (code === INSTALLMENT) {
            if (!config.creditProfileComplete) return t.errProfile;
            if (!els.periodInput?.value) return t.errPeriod;
            if (!els.creditTerms?.checked) return t.errTerms;
        }

        return null;
    }

    els.placeOrder.addEventListener('click', async () => {
        const button = els.placeOrder;
        if (button.disabled) return;

        const validationError = validateBeforeSubmit();
        if (validationError) {
            showError(validationError);
            return;
        }

        button.disabled = true;
        submitting = true;
        clearError();

        const isNew = !addressSelect || addressSelect.value === 'new';
        const val = id => $(id)?.value?.trim() || null;
        const isInstallment = selectedCode() === INSTALLMENT;

        const body = {
            cart: displayedCart,
            address_mode: isNew ? 'new' : 'existing',
            address_id: isNew ? null : Number(addressSelect.value),
            title: val('addressTitle'),
            city_id: Number(val('city')) || null,
            address: val('address'),
            building: val('building'),
            entrance: val('entrance'),
            floor: val('floor'),
            apartment: val('apartment'),
            address_note: val('addressNote'),
            payment_method_id: Number(paymentRadios.find(r => r.checked)?.value),
            credit_period_id: isInstallment ? (Number(els.periodInput?.value) || null) : null,
            birbank_installment_months: selectedCode() === BIRBANK_INSTALLMENT
                ? (Number(birbankMonthRadios.find(r => r.checked)?.value) || null) : null,
            accept_terms: isInstallment && els.creditTerms?.checked ? 1 : 0,
            gift_wrap: $('giftWrap')?.checked ? 1 : 0,
            customer_note: val('customerNote'),
        };

        try {
            const response = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));

            page.querySelectorAll('.checkout-field.is-invalid').forEach(node => node.classList.remove('is-invalid'));

            if (!response.ok) {
                if (data.errors) {
                    const fieldMap = { title: 'addressTitle', city_id: 'city', address_note: 'addressNote' };
                    Object.keys(data.errors).forEach(key => {
                        $(fieldMap[key] || key)?.closest('.checkout-field')?.classList.add('is-invalid');
                    });
                }
                const errors = data.errors ? Object.values(data.errors).flat() : [];
                throw new Error(errors.length ? errors.join(' | ') : (data.message || messages.error));
            }

            if (data.clear_cart) {
                window.parfumshopCart.accept(data.cart || []);
            }
            location.href = data.redirect;
        } catch (error) {
            showError(error.message);
            submitting = false;
            button.disabled = false;
        }
    });

    /* =========================================================
   Mobile bar
   ========================================================= */
    if (els.mobilebar && els.mobileSubmit) {
        // Əsas düyməni işə salır — validasiya və göndərmə eyni yerdən keçir
        els.mobileSubmit.addEventListener('click', () => els.placeOrder.click());

        // Əsas düymə deaktivdirsə, mobil düymə də deaktiv olsun
        const syncDisabled = () => { els.mobileSubmit.disabled = els.placeOrder.disabled; };
        new MutationObserver(syncDisabled).observe(els.placeOrder, { attributes: true, attributeFilter: ['disabled'] });
        syncDisabled();

        // Əsas düymə ekranda görünəndə panel gizlənir
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(([entry]) => {
                els.mobilebar.classList.toggle('is-hidden', entry.isIntersecting);
            }).observe(els.placeOrder);
        }
    }

    /* =========================================================
       Init
       ========================================================= */
    syncPayment();
    els.placeOrder.disabled = true;
    window.parfumshopCart.ready.then(loadItems).catch(error => showError(error.message));
    window.addEventListener('parfumshop:cart-updated', () => {
        if (!submitting) window.parfumshopCart.ready.then(loadItems).catch(error => showError(error.message));
    });
})();
