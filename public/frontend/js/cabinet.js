document.addEventListener("DOMContentLoaded", function () {
  const inviteButton = document.querySelector("button");
  const modal = document.getElementById("inviteModal");
  const closeButton = document.querySelector(".close-icon");

  inviteButton.addEventListener("click", function () {
    modal.style.display = "block";
  });

  closeButton.addEventListener("click", function () {
    modal.style.display = "none";
  });

  window.addEventListener("click", function (event) {
    if (event.target === modal) {
      modal.style.display = "none";
    }
  });
});

const info_icon = document.querySelector(".modal-header-wrap div img");
const info_text = document.querySelector(".modal-header-wrap div span");

info_icon.onclick = () => {
  info_text.classList.toggle("info-active");
};

const order_status_with_actions_modal = document.querySelector(
  ".order-status-modal-w-actions"
);
const order_status_with_actions_modal_close_btn = document.querySelector(
  ".order-status-modal-w-actions .modal-header .close-button"
);

order_status_with_actions_modal_close_btn.addEventListener(
  "click",
  function () {
    order_status_with_actions_modal.style.display = "none";
  }
);
