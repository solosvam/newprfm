/**
 * Admin popup-ları (partials/popups.blade.php → #popup-data).
 * Səhifədə ən çox bir popup: sort_order üzrə ilk uyğun gələn göstərilir.
 *  - tezlik: once — bir dəfə; session — hər sessiyada (sessionStorage); daily — gündə bir dəfə;
 *  - "Bir daha göstərmə": localStorage (qonaq da), daxil olmuş müştəri üçün server bazada da saxlayır;
 *  - mövqe mobil (≤ 767px) və desktop üçün ayrıca: center | corner | bar;
 *  - hadisələr (shown / click / close / dismiss) sendBeacon ilə sayğaca gedir.
 * localStorage / sessionStorage bağlı ola bilər (gizli rejim) — onda hər dəfə göstərilir, xəta vermir.
 */
(function () {
    'use strict';

    let data;
    try { data = JSON.parse(document.getElementById('popup-data')?.textContent || 'null'); } catch (e) { data = null; }
    if (!data || !Array.isArray(data.popups) || !data.popups.length) return;

    const LOCAL_KEY = 'ps_popups';
    const SESSION_KEY = 'ps_popups_session';
    const today = new Date().toISOString().slice(0, 10);

    const read = (storage, key, fallback) => {
        try { return JSON.parse(storage.getItem(key) || 'null') || fallback; } catch (e) { return fallback; }
    };
    const write = (storage, key, value) => {
        try { storage.setItem(key, JSON.stringify(value)); } catch (e) { /* bağlıdır */ }
    };

    const local = read(window.localStorage, LOCAL_KEY, {});
    const session = read(window.sessionStorage, SESSION_KEY, []);

    function eligible(popup) {
        const state = local[popup.id] || {};
        if (state.dismissed) return false;
        if (popup.frequency === 'once') return !state.seen;
        if (popup.frequency === 'daily') return state.seen !== today;
        return !session.includes(popup.id);
    }

    function remember(popup, patch) {
        local[popup.id] = Object.assign(local[popup.id] || {}, patch);
        write(window.localStorage, LOCAL_KEY, local);
    }

    function track(popup, type) {
        const url = data.eventUrl.replace('__ID__', popup.id);
        const body = new FormData();
        body.append('_token', data.token);
        body.append('type', type);
        // klikdən sonra səhifə dəyişir — sendBeacon yarımçıq qalmır
        if (navigator.sendBeacon && navigator.sendBeacon(url, body)) return;
        fetch(url, { method: 'POST', body, credentials: 'same-origin', keepalive: true }).catch(() => {});
    }

    const popup = data.popups.find(eligible);
    if (!popup) return;

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text) node.textContent = text;
        return node;
    }

    function build() {
        const mobile = window.matchMedia('(max-width: 767px)').matches;
        const position = (mobile ? popup.mobile : popup.desktop) || 'center';

        const root = el('div', 'site-popup site-popup--' + position);
        const box = el('div', 'site-popup__box');
        box.setAttribute('role', position === 'center' ? 'dialog' : 'region');
        if (position === 'center') box.setAttribute('aria-modal', 'true');
        if (popup.title) box.setAttribute('aria-label', popup.title);

        const close = el('button', 'site-popup__close');
        close.type = 'button';
        close.setAttribute('aria-label', data.text.close);
        close.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
        box.appendChild(close);

        if (popup.image) {
            const media = el('div', 'site-popup__media');
            const img = el('img');
            img.src = popup.image;
            img.alt = popup.title || '';
            media.appendChild(img);
            box.appendChild(media);
        }

        const content = el('div', 'site-popup__content');
        const copy = el('div', 'site-popup__copy');
        if (popup.title) copy.appendChild(el('div', 'site-popup__title', popup.title));
        if (popup.body) copy.appendChild(el('div', 'site-popup__text', popup.body));
        content.appendChild(copy);

        const actions = el('div', 'site-popup__actions');
        if (popup.button && popup.url) {
            const button = el('a', 'btn btn-dark site-popup__button', popup.button);
            button.href = popup.url;
            button.addEventListener('click', () => {
                track(popup, 'click');
                hide();
            });
            actions.appendChild(button);
        }
        const never = el('button', 'site-popup__never', data.text.never);
        never.type = 'button';
        actions.appendChild(never);
        content.appendChild(actions);
        box.appendChild(content);

        // şəkil linkli ola bilər (düymə olmasa da)
        if (popup.url && !popup.button && popup.image) {
            box.querySelector('.site-popup__media').addEventListener('click', () => {
                track(popup, 'click');
                window.location.href = popup.url;
            });
            box.querySelector('.site-popup__media').classList.add('is-link');
        }

        if (position === 'center') {
            const backdrop = el('div', 'site-popup__backdrop');
            backdrop.addEventListener('click', () => dismissBy('close'));
            root.appendChild(backdrop);
        }
        root.appendChild(box);

        function hide() {
            root.classList.remove('is-open');
            document.removeEventListener('keydown', onKey);
            setTimeout(() => root.remove(), 250);
        }
        function dismissBy(type) {
            if (type === 'dismiss') remember(popup, { dismissed: 1 });
            track(popup, type);
            hide();
        }
        function onKey(event) {
            if (event.key === 'Escape') dismissBy('close');
        }

        close.addEventListener('click', () => dismissBy('close'));
        never.addEventListener('click', () => dismissBy('dismiss'));
        document.addEventListener('keydown', onKey);

        document.body.appendChild(root);
        requestAnimationFrame(() => requestAnimationFrame(() => root.classList.add('is-open')));
        if (position === 'center') close.focus({ preventScroll: true });
    }

    function show() {
        // başqa pəncərə (modal) açıqdırsa, bir az sonra yenidən yoxla
        // cookie zolağı da aşağıdadır — əvvəlcə o bağlansın
        if (document.querySelector('.modal.is-open, .push-ios.is-open, [aria-modal="true"]:not(.site-popup__box), #cookieBar:not([hidden])')) {
            setTimeout(show, 3000);
            return;
        }
        remember(popup, { seen: popup.frequency === 'daily' ? today : 1 });
        if (popup.frequency === 'session') {
            session.push(popup.id);
            write(window.sessionStorage, SESSION_KEY, session);
        }
        track(popup, 'shown');
        build();
    }

    const start = () => setTimeout(show, Math.max(0, Number(popup.delay) || 0) * 1000);
    if (document.readyState === 'complete') start();
    else window.addEventListener('load', start, { once: true });
})();
