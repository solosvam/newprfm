// Brendlər siyahısı → "Loqo axtar": Serper şəkil axtarışı, seçim və tətbiq
(() => {
    'use strict';

    const modalEl = document.getElementById('logoSearchModal');
    if (!modalEl || !window.bootstrap) return;

    const modal = new bootstrap.Modal(modalEl);
    const form = modalEl.querySelector('[data-logo-form]');
    const input = form.querySelector('[name="q"]');
    const results = modalEl.querySelector('[data-logo-results]');
    const title = modalEl.querySelector('[data-logo-brand]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let current = null; // { searchUrl, applyUrl, id, name }
    const source = () => modalEl.querySelector('input[name="logo_source"]:checked')?.value || 'vector';

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const notify = (message, ok) => window.jQuery?.notify(
        { title: 'Bildiriş!', message },
        { type: ok ? 'success' : 'danger', delay: 3000, allow_dismiss: false, z_index: 99999 }
    );

    async function search(q) {
        results.innerHTML = '<div class="text-muted py-4">Axtarılır…</div>';
        try {
            const url = current.searchUrl + '?source=' + source() + '&q=' + encodeURIComponent(q);
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Axtarış alınmadı');
            input.value = data.query;
            results.innerHTML = data.images.length ? data.images.map(img => `
                <button type="button" class="logo-candidate" data-candidate="${img.id}" title="${esc(img.title)}">
                    <span class="logo-candidate__img"><img src="${esc(img.thumbnail)}" alt="" loading="lazy" referrerpolicy="no-referrer"></span>
                    <span class="logo-candidate__src">${esc(img.source || img.title)}</span>
                </button>`).join('') : `<div class="text-muted py-4">Nəticə yoxdur. ${source() === 'vector' ? 'Google şəkillərinə keçin və ya' : ''} sorğunu dəyişin.</div>`;
        } catch (e) {
            results.innerHTML = `<div class="text-danger py-4">${esc(e.message)}</div>`;
        }
    }

    document.addEventListener('click', event => {
        const btn = event.target.closest('[data-logo-search]');
        if (!btn) return;
        current = { searchUrl: btn.dataset.logoSearch, applyUrl: btn.dataset.logoApply, id: btn.dataset.brandId, name: btn.dataset.brandName };
        title.textContent = current.name;
        input.value = '';
        modalEl.querySelector('#logoSourceVector').checked = true;
        modal.show();
        search('');
    });

    // Mənbə dəyişəndə — standart sorğu ilə yenidən axtar
    modalEl.querySelectorAll('input[name="logo_source"]').forEach(r => r.addEventListener('change', () => {
        if (current) search('');
    }));

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (current) search(input.value.trim());
    });

    results.addEventListener('click', async event => {
        const card = event.target.closest('[data-candidate]');
        if (!card || !current) return;
        card.classList.add('is-loading');
        try {
            const res = await fetch(current.applyUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ candidate: Number(card.dataset.candidate) }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Loqo saxlanılmadı');
            const thumb = document.querySelector(`[data-logo-thumb="${current.id}"]`);
            if (thumb) {
                thumb.classList.remove('brand-logo-thumb--empty');
                thumb.innerHTML = `<img src="${esc(data.logo)}" alt="">`;
            }
            notify(data.message, true);
            modal.hide();
        } catch (e) {
            notify(e.message, false);
        } finally {
            card.classList.remove('is-loading');
        }
    });
})();
