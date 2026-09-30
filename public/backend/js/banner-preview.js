// Bannerlər siyahısı: kiçik şəklin üzərinə gələndə (və ya fokusda) böyük ölçü göstərilir.
// Önizləmə body-yə əlavə olunur (position: fixed) ki, DataTable konteynerində kəsilməsin.
(() => {
    'use strict';
    const GAP = 16;
    let box = null;
    let current = null;

    function ensureBox() {
        if (box) return box;
        box = document.createElement('div');
        box.className = 'banner-preview';
        box.setAttribute('aria-hidden', 'true');
        box.appendChild(document.createElement('img'));
        document.body.appendChild(box);
        return box;
    }

    // Kursorun sağında; yer yoxdursa solunda, şaquli olaraq ekrana sığdırılır
    function place(x, y) {
        const rect = box.getBoundingClientRect();
        let left = x + GAP;
        if (left + rect.width > window.innerWidth - GAP) left = Math.max(GAP, x - GAP - rect.width);
        const top = Math.min(Math.max(GAP, y - rect.height / 2), window.innerHeight - rect.height - GAP);
        box.style.left = left + 'px';
        box.style.top = Math.max(GAP, top) + 'px';
    }

    function show(thumb, x, y) {
        ensureBox();
        current = thumb;
        const img = box.querySelector('img');
        const reveal = () => { if (current === thumb) { place(x, y); box.classList.add('is-visible'); } };
        if (img.getAttribute('src') !== thumb.currentSrc && img.src !== thumb.src) {
            img.onload = reveal;
            img.src = thumb.currentSrc || thumb.src;
            if (img.complete) reveal();
        } else {
            reveal();
        }
    }

    function hide() {
        current = null;
        box?.classList.remove('is-visible');
    }

    document.addEventListener('mouseover', (event) => {
        const thumb = event.target.closest?.('[data-banner-preview]');
        if (thumb) show(thumb, event.clientX, event.clientY);
    });
    document.addEventListener('mousemove', (event) => {
        if (current && box?.classList.contains('is-visible')) place(event.clientX, event.clientY);
    });
    document.addEventListener('mouseout', (event) => {
        if (event.target.closest?.('[data-banner-preview]')) hide();
    });
    // Klaviatura: fokusda şəklin yanında göstər
    document.addEventListener('focusin', (event) => {
        const thumb = event.target.closest?.('[data-banner-preview]');
        if (!thumb) return;
        const r = thumb.getBoundingClientRect();
        show(thumb, r.right, r.top + r.height / 2);
    });
    document.addEventListener('focusout', (event) => {
        if (event.target.closest?.('[data-banner-preview]')) hide();
    });
    window.addEventListener('scroll', hide, { passive: true });
})();
