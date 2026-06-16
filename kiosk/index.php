<?php include 'kiosk_header.php'; ?>
<!--=============== MAIN ===============!-->
<div class="main main--dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Track a voucher</h1>
    </header>

    <div class="voucher-card voucher-card--filter kiosk-search-card">
        <p class="kiosk-search-hint">Search by <strong>processing number</strong> (e.g. <code class="kiosk-code">PN-26-01-0001</code>) or <strong>payee name</strong>. Hyphens are optional.</p>
        <div class="filter-download_container">
            <div class="filter_options_container kiosk-search-row">
                <div class="filter-container filter-container--single kiosk-search-field">
                    <form action="" id="searchForm" class="search-form" autocomplete="off">
                        <label for="filterInput" class="visually-hidden">Processing number or payee</label>
                        <input type="text" id="filterInput" name="query"
                            placeholder="PN-26-01-0001 or payee name">
                    </form>
                </div>
                <button type="submit" form="searchForm" class="btn warning btn-flex btn-nowrap btn-pad voucher-dashboard-btn-primary kiosk-search-btn">
                    <i class="ri-search-line" aria-hidden="true"></i>
                    <span>Search</span>
                </button>
            </div>
        </div>
    </div>

    <section class="voucher-card kiosk-stepper-card stepper_container">
        <div class="head">
            <p class="head_1 kiosk-stepper-title">Disbursement <span>Voucher</span></p>
            <p class="head_2 kiosk-stepper-sub">Workflow progress</p>
        </div>

        <ul class="kiosk-stepper-list">
            <li>
                <i class="icon ri-shield-check-line" aria-hidden="true"></i>
                <div class="progress one p1">
                    <p>1</p><i class="ri-check-line" aria-hidden="true"></i>
                </div>
                <p class="text">Internal Control Unit</p>
            </li>
            <li>
                <i class="icon ri-map-pin-line" aria-hidden="true"></i>
                <div class="progress two p2">
                    <p>2</p><i class="ri-check-line" aria-hidden="true"></i>
                </div>
                <p class="text">Planning Section</p>
            </li>
            <li>
                <i class="icon ri-funds-line" aria-hidden="true"></i>
                <div class="progress three p3">
                    <p>3</p><i class="ri-check-line" aria-hidden="true"></i>
                </div>
                <p class="text">Budget Unit</p>
            </li>
            <li>
                <i class="icon ri-file-chart-line" aria-hidden="true"></i>
                <div class="progress four p4">
                    <p>4</p><i class="ri-check-line" aria-hidden="true"></i>
                </div>
                <p class="text">Accounting Unit</p>
            </li>
            <li>
                <i class="icon ri-briefcase-4-line" aria-hidden="true"></i>
                <div class="progress five p5">
                    <p>5</p><i class="ri-check-line" aria-hidden="true"></i>
                </div>
                <p class="text">Office of the PENRO</p>
            </li>
            <li>
                <i class="icon ri-safe-line" aria-hidden="true"></i>
                <div class="progress six p6">
                    <p>6</p><i class="ri-check-line" aria-hidden="true"></i>
                </div>
                <p class="text">Cashiers Unit</p>
            </li>
            <li>
                <i class="icon ri-money-dollar-circle-line" aria-hidden="true"></i>
                <div class="progress seven p7">
                    <p>7</p><i class="ri-check-line" aria-hidden="true"></i>
                </div>
                <p class="text">Paid</p>
            </li>
        </ul>
    </section>

    <div class="voucher-card voucher-card--table kiosk-results-card">
        <h2 class="voucher-card-title">Voucher details</h2>
        <div class="content-wrapper">
            <table class="table content_table content_table--dashboard" id="kiosk-results-table">
                <thead>
                    <tr>
                        <th>Processing No.</th>
                        <th>DV No.</th>
                        <th>Payee</th>
                        <th>Tracking Status</th>
                        <th>Action</th>
                        <th>Last action</th>
                        <th>Paid on</th>
                        <th class="kiosk-col-select">Select</th>
                    </tr>
                </thead>
                <tbody id="results">
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    (function() {
        const COL_COUNT = 8;
        const QUERY_MAX_LEN = 120;
        const resultsTable = document.getElementById('kiosk-results-table');
        const resultsContainer = document.getElementById('results');
        const filterInput = document.getElementById('filterInput');

        filterInput.setAttribute('maxlength', String(QUERY_MAX_LEN));
        filterInput.setAttribute('pattern', '[A-Za-z0-9\\s\\-.,]+');
        filterInput.setAttribute('title', 'Use letters, numbers, spaces, hyphens, periods, or commas only.');

        function sanitizeQuery(value) {
            return String(value || '')
                .replace(/[\x00-\x1F\x7F]/g, '')
                .replace(/\s+/g, ' ')
                .trim()
                .slice(0, QUERY_MAX_LEN);
        }

        function isValidQuery(value) {
            if (value === '') {
                return false;
            }
            return /^[\p{L}\p{N}\s\-.,]+$/u.test(value);
        }

        function notify(message, type, ms) {
            if (typeof showNotify === 'function') {
                showNotify(message, type || 'info', ms || 2500);
            } else if (typeof functionAlert === 'function') {
                functionAlert(message, '_kiosk noop');
            }
        }

        function escapeHtml(s) {
            if (s == null) return '';
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function maskNamePart(part) {
            const value = String(part || '').trim();
            if (value === '') return '';
            if (value.length === 1) return value;

            let head;
            let tail;
            if (value.length > 4) {
                head = value.substring(0, 2);
                tail = value.charAt(value.length - 1);
            } else {
                head = value.charAt(0);
                tail = value.charAt(value.length - 1);
            }

            const hiddenCount = Math.max(0, value.length - head.length - tail.length);
            return head + '*'.repeat(hiddenCount) + tail;
        }

        function formatPartialPayeeName(name) {
            return String(name || '')
                .trim()
                .split(/\s+/)
                .filter(Boolean)
                .map(maskNamePart)
                .join(' ');
        }

        function formatDatetime(value) {
            if (value == null || String(value).trim() === '') {
                return '—';
            }
            const normalized = String(value).trim().replace(' ', 'T');
            const date = new Date(normalized);
            if (Number.isNaN(date.getTime())) {
                return escapeHtml(String(value));
            }
            return date.toLocaleString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        function setProgress(status) {
            const steps = [
                document.querySelector('.p1'),
                document.querySelector('.p2'),
                document.querySelector('.p3'),
                document.querySelector('.p4'),
                document.querySelector('.p5'),
                document.querySelector('.p6'),
                document.querySelector('.p7')
            ];
            steps.forEach(function(step) {
                if (step) step.classList.remove('active');
            });
            document.querySelectorAll('.kiosk-stepper-list li').forEach(function(li) {
                li.classList.remove('completed');
            });

            const lowerStatus = String(status).toLowerCase();
            let activeIndex = -1;

            if (lowerStatus.includes('checking of requirements')) {
                activeIndex = 0;
            } else if (lowerStatus.includes('icu') || lowerStatus.includes('internal control')) {
                activeIndex = 0;
            } else if (lowerStatus.includes('charging')) {
                activeIndex = 1;
            } else if (lowerStatus.includes('verifying')) {
                activeIndex = 2;
            } else if (lowerStatus.includes('processing the disbursement') || lowerStatus.includes('processing')) {
                activeIndex = 3;
            } else if (lowerStatus.includes('approval')) {
                activeIndex = 4;
            } else if (lowerStatus.includes('preparation')) {
                activeIndex = 5;
            } else if (lowerStatus.includes('paid')) {
                activeIndex = 6;
            }

            if (activeIndex >= 0) {
                for (let i = 0; i <= activeIndex; i++) {
                    if (steps[i]) steps[i].classList.add('active');
                    const stepItem = steps[i] ? steps[i].closest('li') : null;
                    if (stepItem) stepItem.classList.add('completed');
                }
            }
        }

        function resetProgress() {
            document.querySelectorAll('.progress').forEach(function(step) {
                step.classList.remove('active');
            });
            document.querySelectorAll('.kiosk-stepper-list li').forEach(function(li) {
                li.classList.remove('completed');
            });
        }

        function toggleSelectColumn(show) {
            if (resultsTable) {
                resultsTable.classList.toggle('kiosk-results-table--multi', show);
            }
        }

        function markSelectedRow(row) {
            resultsContainer.querySelectorAll('tr.kiosk-row-selected').forEach(function(tr) {
                tr.classList.remove('kiosk-row-selected');
            });
            if (row) row.classList.add('kiosk-row-selected');
        }

        function buildResultRow(item, showSelect) {
            const proc = item.processing_no != null ? String(item.processing_no) : '';
            const dv = item.dv_no != null ? String(item.dv_no) : '—';
            const payee = formatPartialPayeeName(item.payee != null ? String(item.payee) : '');
            const trackingStatus = item.tracking_status != null ? String(item.tracking_status) : '—';
            const voucherStatus = item.voucher_status != null ? String(item.voucher_status) : '—';
            const datetimeAction = formatDatetime(item.datetime_action);
            const datetimePaid = formatDatetime(item.datetime_paid);
            const trackingAttr = escapeHtml(item.tracking_status != null ? String(item.tracking_status) : '');

            const selectCell = showSelect
                ? '<td data-label="Select" class="kiosk-col-select">' +
                '<button type="button" class="btn warning btn-flex btn-nowrap btn-pad kiosk-select-btn" data-tracking-status="' + trackingAttr + '">' +
                '<i class="ri-checkbox-circle-line" aria-hidden="true"></i><span>Select</span></button></td>'
                : '<td data-label="Select" class="kiosk-col-select"></td>';

            return '<tr data-tracking-status="' + trackingAttr + '">' +
                '<td data-label="Processing No.">' + escapeHtml(proc) + '</td>' +
                '<td data-label="DV No.">' + escapeHtml(dv) + '</td>' +
                '<td data-label="Payee">' + escapeHtml(payee) + '</td>' +
                '<td data-label="Tracking Status">' + escapeHtml(trackingStatus) + '</td>' +
                '<td data-label="Action">' + escapeHtml(voucherStatus) + '</td>' +
                '<td data-label="Last action">' + datetimeAction + '</td>' +
                '<td data-label="Paid on">' + datetimePaid + '</td>' +
                selectCell +
                '</tr>';
        }

        resultsContainer.addEventListener('click', function(e) {
            const btn = e.target.closest('.kiosk-select-btn');
            if (!btn) return;
            const row = btn.closest('tr');
            const status = btn.getAttribute('data-tracking-status') || (row ? row.getAttribute('data-tracking-status') : '') || '';
            setProgress(status);
            markSelectedRow(row);
            notify('Showing workflow progress for the selected voucher.', 'info', 2000);
        });

        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const query = sanitizeQuery(document.getElementById('filterInput').value);

            if (query === '') {
                resetProgress();
                toggleSelectColumn(false);
                notify('Enter a processing number (e.g. PN-26-01-0001) or a payee name.', 'warning', 2600);
                resultsContainer.innerHTML = '';
                return;
            }

            if (!isValidQuery(query)) {
                resetProgress();
                toggleSelectColumn(false);
                notify('Search text contains invalid characters. Use letters, numbers, spaces, hyphens, periods, or commas only.', 'warning', 3200);
                resultsContainer.innerHTML = '';
                return;
            }

            document.getElementById('filterInput').value = query;

            resultsContainer.innerHTML = '<tr><td colspan="' + COL_COUNT + '" class="kiosk-table-msg">Searching…</td></tr>';

            fetch('search.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        query: query
                    })
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    resultsContainer.innerHTML = '';
                    resetProgress();

                    if (data && data.error) {
                        toggleSelectColumn(false);
                        notify(data.error, 'error', 3200);
                        resultsContainer.innerHTML = '<tr><td colspan="' + COL_COUNT + '" class="kiosk-table-msg">Could not complete search.</td></tr>';
                        return;
                    }

                    if (!Array.isArray(data) || data.length === 0) {
                        toggleSelectColumn(false);
                        resultsContainer.innerHTML = '<tr><td colspan="' + COL_COUNT + '" class="kiosk-table-msg">No matching voucher found. Try the full processing number (e.g. PN-26-01-0001) or check the payee spelling.</td></tr>';
                        notify('No matching voucher found.', 'warning', 2500);
                        return;
                    }

                    const multipleResults = data.length > 1;
                    toggleSelectColumn(multipleResults);

                    data.forEach(function(item, index) {
                        resultsContainer.insertAdjacentHTML('beforeend', buildResultRow(item, multipleResults));

                        if (!multipleResults && index === 0) {
                            setProgress(item.tracking_status || '');
                        }
                    });

                    if (multipleResults) {
                        notify('Found ' + data.length + ' vouchers. Select one to view its workflow progress.', 'success', 2800);
                    } else {
                        notify('Found 1 matching voucher.', 'success', 2200);
                    }
                })
                .catch(function(error) {
                    toggleSelectColumn(false);
                    resultsContainer.innerHTML = '<tr><td colspan="' + COL_COUNT + '" class="kiosk-table-msg">A network error occurred.</td></tr>';
                    resetProgress();
                    notify('Could not reach the server. Try again in a moment.', 'error', 3200);
                    console.error('Kiosk search error:', error);
                });
        });
    })();
</script>

<script src="<?php echo htmlspecialchars(asset_url('main.js', __DIR__ . '/main.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(asset_url('script.js', __DIR__ . '/script.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>

</html>