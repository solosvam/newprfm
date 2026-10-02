(() => {
    'use strict';
    const GUEST_KEY = 'parfumshop_cart';
    const IMPORT_KEY = 'parfumshop_cart_import';
    const loggedIn = document.body.dataset.auth === '1';
    const customerId = document.body.dataset.customerId;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let config = {};
    try { config = JSON.parse(document.getElementById('app-data')?.textContent || '{}').cart || {}; } catch {}
    let items = [];
    let queue = Promise.resolve();
    const channel = loggedIn && typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('parfumshop-cart') : null;

    function read(key, fallback) {
        try { return JSON.parse(localStorage.getItem(key) || 'null') || fallback; } catch { return fallback; }
    }

    function guest() {
        const rows = read(GUEST_KEY, []);
        if (!Array.isArray(rows)) return [];
        const byId = new Map();
        rows.forEach(row => {
            const id = Number(row?.variant_id);
            const quantity = Number(row?.quantity);
            if (!Number.isSafeInteger(id) || id < 1 || !Number.isInteger(quantity) || quantity < 1) return;
            const previous = byId.get(id);
            byId.set(id, { product_id: Number(row.product_id) || null, variant_id: id, quantity: Math.min(99, (previous?.quantity || 0) + quantity) });
        });
        return [...byId.values()];
    }

    function publish(rows, broadcast = false) {
        items = rows;
        window.dispatchEvent(new CustomEvent('parfumshop:cart-updated', { detail: snapshot() }));
        if (broadcast) channel?.postMessage({ customerId });
    }

    function snapshot() {
        return (loggedIn ? items : guest()).map(item => ({ ...item }));
    }

    async function request(url, body) {
        const response = await fetch(url, {
            method: body ? 'POST' : 'GET',
            credentials: 'same-origin', cache: 'no-store',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf || '' },
            ...(body ? { body: JSON.stringify(body) } : {}),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Səbət yadda saxlanılmadı. Yenidən cəhd edin.');
        if (!Array.isArray(data.items)) throw new Error('Səbət məlumatları alınmadı.');
        return data.items;
    }

    async function initialize() {
        if (!loggedIn) {
            publish(guest());
            return;
        }
        // Snapshot və token cavab itəndə də qalır: retry eyni idxalı təkrarlamır.
        let imported = false;
        for (;;) {
            let batch = read(IMPORT_KEY, null);
            if (!batch || !Array.isArray(batch.items) || !batch.token) {
                const rows = guest().slice(0, 200);
                if (!rows.length) break;
                batch = { token: crypto.randomUUID(), items: rows };
                localStorage.setItem(IMPORT_KEY, JSON.stringify(batch));
            }
            await request(config.mergeUrl, batch);
            imported = true;
            // Digər tab artıq təmizləyibsə təkrar çıxmırıq; arada əlavə olunanlar qalır.
            if (read(IMPORT_KEY, null)?.token === batch.token) {
                const quantities = new Map(batch.items.map(item => [Number(item.variant_id), Number(item.quantity)]));
                const remaining = guest().map(item => ({ ...item, quantity: item.quantity - (quantities.get(item.variant_id) || 0) }))
                    .filter(item => item.quantity > 0);
                if (remaining.length) localStorage.setItem(GUEST_KEY, JSON.stringify(remaining));
                else localStorage.removeItem(GUEST_KEY);
                localStorage.removeItem(IMPORT_KEY);
            }
        }
        publish(await request(config.indexUrl), imported);
    }

    function serial(operation) {
        const result = queue.catch(() => {}).then(() => ready).then(operation);
        queue = result.catch(() => {});
        return result;
    }

    function change(variantId, action, quantity = 1, productId = null) {
        return serial(async () => {
            if (loggedIn) {
                publish(await request(config.changeUrl, { variant_id: variantId, action, quantity }), true);
            } else {
                const rows = guest();
                const index = rows.findIndex(item => item.variant_id === Number(variantId));
                if (action === 'remove') {
                    if (index !== -1) rows.splice(index, 1);
                } else if (action === 'decrease') {
                    if (index !== -1) rows[index].quantity = Math.max(1, rows[index].quantity - quantity);
                } else if (index !== -1) {
                    rows[index].quantity = Math.min(99, rows[index].quantity + quantity);
                } else {
                    rows.push({ product_id: productId, variant_id: Number(variantId), quantity: Math.min(99, quantity) });
                }
                localStorage.setItem(GUEST_KEY, JSON.stringify(rows));
                // Qonaq dəyişiklik edibsə əvvəlki uğursuz idxal snapshot-ını saxlayırıq.
                publish(rows);
            }
            return snapshot();
        });
    }

    function refresh() {
        return serial(async () => {
            publish(loggedIn ? await request(config.indexUrl) : guest());
            return snapshot();
        });
    }

    const ready = initialize();
    // Səhifə istifadə etmirsə də unhandled rejection olmasın; əməliyyatlar xəta alır.
    ready.catch(error => {
        console.error(error);
        window.parfumshopNotify?.(error.message, 'error');
    });
    window.parfumshopCart = { ready, get: snapshot, change, refresh, accept: rows => publish(rows, true) };
    channel?.addEventListener('message', event => {
        if (event.data.customerId === customerId) refresh().catch(error => console.error(error));
    });
    window.addEventListener('focus', () => refresh().catch(error => console.error(error)));
    window.addEventListener('storage', event => {
        if (!loggedIn && event.key === GUEST_KEY) publish(guest());
    });
})();
