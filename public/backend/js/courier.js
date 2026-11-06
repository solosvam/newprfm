// Kuryer ekranı: formalar iki dəfə göndərilməsin (zəif internetdə təkrar basma)
document.querySelectorAll('form[data-once]').forEach(form => form.addEventListener('submit', () =>
    form.querySelectorAll('button').forEach(b => { b.disabled = true; })));

// Məhsulun şəkli: basanda tam ekranda böyüyür, istənilən yerə basanda / Esc ilə bağlanır
document.addEventListener('click', event => {
    const thumb = event.target.closest('[data-zoom]');
    if (!thumb) return;
    const overlay = document.createElement('div');
    overlay.className = 'courier-zoom';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-label', 'Məhsulun şəkli');
    const img = document.createElement('img');
    img.src = thumb.dataset.zoom;
    img.alt = '';
    overlay.append(img);
    const close = () => { overlay.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = e => { if (e.key === 'Escape') close(); };
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', onKey);
    document.body.append(overlay);
});
