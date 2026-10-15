// Kassa: "Yeni əməliyyat" (Məxaric / Mədaxil / Köçürmə), yeni hesab, kateqoriyalar; "Əks et" modalını doldurur.
// Sifarişin "Hesablaşmalar" tabında da istifadə olunur (anbara ödəniş modalı).
(() => {
    'use strict';

    document.querySelectorAll('[data-movement-form]').forEach(form => {
        // "Yeni əməliyyat": Məxaric / Mədaxil / Köçürmə düymələri — uyğun bölmələr, balanslar və kateqoriyalar
        const radios = form.querySelectorAll('input[name="kind"][type="radio"]');
        if (radios.length) {
            const sync = () => {
                const kind = form.querySelector('input[name="kind"]:checked')?.value;
                form.querySelectorAll('[data-for]').forEach(block => {
                    const on = block.dataset.for.split(' ').includes(kind);
                    block.hidden = !on;
                    block.querySelectorAll('select, input, textarea').forEach(i => { i.disabled = !on; });
                });
                const toggle = (option, ok) => {
                    option.hidden = option.disabled = !ok;
                    if (!ok && option.selected) option.closest('select').value = '';
                };
                form.querySelectorAll('option[data-kinds]').forEach(o => toggle(o, o.dataset.kinds.split(' ').includes(kind)));
                // Kateqoriya: Mədaxil — mədaxil və "hər ikisi", Məxaric — məxaric və "hər ikisi"
                form.querySelectorAll('option[data-type]').forEach(o => toggle(o, o.dataset.type === 'both' || o.dataset.type === (kind === 'income' ? 'income' : 'expense')));
                form.querySelectorAll('optgroup').forEach(g => { g.hidden = ![...g.children].some(o => !o.hidden); });
            };
            radios.forEach(r => r.addEventListener('change', sync));
            sync();
            return;
        }

        const kind = form.querySelector('[name="kind"]');
        const selects = { from: form.querySelector('[name="from_account_id"]'), to: form.querySelector('[name="to_account_id"]') };
        if (!kind || kind.type === 'hidden') return;

        const filter = () => {
            const option = kind.selectedOptions[0];
            ['from', 'to'].forEach(side => {
                const select = selects[side];
                if (!select) return;
                const allowed = (option?.dataset[side] || '').split(',').filter(Boolean);
                [...select.options].forEach(o => {
                    if (!o.value) return;
                    o.hidden = o.disabled = allowed.length > 0 && !allowed.includes(o.dataset.type);
                });
                if (select.selectedOptions[0]?.disabled) select.value = '';
            });
        };
        kind.addEventListener('change', filter);
        filter();
    });

    // Yeni hesab: şəxsin başlanğıc qalığı olmur (onunla hesablaşma köçürmə ilə aparılır)
    document.querySelectorAll('[data-account-form]').forEach(form => {
        const type = form.querySelector('[name="type"]');
        const opening = form.querySelector('[data-account-opening]');
        if (!type || !opening) return;
        const sync = () => {
            opening.hidden = type.value === 'person';
            opening.querySelector('input').disabled = opening.hidden;
        };
        type.addEventListener('change', sync);
        sync();
    });

    // Kateqoriya: yaratmaq və redaktə eyni pəncərədədir
    const categoryModal = document.getElementById('categoryModal');
    if (categoryModal) {
        const form = categoryModal.querySelector('form');
        categoryModal.addEventListener('show.bs.modal', event => {
            const btn = event.relatedTarget;
            if (!btn) return;
            const d = btn.dataset;
            form.action = d.action;
            form.querySelector('[name="_method"]').value = d.method || 'POST';
            categoryModal.querySelector('.modal-title').textContent = d.title;
            form.querySelector('[name="name"]').value = d.name || '';
            form.querySelector('[name="type"]').value = d.type || 'expense';
            const parent = form.querySelector('[name="parent_id"]');
            parent.value = d.parent || '';
            // Özünün altına və ya alt kateqoriyası varsa başqasının altına keçirilmir
            [...parent.options].forEach(o => { o.hidden = o.disabled = !!o.value && (o.value === d.id || d.hasChildren === '1'); });
        });
    }

    document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', event => {
        if (!confirm(f.dataset.confirm)) event.preventDefault();
    }));

    // Anbara ödəniş və anbardan geri alma (sifariş → Hesablaşmalar): hissə və məbləğ düymədən
    ['warehousePayModal', 'warehouseRefundModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (!modal) return;
        const form = modal.querySelector('form');
        modal.addEventListener('show.bs.modal', event => {
            const btn = event.relatedTarget;
            if (!btn) return;
            form.reset();
            form.querySelector('[name="order_item_allocation_id"]').value = btn.dataset.allocation || '';
            const amount = form.querySelector('[name="amount"]');
            amount.value = btn.dataset.left || '';
            // Ümumi ödənişdə (hissəyə bağlı deyil) yuxarı hədd yoxdur
            if (btn.dataset.left) amount.max = btn.dataset.left; else amount.removeAttribute('max');
            modal.querySelector('[data-pay-title]').textContent = btn.dataset.title;
            form.querySelector('button.btn-primary').disabled = false;
        });
    });

    const reverseModal = document.getElementById('reverseMovement');
    if (reverseModal) {
        const form = reverseModal.querySelector('form');
        reverseModal.addEventListener('show.bs.modal', event => {
            const btn = event.relatedTarget;
            if (!btn) return;
            form.reset();
            form.action = btn.dataset.action;
            reverseModal.querySelector('[data-reverse-title]').textContent = btn.dataset.title;
        });
    }

    // İki dəfə göndərilməsin
    document.querySelectorAll('[data-movement-form], #reverseMovement form, #warehousePayModal form').forEach(f =>
        f.addEventListener('submit', () => f.querySelectorAll('button:not([type="button"])').forEach(b => { b.disabled = true; })));
})();
