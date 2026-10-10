// Anbar təminatı (backend/procurement/order-content.blade.php): yan modalları doldurur.
// Bootstrap 5.0.1 — native "show.bs.modal" hadisəsi, relatedTarget = kliklənən düymə.
(() => {
    'use strict';

    // SMS düymələri (data-once): iki dəfə göndərilməsin. CRM səhifəsində crm-order-detail.js də bağlayır — dataset ilə bir dəfə.
    document.querySelectorAll('form[data-once]').forEach(form => {
        if (form.dataset.onceBound) return;
        form.dataset.onceBound = '1';
        form.addEventListener('submit', () => form.querySelectorAll('button:not([type="button"])').forEach(b => { b.disabled = true; }));
    });

    // Zərərli anbar seçimi (data-confirm): operator təsdiqləməsə göndərilmir
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); });
    });

    const offerModal = document.getElementById('procOfferModal');
    if (offerModal) {
        const form = offerModal.querySelector('form');
        const qty = form.querySelector('[name="available_quantity"]');
        const cost = form.querySelector('[name="unit_cost"]');
        const costBox = form.querySelector('[data-offer-cost]');
        let requested = 1;

        // Tam var → tələb qədər, Yoxdur → 0 (qiymət lazım deyil), Qismən → operator yazır
        const applyAnswer = () => {
            const answer = form.querySelector('[name="answer"]:checked').value;
            qty.max = requested;
            if (answer === 'full') qty.value = requested;
            if (answer === 'none') qty.value = 0;
            if (answer === 'part' && (qty.value === '' || Number(qty.value) >= requested || Number(qty.value) === 0)) qty.value = Math.max(1, requested - 1);
            qty.readOnly = answer !== 'part';
            costBox.hidden = answer === 'none';
            cost.required = answer !== 'none';
            if (answer === 'none') cost.value = '';
        };

        offerModal.addEventListener('show.bs.modal', event => {
            const btn = event.relatedTarget;
            if (!btn) return;
            form.reset();
            form.action = btn.dataset.action;
            requested = Number(btn.dataset.requested) || 1;
            offerModal.querySelector('[data-offer-title]').textContent = btn.dataset.title;
            offerModal.querySelector('[data-offer-requested]').textContent = requested;
            applyAnswer();
        });
        offerModal.addEventListener('shown.bs.modal', () => cost.focus());
        form.querySelectorAll('[name="answer"]').forEach(r => r.addEventListener('change', applyAnswer));
        form.addEventListener('submit', () => form.querySelector('[name="answer"]').closest('.btn-group').querySelectorAll('input').forEach(i => { i.disabled = true; }));
    }

    const cancelModal = document.getElementById('procCancelModal');
    if (cancelModal) {
        const form = cancelModal.querySelector('form');
        cancelModal.addEventListener('show.bs.modal', event => {
            const btn = event.relatedTarget;
            if (!btn) return;
            form.reset();
            form.action = btn.dataset.action;
            cancelModal.querySelector('[data-cancel-title]').textContent = btn.dataset.title;
        });
        cancelModal.addEventListener('shown.bs.modal', () => form.querySelector('textarea').focus());
    }

    // Problem bildir / Davam et: form ünvanı və başlıq düymədən; "qiymət dəyişdi"də yeni qiymət sahəsi
    ['procProblemModal', 'procResumeModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (!modal) return;
        const form = modal.querySelector('form');
        modal.addEventListener('show.bs.modal', event => {
            const btn = event.relatedTarget;
            if (!btn) return;
            form.reset();
            form.action = btn.dataset.action;
            modal.querySelector('[data-modal-title]').textContent = btn.dataset.title;
            const priceBox = modal.querySelector('[data-resume-price]');
            if (priceBox) {
                const price = btn.dataset.price || '';
                priceBox.hidden = !price;
                const input = priceBox.querySelector('input');
                input.required = !!price;
                input.value = price;
            }
        });
    });

    // Göndərəndə düyməni bağla (iki dəfə basılmasın)
    document.querySelectorAll('.proc-item form, #procRequestModal form, #procOfferModal form, #procCancelModal form, #procProblemModal form, #procResumeModal form').forEach(form => {
        form.addEventListener('submit', () => form.querySelectorAll('button:not([type="button"])').forEach(b => { b.disabled = true; }));
    });
})();

// Price listlərdə axtarış: yazdıqca nəticə (ucuzdan bahaya), hər sətirdə anbar, qiymət və siyahının tarixi
(() => {
    const input = document.querySelector('[data-list-search]');
    const box = document.querySelector('[data-list-results]');
    if (!input || !box) return;
    const el = (tag, cls, text) => { const node = document.createElement(tag); if (cls) node.className = cls; if (text !== undefined) node.textContent = text; return node; };
    let timer = null;
    let last = 0;
    const run = () => {
        const q = input.value.trim();
        if (q.length < 2) { box.replaceChildren(el('p', 'text-muted mb-0', 'Ən azı 2 hərf yazın. Sözlərin sırası vacib deyil.')); return; }
        const id = ++last;
        fetch(input.dataset.listSearch + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(response => response.json())
            .then(data => {
                if (id !== last) return; // köhnə sorğunun cavabı
                const items = data.items || [];
                if (!items.length) { box.replaceChildren(el('p', 'text-muted mb-0', 'Heç bir price listdə tapılmadı.')); return; }
                box.replaceChildren(...items.map(item => {
                    const row = el('div', 'd-flex justify-content-between align-items-baseline gap-3 border-bottom py-2');
                    const left = el('div');
                    left.append(el('div', '', item.name), el('div', 'text-muted text-small', item.warehouse + ' · siyahı ' + item.date));
                    row.append(left, el('strong', 'text-nowrap', item.price + ' AZN'));
                    return row;
                }));
            })
            .catch(() => box.replaceChildren(el('p', 'text-danger mb-0', 'Axtarış alınmadı.')));
    };
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 300); });
})();

// Yığılan bölmənin (data-remember-collapse) açıq/bağlı vəziyyəti brauzerdə yadda qalır — bütün sifarişlər üçün ortaq
document.querySelectorAll('[data-remember-collapse]').forEach(panel => {
    const key = 'collapse:' + panel.dataset.rememberCollapse;
    const toggler = document.querySelector('[data-bs-target="#' + panel.id + '"]');
    let saved = null;
    try { saved = localStorage.getItem(key); } catch (_) {}
    if (saved === 'open') {
        panel.classList.add('show');
        toggler?.setAttribute('aria-expanded', 'true');
    }
    panel.addEventListener('shown.bs.collapse', () => { try { localStorage.setItem(key, 'open'); } catch (_) {} });
    panel.addEventListener('hidden.bs.collapse', () => { try { localStorage.setItem(key, 'closed'); } catch (_) {} });
});
