/* Virtual hədiyyə kartı: alış səhifəsi (məbləğ, dizayn, canlı açıqca önizləməsi); kartın səhifəsi (Göndər / Endir / Kodu kopyala).
   Önizləmə və "Endir" eyni funksiyadan (renderPostcard) çəkilir — müştəri ödənişdən əvvəl göndəriləcək şəklin özünü görür. */
(() => {
    'use strict';

    /**
     * Dizaynlar: kartın və açıqcanın rəngləri (CSS-dəki .gc-card--* dəyişənləri ilə eyni).
     * foil — loqo və məbləğin metal gradienti; page/msg/from/how — açıqcanın fonu və mətni.
     */
    const GOLD = [[0, '#b8893b'], [0.42, '#f6e3a6'], [0.68, '#c99a45'], [1, '#efd38c']];
    const PINK = [[0, '#e2a3ab'], [0.42, '#fdeeee'], [0.68, '#d8959e'], [1, '#f6d6d9']];
    const SAGE = [[0, '#3e5a3a'], [0.42, '#7f9f78'], [0.68, '#4f6b4a'], [1, '#6d8e67']];
    const CHAMP = [[0, '#6b4c1a'], [0.42, '#b8893b'], [0.68, '#5e4219'], [1, '#9a722c']];
    const THEMES = {
        standard: {
            bg: ['#3d2d66', '#2b1f4a', '#1d1533'], page: '#f6f3fb', ink: '#ffffff', muted: 'rgba(255,255,255,.7)', soft: 'rgba(255,255,255,.65)',
            line: null, foil: null, msg: '#1a1520', from: '#6b6575', how: '#6b6575',
        },
        signature: {
            bg: ['#3d2c68', '#2b1f4a', '#150e28'], page: '#1d1435', ink: '#ecd8a0', muted: '#cfb676', soft: '#b79d62',
            line: '#d4af5f', foil: GOLD, msg: '#f1dfaa', from: '#cfb676', how: '#a38d5c',
        },
        love: {
            bg: ['#922e46', '#6c1a2e', '#3d0c19'], page: '#f8e8e6', ink: '#f8e1e1', muted: '#e7b3b9', soft: '#d39aa2',
            line: '#ecbcc2', foil: PINK, msg: '#6c1a2e', from: '#a35368', how: '#a35368',
        },
        bloom: {
            bg: ['#fffdf7', '#f7f1e3', '#ece3cd'], page: '#eef2e8', ink: '#2f4a35', muted: '#6f8f6c', soft: '#7f9a7a',
            line: '#8aa585', foil: SAGE, msg: '#2f4a35', from: '#6f8f6c', how: '#6f8f6c',
        },
        celebration: {
            bg: ['#fbf3e2', '#f1e1bf', '#e2c994'], page: '#f7efe0', ink: '#4a3517', muted: '#8a6b33', soft: '#957641',
            line: '#b8893b', foil: CHAMP, msg: '#4a3517', from: '#8a6b33', how: '#8a6b33',
        },
    };

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
        const message = form.querySelector('[data-gc-message]');
        const sender = form.querySelector('[data-gc-sender]');
        const count = form.querySelector('[data-gc-count]');
        const preview = document.querySelector('[data-gc-postcard]');

        // Canlı önizləmə: hər dəyişiklikdən sonra açıqca yenidən çəkilir (son sorğu qalib gəlir)
        let version = 0;
        let timer = null;
        const redraw = () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                const current = ++version;
                const design = form.querySelector('[data-gc-design]:checked')?.value;
                const card = document.querySelector(`[data-gc-design-preview="${design}"] [data-gc-card]`)
                    || document.querySelector('[data-gc-card]');
                if (!card || !preview) return;
                try {
                    const canvas = await renderPostcard(card, {
                        message: message.value.trim(), sender: sender.value.trim(), how: preview.dataset.how,
                    });
                    if (current !== version) return;
                    const ctx = preview.getContext('2d');
                    ctx.clearRect(0, 0, preview.width, preview.height);
                    ctx.drawImage(canvas, 0, 0);
                    preview.classList.add('is-ready');
                } catch (e) {
                    console.error(e);
                }
            }, 80);
        };

        const setAmount = value => {
            const v = Math.min(max, Math.max(min, Math.round(value / step) * step));
            input.value = v;
            valueEl.textContent = v;
            if (payEl) payEl.textContent = v;
            cardAmounts.forEach(el => { el.textContent = v; });
            quick.forEach(b => b.classList.toggle('is-active', Number(b.dataset.gcQuick) === v));
            less.disabled = v <= min;
            more.disabled = v >= max;
            redraw();
        };
        quick.forEach(b => b.addEventListener('click', () => setAmount(Number(b.dataset.gcQuick))));
        form.querySelectorAll('[data-gc-step]').forEach(b => b.addEventListener('click', () => {
            setAmount(Number(input.value) + Number(b.dataset.gcStep) * step);
        }));
        // Dizayna uyğun nümunə mətn (placeholder) — yalnız boş sahədə görünür, yazılmış mətnə toxunulmur
        let placeholders = {};
        try { placeholders = JSON.parse(message.dataset.placeholders || '{}'); } catch (e) {}
        form.querySelectorAll('[data-gc-design]').forEach(radio => radio.addEventListener('change', () => {
            if (placeholders[radio.value]) message.placeholder = placeholders[radio.value];
            redraw();
        }));
        const syncText = () => {
            count.textContent = message.value.length;
            redraw();
        };
        message.addEventListener('input', syncText);
        sender.addEventListener('input', syncText);
        count.textContent = message.value.length;
        setAmount(Number(input.value) || 200);
        // Serif şrift yüklənəndən sonra yenidən (əks halda ilk çəkiliş ehtiyat şriftlə ola bilər)
        document.fonts?.ready.then(redraw);
    }

    /* ---------- Kartın səhifəsi ---------- */
    const show = document.querySelector('[data-gc-show]');
    const d = show?.dataset || {};
    if (show) {

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
            const canvas = await renderPostcard(show.querySelector('[data-gc-card]'), { message: d.message, sender: d.sender, how: d.how });
            const blob = await new Promise((resolve, reject) => canvas.toBlob(b => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/png'));
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
    }

    /**
     * Açıqca: şaquli 4:5 (1080 × 1350) — kart (səhifədəki .gc-card-dan), mətn, kimdən, istifadə qaydası.
     * @param {Element} card  .gc-card (dizayn, məbləğ, kod, tarix ondan oxunur)
     * @param {{message?: string, sender?: string, how?: string}} data
     * @returns {Promise<HTMLCanvasElement>}
     */
    async function renderPostcard(card, data) {
        const W = 1080, H = 1350;
        const canvas = document.createElement('canvas');
        canvas.width = W;
        canvas.height = H;
        const ctx = canvas.getContext('2d');
        const font = getComputedStyle(document.body).fontFamily;
        const serif = '"Playfair Display", Georgia, serif';
        // Şrift (Ə, ə daxil) lazımi hərflərlə yüklənsin — əks halda kanvas ehtiyat şriftlə çəkir
        if (document.fonts?.load) {
            const sample = 'AZN 0123456789 Əə ' + (data.message || '') + ' ' + (data.sender || '');
            await Promise.all([
                document.fonts.load(`500 40px "Playfair Display"`, sample),
                document.fonts.load(`italic 400 40px "Playfair Display"`, sample),
                document.fonts.load(`400 40px "Playfair Display"`, sample),
            ]).catch(() => {});
        }
        const theme = THEMES[card.dataset.design] || THEMES.standard;
        const foil = theme.foil;
        const foilFill = (x0, y0, x1, y1) => {
            const g = ctx.createLinearGradient(x0, y0, x1, y1);
            foil.forEach(([o, c]) => g.addColorStop(o, c));
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

        // Bəzək: kartdakı SVG olduğu kimi (naxış, ikon / güllər, çərçivə)
        ctx.save();
        roundRect(ctx, cx, cy, cw, ch, 40);
        ctx.clip();
        const deco = card.querySelector('.gc-card__deco');
        if (deco) {
            const img = await decoImage(deco, Math.round(cw), Math.round(ch));
            if (img) ctx.drawImage(img, cx, cy, cw, ch);
        } else {
            ctx.strokeStyle = 'rgba(255,255,255,.12)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(cx + cw * 1.17, cy + ch * 1.4, cw * 0.35, 0, Math.PI * 2);
            ctx.stroke();
        }
        ctx.restore();

        const pad = cw * 0.075;
        const logo = await svgImage(card.querySelector('.gc-card__logo svg'), foil, 1024, 176);
        if (logo) {
            const lw = cw * 0.38;
            ctx.drawImage(logo, cx + pad, cy + ch * 0.07, lw, lw * 88 / 512);
        }
        ctx.fillStyle = theme.muted;
        ctx.font = `500 ${Math.round(cw * 0.028)}px ${font}`;
        letterSpaced(ctx, card.querySelector('.gc-card__label').textContent.trim().toUpperCase(), cx + pad, cy + ch * 0.36, cw * (foil ? 0.009 : 0.007));
        if (theme.line) {
            const lg = ctx.createLinearGradient(cx + pad, 0, cx + pad + cw * 0.1, 0);
            lg.addColorStop(0, theme.line);
            lg.addColorStop(1, 'rgba(0,0,0,0)');
            ctx.fillStyle = lg;
            ctx.fillRect(cx + pad, cy + ch * 0.395, cw * 0.1, 2);
        }

        const amount = card.querySelector('[data-gc-amount]').textContent.trim();
        const amountY = cy + ch * (foil ? 0.7 : 0.6);
        ctx.font = foil ? `500 ${Math.round(cw * 0.18)}px ${serif}` : `600 ${Math.round(cw * 0.13)}px ${font}`;
        const amountW = ctx.measureText(amount).width;
        ctx.fillStyle = foil ? foilFill(cx + pad, amountY - cw * 0.12, cx + pad + amountW, amountY) : theme.ink;
        ctx.fillText(amount, cx + pad, amountY);
        ctx.font = `500 ${Math.round(cw * 0.036)}px ${foil ? serif : font}`;
        ctx.fillStyle = theme.line || 'rgba(255,255,255,.8)';
        letterSpaced(ctx, 'AZN', cx + pad + amountW + cw * 0.02, amountY - cw * 0.05, foil ? cw * 0.008 : 0);

        const codeLabel = card.querySelector('.gc-card__code span');
        if (codeLabel) {
            ctx.fillStyle = theme.soft;
            ctx.font = `500 ${Math.round(cw * 0.023)}px ${font}`;
            letterSpaced(ctx, codeLabel.textContent.trim().toUpperCase(), cx + pad, cy + ch * 0.84, cw * 0.003);
        }
        ctx.fillStyle = theme.ink;
        ctx.font = `500 ${Math.round(cw * 0.039)}px ui-monospace, Menlo, monospace`;
        letterSpaced(ctx, card.querySelector('[data-gc-code]').textContent.trim(), cx + pad, cy + ch * 0.9, cw * 0.005);
        const exp = card.querySelector('.gc-card__exp');
        if (exp) {
            ctx.textAlign = 'right';
            ctx.fillStyle = theme.soft;
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
        if (data.message) {
            ctx.fillStyle = theme.msg;
            ctx.font = foil ? `italic 400 50px ${serif}` : `italic 400 46px ${font}`;
            for (const line of wrap(ctx, '“' + data.message + '”', W - 200).slice(0, 5)) {
                ctx.fillText(line, W / 2, y);
                y += 64;
            }
        }
        if (data.sender) {
            y += 10;
            ctx.fillStyle = theme.from;
            ctx.font = `400 38px ${foil ? serif : font}`;
            ctx.fillText('— ' + data.sender, W / 2, y);
        }
        if (foil) {
            ctx.fillStyle = theme.from;
            ctx.fillRect(W / 2 - 60, H - 150, 120, 2);
        }
        ctx.fillStyle = theme.how;
        ctx.font = `400 28px ${font}`;
        const how = wrap(ctx, data.how, W - 200);
        how.forEach((line, i) => ctx.fillText(line, W / 2, H - 80 - (how.length - 1 - i) * 40));

        return canvas;
    }

    /** Kartın bəzək SVG-si (öz gradientləri içindədir) — olduğu kimi şəkilə */
    function decoImage(svg, width, height) {
        const clone = svg.cloneNode(true);
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clone.setAttribute('width', String(width * 2));
        clone.setAttribute('height', String(height * 2));
        clone.removeAttribute('class');
        return loadSvg(new XMLSerializer().serializeToString(clone));
    }

    /** Loqo: metal gradientlə (foil) və ya ağ */
    function svgImage(svg, foilStops, width, height) {
        if (!svg) return Promise.resolve(null);
        const clone = svg.cloneNode(true);
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clone.setAttribute('width', String(width));
        clone.setAttribute('height', String(height));
        clone.removeAttribute('class');
        clone.querySelectorAll('path').forEach(p => { p.setAttribute('fill', 'currentColor'); p.setAttribute('stroke', 'currentColor'); });
        let markup = new XMLSerializer().serializeToString(clone);
        if (foilStops) {
            const defs = '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
                + foilStops.map(([o, c]) => `<stop offset="${o}" stop-color="${c}"/>`).join('') + '</linearGradient></defs>';
            markup = markup.replace(/currentColor/g, 'url(#g)').replace(/(<svg[^>]*>)/, '$1' + defs);
        } else {
            markup = markup.replace(/currentColor/g, '#ffffff');
        }
        return loadSvg(markup);
    }

    function loadSvg(markup) {
        return new Promise(resolve => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(markup);
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
