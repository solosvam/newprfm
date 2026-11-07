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
// MÜVƏQQƏTİ diaqnostika (routes/courier.php — sw-debug)
function debug(info) {
    return fetch('/kuryer/sw-debug', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(info) }).catch(() => {});
}
self.addEventListener('push', event => {
    // Rəqəm dərhal (şəbəkəsiz): push-dakı say, yoxdursa 1 — sonra diaqnostika (MÜVƏQQƏTİ) paralel
    const count = pushBadge(event);
    const hasApi = !!(self.navigator && 'setAppBadge' in self.navigator);
    let raw = null;
    try { raw = event.data ? event.data.text() : null; } catch (e) { raw = 'unreadable: ' + e; }
    const badge = hasApi
        ? self.navigator.setAppBadge(count !== null && !Number.isNaN(count) && count > 0 ? count : 1).then(() => 'ok', e => 'error: ' + e)
        : Promise.resolve('no-api');
    event.waitUntil(Promise.all([
        badge,
        badge.then(result => debug({ step: 'push', hasApi, count, result, raw: raw && raw.slice(0, 900) })),
    ]));
});

// OneSignal push işləyicisi bizimkindən SONRA qeyd olunur (yuxarıdakı rəqəm kodu push-u birinci alsın)
importScripts('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js');
const VERSION = 'kuryer-v14';
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
