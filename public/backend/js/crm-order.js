// CRM → "Yeni sifariş" modalı (backend/crm/partials/add-order.blade.php)
// Axtarış: AjaxController::searchProductCrm · Saxlama: CrmController::storeOrder
(() => {
    'use strict';

    const root = document.querySelector('.crm-order');
    if (!root) return;

    const $ = id => document.getElementById(id);
    const money = n => (Math.round(n * 100) / 100).toFixed(2) + ' ₼';
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const delivery = JSON.parse(root.dataset.delivery || '{}');
    const bonusBalance = Number(root.dataset.bonusBalance) || 0;
    const bonusRate = Number(root.dataset.bonusRate) || 0;
    const giftFee = Number(root.dataset.giftFee) || 0;
    const storeUrl = root.dataset.storeUrl;
    // Bu üsullarda sifariş bonusu yazılmır (frontdakı kimi)
    const NO_BONUS = ['installment', 'bonus_balance'];
    const searchUrl = root.dataset.searchUrl; // AjaxController::searchProductCrm

    const els = {
        search: $('crmOrderSearch'),
        results: $('crmOrderResults'),
        cart: $('crmOrderCart'),
        count: $('crmOrderCount'),
        subtotal: $('crmOrderSubtotal'),
        delivery: $('crmOrderDelivery'),
        total: $('crmOrderTotal'),
        footerTotal: $('crmOrderFooterTotal'),
        address: $('crmOrderAddress'),
        newAddress: $('crmOrderNewAddress'),
        birbank: $('crmOrderBirbank'),
        installment: $('crmOrderInstallment'),
        credit: $('crmOrderCredit'),
        creditRow: $('crmOrderCreditRow'),
        creditFee: $('crmOrderCreditFee'),
        giftWrap: $('crmOrderGiftWrap'),
        giftRow: $('crmOrderGiftRow'),
        gift: $('crmOrderGift'),
        bonus: $('crmOrderBonus'),
        hint: $('crmOrderHint'),
        submit: $('crmOrderSubmit'),
    };

    let results = [];
    /** @type {{variantId:number, name:string, variant:string, price:number, qty:number}[]} */
    let cart = [];

    // ---------- Axtarış ----------
    let timer;
    els.search.addEventListener('input', () => {
        clearTimeout(timer);
        const q = els.search.value.trim();
        if (q.length < 2) {
            renderResults(null);
            return;
        }
        timer = setTimeout(() => search(q), 250);
    });

    let controller;
    async function search(q) {
        controller?.abort(); // köhnə sorğunun cavabı yenisinin üstünə yazılmasın
        controller = new AbortController();
        els.results.innerHTML = '<div class="crm-order__empty">Axtarılır…</div>';
        try {
            const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            results = await res.json();
            renderResults(results);
        } catch (e) {
            if (e.name === 'AbortError') return;
            results = [];
            els.results.innerHTML = '<div class="crm-order__empty text-danger">Axtarış alınmadı. Yenidən cəhd edin.</div>';
        }
    }

    function renderResults(list) {
        if (!list) {
            els.results.innerHTML = '<div class="crm-order__empty">Axtarış nəticələri burada görünəcək.</div>';
            return;
        }
        if (!list.length) {
            els.results.innerHTML = '<div class="crm-order__empty">Heç nə tapılmadı.</div>';
            return;
        }
        els.results.innerHTML = list.map((p, i) => `
            <div class="crm-result" data-index="${i}">
                <div class="crm-result__name">${esc(p.name)}<span class="crm-result__brand">${esc([p.brand, p.type, p.gender].filter(Boolean).join(' · '))}</span></div>
                ${p.variants.length === 1
                    // Tək variant: seçim yoxdur, ox olmadan sadə sahə
                    ? `<div class="form-control crm-result__single" data-variant data-variant-id="${p.variants[0].id}">${esc(p.variants[0].label)} · ${money(p.variants[0].price)}</div>`
                    : `<select class="form-select" data-variant>
                        ${p.variants.map(v => `<option value="${v.id}">${esc(v.label)} · ${money(v.price)}</option>`).join('')}
                    </select>`}
                <input type="number" class="form-control" data-qty value="1" min="1" max="99" aria-label="Say">
                <button type="button" class="btn btn-primary" data-add title="Səbətə əlavə et" aria-label="Səbətə əlavə et">+</button>
            </div>`).join('');
    }

    els.results.addEventListener('click', event => {
        const btn = event.target.closest('[data-add]');
        if (!btn) return;
        const row = btn.closest('.crm-result');
        const product = results[Number(row.dataset.index)];
        const variantEl = row.querySelector('[data-variant]');
        const variantId = Number(variantEl.dataset.variantId || variantEl.value);
        const variant = product.variants.find(v => v.id === variantId);
        const qty = Math.min(99, Math.max(1, parseInt(row.querySelector('[data-qty]').value, 10) || 1));

        const existing = cart.find(i => i.variantId === variantId);
        if (existing) existing.qty = Math.min(99, existing.qty + qty);
        else cart.push({ variantId, name: product.name, brand: product.brand || '', variant: variant.label, price: Number(variant.price), qty });

        row.querySelector('[data-qty]').value = 1;
        renderCart();
    });

    // ---------- Səbət ----------
    function renderCart() {
        els.count.textContent = cart.reduce((n, i) => n + i.qty, 0);
        if (!cart.length) {
            els.cart.innerHTML = '<div class="crm-order__empty">Səbət boşdur. Soldan məhsul əlavə edin.</div>';
        } else {
            els.cart.innerHTML = cart.map((i, idx) => `
                <div class="crm-cart-item" data-index="${idx}">
                    <div class="crm-cart-item__info">
                        <div class="crm-cart-item__name">${esc(i.name)}</div>
                        <div class="crm-cart-item__meta">${esc([i.brand, i.variant, money(i.price)].filter(Boolean).join(' · '))}</div>
                        <div class="crm-cart-item__qty">
                            <button type="button" class="btn btn-outline-primary" data-step="-1" ${i.qty <= 1 ? 'disabled' : ''} aria-label="Azalt">−</button>
                            <span>${i.qty}</span>
                            <button type="button" class="btn btn-outline-primary" data-step="1" ${i.qty >= 99 ? 'disabled' : ''} aria-label="Artır">+</button>
                        </div>
                    </div>
                    <div>
                        <div class="crm-cart-item__price">${money(i.price * i.qty)}</div>
                        <button type="button" class="crm-cart-item__remove" data-remove>Sil</button>
                    </div>
                </div>`).join('');
        }
        updateTotals();
    }

    els.cart.addEventListener('click', event => {
        const item = event.target.closest('.crm-cart-item');
        if (!item) return;
        const idx = Number(item.dataset.index);
        const step = event.target.closest('[data-step]');
        if (step) cart[idx].qty = Math.min(99, Math.max(1, cart[idx].qty + Number(step.dataset.step)));
        if (event.target.closest('[data-remove]')) cart.splice(idx, 1);
        renderCart();
    });

    const subtotal = () => cart.reduce((s, i) => s + i.price * i.qty, 0);
    function deliveryFee(goods) {
        if (!goods || delivery.mode === 'free') return 0;
        if (delivery.free_from > 0 && goods >= delivery.free_from) return 0;
        return Number(delivery.fee) || 0;
    }
    const giftTotal = () => (els.giftWrap.checked ? giftFee : 0);
    // Ödəniləcək məbləğ (kredit faizi olmadan)
    const payable = () => subtotal() + deliveryFee(subtotal()) + giftTotal();
    const selectedPeriod = () => selectedCode() === 'installment'
        ? root.querySelector('input[name="crm_credit_period"]:checked') : null;
    // Yekun: hissə-hissədə faiz də daxil
    const total = () => {
        const period = selectedPeriod();
        return payable() * (1 + (period ? Number(period.dataset.rate) || 0 : 0) / 100);
    };

    function updateTotals() {
        const goods = subtotal();
        const fee = deliveryFee(goods);
        els.subtotal.textContent = money(goods);
        els.delivery.textContent = !goods ? '—' : fee ? money(fee) : 'Pulsuz';

        els.giftRow.hidden = !els.giftWrap.checked;
        els.gift.textContent = giftFee ? money(giftFee) : 'Pulsuz';

        const interest = total() - payable();
        els.creditRow.hidden = !(goods && interest > 0);
        els.creditFee.textContent = money(interest);

        els.total.textContent = money(total());
        els.footerTotal.textContent = money(total());
        updateBonus();
        updateBonusAvailability();
        updateCredit();
        validate();
    }

    // Qazanılacaq bonus: məhsulların məbləği × faiz (BonusService ilə eyni)
    function updateBonus() {
        const goods = subtotal();
        els.bonus.hidden = !goods;
        if (!goods) return;
        const code = selectedCode();
        const off = NO_BONUS.includes(code);
        els.bonus.classList.toggle('is-off', off);
        els.bonus.innerHTML = off
            ? 'Bu ödəniş üsulunda bonus hesablanmır'
            : `<span>Qazanacağı bonus</span><strong>+${money(goods * bonusRate)}</strong>`;
    }

    // ---------- Ünvan ----------
    els.address.addEventListener('change', () => {
        els.newAddress.hidden = els.address.value !== 'new';
        validate();
    });

    // ---------- Ödəniş ----------
    const paymentRadios = [...root.querySelectorAll('input[name="crm_payment_method"]')];
    const selectedCode = () => paymentRadios.find(r => r.checked)?.dataset.code;

    paymentRadios.forEach(r => r.addEventListener('change', () => {
        const code = selectedCode();
        els.birbank.hidden = code !== 'birbank_installment';
        els.installment.hidden = code !== 'installment';
        const target = !els.birbank.hidden ? els.birbank : !els.installment.hidden ? els.installment : null;
        target?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        updateTotals();
    }));
    root.querySelectorAll('input[name="crm_birbank_months"], input[name="crm_credit_period"]')
        .forEach(r => r.addEventListener('change', updateTotals));
    els.giftWrap.addEventListener('change', updateTotals);

    // Bonus balansı yetmirsə, bonusla ödəniş deaktiv
    function updateBonusAvailability() {
        const radio = paymentRadios.find(r => r.dataset.code === 'bonus_balance');
        if (!radio) return;
        const enough = !cart.length || bonusBalance >= payable();
        radio.disabled = !enough;
        radio.closest('.crm-pay').classList.toggle('is-locked', !enough);
        const hint = radio.closest('.crm-pay').querySelector('[data-bonus-hint]');
        if (hint) {
            hint.textContent = enough ? `Balans: ${money(bonusBalance)}` : `${money(payable() - bonusBalance)} çatışmır`;
            hint.classList.toggle('text-danger', !enough);
        }
        if (!enough && radio.checked) {
            radio.checked = false;
            els.birbank.hidden = els.installment.hidden = true;
        }
    }

    // Hissə-hissə: aylıq ödəniş, ümumi məbləğ, faiz məbləği
    function updateCredit() {
        const period = selectedPeriod();
        if (!period || !cart.length) {
            els.credit.hidden = true;
            return;
        }
        const months = Number(period.dataset.months) || 1;
        const full = total();
        els.credit.hidden = false;
        els.credit.querySelector('[data-credit-monthly]').textContent = money(full / months);
        els.credit.querySelector('[data-credit-total]').textContent = money(full);
        els.credit.querySelector('[data-credit-overpay]').textContent = money(full - payable());
    }

    // ---------- Yoxlama və göndərmə ----------
    function problem() {
        if (!cart.length) return 'Səbətə məhsul əlavə edin';
        if (els.address.value === 'new') {
            const f = els.newAddress;
            if (!f.querySelector('[name="city_id"]').value) return 'Şəhəri seçin';
            if (!f.querySelector('[name="address"]').value.trim()) return 'Küçə və ünvanı daxil edin';
        }
        const code = selectedCode();
        if (!code) return 'Ödəniş üsulunu seçin';
        if (code === 'birbank_installment' && !root.querySelector('input[name="crm_birbank_months"]:checked')) return 'Taksit müddətini seçin';
        if (code === 'installment' && !root.querySelector('input[name="crm_credit_period"]:checked')) return 'Kredit müddətini seçin';
        return '';
    }

    function validate() {
        const msg = problem();
        els.hint.textContent = msg;
        els.submit.disabled = !!msg;
    }
    els.newAddress.addEventListener('input', validate);

    // Şəhər: select2 (modal içində açılsın deyə dropdownParent)
    const citySelect = els.newAddress.querySelector('[name="city_id"]');
    if (window.jQuery?.fn?.select2) {
        window.jQuery(citySelect).select2({
            theme: 'bootstrap4', // Acorn admin temasının select2 dizaynı
            placeholder: citySelect.dataset.placeholder,
            width: '100%',
            dropdownParent: window.jQuery('#addOrderModal'),
            language: { noResults: () => 'Şəhər tapılmadı' },
        }).on('change', validate);
    } else {
        citySelect.addEventListener('change', validate);
    }

    // "Ətraflı": bina, blok, mərtəbə, mənzil
    const detailsToggle = $('crmOrderDetailsToggle');
    const details = $('crmOrderAddressDetails');
    detailsToggle.addEventListener('click', () => {
        details.hidden = !details.hidden;
        detailsToggle.setAttribute('aria-expanded', String(!details.hidden));
        if (!details.hidden) details.querySelector('input')?.focus();
    });

    els.submit.addEventListener('click', () => {
        if (problem()) return;
        const isNew = els.address.value === 'new';
        const code = selectedCode();
        const payload = {
            cart: cart.map(i => ({ variant_id: i.variantId, quantity: i.qty })),
            address_mode: isNew ? 'new' : 'existing',
            address_id: isNew ? null : Number(els.address.value),
            ...(isNew ? Object.fromEntries([...els.newAddress.querySelectorAll('[name]')].map(i => [i.name, i.value.trim() || null])) : {}),
            payment_method_id: Number(paymentRadios.find(r => r.checked)?.value),
            birbank_installment_months: code === 'birbank_installment'
                ? Number(root.querySelector('input[name="crm_birbank_months"]:checked')?.value) || null : null,
            credit_period_id: code === 'installment'
                ? Number(root.querySelector('input[name="crm_credit_period"]:checked')?.value) || null : null,
            gift_wrap: els.giftWrap.checked,
        };

        const label = els.submit.textContent;
        els.submit.disabled = true;
        els.submit.textContent = 'Göndərilir…';

        window.jQuery.ajax({
            url: storeUrl,
            method: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            dataType: 'json',
        }).done(res => {
            window.checkResponse(res, () => setTimeout(() => location.reload(), 1200));
            if (!res.success) {
                els.submit.disabled = false;
                els.submit.textContent = label;
            }
        }).fail(xhr => {
            window.checkError(xhr);
            els.submit.disabled = false;
            els.submit.textContent = label;
        });
    });

    // Modal açılanda axtarışa fokus
    document.getElementById('addOrderModal')?.addEventListener('shown.bs.modal', () => els.search.focus());

    renderCart();
})();
