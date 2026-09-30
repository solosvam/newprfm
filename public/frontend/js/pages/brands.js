// Brendlər səhifəsi: ada görə axtarış — uyğun olmayan kartlar, boş hərf qrupları və hərf zolağındakı hərflər gizlənir/sönür.
(() => {
    'use strict';
    const page = document.querySelector('[data-brands-page]');
    if (!page) return;
    const input = page.querySelector('[data-brands-search]');
    const groups = [...page.querySelectorAll('[data-brands-group]')];
    const empty = page.querySelector('[data-brands-empty]');
    const letters = new Map([...page.querySelectorAll('[data-brands-letter]')].map((a) => [a.dataset.brandsLetter, a]));

    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        let visible = 0;
        groups.forEach((group) => {
            let shown = 0;
            group.querySelectorAll('[data-brand-name]').forEach((card) => {
                const match = !query || card.dataset.brandName.includes(query);
                card.hidden = !match;
                if (match) shown++;
            });
            group.hidden = shown === 0;
            letters.get(group.dataset.brandsGroup)?.classList.toggle('is-dim', shown === 0);
            visible += shown;
        });
        empty.hidden = visible > 0;
    });
})();
