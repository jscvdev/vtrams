<?php
include('../includes/header.php');
if (!AccessControl::canAccessExtended()) {
    header('Location: ../documents/index.php');
    die();
}
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
AuditHelper::logPageView('Performance');
?>
<style>
    .main_dashboard {
        overflow-y: scroll;
    }

    .main-content {
        flex: 1;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        width: 100%;
        height: 100%;
    }

    .dashboard-content {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .dashboard-content>div {
        flex: 1;
        min-width: 300px;
    }

    .chart-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        padding: 20px;
        height: 320px;
        display: flex;
        flex-direction: column;
    }

    .chart-container canvas {
        flex: 1;
        max-height: 260px;
    }

    .chart-container h3 {
        margin-bottom: 10px;
        color: rgb(75 85 99 / 0.9);
        font-size: 16px;
        font-weight: 600;
    }

    .table-container {
        width: 100%;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        padding: 20px;
    }

    .stats-card-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
    }

    .modern-stat-card {
        display: flex;
        padding: 12px 16px;
        border-radius: 10px;
        background-color: #fff;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        min-width: 140px;
        flex: 1;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        font-size: 18px;
    }

    .stat-text {
        display: flex;
        flex-direction: column;
        color: rgb(75 85 99 / 0.9);
    }

    .stat-label {
        font-size: 13px;
        font-weight: 500;
    }

    .stat-value {
        font-size: 20px;
        font-weight: bold;
        color: #000;
        margin-top: 4px;
    }

    .filter_options {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
        background-color: #fff;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        margin-bottom: 10px;
        color: rgb(75 85 99 / 0.9);
    }

    .filter_options>div {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .filter_options select {
        padding: 6px 8px;
        border-radius: 5px;
        border: 1px solid #ddd;
    }

    .filter_options button {
        background-color: #0d6efd;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 5px;
        cursor: pointer;
    }

    .filter_options button.print-btn {
        background-color: #28a745;
    }

    .perf-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .perf-table th,
    .perf-table td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    .perf-table th {
        background-color: #f8f9fa;
        color: rgb(75 85 99 / 0.9);
        font-weight: 600;
    }

    .perf-table tr:hover {
        background-color: #f8f9fa;
    }

    .no-data {
        text-align: center;
        padding: 2rem;
        color: #666;
    }

    .perf-table-pager {
        display: none;
        margin-top: 16px;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px 16px;
        padding: 12px 16px;
        background: linear-gradient(180deg, #fafbfc 0%, #f3f4f6 100%);
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .perf-table-pager__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    }

    .perf-table-pager__btn i {
        font-size: 16px;
        opacity: 0.9;
    }

    .perf-table-pager__btn:hover:not(:disabled) {
        background: #f9fafb;
        border-color: #0d6efd;
        color: #0d6efd;
        box-shadow: 0 2px 6px rgba(13, 110, 253, 0.12);
    }

    .perf-table-pager__btn:focus-visible {
        outline: 2px solid #0d6efd;
        outline-offset: 2px;
    }

    .perf-table-pager__btn:disabled {
        opacity: 0.42;
        cursor: not-allowed;
        box-shadow: none;
    }

    .perf-table-pager__info {
        font-size: 13px;
        font-weight: 500;
        color: rgb(75 85 99 / 0.92);
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.01em;
        padding: 6px 12px;
        background: rgba(255, 255, 255, 0.75);
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        min-width: 0;
        text-align: center;
    }

    .perf-table-pager__info--empty {
        color: #9ca3af;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.06em;
    }

    @media print {

        .sidebar,
        .header,
        .filter_options,
        .print-btn,
        .no-print {
            display: none !important;
        }

        .main {
            margin: 0 !important;
        }

        body {
            background: white !important;
        }

        .print-title {
            font-size: 18px;
            margin-bottom: 16px;
        }
    }
</style>

<div class="main" id="main">
    <div class="main-content main_dashboard">
        <h1>Performance</h1>
        <p style="color: rgb(75 85 99 / 0.9)">Voucher action counts: Forwarded, Processed, Returned, Received, Transmitted.</p>

        <section class="filter_options no-print">
            <div>
                <label>Date:</label>
                <input type="date" id="dateFilter" style="padding:6px;border-radius:5px;border:1px solid #ddd;">
            </div>
            <div>
                <label>Year:</label>
                <select id="yearFilter">
                    <option value="all">All</option>
                    <?php for ($y = date('Y'); $y >= 2020; $y--) echo "<option value=\"$y\">$y</option>"; ?>
                </select>
            </div>
            <div>
                <label>Month:</label>
                <select id="monthFilter">
                    <option value="all">All</option>
                    <?php for ($m = 1; $m <= 12; $m++) echo '<option value="' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . '">' . date('M', mktime(0, 0, 0, $m, 1)) . '</option>'; ?>
                </select>
            </div>
            <button id="applyFiltersBtn">Apply</button>
            <button class="print-btn" id="printDailyBtn">Daily Printout (Taken-Action)</button>
            <div style="display:flex;align-items:center;gap:8px;margin-left:auto;">
                <label for="perfTableSearch" style="font-size:14px;">Table search</label>
                <input type="text" id="perfTableSearch" placeholder="Processing no., payee, action…" style="padding:6px;border-radius:5px;border:1px solid #ddd;min-width:200px;" autocomplete="off">
            </div>
        </section>

        <div class="table-container">
            <div class="stats-card-wrapper" id="statsCards"></div>
        </div>

        <section class="dashboard-content">
            <div class="chart-container">
                <h3>Action Type Distribution</h3>
                <canvas id="actionChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Daily Taken-Action Trend</h3>
                <canvas id="dailyChart"></canvas>
            </div>
        </section>

        <section class="table-container">
            <h3 style="margin-bottom:12px;">Taken-Action</h3>
            <table class="perf-table" id="perfTable">
                <thead>
                    <tr>
                        <th>Processing No</th>
                        <th>DV No</th>
                        <th>Payee</th>
                        <th>Action Type</th>
                        <th>Action By</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div class="no-data" id="noDataMsg" style="display:none;">No data for selected filters.</div>
            <div class="no-print perf-table-pager" id="perfTablePager">
                <button type="button" id="perfTablePrev" class="perf-table-pager__btn" aria-label="Previous page">
                    <i class="ri-arrow-left-s-line" aria-hidden="true"></i><span>Previous</span>
                </button>
                <span id="perfTablePagerInfo" class="perf-table-pager__info"></span>
                <button type="button" id="perfTableNext" class="perf-table-pager__btn" aria-label="Next page">
                    <span>Next</span><i class="ri-arrow-right-s-line" aria-hidden="true"></i>
                </button>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    (function() {
        const base = '../../protected/handler/fetch_handlers/fetch_performance_data.php';
        let actionChart, dailyChart;
        let tablePage = 1;
        let tableTotalPages = 1;
        let tableQ = '';
        let debounceTableSearch = null;
        const perfSearch = document.getElementById('perfTableSearch');

        const actionColors = {
            Forwarded: 'rgba(54, 162, 235, 0.7)',
            Processed: 'rgba(75, 192, 192, 0.7)',
            Returned: 'rgba(255, 99, 132, 0.7)',
            Received: 'rgba(255, 206, 86, 0.7)',
            Transmitted: 'rgba(153, 102, 255, 0.7)',
            Other: 'rgba(199, 199, 199, 0.7)'
        };

        function getParams() {
            const p = {};
            const d = document.getElementById('dateFilter').value;
            const y = document.getElementById('yearFilter').value;
            const m = document.getElementById('monthFilter').value;
            if (d) p.date = d;
            if (y !== 'all') p.year = y;
            if (m !== 'all') p.month = m;
            return new URLSearchParams(p).toString();
        }

        function fetchData(cb) {
            const q = getParams();
            const params = new URLSearchParams(q);
            params.set('table_page', String(tablePage));
            params.set('table_per_page', '50');
            if (tableQ.trim() !== '') params.set('q', tableQ.trim());
            const url = base + '?' + params.toString();
            fetch(url)
                .then(r => r.json().then(payload => ({ ok: r.ok, status: r.status, payload })))
                .then(res => {
                    if (!res.ok) {
                        const msg = (res.payload && res.payload.error) ? res.payload.error : ('Request failed (' + res.status + ')');
                        if (typeof showNotify === 'function') showNotify(msg, 'warning', 2600);
                        throw new Error(msg);
                    }
                    const data = res.payload;
                    if (data.error) throw new Error(data.error);
                    cb(data);
                })
                .catch(err => {
                    console.error(err);
                    cb({
                        overall: {},
                        daily: {},
                        table: [],
                        table_meta: { page: 1, per_page: 50, total: 0, total_pages: 1 },
                        users: []
                    });
                });
        }

        function renderStats(overall) {
            const order = ['Forwarded', 'Processed', 'Returned', 'Received', 'Transmitted', 'Other'];
            const icons = {
                Forwarded: 'ri-send-plane-line',
                Processed: 'ri-checkbox-circle-line',
                Returned: 'ri-arrow-left-line',
                Received: 'ri-inbox-line',
                Transmitted: 'ri-broadcast-line',
                Other: 'ri-more-line'
            };
            const html = order.map(t => {
                const v = overall[t] || 0;
                const c = actionColors[t] || actionColors.Other;
                const iconClass = icons[t] || icons.Other;
                return `<div class="modern-stat-card"><div class="stat-icon" style="background:${c}"><i class="${iconClass}" style="font-size:1.25rem;"></i></div><div class="stat-text"><span class="stat-label">${t}</span><span class="stat-value">${v}</span></div></div>`;
            }).join('');
            document.getElementById('statsCards').innerHTML = html;
        }

        function renderActionChart(overall) {
            const labels = Object.keys(overall).filter(k => overall[k] > 0);
            const data = labels.map(k => overall[k]);
            const colors = labels.map(k => actionColors[k] || actionColors.Other);
            const ctx = document.getElementById('actionChart').getContext('2d');
            if (actionChart) actionChart.destroy();
            actionChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        function renderDailyChart(daily) {
            const dates = Object.keys(daily).sort();
            const types = ['Forwarded', 'Processed', 'Returned', 'Received', 'Transmitted'];
            const datasets = types.map((t, i) => ({
                label: t,
                data: dates.map(d => daily[d][t] || 0),
                borderColor: actionColors[t] || actionColors.Other,
                backgroundColor: (actionColors[t] || actionColors.Other).replace('0.7', '0.2'),
                fill: true,
                tension: 0.3
            }));
            const ctx = document.getElementById('dailyChart').getContext('2d');
            if (dailyChart) dailyChart.destroy();
            dailyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function renderTable(rows, meta) {
            const tbody = document.querySelector('#perfTable tbody');
            const noData = document.getElementById('noDataMsg');
            const pager = document.getElementById('perfTablePager');
            const prev = document.getElementById('perfTablePrev');
            const next = document.getElementById('perfTableNext');
            const pinfo = document.getElementById('perfTablePagerInfo');
            tbody.innerHTML = '';
            const p = (meta && Number(meta.page)) ? Number(meta.page) : 1;
            const per = (meta && Number(meta.per_page)) ? Number(meta.per_page) : 50;
            const tot = (meta && Number(meta.total)) ? Number(meta.total) : 0;
            const tp = Math.max(1, (meta && Number(meta.total_pages)) ? Number(meta.total_pages) : 1);
            tableTotalPages = tp;
            if (rows.length === 0) {
                noData.style.display = 'block';
                if (pager && prev && next && pinfo) {
                    pager.style.display = 'flex';
                    prev.disabled = true;
                    next.disabled = true;
                    pinfo.textContent = 'No data to display';
                    pinfo.classList.add('perf-table-pager__info--empty');
                }
                if (tableQ.trim() !== '' && typeof showNotify === 'function') {
                    showNotify('No matching rows in Taken-Action table for your search.', 'warning', 2200);
                }
                return;
            }
            noData.style.display = 'none';
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${r.processing_no || '-'}</td><td>${r.dv_no || '-'}</td><td>${r.payee || '-'}</td><td>${r.action_type || '-'}</td><td>${r.action_by || '-'}</td><td>${r.datetime_action || '-'}</td>`;
                tbody.appendChild(tr);
            });
            if (pager && prev && next && pinfo) {
                const end = Math.min(p * per, tot);
                pinfo.classList.remove('perf-table-pager__info--empty');
                pinfo.textContent = 'Showing ' + end + ' of ' + tot + ' results';
                pager.style.display = 'flex';
                prev.disabled = p <= 1;
                next.disabled = p >= tp;
            }
        }

        function refresh() {
            tablePage = 1;
            if (perfSearch) tableQ = String(perfSearch.value || '');
            fetchData(data => {
                renderStats(data.overall || {});
                renderActionChart(data.overall || {});
                renderDailyChart(data.daily || {});
                renderTable(data.table || [], data.table_meta || {});
            });
        }

        function refreshTableOnly() {
            fetchData(data => {
                renderTable(data.table || [], data.table_meta || {});
            });
        }

        document.getElementById('applyFiltersBtn').addEventListener('click', refresh);
        document.getElementById('printDailyBtn').addEventListener('click', function() {
            const d = document.getElementById('dateFilter').value;
            if (!d) {
                showNotify('Select a date first for daily printout.', 'warning', 3000);
                return;
            }
            const p = new URLSearchParams({
                date: d,
                full_table: '1'
            });
            fetch(base + '?' + p.toString())
                .then(r => r.json())
                .then(data => {
                    const rows = data.table || [];
                    const overall = data.overall || {};
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
<!DOCTYPE html><html><head><title>Daily Taken-Action Report - ${d}</title>
<style>body{font-family:sans-serif;padding:20px;} table{width:100%;border-collapse:collapse;} th,td{padding:8px;border:1px solid #ddd;} th{background:#f5f5f5;}
.summary{margin-bottom:20px;}</style></head><body>
<h1 class="print-title">Daily Taken-Action Report</h1>
<p><strong>Date:</strong> ${d}</p>
<div class="summary"><strong>Summary:</strong> Forwarded: ${overall.Forwarded||0}, Processed: ${overall.Processed||0}, Returned: ${overall.Returned||0}, Received: ${overall.Received||0}, Transmitted: ${overall.Transmitted||0}</div>
<table><thead><tr><th>Processing No</th><th>DV No</th><th>Payee</th><th>Action Type</th><th>Action By</th><th>Date/Time</th></tr></thead><tbody>
${rows.map(r=>`<tr><td>${r.processing_no||'-'}</td><td>${r.dv_no||'-'}</td><td>${r.payee||'-'}</td><td>${r.action_type||'-'}</td><td>${r.action_by||'-'}</td><td>${r.datetime_action||'-'}</td></tr>`).join('')}
</tbody></table>
<p style="margin-top:20px;font-size:12px;color:#666;">Each voucher counted once. PENRO Disbursement Voucher System - Performance Report</p>
</body></html>`);
                    printWindow.document.close();
                    printWindow.print();
                    printWindow.close();
                });
        });

        if (perfSearch) {
            perfSearch.addEventListener('input', function() {
                clearTimeout(debounceTableSearch);
                debounceTableSearch = setTimeout(function() {
                    tableQ = String(perfSearch.value || '');
                    tablePage = 1;
                    refreshTableOnly();
                }, 300);
            });
        }
        document.getElementById('perfTablePrev').addEventListener('click', function() {
            if (tablePage > 1) {
                tablePage--;
                refreshTableOnly();
            }
        });
        document.getElementById('perfTableNext').addEventListener('click', function() {
            if (tablePage < tableTotalPages) {
                tablePage++;
                refreshTableOnly();
            }
        });

        refresh();
    })();
</script>
</body>

</html>