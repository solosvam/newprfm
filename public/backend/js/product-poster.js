(function () {
    'use strict';
    const colors = {
        background: '#fff',                            // fon
        band: '#1f1a28', logo: '#BDA4E5', bandText: '#fff', // tünd zolaqlar
        image: '#ffffff',                                 // şəkil kartı
        accent: '#6f52a8',                                // brend adı
        text: '#1a1520', secondary: '#5f586b', muted: '#8a8198',
        border: '#ddd5ea', strong: '#cfc4e2',
        card: '#2b1f4a', cardSize: '#FFFFFF', cardPrice: '#ffffff', // ölçü kartları
        shadow: 'rgba(43,31,74,.16)',
    };
    const font = '"Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, sans-serif';

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
        const rows = Math.ceil(product.variants.length / 3);
        const lines = nameLines(ctx, product.name);
        // Hündürlük məzmuna görə (əvvəl ən az 1350 idi): şəkil 440, boşluqlar kiçik
        const imageSize = 440;
        const imageY = 182;
        const brandY = imageY + imageSize + 30;
        const nameY = brandY + 40;
        const subtitleY = nameY + lines.length * 51 + 10;
        const pricesHeight = rows * 105 + (rows - 1) * 20;
        const pricesY = subtitleY + 28 + 36;
        canvas.width = 1080;
        const footer = 72;
        canvas.height = pricesY + pricesHeight + 40 + footer;
        const height = canvas.height;
        ctx.fillStyle = colors.background;
        ctx.fillRect(0, 0, 1080, height);

        // Yuxarı zolaq — tünd, loqo lavanda
        ctx.fillStyle = colors.band;
        ctx.fillRect(0, 0, 1080, 142);
        const logoWidth = 65 * logo.naturalWidth / logo.naturalHeight;
        ctx.drawImage(logo, 540 - logoWidth / 2, 38, logoWidth, 65);

        box(ctx, 540 - imageSize / 2, imageY, imageSize, imageSize, 24, colors.image, colors.strong, 30);
        const scale = Math.min((imageSize - 40) / image.naturalWidth, (imageSize - 40) / image.naturalHeight);
        const w = image.naturalWidth * scale, h = image.naturalHeight * scale;
        ctx.drawImage(image, 540 - w / 2, imageY + imageSize / 2 - h / 2, w, h);
        text(ctx, product.brand.toLocaleUpperCase('az'), brandY, 28, 600, colors.accent, 900, 4);
        lines.forEach((line, index) => text(ctx, line, nameY + index * 51, 42, 700, colors.text));
        text(ctx, product.subtitle, subtitleY, 28, 400, colors.secondary, 900, 1);

        const columns = Math.min(3, product.variants.length);
        const cardWidth = (900 - (columns - 1) * 20) / columns;
        product.variants.forEach((variant, index) => {
            const x = 90 + (index % columns) * (cardWidth + 20);
            const y = pricesY + Math.floor(index / columns) * 125;
            box(ctx, x, y, cardWidth, 105, 16, colors.card, colors.card);
            text(ctx, variant.size, y + 20, 22, 600, colors.cardSize, cardWidth - 30, 0, x + cardWidth / 2);
            text(ctx, `${variant.price} ₼`, y + 56, 32, 800, colors.cardPrice, cardWidth - 30, 0, x + cardWidth / 2);
        });
        // Aşağı zolaq
        ctx.fillStyle = colors.band;
        ctx.fillRect(0, height - footer, 1080, footer);
        text(ctx, 'www.parfumshop.az • Orijinal Ətirlər', height - footer + 26, 19, 500, colors.bandText, 960, 1.5);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('PNG hazırlamaq mümkün olmadı.');
        return { blob, filename: product.filename };
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

    document.addEventListener('DOMContentLoaded', function () {
        const element = document.getElementById('productPosterModal');
        if (!element) return;
        const modal = new bootstrap.Modal(element);
        const status = element.querySelector('[data-poster-status]');
        const preview = element.querySelector('[data-poster-preview]');
        const copy = element.querySelector('[data-poster-copy]');
        const download = element.querySelector('[data-poster-download]');
        let current = null, previewUrl = null, busy = false;
        function message(content, kind) {
            status.textContent = content;
            status.className = `alert alert-${kind} text-start`;
        }
        function copyResult(ok) {
            message(ok ? 'Şəkil kopyalandı. WhatsApp-da müştərinin söhbətini açıb Ctrl+V / Cmd+V vurun.'
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
        window.addEventListener('pagehide', () => { if (previewUrl) URL.revokeObjectURL(previewUrl); });
    });
})();
