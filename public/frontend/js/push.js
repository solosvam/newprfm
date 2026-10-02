/**
 * Web push (OneSignal v16).
 *  - App ID və müştəri ID-si #app-data → push-dan gəlir (inline JS yoxdur).
 *  - Daxil olmuş müştəri OneSignal-da "customer-{id}" xarici ID-si ilə bağlanır — server sonra
 *    səbət/qiymət bildirişlərini konkret müştəriyə göndərə bilsin. Çıxışdan sonra bağ açılır.
 *  - İcazə avtomatik istənmir (panelde Auto Prompt söndürülüb): yalnız maraq anında —
 *    sifariş tamamlananda, məhsul seçilmişlərə əlavə olunanda və ya window.psPush.prompt() çağırılanda.
 *  - iPhone-da brauzer tabında icazə mümkün deyil — "Ana ekrana əlavə et" təlimatı göstərilir.
 */
(function () {
    'use strict';

    let data = {};
    try {
        data = JSON.parse(document.getElementById('app-data')?.textContent || '{}').push || {};
    } catch (e) {
        return;
    }
    if (!data.appId) return;

    const LINKED_KEY = 'ps_push_external_id';
    const storage = {
        get: () => { try { return localStorage.getItem(LINKED_KEY); } catch (e) { return null; } },
        set: (v) => { try { v ? localStorage.setItem(LINKED_KEY, v) : localStorage.removeItem(LINKED_KEY); } catch (e) { /* gizli rejim */ } },
    };

    window.OneSignalDeferred = window.OneSignalDeferred || [];
    const withSdk = (fn) => window.OneSignalDeferred.push(fn);

    withSdk(async function (OneSignal) {
        await OneSignal.init({appId: data.appId, serviceWorkerPath: '/OneSignalSDKWorker.js'});

        const externalId = data.customerId ? 'customer-' + data.customerId : null;
        try {
            if (externalId && storage.get() !== externalId) {
                await OneSignal.login(externalId);
                storage.set(externalId);
            } else if (!externalId && storage.get()) {
                await OneSignal.logout();
                storage.set(null);
            }
        } catch (e) { /* bildiriş əsas işə mane olmamalıdır */ }

        if (data.promptNow) prompt();
    });

    /**
     * iPhone/iPad (iOS 16.4+): push yalnız ana ekrana əlavə olunmuş saytda (standalone) işləyir.
     * Brauzer tabındadırsa (Safari və ya Chrome — ikisi də WebKit), icazə əvəzinə təlimat göstərilir.
     */
    const ua = navigator.userAgent;
    const isIos = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const iosVersion = (() => {
        const m = ua.match(/OS (\d+)_(\d+)/);
        return m ? Number(m[1]) + Number(m[2]) / 100 : 99; // iPadOS "Mac" kimi görünür — versiya yoxdur, yeni sayılır
    })();
    const standalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
    const IOS_DISMISSED_KEY = 'ps_push_ios_dismissed';
    const IOS_SNOOZE_MS = 7 * 24 * 3600 * 1000;

    function iosGuide() {
        if (document.querySelector('.push-ios') || !data.ios) return;
        try {
            if (Date.now() - Number(localStorage.getItem(IOS_DISMISSED_KEY) || 0) < IOS_SNOOZE_MS) return;
        } catch (e) { /* */ }

        const t = data.ios;
        const box = document.createElement('div');
        box.className = 'push-ios';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-label', t.title);
        const share = '<svg class="push-ios__share" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="M8 7l4-4 4 4"/>'
            + '<path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>';
        const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
        box.innerHTML = '<button type="button" class="push-ios__close" aria-label="' + esc(t.close) + '">&times;</button>'
            + '<p class="push-ios__title">' + esc(t.title) + '</p>'
            + '<p class="push-ios__text">' + esc(t.text) + '</p>'
            + '<ol class="push-ios__steps"><li>' + esc(t.step1) + ' ' + share + '</li><li>' + esc(t.step2) + '</li><li>' + esc(t.step3) + '</li></ol>';
        box.querySelector('.push-ios__close').addEventListener('click', () => {
            try { localStorage.setItem(IOS_DISMISSED_KEY, String(Date.now())); } catch (e) { /* */ }
            box.classList.remove('is-open');
            setTimeout(() => box.remove(), 300);
        });
        document.body.appendChild(box);
        requestAnimationFrame(() => box.classList.add('is-open'));
    }

    /** Slide prompt: icazə verilməyibsə; müştəri "Sonra" deyibsə OneSignal özü bir müddət təkrar göstərmir */
    function prompt() {
        if (isIos && !standalone) {
            if (iosVersion >= 16.4) iosGuide();
            return;
        }
        withSdk(async function (OneSignal) {
            try {
                if (!OneSignal.Notifications.isPushSupported() || OneSignal.Notifications.permission) return;
                await OneSignal.Slidedown.promptPush();
            } catch (e) { /* */ }
        });
    }

    window.psPush = {prompt};

    // Məhsul seçilmişlərə əlavə olunanda (ürək aktivləşəndə) — "qiyməti düşəndə xəbər verək"
    document.addEventListener('click', (event) => {
        const button = event.target.closest('.fav-btn, .favorite-toggle');
        if (!button) return;
        setTimeout(() => { if (button.classList.contains('is-favorite')) prompt(); }, 600);
    });
})();
