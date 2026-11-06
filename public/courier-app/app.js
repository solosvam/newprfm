// Kuryer tətbiqi (/kuryer): formaların bir dəfə göndərilməsi, təsdiq sualı, şəklin böyüdülməsi,
// ana səhifəyə qayıdanda yenilənmə və service worker (zəif internetdə "oflayn" səhifəsi).
(function () {
    'use strict';

    // Forma: data-confirm — təsdiq sualı ({amount} → formadakı məbləğ); data-once — iki dəfə göndərilməsin (zəif internet)
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const question = form.dataset.confirm;
        if (question && !window.confirm(question.replace('{amount}', form.elements.amount ? form.elements.amount.value : ''))) {
            event.preventDefault();
            return;
        }
        if (form.hasAttribute('data-once')) {
            if (form.dataset.sent) { event.preventDefault(); return; }
            form.dataset.sent = '1';
            form.querySelectorAll('button').forEach(button => { button.disabled = true; button.classList.add('is-busy'); });
        }
    });

    // Geri düyməsi ilə (bfcache) qayıdanda bloklanmış düymələr açılsın
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        document.querySelectorAll('form[data-sent]').forEach(form => {
            delete form.dataset.sent;
            form.querySelectorAll('button').forEach(button => { button.disabled = false; button.classList.remove('is-busy'); });
        });
    });

    // Məhsulun şəkli: basanda tam ekranda; ekrana, × düyməsinə və ya Esc — bağlanır
    function closeZoom() {
        const open = document.querySelector('.zoom');
        if (open) open.remove();
    }
    document.addEventListener('click', event => {
        const thumb = event.target.closest('[data-zoom]');
        if (!thumb) return;
        const overlay = document.createElement('div');
        overlay.className = 'zoom';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Məhsulun şəkli');
        const image = document.createElement('img');
        image.src = thumb.dataset.zoom;
        image.alt = '';
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'zoom__close';
        close.setAttribute('aria-label', 'Bağla');
        close.textContent = '×';
        overlay.append(image, close);
        overlay.addEventListener('click', closeZoom);
        document.body.append(overlay);
        close.focus();
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeZoom(); });

    // Siyahı səhifələri (data-refresh): tətbiqə 1 dəqiqədən sonra qayıdanda — təzə məlumat
    if (document.body.hasAttribute('data-refresh')) {
        let hiddenAt = 0;
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') hiddenAt = Date.now();
            else if (hiddenAt && Date.now() - hiddenAt > 60000) window.location.reload();
        });
    }

    // Service worker: /kuryer/sw.js, əhatəsi /kuryer (server Service-Worker-Allowed başlığı ilə icazə verir)
    const sw = document.body.dataset.sw;
    if (sw && 'serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register(sw, { scope: '/kuryer' }).catch(() => {}));
    }
})();
