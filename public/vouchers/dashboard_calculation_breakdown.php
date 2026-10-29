<?php
include('../includes/header.php');
if (!AccessControl::canAccessCalculationBreakdown()) {
    header('Location: ../documents/index.php');
    die();
}
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_table_filter_helper.inc.php';
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
            <p class="voucher-dashboard-subtitle">Apply the total processing time formula to a voucher’s action log, calendar days, and counted segments.</p>
        </div>
        <div class="voucher-dashboard-header__actions">
            <p id="dashboardRefreshStatus">Loading…</p>
        </div>
    </header>

    <div class="voucher-card">
        <h2 class="voucher-card-title">
            <span class="voucher-card-title__label">
                <i class="ri-functions ri-icon"></i>
                Total processing time formula
            </span>
        </h2>
        <div class="content-wrapper content-wrapper--padded">
            <?php require __DIR__ . '/partials/total_processing_formula.php'; ?>
        </div>
    </div>

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

            <?php voucher_table_filter_render_controls(voucher_table_filter_parse([]), [
                'year_id' => 'yearDateFilter',
                'month_id' => 'monthFilter',
                'day_id' => 'dayFilter',
                'include_sort' => false,
                'visible_labels' => true,
                'field_class' => 'calc-filter-field',
            ]); ?>

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

    <div class="calc-workspace">
        <div class="voucher-card voucher-card--table calc-workspace__list">
            <h2 class="voucher-card-title">
                <span class="voucher-card-title__label">
                    <i class="ri-time-line ri-icon"></i>
                    Vouchers
                </span>
            </h2>
            <div class="content-wrapper content-wrapper--padded">
                <p class="calc-meta">Sources: <code>voucher_action_logs</code>, <code>voucher_tracking</code>, Calendar, <code>user_group</code>.</p>
                <ol id="calculationRulesList" class="calc-rules-list"></ol>
                <p class="calc-meta" id="calculationListMeta">Latest 10 vouchers for the current filters. Search looks up any voucher system-wide.</p>
                <div class="section-table-scroll">
                    <table id="calculationBreakdownTable">
                        <thead>
                            <tr>
                                <th>Processing No.</th>
                                <th>Payee</th>
                                <th>DV No.</th>
                                <th style="text-align:right;" title="Total Processing Time">TPT<br><span style="font-size:10px;font-weight:600;text-transform:none;color:#64748b;">Total Processing Time</span></th>
                                <th style="text-align:right;" title="Post-Accounting Processing Time">PAPT<br><span style="font-size:10px;font-weight:600;text-transform:none;color:#64748b;">Post-Accounting Processing Time</span></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="voucher-card voucher-card--table calc-workspace__detail" id="calculationDetailPanel" style="display:none;">
            <h2 class="voucher-card-title">
                <span class="voucher-card-title__label">
                    <i class="ri-flow-chart ri-icon"></i>
                    <span id="calculationDetailTitle">Applied formula</span>
                </span>
            </h2>
            <div class="content-wrapper content-wrapper--padded">
                <p class="calc-formula-result" id="calculationTotalMeta"></p>
                <p class="calc-formula-result" id="calculationPaptMeta"></p>
                <div class="calc-detail-block" style="margin-top:0;padding-top:0;border-top:0;">
                    <h4>Action log</h4>
                    <div class="section-table-scroll">
                        <table id="calculationEventsTable">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Action</th>
                                    <th>Section</th>
                                    <th>In TPT?</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="calc-detail-block">
                    <h4>Calendar days in W(Start, End)</h4>
                    <div class="section-table-scroll">
                        <table id="calculationCalendarTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Work hours</th>
                                    <th>Effect</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="calc-detail-block">
                    <h4>Section segments (not the TPT sum)</h4>
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
    <script src="<?= htmlspecialchars(voucher_table_filter_script_url(), ENT_QUOTES, 'UTF-8') ?>"></script>
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
            const REFRESH_INTERVAL_MS = 60000;
            let refreshTimer = null;
            let fetchInFlight = false;
            let selectedCalculationPn = null;

            if (typeof bindVoucherTableSequentialFilters === 'function') {
                bindVoucherTableSequentialFilters({
                    yearId: 'yearDateFilter',
                    monthId: 'monthFilter',
                    dayId: 'dayFilter'
                });
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function metricSummaryText(prefix, metric, unpaidFallback) {
                if (!metric || !metric.label) {
                    return unpaidFallback;
                }
                let text = prefix + ': ' + metric.label
                    + ' (' + (metric.start || '—') + ' → ' + (metric.end || '—')
                    + '). Start: ' + (metric.start_source || '—') + '.';
                if (Number(metric.pause_seconds || 0) > 0) {
                    text += ' Clock pauses while returned and waiting to be received.';
                }
                const calendarDays = Array.isArray(metric.calendar_days) ? metric.calendar_days : [];
                const appliedCalendar = calendarDays.filter(day => day && day.applied).length;
                if (appliedCalendar > 0) {
                    text += ' Includes ' + appliedCalendar + ' Calendar tab day(s) in this window.';
                }
                return text;
            }

            function renderCalculationDetail(voucherRow) {
                const detailPanel = document.getElementById('calculationDetailPanel');
                const detailTitle = document.getElementById('calculationDetailTitle');
                const totalMeta = document.getElementById('calculationTotalMeta');
                const paptMeta = document.getElementById('calculationPaptMeta');
                const eventsBody = document.querySelector('#calculationEventsTable tbody');
                const segmentsBody = document.querySelector('#calculationSegmentsTable tbody');

                if (!voucherRow || !detailPanel || !eventsBody || !segmentsBody) {
                    return;
                }

                selectedCalculationPn = voucherRow.processing_no || null;
                detailPanel.style.display = 'block';
                detailTitle.textContent = voucherRow.processing_no || 'Applied formula';

                const calc = voucherRow.calculation || {};
                const total = calc.total || null;
                const papt = calc.papt || null;
                if (totalMeta) {
                    totalMeta.textContent = metricSummaryText(
                        'TPT',
                        total,
                        'TPT is shown for paid vouchers only.'
                    );
                }
                if (paptMeta) {
                    paptMeta.textContent = metricSummaryText(
                        'PAPT',
                        papt && papt.start ? papt : null,
                        'PAPT is shown for paid vouchers after Accounting processing receive (not ICU).'
                    );
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

                const calendarBody = document.querySelector('#calculationCalendarTable tbody');
                const calendarDays = Array.isArray(total && total.calendar_days) ? total.calendar_days : [];
                if (calendarBody) {
                    calendarBody.innerHTML = '';
                    if (calendarDays.length === 0) {
                        calendarBody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">No dashboard Calendar days fall in this voucher\'s processing window.</td></tr>';
                    } else {
                        calendarDays.forEach(day => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `<td>${escapeHtml(day.date || '—')}</td>
                                <td>${escapeHtml(day.title || '—')}</td>
                                <td>${escapeHtml(day.type_label || day.event_type || '—')}</td>
                                <td>${escapeHtml(day.work_label || '—')}</td>
                                <td>${escapeHtml(day.note || '—')}</td>`;
                            calendarBody.appendChild(tr);
                        });
                    }
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
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#888;">No calculation breakdown data for the current filters.</td></tr>';
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
                        <td style="text-align:right;">${escapeHtml((row.papt || '').trim() || '—')}</td>
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
                    setRefreshStatus('Updated ' + new Date().toLocaleTimeString() + ' · auto-refresh every 1 min');
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
