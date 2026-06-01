/**
 * Table loading overlay (replaces global pre-loader).
 *
 * Behavior:
 * - Adds a lightweight overlay to each `.content-wrapper` that contains a table.
 * - Hides the overlay once the table has at least one body row, or if a "NO DATA" message is present.
 * - Works for both server-rendered rows and JS-inserted rows (MutationObserver).
 */
(function () {
  var MIN_LOADER_MS = 350;

  function hasDataRows(table) {
    if (!table) return false;
    // More robust than table.tBodies[0] (some pages have malformed/implicit tbody).
    var rows = table.querySelectorAll('tbody tr');
    return !!(rows && rows.length > 0);
  }

  function wrapperHasNoData(wrapper) {
    if (!wrapper) return false;
    var text = (wrapper.textContent || '').toUpperCase();
    return text.indexOf('NO DATA TO DISPLAY') !== -1;
  }

  function ensureLoader(wrapper) {
    if (!wrapper) return null;
    // Avoid :scope selector (can throw in some browsers / environments).
    for (var i = 0; i < wrapper.children.length; i++) {
      var child = wrapper.children[i];
      if (child && child.classList && child.classList.contains('table-loader')) {
        return null;
      }
    }

    wrapper.style.position = wrapper.style.position || 'relative';

    var loader = document.createElement('div');
    loader.className = 'table-loader';
    loader.innerHTML =
      '<div class="table-loader__inner">' +
      '<div class="table-loader__spinner" aria-hidden="true"></div>' +
      '<div class="table-loader__text">Loading…</div>' +
      '</div>';
    wrapper.appendChild(loader);
    wrapper.setAttribute('data-loader-start', String(Date.now()));
    return loader;
  }

  function hideLoader(loader) {
    if (!loader) return;
    var wrapper = loader.parentElement;
    var started = wrapper ? parseInt(wrapper.getAttribute('data-loader-start') || '0', 10) : 0;
    var elapsed = started > 0 ? (Date.now() - started) : MIN_LOADER_MS;
    var wait = Math.max(0, MIN_LOADER_MS - elapsed);
    setTimeout(function () {
      loader.classList.add('table-loader--hidden');
    }, wait);
  }

  function initOne(wrapper) {
    var table = wrapper.querySelector('table');
    var loader = ensureLoader(wrapper);
    if (!loader) return;

    function evaluate() {
      // Page can explicitly control loader visibility while async table rendering is ongoing.
      if (wrapper.getAttribute('data-table-loading') === 'true') {
        return false;
      }
      // Non-table containers (charts/cards) hide on page load event.
      if (!table) {
        if (document.readyState === 'complete') {
          hideLoader(loader);
          return true;
        }
        return false;
      }
      if (hasDataRows(table) || wrapperHasNoData(wrapper)) {
        hideLoader(loader);
        return true;
      }
      return false;
    }

    // If rows are already there, hide quickly (no flash)
    if (evaluate()) return;
    // Also re-check on next frame (large tables can finalize after DOMContentLoaded).
    if (typeof requestAnimationFrame === 'function') {
      requestAnimationFrame(evaluate);
    } else {
      setTimeout(evaluate, 0);
    }

    // Observe dynamic row insertion
    if (table) {
      var tbody = table.querySelector('tbody');
      if (tbody && typeof MutationObserver !== 'undefined') {
        var obs = new MutationObserver(function () {
          if (evaluate()) {
            obs.disconnect();
          }
        });
        obs.observe(tbody, { childList: true, subtree: true });
      }
    }

    // Fallback: never block forever (unless page explicitly marks table as still loading).
    setTimeout(function () {
      if (wrapper.getAttribute('data-table-loading') !== 'true') {
        hideLoader(loader);
      }
    }, 2500);
  }

  function init() {
    var wrappers = document.querySelectorAll(
      '.content-wrapper, .table-container, .chart-container, .voucher-card--table, .voucher-card--filter'
    );
    wrappers.forEach(initOne);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  window.addEventListener('load', function () {
    var loaders = document.querySelectorAll('.table-loader');
    loaders.forEach(function (loader) {
      var wrapper = loader.parentElement;
      if (!wrapper) return;
      if (wrapper.getAttribute('data-table-loading') === 'true') return;
      hideLoader(loader);
    });
  });
})();

