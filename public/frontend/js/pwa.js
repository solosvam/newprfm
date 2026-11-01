/**
 * Saytın tətbiq kimi quraşdırılması (PWA).
 *  - Service worker: /OneSignalSDKWorker.js (push + offline səhifə) — OneSignal ilə eyni fayl, eyni scope.
 *  - Android / Chrome: brauzerin "beforeinstallprompt" hadisəsi saxlanılır, mobil ekranda öz panelimiz
 *    "Quraşdır" düyməsi ilə göstərilir; basanda brauzerin quraşdırma pəncərəsi açılır.
 *  - iPhone / iPad: brauzer quraşdırma pəncərəsi vermir — "Paylaş → Ana ekrana əlavə et" təlimatı.
 *  - Bağlanan panel 14 gün göstərilmir; tətbiq kimi açılıbsa (standalone) heç göstərilmir.
 * Mətnlər #app-data → pwa-dan gəlir.
 */
(function () {
    'use strict';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/OneSignalSDKWorker.js').catch(() => { /* quraşdırma olmasa da sayt işləyir */ });
        });
    }

    let t = null;
    try {
        t = JSON.parse(document.getElementById('app-data')?.textContent || '{}').pwa || null;
    } catch (e) {
        return;
    }
    if (!t) return;

    const standalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
    if (standalone) return;

    const DISMISSED_KEY = 'ps_pwa_dismissed';
    const SNOOZE_MS = 14 * 24 * 3600 * 1000;
    const snoozed = () => {
        try { return Date.now() - Number(localStorage.getItem(DISMISSED_KEY) || 0) < SNOOZE_MS; } catch (e) { return false; }
    };
    const mobile = () => window.matchMedia('(max-width: 760px)').matches;

    const ua = navigator.userAgent;
    const isIos = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    let deferred = null;
    let box = null;

    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    const share = '<svg class="pwa-install__share" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="M8 7l4-4 4 4"/>'
        + '<path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>';

    function close(remember) {
        if (!box) return;
        if (remember) {
            try { localStorage.setItem(DISMISSED_KEY, String(Date.now())); } catch (e) { /* */ }
        }
        const node = box;
        box = null;
        node.classList.remove('is-open');
        setTimeout(() => node.remove(), 300);
    }

    function show() {
        if (box || snoozed() || !mobile()) return;
        // Başqa aşağı panel açıqdırsa (cookie, iPhone push təlimatı, admin popup-u) — bağlanandan sonra
        if (document.querySelector('#cookieBar:not([hidden]), .push-ios, .site-popup')) {
            setTimeout(show, 3000);
            return;
        }
        box = document.createElement('div');
        box.className = 'pwa-install';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-label', t.title);
        box.innerHTML = '<button type="button" class="pwa-install__close" aria-label="' + esc(t.close) + '">&times;</button>'
            + '<img class="pwa-install__icon" src="/pwa/icon-192.png" alt="" width="48" height="48">'
            + '<div class="pwa-install__body"><p class="pwa-install__title">' + esc(t.title) + '</p>'
            + (deferred
                ? '<p class="pwa-install__text">' + esc(t.text) + '</p>'
                  + '<button type="button" class="btn btn-dark pwa-install__btn">' + esc(t.install) + '</button>'
                : '<ol class="pwa-install__steps"><li>' + esc(t.iosStep1) + ' ' + share + '</li><li>' + esc(t.iosStep2) + '</li></ol>')
            + '</div>';
        box.querySelector('.pwa-install__close').addEventListener('click', () => close(true));
        box.querySelector('.pwa-install__btn')?.addEventListener('click', async () => {
            if (!deferred) return;
            const prompt = deferred;
            deferred = null;
            prompt.prompt();
            const choice = await prompt.userChoice.catch(() => null);
            close(choice?.outcome !== 'accepted');
        });
        document.body.appendChild(box);
        requestAnimationFrame(() => box?.classList.add('is-open'));
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferred = event;
        setTimeout(show, 4000);
    });
    window.addEventListener('appinstalled', () => {
        deferred = null;
        close(true);
    });

    // iPhone-da "beforeinstallprompt" yoxdur — təlimat göstərilir
    if (isIos) setTimeout(show, 4000);
})();
