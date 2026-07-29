(function(global) {
    'use strict';

    function escapeHtml(value) {
        if (value == null) {
            return '';
        }
        var div = document.createElement('div');
        div.textContent = String(value);
        return div.innerHTML;
    }

    function simplifyActionLabel(actionText) {
        var txt = String(actionText || '').trim();
        var byMatch = txt.match(/^(.+?)\s+by\b\s*:?\s*.*$/i);
        if (byMatch && byMatch[1]) {
            return byMatch[1].trim();
        }
        return txt;
    }

    function formatProcessHistoryDisplayLine(rawLine) {
        var line = String(rawLine || '').trim();
        if (line === '') {
            return '';
        }
        if (line.indexOf('|') === -1) {
            return line;
        }

        var parts = line.split(/\s*\|\s*/);
        var name = String(parts[0] || '').trim();
        var action = String(parts[1] || '').trim();
        var section = String(parts[2] || '').trim();
        var tail = String(parts[3] || '').trim();
        var datetime = String(parts[4] || '').trim();
        var unit = section !== '' ? section : tail;
        var actionPart = simplifyActionLabel(action);

        if (datetime !== '') {
            actionPart = actionPart + ' on ' + datetime;
        }

        if (name === '' && unit === '' && actionPart === '') {
            return line;
        }

        return name + ' | ' + unit + ' | ' + actionPart;
    }

    function renderProcessHistoryDisplayList(raw) {
        var normalized = String(raw || '')
            .replace(/\u00A0/g, ' ')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/\\n/g, '\n')
            .trim();

        if (!normalized) {
            return '<li class="status-history-item">No process history recorded.</li>';
        }

        return normalized.split('\n').filter(function(line) {
            return String(line).trim() !== '';
        }).map(function(line) {
            return '<li class="status-history-item">' + escapeHtml(formatProcessHistoryDisplayLine(String(line).trim())) + '</li>';
        }).join('');
    }

    function setVoucherPortalViewMode(isView, options) {
        options = options || {};
        var dynamicBtn = document.querySelector(options.dynamicBtnSelector || '.btn-dynamic');
        if (dynamicBtn) {
            dynamicBtn.style.display = isView ? 'none' : '';
        }

        var returnSubmit = document.querySelector(options.returnSubmitSelector || 'button[name="return_voucher"].warning');
        if (!returnSubmit) {
            returnSubmit = document.querySelector(options.returnSubmitSelector || 'button[name="return_voucher"]');
        }
        if (returnSubmit && options.manageReturnSubmit !== false) {
            returnSubmit.style.display = isView ? 'none' : '';
        }

        var historyWrap = document.getElementById(options.historyWrapId || 'process_history_view_wrap');
        if (historyWrap) {
            historyWrap.style.display = isView ? '' : 'none';
        }

        ['hidden_return_submit', 'hidden_retract_submit'].forEach(function(id) {
            var hiddenSubmit = document.getElementById(id);
            if (hiddenSubmit) {
                hiddenSubmit.style.display = 'none';
            }
        });
    }

    function getProcessHistoryDisplayFromRow(row) {
        if (!row) {
            return '';
        }
        var displayCell = row.querySelector('[data-label="process_history_display"]');
        if (displayCell && String(displayCell.textContent || '').trim() !== '') {
            return displayCell.textContent;
        }
        var rawCell = row.querySelector('[data-label="process_history"]');
        return rawCell ? rawCell.textContent : '';
    }

    function bindVoucherPortalProcessHistory(raw, listId, wrapId) {
        var histList = document.getElementById(listId || 'view_process_history_list');
        var histWrap = document.getElementById(wrapId || 'process_history_view_wrap');
        if (histList) {
            histList.innerHTML = renderProcessHistoryDisplayList(raw);
        }
        if (histWrap) {
            histWrap.style.display = '';
        }
    }

    function bindVoucherPortalProcessHistoryFromRow(row, listId, wrapId) {
        bindVoucherPortalProcessHistory(getProcessHistoryDisplayFromRow(row), listId, wrapId);
    }

    global.formatProcessHistoryDisplayLine = formatProcessHistoryDisplayLine;
    global.renderProcessHistoryDisplayList = renderProcessHistoryDisplayList;
    global.setVoucherPortalViewMode = setVoucherPortalViewMode;
    global.getProcessHistoryDisplayFromRow = getProcessHistoryDisplayFromRow;
    global.bindVoucherPortalProcessHistory = bindVoucherPortalProcessHistory;
    global.bindVoucherPortalProcessHistoryFromRow = bindVoucherPortalProcessHistoryFromRow;
})(window);
