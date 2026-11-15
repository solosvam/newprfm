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
        const cardAmounts = [...document.querySelectorAll('[data-gc-card] [data-gc-amount]')];
        const quick = [...form.querySelectorAll('[data-gc-quick]')];
        const less = form.querySelector('[data-gc-step="-1"]');
        const more = form.querySelector('[data-gc-step="1"]');

        const setAmount = value => {
            const v = Math.min(max, Math.max(min, Math.round(value / step) * step));
            input.value = v;
            valueEl.textContent = v;
            if (payEl) payEl.textContent = v;
            cardAmounts.forEach(el => { el.textContent = v; });
            quick.forEach(b => b.classList.toggle('is-active', Number(b.dataset.gcQuick) === v));
            less.disabled = v <= min;
            more.disabled = v >= max;
        };
        quick.forEach(b => b.addEventListener('click', () => setAmount(Number(b.dataset.gcQuick))));
        form.querySelectorAll('[data-gc-step]').forEach(b => b.addEventListener('click', () => {
            setAmount(Number(input.value) + Number(b.dataset.gcStep) * step);
        }));
        setAmount(Number(input.value) || 200);

        // Dizayn: seçilən dizaynın önizləməsi göstərilir
        form.querySelectorAll('[data-gc-design]').forEach(radio => radio.addEventListener('change', () => {
            document.querySelectorAll('[data-gc-design-preview]').forEach(el => { el.hidden = el.dataset.gcDesignPreview !== radio.value; });
        }));

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

    /** Dizaynlar: kartın və açıqcanın rəngləri (CSS-dəki .gc-card--* ilə eyni) */
    const THEMES = {
        standard: {
            bg: ['#3d2d66', '#2b1f4a', '#1d1533'], page: '#f6f3fb', ink: '#ffffff', muted: 'rgba(255,255,255,.7)',
            msg: '#1a1520', from: '#6b6575', how: '#6b6575', serif: false, gold: false,
        },
        signature: {
            bg: ['#3d2c68', '#2b1f4a', '#150e28'], page: '#1d1435', ink: '#ecd8a0', muted: '#cfb676',
            msg: '#f1dfaa', from: '#cfb676', how: '#a38d5c', serif: true, gold: true,
        },
    };
    const GOLD = [[0, '#b8893b'], [0.42, '#f6e3a6'], [0.68, '#c99a45'], [1, '#efd38c']];

    /** Endirilən şəkil: şaquli 4:5 açıqca (1080 × 1350) — kart, mətn, kimdən, istifadə qaydası */
    async function renderPostcard() {
        const W = 1080, H = 1350;
        const canvas = document.createElement('canvas');
        canvas.width = W;
        canvas.height = H;
        const ctx = canvas.getContext('2d');
        const font = getComputedStyle(document.body).fontFamily;
        const serif = '"Didot", "Bodoni 72", "Playfair Display", Georgia, serif';
        const card = show.querySelector('[data-gc-card]');
        const theme = THEMES[card.dataset.design] || THEMES.standard;
        const gold = (x0, y0, x1, y1) => {
            const g = ctx.createLinearGradient(x0, y0, x1, y1);
            GOLD.forEach(([o, c]) => g.addColorStop(o, c));
            return g;
        };

        ctx.fillStyle = theme.page;
        ctx.fillRect(0, 0, W, H);

        // Kart
        const cx = 90, cy = 110, cw = W - 180, ch = Math.round(cw * 54 / 85.6);
        const grad = ctx.createRadialGradient(cx, cy, 0, cx, cy, cw * 1.25);
        theme.bg.forEach((c, i) => grad.addColorStop([0, 0.45, 1][i], c));
        roundRect(ctx, cx, cy, cw, ch, 40);
        ctx.fillStyle = grad;
        ctx.fill();

        ctx.save();
        roundRect(ctx, cx, cy, cw, ch, 40);
        ctx.clip();
        if (theme.gold) {
            // Gilyoş naxışı (sağ aşağı) və nazik qızılı çərçivə
            // Gilyoş — ikonun arxasında (CSS-dəki kimi)
            ctx.strokeStyle = 'rgba(212,175,95,.11)';
            ctx.lineWidth = 0.9;
            const gx = cx + cw * 672 / 856, gy = cy + ch * 236 / 540;
            for (let i = 0; i < 36; i++) {
                ctx.beginPath();
                ctx.ellipse(gx, gy, cw * 0.222, ch * 0.122, (i * 5) * Math.PI / 180, 0, Math.PI * 2);
                ctx.stroke();
            }
            ctx.strokeStyle = 'rgba(212,175,95,.55)';
            ctx.lineWidth = 1.6;
            roundRect(ctx, cx + cw * 0.026, cy + ch * 0.041, cw * 0.948, ch * 0.918, 22);
            ctx.stroke();
        } else {
            ctx.strokeStyle = 'rgba(255,255,255,.12)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(cx + cw * 1.17, cy + ch * 1.4, cw * 0.35, 0, Math.PI * 2);
            ctx.stroke();
        }
        ctx.restore();

        const pad = cw * 0.075;
        // İkon gilyoşun mərkəzində (kartdakı SVG ilə eyni: mərkəz 672×236, hündürlük 300 — 856×540 koordinatında)
        const icon = card.querySelector('[data-gc-icon]');
        if (icon) {
            const ih = ch * 300 / 540, iw = ih * 480 / 760;
            const wrapper = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            wrapper.setAttribute('viewBox', '0 0 480 760');
            icon.querySelectorAll('path').forEach(p => wrapper.appendChild(p.cloneNode(true)));
            const img = await svgImage(wrapper, true, 480, 760);
            if (img) ctx.drawImage(img, cx + cw * 672 / 856 - iw / 2, cy + ch * 236 / 540 - ih / 2, iw, ih);
        }
        const logo = await logoImage(card.querySelector('.gc-card__logo svg'), theme.gold);
        if (logo) {
            const lw = cw * 0.38;
            ctx.drawImage(logo, cx + pad, cy + ch * 0.07, lw, lw * 88 / 512);
        }
        ctx.fillStyle = theme.muted;
        ctx.font = `500 ${Math.round(cw * 0.028)}px ${font}`;
        letterSpaced(ctx, card.querySelector('.gc-card__label').textContent.trim().toUpperCase(), cx + pad, cy + ch * 0.36, cw * (theme.gold ? 0.009 : 0.007));
        if (theme.gold) {
            const lg = ctx.createLinearGradient(cx + pad, 0, cx + pad + cw * 0.1, 0);
            lg.addColorStop(0, '#d4af5f');
            lg.addColorStop(1, 'rgba(212,175,95,0)');
            ctx.fillStyle = lg;
            ctx.fillRect(cx + pad, cy + ch * 0.395, cw * 0.1, 2);
        }

        const amount = card.querySelector('[data-gc-amount]').textContent.trim();
        const amountY = cy + ch * (theme.gold ? 0.7 : 0.6);
        ctx.font = theme.serif ? `500 ${Math.round(cw * 0.18)}px ${serif}` : `600 ${Math.round(cw * 0.13)}px ${font}`;
        const amountW = ctx.measureText(amount).width;
        ctx.fillStyle = theme.gold ? gold(cx + pad, amountY - cw * 0.12, cx + pad + amountW, amountY) : theme.ink;
        ctx.fillText(amount, cx + pad, amountY);
        ctx.font = `500 ${Math.round(cw * 0.036)}px ${theme.serif ? serif : font}`;
        ctx.fillStyle = theme.gold ? '#d4af5f' : 'rgba(255,255,255,.8)';
        letterSpaced(ctx, 'AZN', cx + pad + amountW + cw * 0.02, amountY - cw * 0.05, theme.gold ? cw * 0.008 : 0);

        const codeLabel = card.querySelector('.gc-card__code span');
        if (codeLabel) {
            ctx.fillStyle = theme.gold ? '#b79d62' : 'rgba(255,255,255,.65)';
            ctx.font = `500 ${Math.round(cw * 0.023)}px ${font}`;
            letterSpaced(ctx, codeLabel.textContent.trim().toUpperCase(), cx + pad, cy + ch * 0.84, cw * 0.003);
        }
        ctx.fillStyle = theme.ink;
        ctx.font = `500 ${Math.round(cw * 0.039)}px ui-monospace, Menlo, monospace`;
        letterSpaced(ctx, card.querySelector('[data-gc-code]').textContent.trim(), cx + pad, cy + ch * 0.9, cw * 0.005);
        const exp = card.querySelector('.gc-card__exp');
        if (exp) {
            ctx.textAlign = 'right';
            ctx.fillStyle = theme.gold ? '#b79d62' : 'rgba(255,255,255,.65)';
            ctx.font = `500 ${Math.round(cw * 0.023)}px ${font}`;
            ctx.fillText(exp.querySelector('span').textContent.trim().toUpperCase(), cx + cw - pad, cy + ch * 0.84);
            ctx.fillStyle = theme.ink;
            ctx.font = `600 ${Math.round(cw * 0.03)}px ${font}`;
            ctx.fillText(exp.querySelector('b').textContent.trim(), cx + cw - pad, cy + ch * 0.9);
            ctx.textAlign = 'left';
        }

        // Mətn və kimdən
        let y = cy + ch + 130;
        ctx.textAlign = 'center';
        if (d.message) {
            ctx.fillStyle = theme.msg;
            ctx.font = theme.serif ? `italic 400 50px ${serif}` : `italic 400 46px ${font}`;
            for (const line of wrap(ctx, '“' + d.message + '”', W - 200).slice(0, 5)) {
                ctx.fillText(line, W / 2, y);
                y += 64;
            }
        }
        if (d.sender) {
            y += 10;
            ctx.fillStyle = theme.from;
            ctx.font = `400 38px ${theme.serif ? serif : font}`;
            ctx.fillText('— ' + d.sender, W / 2, y);
        }
        if (theme.gold) {
            ctx.fillStyle = gold(W / 2 - 60, 0, W / 2 + 60, 0);
            ctx.fillRect(W / 2 - 60, H - 150, 120, 2);
        }
        ctx.fillStyle = theme.how;
        ctx.font = `400 28px ${font}`;
        const how = wrap(ctx, d.how, W - 200);
        how.forEach((line, i) => ctx.fillText(line, W / 2, H - 80 - (how.length - 1 - i) * 40));

        return new Promise((resolve, reject) => canvas.toBlob(b => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/png'));
    }

    const logoImage = (svg, goldFill) => svgImage(svg, goldFill, 1024, 176);

    /** Səhifədəki SVG-ni (loqo, ikon) şəkilə çevirir; qızılı — gradientlə, yoxsa ağ */
    function svgImage(svg, goldFill, width, height) {
        if (!svg) return Promise.resolve(null);
        const clone = svg.cloneNode(true);
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clone.setAttribute('width', String(width));
        clone.setAttribute('height', String(height));
        clone.removeAttribute('class');
        clone.querySelectorAll('path').forEach(p => { if (!p.getAttribute('fill')) p.setAttribute('fill', 'currentColor'); });
        let markup = new XMLSerializer().serializeToString(clone);
        if (goldFill) {
            const defs = '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
                + GOLD.map(([o, c]) => `<stop offset="${o}" stop-color="${c}"/>`).join('') + '</linearGradient></defs>';
            markup = markup.replace(/currentColor/g, 'url(#g)').replace(/(<svg[^>]*>)/, '$1' + defs);
        } else {
            markup = markup.replace(/currentColor/g, '#ffffff');
        }
        const src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(markup);
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
