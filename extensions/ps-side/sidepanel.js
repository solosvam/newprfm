const frame = document.getElementById('app');
const target = new URL(PS_SITE).origin;
const queue = [];
let ready = false;

// iframe-ə yalnız öz saytımıza göndəririk; iframe yüklənənə qədər növbədə saxlayırıq
function post(message) {
    if (ready) frame.contentWindow.postMessage(message, target);
    else queue.push(message);
}

frame.addEventListener('load', () => {
    ready = true;
    queue.splice(0).forEach(post);
});
frame.src = `${PS_SITE}/admin/assistant`;

// sağ klik menyusundan gələnlər: Ətri axtar (mətn/şəkil), Kredit → xana, Vəsiqə şəkli
const FORWARD = ['ps-search', 'ps-image-search', 'ps-search-error', 'ps-credit-field', 'ps-id-card', 'ps-credit-error'];

// Yan panel hər tabın özünündür: WhatsApp tabının paneli yalnız öz tabının söhbətini göstərir,
// Business Suite tabının panelinə WhatsApp söhbəti düşməsin. Ayrıca pəncərə (köhnə Chrome) — istənilən WhatsApp tabı.
const WINDOW_MODE = new URLSearchParams(location.search).get('mode') === 'window';
let ownTabId = null;

function forThisPanel(message) {
    return WINDOW_MODE || !message.tabId || ownTabId === null || message.tabId === ownTabId;
}

chrome.runtime.onMessage.addListener((message, sender) => {
    if (message?.type === 'ps-chat') {                                                    // söhbət dəyişdi
        if (!WINDOW_MODE && sender.tab && ownTabId !== null && sender.tab.id !== ownTabId) return;
        post({ type: 'ps-chat', chat: message.chat });
    }
    if (FORWARD.includes(message?.type)) {
        if (!forThisPanel(message)) return;
        chrome.storage.session.remove('psPending');
        post(message);
    }
});

// panel açılan anda artıq açıq olan söhbət.
// Yan panel: öz tabı (WhatsApp deyilsə — söhbət yoxdur, əl ilə axtarış); ayrıca pəncərə: WhatsApp tabını özümüz tapırıq.
function currentChatTab(callback) {
    if (WINDOW_MODE) {
        chrome.tabs.query({ url: 'https://web.whatsapp.com/*' }, (tabs) => callback(tabs.find((item) => item.active) || tabs[0]));
        return;
    }
    chrome.tabs.query({ active: true, currentWindow: true }, ([tab]) => {
        ownTabId = tab ? tab.id : null;
        callback(tab && tab.url && tab.url.startsWith('https://web.whatsapp.com/') ? tab : null);
    });
}

currentChatTab((tab) => {
    if (!tab) return;
    chrome.tabs.sendMessage(tab.id, { type: 'ps-get-chat' }, (reply) => {
        if (chrome.runtime.lastError) return; // WhatsApp tabı extension-dan əvvəl açılıb
        post({ type: 'ps-chat', chat: reply });
    });
});

// panel menyudan açılıbsa — mesaj panel yüklənməmiş göndərilib, saxlanandan oxuyuruq
chrome.storage.session.get('psPending', ({ psPending }) => {
    if (psPending && Date.now() - psPending.at < 15000 && FORWARD.includes(psPending.message?.type) && forThisPanel(psPending.message)) {
        post(psPending.message);
    }
    chrome.storage.session.remove('psPending');
});
