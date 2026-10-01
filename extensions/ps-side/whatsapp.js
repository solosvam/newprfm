// WhatsApp Web: açıq söhbətin nömrəsini tapır və panelə göndərir.
//  - saxlanmamış nömrə: başlıqda nömrənin özü yazılır;
//  - saxlanmış kontakt: başlıqda ad yazılır — "Kişi bilgisi" (Contact info) açılanda oradakı nömrə
//    ad ilə birlikdə yadda saxlanır (chrome.storage.local), növbəti dəfə avtomatik tanınır.
(function () {
    'use strict';
    const PHONE_TEXT = /^[+\d\s\-()‪-‮]+$/;
    let contacts = {};   // { "Orxan": "994506632699" }
    let last = null;

    chrome.storage.local.get('psContacts', ({ psContacts }) => {
        contacts = psContacts || {};
        last = null; // yaddaş yükləndi — cari söhbəti yenidən yoxla
        check();
    });

    function digitsOf(text) {
        const digits = text.replace(/\D+/g, '');
        return PHONE_TEXT.test(text) && digits.length >= 9 ? digits : null;
    }

    function readChat() {
        const header = document.querySelector('#main header');
        if (!header) return null;
        const node = header.querySelector('span[dir="auto"]');
        const title = (node?.getAttribute('title') || node?.textContent || '').trim();
        if (!title) return null;
        const phone = digitsOf(title);
        if (phone) return { title, phone };
        return contacts[title] ? { title, phone: contacts[title], remembered: true } : { title, phone: null };
    }

    // "Kişi bilgisi" paneli: söhbət siyahısı (#pane-side) və mesajlar (#main) xaricindəki mətnlər.
    // Nömrə müxtəlif elementdə ola bilər (başlığın altında və ya "Telefon numarası" bölməsində) —
    // ona görə element növünə yox, mətnin özünə baxırıq. Qrup məlumatında bir neçə nömrə olur — onda heç nə saxlamırıq.
    function outsideTexts() {
        const texts = [];
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    return node.id === 'pane-side' || node.id === 'main' || node.tagName === 'SCRIPT' || node.tagName === 'STYLE'
                        ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_SKIP;
                }
                return NodeFilter.FILTER_ACCEPT;
            },
        });
        while (walker.nextNode()) {
            // nömrələr görünməz istiqamət simvolları ilə gəlir: "\u202A+994 50 300 70 07\u202C"
            const text = walker.currentNode.nodeValue.replace(/[\u200E\u200F\u202A-\u202E\u2066-\u2069]/g, '').trim();
            if (text) texts.push(text);
        }
        return texts;
    }

    function readContactInfo(title) {
        const texts = outsideTexts();
        if (!texts.includes(title)) return null; // panel həmin kontaktın deyil (və ya bağlıdır)
        const found = new Set();
        texts.forEach((text) => {
            if (!text.startsWith('+') || text.length > 25) return;
            const phone = digitsOf(text);
            if (phone) found.add(phone);
        });
        return found.size === 1 ? [...found][0] : null;
    }

    function remember(title, phone) {
        if (contacts[title] === phone) return;
        contacts[title] = phone;
        if (alive()) chrome.storage.local.set({ psContacts: contacts });
    }

    // Extension yenilənəndə (↻) bu köhnə skript tabda qalır, amma Chrome ilə əlaqəsi kəsilir —
    // onda sakitcə dayanırıq; tab yenilənəndə (F5) yeni skript işə düşür.
    function alive() {
        if (chrome.runtime?.id) return true;
        observer.disconnect();
        clearTimeout(timer);
        return false;
    }

    function send(message) {
        if (!alive()) return;
        try {
            chrome.runtime.sendMessage(message).catch(() => {}); // panel bağlıdırsa — normaldır
        } catch (error) {
            alive();
        }
    }

    function check() {
        if (!alive()) return;
        let chat = readChat();
        if (chat && !chat.phone) {
            const phone = readContactInfo(chat.title);
            if (phone) {
                remember(chat.title, phone);
                chat = { title: chat.title, phone, remembered: true };
            }
        }
        const key = chat ? `${chat.title}|${chat.phone || ''}` : '';
        if (key === last) return;
        last = key;
        send({ type: 'ps-chat', chat });
    }

    // söhbət dəyişəndə URL dəyişmir, ona görə DOM-u izləyirik
    let timer = null;
    const observer = new MutationObserver(() => { clearTimeout(timer); timer = setTimeout(check, 300); });
    observer.observe(document.body, { childList: true, subtree: true });

    // Vəsiqə şəkli: WhatsApp şəkilləri blob: ünvanındadır — səhifənin içindən oxunur
    function toDataUrl(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => reject(reader.error);
            reader.readAsDataURL(blob);
        });
    }

    async function grabImage(src) {
        try {
            const response = await fetch(src);
            if (response.ok) return await toDataUrl(await response.blob());
        } catch (error) { /* aşağıdakı yol */ }
        // ehtiyat: səhifədəki şəkli kətana çəkirik
        const img = [...document.images].find((image) => image.src === src);
        if (!img || !img.naturalWidth) throw new Error('image not found');
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
        canvas.getContext('2d').drawImage(img, 0, 0);
        return canvas.toDataURL('image/jpeg', 0.95);
    }

    chrome.runtime.onMessage.addListener((message, sender, reply) => {
        // panel açılanda cari söhbəti soruşur
        if (message?.type === 'ps-get-chat') reply(readChat());
        if (message?.type === 'ps-grab-image') {
            grabImage(message.src).then((dataUrl) => reply({ dataUrl }), () => reply({ error: true }));
            return true; // cavab asinxrondur
        }
    });
})();
