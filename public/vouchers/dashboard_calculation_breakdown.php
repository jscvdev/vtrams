<?php
include('../includes/header.php');
if (!AccessControl::canAccessCalculationBreakdown()) {
    header('Location: ../documents/index.php');
    die();
}
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';
AuditHelper::logPageView('Calculation Breakdown');
require_once __DIR__ . '/checklist_config.php';
utilities_signatory_ensure_schema($pdo);
$dashboard_offices = utilities_signatory_fetch_offices($pdo);
$dashboard_voucher_types = checklist_types_with_labels();
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$fetch_voucher_data_url = 'dashboard.php?fetch=voucher_tracking&calculation=1';
if ($scriptName !== '') {
    $fetch_voucher_data_url = rtrim(dirname($scriptName), '/') . '/dashboard.php?fetch=voucher_tracking&calculation=1';
}
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

    .filter_options {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
        background-color: #fff;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        color: rgb(75 85 99 / 0.9);
    }

    .filter_options > div {
        display: flex;
        flex-direction: row;
        font-size: 14px;
        align-items: center;
        gap: 1rem;
    }

    .filter_options select {
        padding: 6px;
        border-radius: 5px;
        border: 1px solid #ccc;
        border-color: rgb(209 213 219 / 1);
    }

    .filter_options input[type="search"],
    .filter_options input[type="text"].calculation-search-input {
        padding: 6px 10px;
        border-radius: 5px;
        border: 1px solid rgb(209 213 219 / 1);
        font-size: 14px;
        box-sizing: border-box;
    }

    .filter_options .filter-search-wrap {
        flex: 1 1 100%;
        width: 100%;
        min-width: 0;
        flex-wrap: wrap;
        gap: 8px;
    }

    .filter_options .filter-search-wrap label {
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .filter_options .filter-search-wrap .calculation-search-input {
        flex: 1 1 200px;
        min-width: 0;
        max-width: 100%;
    }

    .filter_options .filter-search-btn {
        flex: 0 0 auto;
        white-space: nowrap;
        padding: 6px 14px;
        border: 1px solid #0d6efd;
        background-color: #0d6efd;
        color: #fff;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        line-height: 1.2;
        height: 32px;
        align-self: center;
    }

    .filter_options .filter-search-btn:hover {
        background-color: #0b5ed7;
        border-color: #0b5ed7;
    }

    .filter_options button#applyFiltersBtn {
        background-color: #0d6efd;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 5px;
        height: fit-content;
        align-self: flex-end;
    }

    .chart-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        padding: 20px;
    }

    .chart-container.chart-container--table {
        height: auto;
        min-height: auto;
    }

    .section-table-scroll {
        overflow-x: auto;
        width: 100%;
    }

    #calculationBreakdownTable th,
    #calculationBreakdownTable td,
    #calculationEventsTable th,
    #calculationEventsTable td,
    #calculationSegmentsTable th,
    #calculationSegmentsTable td {
        border-bottom: 1px solid #ddd;
        padding: 8px;
        font-size: 12px;
        vertical-align: top;
    }

    #calculationBreakdownTable,
    #calculationEventsTable,
    #calculationSegmentsTable {
        width: 100%;
        border-collapse: collapse;
    }

    #calculationBreakdownTable thead th,
    #calculationEventsTable thead th,
    #calculationSegmentsTable thead th {
        background-color: #f9f9f9;
        color: rgb(75 85 99 / 0.7);
    }

    .calculation-rules-list {
        margin: 0;
        padding-left: 18px;
        color: rgb(75 85 99 / 0.85);
        font-size: 13px;
        line-height: 1.5;
    }

    .calculation-meta {
        margin: 0 0 12px;
        color: rgb(75 85 99 / 0.75);
        font-size: 12px;
    }

    .calculation-detail-block {
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid #eee;
    }

    .calculation-detail-block h4 {
        margin: 0 0 8px;
        color: rgb(75 85 99 / 0.9);
        font-size: 14px;
    }

    .calculation-trace-btn {
        border: 1px solid rgb(209 213 219 / 1);
        background: #fff;
        color: rgb(75 85 99 / 0.9);
        padding: 4px 10px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 12px;
    }

    .calculation-trace-btn:hover {
        background: #f3f4f6;
    }
</style>

<div class="main" id="main">
    <div class="main-content main_dashboard">
        <div style="display:flex; flex-wrap:wrap; align-items:baseline; justify-content:space-between; gap:10px;">
            <div>
                <h1 style="margin-bottom:6px;">Processing Time Calculation Breakdown</h1>
                <p style="color: rgb(75 85 99 / 0.9); margin:0;">How section and total processing times are derived from action logs and tracking data</p>
            </div>
            <p id="dashboardRefreshStatus" style="color: rgb(75 85 99 / 0.75); font-size: 12px; margin: 0;">Loading…</p>
        </div>

        <section class="filter_options">
            <div>
                <label>Voucher Type:</label>
                <select id="voucherTypeFilter">
                    <option value="all" selected>All Types</option>
                    <?php foreach ($dashboard_voucher_types as $type_value => $type_label): ?>
                        <option value="<?= htmlspecialchars((string) $type_value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $type_label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="officeFilter">Office:</label>
                <select id="officeFilter">
                    <option value="all" selected>All Offices</option>
                    <?php foreach ($dashboard_offices as $office_name): ?>
                        <option value="<?= htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Date (MDY):</label>
                <div style="display: flex; gap: 5px;">
                    <select id="monthFilter" style="width: 80px;">
                        <option value="all" selected>Month</option>
                        <option value="01">Jan</option>
                        <option value="02">Feb</option>
                        <option value="03">Mar</option>
                        <option value="04">Apr</option>
                        <option value="05">May</option>
                        <option value="06">Jun</option>
                        <option value="07">Jul</option>
                        <option value="08">Aug</option>
                        <option value="09">Sep</option>
                        <option value="10">Oct</option>
                        <option value="11">Nov</option>
                        <option value="12">Dec</option>
                    </select>
                    <select id="dayFilter" style="width: 70px;">
                        <option value="all" selected>Day</option>
                        <?php
                        for ($i = 1; $i <= 31; $i++) {
                            $day = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                            echo "<option value=\"{$day}\">{$day}</option>";
                        }
                        ?>
                    </select>
                    <select id="yearDateFilter" style="width: 80px;">
                        <option value="all" selected>Year</option>
                        <?php
                        $currentYear = (int) date('Y');
                        for ($year = $currentYear; $year >= 2010; $year--) {
                            echo "<option value=\"{$year}\">{$year}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <button id="applyFiltersBtn">Apply Filters</button>

            <div class="filter-search-wrap">
                <label for="calculationSearchInput">Search database:</label>
                <input type="search" id="calculationSearchInput" class="calculation-search-input" placeholder="Processing no., payee, DV no., ORS no." autocomplete="off">
                <button type="button" id="calculationSearchBtn" class="filter-search-btn">Search</button>
            </div>
        </section>

        <section class="chart-container chart-container--table">
            <p class="calculation-meta">Data sources: <code>voucher_action_logs</code>, <code>voucher_tracking</code>, and user role mapping (<code>user_group</code>).</p>
            <ul id="calculationRulesList" class="calculation-rules-list"></ul>
            <div class="calculation-detail-block">
                <h4>Voucher calculation detail</h4>
                <p class="calculation-meta" id="calculationListMeta">Latest 10 vouchers for the current filters. Use Search database to look up any voucher across the system (ignores filters above).</p>
                <div class="section-table-scroll">
                    <table id="calculationBreakdownTable">
                        <thead>
                            <tr>
                                <th>Processing No.</th>
                                <th>Payee</th>
                                <th>DV No.</th>
                                <th style="text-align:right;">Total</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="calculation-detail-block" id="calculationDetailPanel" style="display:none;">
                <h4 id="calculationDetailTitle">Action log trace</h4>
                <p class="calculation-meta" id="calculationTotalMeta"></p>
                <div class="section-table-scroll">
                    <table id="calculationEventsTable">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Action</th>
                                <th>Section</th>
                                <th>Included?</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="calculation-detail-block">
                    <h4>Counted time segments</h4>
                    <div class="section-table-scroll">
                        <table id="calculationSegmentsTable">
                            <thead>
                                <tr>
                                    <th>Section</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th style="text-align:right;">Duration</th>
                                    <th>Start reason</th>
                                    <th>End reason</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

    <script src="../../protected/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const FETCH_VOUCHER_DATA = <?= json_encode($fetch_voucher_data_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
            const voucherTypeFilter = document.getElementById('voucherTypeFilter');
            const officeFilter = document.getElementById('officeFilter');
            const monthFilter = document.getElementById('monthFilter');
            const dayFilter = document.getElementById('dayFilter');
            const yearDateFilter = document.getElementById('yearDateFilter');
            const searchInput = document.getElementById('calculationSearchInput');
            const searchBtn = document.getElementById('calculationSearchBtn');
            const applyBtn = document.getElementById('applyFiltersBtn');
            const refreshStatusEl = document.getElementById('dashboardRefreshStatus');
            const listMetaEl = document.getElementById('calculationListMeta');
            const REFRESH_INTERVAL_MS = 15000;
            let refreshTimer = null;
            let fetchInFlight = false;
            let selectedCalculationPn = null;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function renderCalculationDetail(voucherRow) {
                const detailPanel = document.getElementById('calculationDetailPanel');
                const detailTitle = document.getElementById('calculationDetailTitle');
                const totalMeta = document.getElementById('calculationTotalMeta');
                const eventsBody = document.querySelector('#calculationEventsTable tbody');
                const segmentsBody = document.querySelector('#calculationSegmentsTable tbody');

                if (!voucherRow || !detailPanel || !eventsBody || !segmentsBody) {
                    return;
                }

                selectedCalculationPn = voucherRow.processing_no || null;
                detailPanel.style.display = 'block';
                detailTitle.textContent = `Action log trace — ${voucherRow.processing_no || '-'}`;

                const calc = voucherRow.calculation || {};
                const total = calc.total || null;
                if (total && total.label) {
                    totalMeta.textContent = `Total processing: ${total.label} (${total.start || '—'} → ${total.end || '—'}). Start: ${total.start_source || '—'}. End: ${total.end_source || '—'}.`;
                } else {
                    totalMeta.textContent = 'Total processing time is shown for paid vouchers only.';
                }

                const trace = calc.section_trace || { events: [], segments: [] };
                const events = Array.isArray(trace.events) ? trace.events : [];
                eventsBody.innerHTML = '';
                if (events.length === 0) {
                    eventsBody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">No action log events for this voucher.</td></tr>';
                } else {
                    events.forEach(event => {
                        const note = String(event.note || '');
                        const included = note.toLowerCase().startsWith('excluded') || note.toLowerCase().startsWith('skipped')
                            ? 'No'
                            : (note.toLowerCase().includes('clock started') || note.toLowerCase().includes('closed') || note.toLowerCase().includes('forward logged') || note.toLowerCase().includes('return logged') ? 'Partial' : '—');
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${escapeHtml(event.datetime || '—')}</td>
                            <td>${escapeHtml(event.action || '—')}</td>
                            <td>${escapeHtml(event.section || '—')}</td>
                            <td>${escapeHtml(included)}</td>
                            <td>${escapeHtml(note || '—')}</td>`;
                        eventsBody.appendChild(tr);
                    });
                }

                const segments = Array.isArray(trace.segments) ? trace.segments : [];
                segmentsBody.innerHTML = '';
                if (segments.length === 0) {
                    segmentsBody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#888;">No counted segments for this voucher under current filters.</td></tr>';
                } else {
                    segments.forEach(segment => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${escapeHtml(segment.section || '—')}</td>
                            <td>${escapeHtml(segment.start || '—')}</td>
                            <td>${escapeHtml(segment.end || '—')}</td>
                            <td style="text-align:right;">${escapeHtml(segment.label || '—')}</td>
                            <td>${escapeHtml(segment.start_reason || '—')}</td>
                            <td>${escapeHtml(segment.end_reason || '—')}</td>`;
                        segmentsBody.appendChild(tr);
                    });
                }
            }

            function updateCalculationBreakdown(sectionTiming) {
                const timing = sectionTiming && typeof sectionTiming === 'object' ? sectionTiming : {};
                const breakdown = timing.calculation_breakdown || {};
                const rules = Array.isArray(breakdown.rules) ? breakdown.rules : [];
                const vouchers = Array.isArray(breakdown.vouchers) ? breakdown.vouchers : [];
                const searchTerm = (searchInput?.value || '').trim();

                if (listMetaEl) {
                    if (searchTerm !== '') {
                        listMetaEl.textContent = vouchers.length > 0
                            ? `Database search: ${vouchers.length} match(es) for "${searchTerm}" (filters ignored). Select a voucher to view the action-log trace.`
                            : `Database search: no vouchers match "${searchTerm}".`;
                    } else {
                        listMetaEl.textContent = vouchers.length > 0
                            ? `Latest ${vouchers.length} voucher(s) for the current filters. Use Search database to look up any voucher system-wide.`
                            : 'No calculation breakdown data for the current filters.';
                    }
                }

                const rulesList = document.getElementById('calculationRulesList');
                if (rulesList) {
                    rulesList.innerHTML = '';
                    rules.forEach(rule => {
                        const li = document.createElement('li');
                        li.textContent = rule;
                        rulesList.appendChild(li);
                    });
                }

                const tbody = document.querySelector('#calculationBreakdownTable tbody');
                if (!tbody) {
                    return;
                }
                tbody.innerHTML = '';

                if (vouchers.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">No calculation breakdown data for the current filters.</td></tr>';
                    const detailPanel = document.getElementById('calculationDetailPanel');
                    if (detailPanel) {
                        detailPanel.style.display = 'none';
                    }
                    return;
                }

                vouchers.forEach(row => {
                    const tr = document.createElement('tr');
                    const pn = row.processing_no || '';
                    if (selectedCalculationPn && pn === selectedCalculationPn) {
                        tr.style.backgroundColor = '#f1f5ff';
                    }
                    tr.innerHTML = `<td>${escapeHtml(pn || '—')}</td>
                        <td>${escapeHtml(row.payee || '—')}</td>
                        <td>${escapeHtml(row.dv_no || '—')}</td>
                        <td style="text-align:right;">${escapeHtml((row.total_processing_time || '').trim() || '—')}</td>
                        <td><button type="button" class="calculation-trace-btn">View trace</button></td>`;
                    tr.querySelector('button')?.addEventListener('click', () => renderCalculationDetail(row));
                    tbody.appendChild(tr);
                });

                const selectedRow = vouchers.find(row => row.processing_no === selectedCalculationPn);
                if (selectedRow) {
                    renderCalculationDetail(selectedRow);
                } else if (vouchers.length > 0 && !selectedCalculationPn) {
                    renderCalculationDetail(vouchers[0]);
                }
            }

            function parseFetchResponse(res) {
                const ct = res.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    return res.json();
                }
                return res.text().then(text => {
                    const t = text.trim();
                    if (t.startsWith('{') || t.startsWith('[')) {
                        return JSON.parse(t);
                    }
                    throw new Error('Server returned non-JSON response');
                });
            }

            function setRefreshStatus(message) {
                if (refreshStatusEl) {
                    refreshStatusEl.textContent = message;
                }
            }

            function applyVoucherData(data) {
                if (Array.isArray(data) || data === null) {
                    console.error('Calculation breakdown fetch failed or returned invalid payload', data);
                    updateCalculationBreakdown(null);
                    setRefreshStatus('Update failed · retrying…');
                    return;
                }
                if (data && Array.isArray(data.rows)) {
                    updateCalculationBreakdown(data.section_timing || null);
                    setRefreshStatus('Updated ' + new Date().toLocaleTimeString() + ' · auto-refresh every 15s');
                    return;
                }
                if (data && typeof data.error === 'string') {
                    console.error('Voucher data API error:', data.detail || data.error);
                    setRefreshStatus('Update failed · retrying…');
                } else {
                    console.error('Invalid data format received:', data);
                    setRefreshStatus('Update failed · retrying…');
                }
                updateCalculationBreakdown(null);
            }

            function buildFetchUrl() {
                const params = new URLSearchParams({
                    voucher_type: voucherTypeFilter.value,
                    office: officeFilter.value,
                    month: monthFilter.value,
                    day: dayFilter.value,
                    yearDate: yearDateFilter.value,
                    search: (searchInput?.value || '').trim(),
                    _: String(Date.now())
                });
                const q = params.toString();
                const joiner = FETCH_VOUCHER_DATA.includes('?') ? '&' : '?';
                return FETCH_VOUCHER_DATA + (q ? joiner + q : '');
            }

            function fetchFilteredData() {
                if (fetchInFlight) {
                    return;
                }
                fetchInFlight = true;
                fetch(buildFetchUrl(), { credentials: 'same-origin', cache: 'no-store' })
                    .then(res => parseFetchResponse(res))
                    .then(data => applyVoucherData(data))
                    .catch(err => {
                        console.error('Error fetching calculation breakdown:', err);
                        applyVoucherData(null);
                    })
                    .finally(() => {
                        fetchInFlight = false;
                    });
            }

            function startAutoRefresh() {
                if (refreshTimer) {
                    clearInterval(refreshTimer);
                }
                refreshTimer = setInterval(fetchFilteredData, REFRESH_INTERVAL_MS);
            }

            function stopAutoRefresh() {
                if (refreshTimer) {
                    clearInterval(refreshTimer);
                    refreshTimer = null;
                }
            }

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopAutoRefresh();
                    return;
                }
                fetchFilteredData();
                startAutoRefresh();
            });

            fetchFilteredData();
            startAutoRefresh();
            applyBtn.addEventListener('click', () => {
                if (searchInput) {
                    searchInput.value = '';
                }
                selectedCalculationPn = null;
                fetchFilteredData();
            });

            function runDatabaseSearch() {
                selectedCalculationPn = null;
                fetchFilteredData();
            }

            if (searchBtn) {
                searchBtn.addEventListener('click', runDatabaseSearch);
            }

            if (searchInput) {
                searchInput.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        if (searchDebounceTimer) {
                            clearTimeout(searchDebounceTimer);
                        }
                        runDatabaseSearch();
                    }
                });
            }
        });
    </script>

</body>
</html>
