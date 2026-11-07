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

    // Məkan: quraşdırılmış tətbiqdə (telefon) icazə verilməyənə qədər tətbiq açılmır (.geo-gate);
    // icazə varsa yer hər açılışda və tətbiqə qayıdanda serverə göndərilir (ən çox dəqiqədə bir). Kompüterdə məcbur deyil.
    (function geo() {
        const body = document.body;
        const url = body.dataset.geoUrl;
        const allow = document.querySelector('[data-geo-allow]');
        const deniedBox = document.querySelector('[data-geo-denied]');
        const mustAsk = window.matchMedia('(pointer: coarse)').matches && !window.matchMedia('(display-mode: browser)').matches;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        let lastSent = 0;
        const state = name => {
            body.classList.remove('geo-check', 'geo-need', 'geo-ok');
            body.classList.add('geo-' + name);
        };
        const denied = () => {
            if (!mustAsk) { state('ok'); return; }
            state('need');
            if (deniedBox) deniedBox.hidden = false;
            if (allow) allow.textContent = 'Yenidən yoxla';
        };
        const send = position => {
            if (!url || Date.now() - lastSent < 60000) return;
            lastSent = Date.now();
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ lat: position.coords.latitude, lng: position.coords.longitude, accuracy: position.coords.accuracy }),
            }).catch(() => {});
        };
        const locate = () => navigator.geolocation.getCurrentPosition(
            position => { state('ok'); send(position); },
            error => { if (error.code === error.PERMISSION_DENIED) denied(); else state('ok'); }, // yer tapılmadı — icazə var, tətbiq açılır
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
        );

        if (!('geolocation' in navigator)) { state('ok'); return; }
        if (allow) allow.addEventListener('click', locate); // icazə pəncərəsi düymə ilə (iPhone başqa cür göstərmir)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && body.classList.contains('geo-ok')) locate();
        });
        const start = permission => {
            if (permission === 'granted') { state('ok'); locate(); }
            else if (permission === 'denied') denied();
            else if (mustAsk) state('need');
            else state('ok'); // kompüter: soruşmuruq
        };
        if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({ name: 'geolocation' }).then(status => {
                start(status.state);
                status.onchange = () => start(status.state);
            }).catch(() => start('prompt'));
        } else {
            start('prompt');
        }
    })();

    // Push bildirişləri (OneSignal): kuryer "user-{id}" kimi bağlanır — server ona sifariş təyin olunanda göndərir (CourierPush).
    // İcazə yalnız düymə ilə (iPhone başqa cür icazə vermir); rədd edilibsə — telefon ayarlarına yönləndirən mətn.
    const pushApp = document.body.dataset.pushApp;
    const pushAsk = document.querySelector('[data-push-ask]');
    if (pushApp && pushAsk) {
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        window.OneSignalDeferred.push(async OneSignal => {
            try {
                await OneSignal.init({
                    appId: pushApp,
                    serviceWorkerPath: 'kuryer/sw.js',
                    serviceWorkerParam: { scope: '/kuryer' },
                    promptOptions: { slidedown: { prompts: [] } }, // öz zolağımız var
                });
                await OneSignal.login(document.body.dataset.pushUser);
            } catch (e) {
                return; // bildiriş əsas işə mane olmamalıdır
            }
            const refresh = () => {
                const supported = OneSignal.Notifications.isPushSupported();
                const denied = typeof Notification !== 'undefined' && Notification.permission === 'denied';
                const on = OneSignal.Notifications.permission && OneSignal.User.PushSubscription.optedIn !== false;
                pushAsk.hidden = !supported || on;
                pushAsk.classList.toggle('is-denied', denied);
                pushAsk.querySelector('[data-push-text]').textContent = denied
                    ? 'Bildirişlər bağlıdır. Telefonun ayarlarında PS Kuryer üçün bildirişlərə icazə verin.'
                    : 'Yeni sifarişlərdən dərhal xəbər tutmaq üçün bildirişləri açın.';
            };
            pushAsk.querySelector('[data-push-enable]').addEventListener('click', async () => {
                try {
                    if (OneSignal.Notifications.permission) await OneSignal.User.PushSubscription.optIn();
                    else await OneSignal.Notifications.requestPermission();
                } catch (e) { /* */ }
                refresh();
            });
            OneSignal.Notifications.addEventListener('permissionChange', refresh);
            refresh();
        });
    }

    // Service worker: /kuryer/sw.js, əhatəsi /kuryer (server Service-Worker-Allowed başlığı ilə icazə verir)
    const sw = document.body.dataset.sw;
    if (sw && 'serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register(sw, { scope: '/kuryer' }).catch(() => {}));
    }
})();
