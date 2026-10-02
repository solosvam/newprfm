(() => {
    'use strict';

    const notify = (msg, type) => (window.parfumshopNotify ? window.parfumshopNotify(msg, type) : null);

    async function copyText(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (e) {
            // Köhnə brauzerlər / http
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            const ok = document.execCommand('copy');
            ta.remove();
            return ok;
        }
    }

    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (await copyText(btn.dataset.copy)) {
                notify(btn.dataset.copied || 'OK', 'success');
            }
        });
    });

    // Link inputuna klikləyəndə hamısı seçilsin
    document.getElementById('referralLink')?.addEventListener('focus', (e) => e.target.select());

    // Paylaş: native paylaşma menyusu; dəstəklənmirsə link kopyalanır
    const shareBtn = document.getElementById('referralShare');
    shareBtn?.addEventListener('click', async () => {
        const { title, text, url, copied } = shareBtn.dataset;
        if (typeof navigator.share === 'function') {
            try {
                await navigator.share({ title, text, url });
                return;
            } catch (e) {
                if (e && e.name === 'AbortError') return; // istifadəçi bağladı
            }
        }
        if (await copyText(url)) notify(copied, 'success');
    });
})();
