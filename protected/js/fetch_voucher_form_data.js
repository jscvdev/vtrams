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

/** Default accounting block height: 1 primary + 7 sub UACS rows (e.g. Other Professional Services). */
const DV_ACCOUNTING_MIN_ROWS = 8;

function getDvAccountingMinRows() {
    const cfg = window.DV_ACCOUNTING || {};
    const configured = Number(cfg.accountingMinRows);
    return configured > 0 ? configured : DV_ACCOUNTING_MIN_ROWS;
}

const DV_FALLBACK_EMP_TAGS = [
    'Other Professional Services',
    'Janitorial Services',
    'Security Services',
];

function getDvKnownEmpTags() {
    const cfg = window.DV_ACCOUNTING || {};
    const known = cfg.knownEmpTags;
    if (Array.isArray(known) && known.length) {
        return known;
    }
    return DV_FALLBACK_EMP_TAGS;
}

function normalizeEmpTag(raw) {
    const tag = String(raw || '').trim();
    const cfg = window.DV_ACCOUNTING || {};
    const known = getDvKnownEmpTags();
    if (tag && known.indexOf(tag) !== -1) {
        return tag;
    }
    return String(cfg.defaultEmpTag || 'Other Professional Services').trim() || 'Other Professional Services';
}

function normalizeNameKey(name) {
    return String(name || '')
        .trim()
        .replace(/\s+/g, ' ');
}

function resolveServiceAccountTitle(empTag) {
    return normalizeEmpTag(empTag);
}

function resolveEmpTagForPayee(payee, explicitTag, empId) {
    const explicit = String(explicitTag || '').trim();
    const cfg = window.DV_ACCOUNTING || {};
    const known = getDvKnownEmpTags();
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

    return normalizeEmpTag(cfg.defaultEmpTag || 'Other Professional Services');
}

function getVoucherTypeAccountingRows(voucherType) {
    const cfg = window.DV_ACCOUNTING || {};
    const maps = cfg.voucherTypeAccountingMaps || {};
    const typeKey = String(voucherType || '').trim();
    if (typeKey && maps[typeKey] && maps[typeKey].length) {
        return maps[typeKey];
    }
    return [];
}

function getSalaryAccountingRows(empTag) {
    const cfg = window.DV_ACCOUNTING || {};
    const normalized = resolveServiceAccountTitle(empTag);
    const maps = cfg.empTagSalaryMaps || {};
    if (maps[normalized] && maps[normalized].length) {
        return maps[normalized];
    }
    if (maps[empTag] && maps[empTag].length) {
        return maps[empTag];
    }

    const uacsMap = cfg.uacsMap || {};
    const serviceTitle = normalized;
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
    const cfgTypes = (window.DV_ACCOUNTING && window.DV_ACCOUNTING.contractualTypes) || [];
    if (cfgTypes.indexOf(t) !== -1) {
        return true;
    }
    return DV_CONTRACTUAL_TYPES.has(t);
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

function padAccountingRows(rows) {
    const minRows = getDvAccountingMinRows();
    const padded = rows.slice();
    while (padded.length < minRows) {
        padded.push({ title: '', uacs: '', indent: false, empty: true });
    }
    return padded;
}

function renderAccountingEntries() {
    const tbody = document.getElementById('dv_accounting_body');
    if (!tbody) return;

    const voucherType = sessionStorage.getItem('voucher_type') || '';
    let rows = [];

    if (isContractualSalaryVoucher(voucherType)) {
        const payee = sessionStorage.getItem('payee') || '';
        const empId = sessionStorage.getItem('tin_employee_no') || '';
        const storedTag = sessionStorage.getItem('emp_tag') || '';
        const empTag = resolveEmpTagForPayee(payee, storedTag, empId);
        sessionStorage.setItem('emp_tag', empTag);
        rows = getSalaryAccountingRows(empTag);
    } else {
        rows = getVoucherTypeAccountingRows(voucherType);
    }

    if (!rows.length) {
        tbody.className = 'dv-accounting-body--empty';
        tbody.innerHTML = Array.from({ length: getDvAccountingMinRows() }, buildEmptyAccountingRowHtml).join('');
        return;
    }

    tbody.className = '';
    tbody.innerHTML = padAccountingRows(rows).map(function (row) {
        if (row.empty) {
            return buildEmptyAccountingRowHtml();
        }
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

function getSignatoryById(id) {
    const cfg = window.DV_SIGNATORY || {};
    const lookupId = parseInt(String(id || ''), 10);
    if (!lookupId) return null;

    const byId = cfg.optionsById;
    if (byId && typeof byId === 'object' && !Array.isArray(byId) && byId[lookupId]) {
        return byId[lookupId];
    }

    const options = Array.isArray(cfg.options) ? cfg.options : [];
    for (let i = 0; i < options.length; i++) {
        const opt = options[i];
        if (opt && parseInt(String(opt.id || ''), 10) === lookupId) {
            return opt;
        }
    }
    return null;
}

function getSignatoriesForRole(roleKeys) {
    const cfg = window.DV_SIGNATORY || {};
    const keys = Array.isArray(roleKeys) ? roleKeys : [];
    const options = Array.isArray(cfg.options) ? cfg.options : [];
    const results = [];
    const seen = {};

    keys.forEach(function(key) {
        const bucket = cfg.optionsByKey && cfg.optionsByKey[key];
        if (Array.isArray(bucket)) {
            bucket.forEach(function(opt) {
                const id = String(opt && opt.id || '');
                if (!id || seen[id]) return;
                seen[id] = true;
                results.push(opt);
            });
            return;
        }
        if (bucket && typeof bucket === 'object') {
            const id = String(bucket.id || '');
            if (id && !seen[id]) {
                seen[id] = true;
                results.push(bucket);
            }
            return;
        }
        options.forEach(function(opt) {
            if (!opt || String(opt.key || '') !== key) return;
            const id = String(opt.id || '');
            if (!id || seen[id]) return;
            seen[id] = true;
            results.push(opt);
        });
    });

    return results;
}

function getSignatoryByKey(key) {
    const lookupKey = String(key || '').trim();
    if (!lookupKey) return null;

    const signatories = getSignatoriesForRole([lookupKey]);
    if (!signatories.length) return null;

    const defaulted = signatories.find(function(opt) {
        return opt && opt.isDefault;
    });
    return defaulted || signatories[0];
}

function applyDvSignatoryPayload(payload) {
    if (!payload || typeof payload !== 'object') return;
    const cfg = window.DV_SIGNATORY || {};
    cfg.options = Array.isArray(payload.options) ? payload.options : [];
    cfg.optionsByKey = payload.optionsByKey && typeof payload.optionsByKey === 'object' && !Array.isArray(payload.optionsByKey)
        ? payload.optionsByKey
        : {};
    cfg.optionsById = payload.optionsById && typeof payload.optionsById === 'object' && !Array.isArray(payload.optionsById)
        ? payload.optionsById
        : {};
    if (payload.defaultCertKey) cfg.defaultCertKey = payload.defaultCertKey;
    if (payload.defaultIds) cfg.defaultIds = payload.defaultIds;
    if (payload.office) cfg.office = payload.office;
    window.DV_SIGNATORY = cfg;
}

function populateDvSignatorySelect(selectEl, roleKeys, labels, defaultId) {
    if (!selectEl) return;
    selectEl.innerHTML = '';
    const signatories = getSignatoriesForRole(roleKeys);
    let hasDefault = false;
    const resolvedDefaultId = parseInt(String(defaultId || ''), 10);

    signatories.forEach(function(optData) {
        if (!optData || !optData.id) return;
        const option = document.createElement('option');
        option.value = String(optData.id);
        option.dataset.name = optData.name || '';
        option.dataset.pos1 = optData.pos1 || '';
        option.dataset.pos2 = optData.pos2 || '';
        const label = (labels && labels[optData.key]) ? labels[optData.key] : (optData.key || '');
        option.textContent = optData.name ? (optData.name + ' — ' + label) : label;
        if (resolvedDefaultId && parseInt(String(optData.id), 10) === resolvedDefaultId) {
            option.selected = true;
            hasDefault = true;
        }
        selectEl.appendChild(option);
    });

    if (!hasDefault && selectEl.options.length > 0) {
        selectEl.selectedIndex = 0;
    }
}

function hasDvPrintableSignatoryOptions() {
    const cfg = window.DV_SIGNATORY || {};
    const roles = cfg.roles || {};
    const hasRoleOption = function(roleKeys) {
        return getSignatoriesForRole(roleKeys).length > 0;
    };
    return hasRoleOption(roles.cert) && hasRoleOption(roles.accounting) && hasRoleOption(roles.approved);
}

function readDvSignatoryFromSelect(selectEl) {
    if (!selectEl) return { name: '', pos1: '', pos2: '' };
    const opt = selectEl.selectedOptions && selectEl.selectedOptions[0] ? selectEl.selectedOptions[0] : null;
    if (!opt) return { name: '', pos1: '', pos2: '' };

    const fromId = getSignatoryById(opt.value);
    if (fromId) {
        return { name: fromId.name || '', pos1: fromId.pos1 || '', pos2: fromId.pos2 || '' };
    }

    return {
        name: opt.dataset.name || '',
        pos1: opt.dataset.pos1 || '',
        pos2: opt.dataset.pos2 || '',
    };
}

function populateAllDvSignatorySelects(selectors) {
    const cfg = window.DV_SIGNATORY || {};
    const roles = cfg.roles || {};
    const labels = cfg.labels || {};
    const defaultIds = cfg.defaultIds || {};

    populateDvSignatorySelect(selectors.cert, roles.cert, labels, defaultIds.cert || 0);
    populateDvSignatorySelect(selectors.accounting, roles.accounting, labels, defaultIds.accounting || 0);
    populateDvSignatorySelect(selectors.approved, roles.approved, labels, defaultIds.approved || 0);
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

    const originalAmount = getValueByKey(items, 'amount');
    const formattedNumber = typeof formatAmountDisplay === 'function'
        ? formatAmountDisplay(originalAmount)
        : String(originalAmount || '');

    if (items) {
        const payee = getValueByKey(items, 'payee');
        const address = getValueByKey(items, 'address');
        const voucher_date = getValueByKey(items, 'voucher_date');
        const amount = formattedNumber || String(originalAmount || '');
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
window.getSignatoryById = getSignatoryById;
window.getSignatoryByKey = getSignatoryByKey;
window.getSignatoriesForRole = getSignatoriesForRole;
window.applyDvSignatoryPayload = applyDvSignatoryPayload;
window.populateDvSignatorySelect = populateDvSignatorySelect;
window.hasDvPrintableSignatoryOptions = hasDvPrintableSignatoryOptions;
window.readDvSignatoryFromSelect = readDvSignatoryFromSelect;
window.populateAllDvSignatorySelects = populateAllDvSignatorySelects;

window.addEventListener('beforeprint', () => {
    if (document.body.classList.contains('forward-slip-printing')) {
        return;
    }
    setDocumentData();
});