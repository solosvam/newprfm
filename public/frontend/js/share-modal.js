(() => {
    const modal = document.getElementById('shareModal');
    if (!modal) return;

    const close = modal.querySelector('#share-modal-close');
    document.addEventListener('click', event => {
        const trigger = event.target.closest('.shareBtn, #shareBtn');
        if (trigger) {
            event.preventDefault();
            const title = trigger.dataset.title || document.title;
            const url = trigger.dataset.url || window.location.href;
            const shareLink = modal.querySelector('[data-share-url]');
            if (shareLink) shareLink.value = url;
            const shareTitle = modal.querySelector('[data-share-title]');
            if (shareTitle) shareTitle.textContent = title;
            modal.style.display = 'block';
        }
        if (event.target === modal || event.target === close || event.target.closest('#share-modal-close')) {
            modal.style.display = 'none';
        }
    });
})();
