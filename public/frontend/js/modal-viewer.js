function modalViewer(modalId, show = true) {
  const modal = document.getElementById(modalId);

  if (show) {
    modal.classList.remove("hidden");
    modal.classList.add("flex");
  } else {
    modal.classList.remove("flex");
    modal.classList.add("hidden");
  }
}
