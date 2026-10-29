// Admin bildirişləri (zəng işarəsi ilə eyni mənbə: /admin/notifications) — admin tabı açıq olmasa da.
// Hər 30 saniyədən bir yoxlanır (chrome.alarms); yeni bildirişdə sistem bildirişi + növünə görə səs (offscreen.html),
// ikonda oxunmamış sayı. Brauzerdəki admin girişi ilə işləyir (cookie). Admin tabı ekrandadırsa susur — səsi səhifə özü çalır.
// Səslər və söndürmə — extension-un ayarları (options.html; ikona sağ klik → "Seçimlər").
const NOTIFY_ALARM = 'ps-notifications';

async function notifyMuted() {
    const { psNotifyMuted } = await chrome.storage.local.get('psNotifyMuted');
    return psNotifyMuted === true;
}

function notifyBadge(count) {
    chrome.action.setBadgeBackgroundColor({ color: '#d6283b' });
    chrome.action.setBadgeText({ text: count > 0 ? (count > 99 ? '99+' : String(count)) : '' });
}

// Səs yalnız səhifədə çalına bilər — gizli offscreen səhifə (bir dəfə yaradılır)
async function notifySound(type) {
    if (await notifyMuted()) return;
    if (!(await chrome.offscreen.hasDocument?.())) {
        await chrome.offscreen.createDocument({ url: 'offscreen.html', reasons: ['AUDIO_PLAYBACK'], justification: 'Yeni sifariş bildirişinin səsi' })
            .catch(() => {});
    }
    chrome.runtime.sendMessage({ type: 'ps-notify-sound', sound: type }).catch(() => {});
}

// Admin paneli hazırda ekrandadırsa (aktiv tab, fokusda pəncərə) — səhifə özü səs çalır, təkrarlamırıq
async function adminOnScreen() {
    const [tab] = await chrome.tabs.query({ active: true, lastFocusedWindow: true });
    return !!tab?.url && tab.url.startsWith(PS_SITE + '/admin');
}

async function notifyPoll() {
    let response;
    try {
        response = await fetch(`${PS_SITE}/admin/notifications`, { credentials: 'include', cache: 'no-store', headers: { Accept: 'application/json' } });
    } catch {
        return; // şəbəkə — növbəti dəfə
    }
    const isJson = (response.headers.get('content-type') || '').includes('application/json');
    if (!response.ok || !isJson) {
        notifyBadge(0); // adminə daxil olunmayıb və ya icazə yoxdur — səssiz
        return;
    }
    const data = await response.json().catch(() => null);
    if (!data) return;
    notifyBadge(Number(data.unread) || 0);

    const { psNotifyLastId } = await chrome.storage.local.get('psNotifyLastId');
    const maxId = data.items.reduce((max, n) => Math.max(max, n.id), 0);
    await chrome.storage.local.set({ psNotifyLastId: Math.max(maxId, psNotifyLastId || 0) });
    if (psNotifyLastId === undefined) return; // ilk dəfə — köhnələr üçün bildiriş yox

    const fresh = data.items.filter((n) => n.id > psNotifyLastId).reverse();
    if (!fresh.length || await adminOnScreen()) return;

    const { psNotifyUrls = {} } = await chrome.storage.session.get('psNotifyUrls');
    fresh.slice(-3).forEach((n) => {
        const id = 'ps-notify-' + n.id;
        if (n.url) psNotifyUrls[id] = PS_SITE + n.url;
        chrome.notifications.create(id, {
            type: 'basic', iconUrl: 'icons/icon128.png', priority: 2, requireInteraction: true,
            title: n.title, message: n.body || '', contextMessage: 'Parfumshop · ' + n.time,
        });
    });
    await chrome.storage.session.set({ psNotifyUrls });
    [...new Set(fresh.map((n) => n.type))].forEach(notifySound);
}

function notifySetup() {
    chrome.alarms.create(NOTIFY_ALARM, { periodInMinutes: 0.5 });
    notifyPoll();
}

chrome.alarms.onAlarm.addListener((alarm) => { if (alarm.name === NOTIFY_ALARM) notifyPoll(); });
chrome.runtime.onStartup.addListener(notifySetup);

// Bildirişə klik — sifariş (və ya müraciət) açılır
chrome.notifications.onClicked.addListener(async (id) => {
    const { psNotifyUrls = {} } = await chrome.storage.session.get('psNotifyUrls');
    chrome.tabs.create({ url: psNotifyUrls[id] || PS_SITE + '/admin/orders' });
    chrome.notifications.clear(id);
});

