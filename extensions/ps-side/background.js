const ALLOWED = ['https://web.whatsapp.com/', 'https://www.instagram.com/direct/'];
const WHATSAPP = ['https://web.whatsapp.com/*'];

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
        const text = { contexts: ['selection'], documentUrlPatterns: WHATSAPP };
        chrome.contextMenus.create({ id: 'ps', title: 'PS', ...text });
        chrome.contextMenus.create({ id: 'ps-search', parentId: 'ps', title: 'Ətri axtar', ...text });
        chrome.contextMenus.create({ id: 'ps-sep', parentId: 'ps', type: 'separator', ...text });
        CREDIT_FIELDS.forEach(([field, title]) => {
            chrome.contextMenus.create({ id: `ps-credit:${field}`, parentId: 'ps', title: `Kredit → ${title}`, ...text });
        });

        const image = { contexts: ['image'], documentUrlPatterns: WHATSAPP };
        chrome.contextMenus.create({ id: 'ps-image', title: 'PS', ...image });
        chrome.contextMenus.create({ id: 'ps-image-search', parentId: 'ps-image', title: 'Ətri şəkildən axtar', ...image });
        chrome.contextMenus.create({ id: 'ps-image-sep', parentId: 'ps-image', type: 'separator', ...image });
        chrome.contextMenus.create({ id: 'ps-idcard:front', parentId: 'ps-image', title: 'Vəsiqə → Ön üz', ...image });
        chrome.contextMenus.create({ id: 'ps-idcard:back', parentId: 'ps-image', title: 'Vəsiqə → Arxa üz', ...image });
    });
}

// Panelə mesaj: açıqdırsa dərhal eşidir; yenicə açılırsa sonradan oxusun deyə saxlanır (sidepanel.js)
function deliver(message) {
    chrome.storage.session.set({ psPending: { message, at: Date.now() } }).catch(() => {});
    chrome.runtime.sendMessage(message).catch(() => {});
}

chrome.sidePanel.setPanelBehavior({ openPanelOnActionClick: true });

// Panel yalnız icazəli saytlarda (WhatsApp, Instagram Direct)
function apply(tab) {
    if (!tab.id || !tab.url) return;
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
        chrome.sidePanel.open({ tabId: tab.id }).catch(() => {});
    }
    if (id === 'ps-search') {
        deliver({ type: 'ps-search', text });
    } else if (id.startsWith('ps-credit:')) {
        deliver({ type: 'ps-credit-field', field: id.slice('ps-credit:'.length), text });
    } else if (id.startsWith('ps-idcard:') || id === 'ps-image-search') {
        // WhatsApp şəkilləri blob: ünvanındadır — yalnız səhifənin özü (whatsapp.js) oxuya bilər
        const idCard = id.startsWith('ps-idcard:');
        chrome.tabs.sendMessage(tab.id, { type: 'ps-grab-image', src: info.srcUrl }, (reply) => {
            if (chrome.runtime.lastError || !reply?.dataUrl) {
                const message = 'Şəkil alınmadı — WhatsApp-da şəkli böyük açıb yenidən cəhd edin.';
                deliver(idCard ? { type: 'ps-credit-error', message } : { type: 'ps-search-error', message });
                return;
            }
            deliver(idCard
                ? { type: 'ps-id-card', side: id.slice('ps-idcard:'.length), dataUrl: reply.dataUrl }
                : { type: 'ps-image-search', dataUrl: reply.dataUrl });
        });
    }
});
