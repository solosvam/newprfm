// Kuryer tətbiqi (/kuryer). Səhifələr arası keçid — Hotwire Turbo: səhifə yenidən yüklənmir, yalnız <body> dəyişir
// (ağ ekran yoxdur, geri düyməsində keşdən dərhal, toxunanda əvvəlcədən yükləmə). Ona görə:
//  - tətbiq səviyyəsində bir dəfə: delegasiya olunmuş kliklər / formalar, OneSignal, service worker, məkan taymeri;
//  - hər səhifədə (turbo:load): quraşdırma ekranı, push zolağı, məkan ekranının mətni;
//  - keçidlər arası vəziyyət <html>-dədir (geo-*, is-loading) — Turbo <body>-ni əvəz edir, <html>-ə toxunmur.
// Turbo yüklənməsə (CDN) — adi səhifə keçidləri ilə eyni işləyir.
(function () {
    'use strict';

    const root = document.documentElement;
    const config = root.dataset; // sw, geoUrl, pushApp, pushUser (layout.blade.php)
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const store = {
        get: key => { try { return localStorage.getItem(key); } catch (e) { return null; } },
        set: (key, value) => { try { value === null ? localStorage.removeItem(key) : localStorage.setItem(key, value); } catch (e) { /* */ } },
    };
    const onPage = fn => {
        // Turbo varsa — hər keçiddə (ilk yükləmə daxil), yoxsa bir dəfə
        if (window.Turbo) document.addEventListener('turbo:load', fn);
        else if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    };
    // Turbo-nun öz zolağı yox — bizimki (.loader)
    if (window.Turbo?.config?.drive) window.Turbo.config.drive.progressBarDelay = 60000;
    else if (window.Turbo?.setProgressBarDelay) window.Turbo.setProgressBarDelay(60000);

    // ---------- Splash: tətbiq açılanda bir dəfə (cookie — server sonrakı səhifələrdə çəkmir) ----------
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
        setTimeout(hide, 2500);
    }

    // ---------- Yüklənmə göstəricisi (80 ms-dən tez açılsa — görünmür) ----------
    let loadingTimer = 0;
    const startLoading = () => {
        clearTimeout(loadingTimer);
        loadingTimer = setTimeout(() => root.classList.add('is-loading'), 80);
    };
    const stopLoading = () => {
        clearTimeout(loadingTimer);
        root.classList.remove('is-loading');
    };
    ['turbo:visit', 'turbo:submit-start'].forEach(type => document.addEventListener(type, startLoading));
    ['turbo:load', 'turbo:render', 'turbo:fetch-request-error', 'turbo:submit-end'].forEach(type => document.addEventListener(type, event => {
        if (type !== 'turbo:submit-end' || !event.detail?.success) stopLoading(); // uğurlu forma — ardınca keçid gəlir
    }));
    window.addEventListener('pageshow', stopLoading);
    if (!window.Turbo) {
        // Turbo yoxdur: adi keçid və formalar
        document.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;
            const url = new URL(link.href, location.href);
            if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
            if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
            startLoading();
        });
        document.addEventListener('submit', event => { setTimeout(() => { if (!event.defaultPrevented) startLoading(); }, 0); });
    }

    // ---------- Basılma hissi (iPhone-da :active yalnız toxunuş dinləyicisi olanda) və aşağı menyu ----------
    document.addEventListener('touchstart', () => {}, { passive: true });
    document.addEventListener('pointerdown', event => {
        const tab = event.target.closest('.tabs__item');
        if (tab) tab.classList.add('is-pressed');
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

    // ---------- Formalar: data-confirm — təsdiq ({amount} → məbləğ); data-once — iki dəfə göndərilməsin ----------
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const question = form.dataset.confirm;
        if (question && !window.confirm(question.replace('{amount}', form.elements.amount ? form.elements.amount.value : ''))) {
            event.preventDefault();
            event.stopImmediatePropagation(); // Turbo da göndərməsin
            return;
        }
        if (form.hasAttribute('data-once')) {
            if (form.dataset.sent) { event.preventDefault(); event.stopImmediatePropagation(); return; }
            form.dataset.sent = '1';
            // düymələr göndərişdən sonra bağlanır (Turbo basılan düymənin dəyərini oxuyur)
            setTimeout(() => form.querySelectorAll('button').forEach(button => { button.disabled = true; button.classList.add('is-busy'); }), 0);
        }
    }, true);
    const unlockForms = () => document.querySelectorAll('form[data-sent]').forEach(form => {
        delete form.dataset.sent;
        form.querySelectorAll('button').forEach(button => { button.disabled = false; button.classList.remove('is-busy'); });
    });
    document.addEventListener('turbo:submit-end', event => { if (!event.detail?.success) unlockForms(); }); // xəta — yenidən cəhd
    // Keşə düşən səhifə (geri düyməsi) təmiz olsun
    document.addEventListener('turbo:before-cache', () => {
        unlockForms();
        closeZoom();
        document.querySelectorAll('.tabs__item').forEach(item => {
            item.classList.toggle('is-active', item.getAttribute('aria-current') === 'page');
            item.classList.remove('is-pressed');
        });
        document.querySelectorAll('[data-splash]').forEach(el => el.remove());
    });
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        unlockForms();
        document.querySelectorAll('.tabs__item').forEach(item => {
            item.classList.toggle('is-active', item.getAttribute('aria-current') === 'page');
            item.classList.remove('is-pressed');
        });
    });

    // ---------- Məhsulun şəkli: basanda tam ekranda ----------
    function closeZoom() {
        document.querySelectorAll('.zoom').forEach(el => el.remove());
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

    // ---------- Balans: bulanıq; göz — görünür (0-dan sayılaraq) və gizlənir ----------
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
        const duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 700;
        const started = performance.now();
        const step = now => {
            const progress = duration ? Math.min(1, (now - started) / duration) : 1;
            value.textContent = money.format(target * (1 - Math.pow(1 - progress, 3))) + ' AZN';
            if (progress < 1) value.dataset.frame = String(requestAnimationFrame(step));
        };
        value.dataset.frame = String(requestAnimationFrame(step));
    });

    // ---------- Siyahı səhifələri (body[data-refresh]): 1 dəqiqədən sonra qayıdanda — təzə məlumat ----------
    let hiddenAt = 0;
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') { hiddenAt = Date.now(); return; }
        if (!hiddenAt || Date.now() - hiddenAt < 60000 || !document.body.hasAttribute('data-refresh')) return;
        if (window.Turbo) window.Turbo.visit(location.href, { action: 'replace' }); else location.reload();
    });

    // ---------- Quraşdırma ekranı (.gate — telefonda brauzerdə, app.css) ----------
    let installPrompt = null;
    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        installPrompt = event;
        renderGate();
    });
    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        root.dataset.installed = '1';
        renderGate();
    });
    document.addEventListener('click', async event => {
        if (!event.target.closest('[data-install]') || !installPrompt) return;
        installPrompt.prompt();
        await installPrompt.userChoice.catch(() => null);
        installPrompt = null;
        renderGate();
    });
    function renderGate() {
        const gate = document.querySelector('.gate');
        if (!gate) return;
        const installed = root.dataset.installed === '1';
        gate.querySelector('[data-install]').hidden = !installPrompt;
        gate.querySelector('[data-gate-ios]').hidden = !isIos || installed;
        gate.querySelector('[data-gate-other]').hidden = isIos || !!installPrompt || installed;
        if (installed) gate.querySelector('.gate__text').textContent = 'Tətbiq quraşdırıldı. İndi ana ekrandakı PS Kuryer ikonundan açın.';
    }
    onPage(renderGate);

    // ---------- Məkan ----------
    // Quraşdırılmış tətbiqdə (telefon) icazə olmadan tətbiq açılmır (.geo-gate). iPhone icazəni hər səhifədə "prompt" kimi
    // qaytarır — ona görə əvvəl səssizcə yer oxunur, ekran yalnız rədd ediləndə çıxır; icazə bir dəfə alınıbsa yadda qalır.
    // Tətbiq açıq və ekranda olduqca yer 30 saniyədən bir göndərilir (fonda telefon icazə vermir). Kompüterdə məcbur deyil.
    (function geo() {
        const mustAsk = window.matchMedia('(pointer: coarse)').matches && !window.matchMedia('(display-mode: browser)').matches;
        const OK_KEY = 'ps-geo-ok';
        const LAST_KEY = 'ps-geo-last';
        const EVERY = 30 * 1000;
        const state = name => {
            root.classList.remove('geo-check', 'geo-need', 'geo-ok');
            root.classList.add('geo-' + name);
        };
        const denied = () => {
            store.set(OK_KEY, null);
            if (!mustAsk) { state('ok'); return; }
            root.classList.add('geo-denied');
            state('need');
            renderGeo();
        };
        const send = position => {
            store.set(LAST_KEY, String(Date.now()));
            if (!config.geoUrl) return;
            fetch(config.geoUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ lat: position.coords.latitude, lng: position.coords.longitude, accuracy: position.coords.accuracy }),
            }).catch(() => {});
        };
        // Hədd taymerdən 5 saniyə qısadır: GPS 1–3 saniyə çəkir, yoxsa hər ikinci taymer ötürülərdi
        const recent = () => Date.now() - Number(store.get(LAST_KEY) || 0) < EVERY - 5000;
        const locate = (force = false) => {
            if (!force && recent()) return;
            navigator.geolocation.getCurrentPosition(
                position => { store.set(OK_KEY, '1'); root.classList.remove('geo-denied'); state('ok'); send(position); },
                error => { if (error.code === error.PERMISSION_DENIED) denied(); else state('ok'); },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 20000 },
            );
        };
        function renderGeo() {
            const deniedBox = document.querySelector('[data-geo-denied]');
            const allow = document.querySelector('[data-geo-allow]');
            const isDenied = root.classList.contains('geo-denied');
            if (deniedBox) deniedBox.hidden = !isDenied;
            if (allow && isDenied) allow.lastChild.textContent = ' Yenidən yoxla';
        }
        onPage(renderGeo);

        if (!('geolocation' in navigator)) { state('ok'); return; }
        document.addEventListener('click', event => { if (event.target.closest('[data-geo-allow]')) locate(true); });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && root.classList.contains('geo-ok')) locate();
        });
        setInterval(() => {
            if (document.visibilityState === 'visible' && root.classList.contains('geo-ok') && store.get(OK_KEY)) locate();
        }, EVERY);
        const start = permission => {
            if (permission === 'denied') { denied(); return; }
            if (permission === 'granted' || store.get(OK_KEY) || !mustAsk) {
                state('ok');
                if (permission === 'granted' || store.get(OK_KEY)) locate();
                return;
            }
            locate(true); // "prompt": səssiz yoxlama
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

    // ---------- Push bildirişləri (OneSignal): kuryer "user-{id}" — sifariş təyin olunanda (CourierPush) ----------
    // İcazə yalnız düymə ilə (iPhone başqa cür vermir); rədd edilibsə — telefon ayarlarına yönləndirən mətn.
    if (config.pushApp) {
        let oneSignal = null;
        const renderPush = () => {
            const box = document.querySelector('[data-push-ask]');
            if (!box || !oneSignal) return;
            const supported = oneSignal.Notifications.isPushSupported();
            const blocked = typeof Notification !== 'undefined' && Notification.permission === 'denied';
            const on = oneSignal.Notifications.permission && oneSignal.User.PushSubscription.optedIn !== false;
            box.hidden = !supported || on;
            box.classList.toggle('is-denied', blocked);
            box.querySelector('[data-push-text]').textContent = blocked
                ? 'Bildirişlər bağlıdır. Telefonun ayarlarında PS Kuryer üçün bildirişlərə icazə verin.'
                : 'Yeni sifarişlərdən dərhal xəbər tutmaq üçün bildirişləri açın.';
        };
        document.addEventListener('click', async event => {
            if (!event.target.closest('[data-push-enable]') || !oneSignal) return;
            try {
                if (oneSignal.Notifications.permission) await oneSignal.User.PushSubscription.optIn();
                else await oneSignal.Notifications.requestPermission();
            } catch (e) { /* */ }
            renderPush();
        });
        onPage(renderPush);
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        window.OneSignalDeferred.push(async OneSignal => {
            try {
                await OneSignal.init({
                    appId: config.pushApp,
                    serviceWorkerPath: 'kuryer/sw.js',
                    serviceWorkerParam: { scope: '/kuryer' },
                    promptOptions: { slidedown: { prompts: [] } },
                });
                await OneSignal.login(config.pushUser);
            } catch (e) {
                return; // bildiriş əsas işə mane olmamalıdır
            }
            oneSignal = OneSignal;
            OneSignal.Notifications.addEventListener('permissionChange', renderPush);
            renderPush();
        });
    }

    // ---------- Service worker: /kuryer/sw.js, əhatəsi /kuryer ----------
    if (config.sw && 'serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register(config.sw, { scope: '/kuryer' }).catch(() => {}));
    }
})();
