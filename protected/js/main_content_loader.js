/**
 * Hides #main content until styles are loaded, showing a skeleton placeholder instead.
 */
(function () {
  var MAX_WAIT_MS = 8000;
  var revealed = false;

  function revealMainContent() {
    if (revealed) {
      return;
    }
    revealed = true;
    document.documentElement.classList.remove('page-loading');
    document.documentElement.classList.add('page-loaded');
  }

  function scheduleReveal() {
    if (document.readyState === 'complete') {
      window.requestAnimationFrame(revealMainContent);
      return;
    }
    window.addEventListener('load', function () {
      window.requestAnimationFrame(revealMainContent);
    }, { once: true });
  }

  document.documentElement.classList.add('page-loading');
  scheduleReveal();
  window.setTimeout(revealMainContent, MAX_WAIT_MS);
})();
