// Price listlər: sütun seçimi (map.blade.php) və uyğunlaşdırma (show.blade.php).
// Bootstrap 5.0.1 — native "show.bs.modal" hadisəsi, relatedTarget = kliklənən düymə.
(() => {
    'use strict';

    // Sütun seçimi: vərəq dəyişəndə önizləmə yenidən yüklənir; "Brend sütunu" yalnız "Ayrıca sütun"da lazımdır
    const sheet = document.querySelector('[data-map-sheet]');
    sheet?.addEventListener('change', () => { window.location = sheet.dataset.mapSheet + '?sheet=' + encodeURIComponent(sheet.value); });
    const brandMode = document.getElementById('mapBrandMode');
    const brandCol = document.querySelector('[data-map-brand-col]');
    const syncBrand = () => brandCol?.classList.toggle('d-none', brandMode.value !== 'column');
    brandMode?.addEventListener('change', syncBrand);
    if (brandMode) syncBrand();

    const modal = document.getElementById('plMatchModal');
    if (!modal) return;
    const csrf = modal.dataset.csrf;
    const results = modal.querySelector('[data-match-results]');
    const search = modal.querySelector('[data-match-search]');
    let current = null;
    let timer = null;

    const send = (url, method, body) => fetch(url, {
        method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined,
    }).then(response => { if (!response.ok) throw new Error('Əməliyyat alınmadı (' + response.status + ').'); return response.json(); });

    const el = (tag, cls, text) => { const node = document.createElement(tag); if (cls) node.className = cls; if (text !== undefined) node.textContent = text; return node; };

    const render = items => {
        results.replaceChildren();
        if (!items.length) { results.append(el('p', 'text-muted', 'Uyğun məhsul tapılmadı — axtarışı dəyişin.')); return; }
        items.forEach(item => {
            const row = el('div', 'd-flex justify-content-between align-items-center gap-2 border-bottom py-2');
            const info = el('div');
            const name = el('div', item.active ? '' : 'text-muted', item.label + ' · ' + item.size);
            if (item.exact) name.append(' ', el('span', 'badge bg-success', 'eyni ad və ölçü'));
            else if (item.same_size) name.append(' ', el('span', 'badge bg-outline-primary', 'eyni ölçü'));
            // Cins: sətrin cinsi ilə eynidirsə yaşıl, fərqlidirsə qırmızı (eyni adlı kişi / qadın ətirlərini qarışdırmamaq üçün)
            (item.genders || []).forEach(gender => name.append(' ', el('span', 'badge ' + (item.same_gender === true ? 'bg-outline-success' : item.same_gender === false ? 'bg-outline-danger' : 'bg-outline-muted'), gender)));
            info.append(name, el('div', 'text-muted text-small', [item.type, 'satış ' + item.price + ' AZN', item.active ? null : 'deaktiv', item.same_gender === false ? 'cinsi fərqlidir' : null].filter(Boolean).join(' · ')));
            const pick = el('button', 'btn btn-sm btn-primary text-nowrap', 'Seç');
            pick.type = 'button';
            pick.addEventListener('click', () => {
                pick.disabled = true;
                send(current.match, 'POST', { variant_id: item.id }).then(() => window.location.reload()).catch(error => { pick.disabled = false; alert(error.message); });
            });
            row.append(info, pick);
            results.append(row);
        });
    };

    const load = () => {
        results.replaceChildren(el('p', 'text-muted', 'Yüklənir…'));
        fetch(current.candidates + '?q=' + encodeURIComponent(search.value.trim()), { headers: { 'Accept': 'application/json' } })
            .then(response => response.json()).then(data => render(data.items || []))
            .catch(() => results.replaceChildren(el('p', 'text-danger', 'Siyahı yüklənmədi.')));
    };

    modal.addEventListener('show.bs.modal', event => {
        const btn = event.relatedTarget;
        if (!btn) return;
        current = { candidates: btn.dataset.candidates, match: btn.dataset.match };
        modal.querySelector('[data-match-title]').textContent = btn.dataset.title;
        modal.querySelector('[data-match-price]').textContent = btn.dataset.price;
        search.value = '';
        load();
    });
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 300); });

    document.querySelectorAll('[data-unmatch]').forEach(btn => btn.addEventListener('click', () => {
        if (!window.confirm('Uyğunluq ləğv edilsin? Yaddaşdan da silinəcək.')) return;
        btn.disabled = true;
        send(btn.dataset.unmatch, 'DELETE').then(() => window.location.reload()).catch(error => { btn.disabled = false; alert(error.message); });
    }));
})();
