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
        .no-print,
        .dashboard-content {
            display: none !important;
        }

        .main {
            margin: 0 !important;
        }

        body {
            background: white !important;
        }

        .print-only {
            display: block !important;
        }

        .perf-print-header {
            display: block !important;
        }

        .perf-table th,
        .perf-table td {
            border: none;
            border-bottom: 1px solid #e5e7eb;
            padding: 7px 10px;
            font-size: 11px;
        }

        .perf-table th {
            border-bottom: 2px solid #111827;
            background: transparent !important;
        }
    }

    .print-only,
    .perf-print-header {
        display: none;
    }

    .perf-print-banner {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 18px 20px;
        border: 2px solid #111827;
        border-radius: 10px;
        background: #fff;
        margin-bottom: 14px;
    }

    .perf-print-banner__eyebrow {
        margin: 0 0 4px;
        font-size: 11px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .perf-print-banner h2 {
        margin: 0;
        font-size: 22px;
        line-height: 1.2;
        color: #111827;
    }

    .perf-print-banner__subtitle {
        margin: 6px 0 0;
        font-size: 13px;
        color: #4b5563;
    }

    .perf-print-banner__meta {
        display: grid;
        gap: 8px;
        min-width: 210px;
    }

    .perf-print-banner__meta div {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        font-size: 12px;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 4px;
    }

    .perf-print-banner__meta span {
        color: #6b7280;
    }

    .perf-print-banner__meta strong {
        color: #111827;
        font-weight: 700;
    }

    .perf-print-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .perf-print-summary__item {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 10px 12px;
        background: #f9fafb;
    }

    .perf-print-summary__item span {
        display: block;
        font-size: 11px;
        color: #6b7280;
        margin-bottom: 4px;
    }

    .perf-print-summary__item strong {
        font-size: 18px;
        color: #111827;
    }

    .perf-print-summary__item small {
        display: block;
        font-size: 11px;
        color: #4b5563;
        margin-top: 4px;
    }

    .stat-amount {
        font-size: 12px;
        color: #6b7280;
        margin-top: 2px;
    }

    .perf-total-row td {
        font-weight: 700;
        background: #f9fafb;
        border-top: 2px solid #e5e7eb;
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
            <div>
                <label>Action:</label>
                <select id="actionTypeFilter">
                    <option value="all">All Actions</option>
                    <option value="Forwarded">Forwarded</option>
                    <option value="Processed">Processed</option>
                    <option value="Returned">Returned</option>
                    <option value="Received">Received</option>
                    <option value="Transmitted">Transmitted</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <button id="applyFiltersBtn">Apply</button>
            <button class="print-btn" id="printDailyBtn">Print Report</button>
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

        <div class="perf-print-header print-only" id="perfPrintHeader">
            <div class="perf-print-banner">
                <div>
                    <p class="perf-print-banner__eyebrow">Voucher Tracking</p>
                    <h2>Performance Report</h2>
                    <p class="perf-print-banner__subtitle">Taken-Action Summary</p>
                </div>
                <div class="perf-print-banner__meta">
                    <div><span>Date</span><strong id="perfPrintDate">—</strong></div>
                    <div><span>Action</span><strong id="perfPrintAction">All</strong></div>
                    <div><span>Rows</span><strong id="perfPrintRows">0</strong></div>
                    <div><span>Generated</span><strong id="perfPrintGenerated">—</strong></div>
                </div>
            </div>
            <div class="perf-print-summary" id="perfPrintSummary"></div>
        </div>

        <section class="table-container">
            <h3 style="margin-bottom:12px;">Taken-Action</h3>
            <table class="perf-table" id="perfTable">
                <thead>
                    <tr>
                        <th>Processing No</th>
                        <th>DV No</th>
                        <th>Payee</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Action Type</th>
                        <th>Action By</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot id="perfTableFoot" style="display:none;">
                    <tr class="perf-total-row">
                        <td colspan="3">Total</td>
                        <td style="text-align:right;" id="perfTableTotalAmount">—</td>
                        <td colspan="3" id="perfTableTotalCount"></td>
                    </tr>
                </tfoot>
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
            const a = document.getElementById('actionTypeFilter').value;
            if (d) p.date = d;
            if (y !== 'all') p.year = y;
            if (m !== 'all') p.month = m;
            if (a !== 'all') p.action_type = a;
            return new URLSearchParams(p).toString();
        }

        function formatFilterLabel() {
            const d = document.getElementById('dateFilter').value;
            const y = document.getElementById('yearFilter').value;
            const m = document.getElementById('monthFilter').value;
            const a = document.getElementById('actionTypeFilter').value;
            if (d) return d;
            const parts = [];
            if (m !== 'all') parts.push(m);
            if (y !== 'all') parts.push(y);
            return parts.length ? parts.join('/') : 'All dates';
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
                        overall_amount: {},
                        total_amount: '0.00',
                        daily: {},
                        table: [],
                        table_meta: { page: 1, per_page: 50, total: 0, total_pages: 1 },
                        users: []
                    });
                });
        }

        function renderStats(overall, overallAmount, totalAmount) {
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
                const amt = (overallAmount && overallAmount[t]) ? overallAmount[t] : '';
                const c = actionColors[t] || actionColors.Other;
                const iconClass = icons[t] || icons.Other;
                const amtHtml = amt ? `<span class="stat-amount">₱${amt}</span>` : '';
                return `<div class="modern-stat-card"><div class="stat-icon" style="background:${c}"><i class="${iconClass}" style="font-size:1.25rem;"></i></div><div class="stat-text"><span class="stat-label">${t}</span><span class="stat-value">${v}</span>${amtHtml}</div></div>`;
            }).join('');
            const totalCard = `<div class="modern-stat-card"><div class="stat-icon" style="background:rgba(17,24,39,0.12)"><i class="ri-money-dollar-circle-line" style="font-size:1.25rem;"></i></div><div class="stat-text"><span class="stat-label">Total Amount</span><span class="stat-value">${totalAmount ? '₱' + totalAmount : '—'}</span></div></div>`;
            document.getElementById('statsCards').innerHTML = html + totalCard;
        }

        function renderPrintSummary(overall, overallAmount, totalAmount) {
            const el = document.getElementById('perfPrintSummary');
            if (!el) return;
            const order = ['Forwarded', 'Processed', 'Returned', 'Received', 'Transmitted'];
            const items = order.filter(t => (overall[t] || 0) > 0).map(t => {
                const amt = (overallAmount && overallAmount[t]) ? `₱${overallAmount[t]}` : '';
                return `<div class="perf-print-summary__item"><span>${t}</span><strong>${overall[t] || 0}</strong>${amt ? `<small>${amt}</small>` : ''}</div>`;
            }).join('');
            const totalItem = `<div class="perf-print-summary__item"><span>Total Amount</span><strong>${totalAmount ? '₱' + totalAmount : '—'}</strong></div>`;
            el.innerHTML = items + totalItem;
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

        function renderTable(rows, meta, totalAmount) {
            const tbody = document.querySelector('#perfTable tbody');
            const tfoot = document.getElementById('perfTableFoot');
            const totalAmtEl = document.getElementById('perfTableTotalAmount');
            const totalCountEl = document.getElementById('perfTableTotalCount');
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
                if (tfoot) tfoot.style.display = 'none';
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
                tr.innerHTML = `<td>${r.processing_no || '-'}</td><td>${r.dv_no || '-'}</td><td>${r.payee || '-'}</td><td style="text-align:right;">${r.amount ? '₱' + r.amount : '—'}</td><td>${r.action_type || '-'}</td><td>${r.action_by || '-'}</td><td>${r.datetime_action || '-'}</td>`;
                tbody.appendChild(tr);
            });
            if (tfoot && totalAmtEl && totalCountEl) {
                tfoot.style.display = '';
                totalAmtEl.textContent = totalAmount ? '₱' + totalAmount : '—';
                totalCountEl.textContent = tot + ' voucher' + (tot === 1 ? '' : 's');
            }
            if (pager && prev && next && pinfo) {
                const end = Math.min(p * per, tot);
                pinfo.classList.remove('perf-table-pager__info--empty');
                pinfo.textContent = 'Showing ' + end + ' of ' + tot + ' results';
                pager.style.display = 'flex';
                prev.disabled = p <= 1;
                next.disabled = p >= tp;
            }
        }

        let lastOverall = {};
        let lastOverallAmount = {};
        let lastTotalAmount = '';

        function refresh() {
            tablePage = 1;
            if (perfSearch) tableQ = String(perfSearch.value || '');
            fetchData(data => {
                lastOverall = data.overall || {};
                lastOverallAmount = data.overall_amount || {};
                lastTotalAmount = data.total_amount || '';
                renderStats(lastOverall, lastOverallAmount, lastTotalAmount);
                renderPrintSummary(lastOverall, lastOverallAmount, lastTotalAmount);
                renderActionChart(lastOverall);
                renderDailyChart(data.daily || {});
                renderTable(data.table || [], data.table_meta || {}, lastTotalAmount);
            });
        }

        function refreshTableOnly() {
            fetchData(data => {
                lastTotalAmount = data.total_amount || '';
                renderTable(data.table || [], data.table_meta || {}, lastTotalAmount);
            });
        }

        document.getElementById('applyFiltersBtn').addEventListener('click', refresh);
        document.getElementById('printDailyBtn').addEventListener('click', function() {
            const d = document.getElementById('dateFilter').value;
            const actionSel = document.getElementById('actionTypeFilter');
            const actionLabel = actionSel && actionSel.value !== 'all' ? actionSel.value : 'All';
            const generated = new Date().toLocaleString();
            const p = new URLSearchParams(getParams());
            p.set('full_table', '1');
            fetch(base + '?' + p.toString())
                .then(r => r.json())
                .then(data => {
                    const rows = data.table || [];
                    const overall = data.overall || {};
                    const overallAmount = data.overall_amount || {};
                    const totalAmount = data.total_amount || '';
                    if (rows.length === 0) {
                        if (typeof showNotify === 'function') showNotify('No data to print for selected filters.', 'warning', 3000);
                        return;
                    }
                    const printWindow = window.open('', '_blank');
                    const dateLabel = d || formatFilterLabel();
                    const summaryItems = ['Forwarded', 'Processed', 'Returned', 'Received', 'Transmitted'].filter(t => (overall[t] || 0) > 0).map(t => {
                        const amt = overallAmount[t] ? ` · ₱${overallAmount[t]}` : '';
                        return `<div class="summary-item"><span>${t}</span><strong>${overall[t] || 0}${amt}</strong></div>`;
                    }).join('');
                    printWindow.document.write(`
<!DOCTYPE html><html><head><title>Performance Report</title>
<style>
@page { size: A4 landscape; margin: 12mm; }
body{font-family:"Segoe UI",Arial,sans-serif;padding:20px;color:#111827;}
.banner{display:flex;justify-content:space-between;gap:20px;padding:18px 20px;border:2px solid #111827;border-radius:10px;margin-bottom:14px;}
.banner__eyebrow{margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;}
.banner h1{margin:0;font-size:22px;}
.banner__subtitle{margin:6px 0 0;font-size:13px;color:#4b5563;}
.banner__meta{display:grid;gap:8px;min-width:210px;font-size:12px;}
.banner__meta div{display:flex;justify-content:space-between;border-bottom:1px solid #e5e7eb;padding-bottom:4px;}
.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:16px;}
.summary-item{border:1px solid #d1d5db;border-radius:8px;padding:10px 12px;background:#f9fafb;}
.summary-item span{display:block;font-size:11px;color:#6b7280;margin-bottom:4px;}
.summary-item strong{font-size:18px;}
table{width:100%;border-collapse:collapse;font-size:11px;}
th{border-bottom:2px solid #111827;padding:8px 10px;text-align:left;}
td{border-bottom:1px solid #e5e7eb;padding:7px 10px;}
tfoot td{font-weight:700;background:#f9fafb;border-top:2px solid #e5e7eb;}
.footer{margin-top:16px;font-size:11px;color:#6b7280;text-align:right;}
</style></head><body>
<div class="banner">
  <div>
    <p class="banner__eyebrow">Voucher Tracking</p>
    <h1>Performance Report</h1>
    <p class="banner__subtitle">Taken-Action Summary</p>
  </div>
  <div class="banner__meta">
    <div><span>Date</span><strong>${dateLabel}</strong></div>
    <div><span>Action</span><strong>${actionLabel}</strong></div>
    <div><span>Rows</span><strong>${rows.length}</strong></div>
    <div><span>Generated</span><strong>${generated}</strong></div>
  </div>
</div>
<div class="summary">${summaryItems}<div class="summary-item"><span>Total Amount</span><strong>${totalAmount ? '₱' + totalAmount : '—'}</strong></div></div>
<h3 style="margin:0 0 10px;font-size:15px;">Voucher Listing</h3>
<table><thead><tr><th>Processing No</th><th>DV No</th><th>Payee</th><th style="text-align:right;">Amount</th><th>Action Type</th><th>Action By</th><th>Date/Time</th></tr></thead><tbody>
${rows.map(r=>`<tr><td>${r.processing_no||'-'}</td><td>${r.dv_no||'-'}</td><td>${r.payee||'-'}</td><td style="text-align:right;">${r.amount?'₱'+r.amount:'—'}</td><td>${r.action_type||'-'}</td><td>${r.action_by||'-'}</td><td>${r.datetime_action||'-'}</td></tr>`).join('')}
</tbody><tfoot><tr><td colspan="3">Total</td><td style="text-align:right;">${totalAmount?'₱'+totalAmount:'—'}</td><td colspan="3">${rows.length} voucher${rows.length===1?'':'s'}</td></tr></tfoot></table>
<p class="footer">End of report · ${rows.length} record${rows.length===1?'':'s'} · ${generated}</p>
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