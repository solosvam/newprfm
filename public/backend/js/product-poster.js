(function () {
    'use strict';
    const colors = {
        background: '#fff',                            // fon
        band: '#22193a', logo: '#22193a', bandText: '#fff', // yuxarı: ağ fon + tünd loqo; aşağı: tünd zolaq
        rule: '#8a8198',                                  // loqonun yanlarındakı nazik xətlər
        image: '#ffffff',                                 // şəkil kartı
        accent: '#000',                                // brend adı
        text: '#1a1520', secondary: '#5f586b', muted: '#8a8198',
        border: '#ddd5ea', strong: '#cfc4e2',
        card: '#fbf9f6', cardBorder: '#e6dfd4', cardSize: '#2a2433', cardPrice: '#1a1530', // qiymət sətirləri (açıq krem)
        icon: '#b8a07e', divider: '#e2d9cc',
        shadow: 'rgba(43,31,74,.16)',
    };
    const font = '"Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, sans-serif';
    const priceFont = 'Georgia, "Times New Roman", serif';
    const priceRow = 132, priceGap = 18;

    function loadImage(url) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error('Məhsul şəkli və ya loqo yüklənmədi.'));
            image.src = url;
        });
    }

    async function loadLogo(url) {
        const response = await fetch(url);
        if (!response.ok) throw new Error('Loqo yüklənmədi.');
        const svg = (await response.text()).replace(/currentColor/g, colors.logo);
        const objectUrl = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
        try { return await loadImage(objectUrl); }
        finally { URL.revokeObjectURL(objectUrl); }
    }

    function box(ctx, x, y, w, h, radius, fill, border, shadow = 0) {
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.arcTo(x + w, y, x + w, y + h, radius);
        ctx.arcTo(x + w, y + h, x, y + h, radius);
        ctx.arcTo(x, y + h, x, y, radius);
        ctx.arcTo(x, y, x + w, y, radius);
        ctx.closePath();
        ctx.fillStyle = fill;
        if (shadow) { ctx.shadowColor = colors.shadow; ctx.shadowBlur = shadow; ctx.shadowOffsetY = shadow / 3; }
        ctx.fill();
        ctx.shadowColor = 'transparent';
        ctx.strokeStyle = border;
        ctx.lineWidth = 1;
        ctx.stroke();
        ctx.restore();
    }

    function text(ctx, value, y, size, weight, color, maxWidth = 900, spacing = 0, x = 540) {
        const content = String(value);
        ctx.fillStyle = color;
        ctx.textBaseline = 'top';
        const widthAt = (fontSize) => {
            ctx.font = `${weight} ${fontSize}px ${font}`;
            return ctx.measureText(content).width + Math.max(0, Array.from(content).length - 1) * spacing;
        };
        while (size > 12 && widthAt(size) > maxWidth) size--;
        ctx.font = `${weight} ${size}px ${font}`;
        if (!spacing) { ctx.textAlign = 'center'; ctx.fillText(content, x, y); return; }
        ctx.textAlign = 'left';
        let left = x - widthAt(size) / 2;
        for (const character of content) {
            ctx.fillText(character, left, y);
            left += ctx.measureText(character).width + spacing;
        }
    }

    // Flakon ikonu (qiymət sətrində ölçünün yanında): qapaq, boyun, gövdə
    function bottleIcon(ctx, x, y, size) {
        const u = size / 10;
        ctx.save();
        ctx.strokeStyle = colors.icon;
        ctx.lineWidth = Math.max(2, u * 0.7);
        ctx.lineJoin = 'round';
        ctx.strokeRect(x + 3.5 * u, y, 3 * u, 1.6 * u);
        ctx.beginPath();
        ctx.moveTo(x + 4.2 * u, y + 1.6 * u);
        ctx.lineTo(x + 4.2 * u, y + 2.8 * u);
        ctx.moveTo(x + 5.8 * u, y + 1.6 * u);
        ctx.lineTo(x + 5.8 * u, y + 2.8 * u);
        ctx.stroke();
        ctx.beginPath();
        ctx.roundRect(x + 1.5 * u, y + 2.8 * u, 7 * u, 7.2 * u, 1.6 * u);
        ctx.stroke();
        ctx.restore();
    }

    // Qiymət: 989 → "989.00 ₼"
    function priceLabel(value) {
        return `${value} ₼`;
    }

    function nameLines(ctx, name) {
        ctx.font = `700 42px ${font}`;
        const lines = [];
        let line = '';
        for (const word of String(name).split(/\s+/)) {
            const next = line ? `${line} ${word}` : word;
            if (line && ctx.measureText(next).width > 900) { lines.push(line); line = word; }
            else line = next;
        }
        if (line) lines.push(line);
        return lines;
    }

    async function renderPoster(product) {
        const [image, logo] = await Promise.all([loadImage(product.image), loadLogo(product.logo), document.fonts.ready]);
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('Şəkil hazırlamaq mümkün olmadı.');
        const rows = product.variants.length;
        const lines = nameLines(ctx, product.name);
        // Hündürlük məzmuna görə (əvvəl ən az 1350 idi): şəkil 440, boşluqlar kiçik
        const imageSize = 440;
        const imageY = 182;
        const brandY = imageY + imageSize + 30;
        const nameY = brandY + 40;
        const subtitleY = nameY + lines.length * 51 + 10;
        const pricesHeight = rows * priceRow + (rows - 1) * priceGap;
        const pricesY = subtitleY + 28 + 36;
        canvas.width = 1080;
        const footer = 72;
        canvas.height = pricesY + pricesHeight + 40 + footer;
        const height = canvas.height;
        ctx.fillStyle = colors.background;
        ctx.fillRect(0, 0, 1080, height);

        // Yuxarı hissə — ağ fon, tünd loqo, yanlarında nazik xətlər: — PARFUMSHOP.AZ —
        const logoHeight = 65;
        const logoWidth = logoHeight * logo.naturalWidth / logo.naturalHeight;
        const logoY = 38;
        ctx.drawImage(logo, 540 - logoWidth / 2, logoY, logoWidth, logoHeight);
        ctx.strokeStyle = colors.rule;
        ctx.lineWidth = 2;
        const ruleY = logoY + logoHeight * 0.42; // loqonun böyük yazısının ortası
        const gap = 28, ruleLength = 70;
        ctx.beginPath();
        ctx.moveTo(540 - logoWidth / 2 - gap - ruleLength, ruleY);
        ctx.lineTo(540 - logoWidth / 2 - gap, ruleY);
        ctx.moveTo(540 + logoWidth / 2 + gap, ruleY);
        ctx.lineTo(540 + logoWidth / 2 + gap + ruleLength, ruleY);
        ctx.stroke();

        box(ctx, 540 - imageSize / 2, imageY, imageSize, imageSize, 24, colors.image, colors.strong, 30);
        const scale = Math.min((imageSize - 40) / image.naturalWidth, (imageSize - 40) / image.naturalHeight);
        const w = image.naturalWidth * scale, h = image.naturalHeight * scale;
        ctx.drawImage(image, 540 - w / 2, imageY + imageSize / 2 - h / 2, w, h);
        text(ctx, product.brand.toLocaleUpperCase('az'), brandY, 28, 600, colors.accent, 900, 4);
        lines.forEach((line, index) => text(ctx, line, nameY + index * 51, 42, 700, colors.text));
        text(ctx, product.subtitle, subtitleY, 28, 400, colors.secondary, 900, 1);

        // Qiymətlər: hər ölçü ayrıca enli sətirdə, alt-alta — solda flakon + ölçü, sağda iri qiymət
        const rowX = 60, rowWidth = 960, dividerX = rowX + Math.round(rowWidth * 0.44);
        product.variants.forEach((variant, index) => {
            const y = pricesY + index * (priceRow + priceGap);
            box(ctx, rowX, y, rowWidth, priceRow, 22, colors.card, colors.cardBorder, 18);

            ctx.font = `500 40px ${font}`;
            const sizeWidth = ctx.measureText(variant.size).width;
            const iconSize = 50, iconGap = 34;
            const groupLeft = rowX + (dividerX - rowX) / 2 - (iconSize + iconGap + sizeWidth) / 2;
            bottleIcon(ctx, groupLeft, y + priceRow / 2 - iconSize / 2, iconSize);
            ctx.fillStyle = colors.cardSize;
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText(variant.size, groupLeft + iconSize + iconGap, y + priceRow / 2 + 2);

            ctx.strokeStyle = colors.divider;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(dividerX, y + 28);
            ctx.lineTo(dividerX, y + priceRow - 28);
            ctx.stroke();

            const right = rowX + rowWidth;
            let priceSize = 76;
            ctx.font = `700 ${priceSize}px ${priceFont}`;
            while (priceSize > 40 && ctx.measureText(priceLabel(variant.price)).width > right - dividerX - 60) {
                priceSize -= 2;
                ctx.font = `700 ${priceSize}px ${priceFont}`;
            }
            ctx.fillStyle = colors.cardPrice;
            ctx.textAlign = 'center';
            ctx.fillText(priceLabel(variant.price), dividerX + (right - dividerX) / 2, y + priceRow / 2 + 4);
        });
        // Aşağı zolaq
        ctx.fillStyle = colors.band;
        ctx.fillRect(0, height - footer, 1080, footer);
        text(ctx, 'www.ParfumShop.az • Orijinal Ətirlər', height - footer + 24, 22, 600, colors.bandText, 960, 1);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('PNG hazırlamaq mümkün olmadı.');
        return { blob, filename: product.filename, caption: product.caption || '' };
    }

    function clipboardWrite(blobPromise) {
        if (!window.isSecureContext || !navigator.clipboard?.write || typeof ClipboardItem === 'undefined') {
            return Promise.resolve(false);
        }
        // Start during the click: Safari requires the user gesture even while PNG is being prepared.
        try {
            return navigator.clipboard.write([new ClipboardItem({ 'image/png': blobPromise })]).then(() => true, () => false);
        } catch (error) { return Promise.resolve(false); }
    }

    // Posterin yanında göndərilən qiymət mətni (müştəri şəkildəki yazını oxumadan soruşmasın)
    async function copyText(value) {
        if (!value) return false;
        try {
            if (window.isSecureContext && navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(value);
                return true;
            }
        } catch (error) { /* köhnə üsula keç */ }
        const area = document.createElement('textarea');
        area.value = value;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.append(area);
        area.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (error) { ok = false; }
        area.remove();
        return ok;
    }

    // Operator paneli (backend/assistant) də eyni posteri çəkir
    window.ProductPoster = { render: renderPoster, copy: clipboardWrite, copyText };

    document.addEventListener('DOMContentLoaded', function () {
        const element = document.getElementById('productPosterModal');
        if (!element) return;
        const modal = new bootstrap.Modal(element);
        const status = element.querySelector('[data-poster-status]');
        const preview = element.querySelector('[data-poster-preview]');
        const copy = element.querySelector('[data-poster-copy]');
        const download = element.querySelector('[data-poster-download]');
        const caption = element.querySelector('[data-poster-caption]');
        const copyCaption = element.querySelector('[data-poster-copy-text]');
        let current = null, previewUrl = null, busy = false;
        function message(content, kind) {
            status.textContent = content;
            status.className = `alert alert-${kind} text-start`;
        }
        function copyResult(ok) {
            message(ok ? 'Şəkil kopyalandı. WhatsApp-da müştərinin söhbətini açıb Ctrl+V / Cmd+V vurun, sonra “Qiymət mətnini kopyala” basıb şəklin altındakı yazıya yapışdırın.'
                : 'Avtomatik kopyalama alınmadı. “Şəkli kopyala” düyməsini basın və ya PNG-ni endirib WhatsApp-a əlavə edin.', ok ? 'success' : 'warning');
        }
        document.addEventListener('click', async function (event) {
            const button = event.target.closest('[data-product-poster-url]');
            if (!button) return;
            event.preventDefault();
            if (busy) return;
            busy = true;
            button.disabled = true;
            current = null;
            copy.disabled = true;
            preview.hidden = true;
            download.hidden = true;
            caption.hidden = true;
            copyCaption.hidden = true;
            message('Poster hazırlanır...', 'info');
            modal.show();
            // Fresh saved product data is fetched on every click.
            const task = fetch(button.dataset.productPosterUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' })
                .then(async (response) => {
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'Məhsul məlumatları alınmadı.');
                    return renderPoster(data);
                });
            const blobPromise = task.then((result) => result.blob);
            // Observe rejection even when this browser has no clipboard image support.
            blobPromise.catch(() => {});
            const copied = clipboardWrite(blobPromise);
            try {
                current = await task;
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = URL.createObjectURL(current.blob);
                preview.src = previewUrl;
                preview.hidden = false;
                download.href = previewUrl;
                download.download = current.filename;
                download.hidden = false;
                copy.disabled = false;
                caption.value = current.caption;
                caption.rows = Math.max(2, current.caption.split('\n').length);
                caption.hidden = !current.caption;
                copyCaption.hidden = !current.caption;
                copyResult(await copied);
            } catch (error) {
                message(error.message || 'Poster hazırlamaq mümkün olmadı.', 'danger');
            } finally { busy = false; button.disabled = false; }
        });
        copy.addEventListener('click', async function () {
            if (!current) return;
            copy.disabled = true;
            copyResult(await clipboardWrite(Promise.resolve(current.blob)));
            copy.disabled = false;
        });
        copyCaption.addEventListener('click', async function () {
            if (!current) return;
            const ok = await copyText(current.caption);
            message(ok ? 'Qiymət mətni kopyalandı — WhatsApp-da şəklin altındakı yazı sahəsinə və ya söhbətə yapışdırın.'
                : 'Mətn kopyalanmadı — aşağıdakı mətni seçib əl ilə kopyalayın.', ok ? 'success' : 'warning');
        });
        window.addEventListener('pagehide', () => { if (previewUrl) URL.revokeObjectURL(previewUrl); });
    });
})();
