/**
 * Web push (OneSignal v16).
 *  - App ID və müştəri ID-si #app-data → push-dan gəlir (inline JS yoxdur).
 *  - Daxil olmuş müştəri OneSignal-da "customer-{id}" xarici ID-si ilə bağlanır — server sonra
 *    səbət/qiymət bildirişlərini konkret müştəriyə göndərə bilsin. Çıxışdan sonra bağ açılır.
 *  - İcazə avtomatik istənmir (panelde Auto Prompt söndürülüb): yalnız maraq anında —
 *    sifariş tamamlananda, məhsul seçilmişlərə əlavə olunanda və ya window.psPush.prompt() çağırılanda.
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

    /** Slide prompt: icazə verilməyibsə; müştəri "Sonra" deyibsə OneSignal özü bir müddət təkrar göstərmir */
    function prompt() {
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
