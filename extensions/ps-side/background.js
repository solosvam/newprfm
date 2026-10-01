const ALLOWED = ['https://web.whatsapp.com/', 'https://www.instagram.com/direct/', 'https://business.facebook.com/'];
// sağ klik menyusu: WhatsApp Web və Meta Business Suite (Messenger / Instagram Direct inbox)
const CHAT_PAGES = ['https://web.whatsapp.com/*', 'https://business.facebook.com/*'];

// Sağ klik menyusu. Seçilmiş mətn: PS → Ətri axtar / Kredit → … ;
// şəkil: PS → Ətri şəkildən axtar / Vəsiqə → Ön üz / Vəsiqə → Arxa üz
const CREDIT_FIELDS = [
    ['father_name', 'Ata adı'],
    ['fin', 'FİN'],
    ['id_card', 'Vəsiqənin seriya və nömrəsi'],
    ['relative_1_name', 'Qohum 1 — ad'],
    ['relative_1_phone', 'Qohum 1 — nömrə'],
    ['relative_2_name', 'Qohum 2 — ad'],
    ['relative_2_phone', 'Qohum 2 — nömrə'],
    ['workplace_name', 'İş yeri'],
    ['salary', 'Əmək haqqı'],
];

function createMenus() {
    chrome.contextMenus.removeAll(() => {
        const text = { contexts: ['selection'], documentUrlPatterns: CHAT_PAGES };
        chrome.contextMenus.create({ id: 'ps', title: 'PS', ...text });
        chrome.contextMenus.create({ id: 'ps-search', parentId: 'ps', title: 'Ətri axtar', ...text });
        chrome.contextMenus.create({ id: 'ps-sep', parentId: 'ps', type: 'separator', ...text });
        CREDIT_FIELDS.forEach(([field, title]) => {
            chrome.contextMenus.create({ id: `ps-credit:${field}`, parentId: 'ps', title: `Kredit → ${title}`, ...text });
        });

        const image = { contexts: ['image'], documentUrlPatterns: CHAT_PAGES };
        chrome.contextMenus.create({ id: 'ps-image', title: 'PS', ...image });
        chrome.contextMenus.create({ id: 'ps-image-search', parentId: 'ps-image', title: 'Ətri şəkildən axtar', ...image });
        chrome.contextMenus.create({ id: 'ps-image-sep', parentId: 'ps-image', type: 'separator', ...image });
        chrome.contextMenus.create({ id: 'ps-idcard:front', parentId: 'ps-image', title: 'Vəsiqə → Ön üz', ...image });
        chrome.contextMenus.create({ id: 'ps-idcard:back', parentId: 'ps-image', title: 'Vəsiqə → Arxa üz', ...image });
    });
}

async function fetchImage(url) {
    if (!/^https:\/\//.test(url)) return null;
    const response = await fetch(url, { credentials: 'include' });
    if (!response.ok) return null;
    const blob = await response.blob();
    if (!blob.type.startsWith('image/') || blob.size > 15 * 1024 * 1024) return null;
    // service worker-də FileReader yoxdur — base64 əl ilə
    const bytes = new Uint8Array(await blob.arrayBuffer());
    let binary = '';
    for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
    return `data:${blob.type};base64,${btoa(binary)}`;
}

// Mesaj hansı tabdan gəlib — yan panel rejimində yalnız həmin tabın paneli götürür (sidepanel.js)
function deliverTo(tabId, message) {
    deliver({ ...message, tabId });
}

// Panelə mesaj: açıqdırsa dərhal eşidir; yenicə açılırsa sonradan oxusun deyə saxlanır (sidepanel.js)
function deliver(message) {
    chrome.storage.session.set({ psPending: { message, at: Date.now() } }).catch(() => {});
    chrome.runtime.sendMessage(message).catch(() => {});
}

/*
 * İki rejim — Chrome versiyasına görə özü seçilir:
 *  - yan panel (Chrome 116+: chrome.sidePanel.setPanelBehavior + open);
 *  - ayrıca dar pəncərə (köhnə Chrome, məs. Windows 7/8.1-də qalmış 109): ikona/menyuya basanda
 *    WhatsApp pəncərəsinin sağ kənarında açılır; yeri və ölçüsü yadda qalır.
 */
const SIDE_PANEL = Boolean(chrome.sidePanel?.setPanelBehavior && chrome.sidePanel?.open);
const WINDOW_WIDTH = 380;

if (SIDE_PANEL) {
    chrome.sidePanel.setPanelBehavior({ openPanelOnActionClick: true }).catch(() => {});
} else {
    chrome.action.onClicked.addListener((tab) => openPanel(tab));
}

// Paneli aç: yan panel və ya ayrıca pəncərə (artıq açıqdırsa — önə gətir)
function openPanel(tab) {
    if (SIDE_PANEL) {
        chrome.sidePanel.open({ tabId: tab.id }).catch(() => {});
        return;
    }
    chrome.storage.session.get('psWindowId', ({ psWindowId }) => {
        if (psWindowId) {
            chrome.windows.update(psWindowId, { focused: true }, () => {
                if (chrome.runtime.lastError) createWindow(tab); // istifadəçi bağlayıb
            });
            return;
        }
        createWindow(tab);
    });
}

function createWindow(tab) {
    chrome.storage.local.get('psWindowBounds', ({ psWindowBounds }) => {
        chrome.windows.get(tab.windowId, (browser) => {
            // ilk dəfə: brauzer pəncərəsinin sağ kənarında, eyni hündürlükdə
            const bounds = psWindowBounds || (browser && !chrome.runtime.lastError ? {
                left: Math.max(0, browser.left + browser.width - WINDOW_WIDTH),
                top: browser.top,
                width: WINDOW_WIDTH,
                height: browser.height,
            } : { width: WINDOW_WIDTH, height: 800 });
            chrome.windows.create({ url: 'sidepanel.html?mode=window', type: 'popup', focused: true, ...bounds }, (created) => {
                if (created) chrome.storage.session.set({ psWindowId: created.id });
            });
        });
    });
}

if (!SIDE_PANEL) {
    chrome.windows.onRemoved.addListener((windowId) => {
        chrome.storage.session.get('psWindowId', ({ psWindowId }) => {
            if (psWindowId === windowId) chrome.storage.session.remove('psWindowId');
        });
    });
    // operator pəncərəni yerini/ölçüsünü dəyişəndə — növbəti dəfə orada açılsın
    chrome.windows.onBoundsChanged?.addListener((win) => {
        chrome.storage.session.get('psWindowId', ({ psWindowId }) => {
            if (psWindowId !== win.id) return;
            chrome.storage.local.set({ psWindowBounds: { left: win.left, top: win.top, width: win.width, height: win.height } });
        });
    });
}

// Panel yalnız icazəli saytlarda (WhatsApp, Instagram Direct); pəncərə rejimində ikon hər yerdə işləyir
function apply(tab) {
    if (!SIDE_PANEL || !tab.id || !tab.url) return;
    const allowed = ALLOWED.some((prefix) => tab.url.startsWith(prefix));
    chrome.sidePanel.setOptions(
        allowed ? { tabId: tab.id, path: 'sidepanel.html', enabled: true } : { tabId: tab.id, enabled: false }
    );
}

function applyAll() {
    chrome.tabs.query({}, (tabs) => tabs.forEach(apply));
}

// Quraşdırılanda/yenilənəndə artıq açıq olan WhatsApp tablarında skript yoxdur (və ya köhnəsinin əlaqəsi kəsilib) —
// səhifəni yeniləmədən yenisini yerləşdiririk, operatorun ekranında heç nə dəyişmir
function injectWhatsapp() {
    chrome.tabs.query({ url: 'https://web.whatsapp.com/*' }, (tabs) => {
        tabs.forEach((tab) => {
            chrome.scripting.executeScript({ target: { tabId: tab.id }, files: ['whatsapp.js'] }).catch(() => {});
        });
    });
}

// extension quraşdırılanda/yenilənəndə — açıq tablar + sağ klik menyusu
chrome.runtime.onInstalled.addListener(({ reason }) => {
    applyAll();
    // Chrome-un öz yenilənməsində tablar skripti manifest-dən onsuz da alır — təkrar yerləşdirmirik
    if (reason === 'install' || reason === 'update') injectWhatsapp();
    createMenus();
});
chrome.runtime.onStartup.addListener(applyAll);

// tab yüklənəndə və ya ünvanı dəyişəndə
chrome.tabs.onUpdated.addListener((tabId, info, tab) => apply(tab));

// Menyu seçimi: paneli klikin özündə açırıq (Chrome yalnız istifadəçi hərəkəti ilə açır), sonra məlumatı ötürürük
chrome.contextMenus.onClicked.addListener((info, tab) => {
    if (!tab?.id) return;
    const id = String(info.menuItemId);
    const text = (info.selectionText || '').trim();

    if (id === 'ps-search' || id === 'ps-image-search' || id.startsWith('ps-credit:') || id.startsWith('ps-idcard:')) {
        openPanel(tab);
    }
    if (id === 'ps-search') {
        deliverTo(tab.id, { type: 'ps-search', text });
    } else if (id.startsWith('ps-credit:')) {
        deliverTo(tab.id, { type: 'ps-credit-field', field: id.slice('ps-credit:'.length), text });
    } else if (id.startsWith('ps-idcard:') || id === 'ps-image-search') {
        const idCard = id.startsWith('ps-idcard:');
        const done = (dataUrl) => {
            if (!dataUrl) {
                const message = 'Şəkil alınmadı — şəkli böyük açıb yenidən cəhd edin.';
                deliverTo(tab.id, idCard ? { type: 'ps-credit-error', message } : { type: 'ps-search-error', message });
                return;
            }
            deliverTo(tab.id, idCard
                ? { type: 'ps-id-card', side: id.slice('ps-idcard:'.length), dataUrl }
                : { type: 'ps-image-search', dataUrl });
        };
        const src = String(info.srcUrl || '');
        if (src.startsWith('blob:')) {
            // WhatsApp şəkilləri blob: ünvanındadır — yalnız səhifənin özü (whatsapp.js) oxuya bilər
            chrome.tabs.sendMessage(tab.id, { type: 'ps-grab-image', src }, (reply) => {
                done(chrome.runtime.lastError ? null : reply?.dataUrl);
            });
        } else {
            // Business Suite (Messenger/Instagram) şəkilləri adi https (fbcdn) ünvanındadır — extension özü yükləyir
            fetchImage(src).then(done, () => done(null));
        }
    }
});
