// Rolun icazələri: switch → dərhal saxlanır; bölmədə "Hamısı" → toplu; axtarış və süzgəc.
(() => {
    'use strict';
    const root = document.querySelector('[data-rp]');
    if (!root) return;
    const url = root.dataset.url;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const alertBox = root.querySelector('[data-rp-alert]');
    const items = [...root.querySelectorAll('[data-rp-item]')];
    const inputOf = (item) => item.querySelector('input[type="checkbox"]');

    function paintGroup(group) {
        const boxes = [...group.querySelectorAll('[data-rp-item] input')];
        const on = boxes.filter((b) => b.checked).length;
        group.querySelector('[data-rp-group-count]').textContent = `${on} / ${boxes.length}`;
        const all = group.querySelector('[data-rp-all]');
        all.checked = on === boxes.length;
        all.indeterminate = on > 0 && on < boxes.length;
    }
    function paintAll() {
        root.querySelectorAll('[data-rp-group]').forEach(paintGroup);
        root.querySelector('[data-rp-count]').textContent = items.filter((i) => inputOf(i).checked).length;
        applyFilter();
    }
    function mark(targets, cls, text) {
        targets.forEach((item) => {
            const state = item.querySelector('.rp-item__state');
            state.className = 'rp-item__state ' + cls;
            state.textContent = text;
            if (cls === 'is-ok') setTimeout(() => { if (state.textContent === text) state.textContent = ''; }, 1500);
        });
    }

    async function save(targets, checked) {
        const ids = targets.map((item) => Number(inputOf(item).value));
        const before = new Map(targets.map((item) => [item, !checked]));
        targets.forEach((item) => { item.classList.add('is-saving'); inputOf(item).checked = checked; });
        alertBox.classList.add('d-none');
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ permission_ids: ids, checked }),
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(body.message || `Saxlanmadı (HTTP ${response.status}).`);
            const granted = new Set(body.granted.map(Number));
            items.forEach((item) => { inputOf(item).checked = granted.has(Number(inputOf(item).value)); });
            mark(targets, 'is-ok', '✓');
        } catch (error) {
            before.forEach((value, item) => { inputOf(item).checked = value; });
            mark(targets, 'is-bad', '✕');
            alertBox.textContent = error.message;
            alertBox.classList.remove('d-none');
        } finally {
            targets.forEach((item) => item.classList.remove('is-saving'));
            paintAll();
        }
    }

    items.forEach((item) => inputOf(item).addEventListener('change', (event) => save([item], event.target.checked)));

    root.querySelectorAll('[data-rp-group]').forEach((group) => {
        group.querySelector('[data-rp-all]').addEventListener('change', (event) => {
            const checked = event.target.checked;
            const targets = [...group.querySelectorAll('[data-rp-item]')].filter((item) => inputOf(item).checked !== checked);
            if (targets.length) save(targets, checked); else paintGroup(group);
        });
    });

    // Axtarış + süzgəc (Hamısı / Verilən / Verilməyən)
    const search = root.querySelector('[data-rp-search]');
    function applyFilter() {
        const query = search.value.trim().toLowerCase(); // PHP mb_strtolower ilə eyni (İ → i̇)
        const mode = root.querySelector('[data-rp-filter]:checked')?.value || 'all';
        let visible = 0;
        root.querySelectorAll('[data-rp-group]').forEach((group) => {
            let shown = 0;
            group.querySelectorAll('[data-rp-item]').forEach((item) => {
                const on = inputOf(item).checked;
                const match = (!query || item.dataset.search.includes(query)) && (mode === 'all' || (mode === 'on') === on);
                item.classList.toggle('d-none', !match);
                if (match) shown++;
            });
            group.classList.toggle('d-none', shown === 0);
            visible += shown;
        });
        root.querySelector('[data-rp-empty]').classList.toggle('d-none', visible > 0);
    }
    search.addEventListener('input', applyFilter);
    root.querySelectorAll('[data-rp-filter]').forEach((radio) => radio.addEventListener('change', applyFilter));

    paintAll();
})();
