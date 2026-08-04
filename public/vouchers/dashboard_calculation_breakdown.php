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
<?php require __DIR__ . '/../utilities/partials/list_filter_styles.php'; ?>
<div class="main main--voucher-dashboard util-premium-page calc-page" id="main">
    <header class="voucher-dashboard-header">
        <div class="voucher-dashboard-header__text">
            <h1 class="voucher-dashboard-title">Processing Time Calculation Breakdown</h1>
            <p class="voucher-dashboard-subtitle">How section and total processing times are derived from action logs and tracking data.</p>
        </div>
        <div class="voucher-dashboard-header__actions">
            <p id="dashboardRefreshStatus">Loading…</p>
        </div>
    </header>

    <div class="voucher-card voucher-card--filter">
        <div class="calc-filter-grid">
            <div class="calc-filter-field">
                <label for="voucherTypeFilter">Voucher type</label>
                <select id="voucherTypeFilter">
                    <option value="all" selected>All types</option>
                    <?php foreach ($dashboard_voucher_types as $type_value => $type_label): ?>
                        <option value="<?= htmlspecialchars((string) $type_value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $type_label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="calc-filter-field">
                <label for="officeFilter">Office</label>
                <select id="officeFilter">
                    <option value="all" selected>All offices</option>
                    <?php foreach ($dashboard_offices as $office_name): ?>
                        <option value="<?= htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="calc-filter-field">
                <label>Date (MDY)</label>
                <div style="display:flex;gap:0.375rem;">
                    <select id="monthFilter" style="width:5.5rem;">
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
                    <select id="dayFilter" style="width:4.5rem;">
                        <option value="all" selected>Day</option>
                        <?php
                        for ($i = 1; $i <= 31; $i++) {
                            $day = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                            echo "<option value=\"{$day}\">{$day}</option>";
                        }
                        ?>
                    </select>
                    <select id="yearDateFilter" style="width:5rem;">
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

            <div class="calc-filter-actions">
                <button type="button" class="btn primary" id="applyFiltersBtn">Apply filters</button>
            </div>

            <div class="calc-filter-search">
                <div class="calc-filter-field">
                    <label for="calculationSearchInput">Search database</label>
                    <div class="calc-filter-search-row">
                        <input type="search" id="calculationSearchInput" placeholder="Processing no., payee, DV no…" autocomplete="off">
                        <button type="button" id="calculationSearchBtn" class="btn secondary">Search</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">
            <span class="voucher-card-title__label">
                <i class="ri-time-line ri-icon"></i>
                Calculation breakdown
            </span>
        </h2>
        <div class="content-wrapper content-wrapper--padded">
            <p class="calc-meta">Data sources: <code>voucher_action_logs</code>, <code>voucher_tracking</code>, and user role mapping (<code>user_group</code>).</p>
            <ul id="calculationRulesList" class="calc-rules-list"></ul>
            <div class="calc-detail-block">
                <h4>Voucher calculation detail</h4>
                <p class="calc-meta" id="calculationListMeta">Latest 10 vouchers for the current filters. Use Search database to look up any voucher across the system (ignores filters above).</p>
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
            <div class="calc-detail-block" id="calculationDetailPanel" style="display:none;">
                <h4 id="calculationDetailTitle">Action log trace</h4>
                <p class="calc-meta" id="calculationTotalMeta"></p>
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
                <div class="calc-detail-block">
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
        </div>
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
                if (data && (data.section_timing || Array.isArray(data.rows) || data.ok)) {
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
