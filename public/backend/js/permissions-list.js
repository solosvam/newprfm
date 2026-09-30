// İcazələr siyahısı: yaratma xətası olanda sağ modal yenidən açılır (data-open-on-load).
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('.modal[data-open-on-load]');
    if (modal && window.bootstrap) new bootstrap.Modal(modal).show();
});
