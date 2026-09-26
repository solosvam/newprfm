document.addEventListener('click', function (e) {
    const favBtn = e.target.closest('.fav-btn');
    if (favBtn) {
        const productId = favBtn.dataset.productId;
        const wasActive = favBtn.classList.contains('active');

        favBtn.classList.toggle('active');

        fetch(`/favorites/${productId}`, {
            method: wasActive ? 'DELETE' : 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        }).catch(() => {
            favBtn.classList.toggle('active');
        });
        return;
    }

    const shareBtn = e.target.closest('.share-btn');
    if (shareBtn) {
        const url = shareBtn.dataset.url;
        const title = shareBtn.dataset.title;

        if (navigator.share) {
            navigator.share({ title, url }).catch(() => {});
        } else {
            navigator.clipboard.writeText(url).then(() => {
                shareBtn.setAttribute('aria-label', 'Kopyalandı');
                setTimeout(() => shareBtn.setAttribute('aria-label', 'Paylaş'), 1500);
            });
        }
        return;
    }

    // Fav/share düymələrinə klik olmayıbsa, kartın özünə klik kimi qəbul et
    const card = e.target.closest('.card');
    if (card && card.dataset.href) {
        window.location.href = card.dataset.href;
    }
});

// Orta/cmd-klik ilə yeni tabda açmaq istəyənlər üçün (opsional, amma UX üçün faydalı)
document.addEventListener('auxclick', function (e) {
    if (e.button !== 1) return; // yalnız orta klik
    const card = e.target.closest('.card');
    if (card && card.dataset.href && !e.target.closest('.icon-btn')) {
        window.open(card.dataset.href, '_blank');
    }
});
