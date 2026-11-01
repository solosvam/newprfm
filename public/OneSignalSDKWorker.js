/**
 * Saytın yeganə service worker-i: OneSignal web push + PWA (quraşdırma, internet yoxdursa offline səhifə).
 * Ayrıca sw.js yoxdur — eyni scope-da ("/") iki service worker ola bilməz, ikincisi push-u sındırardı.
 * Qeydiyyatı OneSignal (push.js) və pwa.js eyni ünvanla edir — biri digərini əvəz etmir.
 * Səhifələr keşlənmir (qiymət, stok, səbət həmişə serverdən): yalnız naviqasiya alınmayanda offline.html.
 */
importScripts('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js');

const PS_CACHE = 'ps-pwa-v1';
const PS_OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(PS_CACHE).then((cache) => cache.addAll([PS_OFFLINE, '/pwa/logo.svg'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((k) => k.startsWith('ps-pwa-') && k !== PS_CACHE).map((k) => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET' || request.mode !== 'navigate') return;
    event.respondWith(fetch(request).catch(() => caches.match(PS_OFFLINE)));
});
