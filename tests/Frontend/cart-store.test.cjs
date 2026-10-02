const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const { randomUUID } = require('node:crypto');
const source = fs.readFileSync('public/frontend/js/cart-store.js', 'utf8');
const GUEST = 'parfumshop_cart';
const IMPORT = 'parfumshop_cart_import';

function browser({ loggedIn = false, storage = new Map(), fetch = async () => { throw Error('Unexpected request'); } } = {}) {
    const events = new Map();
    const window = {
        addEventListener(name, handler) { const list = events.get(name) || []; list.push(handler); events.set(name, list); },
        dispatchEvent(event) { for (const handler of events.get(event.type) || []) handler(event); },
    };
    const context = vm.createContext({ window,
        document: { body: { dataset: { auth: loggedIn ? '1' : '0', customerId: '1' } },
            querySelector: () => ({ content: 'csrf' }),
            getElementById: () => ({ textContent: JSON.stringify({ cart: { indexUrl: '/cart/items', mergeUrl: '/cart/merge', changeUrl: '/cart/items' } }) }),
        },
        localStorage: { getItem: key => storage.get(key) || null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) },
        crypto: { randomUUID }, fetch, console: { error() {} },
        CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init?.detail; } },
    });
    vm.runInContext(source, context);
    return { cart: window.parfumshopCart, storage, window };
}
const plain = value => JSON.parse(JSON.stringify(value));
const response = items => ({ ok: true, json: async () => ({ items }) });

function server(initial = []) {
    let items = structuredClone(initial);
    const tokens = new Set();
    const calls = [];
    const fetch = async (url, options) => {
        const body = options.body && JSON.parse(options.body);
        calls.push({ url, body });
        if (url === '/cart/merge') {
            if (!tokens.has(body.token)) {
                tokens.add(body.token);
                for (const row of body.items) {
                    const current = items.find(i => i.variant_id === row.variant_id);
                    if (current) current.quantity += row.quantity;
                    else items.push({ ...row });
                }
            }
        } else if (body) {
            const current = items.find(i => i.variant_id === body.variant_id);
            if (body.action === 'remove') items = items.filter(i => i.variant_id !== body.variant_id);
            else if (body.action === 'decrease') current.quantity = Math.max(1, current.quantity - body.quantity);
            else if (current) current.quantity += body.quantity;
            else items.push({ variant_id: body.variant_id, quantity: body.quantity });
        }
        return response(structuredClone(items));
    };
    return { fetch, calls, get: () => structuredClone(items) };
}

test('guest changes stay local and survive reload', async () => {
    const b = browser(); await b.cart.ready;
    await b.cart.change(12, 'add', 2, 5);
    await b.cart.change(12, 'add');
    assert.equal(b.cart.get()[0].quantity, 3);
    const reloaded = browser({ storage: b.storage }); await reloaded.cart.ready;
    assert.equal(reloaded.cart.get()[0].quantity, 3);
    await reloaded.cart.change(12, 'remove');
    assert.equal(reloaded.cart.get().length, 0);
});

test('login imports guest without replacing existing cart, then uses only server', async () => {
    const storage = new Map([[GUEST, JSON.stringify([{ variant_id: 1, quantity: 3 }])]]);
    const db = server([{ variant_id: 1, quantity: 2 }, { variant_id: 2, quantity: 1 }]);
    const b = browser({ loggedIn: true, storage, fetch: db.fetch }); await b.cart.ready;
    assert.deepEqual(plain(b.cart.get()).map(i => i.quantity), [5, 1]);
    assert.equal(storage.has(GUEST), false);
    assert.equal(storage.has(IMPORT), false);
    await b.cart.change(2, 'add', 2);
    assert.equal(db.get()[1].quantity, 3);
    assert.equal(storage.has(GUEST), false);
    const otherDevice = browser({ loggedIn: true, fetch: db.fetch }); await otherDevice.cart.ready;
    assert.deepEqual(plain(otherDevice.cart.get()), plain(b.cart.get()));
});

test('lost merge response preserves guest and token; retry does not duplicate server quantities', async () => {
    const storage = new Map([[GUEST, JSON.stringify([{ variant_id: 1, quantity: 2 }])]]);
    const db = server();
    const failed = browser({ loggedIn: true, storage, fetch: async (url, options) => {
        await db.fetch(url, options); throw Error('Response lost');
    } });
    await assert.rejects(failed.cart.ready, /Response lost/);
    const token = JSON.parse(storage.get(IMPORT)).token;
    assert.equal(storage.has(GUEST), true);
    const retry = browser({ loggedIn: true, storage, fetch: db.fetch }); await retry.cart.ready;
    assert.equal(db.get()[0].quantity, 2);
    assert.equal(db.calls[1].body.token, token);
    assert.equal(storage.has(GUEST), false);
});

test('two tabs importing same guest use one token and clear only once', async () => {
    const storage = new Map([[GUEST, JSON.stringify([{ variant_id: 1, quantity: 2 }])]]);
    const db = server([{ variant_id: 1, quantity: 1 }]);
    const first = browser({ loggedIn: true, storage, fetch: db.fetch });
    const second = browser({ loggedIn: true, storage, fetch: db.fetch });
    await Promise.all([first.cart.ready, second.cart.ready]);
    assert.equal(db.get()[0].quantity, 3);
    assert.equal(db.calls.filter(c => c.url === '/cart/merge').length, 2);
    assert.equal(storage.has(GUEST), false);
});

test('guest additions during import are imported too', async () => {
    const storage = new Map([[GUEST, JSON.stringify([{ variant_id: 1, quantity: 2 }])]]);
    const db = server(); let changed = false;
    const b = browser({ loggedIn: true, storage, fetch: async (url, options) => {
        const result = await db.fetch(url, options);
        if (url === '/cart/merge' && !changed) {
            changed = true;
            storage.set(GUEST, JSON.stringify([{ variant_id: 1, quantity: 3 }, { variant_id: 2, quantity: 1 }]));
        }
        return result;
    } });
    await b.cart.ready;
    assert.deepEqual(db.get().map(i => i.quantity), [3, 1]);
    assert.equal(storage.has(GUEST), false);
});

test('account cart does not become guest cart after logout', async () => {
    const storage = new Map(); const db = server([{ variant_id: 1, quantity: 4 }]);
    const account = browser({ loggedIn: true, storage, fetch: db.fetch }); await account.cart.ready;
    const guest = browser({ storage }); await guest.cart.ready;
    assert.equal(guest.cart.get().length, 0);
    account.cart.accept([]);
    assert.equal(account.cart.get().length, 0);
});

test('failed server mutation keeps current cart and later mutations still work', async () => {
    const db = server([{ variant_id: 1, quantity: 2 }]); let fail = true;
    const b = browser({ loggedIn: true, fetch: async (url, options) => {
        if (options.body && fail) { fail = false; throw Error('Offline'); }
        return db.fetch(url, options);
    } }); await b.cart.ready;
    await assert.rejects(b.cart.change(1, 'add'), /Offline/);
    assert.equal(b.cart.get()[0].quantity, 2);
    await b.cart.change(1, 'add');
    assert.equal(b.cart.get()[0].quantity, 3);
});
