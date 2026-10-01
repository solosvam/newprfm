document.addEventListener('DOMContentLoaded', () => {
    const box = document.querySelector('[data-brand-aliases]');
    const suggest = box?.querySelector('[data-alias-suggest]');
    if (!suggest) return;
    const form = box.querySelector('[data-brand-alias-form]');
    const candidates = box.querySelector('[data-alias-candidates]');
    const existing = box.querySelector('[data-existing-aliases]');
    const status = box.querySelector('[data-alias-status]');
    const all = box.querySelector('[data-alias-all]');
    const save = box.querySelector('[data-alias-save]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let busy = false;
    const checks = () => [...candidates.querySelectorAll('input[type="checkbox"]')];
    function refresh() {
        const selected = checks().filter(input => input.checked).length;
        box.querySelector('[data-alias-count]').textContent = selected;
        save.disabled = busy || selected === 0;
        all.checked = checks().length > 0 && selected === checks().length;
        all.indeterminate = selected > 0 && selected < checks().length;
    }
    function setBusy(value) {
        busy = value;
        suggest.disabled = value;
        all.disabled = value;
        checks().forEach(input => { input.disabled = value; });
        existing.querySelectorAll('button').forEach(button => { button.disabled = value; });
        refresh();
    }
    function message(value, error = false) {
        status.textContent = value;
        status.classList.toggle('text-danger', error);
    }
    async function request(url, method, body) {
        const response = await fetch(url, {
            method,
            headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify(body),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Əməliyyat alınmadı.');
        return data;
    }
    function renderExisting(aliases) {
        existing.replaceChildren();
        aliases.forEach(alias => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-outline-primary d-inline-flex align-items-center gap-2';
            badge.dataset.aliasId = alias.id;
            const label = document.createElement('span');
            label.textContent = alias.alias;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-link p-0';
            remove.dataset.aliasDelete = alias.id;
            remove.setAttribute('aria-label', `${alias.alias} aliasını sil`);
            remove.textContent = '×';
            badge.append(label, remove);
            existing.append(badge);
        });
        if (!aliases.length) existing.textContent = 'Bu brend üçün alias yoxdur.';
    }
    suggest.addEventListener('click', async () => {
        if (busy) return;
        if (!form.hidden && checks().some(input => input.checked) && !window.confirm('Seçilən təkliflər əvəz olunsun?')) return;
        form.hidden = true;
        candidates.replaceChildren();
        setBusy(true);
        message('Yazılış variantları hazırlanır…');
        try {
            const data = await request(box.dataset.suggestUrl, 'POST', {});
            data.aliases.forEach((alias, index) => {
                const column = document.createElement('div');
                column.className = 'col-12 col-sm-6 col-lg-4';
                const wrapper = document.createElement('div');
                wrapper.className = 'form-check';
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input';
                input.id = `brandAliasCandidate${index}`;
                input.value = alias;
                const label = document.createElement('label');
                label.className = 'form-check-label';
                label.htmlFor = input.id;
                label.textContent = alias;
                wrapper.append(input, label);
                column.append(wrapper);
                candidates.append(column);
            });
            form.hidden = data.aliases.length === 0;
            message(data.aliases.length ? 'Uyğun variantları seçib təsdiqləyin.' : 'Yeni uyğun variant tapılmadı. Mövcud aliaslar təkrarlanmır.');
        } catch (error) { message(error.message, true); }
        finally { setBusy(false); }
    });
    all.addEventListener('change', () => {
        checks().forEach(input => { input.checked = all.checked; });
        refresh();
    });
    candidates.addEventListener('change', refresh);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const aliases = checks().filter(input => input.checked).map(input => input.value);
        if (busy || !aliases.length) return;
        setBusy(true);
        message('Aliaslar yadda saxlanılır…');
        try {
            const data = await request(box.dataset.storeUrl, 'POST', {aliases});
            renderExisting(data.aliases);
            checks().filter(input => input.checked).forEach(input => input.closest('.col-12').remove());
            form.hidden = checks().length === 0;
            message(data.message);
        } catch (error) { message(error.message, true); }
        finally { setBusy(false); }
    });
    existing.addEventListener('click', async event => {
        const button = event.target.closest('[data-alias-delete]');
        if (!button || busy) return;
        const badge = button.closest('[data-alias-id]');
        if (!window.confirm(`“${badge.querySelector('span').textContent}” aliası silinsin?`)) return;
        setBusy(true);
        try {
            const data = await request(box.dataset.deleteUrl.replace('__ALIAS__', button.dataset.aliasDelete), 'DELETE', {});
            badge.remove();
            if (!existing.querySelector('[data-alias-id]')) existing.textContent = 'Bu brend üçün alias yoxdur.';
            message(data.message);
        } catch (error) { message(error.message, true); }
        finally { setBusy(false); }
    });
});
