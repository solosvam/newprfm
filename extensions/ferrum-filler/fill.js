// Ferrum səhifəsində icra olunur (chrome.scripting.executeScript) — funksiya özündə tamdır,
// xarici dəyişənlərə toxunmur, bütün məlumat plan ilə gəlir.
// Ferrum DevExpress Blazor-dur: input id/name hər render-də dəyişir, ona görə hər şey label mətninə görə tapılır.
// Səhifənin küncündə proqres paneli göstərilir (popup bağlansa da görünür) və popup-a mesaj göndərilir.
// Müraciəti GÖNDƏRMİR və YADDA SAXLAMIR — yalnız modalların öz "Yadda Saxla" düyməsi (sətir əlavə edir) basılır.
export async function fillPage(plan) {
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    const norm = (s) => String(s ?? '').replace(/\s+/g, ' ').trim().toLocaleLowerCase('az');
    const visible = (node) => !!node && node.getClientRects().length > 0 && getComputedStyle(node).visibility !== 'hidden';
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
    const areaSetter = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set;
    const log = [];
    const problems = [];
    const want = (step) => !plan.steps || plan.steps.includes(step); // test üçün: yalnız bu addımlar

    /* ---------------- proqres paneli ---------------- */
    function send(message) {
        try { chrome.runtime?.sendMessage({ type: 'ferrum-progress', ...message }).catch(() => {}); } catch { /* popup bağlıdır */ }
    }
    const panel = (() => {
        document.getElementById('psf-panel')?.remove();
        const box = document.createElement('div');
        box.id = 'psf-panel';
        box.style.cssText = 'position:fixed;left:16px;top:16px;z-index:2147483647;width:340px;max-height:60vh;overflow:auto;'
            + 'background:#fff;color:#1d2433;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.3);'
            + 'font:13px/1.45 system-ui,-apple-system,Segoe UI,sans-serif;border:3px solid #ff0000';
        const head = document.createElement('div');
        head.style.cssText = 'display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid #eef0f5;font-weight:600;position:sticky;top:0;background:#fff';
        const title = document.createElement('span');
        title.textContent = 'Parfumshop → Ferrum: doldurulur…';
        title.style.flex = '1';
        const close = document.createElement('button');
        close.type = 'button';
        close.textContent = '✕';
        close.title = 'Bağla';
        close.style.cssText = 'border:0;background:none;font-size:14px;cursor:pointer;color:#6b7280';
        close.addEventListener('click', () => box.remove());
        head.append(title, close);
        const list = document.createElement('div');
        list.style.cssText = 'padding:8px 12px 12px';
        box.append(head, list);
        document.body.append(box);
        let current = null;
        const line = (icon, text, color) => {
            const row = document.createElement('div');
            row.style.cssText = `display:flex;gap:6px;padding:2px 0;color:${color}`;
            const i = document.createElement('span');
            i.textContent = icon;
            i.style.flex = '0 0 16px';
            const t = document.createElement('span');
            t.textContent = text;
            row.append(i, t);
            list.append(row);
            row.scrollIntoView({ block: 'nearest' });
            return row;
        };
        return {
            step(text) {
                if (current) current.remove();
                current = line('⏳', text, '#374151');
                send({ kind: 'step', text });
            },
            ok(text) { line('✓', text, '#2f7d4f'); send({ kind: 'ok', text }); },
            bad(text) { line('⚠', text, '#b54708'); send({ kind: 'bad', text }); },
            done(text) {
                if (current) current.remove();
                current = null;
                title.textContent = text;
                send({ kind: 'done', text });
            },
        };
    })();
    const ok = (text) => { log.push(text); panel.ok(text); };
    const bad = (text) => { problems.push(text); panel.bad(text); };

    /* ---------------- köməkçilər ---------------- */
    async function waitFor(fn, timeout = 5000, step = 100) {
        const end = Date.now() + timeout;
        for (;;) {
            const value = fn();
            if (value) return value;
            if (Date.now() > end) return null;
            await sleep(step);
        }
    }

    function topPopup() {
        const popups = [...document.querySelectorAll('.dxbs-popup, .modal-dialog')].filter(visible);
        return popups[popups.length - 1] || null;
    }

    // Label mətni → input (label[for] və ya eyni blokdakı input)
    function inputByLabel(text, scope = document) {
        const wanted = norm(text);
        const label = [...scope.querySelectorAll('label')].find((l) => norm(l.textContent) === wanted && visible(l));
        if (!label) return null;
        const id = label.getAttribute('for');
        if (id) {
            const byId = document.getElementById(id);
            if (byId) return byId;
        }
        let parent = label.parentElement;
        for (let depth = 0; depth < 4 && parent; depth++, parent = parent.parentElement) {
            const node = parent.querySelector('input:not([type=hidden]):not([type=file]), textarea');
            if (node) return node;
        }
        return null;
    }

    function mark(node, good = true) {
        const box = node.closest('.dxbs-dropdown-edit') || node;
        box.style.outline = `2px solid ${good ? '#2f9e5a' : '#d64545'}`;
        box.style.outlineOffset = '1px';
        setTimeout(() => { box.style.outline = ''; box.style.outlineOffset = ''; }, 8000);
    }

    function pointerClick(node) {
        const opts = { bubbles: true, cancelable: true, view: window, button: 0 };
        node.dispatchEvent(new PointerEvent('pointerdown', opts));
        node.dispatchEvent(new MouseEvent('mousedown', opts));
        node.dispatchEvent(new PointerEvent('pointerup', opts));
        node.dispatchEvent(new MouseEvent('mouseup', opts));
        node.dispatchEvent(new MouseEvent('click', opts));
    }

    function mouseClick(node) {
        for (const type of ['mousedown', 'mouseup', 'click']) {
            node.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true, view: window, button: 0 }));
        }
    }

    function setValue(node, value) {
        (node instanceof HTMLTextAreaElement ? areaSetter : setter).call(node, value);
        node.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // İstifadəçi kimi hərf-hərf yazır (DevExpress combo filtri yalnız klaviatura hadisələri ilə işə düşür)
    async function typeText(node, text) {
        setValue(node, '');
        let current = '';
        for (const ch of String(text)) {
            current += ch;
            node.dispatchEvent(new KeyboardEvent('keydown', { key: ch, bubbles: true }));
            setter.call(node, current);
            node.dispatchEvent(new InputEvent('input', { bubbles: true, data: ch, inputType: 'insertText' }));
            node.dispatchEvent(new KeyboardEvent('keyup', { key: ch, bubbles: true }));
            await sleep(25);
        }
    }

    async function setText(label, value, scope = document) {
        if (value === null || value === undefined || value === '') return false;
        const node = inputByLabel(label, scope);
        if (!node) { bad(`${label}: xana tapılmadı`); return false; }
        if (node.readOnly || node.disabled) return false;
        node.focus();
        setValue(node, String(value));
        node.dispatchEvent(new Event('change', { bubbles: true }));
        node.blur();
        mark(node);
        await sleep(150);
        return true;
    }

    const openLists = () => [...document.querySelectorAll('.dxbs-listbox')].filter(visible);
    const findItem = (text, match, index) => {
        const wanted = norm(text);
        const found = openLists().flatMap((l) => [...l.querySelectorAll('li')])
            .filter((li) => (match === 'contains' ? norm(li.textContent).includes(wanted) : norm(li.textContent) === wanted));
        return found.length ? found[Math.min(index, found.length - 1)] : null;
    };

    // DevExpress combo. Siyahı virtual scroll-dur (yalnız ~10 variant render olunur), ona görə:
    // dropdown düyməsi (✕ yox) ilə açırıq; variant görünmürsə mətni hərf-hərf yazıb süzürük.
    // index: eyni adlı variantlardan neçəncisi (məs. iki "Gözəllik və sağlamlıq")
    async function setCombo(label, text, scope = document, { match = 'exact', index = 0 } = {}) {
        if (!text) return false;
        const node = inputByLabel(label, scope);
        if (!node) { bad(`${label}: xana tapılmadı`); return false; }
        const wrap = node.closest('.dxbs-dropdown-edit');
        if (node.disabled || !wrap) return false;
        const toggle = wrap.querySelector('button.dxbs-edit-btn:not(.dxbs-clear-btn)');

        if (toggle && !openLists().length) pointerClick(toggle);
        await waitFor(() => openLists().length, 2000);
        let item = await waitFor(() => findItem(text, match, index), 800);
        if (!item && !node.readOnly) {
            node.focus();
            await typeText(node, text);
            item = await waitFor(() => findItem(text, match, index), 3000);
        }
        if (!item) {
            if (!node.readOnly) setValue(node, ''); // yazdığımız süzgəc mətni sahədə qalmasın
            if (openLists().length && toggle) pointerClick(toggle);
            bad(`${label}: "${text}" tapılmadı — özünüz seçin`);
            mark(node, false);
            return false;
        }
        mouseClick(item.querySelector('a') || item);
        await waitFor(() => !openLists().length, 2000);
        mark(node);
        await sleep(250);
        return true;
    }

    function buttonByText(text, scope = document) {
        const wanted = norm(text);
        return [...scope.querySelectorAll('button')].find((b) => norm(b.textContent) === wanted && visible(b));
    }

    // Bölmə başlığının yanındakı "+" düyməsi
    function plusButton(sectionTitle) {
        const wanted = norm(sectionTitle);
        const title = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6,span,div')]
            .find((e) => e.children.length === 0 && norm(e.textContent) === wanted && visible(e) && e.getBoundingClientRect().x > 250);
        if (!title) return null;
        let parent = title.parentElement;
        for (let depth = 0; depth < 3 && parent; depth++, parent = parent.parentElement) {
            const button = [...parent.querySelectorAll('button')].find((b) => visible(b) && !norm(b.textContent).length);
            if (button) return button;
        }
        return null;
    }

    async function openModal(sectionTitle, modalTitle = null) {
        const plus = plusButton(sectionTitle);
        if (!plus) { bad(`${sectionTitle}: "+" düyməsi tapılmadı`); return null; }
        plus.scrollIntoView({ block: 'center' });
        plus.click();
        const popup = await waitFor(() => {
            const p = topPopup();
            if (!p) return null;
            return !modalTitle || norm(p.querySelector('.modal-title')?.textContent) === norm(modalTitle) ? p : null;
        }, 4000);
        if (!popup) bad(`${modalTitle || sectionTitle} modalı açılmadı`);
        await sleep(250);
        return popup;
    }

    async function saveModal(popup, what) {
        const save = buttonByText('Yadda Saxla', popup);
        if (!save) { bad(`${what}: modalın "Yadda Saxla" düyməsi tapılmadı`); return false; }
        save.click();
        const closed = await waitFor(() => !visible(popup) || !document.contains(popup), 4000);
        if (!closed) {
            const errors = [...popup.querySelectorAll('.validation-message, .invalid-feedback, .text-danger')].map((e) => e.textContent.trim()).filter(Boolean);
            bad(`${what}: modal bağlanmadı${errors.length ? ' — ' + errors.join('; ') : ''}. Modalı yoxlayıb özünüz saxlayın.`);
            return false;
        }
        await sleep(300);
        return true;
    }

    /* ================= addımlar ================= */
    const c = plan.customer;
    if (!inputByLabel('PinKod')) {
        panel.bad('Müraciət forması tapılmadı. Ferrum-da "Əlavə et" ilə yeni müraciət açın.');
        panel.done('Doldurma alınmadı');
        return { ok: false, log, problems: ['Müraciət forması tapılmadı. "Əlavə et" ilə yeni müraciət açın.'] };
    }

    // 1. Satış məlumatları
    if (want('source')) {
        panel.step('Sifarişin mənbəyi');
        if (await setCombo('Sifarişin mənbəyi', plan.source)) ok(`Sifarişin mənbəyi: ${plan.source}`);
    }

    // 2. PinKod → Ferrum axtarışı (tapılsa Ad xanası ***** + readonly olur)
    let known = false;
    if (want('pin') && c.fin) {
        panel.step('PinKod yazılır, Ferrum axtarır…');
        // Axtarış zamanı Ferrum ekrana loader çıxarır (DOM-a dinamik əlavə olunur).
        // Blur-dan sonra əlavə olunan böyük/fixed elementləri izləyirik: görünür → axtarış gedir, itir → bitdi.
        const loaders = new Set();
        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                for (const added of m.addedNodes) {
                    if (!(added instanceof HTMLElement) || added.closest('#psf-panel')) continue;
                    const style = getComputedStyle(added);
                    const rect = added.getBoundingClientRect();
                    if (style.position === 'fixed' || (rect.width > 150 && rect.height > 150 && !added.closest('form, .modal-dialog'))) loaders.add(added);
                }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
        const pinFilled = await setText('PinKod', c.fin);
        const known_ = () => { const ad = inputByLabel('Ad'); return !!(ad && (ad.readOnly || ad.value)); };
        const loaderVisible = () => [...loaders].some((n) => document.contains(n) && visible(n));
        if (pinFilled) {
            // loader-in çıxmasını qısa gözləyirik (və ya nəticə dərhal gəlir)
            await waitFor(() => loaderVisible() || known_(), 1500, 50);
            if (loaderVisible()) {
                // loader itənə qədər
                await waitFor(() => !loaderVisible(), plan.pinTimeout || 15000, 100);
            } else if (!known_()) {
                // loader tutulmadısa — nəticəni bir az da gözləyirik
                await waitFor(known_, 3000, 100);
            }
            await sleep(300); // render
        }
        observer.disconnect();
        known = known_();
        ok(known ? 'Müştəri Ferrum-da var — şəxsi məlumatlar keçildi' : 'Müştəri Ferrum-da yoxdur — şəxsi məlumatlar yazılır');
        await sleep(400);
    } else if (want('pin')) {
        bad('FİN yoxdur — PinKod boş qaldı');
    }

    // 3. Müştəri (yalnız Ferrum-da yoxdursa)
    if (want('customer') && !known) {
        panel.step('Müştəri məlumatları');
        await setText('Ad', c.name);
        await setText('Soyad', c.surname);
        await setText('Ata adı', c.father_name);
        if (c.gender) await setCombo('Cinsi', c.gender, document, { match: 'contains' });
        bad('Ş.V. Seriya və Ş.V. Nömrə bizdə yoxdur — vəsiqədən baxıb özünüz yazın');
    }

    // 4. Faktoring
    if (want('factoring')) {
        panel.step('Faktoring məhsulu');
        if (!plan.product) {
            bad(plan.productProblem || 'Məhsul seçilmədi — özünüz seçin');
        } else if (await setCombo('Məhsul', plan.product)) {
            await sleep(500);
            await setText('İlkin ödəniş', '0');
            const months = inputByLabel('Müddət')?.value;
            ok(`Məhsul: ${plan.product}${months ? ` (müddət ${months})` : ''}`);
        }
    }

    // 5. Mallar
    for (const [i, item] of (want('items') ? plan.items : []).entries()) {
        panel.step(`Mal ${i + 1}/${plan.items.length}: ${item.name}`);
        const popup = await openModal('Mallar', 'Mal');
        if (!popup) break;
        await setCombo('Kateqoriya', plan.category, popup);
        await sleep(400);
        await setCombo('Alt kateqoriya', plan.subcategory, popup);
        await setText('Mal', item.name, popup);
        await setText('Qiymət', item.price, popup);
        await setText('Say', item.quantity, popup);
        if (await saveModal(popup, `Mal "${item.name}"`)) ok(`Mal: ${item.name} — ${item.quantity} × ${item.price}`);
    }

    // 6. Telefonlar
    for (const [i, phone] of (want('phones') ? plan.phones : []).entries()) {
        panel.step(`Telefon ${i + 1}/${plan.phones.length}: ${phone.number}`);
        const popup = await openModal('Telefonlar', 'Telefon');
        if (!popup) break;
        await setText('Nömrə', phone.number, popup);
        await setCombo('Tip', phone.type, popup);
        await setText('Sahibi', phone.owner, popup);
        await setCombo('Əlaqəlilik', phone.relation, popup);
        if (await saveModal(popup, `Telefon ${phone.number}`)) ok(`Telefon: ${phone.number} (${phone.relation})`);
    }

    // 7. İş yeri
    if (want('work') && plan.work?.name) {
        panel.step('İş yeri');
        const popup = await openModal('İş yerləri');
        if (popup) {
            await setText('Adı', plan.work.name, popup) || await setText('Ad', plan.work.name, popup);
            await setText('Əmək haqqı', plan.work.salary, popup);
            if (await saveModal(popup, 'İş yeri')) ok(`İş yeri: ${plan.work.name}`);
        }
    }

    // 8. Sənəd: SV (vəsiqənin ön üzü, PNG)
    if (want('document')) {
        panel.step('Vəsiqə yüklənir');
        if (!plan.document?.dataUrl) {
            bad('Vəsiqənin ön üzü yoxdur — sənəd əlavə olunmadı');
        } else {
            const before = document.querySelectorAll('input[type=file]').length;
            const plus = plusButton('Sənədlər');
            if (!plus) {
                bad('Sənədlər: "+" düyməsi tapılmadı');
            } else {
                plus.scrollIntoView({ block: 'center' });
                plus.click();
                const fileInput = await waitFor(() => {
                    const inputs = document.querySelectorAll('input[type=file]');
                    return inputs.length > before ? inputs[inputs.length - 1] : null;
                }, 4000);
                const row = fileInput?.closest('tr');
                if (!row) {
                    bad('Sənəd sətri yaranmadı');
                } else {
                    const combo = row.querySelector('.dxbs-dropdown-edit input');
                    const toggle = combo?.closest('.dxbs-dropdown-edit')?.querySelector('button.dxbs-edit-btn:not(.dxbs-clear-btn)');
                    if (toggle) {
                        pointerClick(toggle);
                        const item = await waitFor(() => findItem(plan.document.kind, 'exact', 0), 3000);
                        if (item) { mouseClick(item.querySelector('a') || item); await sleep(400); mark(combo); }
                        else { pointerClick(toggle); bad(`Sənəd növü "${plan.document.kind}" tapılmadı — özünüz seçin`); }
                    }
                    const blob = await (await fetch(plan.document.dataUrl)).blob();
                    const transfer = new DataTransfer();
                    transfer.items.add(new File([blob], plan.document.name, { type: blob.type }));
                    fileInput.files = transfer.files;
                    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                    fileInput.dispatchEvent(new Event('input', { bubbles: true }));
                    // Blazor sətri yenidən render edir (köhnə row DOM-dan çıxır) — fayl adını bütün səhifədə axtarırıq
                    const name = await waitFor(() => [...document.querySelectorAll('input:not([type=file])')]
                        .map((i) => i.value).find((v) => v === plan.document.name), 10000, 200);
                    if (name) ok(`Sənəd (${plan.document.kind}): ${name}`);
                    else bad('Vəsiqə faylı qoyuldu, amma "Fayl adı" dolmadı — "Fayl seç" ilə özünüz seçin');
                }
            }
        }
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
    panel.done(problems.length ? `Bitdi — ${problems.length} xəbərdarlıq. Yoxlayın, sonra özünüz saxlayın.` : 'Bitdi. Yoxlayın, sonra özünüz saxlayın.');
    return { ok: true, known, log, problems };
}
