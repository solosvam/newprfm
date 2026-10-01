// Axtarış idarəetməsi: alias modalı (növə görə sahələr), "Nəticəsiz axtarışlar"dan doldurma, silmə təsdiqi
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('newAlias');
    if (!modalElement || !window.bootstrap) return;

    const form = modalElement.querySelector('[data-alias-form]');
    const aliasInput = form.querySelector('[data-alias-input]');
    const fromInput = form.querySelector('[data-alias-from]');
    const typeSelect = form.querySelector('[data-alias-type]');
    const brandSelect = form.querySelector('[data-alias-brand]');
    const brandField = form.querySelector('[data-alias-field="brand"]');
    const modelField = form.querySelector('[data-alias-field="model"]');
    const ignoreField = form.querySelector('[data-alias-field="ignore"]');
    const optional = form.querySelector('[data-alias-optional]');

    const modal = () => bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);

    // brend: brend məcburi; model: brend istəyə bağlı + düzgün ad; artıq söz: tam söz və ya kök
    function applyType() {
        const type = typeSelect.value;
        brandField.hidden = type === 'ignore';
        modelField.hidden = type !== 'model';
        ignoreField.hidden = type !== 'ignore';
        optional.hidden = type !== 'model';
        form.querySelectorAll('[data-alias-hint]').forEach((hint) => { hint.hidden = hint.dataset.aliasHint !== type; });
    }
    typeSelect.addEventListener('change', applyType);
    applyType();

    // Artıq söz: default — kök; 4 hərfdən qısa söz avtomatik "yalnız bu söz" (de, la, var — kök olsa adları atar).
    // Admin özü seçibsə, ona toxunmuruq.
    const matchExact = form.querySelector('#aliasMatchExact');
    const matchPrefix = form.querySelector('#aliasMatchPrefix');
    let matchTouched = form.querySelector('[name="confirm_prefix"]') !== null; // xəta ilə qayıdıbsa — seçim adminindir
    [matchExact, matchPrefix].forEach((radio) => radio.addEventListener('change', () => { matchTouched = true; }));

    function autoMatch() {
        if (matchTouched) return;
        const letters = aliasInput.value.trim().replace(/[^\p{L}\p{N}]+/gu, '');
        (letters.length > 0 && letters.length < 4 ? matchExact : matchPrefix).checked = true;
    }
    aliasInput.addEventListener('input', autoMatch);

    if (window.jQuery && window.jQuery.fn.select2) {
        window.jQuery(brandSelect).select2({
            theme: 'bootstrap4', // Acorn admin temasının select2 dizaynı (crm-order.js-dəki kimi)
            placeholder: 'Brend seçin',
            width: '100%',
            dropdownParent: window.jQuery(modalElement),
            language: { noResults: () => 'Brend tapılmadı' },
        });
    }

    // "Yeni alias" və "Alias əlavə et" (nəticəsiz sorğudan — mətn hazır gəlir)
    document.querySelectorAll('[data-alias-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const query = button.dataset.aliasQuery || '';
            aliasInput.value = query;
            fromInput.value = query;
            matchTouched = false;
            autoMatch();
            modal().show();
        });
    });
    modalElement.addEventListener('shown.bs.modal', () => aliasInput.focus());

    // səhv olanda modal yenidən açılır
    if (modalElement.hasAttribute('data-open-on-load')) modal().show();

    document.querySelectorAll('form[data-confirm]').forEach((element) => {
        element.addEventListener('submit', (event) => {
            if (!window.confirm(element.dataset.confirm)) event.preventDefault();
        });
    });
});

// "Nəticəsiz axtarışlar": tək və toplu silmə — səhifə yenilənmədən
document.addEventListener('DOMContentLoaded', () => {
    const box = document.querySelector('[data-no-result]');
    if (!box) return;

    const bulk = box.querySelector('[data-no-result-bulk]');
    const count = box.querySelector('[data-no-result-count]');
    const all = box.querySelector('[data-no-result-all]');
    const empty = box.querySelector('[data-no-result-empty]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const checks = () => [...box.querySelectorAll('[data-no-result-check]')];
    const selected = () => checks().filter((check) => check.checked).map((check) => check.value);

    function refresh() {
        const list = checks();
        const chosen = selected().length;
        if (count) count.textContent = chosen;
        if (bulk) bulk.disabled = chosen === 0;
        if (all) {
            all.checked = list.length > 0 && chosen === list.length;
            all.indeterminate = chosen > 0 && chosen < list.length;
            all.closest('.form-check').hidden = list.length === 0;
        }
        if (bulk) bulk.hidden = list.length === 0;
        empty.hidden = list.length > 0;
    }

    async function remove(queries, button) {
        if (!queries.length) return;
        const text = queries.length === 1 ? `“${queries[0]}” qeydi silinsin?` : `${queries.length} qeyd silinsin?`;
        if (!window.confirm(text)) return;

        if (button) button.disabled = true;
        try {
            const response = await fetch(box.dataset.url, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ queries }),
            });
            if (!response.ok) throw new Error(`Xəta (${response.status})`);
            queries.forEach((query) => {
                box.querySelectorAll('[data-no-result-row]').forEach((row) => {
                    if (row.dataset.noResultRow === query) row.remove();
                });
            });
            refresh();
        } catch (error) {
            window.alert(`Silinmədi: ${error.message}. Səhifəni yeniləyib yenidən cəhd edin.`);
        } finally {
            if (button) button.disabled = false;
            refresh();
        }
    }

    box.addEventListener('change', (event) => {
        if (event.target === all) checks().forEach((check) => { check.checked = all.checked; });
        refresh();
    });
    box.addEventListener('click', (event) => {
        const single = event.target.closest('[data-no-result-delete]');
        if (single) remove([single.dataset.noResultDelete], single);
    });
    if (bulk) bulk.addEventListener('click', () => remove(selected(), bulk));
    refresh();
});
