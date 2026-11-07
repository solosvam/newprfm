// Kuryer tətbiqinin service worker-i (/kuryer/sw.js, əhatəsi /kuryer — CourierAppController::serviceWorker).
// Səhifələr həmişə şəbəkədən (məlumat təzə olmalıdır, keşə yazılmır); internet yoxdursa — "oflayn" səhifəsi.
// Tətbiqin CSS / JS / ikonları — keşdən dərhal, arxa planda yenilənir.
// Push bildirişləri (OneSignal) də bu service worker-dədir — tətbiqdə bir service worker (app.js, serviceWorkerPath).
// Push gələndə tətbiqin ikonundakı rəqəm — oxunmamış bildirişlərin sayı (tətbiq bağlı olsa da).
// Say push-un özündədir (OneSignal əlavə məlumatı: custom.a.badge — CourierNotifier); yoxdursa — serverdən soruşulur.
function pushBadge(event) {
    try {
        const payload = event.data ? event.data.json() : null;
        const value = payload && payload.custom && payload.custom.a ? payload.custom.a.badge : undefined;
        return value === undefined || value === null ? null : Number(value);
    } catch (e) {
        return null;
    }
}
function applyBadge(count) {
    if (!self.navigator || !('setAppBadge' in self.navigator)) return Promise.resolve();
    return (count ? self.navigator.setAppBadge(count) : self.navigator.clearAppBadge()).catch(() => {});
}
self.addEventListener('push', event => {
    // Rəqəm dərhal (şəbəkəsiz): push-dakı say, yoxdursa — serverdən soruşulur.
    // Qeyd: iPhone (iOS 18.4+) push-u service worker-i oyatmadan özü göstərir — orada rəqəm tətbiq açılanda yenilənir (app.js).
    const count = pushBadge(event);
    const work = count !== null && !Number.isNaN(count)
        ? applyBadge(count)
        : fetch('/kuryer/badge', { credentials: 'include', headers: { Accept: 'application/json' } })
            .then(r => (r.ok ? r.json() : null))
            .then(json => (json ? applyBadge(Number(json.unread) || 0) : null))
            .catch(() => {});
    event.waitUntil(work);
});

// OneSignal push işləyicisi bizimkindən SONRA qeyd olunur (yuxarıdakı rəqəm kodu push-u birinci alsın)
importScripts('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js');
const VERSION = 'kuryer-v15';
const OFFLINE = '/courier-app/offline.html';
const PRECACHE = [OFFLINE, '/courier-app/app.css', '/courier-app/manifest.webmanifest', '/courier-app/icons/icon-192.png'];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(VERSION).then(cache => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys()
        .then(keys => Promise.all(keys.filter(key => key.startsWith('kuryer-') && key !== VERSION).map(key => caches.delete(key))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
        return;
    }
    if (url.pathname.startsWith('/courier-app/')) {
        event.respondWith(caches.open(VERSION).then(cache => cache.match(request).then(cached => {
            const fresh = fetch(request).then(response => {
                if (response.ok) cache.put(request, response.clone());
                return response;
            }).catch(() => cached);
            return cached || fresh;
        })));
    }
});
