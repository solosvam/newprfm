// Parfumshop → Ferrum popup.
// 1) Sifariş nömrəsi → parfumshop admin: GET /admin/ferrum/orders/{ref} (admin sessiyası ilə, cookie).
// 2) Məlumat göstərilir; hər sətrə klik — kopyalanır.
// 3) "Ferrum-a doldur" — sifarişdən doldurma planı qurulur, fill.js onu aktiv Ferrum tabında addım-addım icra edir.
//    Müraciət nə yadda saxlanır, nə göndərilir — operator yoxlayıb özü edir.
// Məlumat yalnız chrome.storage.session-da saxlanır (brauzer bağlananda silinir).
import { SITES, FERRUM_HOST, FERRUM } from './config.js';
import { fillPage } from './fill.js';

const $ = (id) => document.getElementById(id);
const view = $('view');
const state = { site: SITES[0], order: null, cards: {} };

/* ---------- kiçik DOM köməkçisi (innerHTML yoxdur — məlumat həmişə textContent) ---------- */
function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
        if (value === null || value === undefined || value === false) continue;
        if (key === 'class') node.className = value;
        else if (key === 'text') node.textContent = value;
        else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
        else node.setAttribute(key, value === true ? '' : value);
    }
    for (const child of children.flat()) {
        if (child === null || child === undefined || child === false) continue;
        node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    }
    return node;
}
const money = (n) => (n === null || n === undefined ? '—' : Number(n).toFixed(2) + ' ₼');
const blank = (v) => v === null || v === undefined || v === '';
// Server nisbi link qaytarır (/admin/...) — seçilmiş saytın ünvanına qoşulur
const absolute = (path) => (path ? new URL(path, state.site.url).href : null);

/* ---------- ayarlar ---------- */
async function loadSettings() {
    const { siteId } = await chrome.storage.local.get('siteId');
    state.site = SITES.find((s) => s.id === siteId) || SITES[0];
    const select = $('site');
    SITES.forEach((s) => select.append(el('option', { value: s.id, text: `${s.label} — ${s.url}` })));
    select.value = state.site.id;
    select.addEventListener('change', async () => {
        state.site = SITES.find((s) => s.id === select.value) || SITES[0];
        await chrome.storage.local.set({ siteId: state.site.id });
    });
    $('settingsToggle').addEventListener('click', () => {
        const open = $('settings').hidden;
        $('settings').hidden = !open;
        $('settingsToggle').setAttribute('aria-expanded', String(open));
    });
}

/* ---------- vəziyyətlər ---------- */
function showState(kind, message, action) {
    view.replaceChildren(el('div', { class: `state state--${kind}` },
        kind === 'loading' ? el('div', { class: 'spinner' }) : null,
        el('p', { text: message }),
        action || null));
    $('actions').hidden = true;
}

/* ---------- çəkmə ---------- */
async function fetchOrder(ref) {
    // Əvvəlki sifarişin məlumatı qalmasın (xəta olsa belə)
    state.order = null;
    Object.values(state.cards).forEach((c) => URL.revokeObjectURL(c.url));
    state.cards = {};
    await chrome.storage.session.remove('order');
    showState('loading', 'Məlumat çəkilir…');
    let response;
    try {
        response = await fetch(`${state.site.url}/admin/ferrum/orders/${encodeURIComponent(ref)}`, {
            credentials: 'include', cache: 'no-store', headers: { Accept: 'application/json' },
        });
    } catch {
        return showState('error', `${state.site.label} ilə əlaqə qurulmadı. İnterneti və ayarlardakı saytı yoxlayın.`);
    }
    const isJson = (response.headers.get('content-type') || '').includes('application/json');
    if (response.status === 401 || (!isJson && response.redirected)) {
        return showState('error', `${state.site.label} admin panelinə daxil olmamısınız.`,
            el('button', { class: 'btn btn-primary btn-sm', text: 'Adminə daxil ol', onclick: () => chrome.tabs.create({ url: `${state.site.url}/admin/login` }) }));
    }
    if (response.status === 403) return showState('error', 'Hesabınızın "ferrum" icazəsi yoxdur. Administratora müraciət edin.');
    if (response.status === 429) return showState('error', 'Çox sorğu göndərildi. Bir dəqiqə sonra yenidən cəhd edin.');
    const body = isJson ? await response.json().catch(() => null) : null;
    if (!response.ok || !body) return showState('error', body?.message || `Xəta baş verdi (HTTP ${response.status}).`);

    state.order = body;
    await chrome.storage.session.set({ order: body, siteId: state.site.id });
    render();
}

/* ---------- vəsiqə şəkilləri (admin sessiyası ilə blob) ---------- */
async function loadCard(side) {
    const url = state.order?.id_card?.[side];
    if (!url) return null;
    if (state.cards[side]) return state.cards[side];
    const response = await fetch(absolute(url), { credentials: 'include', cache: 'no-store' });
    if (!response.ok) throw new Error('HTTP ' + response.status);
    const blob = await response.blob();
    state.cards[side] = { blob, url: URL.createObjectURL(blob) };
    return state.cards[side];
}

/* ---------- görünüş ---------- */
function field(label, value, display) {
    const empty = blank(value);
    const node = el('button', { type: 'button', class: 'field', title: empty ? '' : 'Kopyalamaq üçün klikləyin' },
        el('span', { class: 'field__label', text: label }),
        el('span', { class: 'field__value' + (empty ? ' is-empty' : ''), text: empty ? 'yoxdur' : (display ?? String(value)) }),
        el('span', { class: 'field__copy', 'aria-hidden': 'true', text: '⧉' }));
    if (!empty) {
        node.addEventListener('click', async () => {
            await navigator.clipboard.writeText(String(value));
            node.classList.add('is-copied');
            node.querySelector('.field__copy').textContent = '✓';
            setTimeout(() => { node.classList.remove('is-copied'); node.querySelector('.field__copy').textContent = '⧉'; }, 1200);
        });
    }
    return node;
}
const section = (title, ...content) => el('section', { class: 'section' }, el('h3', { text: title }), ...content);

function render() {
    const d = state.order;
    const o = d.order;
    const head = el('div', { class: 'head' },
        el('div', { class: 'head__row' },
            el('div', {}, el('div', { class: 'head__no', text: o.order_no }),
                el('div', { class: 'head__meta', text: [o.created_at, o.payment_method?.name].filter(Boolean).join(' · ') })),
            o.status ? el('span', { class: 'chip', text: o.status }) : null),
        d.credit ? el('div', { class: 'credit' },
            el('div', {}, el('small', { text: 'Müddət' }), el('b', { text: `${d.credit.months ?? '—'} ay` })),
            el('div', {}, el('small', { text: 'Aylıq' }), el('b', { text: money(d.credit.monthly) })),
            el('div', {}, el('small', { text: 'Cəmi' }), el('b', { text: money(d.credit.total) }))) : null);

    const alerts = [];
    if (d.warnings?.length) alerts.push(el('div', { class: 'alert alert--warn' }, ...d.warnings.map((w) => el('div', { text: '⚠ ' + w }))));
    if (d.missing?.length) alerts.push(el('div', { class: 'alert alert--bad' }, el('b', { text: 'Çatışmayan məlumat:' }),
        el('ul', {}, ...d.missing.map((m) => el('li', { text: m })))));

    const c = d.customer;
    const cards = el('div', { class: 'cards' }, ...['front', 'back'].map((side) => {
        const box = el('div', { class: 'idcard', 'data-side': side }, d.id_card?.[side] ? 'Yüklənir…' : 'Şəkil yoxdur');
        return box;
    }));

    const more = el('details', { class: 'more' }, el('summary', { text: 'Ətraflı məlumat' }),
        section('Müştəri', el('div', { class: 'fields' },
            field('Ad', c.name), field('Soyad', c.surname), field('Ata adı', c.father_name),
            field('FİN', c.fin), field('Vəsiqə', [c.id_card_series, c.id_card_number].filter(Boolean).join(' ') || null),
            field('Telefon', c.mobile), field('E-poçt', c.email))),
        section('İş', el('div', { class: 'fields' },
            field('İş yeri', d.work.workplace),
            field('Maaş', d.work.salary, blank(d.work.salary) ? null : money(d.work.salary)))),
        section('Qohumlar', el('div', { class: 'fields' },
            ...d.relatives.flatMap((r, i) => [field(`Qohum ${i + 1}`, r.name), field(`Telefon ${i + 1}`, r.phone)]))),
        section('Ünvan', el('div', { class: 'fields' }, field('Ünvan', d.address?.full))),
        section('Məhsullar', el('div', { class: 'items' },
            ...d.items.map((it) => el('div', { class: 'item' },
                el('div', {}, [it.brand, it.product].filter(Boolean).join(' '), el('small', { text: [it.size, `${it.quantity} ədəd × ${money(it.unit_price)}`].filter(Boolean).join(' · ') })),
                el('b', { text: money(it.total) }))),
            el('div', { class: 'item' }, el('b', { text: 'Sifariş cəmi' }), el('b', { text: money(o.total) })))),
        section('Şəxsiyyət vəsiqəsi', cards),
        o.admin_url ? el('p', { class: 'hint' }, el('a', { class: 'link', href: absolute(o.admin_url), target: '_blank', text: 'Sifarişi admin paneldə aç ↗' })) : null);
    const summary = el('div', { class: 'summary' },
        el('span', { text: [c.name, c.surname].filter(Boolean).join(' ') || '—' }),
        el('span', { text: `${d.items.length} məhsul · ${money(o.total)}` }));
    view.replaceChildren(head, ...alerts, summary, more);

    ['front', 'back'].forEach(async (side) => {
        const box = cards.querySelector(`[data-side="${side}"]`);
        if (!d.id_card?.[side]) return;
        try {
            const card = await loadCard(side);
            box.replaceChildren(el('img', { src: card.url, alt: side === 'front' ? 'Vəsiqə — ön' : 'Vəsiqə — arxa' }),
                el('span', { text: side === 'front' ? 'Ön' : 'Arxa' }));
        } catch {
            box.textContent = 'Yüklənmədi';
        }
    });

    $('actions').hidden = false;
    updateFillButton();
}

/* ---------- doldurma ---------- */
async function activeTab() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    return tab;
}

async function updateFillButton() {
    const button = $('fillBtn');
    const hint = $('fillHint');
    const tab = await activeTab();
    let host = '';
    try { host = new URL(tab?.url || '').host; } catch {}
    if (host !== FERRUM_HOST) {
        button.disabled = true;
        hint.textContent = `Doldurmaq üçün ${FERRUM_HOST} səhifəsində müraciət formasını açın.`;
    } else {
        button.disabled = false;
        hint.textContent = 'Əvvəl Ferrum-da "Əlavə et" ilə boş müraciət açın. Forma doldurulacaq, amma saxlanmayacaq və göndərilməyəcək.';
    }
}

// Ferrum webp qəbul etmir — şəkil həmişə PNG-yə çevrilir
async function fileData(side) {
    const card = await loadCard(side);
    if (!card) return null;
    const bitmap = await createImageBitmap(card.blob);
    const canvas = new OffscreenCanvas(bitmap.width, bitmap.height);
    canvas.getContext('2d').drawImage(bitmap, 0, 0);
    bitmap.close();
    const png = await canvas.convertToBlob({ type: 'image/png' });
    const dataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(png);
    });
    return { name: `vesiqe-${side === 'front' ? 'on' : 'arxa'}-${state.order.order.order_no}.png`, dataUrl };
}

// 994103227575 / +994 10 322 75 75 / 0103227575 → (010)-322-75-75 (Ferrum maskası)
function ferrumPhone(raw) {
    const digits = String(raw || '').replace(/\D/g, '');
    if (digits.length < 9) return null;
    const n = digits.slice(-9);
    return `(0${n.slice(0, 2)})-${n.slice(2, 5)}-${n.slice(5, 7)}-${n.slice(7, 9)}`;
}

// "Amouage" + "Amouage Opus XV" → brend təkrarlanmasın
function itemName(it) {
    const brand = (it.brand || '').trim();
    let product = (it.product || '').trim();
    if (brand && product.toLowerCase().startsWith(brand.toLowerCase())) product = product.slice(brand.length).trim();
    return [brand, product, it.size].filter(Boolean).join(' ');
}

async function buildPlan() {
    const d = state.order;
    const c = d.customer;
    const months = d.credit?.months;
    const phones = [];
    const self = ferrumPhone(c.mobile);
    // Birinci həmişə müştərinin öz nömrəsi; təkrar müştəridə (Ferrum-da var) yalnız o yazılır — fill.js seçir
    if (self) phones.push({ number: self, type: FERRUM.phoneType, owner: FERRUM.ownerSelf, relation: FERRUM.relationSelf, self: true });
    d.relatives.forEach((r) => {
        const number = ferrumPhone(r.phone);
        if (number) phones.push({ number, type: FERRUM.phoneType, owner: r.name, relation: FERRUM.relationRelative });
    });
    return {
        customer: {
            fin: c.fin, name: c.name, surname: c.surname, father_name: c.father_name,
            id_card_series: c.id_card_series, id_card_number: c.id_card_number,
            gender: c.gender === null || c.gender === undefined ? null : FERRUM.gender[Number(c.gender)],
        },
        source: FERRUM.source,
        months: months ? Number(months) : null,
        price: Number(d.order.total) || 0,                 // parfumshop sifarişinin yekunu
        products: FERRUM.products,
        category: FERRUM.category,
        subcategory: FERRUM.subcategory,
        items: d.items.map((it) => ({ name: itemName(it), price: String(Number(it.unit_price)), quantity: String(it.quantity) })),
        phones,
        work: d.work?.workplace ? { name: d.work.workplace, salary: d.work.salary === null ? null : String(Number(d.work.salary)) } : null,
        document: null, // Sənədlər bölməsinə heç nə yüklənmir
        pinTimeout: FERRUM.pinTimeout,
    };
}

async function fill() {
    const tab = await activeTab();
    const button = $('fillBtn');
    const hint = $('fillHint');
    button.disabled = true;
    hint.textContent = 'Doldurulur… gedişat Ferrum səhifəsində görünür.';
    try {
        const plan = await buildPlan();
        const [{ result }] = await chrome.scripting.executeScript({ target: { tabId: tab.id }, func: fillPage, args: [plan] });
        const count = result.problems?.length || 0;
        hint.textContent = !result.ok ? (result.problems?.[0] || 'Doldurma alınmadı.')
            : count ? `Bitdi — ${count} xəbərdarlıq (səhifədə). Yoxlayıb özünüz saxlayın.` : 'Bitdi. Yoxlayıb özünüz saxlayın.';
    } catch (error) {
        hint.textContent = 'Doldurmaq alınmadı: ' + error.message;
    } finally {
        button.disabled = false;
    }
}

/* ---------- başlanğıc ---------- */
async function init() {
    await loadSettings();
    const saved = await chrome.storage.session.get(['order', 'siteId']);
    if (saved.order && (!saved.siteId || saved.siteId === state.site.id)) {
        state.order = saved.order;
        $('orderRef').value = saved.order.order.order_no;
        render();
    }
    $('searchForm').addEventListener('submit', (event) => {
        event.preventDefault();
        const ref = $('orderRef').value.trim().toUpperCase();
        if (ref) fetchOrder(ref);
    });
    $('clearBtn').addEventListener('click', async () => {
        await chrome.storage.session.remove('order');
        Object.values(state.cards).forEach((c) => URL.revokeObjectURL(c.url));
        state.order = null; state.cards = {};
        $('orderRef').value = '';
        showState('empty', 'Təmizləndi. Yeni sifariş nömrəsi yazın.');
        $('orderRef').focus();
    });
    $('fillBtn').addEventListener('click', fill);
    $('orderRef').focus();
}

init();
