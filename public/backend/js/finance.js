// Kassa: "Yeni əməliyyat" — növə görə hesab siyahılarını süzür; "Əks et" modalını doldurur.
// Sifarişin "Hesablaşmalar" tabında da istifadə olunur (anbara ödəniş modalı).
(() => {
    'use strict';

    document.querySelectorAll('[data-movement-form]').forEach(form => {
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
        // Xərc: adı məcburidir ("Ofis icarəsi — oktyabr"); alan hesab yeganə "Xərclər" hesabıdır
        const note = form.querySelector('[name="note"]');
        const noteLabel = form.querySelector('[data-note-label]');
        const expense = () => {
            const isExpense = kind.value === 'expense';
            if (note) {
                note.required = isExpense;
                note.placeholder = isExpense ? 'Məs.: Ofis icarəsi — oktyabr' : '';
            }
            if (noteLabel) noteLabel.textContent = isExpense ? 'Xərcin adı' : 'Qeyd';
            if (isExpense && selects.to) {
                const only = [...selects.to.options].filter(o => o.value && !o.disabled);
                if (only.length === 1) selects.to.value = only[0].value;
            }
        };
        kind.addEventListener('change', () => { filter(); expense(); });
        filter();
        expense();
    });

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
