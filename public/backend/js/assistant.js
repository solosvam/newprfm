// Operator yan paneli (extensions/ps-side): mesajları yalnız bizim extension-dan qəbul edir
(function () {
    'use strict';
    const body = document.body;
    const origin = body.dataset.extensionOrigin;
    const customerBox = document.querySelector('[data-customer]');
    const searchForm = document.querySelector('[data-search-form]');
    const searchInput = document.querySelector('[data-search-input]');
    const searchNote = document.querySelector('[data-search-note]');
    const searchResults = document.querySelector('[data-search-results]');
    let searchRequest = null;
    let searchTimer = null;
    let notePrefix = ''; // şəkildən axtarışda: "Şəkildə: Dior Sauvage — "
    const searchImage = document.querySelector('[data-search-image]');
    const poster = {
        box: document.querySelector('[data-poster]'),
        status: document.querySelector('[data-poster-status]'),
        img: document.querySelector('[data-poster-img]'),
        copy: document.querySelector('[data-poster-copy]'),
        download: document.querySelector('[data-poster-download]'),
        caption: document.querySelector('[data-poster-caption]'),
        copyText: document.querySelector('[data-poster-copy-text]'),
        current: null,
        url: null,
        busy: false,
    };
    let currentPhone = null;
    let request = null;

    // DOM-u mətnlə qururuq (innerHTML yox) — müştəri adı və s. HTML kimi işlənməsin
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function link(href, text, className) {
        const node = el('a', className, text);
        node.href = href;
        node.target = '_blank';
        node.rel = 'noopener';
        return node;
    }

    function show(...nodes) {
        customerBox.replaceChildren(...nodes);
    }

    function formatPhone(mobile) {
        // 994501234567 → +994 50 123 45 67
        const m = String(mobile).match(/^994(\d{2})(\d{3})(\d{2})(\d{2})$/);
        return m ? `+994 ${m[1]} ${m[2]} ${m[3]} ${m[4]}` : mobile;
    }

    function creditFact(ready) {
        const node = el('span', ready ? 'as-ok' : 'as-muted', ready ? 'Kredit profili: tam ✓' : 'Kredit profili: natamam');
        node.dataset.creditFact = '';
        return node;
    }

    // kredit profili bloku (assistant-credit.js) cari müştərini bu hadisədən bilir
    function announce(customer) {
        document.dispatchEvent(new CustomEvent('ps:customer', { detail: customer || null }));
    }

    function renderCustomer(data) {
        announce(data.valid ? data.customer : null);
        if (!data.valid) {
            show(el('p', 'as-muted', 'Azərbaycan mobil nömrəsi deyil'));
            return;
        }
        const phone = el('div', 'as-phone', formatPhone(data.mobile));
        if (!data.customer) {
            // "Yarat" basılanda forma açılır
            const row = el('div', 'as-row as-missing');
            const create = el('button', 'as-btn as-btn-sm', 'Yarat');
            create.type = 'button';
            row.append(el('span', 'as-warn', 'Müştəri CRM-də tapılmadı'), create);
            create.addEventListener('click', () => {
                create.remove();
                customerBox.append(newCustomerForm(data.mobile));
            });
            show(phone, row);
            return;
        }
        const c = data.customer;
        const head = el('div', 'as-row');
        head.append(el('strong', 'as-name', c.fullname), link(c.url, 'CRM ↗', 'as-link'));

        const facts = el('div', 'as-facts');
        facts.append(
            el('span', null, `Bonus ${c.bonus} ₼`),
            el('span', null, `${c.orders_count} sifariş`),
            creditFact(c.credit_ready),
        );

        const nodes = [head, phone, facts];
        if (data.orders.length) {
            const list = el('ul', 'as-orders');
            data.orders.forEach((order) => {
                const item = el('li');
                const a = link(order.url, `#${order.no}`, 'as-link');
                item.append(a, el('span', 'as-muted', ` ${order.date} · ${order.status || '—'} · ${order.total} ₼`));
                list.append(item);
            });
            nodes.push(list);
        }
        show(...nodes);
    }

    /* ---------- yeni müştəri: paneldə forma (CRM-ə keçmədən) ---------- */
    function newCustomerForm(mobile) {
        const form = document.querySelector('template[data-new-customer]').content.firstElementChild.cloneNode(true);
        const error = form.querySelector('[data-form-error]');
        const submit = form.querySelector('button[type="submit"]');
        form.elements.mobile.value = mobile;

        function clearErrors() {
            error.hidden = true;
            form.querySelectorAll('.as-field-error').forEach((node) => node.remove());
            form.querySelectorAll('.is-invalid').forEach((node) => node.classList.remove('is-invalid'));
        }

        function fieldError(name, message) {
            const field = form.querySelector(`[data-field="${name}"]`) || form.elements[name]?.closest('.as-field');
            if (!field) { error.textContent = message; error.hidden = false; return; }
            field.classList.add('is-invalid');
            field.append(el('small', 'as-field-error', message));
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();
            const values = {
                mobile,
                name: form.elements.name.value.trim(),
                surname: form.elements.surname.value.trim(),
                email: form.elements.email.value.trim() || null,
                gender: form.querySelector('input[name="gender"]:checked')?.value ?? null,
                send_password: form.elements.send_password.checked,
            };
            submit.disabled = true;
            try {
                const response = await fetch(body.dataset.customerStoreUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(values),
                });
                const data = await response.json().catch(() => ({}));
                if (response.status === 422) {
                    Object.entries(data.errors || {}).forEach(([name, messages]) => fieldError(name, messages[0]));
                    return;
                }
                if (response.status === 401 || response.status === 419) throw new Error('Sessiya bitib — admin panelə yenidən daxil olun');
                if (!response.ok) throw new Error(data.message || `Xəta (${response.status})`);

                // yaradıldı — kart göstərilir, mesaj bir neçə saniyə qalır
                currentPhone = null;
                loadCustomer(data.mobile, data.message);
            } catch (failure) {
                error.textContent = failure.message;
                error.hidden = false;
            } finally {
                submit.disabled = false;
            }
        });

        setTimeout(() => form.elements.name.focus(), 0);
        return form;
    }

    function loadCustomer(phone, notice) {
        if (phone === currentPhone) return;
        currentPhone = phone;
        if (request) request.abort();
        if (!phone) return;

        request = new AbortController();
        announce(null);
        show(el('div', 'as-phone', formatPhone(phone)), el('p', 'as-muted', 'Axtarılır…'));
        const url = `${body.dataset.customerUrl}?phone=${encodeURIComponent(phone)}`;
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: request.signal })
            .then((response) => {
                if (response.status === 401 || response.status === 419) throw new Error('Sessiya bitib — admin panelə yenidən daxil olun');
                if (!response.ok) throw new Error(`Xəta (${response.status})`);
                return response.json();
            })
            .then((data) => {
                renderCustomer(data);
                if (notice) customerBox.prepend(el('p', 'as-ok as-notice', notice));
            })
            .catch((error) => {
                if (error.name === 'AbortError') return;
                currentPhone = null; // növbəti dəfə yenidən cəhd etsin
                show(el('p', 'as-warn', error.message));
            });
    }

    // Nömrə ilə əl ilə axtarış: Messenger / Instagram (Business Suite) söhbətlərində nömrə görünmür,
    // WhatsApp-da isə saxlanmış kontaktda — operator müştəridən soruşub yazır
    function lookupForm(hint) {
        const form = el('form', 'as-lookup');
        const input = el('input', 'as-input');
        input.type = 'tel';
        input.placeholder = '050 123 45 67';
        input.maxLength = 20;
        const button = el('button', 'as-btn as-btn-sm', 'Tap');
        button.type = 'submit';
        const row = el('div', 'as-lookup-row');
        row.append(input, button);
        form.append(el('p', 'as-muted', hint), row);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const phone = input.value.replace(/\D+/g, '');
            if (phone.length < 9) { input.focus(); return; }
            currentPhone = null;
            loadCustomer(phone);
        });
        return form;
    }

    function onChat(chat) {
        if (!chat || !chat.phone) announce(null);
        if (!chat) {
            currentPhone = null;
            show(lookupForm('Söhbətdə nömrə yoxdur — müştərini nömrə ilə tapın:'));
        } else if (chat.phone) {
            loadCustomer(chat.phone);
        } else {
            currentPhone = null;
            show(
                el('p', null, chat.title),
                el('p', 'as-muted', 'Saxlanmış kontakt — nömrə görünmür. WhatsApp-da söhbətin başlığına klikləyin (Kişi bilgisi), nömrə yadda qalacaq.'),
                lookupForm('Və ya nömrəni yazın:'),
            );
        }
    }

    onChat(null); // başlanğıc: WhatsApp-dan söhbət gələnə qədər (Business Suite-də — həmişə) əl ilə axtarış

    /* ---------- ətir axtarışı ---------- */
    function note(text) {
        const full = text ? notePrefix + text : notePrefix.replace(/ — $/, '');
        searchNote.textContent = full;
        searchNote.hidden = !full;
    }

    function clearImage() {
        notePrefix = '';
        if (searchImage.dataset.objectUrl) URL.revokeObjectURL(searchImage.dataset.objectUrl);
        delete searchImage.dataset.objectUrl;
        searchImage.hidden = true;
        searchImage.removeAttribute('src');
    }

    function renderProduct(product) {
        const item = el('li', 'as-product');
        const media = el('div', 'as-thumb');
        if (product.image) {
            const img = el('img');
            img.src = product.image;
            img.alt = '';
            img.loading = 'lazy';
            media.append(img);
        }
        const info = el('div', 'as-info');
        const title = link(product.url, product.brand ? `${product.brand} ${product.name}` : product.name, 'as-title');
        info.append(title);
        // tip + nəticənin mənbəyi: qısaltma ("dol int" — brend, sonra model), adi (lüğət) axtarış və ya hər ikisi
        const meta = el('div', 'as-meta');
        if (product.type) meta.append(el('span', 'as-muted', product.type));
        const sources = { shortcut: 'qısaltma', smart: 'adi', both: 'hər ikisi' };
        if (sources[product.source]) meta.append(el('span', `as-source is-${product.source}`, sources[product.source]));
        info.append(meta);

        const chips = el('div', 'as-chips');
        product.variants.forEach((variant) => {
            const chip = el('span', variant.match ? 'as-chip is-match' : 'as-chip');
            chip.append(el('span', null, variant.size), el('strong', null, `${variant.price} ₼`));
            chips.append(chip);
        });
        if (!product.variants.length) chips.append(el('span', 'as-muted', 'Aktiv ölçü yoxdur'));
        info.append(chips);

        const posterButton = el('button', 'as-btn as-btn-sm', 'Poster');
        posterButton.type = 'button';
        posterButton.addEventListener('click', () => makePoster(product, posterButton));
        info.append(posterButton);

        item.append(media, info);
        return item;
    }

    function runSearch(text) {
        const q = String(text || '').trim().slice(0, 200);
        clearTimeout(searchTimer);
        if (searchRequest) searchRequest.abort();
        searchResults.replaceChildren();
        if (q.length < 2) { note(''); return; }

        searchRequest = new AbortController();
        note('Axtarılır…');
        fetch(`${body.dataset.searchUrl}?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: searchRequest.signal,
        })
            .then((response) => {
                if (response.status === 401 || response.status === 419) throw new Error('Sessiya bitib — admin panelə yenidən daxil olun');
                if (!response.ok) throw new Error(`Xəta (${response.status})`);
                return response.json();
            })
            .then((data) => {
                if (!data.results.length) {
                    // artıq sözlər atılmış halda göstərilir — qalan sözlərdən hansı tanınmadığı görünsün
                    const shown = data.interpreted || '';
                    note(shown ? `"${shown}" tapılmadı — səhv yazılış və ya artıq sözdürsə, "Axtarış idarəetməsi"ndə əlavə edin` : 'Mətndə ətir adı tapılmadı');
                    return;
                }
                const parts = [`"${data.interpreted || data.query}"`];
                if (data.size) parts.push(`${data.size} ml`);
                note(`${parts.join(' · ')} — ${data.results.length} nəticə`);
                searchResults.replaceChildren(...data.results.map(renderProduct));
            })
            .catch((error) => {
                if (error.name !== 'AbortError') note(error.message);
            });
    }

    /* ---------- poster (product-poster.js — məhsul siyahısındakı ilə eyni) ---------- */
    function posterStatus(text, kind) {
        poster.status.textContent = text;
        poster.status.className = `as-poster-status ${kind === 'ok' ? 'as-ok' : kind === 'bad' ? 'as-warn' : 'as-muted'}`;
    }

    function copyResult(ok) {
        posterStatus(ok ? 'Kopyalandı — WhatsApp-da söhbətə Ctrl+V / Cmd+V vurun, sonra "Mətni kopyala" ilə qiyməti şəklin altına yapışdırın.'
            : 'Avtomatik kopyalanmadı — "Kopyala" basın və ya PNG-ni endirin.', ok ? 'ok' : 'bad');
    }

    function makePoster(product, button) {
        if (poster.busy || !window.ProductPoster) return;
        poster.busy = true;
        button.disabled = true;
        poster.current = null;
        poster.copy.disabled = true;
        poster.img.hidden = true;
        poster.download.hidden = true;
        poster.caption.hidden = true;
        poster.copyText.hidden = true;
        poster.box.hidden = false;
        posterStatus(`${product.brand ? product.brand + ' ' : ''}${product.name} — poster hazırlanır…`);
        poster.box.scrollIntoView({ behavior: 'smooth', block: 'start' });

        const url = body.dataset.posterUrl.replace('__ID__', encodeURIComponent(product.id));
        const task = fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.message || `Xəta (${response.status})`);
                return window.ProductPoster.render(data);
            });
        const blobPromise = task.then((result) => result.blob);
        blobPromise.catch(() => {});
        // kopyalama klikin özündə başlayır — brauzer yalnız istifadəçi hərəkəti ilə icazə verir
        const copied = window.ProductPoster.copy(blobPromise);

        task.then(async (result) => {
            poster.current = result;
            if (poster.url) URL.revokeObjectURL(poster.url);
            poster.url = URL.createObjectURL(result.blob);
            poster.img.src = poster.url;
            poster.img.hidden = false;
            poster.download.href = poster.url;
            poster.download.download = result.filename;
            poster.download.hidden = false;
            poster.copy.disabled = false;
            poster.caption.textContent = result.caption;
            poster.caption.hidden = !result.caption;
            poster.copyText.hidden = !result.caption;
            copyResult(await copied);
        }).catch((error) => {
            posterStatus(error.message || 'Poster hazırlamaq mümkün olmadı.', 'bad');
        }).finally(() => {
            poster.busy = false;
            button.disabled = false;
        });
    }

    poster.copy.addEventListener('click', async () => {
        if (!poster.current) return;
        poster.copy.disabled = true;
        copyResult(await window.ProductPoster.copy(Promise.resolve(poster.current.blob)));
        poster.copy.disabled = false;
    });
    poster.copyText.addEventListener('click', async () => {
        if (!poster.current) return;
        const ok = await window.ProductPoster.copyText(poster.current.caption);
        posterStatus(ok ? 'Mətn kopyalandı — şəklin altındakı yazıya və ya söhbətə yapışdırın.' : 'Mətn kopyalanmadı — aşağıdakı mətni seçib kopyalayın.', ok ? 'ok' : 'bad');
    });
    document.querySelector('[data-poster-close]').addEventListener('click', () => { poster.box.hidden = true; });


    // WhatsApp-dan gələn mətn — dərhal axtar
    function onSearch(text) {
        clearImage();
        searchInput.value = text;
        runSearch(text);
    }

    // WhatsApp-da şəklə sağ klik → "Ətri şəkildən axtar": Google Vision (oxşar şəkillər + yazı) → sorğu → adi axtarış
    async function onImageSearch(dataUrl) {
        clearImage();
        clearTimeout(searchTimer);
        if (searchRequest) searchRequest.abort();
        searchResults.replaceChildren();
        searchInput.value = '';
        note('Şəkil tanınır…');
        try {
            const blob = await (await fetch(dataUrl)).blob();
            searchImage.dataset.objectUrl = URL.createObjectURL(blob);
            searchImage.src = searchImage.dataset.objectUrl;
            searchImage.hidden = false;

            const data = new FormData();
            data.append('image', new File([blob], 'perfume.jpg', { type: blob.type || 'image/jpeg' }));
            const response = await fetch(body.dataset.searchImageUrl, {
                method: 'POST', credentials: 'same-origin', body: data,
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            const result = await response.json().catch(() => ({}));
            if (response.status === 401 || response.status === 419) throw new Error('Sessiya bitib — admin panelə yenidən daxil olun');
            if (!response.ok) throw new Error([result.message || `Xəta (${response.status})`, result.debug].filter(Boolean).join(' — '));

            const seen = result.detected?.label || (result.detected?.entities || []).slice(0, 3).join(', ');
            if (!result.query) {
                note(`Şəkildə ətir tanınmadı${seen ? ` (Google: ${seen})` : ''} — adı əl ilə yazın.`);
                searchInput.focus();
                return;
            }
            notePrefix = seen ? `Şəkildə: ${seen} — ` : '';
            searchInput.value = result.size ? `${result.query} ${result.size}` : result.query;
            runSearch(searchInput.value);
        } catch (error) {
            note(error.message);
        }
    }

    // əl ilə yazanda — yazmağı bitirəndə axtar
    searchInput.addEventListener('input', () => {
        clearImage();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => runSearch(searchInput.value), 400);
    });
    searchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        runSearch(searchInput.value);
    });

    window.addEventListener('message', (event) => {
        if (event.origin !== origin || event.source !== window.parent) return;
        const data = event.data || {};
        if (data.type === 'ps-chat') onChat(data.chat);
        if (data.type === 'ps-search') onSearch(String(data.text || '').trim().slice(0, 200));
        if (data.type === 'ps-image-search' && data.dataUrl) onImageSearch(data.dataUrl);
        if (data.type === 'ps-search-error') { clearImage(); note(String(data.message || 'Xəta')); }
    });
})();
