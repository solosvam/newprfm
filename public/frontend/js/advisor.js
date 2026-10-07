/**
 * AI ətir seçici (/advisor): cavablar POST /advisor-a gedir, nəticə (intro + kartların HTML-i) səhifəyə əlavə olunur.
 * Ətir ailələrindən ən çox 3-ü seçilə bilər. Gözləmə — 5–15 saniyə (AI kataloqu nəzərdən keçirir).
 */
(function () {
    'use strict';

    const form = document.getElementById('advisorForm');
    const results = document.getElementById('advisorResults');
    if (!form || !results) return;

    const body = document.getElementById('advisorBody');
    const intro = document.getElementById('advisorIntro');
    const submit = form.querySelector('.advisor-submit');
    const thinking = document.getElementById('advisorThinking');
    const submitHtml = submit.innerHTML;
    const say = (message, type) => (window.ParfumNotify ? window.ParfumNotify(message, type) : window.alert(message));

    // ailələr: 3-dən çox seçilməsin
    const families = form.querySelector('[data-max]');
    families?.addEventListener('change', () => {
        const max = Number(families.dataset.max);
        const boxes = [...families.querySelectorAll('input')];
        const checked = boxes.filter((b) => b.checked).length;
        boxes.forEach((b) => { b.disabled = !b.checked && checked >= max; });
    });

    function setLoading(on) {
        submit.disabled = on;
        form.classList.toggle('is-loading', on);
        submit.innerHTML = on ? '<span class="advisor-spinner" aria-hidden="true"></span>' + form.dataset.loading : submitHtml;
        // forma gizlənir, robot "düşünür"
        if (thinking) {
            thinking.hidden = !on;
            form.closest('.advisor')?.classList.toggle('is-thinking', on);   // başlıqdakı robot gizlənir
            form.hidden = on;
            if (on) thinking.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        setLoading(true);
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) { form.hidden = false; throw new Error(data.message || form.dataset.error); }

            intro.textContent = data.intro || data.empty || '';
            body.innerHTML = data.html || '';
            results.hidden = false;
            form.hidden = true;
            document.dispatchEvent(new CustomEvent('parfum:cards-added'));
            results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            say(error.message || form.dataset.error, 'error');
        } finally {
            setLoading(false);
        }
    });

    document.getElementById('advisorAgain')?.addEventListener('click', () => {
        results.hidden = true;
        form.hidden = false;
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
})();
