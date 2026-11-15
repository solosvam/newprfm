/* Virtual hədiyyə kartı: məbləğ seçimi və önizləmə (alış səhifəsi); Göndər / Endir / Kodu kopyala (kartın səhifəsi) */
(() => {
    'use strict';

    /* ---------- Alış səhifəsi ---------- */
    const form = document.querySelector('[data-gc-form]');
    if (form) {
        const min = Number(form.dataset.min) || 100;
        const max = Number(form.dataset.max) || 1500;
        const step = Number(form.dataset.step) || 50;
        const input = form.querySelector('[data-gc-amount-input]');
        const valueEl = form.querySelector('[data-gc-value]');
        const payEl = form.querySelector('[data-gc-pay]');
        const cardAmount = document.querySelector('[data-gc-card] [data-gc-amount]');
        const quick = [...form.querySelectorAll('[data-gc-quick]')];
        const less = form.querySelector('[data-gc-step="-1"]');
        const more = form.querySelector('[data-gc-step="1"]');

        const setAmount = value => {
            const v = Math.min(max, Math.max(min, Math.round(value / step) * step));
            input.value = v;
            valueEl.textContent = v;
            if (payEl) payEl.textContent = v;
            if (cardAmount) cardAmount.textContent = v;
            quick.forEach(b => b.classList.toggle('is-active', Number(b.dataset.gcQuick) === v));
            less.disabled = v <= min;
            more.disabled = v >= max;
        };
        quick.forEach(b => b.addEventListener('click', () => setAmount(Number(b.dataset.gcQuick))));
        form.querySelectorAll('[data-gc-step]').forEach(b => b.addEventListener('click', () => {
            setAmount(Number(input.value) + Number(b.dataset.gcStep) * step);
        }));
        setAmount(Number(input.value) || 200);

        const message = form.querySelector('[data-gc-message]');
        const sender = form.querySelector('[data-gc-sender]');
        const count = form.querySelector('[data-gc-count]');
        const previewMsg = document.querySelector('[data-gc-preview-message]');
        const previewFrom = document.querySelector('[data-gc-preview-from]');
        const syncText = () => {
            const m = message.value.trim();
            const s = sender.value.trim();
            count.textContent = message.value.length;
            previewMsg.textContent = m ? '“' + m + '”' : '';
            previewMsg.hidden = !m;
            previewFrom.textContent = s ? '— ' + s : '';
            previewFrom.hidden = !s;
        };
        message.addEventListener('input', syncText);
        sender.addEventListener('input', syncText);
        syncText();
    }

    /* ---------- Kartın səhifəsi ---------- */
    const show = document.querySelector('[data-gc-show]');
    if (!show) return;
    const d = show.dataset;

    const copyBtn = show.querySelector('[data-gc-copy]');
    copyBtn?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(copyBtn.dataset.gcCopy);
            const label = copyBtn.textContent;
            copyBtn.textContent = d.copied;
            setTimeout(() => { copyBtn.textContent = label; }, 1800);
        } catch (e) { /* köhnə brauzer — kod kartın üzərində görünür */ }
    });

    show.querySelector('[data-gc-share]')?.addEventListener('click', async () => {
        if (navigator.share) {
            try {
                await navigator.share({ title: d.shareTitle, text: d.shareTitle, url: d.shareUrl });
                return;
            } catch (e) {
                if (e && e.name === 'AbortError') return;
            }
        }
        window.open('https://wa.me/?text=' + encodeURIComponent(d.shareTitle + ': ' + d.shareUrl), '_blank', 'noopener');
    });

    show.querySelector('[data-gc-download]')?.addEventListener('click', async () => {
        try {
            const blob = await renderPostcard();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = d.fileName || 'parfumshop-gift-card.png';
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(url), 4000);
        } catch (e) {
            console.error(e);
        }
    });

    /** Endirilən şəkil: şaquli 4:5 açıqca (1080 × 1350) — kart, mətn, kimdən, istifadə qaydası */
    async function renderPostcard() {
        const W = 1080, H = 1350;
        const canvas = document.createElement('canvas');
        canvas.width = W;
        canvas.height = H;
        const ctx = canvas.getContext('2d');
        const font = getComputedStyle(document.body).fontFamily;
        const card = show.querySelector('[data-gc-card]');

        ctx.fillStyle = '#f6f3fb';
        ctx.fillRect(0, 0, W, H);

        // Kart
        const cx = 90, cy = 110, cw = W - 180, ch = Math.round(cw * 54 / 85.6);
        const grad = ctx.createRadialGradient(cx, cy, 0, cx, cy, cw * 1.2);
        grad.addColorStop(0, '#3d2d66');
        grad.addColorStop(0.55, '#2b1f4a');
        grad.addColorStop(1, '#1d1533');
        roundRect(ctx, cx, cy, cw, ch, 40);
        ctx.fillStyle = grad;
        ctx.fill();
        ctx.save();
        roundRect(ctx, cx, cy, cw, ch, 40);
        ctx.clip();
        ctx.strokeStyle = 'rgba(255,255,255,.12)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(cx + cw * 1.17, cy + ch * 1.4, cw * 0.35, 0, Math.PI * 2);
        ctx.stroke();
        ctx.restore();

        const pad = cw * 0.075;
        const logo = await logoImage(card.querySelector('.gc-card__logo svg'));
        if (logo) {
            const lw = cw * 0.38;
            ctx.drawImage(logo, cx + pad, cy + ch * 0.07, lw, lw * 88 / 512);
        }
        ctx.fillStyle = 'rgba(255,255,255,.7)';
        ctx.font = `500 ${Math.round(cw * 0.028)}px ${font}`;
        letterSpaced(ctx, card.querySelector('.gc-card__label').textContent.trim().toUpperCase(), cx + pad, cy + ch * 0.36, cw * 0.007);

        ctx.fillStyle = '#ffffff';
        ctx.font = `600 ${Math.round(cw * 0.13)}px ${font}`;
        const amount = card.querySelector('[data-gc-amount]').textContent.trim();
        ctx.fillText(amount, cx + pad, cy + ch * 0.6);
        const amountW = ctx.measureText(amount).width;
        ctx.font = `600 ${Math.round(cw * 0.036)}px ${font}`;
        ctx.fillStyle = 'rgba(255,255,255,.8)';
        ctx.fillText('AZN', cx + pad + amountW + cw * 0.02, cy + ch * 0.6 - cw * 0.05);

        ctx.fillStyle = '#ffffff';
        ctx.font = `500 ${Math.round(cw * 0.039)}px ui-monospace, Menlo, monospace`;
        letterSpaced(ctx, card.querySelector('[data-gc-code]').textContent.trim(), cx + pad, cy + ch * 0.9, cw * 0.005);
        const exp = card.querySelector('.gc-card__exp');
        if (exp) {
            ctx.textAlign = 'right';
            ctx.fillStyle = 'rgba(255,255,255,.65)';
            ctx.font = `500 ${Math.round(cw * 0.023)}px ${font}`;
            ctx.fillText(exp.querySelector('span').textContent.trim().toUpperCase(), cx + cw - pad, cy + ch * 0.84);
            ctx.fillStyle = '#ffffff';
            ctx.font = `600 ${Math.round(cw * 0.03)}px ${font}`;
            ctx.fillText(exp.querySelector('b').textContent.trim(), cx + cw - pad, cy + ch * 0.9);
            ctx.textAlign = 'left';
        }

        // Mətn və kimdən
        let y = cy + ch + 130;
        ctx.textAlign = 'center';
        if (d.message) {
            ctx.fillStyle = '#1a1520';
            ctx.font = `italic 400 46px ${font}`;
            for (const line of wrap(ctx, '“' + d.message + '”', W - 200).slice(0, 5)) {
                ctx.fillText(line, W / 2, y);
                y += 62;
            }
        }
        if (d.sender) {
            y += 10;
            ctx.fillStyle = '#6b6575';
            ctx.font = `400 38px ${font}`;
            ctx.fillText('— ' + d.sender, W / 2, y);
        }
        ctx.fillStyle = '#6b6575';
        ctx.font = `400 28px ${font}`;
        const how = wrap(ctx, d.how, W - 200);
        how.forEach((line, i) => ctx.fillText(line, W / 2, H - 80 - (how.length - 1 - i) * 40));

        return new Promise((resolve, reject) => canvas.toBlob(b => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/png'));
    }

    function logoImage(svg) {
        if (!svg) return Promise.resolve(null);
        const clone = svg.cloneNode(true);
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clone.setAttribute('width', '1024');
        clone.setAttribute('height', '176');
        const src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(clone).replace(/currentColor/g, '#ffffff'));
        return new Promise(resolve => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = src;
        });
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function letterSpaced(ctx, text, x, y, spacing) {
        for (const ch of text) {
            ctx.fillText(ch, x, y);
            x += ctx.measureText(ch).width + spacing;
        }
    }

    function wrap(ctx, text, maxWidth) {
        const lines = [];
        let line = '';
        for (const word of String(text).split(/\s+/)) {
            const test = line ? line + ' ' + word : word;
            if (ctx.measureText(test).width > maxWidth && line) {
                lines.push(line);
                line = word;
            } else {
                line = test;
            }
        }
        if (line) lines.push(line);
        return lines;
    }
})();
