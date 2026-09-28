// Məhsul səhifəsi: aşağıda sabit "Səbətə at" paneli (mobil)
(() => {
    const bar = document.getElementById('productSticky');
    const mainBtn = document.querySelector('.buy-core [data-add-to-cart]');
    const buyCore = document.querySelector('.buy-core');
    if (!bar || !mainBtn || !buyCore) return;

    const stickyBtn = bar.querySelector('[data-sticky-add]');
    const sizeEl = bar.querySelector('[data-sticky-size]');
    const priceEl = bar.querySelector('[data-sticky-price]');

    // Ölçü, qiymət, düymə mətni və vəziyyəti əsas blokla eyni qalsın
    function sync() {
        sizeEl.textContent = buyCore.querySelector('.size-pill.active-size-amount')?.textContent.trim() || '';
        priceEl.textContent = buyCore.querySelector('[data-price-display]')?.textContent.trim() || '';
        stickyBtn.textContent = mainBtn.textContent.trim();
        stickyBtn.disabled = mainBtn.disabled;
    }

    new MutationObserver(sync).observe(buyCore, {
        subtree: true, childList: true, characterData: true,
        attributes: true, attributeFilter: ['class', 'disabled'],
    });
    sync();

    // Əsas düyməni işə salırıq — main.js-in səbət məntiqi eyni qalır
    stickyBtn.addEventListener('click', () => mainBtn.click());

    // Panel yalnız əsas düymə yuxarıda, ekrandan çıxanda görünür
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            const show = !entry.isIntersecting && entry.boundingClientRect.top < 0;
            bar.classList.toggle('is-visible', show);
            bar.inert = !show;
        }).observe(mainBtn);
    }
})();
