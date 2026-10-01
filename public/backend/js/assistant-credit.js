// Operator paneli — kredit profili.
//  - WhatsApp-da seçilmiş mətn: sağ klik → PS → "Kredit → Qohum 1 adı" və s. → uyğun xanaya düşür (copy-paste yox);
//  - vəsiqə şəkli: sağ klik → "PS: Vəsiqə" → Ön üz / Arxa üz → kəsmə (IdCardCropper) → OCR (FİN, ata adı, seriya/nömrə);
//  - heç nə avtomatik saxlanmır — operator yoxlayıb "Yadda saxla" basır.
(function () {
    'use strict';
    const body = document.body;
    const box = document.querySelector('[data-credit]');
    if (!box) return;

    const origin = body.dataset.extensionOrigin;
    const form = box.querySelector('[data-credit-form]');
    const toggle = box.querySelector('[data-credit-toggle]');
    const state = box.querySelector('[data-credit-state]');
    const status = box.querySelector('[data-credit-status]');
    const formError = form.querySelector('[data-form-error]');
    const submit = form.querySelector('button[type="submit"]');
    const doubleSide = JSON.parse(box.dataset.doubleSide || '[]');
    const series = form.elements.id_card_series;
    const seriesOptions = [...series.options].map((option) => option.value).filter(Boolean)
        .sort((a, b) => b.length - a.length); // "AAA" "AA"-dan əvvəl yoxlansın

    let customer = null;
    const images = { id_card_front: null, id_card_back: null };
    const autofilled = new Set(); // OCR-ın doldurduğu xanalar — növbəti OCR onları yeniləyə bilər

    /* ---------- köməkçilər ---------- */
    function url(template) {
        return template.replace('__ID__', encodeURIComponent(customer.id));
    }

    function setStatus(text, kind) {
        status.hidden = !text;
        status.textContent = text || '';
        status.className = `as-credit-status ${kind === 'ok' ? 'as-ok' : kind === 'bad' ? 'as-warn' : 'as-muted'}`;
    }

    function open(expanded = true) {
        form.hidden = !expanded;
        toggle.setAttribute('aria-expanded', String(expanded));
    }

    function flash(input) {
        const field = input.closest('.as-field');
        field.classList.remove('is-invalid');
        field.querySelector('.as-field-error')?.remove();
        field.classList.remove('is-flash');
        void field.offsetWidth; // animasiyanı yenidən başlat
        field.classList.add('is-flash');
    }

    function toggleBackSide() {
        // arxa üz: AZE seriyasında və ya şəkli artıq varsa (WhatsApp-dan "Arxa üz" göndərilib)
        const back = form.querySelector('[data-idcard-preview="id_card_back"]');
        form.querySelector('[data-back-side]').hidden = !doubleSide.includes(series.value) && back.hidden;
    }
    series.addEventListener('change', toggleBackSide);

    function setPreview(side, src) {
        const img = form.querySelector(`[data-idcard-preview="${side}"]`);
        if (img.dataset.objectUrl) URL.revokeObjectURL(img.dataset.objectUrl);
        delete img.dataset.objectUrl;
        img.hidden = !src;
        if (src) img.src = src; else img.removeAttribute('src');
    }

    function warnNoCustomer() {
        const card = document.querySelector('[data-customer]');
        card.querySelector('.as-credit-warn')?.remove();
        const note = document.createElement('p');
        note.className = 'as-warn as-credit-warn';
        note.textContent = 'Əvvəlcə müştəri tanınmalıdır (söhbəti açın, lazım olsa "Yarat").';
        card.prepend(note);
        setTimeout(() => note.remove(), 6000);
    }

    /* ---------- müştəri dəyişdi (assistant.js) ---------- */
    function fill(credit) {
        ['father_name', 'fin', 'id_card_series', 'id_card_number', 'relative_1_name', 'relative_1_phone',
            'relative_2_name', 'relative_2_phone', 'workplace_name', 'salary'].forEach((name) => {
            form.elements[name].value = credit?.[name] ?? '';
        });
        images.id_card_front = null;
        images.id_card_back = null;
        autofilled.clear();
        setPreview('id_card_front', credit?.id_card_front || null);
        setPreview('id_card_back', credit?.id_card_back || null);
        form.querySelectorAll('.is-invalid, .is-flash').forEach((node) => node.classList.remove('is-invalid', 'is-flash'));
        form.querySelectorAll('.as-field-error').forEach((node) => node.remove());
        formError.hidden = true;
        toggleBackSide();
    }

    function setState(ready) {
        state.textContent = ready ? 'tam ✓' : 'natamam';
        state.className = ready ? 'as-ok' : 'as-muted';
        const fact = document.querySelector('[data-credit-fact]');
        if (fact) {
            fact.textContent = ready ? 'Kredit profili: tam ✓' : 'Kredit profili: natamam';
            fact.className = ready ? 'as-ok' : 'as-muted';
        }
    }

    document.addEventListener('ps:customer', (event) => {
        const next = event.detail;
        if (!next) {
            customer = null;
            box.hidden = true;
            return;
        }
        const changed = !customer || customer.id !== next.id;
        customer = next;
        box.hidden = false;
        setState(next.credit_ready);
        if (changed) {
            fill(next.credit);
            setStatus('');
            open(false);
        }
    });

    toggle.addEventListener('click', () => open(form.hidden));

    /* ---------- seçilmiş mətn → xana ---------- */
    function phone(text) {
        const digits = text.replace(/\D+/g, '');
        const m = digits.match(/^(?:994|0)?([1-9]\d{8})$/);
        return m ? `994${m[1]}` : digits.slice(0, 16);
    }

    function salary(text) {
        const m = text.replace(/\s+/g, '').replace(',', '.').match(/\d+(?:\.\d{1,2})?/);
        return m ? m[0] : '';
    }

    function clean(text, max) {
        return text.replace(/\s+/g, ' ').trim().slice(0, max);
    }

    // "AA 5267450", "AZE 12345678", "aa5267450"
    function idCard(text) {
        const compact = text.toLocaleUpperCase('az').replace(/[^A-ZƏİÖÜĞŞÇ0-9]+/g, '');
        const found = seriesOptions.find((value) => compact.startsWith(value.toLocaleUpperCase('az')))
            || seriesOptions.find((value) => compact.startsWith(value.replace('İ', 'I')));
        const number = (found ? compact.slice(found.replace('İ', 'I').length) : compact).replace(/[^A-Z0-9]/g, '').slice(0, 8);
        return { series: found || '', number };
    }

    const SETTERS = {
        father_name: (text) => ({ father_name: clean(text, 100) }),
        fin: (text) => ({ fin: text.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7) }),
        id_card: (text) => {
            const card = idCard(text);
            return card.series ? { id_card_series: card.series, id_card_number: card.number } : { id_card_number: card.number };
        },
        relative_1_name: (text) => ({ relative_1_name: clean(text, 100) }),
        relative_1_phone: (text) => ({ relative_1_phone: phone(text) }),
        relative_2_name: (text) => ({ relative_2_name: clean(text, 100) }),
        relative_2_phone: (text) => ({ relative_2_phone: phone(text) }),
        workplace_name: (text) => ({ workplace_name: clean(text, 255) }),
        salary: (text) => ({ salary: salary(text) }),
    };

    function onField(field, text) {
        if (!customer) { warnNoCustomer(); return; }
        const setter = SETTERS[field];
        if (!setter) return;
        open(true);
        Object.entries(setter(String(text || ''))).forEach(([name, value]) => {
            const input = form.elements[name];
            input.value = value;
            autofilled.delete(name); // operatorun seçdiyi — OCR üstündən yazmasın
            if (name === 'id_card_series') toggleBackSide();
            flash(input);
        });
        form.elements[Object.keys(setter(String(text || '')))[0]].closest('.as-field').scrollIntoView({ behavior: 'smooth', block: 'center' });
        setStatus('Xana dolduruldu — yoxlayıb "Yadda saxla" basın.', 'info');
    }

    /* ---------- vəsiqə şəkli → kəsmə → OCR ---------- */
    async function onImage(side, blob) {
        if (!customer) { warnNoCustomer(); return; }
        if (!window.IdCardCropper) { setStatus('Kəsmə aləti yüklənməyib — səhifəni yeniləyin.', 'bad'); return; }
        open(true);
        const cropped = await window.IdCardCropper.open(new File([blob], `${side}.jpg`, { type: blob.type || 'image/jpeg' }));
        if (!cropped) return; // ləğv
        images[side] = cropped;
        const img = form.querySelector(`[data-idcard-preview="${side}"]`);
        setPreview(side, null);
        img.dataset.objectUrl = URL.createObjectURL(cropped);
        img.src = img.dataset.objectUrl;
        img.hidden = false;
        toggleBackSide();
        flash(img);
        await readIdCard(cropped);
    }

    async function readIdCard(blob) {
        setStatus('Vəsiqə oxunur…', 'info');
        const data = new FormData();
        data.append('image', new File([blob], 'id-card.jpg', { type: 'image/jpeg' }));
        try {
            const response = await fetch(url(box.dataset.ocrUrl), {
                method: 'POST', credentials: 'same-origin', body: data,
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error([result.message || `OCR alınmadı (${response.status})`, result.debug].filter(Boolean).join(' — '));

            const filled = [];
            Object.entries(result.fields || {}).forEach(([name, value]) => {
                const input = form.elements[name];
                if (!input || !value) return;
                // operatorun yazdığını dəyişmirik — yalnız boş və ya əvvəl OCR-ın doldurduğu xana
                if (input.value && !autofilled.has(name) && input.value !== value) return;
                if (input.tagName === 'SELECT' && ![...input.options].some((option) => option.value === value)) return;
                input.value = value;
                autofilled.add(name);
                flash(input);
                filled.push(input.closest('.as-field').querySelector('span').textContent);
            });
            toggleBackSide();

            const lines = [];
            let kind = 'ok';
            if (filled.length) lines.push(`Vəsiqədən dolduruldu: ${filled.join(', ')}. Yoxlayın.`);
            else { lines.push(result.is_id_card ? 'Vəsiqədən yeni məlumat oxunmadı.' : 'Şəkil vəsiqəyə oxşamır — yoxlayın.'); kind = 'bad'; }
            if (result.name_match === false && result.card_name) {
                lines.push(`Diqqət: vəsiqədəki ad "${result.card_name}" — müştərinin adı ilə uyğun gəlmir.`);
                kind = 'bad';
            }
            setStatus(lines.join(' '), kind);
        } catch (error) {
            setStatus(error.message, 'bad');
        }
    }

    // əl ilə fayl seçmək də olar (kompüterdəki şəkil)
    form.querySelectorAll('[data-idcard-file]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            input.value = '';
            if (file) onImage(input.dataset.idcardFile, file);
        });
    });

    /* ---------- yadda saxla ---------- */
    form.addEventListener('input', (event) => {
        if (event.target.name) autofilled.delete(event.target.name);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!customer) return;
        form.querySelectorAll('.is-invalid').forEach((node) => node.classList.remove('is-invalid'));
        form.querySelectorAll('.as-field-error').forEach((node) => node.remove());
        formError.hidden = true;

        const data = new FormData();
        ['father_name', 'fin', 'id_card_series', 'id_card_number', 'relative_1_name', 'relative_1_phone',
            'relative_2_name', 'relative_2_phone', 'workplace_name', 'salary'].forEach((name) => data.append(name, form.elements[name].value.trim()));
        Object.entries(images).forEach(([side, blob]) => { if (blob) data.append(side, new File([blob], `${side}.jpg`, { type: 'image/jpeg' })); });

        submit.disabled = true;
        try {
            const response = await fetch(url(box.dataset.saveUrl), {
                method: 'POST', credentials: 'same-origin', body: data,
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            const result = await response.json().catch(() => ({}));
            if (response.status === 422) {
                Object.entries(result.errors || {}).forEach(([name, messages]) => {
                    const field = form.querySelector(`[data-field="${name}"]`) || form.elements[name]?.closest('.as-field');
                    if (!field) { formError.textContent = messages[0]; formError.hidden = false; return; }
                    field.classList.add('is-invalid');
                    const note = document.createElement('small');
                    note.className = 'as-field-error';
                    note.textContent = messages[0];
                    field.append(note);
                });
                setStatus('Bəzi xanalar düzgün deyil — qırmızı xanalara baxın.', 'bad');
                form.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (response.status === 401 || response.status === 419) throw new Error('Sessiya bitib — admin panelə yenidən daxil olun');
            if (!response.ok) throw new Error(result.message || `Xəta (${response.status})`);

            customer.credit_ready = result.complete;
            customer.credit = result.credit;
            fill(result.credit);
            setState(result.complete);
            setStatus(result.message, 'ok');
        } catch (error) {
            setStatus(error.message, 'bad');
        } finally {
            submit.disabled = false;
        }
    });

    /* ---------- extension-dan mesajlar ---------- */
    window.addEventListener('message', async (event) => {
        if (event.origin !== origin || event.source !== window.parent) return;
        const data = event.data || {};
        if (data.type === 'ps-credit-field') onField(String(data.field || ''), data.text);
        if (data.type === 'ps-credit-error') {
            if (!customer) { warnNoCustomer(); return; }
            open(true);
            setStatus(String(data.message || 'Xəta'), 'bad');
        }
        if (data.type === 'ps-id-card' && data.dataUrl) {
            const side = data.side === 'back' ? 'id_card_back' : 'id_card_front';
            try {
                const blob = await (await fetch(data.dataUrl)).blob();
                onImage(side, blob);
            } catch (error) {
                setStatus('Şəkil alınmadı — WhatsApp-da şəkli böyük açıb yenidən cəhd edin.', 'bad');
            }
        }
    });
})();
