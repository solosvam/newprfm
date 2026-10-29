// Admin bildirişləri (zəng işarəsi): hər 15 saniyədən bir soruşur, yeni bildirişdə növünə görə səs çalır.
// Server: AdminNotificationsController (feed / read). Səsi söndürmək — bu brauzer üçün (localStorage).
(function () {
    'use strict';
    const root = document.getElementById('adminNotifications');
    if (!root) return;

    const list = root.querySelector('[data-notifications-list]');
    const count = root.querySelector('[data-notifications-count]');
    const dot = root.querySelector('[data-notifications-dot]');
    const muteButton = root.querySelector('[data-notifications-mute]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const MUTE_KEY = 'admin-notifications-muted';
    let lastId = null; // ilk yükləmədə səs çalınmır
    let muted = false;
    try { muted = localStorage.getItem(MUTE_KEY) === '1'; } catch (e) {}

    // Hər növ üçün ayrı səs (fayl yoxdur — WebAudio): notların tezliyi (Hz)
    const SOUNDS = {
        one_click: [880, 1175],          // iki qısa yüksək
        credit: [523, 659, 784],         // üç pilləli yuxarı
        site_order: [660],               // bir ton
    };
    let audio = null;
    function play(type) {
        if (muted) return;
        try {
            audio ??= new (window.AudioContext || window.webkitAudioContext)();
            if (audio.state === 'suspended') audio.resume();
            (SOUNDS[type] || SOUNDS.site_order).forEach((frequency, index) => {
                const start = audio.currentTime + index * 0.18;
                const osc = audio.createOscillator();
                const gain = audio.createGain();
                osc.type = 'sine';
                osc.frequency.value = frequency;
                gain.gain.setValueAtTime(0.0001, start);
                gain.gain.exponentialRampToValueAtTime(0.25, start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.16);
                osc.connect(gain).connect(audio.destination);
                osc.start(start);
                osc.stop(start + 0.17);
            });
        } catch (e) { /* səs dəstəklənmir */ }
    }
    // Brauzer səsi yalnız istifadəçi bir dəfə klik edəndən sonra buraxır
    document.addEventListener('click', () => { if (audio?.state === 'suspended') audio.resume(); }, { once: true });

    function renderMute() {
        muteButton.textContent = muted ? 'Səsi aç' : 'Səsi söndür';
    }
    muteButton.addEventListener('click', event => {
        event.stopPropagation();
        muted = !muted;
        try { localStorage.setItem(MUTE_KEY, muted ? '1' : '0'); } catch (e) {}
        renderMute();
    });
    renderMute();

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function render(data) {
        const unread = Number(data.unread) || 0;
        count.textContent = unread > 99 ? '99+' : String(unread);
        count.classList.toggle('d-none', unread === 0);
        dot.classList.add('d-none');
        list.innerHTML = data.items.length ? data.items.map(n => `
            <li class="mb-3 pb-3 border-bottom border-separator-light ${n.unread ? 'admin-notification--unread' : ''}">
                <a href="${esc(n.url || '#')}">${esc(n.title)}</a>
                ${n.body ? `<div class="text-small text-muted">${esc(n.body)}</div>` : ''}
                <div class="text-small text-muted">${esc(n.time)}</div>
            </li>`).join('') : '<li class="text-muted">Bildiriş yoxdur</li>';
    }

    async function poll() {
        try {
            const response = await fetch(root.dataset.feedUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            if (lastId !== null) {
                // Yeni gələnlər — hər növ üçün bir dəfə səs
                [...new Set(data.items.filter(n => n.id > lastId).map(n => n.type))].forEach(play);
            }
            lastId = data.items.reduce((max, n) => Math.max(max, n.id), lastId ?? 0);
            render(data);
        } catch (e) { /* şəbəkə — növbəti dəfə */ }
    }

    // Açılanda hamısı oxunmuş sayılır
    root.addEventListener('shown.bs.dropdown', async () => {
        if (count.classList.contains('d-none')) return;
        try {
            await fetch(root.dataset.readUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            count.classList.add('d-none');
        } catch (e) {}
    });

    poll();
    setInterval(() => { if (!document.hidden) poll(); }, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
