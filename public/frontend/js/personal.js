const toggleInput = document.getElementById("toggle-checkbox");
const form_credit = document.querySelector(".form__credit");

toggleInput?.addEventListener("change", function () {
  if (this.checked) {
    form_credit.style.display = "block";
  } else {
    form_credit.style.display = "none";
  }
});

const eyeIcon = document.querySelectorAll(".pass-eye-icon");

for (let icon of eyeIcon) {
  icon.onclick = function () {
    if (this.previousElementSibling.previousElementSibling.type === "text") {
      this.previousElementSibling.previousElementSibling.type = "password";
      this.previousElementSibling.style.display = "none";
    } else {
      this.previousElementSibling.previousElementSibling.type = "text";
      this.previousElementSibling.style.display = "block";
    }
  };
}
