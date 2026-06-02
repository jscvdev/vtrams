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
    document.querySelectorAll(selector || '.amount').forEach(function(el) {
        var raw = el.getAttribute('data-amount');
        if (raw === null || raw === '') {
            raw = el.textContent;
        }
        var formatted = formatAmountDisplay(raw);
        if (formatted !== '') {
            el.textContent = formatted;
        }
    });
}
