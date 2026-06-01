function getCurrentTime() {
    const options = {
        timeZone: 'Asia/Singapore',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };

    const date = new Intl.DateTimeFormat('en-US', options).format(new Date());
    const [month, day, year, hour, minute, second] = date.split(/[/,\s:]+/);
    return `${month}-${day}-${year} ${hour}:${minute}:${second}`;
}

// Run the function every 2 seconds
setInterval(getCurrentTime, 2000);

// Run the function every 2 seconds
setInterval(getCurrentTime, 2000);

const DV_CONTRACTUAL_TYPES = new Set([
    'Contractual Services or Job Order',
    'Contractual Services or Job Order Salary',
]);

const DV_KNOWN_EMP_TAGS = new Set([
    'Other Professional Services',
    'Janitorial Services',
    'Security Services',
]);

function normalizeNameKey(name) {
    return String(name || '')
        .trim()
        .replace(/\s+/g, ' ');
}

function normalizeEmpTag(raw) {
    const tag = String(raw || '').trim();
    if (tag === 'Janitorial Services' || tag === 'Security Services') {
        return tag;
    }
    return 'Other Professional Services';
}

function resolveServiceAccountTitle(empTag) {
    return normalizeEmpTag(empTag);
}

function resolveEmpTagForPayee(payee, explicitTag, empId) {
    const explicit = String(explicitTag || '').trim();
    if (explicit && DV_KNOWN_EMP_TAGS.has(explicit)) {
        return normalizeEmpTag(explicit);
    }

    const cfg = window.DV_ACCOUNTING || {};
    const known = cfg.knownEmpTags || Array.from(DV_KNOWN_EMP_TAGS);
    if (explicit && known.indexOf(explicit) !== -1) {
        return normalizeEmpTag(explicit);
    }

    const id = String(empId || '').trim();
    if (id && cfg.payeeEmpTagsByEmpId && cfg.payeeEmpTagsByEmpId[id]) {
        return normalizeEmpTag(cfg.payeeEmpTagsByEmpId[id]);
    }

    const payeeKey = normalizeNameKey(payee);
    if (payeeKey && cfg.payeeEmpTags && cfg.payeeEmpTags[payeeKey]) {
        return normalizeEmpTag(cfg.payeeEmpTags[payeeKey]);
    }
    if (payeeKey && cfg.payeeEmpTagsLower && cfg.payeeEmpTagsLower[payeeKey.toLowerCase()]) {
        return normalizeEmpTag(cfg.payeeEmpTagsLower[payeeKey.toLowerCase()]);
    }

    const loggedName = normalizeNameKey(cfg.loggedUserName || '');
    if (loggedName && payeeKey && loggedName.toLowerCase() === payeeKey.toLowerCase()) {
        return normalizeEmpTag(cfg.defaultEmpTag || 'Other Professional Services');
    }

    return 'Other Professional Services';
}

function getSalaryAccountingRows(empTag) {
    const cfg = window.DV_ACCOUNTING || {};
    const uacsMap = cfg.uacsMap || {};
    const serviceTitle = resolveServiceAccountTitle(empTag);
    const commonTitles = cfg.salaryCommonTitles || [
        'Due to Pag-ibig Premium',
        'Due to Pag-ibig MPL',
        'Due to Pag-ibig CAL',
        'Due to PhilHealth',
        'Due to GOCCs',
        'Cash-MDS, Regular',
    ];
    const rows = [
        { title: serviceTitle, uacs: uacsMap[serviceTitle] || '', indent: false },
    ];
    commonTitles.forEach(function (title) {
        rows.push({ title: title, uacs: uacsMap[title] || '', indent: true });
    });
    return rows;
}

function isContractualSalaryVoucher(voucherType) {
    const t = String(voucherType || '').trim();
    if (DV_CONTRACTUAL_TYPES.has(t)) return true;
    const cfgTypes = (window.DV_ACCOUNTING && window.DV_ACCOUNTING.contractualTypes) || [];
    return cfgTypes.indexOf(t) !== -1;
}

function buildEmptyAccountingRowHtml() {
    return (
        '<tr class="dv-accounting-row dv-accounting-row--empty">' +
        '<td class="pad-2 dv-account-title" colspan="2">&nbsp;</td>' +
        '<td class="pad-2 dv-uacs-code text-centered" style="width: 100px !important;">&nbsp;</td>' +
        '<td class="pad-2 dv-debit">&nbsp;</td>' +
        '<td class="pad-2 dv-credit">&nbsp;</td>' +
        '</tr>'
    );
}

function renderAccountingEntries() {
    const tbody = document.getElementById('dv_accounting_body');
    if (!tbody) return;

    const voucherType = sessionStorage.getItem('voucher_type') || '';
    if (!isContractualSalaryVoucher(voucherType)) {
        tbody.className = 'dv-accounting-body--empty';
        tbody.innerHTML = Array.from({ length: 7 }, buildEmptyAccountingRowHtml).join('');
        return;
    }

    tbody.className = '';
    const payee = sessionStorage.getItem('payee') || '';
    const empId = sessionStorage.getItem('tin_employee_no') || '';
    const storedTag = sessionStorage.getItem('emp_tag') || '';
    const empTag = resolveEmpTagForPayee(payee, storedTag, empId);
    sessionStorage.setItem('emp_tag', empTag);
    const rows = getSalaryAccountingRows(empTag);
    tbody.innerHTML = rows.map(function (row) {
        const titleClass = 'pad-2 dv-account-title' + (row.indent ? ' dv-account-title--indent' : '');
        const titleText = row.title ? escapeHtml(row.title) : '&nbsp;';
        const uacsText = row.uacs ? escapeHtml(row.uacs) : '&nbsp;';
        return (
            '<tr class="dv-accounting-row">' +
            '<td class="' + titleClass + '" colspan="2">' + titleText + '</td>' +
            '<td class="pad-2 dv-uacs-code" style="width: 100px !important;">' + uacsText + '</td>' +
            '<td class="pad-2 dv-debit">&nbsp;</td>' +
            '<td class="pad-2 dv-credit">&nbsp;</td>' +
            '</tr>'
        );
    }).join('');
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function clearDvSignatories() {
    [
        'dv_sig_cert_name', 'dv_sig_cert_pos1',
        'dv_sig_accounting_name', 'dv_sig_accounting_pos1', 'dv_sig_accounting_pos2',
        'dv_sig_approved_name', 'dv_sig_approved_pos1', 'dv_sig_approved_pos2',
    ].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.textContent = '';
    });
}

function applyDvSignatories(signatories) {
    const sig = signatories || {};
    const cert = sig.cert || {};
    const accounting = sig.accounting || {};
    const approved = sig.approved || {};

    const map = {
        dv_sig_cert_name: cert.name,
        dv_sig_cert_pos1: cert.pos1,
        dv_sig_accounting_name: accounting.name,
        dv_sig_accounting_pos1: accounting.pos1,
        dv_sig_accounting_pos2: accounting.pos2,
        dv_sig_approved_name: approved.name,
        dv_sig_approved_pos1: approved.pos1,
        dv_sig_approved_pos2: approved.pos2,
    };

    Object.keys(map).forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.textContent = String(map[id] || '');
    });
    storeDvSignatories(signatories);
}

function getSignatoryByKey(key) {
    const cfg = window.DV_SIGNATORY || {};
    const lookupKey = String(key || '').trim();
    if (!lookupKey) return null;

    const byKey = cfg.optionsByKey;
    if (byKey && typeof byKey === 'object' && !Array.isArray(byKey) && byKey[lookupKey]) {
        return byKey[lookupKey];
    }

    const options = Array.isArray(cfg.options) ? cfg.options : [];
    for (let i = 0; i < options.length; i++) {
        const opt = options[i];
        if (opt && String(opt.key || '') === lookupKey) {
            return opt;
        }
    }
    return null;
}

function storeDvSignatories(signatories) {
    try {
        sessionStorage.setItem('dv_signatories', JSON.stringify(signatories || {}));
    } catch (e) {
        // ignore storage failures
    }
}

function applyStoredDvSignatories() {
    try {
        const raw = sessionStorage.getItem('dv_signatories');
        if (!raw) return;
        applyDvSignatories(JSON.parse(raw));
    } catch (e) {
        // ignore invalid stored payload
    }
}

function buildSignatorySelection(certKey, accountingKey, approvedKey) {
    const cert = getSignatoryByKey(certKey);
    const accounting = getSignatoryByKey(accountingKey);
    const approved = getSignatoryByKey(approvedKey);
    return {
        cert: cert ? { name: cert.name, pos1: cert.pos1, pos2: cert.pos2 } : { name: '', pos1: '', pos2: '' },
        accounting: accounting ? { name: accounting.name, pos1: accounting.pos1, pos2: accounting.pos2 } : { name: '', pos1: '', pos2: '' },
        approved: approved ? { name: approved.name, pos1: approved.pos1, pos2: approved.pos2 } : { name: '', pos1: '', pos2: '' },
    };
}

function passItem(itemList, processing_no) {
    const items = itemList[processing_no];

    // Get the original number from the table cell
    const originalAmount = getValueByKey(items, 'amount');;

    const number = parseFloat(originalAmount);
    // Format the number
    const formattedNumber = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(number);

    if (items) {
        const payee = getValueByKey(items, 'payee');
        const address = getValueByKey(items, 'address');
        const voucher_date = getValueByKey(items, 'voucher_date');
        const amount = formattedNumber;
        const tin_employee_no = getValueByKey(items, 'tin_employee_no') || '';
        const particulars = getValueByKey(items, 'particulars');
        const voucher_type = getValueByKey(items, 'voucher_type') || '';
        const explicitTag = getValueByKey(items, 'emp_tag') || getValueByKey(items, 'tag');
        const emp_tag = resolveEmpTagForPayee(payee, explicitTag, tin_employee_no);

        sessionStorage.setItem('payee', payee);
        sessionStorage.setItem('address', address);
        sessionStorage.setItem('voucher_date', voucher_date);
        sessionStorage.setItem('amount', amount);
        sessionStorage.setItem('tin_employee_no', tin_employee_no);
        sessionStorage.setItem('particulars', particulars);
        sessionStorage.setItem('voucher_type', voucher_type);
        sessionStorage.setItem('emp_tag', emp_tag);

        sessionStorage.removeItem('dv_signatories');
        clearDvSignatories();
        renderAccountingEntries();
        console.log('Data updated:', sessionStorage.getItem('particulars'));
        console.log('Data updated:', sessionStorage.getItem('amount'));
    } else {
        sessionStorage.clear();
        console.log('No items found for payee, sessionStorage cleared.');
    }
}

//HANDLE QUOTES AND OTHER CHARS
function decodeHtmlEntities(str) {
    const htmlEntitiesMap = {
        '&quot;': '"',
        '&amp;': '&',
        '&lt;': '<',
        '&gt': '>'
    }

    return str.replace(/&[a-zA-Z0-9#]+;/g, (match) => htmlEntitiesMap[match] || match)
}

function getValueByKey(items, key) {
    const item = items.find(item => item.key === key);
    return item ? decodeHtmlEntities(item.value) : null;
}

function setDocumentData() {
    const payeeEl = document.getElementById('voucher_form_payee');
    if (!payeeEl) return;

    payeeEl.textContent = sessionStorage.getItem('payee') || '';
    const amountEl = document.getElementById('voucher_form_amount');
    if (amountEl) amountEl.textContent = sessionStorage.getItem('amount') || '';
    const payee2El = document.getElementById('voucher_form_payee2');
    if (payee2El) payee2El.textContent = sessionStorage.getItem('payee') || '';
    const amount2El = document.getElementById('voucher_form_amount2');
    if (amount2El) amount2El.textContent = sessionStorage.getItem('amount') || '';
    const addressEl = document.getElementById('voucher_form_address');
    if (addressEl) addressEl.textContent = sessionStorage.getItem('address') || '';
    const dateEl = document.getElementById('voucher_form_voucher_date');
    if (dateEl) dateEl.textContent = sessionStorage.getItem('voucher_date') || '';
    const tinEl = document.getElementById('voucher_form_tin_employee_no');
    if (tinEl) tinEl.textContent = sessionStorage.getItem('tin_employee_no') || '';
    const particularsEl = document.getElementById('voucher_form_particulars');
    if (particularsEl) particularsEl.textContent = sessionStorage.getItem('particulars') || '';
    renderAccountingEntries();
    applyStoredDvSignatories();
}

window.applyDvSignatories = applyDvSignatories;
window.buildSignatorySelection = buildSignatorySelection;
window.applyStoredDvSignatories = applyStoredDvSignatories;
window.storeDvSignatories = storeDvSignatories;

window.addEventListener('beforeprint', () => {
    setDocumentData();
});