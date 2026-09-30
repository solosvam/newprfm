// Anbar portalı (/w/{token}): seçilən mövcudluğa görə sahələr
//  Var → qiymət · Qismən → say + qiymət · Yoxdur → yalnız qeyd
(() => {
    'use strict';
    document.querySelectorAll('[data-wp-form]').forEach(form => {
        const qtyField = form.querySelector('[data-field="quantity"]');
        const priceField = form.querySelector('[data-field="price"]');
        const qty = qtyField.querySelector('input');
        const price = priceField.querySelector('input');

        const update = () => {
            const value = form.querySelector('[name="availability"]:checked')?.value;
            const partial = value === 'partial';
            const available = partial || value === 'available';
            qtyField.hidden = !partial;
            qty.disabled = qty.required = false;
            qty.disabled = !partial;
            qty.required = partial;
            priceField.hidden = !available;
            price.disabled = !available;
            price.required = available;
        };
        form.querySelectorAll('[name="availability"]').forEach(radio => radio.addEventListener('change', () => {
            update();
            // Qiymət sahəsinə keçid — mobildə klaviatura dərhal açılsın
            const next = form.querySelector('[name="availability"]:checked').value === 'partial' ? qty : price;
            if (!next.disabled) next.focus();
        }));
        form.addEventListener('submit', () => { form.querySelector('.wp-submit').disabled = true; });
        update();
    });

    // Seçilənlər → "Problem var": "Qiymət dəyişib" seçiləndə yeni qiymət sahəsi
    document.querySelectorAll('[data-wp-problem]').forEach(form => {
        const field = form.querySelector('[data-field="price"]');
        const input = field.querySelector('input');
        const update = () => {
            const priced = form.querySelector('[name="problem_type"]:checked')?.value === 'price_changed';
            field.hidden = !priced;
            input.disabled = !priced;
            input.required = priced;
        };
        form.querySelectorAll('[name="problem_type"]').forEach(radio => radio.addEventListener('change', () => {
            update();
            if (!input.disabled) input.focus();
        }));
        update();
    });

    // Təsdiq/problem formaları: iki dəfə göndərilməsin
    document.querySelectorAll('form[data-wp-once]').forEach(form => form.addEventListener('submit', () =>
        form.querySelectorAll('button[type="submit"]').forEach(b => { b.disabled = true; })));
})();
