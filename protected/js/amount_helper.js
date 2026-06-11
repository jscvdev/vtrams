/** Exact amount helpers — strip commas only; never round via parseFloat/toFixed. */
function normalizeAmountInput(raw) {
    var v = String(raw || '').replace(/,/g, '').trim();
    if (v === '') return '';
    v = v.replace(/[^\d.]/g, '');
    var dot = v.indexOf('.');
    if (dot !== -1) {
        v = v.slice(0, dot + 1) + v.slice(dot + 1).replace(/\./g, '');
    }
    return v;
}

/** Append .00 when amount has no decimal part (e.g. 15000 → 15000.00). */
function ensureAmountTwoDecimals(raw) {
    var normalized = normalizeAmountInput(raw);
    if (normalized === '') return '';
    return normalized.indexOf('.') === -1 ? normalized + '.00' : normalized;
}

function formatAmountDisplay(raw) {
    var normalized = normalizeAmountInput(raw);
    if (normalized === '') return '';
    var parts = normalized.split('.');
    var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.length > 1 ? intPart + '.' + parts[1] : intPart;
}

function sanitizeAmountInputField(input) {
    if (!input) return;
    input.value = String(input.value || '')
        .replace(/[^0-9.,]/g, '')
        .replace(/(\..*)\./g, '$1');
}

function isNonZeroAmount(raw) {
    var normalized = normalizeAmountInput(raw);
    return normalized !== '' && !/^0+\.?0*$/.test(normalized);
}

function syncAmountFields(sourceValue, outputElement) {
    if (!outputElement) return;
    var normalized = normalizeAmountInput(sourceValue);
    if (normalized !== '') {
        outputElement.value = normalized;
    }
}

function formatAmountTableCells(selector) {
    var nodes = document.querySelectorAll(selector || '.amount[data-amount]:not([data-amount-skip])');
    nodes.forEach(function(el) {
        if (el.getAttribute('data-amount-skip') === '1' || el.getAttribute('data-amount-formatted') === 'php') {
            return;
        }
        var raw = el.getAttribute('data-amount');
        if (raw === null || String(raw).trim() === '') {
            return;
        }
        var formatted = formatAmountDisplay(raw);
        if (formatted !== '') {
            el.textContent = formatted;
        }
    });
}
