(function (global) {
  'use strict';

  /**
   * Bind USB QR/barcode scanner input to a search field.
   * Scanners emulate keyboard: rapid keystrokes ending with Enter.
   */
  function bindQrScannerSearch(options) {
    var inputId = options && options.inputId ? options.inputId : 'filterInput';
    var onSubmit = options && typeof options.onSubmit === 'function' ? options.onSubmit : null;
    var scanGapMs = (options && options.scanGapMs) || 120;
    var scanResetMs = (options && options.scanResetMs) || 250;
    var maxScanMs = (options && options.maxScanMs) || 900;
    var minLength = (options && options.minLength) || 2;

    var input = document.getElementById(inputId);
    if (!input) return;

    var buffer = '';
    var firstKeyAt = 0;
    var lastKeyAt = 0;
    var resetTimer = null;

    function clearBuffer() {
      buffer = '';
      firstKeyAt = 0;
      lastKeyAt = 0;
      if (resetTimer) {
        clearTimeout(resetTimer);
        resetTimer = null;
      }
    }

    function scheduleClear() {
      if (resetTimer) clearTimeout(resetTimer);
      resetTimer = setTimeout(clearBuffer, scanResetMs);
    }

    function submitScan(value) {
      clearBuffer();
      input.focus();
      input.value = value;
      input.select();
      if (onSubmit) {
        onSubmit(value, input);
      }
    }

    function isPrintableKey(key) {
      return typeof key === 'string' && key.length === 1;
    }

    function isModifier(e) {
      return e.ctrlKey || e.metaKey || e.altKey;
    }

    function isEditableTypingTarget(el) {
      if (!el || el === input) return el === input;
      if (el.isContentEditable) return true;
      var tag = el.tagName;
      if (tag === 'TEXTAREA') return true;
      if (tag !== 'INPUT') return false;
      var type = String(el.type || 'text').toLowerCase();
      if (type === 'checkbox' || type === 'radio' || type === 'button' || type === 'submit' || type === 'hidden') {
        return false;
      }
      return !el.readOnly && !el.disabled;
    }

    function isActiveScan(now) {
      return buffer.length > 0 && firstKeyAt > 0 && (now - firstKeyAt) <= maxScanMs;
    }

    function shouldIntercept(now) {
      if (!isActiveScan(now)) return false;
      if (document.activeElement === input) return false;
      if (isEditableTypingTarget(document.activeElement)) return false;
      return buffer.length >= 1;
    }

    document.addEventListener(
      'keydown',
      function (e) {
        if (isModifier(e)) return;

        var now = Date.now();

        if (e.key === 'Enter') {
          if (buffer.length >= minLength && isActiveScan(now)) {
            e.preventDefault();
            e.stopPropagation();
            submitScan(buffer);
          } else {
            clearBuffer();
          }
          return;
        }

        if (!isPrintableKey(e.key)) return;

        var gap = lastKeyAt ? now - lastKeyAt : scanGapMs + 1;

        if (!buffer) {
          buffer = e.key;
          firstKeyAt = now;
        } else if (gap > scanGapMs) {
          if (firstKeyAt && now - firstKeyAt <= scanResetMs) {
            buffer += e.key;
          } else {
            buffer = e.key;
            firstKeyAt = now;
          }
        } else {
          buffer += e.key;
        }

        lastKeyAt = now;
        scheduleClear();

        if (shouldIntercept(now)) {
          e.preventDefault();
          e.stopPropagation();
        }
      },
      true
    );
  }

  global.bindQrScannerSearch = bindQrScannerSearch;
})(typeof window !== 'undefined' ? window : this);
