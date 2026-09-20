document.addEventListener('DOMContentLoaded', function () {
    const inviteButton = document.getElementById('inviteButton');
    const modal = document.getElementById('inviteModal');
    const closeButton = modal?.querySelector('.close-icon');
    const infoIcon = modal?.querySelector('.modal-header-wrap div img');
    const infoText = modal?.querySelector('.modal-header-wrap div span');

    if (inviteButton && modal) {
        inviteButton.addEventListener('click', function () {
            modal.style.display = 'block';
        });
    }

    if (closeButton && modal) {
        closeButton.addEventListener('click', function () {
            modal.style.display = 'none';
        });
    }

    if (infoIcon && infoText) {
        infoIcon.addEventListener('click', function () {
            infoText.classList.toggle('info-active');
        });
    }

    window.addEventListener('click', function (event) {
        if (modal && event.target === modal) {
            modal.style.display = 'none';
        }
    });

    const orderModal = document.querySelector('.order-status-modal-w-actions');
    const orderClose = orderModal?.querySelector('.modal-header .close-button');

    if (orderModal && orderClose) {
        orderClose.addEventListener('click', function () {
            orderModal.style.display = 'none';
        });
    }
});
