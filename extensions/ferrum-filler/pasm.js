// Ferrum /pasm cədvəli: hər müraciətin çap düyməsinin yanına "Parfumshop-a bağla" düyməsi.
// Basanda sifariş nömrəsi soruşulur və yoxlanır → extension sətrin çap düyməsini özü basır →
// pasm-hook.js PDF-i tutur (yükləmir) → background.js parfumshop-a göndərir, sifarişin contract_file sahəsinə yazılır.
// Ferrum DevExpress Blazor-dur: cədvəl tez-tez yenidən çəkilir, ona görə düymələr MutationObserver ilə yenidən qoyulur.
(() => {
    const TAG = 'ps-ferrum-contract';
    const PRINT = /çap|print/i;
    const APP_NO = /PFL-APP-\d+/i;
    const onPasm = () => location.pathname.toLowerCase().startsWith('/pasm');

    /* ---------- çap düyməsi və müraciət nömrəsi ---------- */
    function printButton(row) {
        return [...row.querySelectorAll('button, a, [role="button"]')].find((node) => !node.classList.contains('ps-contract-btn')
            && [node.textContent, node.getAttribute('title'), node.getAttribute('aria-label'), node.getAttribute('class'),
                ...[...node.querySelectorAll('[class], [title]')].map((c) => (c.getAttribute('class') || '') + ' ' + (c.getAttribute('title') || ''))]
                .some((text) => PRINT.test(text || '')));
    }
    // Xana-xana axtarılır: sətrin bütöv mətnində qonşu xana bitişik gəlir (PFL-APP-002227 + 6EPCQU9 → …22276EPCQU9)
    const appNo = (row) => ([...row.cells].map((cell) => cell.textContent.match(APP_NO)?.[0]).find(Boolean) || '').toUpperCase();
    // Blazor sətri yenidən çəkə bilər — müraciət nömrəsi ilə təzə sətir tapılır
    const findRow = (no) => [...document.querySelectorAll('tr')].find((tr) => appNo(tr) === no && printButton(tr));

    function scan() {
        if (!onPasm()) return;
        document.querySelectorAll('tr').forEach((row) => {
            if (row.querySelector('.ps-contract-btn')) return;
            const print = printButton(row);
            if (!print) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ps-contract-btn';
            button.textContent = 'Parfumshop-a bağla';
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openDialog(row);
            });
            print.after(button);
        });
    }
    let queued = false;
    new MutationObserver(() => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; scan(); });
    }).observe(document.documentElement, { childList: true, subtree: true });
    scan();

    /* ---------- PDF-in tutulması (pasm-hook.js ilə) ---------- */
    function capturePdf(row) {
        return new Promise((resolve, reject) => {
            const id = Math.random().toString(36).slice(2);
            const done = (fn, value) => { clearTimeout(timer); window.removeEventListener('message', listener); fn(value); };
            const listener = (event) => {
                const data = event.data;
                if (event.source !== window || !data || data.source !== TAG || data.dir !== 'out' || data.id !== id) return;
                if (data.type === 'captured') done(resolve, data);
                if (data.type === 'failed') done(reject, new Error(data.error || 'PDF tutulmadı.'));
            };
            const timer = setTimeout(() => {
                window.postMessage({ source: TAG, dir: 'in', type: 'disarm', id }, location.origin);
                done(reject, new Error('Ferrum PDF-i 45 saniyə ərzində vermədi. Yenidən cəhd edin.'));
            }, 45000);
            window.addEventListener('message', listener);
            window.postMessage({ source: TAG, dir: 'in', type: 'arm', id }, location.origin);
            const print = printButton(row);
            if (!print) return done(reject, new Error('Sətirdə çap düyməsi tapılmadı.'));
            print.click();
        });
    }

    /* ---------- pəncərə (Shadow DOM — Ferrum-un CSS-i təsir etmir) ---------- */
    let host = null;
    function el(tag, attrs = {}, ...children) {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(attrs)) {
            if (value === null || value === undefined || value === false) continue;
            if (key === 'class') node.className = value;
            else if (key === 'text') node.textContent = value;
            else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
            else node.setAttribute(key, value === true ? '' : value);
        }
        node.append(...children.filter(Boolean));
        return node;
    }
    const money = (n) => Number(n).toFixed(2) + ' ₼';

    function openDialog(row) {
        host?.remove();
        const no = appNo(row);
        host = el('div');
        const root = host.attachShadow({ mode: 'closed' });
        root.append(el('style', { text: STYLE }));
        const input = el('input', { type: 'text', placeholder: 'PS… və ya ID', autocomplete: 'off', spellcheck: 'false' });
        const info = el('div', { class: 'info' });
        const status = el('div', { class: 'status' });
        const check = el('button', { type: 'submit', class: 'primary', text: 'Yoxla' });
        const upload = el('button', { type: 'button', class: 'primary', text: 'Yüklə', hidden: true });
        const close = () => { host?.remove(); host = null; };
        let order = null;

        const setStatus = (text, kind = '') => { status.className = 'status ' + kind; status.textContent = text; };
        const reset = () => { order = null; info.replaceChildren(); upload.hidden = true; check.hidden = false; setStatus(''); };
        input.addEventListener('input', reset);

        const form = el('form', {
            onsubmit: async (event) => {
                event.preventDefault();
                const ref = input.value.trim();
                if (!ref) return input.focus();
                check.disabled = true;
                setStatus('Yoxlanır…');
                const result = await chrome.runtime.sendMessage({ type: 'contract-check', ref }).catch(() => null);
                check.disabled = false;
                if (!result?.ok) return setStatus(result?.error || 'Extension cavab vermədi — səhifəni yeniləyin.', 'error');
                order = result.data;
                info.replaceChildren(...[
                    el('div', { class: 'order', text: `${order.order_no} · ${order.customer || '—'} · ${money(order.total)}` }),
                    order.cancelled ? el('div', { class: 'warn', text: 'Sifariş ləğv olunub.' }) : null,
                    order.has_contract ? el('div', { class: 'warn', text: 'Sifarişdə artıq müqavilə var — yenisi ilə əvəz olunacaq.' }) : null,
                ].filter(Boolean));
                setStatus('');
                check.hidden = true;
                upload.hidden = false;
                upload.focus();
            },
        },
        el('div', { class: 'head' }, el('strong', { text: 'Parfumshop-a bağla' }), el('button', { type: 'button', class: 'x', text: '×', 'aria-label': 'Bağla', onclick: close })),
        el('div', { class: 'muted', text: no ? `Müraciət: ${no}` : 'Müraciət nömrəsi sətirdə tapılmadı' }),
        el('label', { text: 'Parfumshop sifariş nömrəsi' }), input, info, status,
        el('div', { class: 'actions' }, check, upload));

        upload.addEventListener('click', async () => {
            if (!order) return;
            upload.disabled = true;
            input.disabled = true;
            try {
                setStatus('Ferrum PDF-i hazırlayır…');
                const target = (row.isConnected ? row : null) || findRow(no);
                if (!target) throw new Error('Sətir cədvəldə tapılmadı — səhifəni yeniləyin.');
                const pdf = await capturePdf(target);
                setStatus('Parfumshop-a yüklənir…');
                const result = await chrome.runtime.sendMessage({ type: 'contract-upload', orderId: order.id, csrf: order.csrf, base64: pdf.base64, appNo: no || null });
                if (!result?.ok) throw new Error(result?.error || 'Yüklənmədi.');
                setStatus(`✓ ${result.data.message} Fayl: ${result.data.file}`, 'ok');
                upload.hidden = true;
                (findRow(no) || target).querySelector('.ps-contract-btn')?.classList.add('is-done');
            } catch (error) {
                setStatus(error.message, 'error');
                upload.disabled = false;
                input.disabled = false;
            }
        });
        root.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
        root.append(form);
        document.body.append(host);
        input.focus();
    }

    const STYLE = `
        form { position: fixed; top: 16px; right: 16px; z-index: 2147483647; width: 340px; box-sizing: border-box;
            background: #fff; color: #1d2433; border-radius: 12px; padding: 14px 16px; box-shadow: 0 12px 40px rgba(0,0,0,.3);
            font: 13px/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; border: 2px solid #1e88e5; }
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; font-size: 14px; }
        .x { border: 0; background: none; font-size: 20px; line-height: 1; cursor: pointer; color: #6b7385; }
        .muted { color: #6b7385; margin-bottom: 10px; }
        label { display: block; font-weight: 600; margin-bottom: 4px; }
        input { width: 100%; box-sizing: border-box; padding: 7px 9px; border: 1px solid #cfd5e1; border-radius: 8px; font: inherit; }
        input:focus { outline: 2px solid #1e88e5; border-color: transparent; }
        .info:not(:empty) { margin-top: 10px; padding: 8px 10px; background: #f4f6fa; border-radius: 8px; }
        .order { font-weight: 600; }
        .warn { color: #b26a00; margin-top: 4px; }
        .status:not(:empty) { margin-top: 10px; }
        .status.error { color: #c62828; }
        .status.ok { color: #2e7d32; font-weight: 600; }
        .actions { display: flex; justify-content: flex-end; margin-top: 12px; }
        .primary { border: 0; border-radius: 8px; padding: 7px 16px; background: #1e88e5; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .primary:disabled { opacity: .6; cursor: default; }
        [hidden] { display: none !important; }
    `;
})();
