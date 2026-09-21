/**
 * Loader
 * Shows the spinner only while the document is still loading.
 */
(function () {
  let timeoutId = null;

  const removeSpinner = () => {
    if (timeoutId) {
      clearTimeout(timeoutId);
    }
    document.body.classList.remove('spinner');
  };

  if (document.readyState === 'loading') {
    timeoutId = setTimeout(() => {
      if (document.readyState === 'loading') {
        document.body.classList.add('spinner');
      }
    }, 500);

    document.addEventListener('DOMContentLoaded', removeSpinner, { once: true });
  } else {
    removeSpinner();
  }

  window.addEventListener('pageshow', removeSpinner);
})();
