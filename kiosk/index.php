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
            <p class="head_1" style="font-size: 1.35rem; font-style: italic;">Disbursement <span>Voucher</span></p>
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
                        <th>Status</th>
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

        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const query = document.getElementById('filterInput').value.trim();
            const resultsContainer = document.getElementById('results');

            if (query === '') {
                resetProgress();
                notify('Enter a processing number (e.g. PN-26-01-0001) or a payee name.', 'warning', 2600);
                resultsContainer.innerHTML = '';
                return;
            }

            resultsContainer.innerHTML = '<tr><td colspan="4" class="kiosk-table-msg">Searching…</td></tr>';

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
                        notify(data.error, 'error', 3200);
                        resultsContainer.innerHTML = '<tr><td colspan="4" class="kiosk-table-msg">Could not complete search.</td></tr>';
                        return;
                    }

                    if (!Array.isArray(data) || data.length === 0) {
                        resultsContainer.innerHTML = '<tr><td colspan="4" class="kiosk-table-msg">No matching voucher found. Try the full processing number (e.g. PN-26-01-0001) or check the payee spelling.</td></tr>';
                        notify('No matching voucher found.', 'warning', 2500);
                        return;
                    }

                    data.forEach(function(item, index) {
                        const proc = item.processing_no != null ? String(item.processing_no) : '';
                        const dv = item.dv_no != null ? String(item.dv_no) : '—';
                        const payee = item.payee != null ? String(item.payee) : '';
                        const vstat = item.tracking_status != null ?
                            String(item.tracking_status) :
                            (item.voucher_status != null ? String(item.voucher_status) : '');
                        const row = '<tr>' +
                            '<td data-label="processing_no">' + escapeHtml(proc) + '</td>' +
                            '<td data-label="dv_no">' + escapeHtml(dv) + '</td>' +
                            '<td data-label="payee">' + escapeHtml(payee) + '</td>' +
                            '<td data-label="voucher_status">' + escapeHtml(vstat) + '</td>' +
                            '</tr>';
                        resultsContainer.insertAdjacentHTML('beforeend', row);

                        if (index === 0) {
                            // Progress is driven by voucher_tracking.status (tracking_status alias from search.php).
                            const progressStatus = item.tracking_status || item.voucher_status || '';
                            setProgress(progressStatus);
                        }
                    });
                    notify('Found ' + data.length + ' matching voucher' + (data.length > 1 ? 's.' : '.'), 'success', 2200);
                })
                .catch(function(error) {
                    resultsContainer.innerHTML = '<tr><td colspan="4" class="kiosk-table-msg">A network error occurred.</td></tr>';
                    resetProgress();
                    notify('Could not reach the server. Try again in a moment.', 'error', 3200);
                    console.error('Kiosk search error:', error);
                });

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

                if (lowerStatus.includes('charging')) activeIndex = 0;
                else if (lowerStatus.includes('verifying')) activeIndex = 2;
                else if (lowerStatus.includes('planning')) activeIndex = 3;
                else if (lowerStatus.includes('processing')) activeIndex = 3;
                else if (lowerStatus.includes('approval')) activeIndex = 4;
                else if (lowerStatus.includes('preparation')) activeIndex = 5;
                else if (lowerStatus.includes('payment')) activeIndex = 6;

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
        });
    })();
</script>

<script src="main.js"></script>
<script src="script.js"></script>
</body>

</html>