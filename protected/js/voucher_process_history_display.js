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

    function parseProcessHistoryDisplayRow(rawLine) {
        var line = String(rawLine || '').trim();
        if (line === '') {
            return null;
        }
        if (line.indexOf('|') === -1) {
            return { name: '', action: line, unit: '', office: '', datetime: '' };
        }

        var parts = line.split(/\s*\|\s*/);
        var name = String(parts[0] || '').trim();
        var action = String(parts[1] || '').trim();
        var section = String(parts[2] || '').trim();
        var office = String(parts[3] || '').trim();
        var datetime = String(parts[4] || '').trim();

        if (parts.length === 3) {
            return {
                name: name,
                action: simplifyActionLabel(action),
                unit: section,
                office: '',
                datetime: ''
            };
        }

        var unit = section !== '' ? section : office;
        if (section === '') {
            office = '';
        }

        return {
            name: name,
            action: simplifyActionLabel(action),
            unit: unit,
            office: office,
            datetime: datetime
        };
    }

    function formatProcessHistoryDisplayLine(rawLine) {
        var row = parseProcessHistoryDisplayRow(rawLine);
        if (!row) {
            return '';
        }
        var actionPart = row.action;
        if (row.datetime !== '') {
            actionPart = actionPart + ' on ' + row.datetime;
        }
        if (row.name === '' && row.unit === '' && actionPart === '') {
            return String(rawLine || '').trim();
        }

        return row.name + ' | ' + row.unit + ' | ' + actionPart;
    }

    function historyActionTone(action) {
        var a = String(action || '').toLowerCase();
        if (a.indexOf('forward') === 0) return 'is-forwarded';
        if (a.indexOf('receiv') === 0) return 'is-received';
        if (a.indexOf('process') === 0) return 'is-processed';
        if (a.indexOf('return') === 0) return 'is-returned';
        if (a.indexOf('encod') === 0) return 'is-encoded';
        if (a.indexOf('archiv') === 0 || a.indexOf('paid') !== -1) return 'is-paid';
        return '';
    }

    function formatHistoryDatetime(value) {
        var raw = String(value || '').trim();
        if (raw === '') {
            return '—';
        }
        var parts = raw.split(/\s+/);
        if (parts.length < 2) {
            return escapeHtml(raw);
        }
        return '<span class="status-history-date">' + escapeHtml(parts[0]) + '</span>'
            + '<span class="status-history-time">' + escapeHtml(parts.slice(1).join(' ')) + '</span>';
    }

    function renderProcessHistoryDisplayTable(raw) {
        var normalized = String(raw || '')
            .replace(/\u00A0/g, ' ')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/\\n/g, '\n')
            .trim();

        var rows = normalized === '' ? [] : normalized.split('\n').map(function(line) {
            return parseProcessHistoryDisplayRow(String(line).trim());
        }).filter(function(row) {
            return row !== null;
        });

        var html = '<table class="status-history-table">'
            + '<thead><tr>'
            + '<th class="status-history-col-step">#</th>'
            + '<th>User</th>'
            + '<th>Action</th>'
            + '<th>Unit</th>'
            + '<th>Office</th>'
            + '<th>Date / Time</th>'
            + '</tr></thead><tbody>';

        if (rows.length === 0) {
            html += '<tr class="status-history-empty"><td colspan="6">No process history recorded.</td></tr>';
        } else {
            rows.forEach(function(row, index) {
                var tone = historyActionTone(row.action);
                var latest = index === rows.length - 1 ? ' is-latest' : '';
                html += '<tr class="' + tone + latest + '">'
                    + '<td class="status-history-col-step">' + (index + 1) + '</td>'
                    + '<td>' + escapeHtml(row.name || '—') + '</td>'
                    + '<td><span class="status-history-action">' + escapeHtml(row.action || '—') + '</span></td>'
                    + '<td>' + escapeHtml(row.unit || '—') + '</td>'
                    + '<td>' + escapeHtml(row.office || '—') + '</td>'
                    + '<td class="status-history-col-datetime">' + formatHistoryDatetime(row.datetime) + '</td>'
                    + '</tr>';
            });
        }

        return html + '</tbody></table>';
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
    global.parseProcessHistoryDisplayRow = parseProcessHistoryDisplayRow;
    global.renderProcessHistoryDisplayList = renderProcessHistoryDisplayList;
    global.renderProcessHistoryDisplayTable = renderProcessHistoryDisplayTable;
    global.setVoucherPortalViewMode = setVoucherPortalViewMode;
    global.getProcessHistoryDisplayFromRow = getProcessHistoryDisplayFromRow;
    global.bindVoucherPortalProcessHistory = bindVoucherPortalProcessHistory;
    global.bindVoucherPortalProcessHistoryFromRow = bindVoucherPortalProcessHistoryFromRow;
})(window);
