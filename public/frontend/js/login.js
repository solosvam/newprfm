document.getElementById("submitBtn").addEventListener("click", function () {
  // Show OTP section and hide login form
  document.getElementById("otpSection").classList.add("show-otp-section");
  document.querySelector(".login-page__wrap__form").classList.add("hide-form");
});

document.getElementById("backBtn").addEventListener("click", function () {
  // Show the login form and hide OTP section
  document.getElementById("otpSection").classList.remove("show-otp-section");
  document
    .querySelector(".login-page__wrap__form")
    .classList.remove("hide-form");

  // Re-add title when going back to the login form
  if (!document.querySelector("h1")) {
    const h1 = document.createElement("h1");
    h1.textContent = "Şəxsi kabinet";
    document.querySelector(".login-page__wrap").prepend(h1);
  }
});

document.getElementById("otpSubmitBtn").addEventListener("click", function () {
  const otpCode = document.getElementById("otpInput").value;
  const correctOtpCode = "123456";

  const otpInput = document.getElementById("otpInput");
  const errorMessage = document.querySelector(".otp-error-message");

  if (otpCode !== correctOtpCode) {
    // OTP code is incorrect
    otpInput.classList.add("error");
    if (!errorMessage) {
      const errorSpan = document.createElement("span");
      errorSpan.classList.add("otp-error-message");
      errorSpan.textContent = "Yanlış kod";
      otpInput.insertAdjacentElement("afterend", errorSpan);
    }
  } else {
    // OTP code is correct
    otpInput.classList.remove("error");
    if (errorMessage) {
      errorMessage.remove();
    }

    // Hide OTP section and show success section
    document.getElementById("otpSection").classList.add("hide-form");
    document.getElementById("otpSection").classList.remove("show-otp-section");
    document.getElementById("success-login").classList.remove("hide-form");

    // Remove the title when showing success section
    const title = document.querySelector("h1");
    if (title) {
      title.remove();
    }
  }
});
