// Kuryer tətbiqi (/kuryer): formaların bir dəfə göndərilməsi, təsdiq sualı, şəklin böyüdülməsi,
// ana səhifəyə qayıdanda yenilənmə və service worker (zəif internetdə "oflayn" səhifəsi).
(function () {
    'use strict';

    // Splash: bu sessiyada artıq göstərildi (cookie — server növbəti səhifələrdə çəkmir); ən azı 0.7 s, səhifə yüklənəndə yox olur
    const splash = document.querySelector('[data-splash]');
    if (splash) {
        document.cookie = 'ps_k_splash=1; path=/kuryer; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        const hide = () => {
            if (splash.classList.contains('is-hidden')) return;
            splash.classList.add('is-hidden');
            setTimeout(() => splash.remove(), 400);
        };
        const later = () => setTimeout(hide, Math.max(0, 700 - performance.now()));
        if (document.readyState === 'complete') later(); else window.addEventListener('load', later);
        setTimeout(hide, 2500); // yüklənmə uzansa belə
    }

    // iPhone-da :active (basılma hissi) yalnız səhifədə toxunuş dinləyicisi olanda işləyir
    document.addEventListener('touchstart', () => {}, { passive: true });

    // Aşağı menyu: toxunan anda düymə seçilmiş görünür və növbəti səhifə əvvəlcədən yüklənməyə başlayır
    const prefetched = new Set();
    document.addEventListener('pointerdown', event => {
        const tab = event.target.closest('.tabs__item');
        if (!tab) return;
        tab.classList.add('is-pressed');
        if (tab.href && !prefetched.has(tab.href) && !tab.classList.contains('is-active')) {
            prefetched.add(tab.href);
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = tab.href;
            document.head.append(link);
        }
    });
    document.addEventListener('click', event => {
        const tab = event.target.closest('.tabs__item');
        if (!tab) return;
        document.querySelectorAll('.tabs__item.is-active').forEach(item => item.classList.remove('is-active'));
        tab.classList.add('is-active');
    });
    ['pointerup', 'pointercancel'].forEach(type => document.addEventListener(type, () => {
        setTimeout(() => document.querySelectorAll('.tabs__item.is-pressed').forEach(item => item.classList.remove('is-pressed')), 180);
    }));

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

    // Geri düyməsi ilə (bfcache) qayıdanda bloklanmış düymələr açılsın, menyuda bu səhifənin düyməsi seçilsin
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        document.querySelectorAll('.tabs__item').forEach(item => {
            item.classList.toggle('is-active', item.getAttribute('aria-current') === 'page');
            item.classList.remove('is-pressed');
        });
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

    // Məkan: quraşdırılmış tətbiqdə (telefon) icazə verilməyənə qədər tətbiq açılmır (.geo-gate).
    // iPhone tətbiqdə icazənin vəziyyətini hər səhifədə "prompt" kimi qaytarır — ona görə ekran dərhal göstərilmir:
    // əvvəl səssizcə yer oxunur (telefonun ayarında icazə varsa dərhal gəlir), yalnız rədd edilsə ekran çıxır.
    // İcazə bir dəfə alınıbsa yadda qalır (localStorage) — tətbiq gözləmədən açılır. Tətbiq açıq və ekranda olduqca
    // yer 30 saniyədən bir serverə göndərilir (açılanda, qayıdanda və taymerlə; fonda telefon icazə vermir). Kompüterdə məcbur deyil.
    (function geo() {
        const body = document.body;
        const url = body.dataset.geoUrl;
        const allow = document.querySelector('[data-geo-allow]');
        const deniedBox = document.querySelector('[data-geo-denied]');
        const mustAsk = window.matchMedia('(pointer: coarse)').matches && !window.matchMedia('(display-mode: browser)').matches;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const OK_KEY = 'ps-geo-ok';      // icazə verilib (1)
        const LAST_KEY = 'ps-geo-last';  // son göndəriş vaxtı (ms)
        const EVERY = 30 * 1000;
        const store = {
            get: key => { try { return localStorage.getItem(key); } catch (e) { return null; } },
            set: (key, value) => { try { value === null ? localStorage.removeItem(key) : localStorage.setItem(key, value); } catch (e) { /* */ } },
        };
        const state = name => {
            body.classList.remove('geo-check', 'geo-need', 'geo-ok');
            body.classList.add('geo-' + name);
        };
        const denied = () => {
            store.set(OK_KEY, null);
            if (!mustAsk) { state('ok'); return; }
            state('need');
            if (deniedBox) deniedBox.hidden = false;
            if (allow) allow.textContent = 'Yenidən yoxla';
        };
        const send = position => {
            store.set(LAST_KEY, String(Date.now()));
            if (!url) return;
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ lat: position.coords.latitude, lng: position.coords.longitude, accuracy: position.coords.accuracy }),
            }).catch(() => {});
        };
        // Son göndərişdən 25 saniyə keçməyibsə — oxunmur (səhifə keçidlərində təkrar olmasın). Taymer 30 saniyədir:
        // GPS 1–3 saniyə çəkdiyi üçün hədd taymerdən bir az qısadır, yoxsa hər ikinci taymer ötürülərdi (60 saniyə olardı)
        const recent = () => Date.now() - Number(store.get(LAST_KEY) || 0) < EVERY - 5000;
        const locate = (force = false) => {
            if (!force && recent()) return;
            navigator.geolocation.getCurrentPosition(
                position => { store.set(OK_KEY, '1'); state('ok'); send(position); },
                error => { if (error.code === error.PERMISSION_DENIED) denied(); else state('ok'); }, // yer tapılmadı — icazə var
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 20000 },
            );
        };

        if (!('geolocation' in navigator)) { state('ok'); return; }
        if (allow) allow.addEventListener('click', () => locate(true)); // ilk icazə — düymə ilə
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && body.classList.contains('geo-ok')) locate();
        });
        // Tətbiq açıq və ekranda olduqca — 30 saniyədən bir (icazə varsa)
        setInterval(() => {
            if (document.visibilityState === 'visible' && body.classList.contains('geo-ok') && store.get(OK_KEY)) locate();
        }, EVERY);
        const start = permission => {
            if (permission === 'denied') { denied(); return; }
            if (permission === 'granted' || store.get(OK_KEY) || !mustAsk) {
                state('ok');     // tətbiq dərhal açılır
                if (permission === 'granted' || store.get(OK_KEY)) locate(); // yer arxa planda (30 saniyədə bir)
                return;
            }
            // "prompt": ekran göstərmədən yer oxunur — icazə varsa tətbiq açılır, rədd edilsə ekran çıxır
            locate(true);
        };
        if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({ name: 'geolocation' }).then(status => {
                start(status.state);
                status.onchange = () => { if (status.state === 'denied') denied(); };
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
