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
    if (normalized.indexOf('.') === -1) {
        normalized += '.00';
    }
    var parts = normalized.split('.');
    var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    var frac = (parts[1] || '00').padEnd(2, '0').slice(0, 2);
    return '₱' + intPart + '.' + frac;
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

function amountsEqualString(a, b) {
    return normalizeAmountInput(a) === normalizeAmountInput(b);
}

/** True when net should display separately from gross (non-zero and different). */
function shouldShowChargedNet(charged, gross) {
    if (!isNonZeroAmount(charged)) {
        return false;
    }
    if (gross !== undefined && gross !== null && String(gross).trim() !== '') {
        return !amountsEqualString(charged, gross);
    }
    return true;
}

/** Alias used by voucher portal pages (gross, net argument order). */
function hasDistinctNetAmount(gross, net) {
    return shouldShowChargedNet(net, gross);
}

function resolveEffectiveAmount(gross, charged) {
    var grossNorm = normalizeAmountInput(gross);
    var chargedNorm = normalizeAmountInput(charged);
    return shouldShowChargedNet(chargedNorm, grossNorm) ? chargedNorm : grossNorm;
}

function buildAmountStackHtml(gross, charged) {
    var grossNorm = normalizeAmountInput(gross);
    var chargedNorm = normalizeAmountInput(charged);
    var showNet = shouldShowChargedNet(chargedNorm, grossNorm);
    if (grossNorm === '' && !showNet) {
        return '—';
    }

    var html = '<div class="voucher-amount-stack">'
        + '<div class="voucher-amount-row voucher-amount-row--gross">'
        + '<span class="voucher-amount-row__label">Gross</span>'
        + '<span class="voucher-amount-row__value" data-amount-part="gross">' + formatAmountDisplay(grossNorm) + '</span>'
        + '</div>';

    if (showNet) {
        html += '<div class="voucher-amount-row voucher-amount-row--net">'
            + '<span class="voucher-amount-row__label">Net</span>'
            + '<span class="voucher-amount-row__value" data-amount-part="net">' + formatAmountDisplay(chargedNorm) + '</span>'
            + '</div>';
    }

    html += '</div>';
    return html;
}

function setAmountSplitViewMode(showSplit) {
    var amountMainLabel = document.querySelector('.amount_main_label');
    var amountMainDisplay = document.querySelector('.amount_main_display');
    var splitPanel = document.getElementById('voucherAmountSplitPanel');
    if (amountMainLabel) amountMainLabel.style.display = showSplit ? 'none' : '';
    if (amountMainDisplay) amountMainDisplay.style.display = showSplit ? 'none' : '';
    if (splitPanel) splitPanel.style.display = showSplit ? 'block' : 'none';
}

function populateAmountSplitView(gross, charged) {
    var grossNorm = normalizeAmountInput(gross);
    var chargedNorm = normalizeAmountInput(charged);
    var showSplit = shouldShowChargedNet(chargedNorm, grossNorm);
    var stringAmountInput = document.querySelector('.amount_main_display') || document.querySelector('.string_amount');
    var originalStringInput = document.getElementById('original_string_amount');
    var chargedStringInput = document.getElementById('charged_string_amount');
    var amountHidden = document.querySelector('.amount');
    var effective = showSplit ? chargedNorm : grossNorm;

    setAmountSplitViewMode(showSplit);

    if (amountHidden) {
        amountHidden.value = effective;
    }

    if (showSplit) {
        if (originalStringInput) setAmountDisplayValue(originalStringInput, grossNorm);
        if (chargedStringInput) setAmountDisplayValue(chargedStringInput, chargedNorm);
        if (stringAmountInput) stringAmountInput.value = '';
    } else {
        if (stringAmountInput) setAmountDisplayValue(stringAmountInput, grossNorm);
        if (originalStringInput) originalStringInput.value = '';
        if (chargedStringInput) chargedStringInput.value = '';
    }

    var originalContainer = document.querySelector('.original_charged_container');
    var chargedContainer = document.querySelector('.charged_amount_container');
    if (originalContainer) originalContainer.style.display = showSplit ? 'flex' : 'none';
    if (chargedContainer) chargedContainer.style.display = showSplit ? 'flex' : 'none';
}

function syncAmountFields(sourceValue, outputElement) {
    if (!outputElement) return;
    var normalized = normalizeAmountInput(sourceValue);
    if (normalized !== '') {
        outputElement.value = normalized;
    }
}

function formatAmountTableCells(selector) {
    var nodes = document.querySelectorAll(selector || 'td.amount[data-amount]:not([data-amount-skip])');
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
            if (el.getAttribute('data-amount-charged') === '1') {
                el.innerHTML = '<span style="color: red;">' + formatted + '</span>';
            } else {
                el.textContent = formatted;
            }
        }
    });
}

/** Readonly inputs: show comma-separated amount; stored/submitted values stay unformatted. */
function setAmountDisplayValue(input, raw) {
    if (!input) return;
    input.value = formatAmountDisplay(raw);
}

function formatAmountStackCells() {
    document.querySelectorAll('.voucher-amount-stack-cell').forEach(function(td) {
        var gross = td.getAttribute('data-amount-gross') || '';
        var net = td.getAttribute('data-amount-net') || '';
        var grossEl = td.querySelector('[data-amount-part="gross"]');
        var netEl = td.querySelector('[data-amount-part="net"]');
        if (grossEl && gross) {
            grossEl.textContent = formatAmountDisplay(gross);
        }
        if (netEl && net) {
            netEl.textContent = formatAmountDisplay(net);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    formatAmountTableCells();
    formatAmountStackCells();
});
