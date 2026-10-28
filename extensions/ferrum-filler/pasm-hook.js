// Ferrum səhifəsinin öz dünyasında (world: MAIN) işləyir — Ferrum-un PDF yükləməsini tutmaq üçün.
// Ferrum Blazor Server-dir: "Çap et" basılanda PDF serverdə yaranır, SignalR ilə gəlir və
// window.downloadFileClientSide(file, fileName, format) onu yükləyir — ayrıca URL yoxdur.
// pasm.js "arm" edəndə həmin funksiya bir dəfəlik əvəz olunur: PDF yüklənmir, pasm.js-ə ötürülür.
// Qalan vaxt Ferrum-un öz funksiyası dəyişmədən işləyir (adi çap əvvəlki kimi).
(() => {
    const TAG = 'ps-ferrum-contract';
    const FN = 'downloadFileClientSide';
    let armed = null; // { id, original, timer }

    const send = (data) => window.postMessage({ source: TAG, dir: 'out', ...data }, location.origin);

    async function toBase64(file) {
        if (typeof file === 'string') return file.replace(/^data:[^,]*,/, '');
        let bytes;
        if (file && typeof file.arrayBuffer === 'function') bytes = new Uint8Array(await file.arrayBuffer()); // Blob, DotNetStreamReference
        else if (file instanceof ArrayBuffer) bytes = new Uint8Array(file);
        else if (ArrayBuffer.isView(file)) bytes = new Uint8Array(file.buffer, file.byteOffset, file.byteLength);
        else if (Array.isArray(file)) bytes = Uint8Array.from(file);
        else throw new Error('PDF tanınmayan formatda gəldi.');
        let binary = '';
        for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        return btoa(binary);
    }

    function disarm() {
        if (!armed) return null;
        const { id, original, timer } = armed;
        clearTimeout(timer);
        if (original) window[FN] = original;
        armed = null;
        return id;
    }

    async function capture(file, fileName) {
        const id = disarm();
        if (id === null) return;
        try {
            send({ type: 'captured', id, base64: await toBase64(file), fileName: String(fileName || '') });
        } catch (error) {
            send({ type: 'failed', id, error: error.message });
        }
    }

    function arm(id) {
        disarm();
        // Funksiya Ferrum-un JS-i yüklənəndən sonra mövcud olur — ona görə yalnız lazım olan anda əvəz olunur
        const original = typeof window[FN] === 'function' ? window[FN] : null;
        armed = { id, original, timer: setTimeout(disarm, 60000) };
        if (original) {
            window[FN] = function (file, fileName) {
                if (armed) return void capture(file, fileName);
                return original.apply(this, arguments);
            };
        }
    }

    // Ehtiyat yol: Ferrum funksiyanın adını dəyişsə — blob/data linkinə <a download>.click() tutulur
    const anchorClick = HTMLAnchorElement.prototype.click;
    HTMLAnchorElement.prototype.click = function () {
        if (armed && this.hasAttribute('download') && /^(blob|data):/.test(this.href)) {
            const name = this.getAttribute('download');
            fetch(this.href).then((r) => r.blob()).then((blob) => capture(blob, name))
                .catch((error) => { const id = disarm(); if (id !== null) send({ type: 'failed', id, error: error.message }); });
            return;
        }
        return anchorClick.call(this);
    };

    window.addEventListener('message', (event) => {
        const data = event.data;
        if (event.source !== window || !data || data.source !== TAG || data.dir !== 'in') return;
        if (data.type === 'arm') arm(data.id);
        if (data.type === 'disarm' && armed?.id === data.id) disarm();
    });
})();
