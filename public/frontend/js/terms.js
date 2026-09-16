const modalContainer = document.querySelector('.rules-modal-container');
const modalOverlay = document.querySelector('.modal-overlay');
const modalCloseBtn = document.querySelector('.modal-section__close');
const rulesButtons = document.querySelectorAll('.terms-link'); 

function openModal() {
  modalContainer.style.display = 'block';
}

function closeModal() {
  modalContainer.style.display = 'none';
}

rulesButtons.forEach(button => {
  button.addEventListener('click', openModal);
});

modalOverlay.addEventListener('click', closeModal);
modalCloseBtn.addEventListener('click', closeModal);



let contents = document.querySelectorAll('.rules-content .content-box');
let rules_btns = document.querySelectorAll('.actions-container button');

for (let btn of rules_btns) {
  btn.onclick = function () {
    // Aktiv düyməni dəyiş
    let activeBtn = document.querySelector('.active-rules-btn');
    if (activeBtn) activeBtn.classList.remove('active-rules-btn');
    this.classList.add('active-rules-btn');

    // Aktiv məzmunu dəyiş
    let id = this.getAttribute('data-id');
    for (let content of contents) {
      if (content.id === id) {
        content.classList.add('active-content');
      } else {
        content.classList.remove('active-content');
      }
    }
  };
}