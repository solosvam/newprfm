/**
 * Sonsuz scroll: [data-infinite-list] — elementlər bura əlavə olunur, data-next — növbəti səhifənin ünvanı.
 * [data-infinite-sentinel] görünəndə növbəti səhifə AJAX ilə gəlir (server {html, next} qaytarır).
 * Xəta olsa — "Yenidən cəhd et"; IntersectionObserver yoxdursa — "Daha çox göstər" düyməsi.
 * JS işləməsə — adi səhifə linkləri ([data-infinite-fallback]) qalır.
 */
(function () {
    'use strict';

    const list = document.querySelector('[data-infinite-list]');
    const sentinel = document.querySelector('[data-infinite-sentinel]');
    const fallback = document.querySelector('[data-infinite-fallback]');
    if (!list || window.__infiniteListInit) return;
    window.__infiniteListInit = true;
    if (fallback) fallback.hidden = true;
    if (!sentinel || !list.dataset.next) return;

    const text = sentinel.querySelector('.infinite-sentinel__text');
    let loading = false;
    let observer = null;

    function setState(state) {
        sentinel.dataset.state = state;
        text.textContent = '';
        if (state === 'loading') text.textContent = sentinel.dataset.loading;
        if (state === 'error' || state === 'manual') {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline infinite-sentinel__button';
            button.textContent = state === 'error' ? sentinel.dataset.retry : sentinel.dataset.more;
            button.addEventListener('click', load);
            if (state === 'error') text.append(sentinel.dataset.error + ' ');
            text.append(button);
        }
    }

    async function load() {
        const url = list.dataset.next;
        if (loading || !url) return;
        loading = true;
        setState('loading');
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error(String(response.status));
            const data = await response.json();
            list.insertAdjacentHTML('beforeend', data.html || '');
            list.dataset.next = data.next || '';
            // ünvan dəyişdirilmir: ?page=N-ə keçsə, yeniləyəndə əvvəlki səhifələr itərdi
            if (!data.next) {
                observer?.disconnect();
                sentinel.remove();
                return;
            }
            setState(observer ? 'idle' : 'manual');
        } catch (e) {
            setState('error');
        } finally {
            loading = false;
        }
        // sentinel hələ də görünürsə (qısa səhifə) — davam et
        if (observer && list.dataset.next) {
            const rect = sentinel.getBoundingClientRect();
            if (rect.top < window.innerHeight + 300) load();
        }
    }

    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting) && sentinel.dataset.state !== 'error') load();
        }, { rootMargin: '0px 0px 400px 0px' });
        observer.observe(sentinel);
        setState('idle');
    } else {
        setState('manual');
    }
})();
