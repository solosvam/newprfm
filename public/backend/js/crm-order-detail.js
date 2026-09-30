$(document).ready(function () {
    const workspace = document.querySelector('[data-order-workspace]');
    if (workspace) {
        const storageKey = 'order-tab:' + window.location.pathname;
        const tabs = Array.from(workspace.querySelectorAll('[data-bs-toggle="tab"]'));
        const openTab = (target) => {
            const button = tabs.find(tab => tab.dataset.bsTarget === target);
            if (button && window.bootstrap) {
                const tab = bootstrap.Tab.getInstance(button) || new bootstrap.Tab(button);
                tab.show();
            }
        };
        tabs.forEach(button => button.addEventListener('shown.bs.tab', () => {
            const target = button.dataset.bsTarget;
            try { sessionStorage.setItem(storageKey, target); } catch (_) {}
            history.replaceState(null, '', window.location.pathname + window.location.search + target);
        }));
        workspace.querySelectorAll('[data-order-tab-link]').forEach(link => {
            link.addEventListener('click', event => {
                event.preventDefault();
                openTab(link.getAttribute('href'));
                workspace.querySelector('.order-detail-tabs').scrollIntoView({block: 'nearest'});
            });
        });
        let saved = null;
        try { saved = sessionStorage.getItem(storageKey); } catch (_) {}
        openTab(window.location.hash || saved);
        window.addEventListener('hashchange', () => openTab(window.location.hash));
    }

    // Məhsul ləğvi (Məhsullar tabı): nəticələr serverdə hesablanıb (data-previews), say dəyişdikcə göstərilir
    const cancelModal = document.getElementById('cancelItemModal');
    if (cancelModal) {
        const form = cancelModal.querySelector('form');
        const qty = form.querySelector('[name="quantity"]');
        const note = form.querySelector('[name="note"]');
        const reason = form.querySelector('[name="reason"]');
        const money = n => Number(n).toFixed(2) + ' AZN';
        const refundLabels = { pending: 'Karta qaytarılacaq', bonus: 'Bonus balansına qaytarılır' };
        let previews = {};

        const render = () => {
            const r = previews[qty.value];
            if (!r) return;
            const set = (key, text) => { cancelModal.querySelector('[data-r="' + key + '"]').textContent = text; };
            set('amount', '−' + money(r.amount));
            set('total', money(r.total));
            cancelModal.querySelector('[data-r-row="refund"]').hidden = !r.refund;
            if (r.refund) { set('refund-label', refundLabels[r.refund] || 'Qaytarılacaq'); set('refund', money(r.amount)); }
            cancelModal.querySelector('[data-r-row="bonus"]').hidden = !(r.bonus > 0);
            set('bonus', '−' + money(r.bonus));
        };

        cancelModal.addEventListener('show.bs.modal', event => {
            const btn = event.relatedTarget;
            if (!btn) return;
            form.reset();
            form.action = btn.dataset.action;
            previews = JSON.parse(btn.dataset.previews || '{}');
            cancelModal.querySelector('[data-cancel-item]').textContent = btn.dataset.title;
            cancelModal.querySelector('[data-cancel-active]').textContent = btn.dataset.active;
            qty.innerHTML = Object.keys(previews).map(q => '<option value="' + q + '">' + q + ' ədəd</option>').join('');
            qty.value = btn.dataset.active; // adətən hamısı ləğv olunur
            if (!previews[qty.value]) qty.value = Object.keys(previews)[0];
            note.required = reason.value === 'other';
            render();
        });
        qty.addEventListener('change', render);
        reason.addEventListener('change', () => { note.required = reason.value === 'other'; });
        form.addEventListener('submit', () => form.querySelector('button.btn-danger').disabled = true);
    }

    // Karta qaytarma: təsdiq pəncərəsi (Ödənişlər → Geri qaytarmalar)
    const refundModal = document.getElementById('refundConfirmModal');
    if (refundModal) {
        const form = refundModal.querySelector('form');
        refundModal.addEventListener('show.bs.modal', event => {
            const btn = event.relatedTarget;
            if (!btn) return;
            form.action = btn.dataset.action;
            form.querySelector('button.btn-primary').disabled = false;
            refundModal.querySelector('[data-refund-text]').textContent = btn.dataset.text;
        });
        // İki dəfə basılmasın
        form.addEventListener('submit', () => { form.querySelector('button.btn-primary').disabled = true; });
    }

    // data-once formalar (İcraya götür, Kuryer təyin et): iki dəfə göndərilməsin
    document.querySelectorAll('form[data-once]').forEach(form => form.addEventListener('submit', () =>
        form.querySelectorAll('button:not([type="button"])').forEach(b => { b.disabled = true; })));

    // Məhsulların tarixçəsi: məhsula görə süzgəc (məhsulsuz hadisələr — sorğular — həmişə görünür)
    const historyFilter = document.querySelector('[data-history-filter]');
    if (historyFilter) {
        historyFilter.addEventListener('click', event => {
            const btn = event.target.closest('[data-item]');
            if (!btn) return;
            historyFilter.querySelectorAll('[data-item]').forEach(b => {
                b.classList.toggle('btn-primary', b === btn);
                b.classList.toggle('btn-outline-primary', b !== btn);
            });
            const id = btn.dataset.item;
            document.querySelectorAll('.od-events > li').forEach(li => {
                li.hidden = !!id && !!li.dataset.item && li.dataset.item !== id;
            });
        });
    }

    // Ödəniş linki: kopyala
    $(document).on('click', '[data-pay-link-copy]', function () {
        const btn = this;
        const input = btn.closest('.input-group').querySelector('[data-pay-link-url]');
        const done = () => {
            btn.textContent = 'Kopyalandı ✓';
            setTimeout(() => { btn.textContent = 'Kopyala'; }, 1500);
        };
        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(input.value).then(done);
        } else {
            input.select();
            document.execCommand('copy');
            done();
        }
    });

    // Ödəniş linki: SMS ilə göndər (CrmController::sendPayLink)
    $(document).on('click', '[data-pay-link-sms]', function () {
        const btn = $(this);
        const label = btn.text();
        btn.prop('disabled', true).text('Göndərilir…');
        $.post(btn.data('url'))
            .done(res => window.checkResponse(res))
            .fail(xhr => window.checkError(xhr))
            .always(() => btn.prop('disabled', false).text(label));
    });

});
