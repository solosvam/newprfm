// Vəsiqə şəklinin kəsilməsi: fayl seçilən kimi modal açılır, müştəri vəsiqəni çərçivəyə salır.
// Çərçivə tərpəndikcə anında yoxlama: kəskinlik (bulanıqlıq), ölçü, işıq. Bloklamır — xəbərdarlıq edir.
// İstifadə: const blob = await window.IdCardCropper.open(file)  → JPEG Blob və ya null (ləğv).
// Tələb: vendor/cropper.min.js (Cropper.js 1.6) və səhifədə #idCardCropDialog.
(function () {
    'use strict';

    const dialog = document.getElementById('idCardCropDialog');
    if (!dialog || typeof window.Cropper !== 'function') return;

    const CARD_RATIO = 85.6 / 54;      // ID-1 kart ölçüsü (ISO/IEC 7810)
    const LIMITS = {
        sharp: 80,                     // Laplacian dispersiyası — real nümunələrdə: bulanıq 41, yaxşı 200–960
        minSide: 600,                  // kəsilmiş hissənin uzun tərəfi (mənbə pikseli)
        dark: 60, bright: 225,         // orta parlaqlıq (0–255)
    };
    const OUTPUT_MAX = 1800;           // serverə gedən şəklin uzun tərəfi

    const image = dialog.querySelector('[data-crop-image]');
    const confirmButton = dialog.querySelector('[data-crop-confirm]');
    const checks = {
        sharp: dialog.querySelector('[data-check="sharp"]'),
        size: dialog.querySelector('[data-check="size"]'),
        light: dialog.querySelector('[data-check="light"]'),
    };

    let cropper = null;
    let objectUrl = null;
    let resolveOpen = null;
    let timer = null;

    /* ---------- ölçmə: boz rəng, Laplacian dispersiyası, orta parlaqlıq ---------- */
    function analyze(canvas) {
        const scale = Math.min(1, 640 / Math.max(canvas.width, canvas.height)); // böyütmə yox — kəskinliyi süni azaldır
        const w = Math.max(3, Math.round(canvas.width * scale));
        const h = Math.max(3, Math.round(canvas.height * scale));
        const small = document.createElement('canvas');
        small.width = w;
        small.height = h;
        const ctx = small.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(canvas, 0, 0, w, h);
        const px = ctx.getImageData(0, 0, w, h).data;
        const gray = new Float32Array(w * h);
        let sum = 0;
        for (let i = 0, j = 0; i < px.length; i += 4, j++) {
            gray[j] = 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2];
            sum += gray[j];
        }
        // Welford: dispersiya bir keçiddə
        let n = 0, mean = 0, m2 = 0;
        for (let y = 1; y < h - 1; y++) {
            for (let x = 1; x < w - 1; x++) {
                const k = y * w + x;
                const lap = 4 * gray[k] - gray[k - 1] - gray[k + 1] - gray[k - w] - gray[k + w];
                n++;
                const d = lap - mean;
                mean += d / n;
                m2 += d * (lap - mean);
            }
        }
        return { sharp: n ? m2 / n : 0, brightness: sum / (w * h) };
    }

    function setCheck(node, ok) {
        if (!node) return;
        node.classList.toggle('is-ok', ok);
        node.classList.toggle('is-bad', !ok);
        node.querySelector('[data-check-text]').textContent = node.dataset[ok ? 'ok' : 'bad'];
    }

    function runChecks() {
        if (!cropper) return;
        const data = cropper.getData(true);
        const canvas = cropper.getCroppedCanvas({ maxWidth: 640, maxHeight: 640 });
        if (!canvas || !canvas.width) return;
        const result = analyze(canvas);
        const sharpOk = result.sharp >= LIMITS.sharp;
        const sizeOk = Math.max(data.width, data.height) >= LIMITS.minSide;
        const lightOk = result.brightness >= LIMITS.dark && result.brightness <= LIMITS.bright;
        setCheck(checks.sharp, sharpOk);
        setCheck(checks.size, sizeOk);
        setCheck(checks.light, lightOk);
        const allOk = sharpOk && sizeOk && lightOk;
        confirmButton.textContent = confirmButton.dataset[allOk ? 'label' : 'labelAnyway'];
        confirmButton.classList.toggle('is-warning', !allOk);
    }

    function scheduleChecks() {
        clearTimeout(timer);
        timer = setTimeout(runChecks, 180);
    }

    function cleanup() {
        clearTimeout(timer);
        if (cropper) { cropper.destroy(); cropper = null; }
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        image.removeAttribute('src');
        Object.values(checks).forEach((node) => node && node.classList.remove('is-ok', 'is-bad'));
    }

    function finish(blob) {
        const resolve = resolveOpen;
        resolveOpen = null;
        cleanup();
        if (dialog.open) dialog.close();
        if (resolve) resolve(blob);
    }

    function open(file) {
        if (resolveOpen) finish(null); // əvvəlki açıq qalıbsa
        return new Promise((resolve) => {
            resolveOpen = resolve;
            objectUrl = URL.createObjectURL(file);
            image.onerror = () => {
                window.alert(dialog.dataset.readError);
                finish(null);
            };
            image.onload = () => {
                image.onload = null;
                cropper = new window.Cropper(image, {
                    aspectRatio: CARD_RATIO,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.9,
                    background: false,
                    responsive: true,
                    restore: false,
                    toggleDragModeOnDblclick: false,
                    checkOrientation: true,
                    ready: runChecks,
                    crop: scheduleChecks,
                });
            };
            image.src = objectUrl;
            confirmButton.textContent = confirmButton.dataset.label;
            confirmButton.classList.remove('is-warning');
            dialog.showModal();
        });
    }

    function confirm() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({
            maxWidth: OUTPUT_MAX, maxHeight: OUTPUT_MAX,
            fillColor: '#fff', imageSmoothingEnabled: true, imageSmoothingQuality: 'high',
        });
        confirmButton.disabled = true;
        canvas.toBlob((blob) => {
            confirmButton.disabled = false;
            finish(blob);
        }, 'image/jpeg', 0.92);
    }

    // Fırlatmadan sonra şəkil sahəyə sığdırılır, çərçivə yenidən ortalanır (yoxsa şəklin bir hissəsi kənarda qalır)
    function rotate(degrees) {
        if (!cropper) return;
        cropper.clear();
        cropper.rotate(degrees);
        const box = cropper.getContainerData();
        const canvas = cropper.getCanvasData();
        const ratio = Math.min(box.width / canvas.width, box.height / canvas.height);
        const width = canvas.width * ratio;
        const height = canvas.height * ratio;
        cropper.setCanvasData({ left: (box.width - width) / 2, top: (box.height - height) / 2, width, height });
        cropper.crop();
        const area = cropper.getCanvasData();
        let cropWidth = area.width * 0.9;
        let cropHeight = cropWidth / CARD_RATIO;
        if (cropHeight > area.height * 0.9) { cropHeight = area.height * 0.9; cropWidth = cropHeight * CARD_RATIO; }
        cropper.setCropBoxData({
            left: area.left + (area.width - cropWidth) / 2, top: area.top + (area.height - cropHeight) / 2,
            width: cropWidth, height: cropHeight,
        });
        scheduleChecks();
    }

    dialog.querySelectorAll('[data-crop-rotate]').forEach((button) => {
        button.addEventListener('click', () => rotate(Number(button.dataset.cropRotate)));
    });
    dialog.querySelectorAll('[data-crop-zoom]').forEach((button) => {
        button.addEventListener('click', () => cropper && cropper.zoom(Number(button.dataset.cropZoom)));
    });
    dialog.querySelectorAll('[data-crop-cancel]').forEach((button) => button.addEventListener('click', () => finish(null)));
    confirmButton.addEventListener('click', confirm);
    dialog.addEventListener('cancel', (event) => { event.preventDefault(); finish(null); }); // Escape

    window.IdCardCropper = { open };
})();
