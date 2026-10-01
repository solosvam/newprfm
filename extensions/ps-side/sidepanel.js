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

chrome.runtime.onMessage.addListener((message) => {
    if (message?.type === 'ps-chat') post({ type: 'ps-chat', chat: message.chat });    // söhbət dəyişdi
    if (FORWARD.includes(message?.type)) {
        chrome.storage.session.remove('psPending');
        post(message);
    }
});

// panel açılan anda artıq açıq olan söhbət
chrome.tabs.query({ active: true, currentWindow: true }, ([tab]) => {
    if (!tab) return;
    chrome.tabs.sendMessage(tab.id, { type: 'ps-get-chat' }, (reply) => {
        if (chrome.runtime.lastError) return; // WhatsApp tabı extension-dan əvvəl açılıb
        post({ type: 'ps-chat', chat: reply });
    });
});

// panel menyudan açılıbsa — mesaj panel yüklənməmiş göndərilib, saxlanandan oxuyuruq
chrome.storage.session.get('psPending', ({ psPending }) => {
    if (psPending && Date.now() - psPending.at < 15000 && FORWARD.includes(psPending.message?.type)) post(psPending.message);
    chrome.storage.session.remove('psPending');
});
