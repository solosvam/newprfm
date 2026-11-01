/**
 * Saytın tətbiq kimi quraşdırılması (PWA).
 *  - Service worker: /OneSignalSDKWorker.js (push + offline səhifə) — OneSignal ilə eyni fayl, eyni scope.
 *  - Android / Chrome: brauzerin "beforeinstallprompt" hadisəsi saxlanılır, mobil ekranda öz panelimiz
 *    "Quraşdır" düyməsi ilə göstərilir; basanda brauzerin quraşdırma pəncərəsi açılır.
 *  - iPhone / iPad: brauzer quraşdırma pəncərəsi vermir — brauzerə görə təlimat (iosSteps):
 *    Safari 26+ — Paylaş ünvan sətrindəki menyunun (≡) içindədir; köhnə Safari və Chrome — Paylaş düyməsi birbaşa görünür;
 *    Instagram / Facebook / TikTok və s. daxili brauzeri ana ekrana əlavə edə bilmir — "Safari-də açın".
 *  - Bağlanan panel həmin gün bir daha göstərilmir, ertəsi gün yenidən çıxır; tətbiq kimi açılıbsa (standalone) heç göstərilmir.
 *  - Statistika (/pwa/event → admin "Ana ekran"): Android-də "appinstalled" = quraşdırma; ana ekrandan açılanda
 *    sessiyada bir "open", cihazın ilk açılışı həm də quraşdırma sayılır (iPhone-da quraşdırma hadisəsi yoxdur).
 *    Cihaz — localStorage-da təsadüfi id; model və Android versiyası Client Hints-dən (yalnız Chrome).
 * Mətnlər #app-data → pwa-dan gəlir.
 */
(function () {
    'use strict';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/OneSignalSDKWorker.js').catch(() => { /* quraşdırma olmasa da sayt işləyir */ });
        });
    }

    const store = {
        get: (k, s = localStorage) => { try { return s.getItem(k); } catch (e) { return null; } },
        set: (k, v, s = localStorage) => { try { s.setItem(k, v); return true; } catch (e) { return false; } },
    };
    function deviceId() {
        let id = store.get('ps_pwa_device');
        if (!id) {
            id = window.crypto?.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
            if (!store.set('ps_pwa_device', id)) return null;
        }
        return id;
    }
    async function track(event, method) {
        const device = deviceId();
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!device || !token) return;
        const body = {
            event, method, device,
            screen: screen.width + 'x' + screen.height,
            dpr: Math.round((window.devicePixelRatio || 1) * 10) / 10,
            lang: (navigator.language || '').slice(0, 10),
            dark: window.matchMedia('(prefers-color-scheme: dark)').matches,
        };
        try {
            const hints = await navigator.userAgentData?.getHighEntropyValues?.(['model', 'platformVersion']);
            if (hints) { body.model = hints.model || null; body.os = hints.platformVersion || null; }
        } catch (e) { /* */ }
        fetch('/pwa/event', {
            method: 'POST', keepalive: true, credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify(body),
        }).catch(() => { /* statistika əsas işə mane olmamalıdır */ });
    }
    function markInstalled(method) {
        if (store.get('ps_pwa_installed') === '1') return;
        store.set('ps_pwa_installed', '1');
        track('install', method);
    }

    const launched = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
    if (launched) {
        markInstalled('first_open');
        if (store.get('ps_pwa_open', sessionStorage) !== '1') {
            store.set('ps_pwa_open', '1', sessionStorage);
            track('open');
        }
    }
    window.addEventListener('appinstalled', () => markInstalled('prompt'));

    let t = null;
    try {
        t = JSON.parse(document.getElementById('app-data')?.textContent || '{}').pwa || null;
    } catch (e) {
        return;
    }
    if (!t) return;

    if (launched) return;

    const DISMISSED_KEY = 'ps_pwa_dismissed';
    const today = () => new Date().toDateString(); // cihazın öz günü
    const snoozed = () => {
        try { return localStorage.getItem(DISMISSED_KEY) === today(); } catch (e) { return false; }
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
    const menu = '<svg class="pwa-install__share" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        + 'stroke-linecap="round" aria-hidden="true"><path d="M5 7h14"/><path d="M5 12h14"/><path d="M5 17h14"/></svg>';

    /** iPhone təlimatı — brauzerə görə (User-Agent) */
    function iosGuide() {
        if (/Instagram|FBAN|FBAV|FB_IAB|musical_ly|Bytedance|Line\/|Snapchat|Pinterest|GSA\//.test(ua)) {
            return '<p class="pwa-install__text">' + esc(t.iosInApp) + '</p>';
        }
        return '<ol class="pwa-install__steps"><li>' + iosSteps().join('</li><li>') + '</li></ol>';
    }
    function iosSteps() {
        const add = esc(t.iosStep2);
        const safari = !/CriOS|FxiOS|EdgiOS|OPiOS|YaBrowser/.test(ua);
        const safariVersion = Number((ua.match(/Version\/(\d+)/) || [])[1] || 0);
        if (safari && safariVersion >= 26) return [esc(t.iosMenu) + ' ' + menu, esc(t.iosShare) + ' ' + share, add];
        return [esc(t.iosStep1) + ' ' + share, add];
    }

    function close(remember) {
        if (!box) return;
        if (remember) {
            try { localStorage.setItem(DISMISSED_KEY, today()); } catch (e) { /* */ }
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
                : iosGuide())
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
