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

    // Balans: bulanıq açılır; göz düyməsi — görünür (0-dan məbləğə sayılır) və yenidən gizlənir
    const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.addEventListener('click', event => {
        const toggle = event.target.closest('[data-money-toggle]');
        if (!toggle) return;
        const value = toggle.closest('[data-money]').querySelector('[data-money-value]');
        const target = parseFloat(value.dataset.moneyValue) || 0;
        const show = toggle.getAttribute('aria-pressed') !== 'true';
        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        toggle.setAttribute('aria-label', show ? 'Məbləği gizlət' : 'Məbləği göstər');
        cancelAnimationFrame(Number(value.dataset.frame || 0));
        if (!show) {
            value.classList.add('is-hidden');
            value.textContent = money.format(target) + ' AZN';
            return;
        }
        value.classList.remove('is-hidden');
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const duration = reduce ? 0 : 700;
        const started = performance.now();
        const step = now => {
            const progress = duration ? Math.min(1, (now - started) / duration) : 1;
            const eased = 1 - Math.pow(1 - progress, 3); // sonda yavaşlayır
            value.textContent = money.format(target * eased) + ' AZN';
            if (progress < 1) value.dataset.frame = String(requestAnimationFrame(step));
        };
        value.dataset.frame = String(requestAnimationFrame(step));
    });

    // Siyahı səhifələri (data-refresh): tətbiqə 1 dəqiqədən sonra qayıdanda — təzə məlumat
    if (document.body.hasAttribute('data-refresh')) {
        let hiddenAt = 0;
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') hiddenAt = Date.now();
            else if (hiddenAt && Date.now() - hiddenAt > 60000) window.location.reload();
        });
    }

    // Quraşdırma ekranı (.gate, yalnız telefonda brauzerdə görünür — app.css):
    // Android (Chrome) — "Quraşdır" düyməsi brauzerin quraşdırma pəncərəsini açır; iPhone — Safari addımları
    const gate = document.querySelector('.gate');
    if (gate) {
        const installButton = gate.querySelector('[data-install]');
        const iosSteps = gate.querySelector('[data-gate-ios]');
        const otherSteps = gate.querySelector('[data-gate-other]');
        const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (isIos) {
            iosSteps.hidden = false;
            otherSteps.hidden = true;
        }
        let deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', event => {
            event.preventDefault();
            deferredPrompt = event;
            installButton.hidden = false;
            otherSteps.hidden = true;
        });
        installButton.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice.catch(() => null);
            deferredPrompt = null;
            installButton.hidden = true;
        });
        // Quraşdırıldı: tətbiqi ikondan açmaq qalır
        window.addEventListener('appinstalled', () => {
            installButton.hidden = true;
            otherSteps.hidden = true;
            gate.querySelector('.gate__text').textContent = 'Tətbiq quraşdırıldı. İndi ana ekrandakı PS Kuryer ikonundan açın.';
        });
    }

    // Service worker: /kuryer/sw.js, əhatəsi /kuryer (server Service-Worker-Allowed başlığı ilə icazə verir)
    const sw = document.body.dataset.sw;
    if (sw && 'serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register(sw, { scope: '/kuryer' }).catch(() => {}));
    }
})();
