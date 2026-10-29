<?php
if (isset($_GET['fetch']) && $_GET['fetch'] === 'voucher_tracking') {
    require __DIR__ . '/../../protected/core/components/security/err_blocker.inc.php';
    require __DIR__ . '/../../protected/dbconnection.inc.php';
    require __DIR__ . '/../../protected/core/components/security/config_session.inc.php';
    require __DIR__ . '/../../protected/core/components/security/router.inc.php';
    require_once __DIR__ . '/../../protected/core/components/security/access_control.inc.php';

    if (!AccessControl::canAccessOverviewReports()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    require_once __DIR__ . '/../../protected/core/components/helpers/voucher_tracking_helper.inc.php';
    require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';

    $voucher_type = isset($_GET['voucher_type']) && $_GET['voucher_type'] !== 'all' ? trim((string) $_GET['voucher_type']) : null;
    $office = isset($_GET['office']) && $_GET['office'] !== 'all' ? trim((string) $_GET['office']) : null;
    $month = isset($_GET['month']) && $_GET['month'] !== 'all' ? (int) $_GET['month'] : null;
    $day = isset($_GET['day']) && $_GET['day'] !== 'all' ? (int) $_GET['day'] : null;
    $yearDate = isset($_GET['yearDate']) && $_GET['yearDate'] !== 'all' ? (int) $_GET['yearDate'] : null;
    $search = trim((string) ($_GET['search'] ?? ''));
    $calculationMode = isset($_GET['calculation']) && (string) $_GET['calculation'] === '1';

    if ($calculationMode && !AccessControl::canAccessCalculationBreakdown()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    try {
        $params = [];
        $searchActive = $search !== '';
        $selectColumns = voucher_tracking_dashboard_fetch_columns_sql();

        if ($calculationMode && $searchActive) {
            // Full-database search for calculation breakdown (ignores type/office/date filters).
            $query = 'SELECT ' . $selectColumns . ' FROM voucher_tracking vt WHERE 1=1' . voucher_tracking_counts_include_sql('vt');
            $query .= voucher_tracking_dashboard_search_sql('vt');
            voucher_tracking_dashboard_bind_search_params($params, $search);
            $query .= ' ORDER BY COALESCE(NULLIF(vt.datetime_status, \'\'), vt.voucher_date, vt.datetime_encoded) DESC';
        } else {
            $query = 'SELECT ' . $selectColumns . ' FROM voucher_tracking vt WHERE 1=1' . voucher_tracking_counts_include_sql('vt');

            if ($voucher_type !== null && $voucher_type !== '') {
                $query .= ' AND vt.voucher_type = :voucher_type';
                $params[':voucher_type'] = $voucher_type;
            }

            if ($office !== null && $office !== '') {
                $query .= ' AND LOWER(TRIM(vt.office_from)) = LOWER(TRIM(:office))';
                $params[':office'] = $office;
            }

            if ($searchActive) {
                $query .= voucher_tracking_dashboard_search_sql('vt');
                voucher_tracking_dashboard_bind_search_params($params, $search);
            }

            if ($month !== null && $day !== null && $yearDate !== null) {
                $query .= ' AND DATE(vt.voucher_date) = :date_filter';
                $params[':date_filter'] = sprintf('%04d-%02d-%02d', $yearDate, $month, $day);
            } elseif ($month !== null && $yearDate !== null) {
                $query .= ' AND MONTH(vt.voucher_date) = :month AND YEAR(vt.voucher_date) = :yearDate';
                $params[':month'] = $month;
                $params[':yearDate'] = $yearDate;
            } elseif ($yearDate !== null) {
                $query .= ' AND YEAR(vt.voucher_date) = :yearDate';
                $params[':yearDate'] = $yearDate;
            }

            $query .= ' ORDER BY vt.voucher_date DESC';
        }

        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $breakdownLimit = null;
        if ($calculationMode) {
            $breakdownLimit = $searchActive
                ? min(count($rows), voucher_tracking_dashboard_calculation_search_limit())
                : voucher_tracking_dashboard_calculation_recent_limit();
        }
        $sectionTiming = voucher_tracking_build_section_timing_report(
            $pdo,
            $rows,
            voucher_tracking_dashboard_breakdown_sections(),
            $breakdownLimit,
            $calculationMode
        );

        $payload = [
            'section_timing' => $sectionTiming,
        ];
        if ($calculationMode) {
            $payload['ok'] = true;
        } else {
            $payload['analytics'] = voucher_tracking_dashboard_build_analytics_stats($rows);
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
        echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
    }
    exit;
}

include('../includes/header.php');
if (!AccessControl::canAccessOverviewReports()) {
    header('Location: ../documents/index.php');
    die();
}
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';
AuditHelper::logPageView('Dashboard');
require_once __DIR__ . '/checklist_config.php';
utilities_signatory_ensure_schema($pdo);
$dashboard_offices = utilities_signatory_fetch_offices($pdo);
$dashboard_breakdown_sections = voucher_tracking_dashboard_breakdown_sections();
$dashboard_timing_section_labels = array_map(
    static fn(string $section): string => voucher_tracking_dashboard_section_label($section),
    $dashboard_breakdown_sections
);
$dashboard_section_timing_blurb = count($dashboard_timing_section_labels) > 1
    ? implode(', ', array_slice($dashboard_timing_section_labels, 0, -1))
        . ', and ' . $dashboard_timing_section_labels[array_key_last($dashboard_timing_section_labels)]
    : (string) ($dashboard_timing_section_labels[0] ?? '');
$dashboard_voucher_types = checklist_types_with_labels();
// Root-relative URL so fetch works regardless of /public vs /vtrams/public path depth
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$fetch_voucher_data_url = 'dashboard.php?fetch=voucher_tracking';
if ($scriptName !== '') {
    $fetch_voucher_data_url = rtrim(dirname($scriptName), '/') . '/dashboard.php?fetch=voucher_tracking';
}
$processing_calendar_api_url = '../../protected/handler/processing_calendar_module/processing_calendar_handler.php';
if ($scriptName !== '') {
    $processing_calendar_api_url = rtrim(dirname($scriptName), '/') . '/../../protected/handler/processing_calendar_module/processing_calendar_handler.php';
}
$can_edit_processing_calendar = AccessControl::hasRole('System Admin');
$processing_calendar_csrf = function_exists('vtrams_csrf_token') ? vtrams_csrf_token() : (string) ($_SESSION['token'] ?? '');
?>
<style>
    .main.main--analytics-dashboard {
        height: calc(100dvh - 4rem);
        max-height: calc(100dvh - 4rem);
        overflow: hidden;
        padding: clamp(0.75rem, 1.6vw, 1.5rem) clamp(0.875rem, 2vw, 1.75rem);
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: clamp(0.75rem, 1.4vw, 1.25rem);
        background: #F5F6F8;
    }

    .analytics-dashboard-shell {
        display: flex;
        flex-direction: column;
        gap: clamp(0.75rem, 1.4vw, 1.25rem);
        flex: 1;
        min-height: 0;
        min-width: 0;
    }

    .analytics-dashboard-toolbar {
        display: flex;
        flex-direction: column;
        gap: clamp(0.75rem, 1.4vw, 1.25rem);
        flex-shrink: 0;
    }

    .analytics-dashboard-viewport {
        flex: 1;
        min-height: 0;
        min-width: 0;
        overflow: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        position: relative;
        isolation: isolate;
        contain: layout paint;
    }

    .analytics-dashboard-viewport.is-loading {
        overflow: hidden;
    }

    .analytics-dashboard-loader {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(245, 246, 248, 0.82);
        backdrop-filter: blur(2px);
        z-index: 5;
        overflow: hidden;
        pointer-events: none;
        transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .analytics-dashboard-viewport.is-loading .analytics-dashboard-loader {
        pointer-events: auto;
    }

    .analytics-dashboard-loader--hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .analytics-dashboard-loader__inner {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 999px;
        background: #fff;
        border: 1px solid #eef2f7;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        color: #64748b;
        font-size: 12px;
        font-weight: 500;
    }

    .analytics-dashboard-loader__spinner {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid rgba(148, 163, 184, 0.35);
        border-top-color: #4A76FF;
        animation: analyticsDashboardSpin 0.75s linear infinite;
        flex-shrink: 0;
    }

    @keyframes analyticsDashboardSpin {
        to {
            transform: rotate(360deg);
        }
    }

    .analytics-dashboard-scale {
        width: 100%;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .analytics-dashboard-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }

    .analytics-dashboard-header__title {
        margin: 0 0 2px;
        font-size: 1.125rem;
        font-weight: 600;
        color: #111827;
        letter-spacing: -0.015em;
    }

    .analytics-dashboard-header__subtitle {
        margin: 0;
        color: #94a3b8;
        font-size: 0.8125rem;
        line-height: 1.4;
        max-width: 640px;
    }

    .analytics-dashboard-refresh {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #fff;
        border: 1px solid #eef2f7;
        color: #64748b;
        font-size: 11px;
        font-weight: 500;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        white-space: nowrap;
    }

    .analytics-dashboard-refresh::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #4A76FF;
        box-shadow: 0 0 0 2px rgba(74, 118, 255, 0.16);
    }

    .analytics-filter-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 10px;
        padding: 12px 14px;
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }

    .analytics-filter-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .analytics-filter-field label {
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: #94a3b8;
    }

    .analytics-filter-field select {
        min-height: 34px;
        padding: 0 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fafbfc;
        color: #334155;
        font-size: 12px;
        font-weight: 500;
        transition: border-color 120ms ease, box-shadow 120ms ease, background 120ms ease;
    }

    .analytics-filter-field select:focus {
        outline: none;
        background: #fff;
        border-color: #b8c9ff;
        box-shadow: 0 0 0 3px rgba(74, 118, 255, 0.12);
    }

    .analytics-filter-field--date-group {
        flex: 1 1 260px;
    }

    .analytics-filter-date-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .analytics-filter-date-row select {
        flex: 1 1 72px;
        min-width: 72px;
    }

    .analytics-filter-apply {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0 14px;
        border: none;
        border-radius: 8px;
        background: linear-gradient(135deg, #4A76FF, #3d67e8);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(74, 118, 255, 0.22);
        transition: transform 120ms ease, box-shadow 120ms ease;
    }

    .analytics-filter-apply:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(74, 118, 255, 0.28);
    }

    .analytics-tabs {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 4px;
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        width: fit-content;
    }

    .analytics-tab-btn {
        border: none;
        background: transparent;
        color: #64748b;
        padding: 8px 12px;
        border-radius: 7px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: background 120ms ease, color 120ms ease, box-shadow 120ms ease;
    }

    .analytics-tab-btn:hover {
        background: #f3f6fb;
        color: #1d4ed8;
    }

    .analytics-tab-btn.is-active {
        background: linear-gradient(135deg, #4A76FF, #3d67e8);
        color: #fff;
        box-shadow: 0 4px 12px rgba(74, 118, 255, 0.24);
    }

    .analytics-tab-panel {
        display: none;
        flex-direction: column;
        gap: 12px;
        min-width: 0;
        max-width: 100%;
    }

    .analytics-tab-panel.is-active {
        display: flex;
    }

    .analytics-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        width: 100%;
        align-items: stretch;
    }

    .analytics-stat-card {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        width: 100%;
        height: 100%;
        min-height: 96px;
        padding: 18px 20px;
        box-sizing: border-box;
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        min-width: 0;
    }

    .analytics-stat-card__body {
        min-width: 0;
        flex: 0 1 auto;
        display: flex;
        flex-direction: column;
        justify-content: center;
        max-width: calc(100% - 54px);
    }

    .analytics-stat-card__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 11px;
        font-size: 20px;
        flex-shrink: 0;
    }

    .analytics-stat-card__icon--blue {
        background: linear-gradient(135deg, rgba(74, 118, 255, 0.16), rgba(74, 118, 255, 0.08));
        color: #4A76FF;
    }

    .analytics-stat-card__icon--green {
        background: linear-gradient(135deg, rgba(5, 150, 105, 0.16), rgba(5, 150, 105, 0.08));
        color: #059669;
    }

    .analytics-stat-card__icon--amber {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.16), rgba(245, 158, 11, 0.08));
        color: #d97706;
    }

    .analytics-stat-card__label {
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        line-height: 1.2;
    }

    .analytics-stat-card__value {
        margin-top: 4px;
        font-size: 1.375rem;
        font-weight: 650;
        color: #0f172a;
        line-height: 1.15;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .analytics-stat-card__value .voucher-amount-stack {
        min-width: 0;
        width: 100%;
        display: flex;
    }

    .analytics-stat-card__value .voucher-amount-row {
        gap: 16px;
    }

    .analytics-stat-card__value .voucher-amount-row__value {
        font-size: 1.125rem;
        font-weight: 650;
    }

    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .analytics-grid--full {
        grid-template-columns: minmax(0, 1fr);
    }

    .analytics-card {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        padding: 14px 16px 16px;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .analytics-card__title {
        margin: 0 0 10px;
        color: #475569;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .analytics-card__note {
        margin: -6px 0 10px;
        color: #94a3b8;
        font-size: 11px;
        line-height: 1.45;
    }

    .formula-sample-eq {
        margin: 0 0 12px;
        padding: 12px 14px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #0f172a;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 13px;
        line-height: 1.5;
        overflow-x: auto;
    }

    .formula-sample-eq span {
        color: #1e3a8a;
    }

    .formula-sample-meta {
        margin: 0 0 12px;
        padding: 10px 12px;
        border-radius: 10px;
        background: #eef2ff;
        color: #1e3a8a;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.5;
    }

    .formula-sample-block {
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid #e2e8f0;
    }

    .formula-sample-block h4 {
        margin: 0 0 8px;
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }

    .formula-sample-table th,
    .formula-sample-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #f1f5f9;
        text-align: left;
        vertical-align: top;
        color: #334155;
    }

    .formula-sample-table th {
        background: #fafbfc;
        color: #94a3b8;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .formula-sample-empty {
        margin: 0;
        color: #94a3b8;
        font-size: 13px;
    }

    .analytics-card--chart {
        min-height: auto;
    }

    .analytics-chart-wrap {
        position: relative;
        height: 220px;
        min-height: 220px;
        max-height: 220px;
    }

    .analytics-card--chart canvas {
        max-height: none;
        height: 100% !important;
    }

    .analytics-card--table {
        height: auto;
        min-height: auto;
    }

    .analytics-table-wrap {
        overflow-x: auto;
        width: 100%;
        border: 1px solid #eef2f7;
        border-radius: 10px;
    }

    #sectionSummaryTable,
    #sectionVoucherTable,
    .formula-sample-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    #sectionSummaryTable th,
    #sectionVoucherTable th,
    #sectionSummaryTable td,
    #sectionVoucherTable td {
        padding: 9px 12px;
        border-bottom: 1px solid #f1f5f9;
        text-align: left;
    }

    #sectionSummaryTable th,
    #sectionVoucherTable th {
        background: #fafbfc;
        color: #94a3b8;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    #sectionSummaryTable tbody tr:hover,
    #sectionVoucherTable tbody tr:hover {
        background: #f8fbff;
    }

    #sectionSummaryTable td,
    #sectionVoucherTable td {
        color: #334155;
    }

    #sectionVoucherTable th.metric-th {
        white-space: normal;
        min-width: 7.5rem;
        line-height: 1.25;
    }

    .metric-th-sub {
        display: block;
        margin-top: 3px;
        font-size: 9px;
        font-weight: 600;
        letter-spacing: 0.01em;
        text-transform: none;
        color: #94a3b8;
    }

    #sectionVoucherTable th.metric-th--tpt,
    #sectionVoucherTable td.metric-th--tpt {
        background: #eef2ff;
        color: #3730a3;
    }

    #sectionVoucherTable th.metric-th--papt,
    #sectionVoucherTable td.metric-th--papt {
        background: #ecfeff;
        color: #0f766e;
    }

    .processing-calendar-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.8fr);
        gap: 12px;
        align-items: start;
    }

    .processing-calendar-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 10px;
    }

    .processing-calendar-nav__title {
        margin: 0;
        font-size: 14px;
        font-weight: 650;
        color: #0f172a;
    }

    .processing-calendar-nav button {
        min-height: 32px;
        padding: 0 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fafbfc;
        color: #334155;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .processing-calendar-weekdays,
    .processing-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 6px;
    }

    .processing-calendar-weekdays span {
        text-align: center;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        padding: 0 0 6px;
    }

    .processing-calendar-day {
        min-height: 78px;
        border: 1px solid #eef2f7;
        border-radius: 10px;
        background: #fafbfc;
        padding: 6px;
        text-align: left;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .processing-calendar-day:hover {
        border-color: #b8c9ff;
        background: #fff;
    }

    .processing-calendar-day.is-outside {
        opacity: 0.35;
        pointer-events: none;
    }

    .processing-calendar-day.is-weekend {
        background: #f8fafc;
        cursor: default;
    }

    .processing-calendar-day.is-selected {
        border-color: #4A76FF;
        box-shadow: 0 0 0 3px rgba(74, 118, 255, 0.12);
        background: #fff;
    }

    .processing-calendar-day__num {
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }

    .processing-calendar-day__mark {
        font-size: 10px;
        line-height: 1.25;
        color: #475569;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        line-clamp: 3;
        -webkit-box-orient: vertical;
    }

    .processing-calendar-day.is-national_holiday .processing-calendar-day__mark { color: #be123c; }
    .processing-calendar-day.is-holiday .processing-calendar-day__mark { color: #b45309; }
    .processing-calendar-day.is-special_order .processing-calendar-day__mark { color: #7c3aed; }
    .processing-calendar-day.is-other .processing-calendar-day__mark { color: #0369a1; }

    .processing-calendar-form label {
        display: block;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: #94a3b8;
        margin: 8px 0 4px;
    }

    .processing-calendar-form input,
    .processing-calendar-form select,
    .processing-calendar-form textarea {
        width: 100%;
        box-sizing: border-box;
        min-height: 34px;
        padding: 6px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fafbfc;
        color: #334155;
        font-size: 12px;
    }

    .processing-calendar-form textarea {
        min-height: 72px;
        resize: vertical;
    }

    .processing-calendar-form__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }

    .processing-calendar-form__actions button {
        min-height: 34px;
        padding: 0 12px;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .processing-calendar-btn-save {
        background: linear-gradient(135deg, #4A76FF, #3d67e8);
        color: #fff;
    }

    .processing-calendar-btn-clear,
    .processing-calendar-btn-delete {
        background: #f1f5f9;
        color: #475569;
    }

    .processing-calendar-btn-delete {
        color: #b91c1c;
    }

    .processing-calendar-status {
        margin: 8px 0 0;
        font-size: 12px;
        color: #64748b;
        min-height: 18px;
    }

    .processing-calendar-status.is-error { color: #b91c1c; }
    .processing-calendar-status.is-ok { color: #047857; }

    .processing-calendar-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
        font-size: 11px;
        color: #64748b;
    }

    .processing-calendar-readonly {
        font-size: 12px;
        color: #64748b;
        margin: 0 0 8px;
    }

    @media (max-width: 1100px) {
        .analytics-stats-grid,
        .analytics-grid,
        .processing-calendar-layout {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 640px) {
        .main.main--analytics-dashboard {
            padding: 1rem;
        }

        .analytics-filter-bar {
            padding: 14px;
        }

        .analytics-filter-apply {
            width: 100%;
        }
    }
</style>

<!--=============== MAIN ===============-->
<div class="main main--analytics-dashboard" id="main">
    <div class="analytics-dashboard-shell">
        <div class="analytics-dashboard-toolbar">
            <header class="analytics-dashboard-header">
                <div>
                    <h1 class="analytics-dashboard-header__title">Voucher Analytics Dashboard</h1>
                    <p class="analytics-dashboard-header__subtitle">Analytics for forwarded, received, and returned vouchers (excludes encoded/pending at encoder only)</p>
                </div>
                <p class="analytics-dashboard-refresh" id="dashboardRefreshStatus">Loading…</p>
            </header>

            <section class="analytics-filter-bar" id="analyticsFilterBar">
        <div class="analytics-filter-field">
            <label for="voucherTypeFilter">Voucher Type</label>
            <select id="voucherTypeFilter">
                <option value="all" selected>All Types</option>
                <?php foreach ($dashboard_voucher_types as $type_value => $type_label): ?>
                    <option value="<?= htmlspecialchars((string)$type_value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$type_label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="analytics-filter-field">
            <label for="officeFilter">Office</label>
            <select id="officeFilter">
                <option value="all" selected>All Offices</option>
                <?php foreach ($dashboard_offices as $office_name): ?>
                    <option value="<?= htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="analytics-filter-field analytics-filter-field--date-group">
            <label>Date (MDY)</label>
            <div class="analytics-filter-date-row">
                <select id="monthFilter" aria-label="Filter month">
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
                <select id="dayFilter" aria-label="Filter day">
                    <option value="all" selected>Day</option>
                    <?php
                    for ($i = 1; $i <= 31; $i++) {
                        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
                        echo "<option value=\"$day\">$day</option>";
                    }
                    ?>
                </select>
                <select id="yearDateFilter" aria-label="Filter year">
                    <option value="all" selected>Year</option>
                    <?php
                    $currentYear = date('Y');
                    for ($year = $currentYear; $year >= 2010; $year--) {
                        echo "<option value=\"$year\">$year</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <button type="button" class="analytics-filter-apply" id="applyFiltersBtn">Apply Filters</button>
            </section>

            <nav class="analytics-tabs" aria-label="Dashboard views">
                <button type="button" class="analytics-tab-btn dashboard-tab-btn is-active" data-dashboard-tab="analytics">Analytics</button>
                <button type="button" class="analytics-tab-btn dashboard-tab-btn" data-dashboard-tab="processing">Processing Times</button>
                <button type="button" class="analytics-tab-btn dashboard-tab-btn" data-dashboard-tab="formula">TPT Formula</button>
                <button type="button" class="analytics-tab-btn dashboard-tab-btn" data-dashboard-tab="calendar">Calendar</button>
            </nav>
        </div>

        <div class="analytics-dashboard-viewport" id="analyticsDashboardViewport">
            <div class="analytics-dashboard-loader analytics-dashboard-loader--hidden" id="analyticsDashboardLoader" role="status" aria-live="polite" aria-hidden="true" aria-label="Loading dashboard data">
                <div class="analytics-dashboard-loader__inner">
                    <div class="analytics-dashboard-loader__spinner" aria-hidden="true"></div>
                    <span>Loading dashboard data…</span>
                </div>
            </div>
            <div class="analytics-dashboard-scale" id="analyticsDashboardScale">
    <div class="analytics-tab-panel dashboard-tab-panel is-active" id="dashboardTabAnalytics">
        <div class="analytics-stats-grid" id="overallTable"></div>

        <section class="analytics-grid">
            <article class="analytics-card analytics-card--chart">
                <h3 class="analytics-card__title">Voucher Type Distribution</h3>
                <div class="analytics-chart-wrap"><canvas id="voucherTypeChart"></canvas></div>
            </article>
            <article class="analytics-card analytics-card--chart">
                <h3 class="analytics-card__title" id="amountChartTitle">Amount by Voucher Type</h3>
                <div class="analytics-chart-wrap"><canvas id="amountChart"></canvas></div>
            </article>
        </section>

        <section class="analytics-grid analytics-grid--full">
            <article class="analytics-card analytics-card--chart">
                <h3 class="analytics-card__title">Monthly Trends</h3>
                <div class="analytics-chart-wrap"><canvas id="monthlyChart"></canvas></div>
            </article>
        </section>
    </div>

    <div class="analytics-tab-panel dashboard-tab-panel" id="dashboardTabProcessing">
        <section class="analytics-grid">
            <article class="analytics-card analytics-card--chart">
                <h3 class="analytics-card__title">Average Processing Time by Section</h3>
                <div class="analytics-chart-wrap"><canvas id="sectionTimeChart"></canvas></div>
            </article>
            <article class="analytics-card analytics-card--table">
                <h3 class="analytics-card__title">Section Processing Time Summary</h3>
                <p class="analytics-card__note"><?= htmlspecialchars($dashboard_section_timing_blurb, ENT_QUOTES, 'UTF-8') ?> only — from when received by the section until successfully forwarded (confirmed by the next section/process), or processed/paid for Cashiers. Includes each re-processing stint after a return (e.g. Accounting returns to Planning and Planning forwards again). Encoding and forwarding by a process-section user before receive is excluded. Processing time counts Monday through Thursday, 7:00 AM to 6:00 PM (until 7:00 PM max). Fridays, Saturdays, Sundays, and days marked on the Calendar tab (holidays, special orders, half days) are excluded or reduced.</p>
                <div class="analytics-table-wrap">
                    <table id="sectionSummaryTable">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th style="text-align:right;">Vouchers</th>
                                <th style="text-align:right;">Avg Time</th>
                                <th style="text-align:right;">Min</th>
                                <th style="text-align:right;">Max</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="analytics-grid analytics-grid--full">
            <article class="analytics-card analytics-card--table">
                <h3 class="analytics-card__title">Per-Voucher Section Processing Breakdown</h3>
                <p class="analytics-card__note" id="dashboardVoucherListMeta">Showing the 15 most recently processed vouchers for the current filters. TPT is total processing time from workflow start; PAPT is post-accounting processing time from Accounting receive (not ICU).</p>
                <div class="analytics-table-wrap">
                    <table id="sectionVoucherTable">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>

    <div class="analytics-tab-panel dashboard-tab-panel" id="dashboardTabFormula">
        <section class="analytics-grid analytics-grid--full">
            <article class="analytics-card">
                <h3 class="analytics-card__title">Total processing time formula</h3>
                <p class="analytics-card__note">Live rule for dashboard TPT and PAPT. Section times use the same work-hour windows but only while that section holds the voucher.</p>
                <?php require __DIR__ . '/partials/total_processing_formula.php'; ?>
            </article>
            <article class="analytics-card">
                <h3 class="analytics-card__title">Worked example (first voucher)</h3>
                <p class="analytics-card__note" id="formulaSampleNote">Uses the first processing number in the current dashboard data as a full sample of TPT and PAPT.</p>
                <div id="formulaSampleBody"><p class="formula-sample-empty">Loading sample calculation…</p></div>
            </article>
        </section>
    </div>

    <div class="analytics-tab-panel dashboard-tab-panel" id="dashboardTabCalendar">
        <section class="analytics-grid analytics-grid--full">
            <article class="analytics-card">
                <h3 class="analytics-card__title">Processing Calendar</h3>
                <p class="analytics-card__note">Office hours are Monday–Thursday, 7:00 AM to 6:00 PM (counted until 7:00 PM). Fridays, Saturdays, and Sundays never count. Nationwide Philippine holidays are pre-loaded (regular holidays plus statutory special non-working days). Add local holidays, special orders, and half days yourself — Chinese New Year, Black Saturday, Christmas Eve, EDSA, and other proclamation extras are not pre-loaded.</p>
                <div class="processing-calendar-layout">
                    <div>
                        <div class="processing-calendar-nav">
                            <button type="button" id="calendarPrevMonth">Previous</button>
                            <h4 class="processing-calendar-nav__title" id="calendarMonthTitle">—</h4>
                            <button type="button" id="calendarNextMonth">Next</button>
                        </div>
                        <div class="processing-calendar-weekdays">
                            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                        </div>
                        <div class="processing-calendar-grid" id="processingCalendarGrid"></div>
                        <div class="processing-calendar-legend">
                            <span>Rose: national holiday</span>
                            <span>Amber: local holiday</span>
                            <span>Violet: special order</span>
                            <span>Blue: other / half day / custom</span>
                            <span>Grey Fri–Sun: excluded automatically</span>
                        </div>
                    </div>
                    <div>
                        <?php if (!$can_edit_processing_calendar): ?>
                            <p class="processing-calendar-readonly">View only. System Admin can add or edit non-working days.</p>
                        <?php endif; ?>
                        <form class="processing-calendar-form" id="processingCalendarForm">
                            <input type="hidden" id="calendarEntryId" value="">
                            <label for="calendarEventDate">Date</label>
                            <input type="date" id="calendarEventDate" required <?= $can_edit_processing_calendar ? '' : 'disabled' ?>>
                            <label for="calendarEventType">Day type</label>
                            <select id="calendarEventType" <?= $can_edit_processing_calendar ? '' : 'disabled' ?>>
                                <option value="holiday">Local holiday</option>
                                <option value="national_holiday">National holiday</option>
                                <option value="special_order">Special order (no work / office event)</option>
                                <option value="other">Other non-working day</option>
                            </select>
                            <label for="calendarTitle">Title</label>
                            <input type="text" id="calendarTitle" maxlength="255" placeholder="e.g. Foundation Day" <?= $can_edit_processing_calendar ? '' : 'disabled' ?>>
                            <label for="calendarCoverage">Coverage</label>
                            <select id="calendarCoverage" <?= $can_edit_processing_calendar ? '' : 'disabled' ?>>
                                <option value="whole_day">Whole day (no work counted)</option>
                                <option value="half_day_am">Half day — morning off (work 12:00 NN–7:00 PM)</option>
                                <option value="half_day_pm">Half day — afternoon off (work 7:00 AM–12:00 NN)</option>
                                <option value="custom">Custom remaining work hours</option>
                            </select>
                            <div id="calendarCustomTimes" style="display:none;">
                                <label for="calendarWorkStart">Work starts</label>
                                <input type="time" id="calendarWorkStart" <?= $can_edit_processing_calendar ? '' : 'disabled' ?>>
                                <label for="calendarWorkEnd">Work ends (max 7:00 PM)</label>
                                <input type="time" id="calendarWorkEnd" <?= $can_edit_processing_calendar ? '' : 'disabled' ?>>
                            </div>
                            <label for="calendarNotes">Notes</label>
                            <textarea id="calendarNotes" maxlength="500" <?= $can_edit_processing_calendar ? '' : 'disabled' ?>></textarea>
                            <?php if ($can_edit_processing_calendar): ?>
                            <div class="processing-calendar-form__actions">
                                <button type="submit" class="processing-calendar-btn-save">Save day</button>
                                <button type="button" class="processing-calendar-btn-clear" id="calendarClearBtn">New entry</button>
                                <button type="button" class="processing-calendar-btn-delete" id="calendarDeleteBtn" hidden>Delete</button>
                            </div>
                            <?php endif; ?>
                            <p class="processing-calendar-status" id="calendarFormStatus"></p>
                        </form>
                    </div>
                </div>
            </article>
        </section>
    </div>
            </div>
        </div>
    </div>
</div>

    <!--=============== JS ===============-->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../protected/js/amount_helper.js"></script>
    <script src="../../protected/js/main.js"></script>
    <script src="../../protected/js/popscript.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const FETCH_VOUCHER_DATA = <?= json_encode($fetch_voucher_data_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
            const CALENDAR_API = <?= json_encode($processing_calendar_api_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
            const CALENDAR_CSRF = <?= json_encode($processing_calendar_csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
            const CAN_EDIT_CALENDAR = <?= $can_edit_processing_calendar ? 'true' : 'false' ?>;
            const voucherTypeFilter = document.getElementById('voucherTypeFilter');
            const officeFilter = document.getElementById('officeFilter');
            const monthFilter = document.getElementById('monthFilter');
            const dayFilter = document.getElementById('dayFilter');
            const yearDateFilter = document.getElementById('yearDateFilter');
            const applyBtn = document.getElementById('applyFiltersBtn');
            const refreshStatusEl = document.getElementById('dashboardRefreshStatus');
            const dashboardLoader = document.getElementById('analyticsDashboardLoader');
            const dashboardViewport = document.getElementById('analyticsDashboardViewport');
            const REFRESH_INTERVAL_MS = 60000;
            const MIN_LOADER_MS = 350;
            let refreshTimer = null;
            let fetchInFlight = false;
            let loaderShownAt = 0;
            let loaderHideTimer = null;
            let lastFormulaSample = null;

            // Chart contexts
            const ctxVoucherType = document.getElementById('voucherTypeChart').getContext('2d');
            const ctxAmount = document.getElementById('amountChart').getContext('2d');
            const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
            const ctxSectionTime = document.getElementById('sectionTimeChart').getContext('2d');

            let voucherTypeChart, amountChart, monthlyChart, sectionTimeChart;

            let layoutSyncTimer = null;

            const chartFont = {
                family: 'inherit',
                size: 10,
                weight: '500'
            };

            const chartLegend = {
                position: 'bottom',
                labels: {
                    boxWidth: 8,
                    boxHeight: 8,
                    padding: 10,
                    font: chartFont,
                    color: '#94a3b8'
                }
            };

            const chartScaleTicks = {
                font: chartFont,
                color: '#94a3b8',
                maxTicksLimit: 7
            };

            function formatPesoAmount(raw) {
                if (raw === '' || raw == null) return '';
                const n = parseFloat(String(raw).replace(/,/g, ''));
                if (!isFinite(n)) return String(raw);
                return n.toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function resizeAllCharts() {
                [voucherTypeChart, amountChart, monthlyChart, sectionTimeChart].forEach(chart => {
                    if (chart && typeof chart.resize === 'function') {
                        chart.resize();
                    }
                });
            }

            function scheduleChartResize() {
                if (layoutSyncTimer) {
                    clearTimeout(layoutSyncTimer);
                }
                layoutSyncTimer = setTimeout(resizeAllCharts, 120);
            }

            window.addEventListener('resize', scheduleChartResize);

            const tabButtons = document.querySelectorAll('.dashboard-tab-btn');
            const tabPanels = {
                analytics: document.getElementById('dashboardTabAnalytics'),
                processing: document.getElementById('dashboardTabProcessing'),
                formula: document.getElementById('dashboardTabFormula'),
                calendar: document.getElementById('dashboardTabCalendar'),
            };
            const staticDashboardTabs = { calendar: true, formula: true };
            const analyticsFilterBar = document.getElementById('analyticsFilterBar');
            let activeDashboardTab = 'analytics';

            tabButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const tab = btn.getAttribute('data-dashboard-tab');
                    activeDashboardTab = tab || 'analytics';
                    tabButtons.forEach(b => b.classList.toggle('is-active', b === btn));
                    Object.entries(tabPanels).forEach(([key, panel]) => {
                        if (panel) {
                            panel.classList.toggle('is-active', key === tab);
                        }
                    });
                    if (analyticsFilterBar) {
                        analyticsFilterBar.style.display = staticDashboardTabs[tab] ? 'none' : '';
                    }
                    if (tab === 'calendar') {
                        hideDashboardLoader(true);
                        stopAutoRefresh();
                        loadProcessingCalendar();
                    } else if (tab === 'formula') {
                        hideDashboardLoader(true);
                        stopAutoRefresh();
                        renderFormulaSample(lastFormulaSample);
                    } else {
                        startAutoRefresh();
                        scheduleChartResize();
                    }
                });
            });

            function showDashboardLoader() {
                if (!dashboardLoader || staticDashboardTabs[activeDashboardTab]) {
                    return;
                }
                if (loaderHideTimer) {
                    clearTimeout(loaderHideTimer);
                    loaderHideTimer = null;
                }
                loaderShownAt = Date.now();
                dashboardLoader.classList.remove('analytics-dashboard-loader--hidden');
                dashboardLoader.setAttribute('aria-hidden', 'false');
                if (dashboardViewport) {
                    dashboardViewport.classList.add('is-loading');
                }
            }

            function hideDashboardLoader(immediate) {
                if (!dashboardLoader) {
                    return;
                }
                const finish = () => {
                    loaderHideTimer = null;
                    dashboardLoader.classList.add('analytics-dashboard-loader--hidden');
                    dashboardLoader.setAttribute('aria-hidden', 'true');
                    if (dashboardViewport) {
                        dashboardViewport.classList.remove('is-loading');
                    }
                };
                if (loaderHideTimer) {
                    clearTimeout(loaderHideTimer);
                    loaderHideTimer = null;
                }
                if (immediate || staticDashboardTabs[activeDashboardTab]) {
                    finish();
                    return;
                }
                const elapsed = loaderShownAt > 0 ? (Date.now() - loaderShownAt) : MIN_LOADER_MS;
                const wait = Math.max(0, MIN_LOADER_MS - elapsed);
                loaderHideTimer = setTimeout(finish, wait);
            }

            function processData(data) {
                const stats = {
                    voucherType: {},
                    amountByType: {},
                    amountByTypeGross: {},
                    amountByTypeNet: {},
                    monthly: {},
                    totalEntries: 0,
                    totalAmount: 0,
                    totalGross: 0,
                    totalNet: 0,
                    hasDistinctNet: false
                };

                data.forEach(row => {
                    const voucherType = row.voucher_type || 'Unknown';
                    stats.voucherType[voucherType] = (stats.voucherType[voucherType] || 0) + 1;

                    const grossSource = normalizeAmountInput(row.amount || '');
                    const netSource = typeof resolveEffectiveAmount === 'function'
                        ? resolveEffectiveAmount(row.amount || '', row.charged_amount || '')
                        : (isNonZeroAmount(row.charged_amount) ? row.charged_amount : (row.amount || ''));
                    const grossAmount = parseFloat(grossSource) || 0;
                    const netAmount = parseFloat(normalizeAmountInput(netSource)) || 0;

                    if (typeof hasDistinctNetAmount === 'function'
                        ? hasDistinctNetAmount(row.amount || '', row.charged_amount || '')
                        : (isNonZeroAmount(row.charged_amount) && !amountsEqualString(row.amount || '', row.charged_amount || ''))) {
                        stats.hasDistinctNet = true;
                    }

                    stats.amountByTypeGross[voucherType] = (stats.amountByTypeGross[voucherType] || 0) + grossAmount;
                    stats.amountByTypeNet[voucherType] = (stats.amountByTypeNet[voucherType] || 0) + netAmount;
                    stats.amountByType[voucherType] = (stats.amountByType[voucherType] || 0) + netAmount;

                    if (row.voucher_date) {
                        const date = new Date(row.voucher_date);
                        const monthYear = date.toLocaleDateString('en-US', {
                            month: 'short',
                            year: 'numeric'
                        });
                        stats.monthly[monthYear] = (stats.monthly[monthYear] || 0) + 1;
                    }

                    stats.totalEntries++;
                    stats.totalGross += grossAmount;
                    stats.totalNet += netAmount;
                    stats.totalAmount += netAmount;
                });

                return stats;
            }

            function updateCharts(stats) {
                const normalizedStats = stats && typeof stats === 'object' ? stats : processData([]);
                const voucherTypeMap = normalizedStats.voucherType || {};
                const amountByTypeMap = normalizedStats.amountByType || {};
                const amountByTypeGrossMap = normalizedStats.amountByTypeGross || amountByTypeMap;
                const amountByTypeNetMap = normalizedStats.amountByTypeNet || amountByTypeMap;
                const monthlyMap = normalizedStats.monthly || {};
                const totalEntries = normalizedStats.totalEntries || 0;
                const totalAmount = normalizedStats.totalAmount || normalizedStats.totalNet || 0;
                const totalGross = normalizedStats.totalGross != null ? normalizedStats.totalGross : totalAmount;
                const totalNet = normalizedStats.totalNet != null ? normalizedStats.totalNet : totalAmount;
                const hasDistinctNet = !!normalizedStats.hasDistinctNet
                    || (typeof hasDistinctNetAmount === 'function'
                        ? hasDistinctNetAmount(String(totalGross), String(totalNet))
                        : totalGross !== totalNet);
                const voucherTypeCount = Object.keys(voucherTypeMap).length;

                // Color palettes aligned with dashboard theme
                const baseColors = [
                    'rgba(74, 118, 255, 0.72)',
                    'rgba(5, 150, 105, 0.72)',
                    'rgba(245, 158, 11, 0.72)',
                    'rgba(139, 92, 246, 0.72)',
                    'rgba(236, 72, 153, 0.72)',
                    'rgba(14, 165, 233, 0.72)',
                    'rgba(107, 114, 128, 0.72)',
                    'rgba(239, 68, 68, 0.72)'
                ];

                function colorsForCount(n) {
                    const out = [];
                    for (let i = 0; i < n; i++) {
                        out.push(baseColors[i % baseColors.length]);
                    }
                    return out;
                }

                // ==== VOUCHER TYPE DOUGHNUT CHART ====
                if (voucherTypeChart) voucherTypeChart.destroy();
                const voucherTypeLabels = Object.keys(voucherTypeMap);
                const voucherTypeData = Object.values(voucherTypeMap);
                voucherTypeChart = new Chart(ctxVoucherType, {
                    type: 'doughnut',
                    data: {
                        labels: voucherTypeLabels,
                        datasets: [{
                            data: voucherTypeData,
                            backgroundColor: colorsForCount(voucherTypeLabels.length),
                            borderWidth: 0,
                            spacing: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 350 },
                        cutout: '62%',
                        plugins: { legend: chartLegend }
                    }
                });

                // ==== AMOUNT BY VOUCHER TYPE BAR CHART ====
                if (amountChart) amountChart.destroy();
                const amountChartTitle = document.getElementById('amountChartTitle');
                if (amountChartTitle) {
                    amountChartTitle.textContent = hasDistinctNet
                        ? 'Amount by Voucher Type (Gross / Net)'
                        : 'Amount by Voucher Type';
                }
                const amountLabelSet = new Set([
                    ...Object.keys(amountByTypeGrossMap),
                    ...Object.keys(amountByTypeNetMap)
                ]);
                const amountLabels = Array.from(amountLabelSet);
                const amountDatasets = hasDistinctNet ? [{
                        label: 'Gross',
                        data: amountLabels.map(label => amountByTypeGrossMap[label] || 0),
                        backgroundColor: 'rgba(5, 150, 105, 0.72)',
                        borderRadius: 6,
                        maxBarThickness: 28
                    },
                    {
                        label: 'Net',
                        data: amountLabels.map(label => amountByTypeNetMap[label] || 0),
                        backgroundColor: 'rgba(37, 99, 235, 0.72)',
                        borderRadius: 6,
                        maxBarThickness: 28
                    }
                ] : [{
                    label: 'Total Amount',
                    data: amountLabels.map(label => amountByTypeNetMap[label] || amountByTypeGrossMap[label] || 0),
                    backgroundColor: 'rgba(74, 118, 255, 0.72)',
                    borderRadius: 6,
                    maxBarThickness: 36
                }];
                amountChart = new Chart(ctxAmount, {
                    type: 'bar',
                    data: {
                        labels: amountLabels,
                        datasets: amountDatasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 350 },
                        plugins: {
                            legend: hasDistinctNet ? chartLegend : { display: false },
                            tooltip: {
                                callbacks: {
                                    label(ctx) {
                                        const value = ctx.parsed.y || 0;
                                        return (ctx.dataset.label || 'Amount') + ': ₱' + formatPesoAmount(value);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: chartScaleTicks,
                                grid: { display: false }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    ...chartScaleTicks,
                                    callback(value) {
                                        return '₱' + formatPesoAmount(value);
                                    }
                                },
                                grid: { color: '#f1f5f9', drawBorder: false }
                            }
                        }
                    }
                });

                // ==== MONTHLY TRENDS LINE CHART ====
                if (monthlyChart) monthlyChart.destroy();
                const monthlyLabels = Object.keys(monthlyMap).sort((a, b) => {
                    const dateA = new Date(a);
                    const dateB = new Date(b);
                    return dateA - dateB;
                });
                const monthlyData = monthlyLabels.map(m => monthlyMap[m] || 0);
                monthlyChart = new Chart(ctxMonthly, {
                    type: 'line',
                    data: {
                        labels: monthlyLabels,
                        datasets: [{
                            label: 'Number of Vouchers',
                            data: monthlyData,
                            borderColor: 'rgba(74, 118, 255, 0.95)',
                            backgroundColor: 'rgba(74, 118, 255, 0.12)',
                            pointBackgroundColor: '#4A76FF',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 0,
                            pointHitRadius: 8,
                            borderWidth: 2,
                            tension: 0.35,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 350 },
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { display: false } },
                        scales: {
                            x: {
                                ticks: chartScaleTicks,
                                grid: { color: '#f1f5f9', drawBorder: false }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: chartScaleTicks,
                                grid: { color: '#f1f5f9', drawBorder: false }
                            }
                        }
                    }
                });


                // ==== STAT CARDS ====
                const overallTableDiv = document.getElementById('overallTable');
                overallTableDiv.innerHTML = '';

                const totalAmountDisplay = typeof buildAmountStackHtml === 'function'
                    ? buildAmountStackHtml(
                        typeof ensureAmountTwoDecimals === 'function' ? ensureAmountTwoDecimals(String(totalGross)) : String(totalGross),
                        hasDistinctNet
                            ? (typeof ensureAmountTwoDecimals === 'function' ? ensureAmountTwoDecimals(String(totalNet)) : String(totalNet))
                            : ''
                    )
                    : ('₱' + formatPesoAmount(totalNet));

                const statCards = [{
                        label: 'Total Vouchers',
                        value: totalEntries,
                        icon: 'ri-file-list-3-line',
                        tone: 'blue'
                    },
                    {
                        label: 'Total Amount',
                        value: totalAmountDisplay,
                        isHtml: typeof buildAmountStackHtml === 'function',
                        icon: 'ri-money-dollar-circle-line',
                        tone: 'green'
                    },
                    {
                        label: 'Voucher Types',
                        value: voucherTypeCount,
                        icon: 'ri-stack-line',
                        tone: 'amber'
                    }
                ];

                statCards.forEach(stat => {
                    const card = document.createElement('article');
                    card.className = 'analytics-stat-card';
                    const valueMarkup = stat.isHtml ? stat.value : stat.value;
                    card.innerHTML = `
                        <div class="analytics-stat-card__icon analytics-stat-card__icon--${stat.tone}">
                            <i class="${stat.icon}" aria-hidden="true"></i>
                        </div>
                        <div class="analytics-stat-card__body">
                            <div class="analytics-stat-card__label">${stat.label}</div>
                            <div class="analytics-stat-card__value">${valueMarkup}</div>
                        </div>`;
                    overallTableDiv.appendChild(card);
                });

                scheduleChartResize();
            }

            function escapeFormulaHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function formulaTableHtml(headers, rows, emptyMessage) {
                if (!rows || rows.length === 0) {
                    return '<p class="formula-sample-empty">' + escapeFormulaHtml(emptyMessage) + '</p>';
                }
                let html = '<div class="analytics-table-wrap"><table class="formula-sample-table"><thead><tr>';
                headers.forEach(header => {
                    html += '<th>' + escapeFormulaHtml(header) + '</th>';
                });
                html += '</tr></thead><tbody>';
                rows.forEach(cells => {
                    html += '<tr>';
                    cells.forEach(cell => {
                        html += '<td>' + escapeFormulaHtml(cell || '—') + '</td>';
                    });
                    html += '</tr>';
                });
                return html + '</tbody></table></div>';
            }

            function formulaMetricHtml(title, metric) {
                if (!metric) {
                    return '';
                }
                const eqName = String(metric.metric || title.split('—')[0] || title).trim();
                const hasWindow = String(metric.start || '').trim() !== '';
                const gross = metric.gross_label || '0 seconds';
                const pause = metric.pause_label || '0 seconds';
                const net = metric.label || '0 seconds';
                let html = '<div class="formula-sample-block"><h4>' + escapeFormulaHtml(title) + '</h4>';
                if (!hasWindow) {
                    html += '<p class="formula-sample-empty">' + escapeFormulaHtml(title)
                        + ' could not be measured for this voucher (no valid start/end).</p></div>';
                    return html;
                }
                html += '<pre class="formula-sample-eq">' + escapeFormulaHtml(eqName)
                    + ' = <span>W(Start, End)</span> − Σ <span>W(Return, NextReceive)</span><br>'
                    + escapeFormulaHtml(eqName) + ' = <span>' + escapeFormulaHtml(gross)
                    + '</span> − <span>' + escapeFormulaHtml(pause)
                    + '</span> = <span>' + escapeFormulaHtml(net) + '</span></pre>';
                html += '<p class="formula-sample-meta">'
                    + escapeFormulaHtml(net) + ' (' + escapeFormulaHtml(metric.start || '—')
                    + ' → ' + escapeFormulaHtml(metric.end || '—') + '). Start: '
                    + escapeFormulaHtml(metric.start_source || '—') + '</p>';
                const pauses = Array.isArray(metric.pause_intervals) ? metric.pause_intervals : [];
                html += '<h4>Return pauses in this window</h4>';
                html += formulaTableHtml(
                    ['Return', 'Next receive', 'Subtracted'],
                    pauses.map(row => [row.start, row.end, row.label]),
                    'No return-wait intervals in this window.'
                );
                const calendarDays = Array.isArray(metric.calendar_days) ? metric.calendar_days : [];
                html += '<h4>Calendar days in this window</h4>';
                html += formulaTableHtml(
                    ['Date', 'Title', 'Type', 'Work hours', 'Effect'],
                    calendarDays.map(day => [day.date, day.title, day.type_label || day.event_type, day.work_label, day.note]),
                    'No dashboard Calendar days fall in this window.'
                );
                html += '</div>';
                return html;
            }

            function renderFormulaSample(sample) {
                const body = document.getElementById('formulaSampleBody');
                const note = document.getElementById('formulaSampleNote');
                if (!body) {
                    return;
                }
                if (!sample || !sample.processing_no) {
                    body.innerHTML = '<p class="formula-sample-empty">No voucher is available yet for a sample calculation.</p>';
                    return;
                }
                if (note) {
                    note.textContent = 'First record in the current data: '
                        + sample.processing_no
                        + (sample.payee ? (' · ' + sample.payee) : '')
                        + '. TPT from workflow start; PAPT from Accounting processing receive (not ICU).';
                }
                const trace = sample.section_trace || {};
                const events = Array.isArray(trace.events) ? trace.events : [];
                const segments = Array.isArray(trace.segments) ? trace.segments : [];
                let html = '<p class="formula-sample-meta">'
                    + escapeFormulaHtml(sample.processing_no || '—')
                    + (sample.dv_no ? (' · DV ' + escapeFormulaHtml(sample.dv_no)) : '')
                    + (sample.voucher_type ? (' · ' + escapeFormulaHtml(sample.voucher_type)) : '')
                    + '</p>';
                html += formulaMetricHtml('TPT — Total Processing Time', sample.tpt);
                html += formulaMetricHtml('PAPT — Post-Accounting Processing Time', sample.papt);
                html += '<div class="formula-sample-block"><h4>Action log</h4>';
                html += formulaTableHtml(
                    ['Date/Time', 'Action', 'Section', 'Notes'],
                    events.map(event => [event.datetime, event.action, event.section, event.note]),
                    'No action log events for this voucher.'
                );
                html += '</div><div class="formula-sample-block"><h4>Section segments (not the TPT/PAPT sum)</h4>';
                html += formulaTableHtml(
                    ['Section', 'Start', 'End', 'Duration', 'Start reason', 'End reason'],
                    segments.map(segment => [segment.section, segment.start, segment.end, segment.label, segment.start_reason, segment.end_reason]),
                    'No counted section segments for this voucher.'
                );
                html += '</div>';
                body.innerHTML = html;
            }

            function updateSectionTiming(sectionTiming) {
                const timing = sectionTiming && typeof sectionTiming === 'object' ? sectionTiming : {
                    sections: [],
                    summary: [],
                    by_voucher: []
                };
                const summary = Array.isArray(timing.summary) ? timing.summary : [];
                const sections = Array.isArray(timing.sections) ? timing.sections : [];
                const byVoucherSections = Array.isArray(timing.by_voucher_sections) && timing.by_voucher_sections.length
                    ? timing.by_voucher_sections
                    : sections;
                const byVoucher = Array.isArray(timing.by_voucher) ? timing.by_voucher : [];
                const voucherListMeta = document.getElementById('dashboardVoucherListMeta');
                if (voucherListMeta) {
                    const limit = timing.by_voucher_limit || byVoucher.length || 15;
                    voucherListMeta.textContent = byVoucher.length > 0
                        ? `Showing the ${byVoucher.length} most recently processed voucher(s) for the current filters (limit ${limit}; updates automatically).`
                        : 'No per-voucher section timing data for the current filters.';
                }

                if (sectionTimeChart) sectionTimeChart.destroy();
                const sectionLabels = summary.map(row => row.section || 'Unknown');
                const sectionAvgHours = summary.map(row => ((row.avg_seconds || 0) / 3600));
                sectionTimeChart = new Chart(ctxSectionTime, {
                    type: 'bar',
                    data: {
                        labels: sectionLabels,
                        datasets: [{
                            label: 'Avg Hours',
                            data: sectionAvgHours,
                            backgroundColor: 'rgba(74, 118, 255, 0.72)',
                            borderRadius: 6,
                            maxBarThickness: 36
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 350 },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label(ctx) {
                                        const row = summary[ctx.dataIndex];
                                        return row ? `Avg: ${row.avg_label}` : '';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: chartScaleTicks,
                                grid: { display: false }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: chartScaleTicks,
                                grid: { color: '#f1f5f9', drawBorder: false },
                                title: {
                                    display: true,
                                    text: 'Hours',
                                    font: chartFont,
                                    color: '#94a3b8'
                                }
                            }
                        }
                    }
                });

                const summaryBody = document.querySelector('#sectionSummaryTable tbody');
                summaryBody.innerHTML = '';
                if (summary.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="5" style="text-align:center;color:#888;">No section timing data for the current filters.</td>';
                    summaryBody.appendChild(row);
                } else {
                    summary.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${row.section || '-'}</td>
                            <td style="text-align:right;">${row.count || 0}</td>
                            <td style="text-align:right;">${row.avg_label || '—'}</td>
                            <td style="text-align:right;">${row.min_label || '—'}</td>
                            <td style="text-align:right;">${row.max_label || '—'}</td>`;
                        summaryBody.appendChild(tr);
                    });
                }

                const voucherHead = document.querySelector('#sectionVoucherTable thead');
                const voucherBody = document.querySelector('#sectionVoucherTable tbody');
                voucherHead.innerHTML = '';
                voucherBody.innerHTML = '';

                const headerRow = document.createElement('tr');
                let headerHtml = '<th>Processing No.</th><th>Payee</th><th>DV No.</th>';
                byVoucherSections.forEach(section => {
                    headerHtml += `<th style="text-align:right;">${section}</th>`;
                });
                headerHtml += '<th class="metric-th metric-th--tpt" style="text-align:right;" title="Total Processing Time">TPT<span class="metric-th-sub">Total Processing Time</span></th>';
                headerHtml += '<th class="metric-th metric-th--papt" style="text-align:right;" title="Post-Accounting Processing Time">PAPT<span class="metric-th-sub">Post-Accounting Processing Time</span></th>';
                headerRow.innerHTML = headerHtml;
                voucherHead.appendChild(headerRow);

                if (byVoucher.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td colspan="${5 + byVoucherSections.length}" style="text-align:center;color:#888;">No per-voucher section timing data for the current filters.</td>`;
                    voucherBody.appendChild(row);
                    return;
                }

                byVoucher.forEach(row => {
                    const tr = document.createElement('tr');
                    let html = `<td>${row.processing_no || '-'}</td>
                        <td>${row.payee || '-'}</td>
                        <td>${row.dv_no || '-'}</td>`;
                    const labels = row.sections_label || {};
                    byVoucherSections.forEach(section => {
                        html += `<td style="text-align:right;">${labels[section] || '—'}</td>`;
                    });
                    const tpt = (row.total_processing_time || '').trim();
                    const papt = (row.papt || '').trim();
                    html += `<td class="metric-th--tpt" style="text-align:right;">${tpt !== '' ? tpt : '—'}</td>`;
                    html += `<td class="metric-th--papt" style="text-align:right;">${papt !== '' ? papt : '—'}</td>`;
                    tr.innerHTML = html;
                    voucherBody.appendChild(tr);
                });

                scheduleChartResize();
            }

            function parseFetchResponse(res) {
                const ct = res.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    return res.json();
                }
                return res.text().then(text => {
                    const t = text.trim();
                    if (t.startsWith('{') || t.startsWith('[')) {
                        try {
                            return JSON.parse(t);
                        } catch (e) {
                            console.error('Invalid JSON body:', t.substring(0, 300));
                            throw e;
                        }
                    }
                    console.error('Non-JSON response (HTTP ' + res.status + '):', t.substring(0, 400));
                    throw new Error('Server returned non-JSON response');
                });
            }

            function setRefreshStatus(message) {
                if (refreshStatusEl) {
                    refreshStatusEl.textContent = message;
                }
            }

            function applyVoucherData(data) {
                if (data === null) {
                    updateCharts(null);
                    updateSectionTiming(null);
                    lastFormulaSample = null;
                    renderFormulaSample(null);
                    setRefreshStatus('Update failed · retrying…');
                    return;
                }
                if (data && data.analytics) {
                    updateCharts(data.analytics);
                    updateSectionTiming(data.section_timing || null);
                    lastFormulaSample = (data.section_timing && data.section_timing.formula_sample) || null;
                    renderFormulaSample(lastFormulaSample);
                    setRefreshStatus('Updated ' + new Date().toLocaleTimeString() + ' · auto-refresh every 1 min');
                    return;
                }
                if (Array.isArray(data)) {
                    console.error('Voucher data fetch returned unexpected array payload');
                    updateCharts(null);
                    updateSectionTiming(null);
                    lastFormulaSample = null;
                    renderFormulaSample(null);
                    setRefreshStatus('Update failed · retrying…');
                    return;
                }
                if (data && Array.isArray(data.rows)) {
                    updateCharts(processData(data.rows));
                    updateSectionTiming(data.section_timing || null);
                    lastFormulaSample = (data.section_timing && data.section_timing.formula_sample) || null;
                    renderFormulaSample(lastFormulaSample);
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
                updateCharts(null);
                updateSectionTiming(null);
                lastFormulaSample = null;
                renderFormulaSample(null);
            }

            function buildFetchUrl() {
                const params = new URLSearchParams({
                    voucher_type: voucherTypeFilter.value,
                    office: officeFilter.value,
                    month: monthFilter.value,
                    day: dayFilter.value,
                    yearDate: yearDateFilter.value,
                    _: String(Date.now())
                });
                const q = params.toString();
                const joiner = FETCH_VOUCHER_DATA.includes('?') ? '&' : '?';
                return FETCH_VOUCHER_DATA + (q ? joiner + q : '');
            }

            function fetchFilteredData() {
                if (staticDashboardTabs[activeDashboardTab] || fetchInFlight) {
                    return;
                }
                fetchInFlight = true;
                showDashboardLoader();
                fetch(buildFetchUrl(), {
                        credentials: 'same-origin',
                        cache: 'no-store'
                    })
                    .then(res => {
                        if (!res.ok) {
                            console.error('Voucher data fetch failed HTTP', res.status);
                        }
                        return parseFetchResponse(res);
                    })
                    .then(data => applyVoucherData(data))
                    .catch(err => {
                        console.error('Error fetching voucher data:', err);
                        applyVoucherData(null);
                    })
                    .finally(() => {
                        fetchInFlight = false;
                        hideDashboardLoader(!!staticDashboardTabs[activeDashboardTab]);
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
                if (activeDashboardTab === 'calendar') {
                    loadProcessingCalendar();
                    return;
                }
                if (activeDashboardTab === 'formula') {
                    return;
                }
                fetchFilteredData();
                startAutoRefresh();
            });

            // Initial load (same endpoint as filter, all types)
            fetchFilteredData();
            startAutoRefresh();

            applyBtn.addEventListener('click', () => {
                fetchFilteredData();
            });

            const calendarGrid = document.getElementById('processingCalendarGrid');
            const calendarMonthTitle = document.getElementById('calendarMonthTitle');
            const calendarForm = document.getElementById('processingCalendarForm');
            const calendarStatus = document.getElementById('calendarFormStatus');
            const calendarCustomTimes = document.getElementById('calendarCustomTimes');
            const calendarDeleteBtn = document.getElementById('calendarDeleteBtn');
            let calendarYear = new Date().getFullYear();
            let calendarMonth = new Date().getMonth() + 1;
            let calendarDays = {};
            let calendarLoaded = false;

            function setCalendarStatus(message, kind) {
                if (!calendarStatus) {
                    return;
                }
                calendarStatus.textContent = message || '';
                calendarStatus.classList.toggle('is-error', kind === 'error');
                calendarStatus.classList.toggle('is-ok', kind === 'ok');
            }

            function pad2(n) {
                return String(n).padStart(2, '0');
            }

            function ymd(year, month, day) {
                return year + '-' + pad2(month) + '-' + pad2(day);
            }

            function toggleCustomTimes() {
                const coverage = document.getElementById('calendarCoverage');
                if (calendarCustomTimes) {
                    calendarCustomTimes.style.display = coverage && coverage.value === 'custom' ? 'block' : 'none';
                }
            }

            function clearCalendarForm(keepDate) {
                const keep = keepDate || '';
                document.getElementById('calendarEntryId').value = '';
                document.getElementById('calendarEventDate').value = keep;
                document.getElementById('calendarEventType').value = 'holiday';
                document.getElementById('calendarTitle').value = '';
                document.getElementById('calendarCoverage').value = 'whole_day';
                document.getElementById('calendarWorkStart').value = '07:00';
                document.getElementById('calendarWorkEnd').value = '18:00';
                document.getElementById('calendarNotes').value = '';
                if (calendarDeleteBtn) {
                    calendarDeleteBtn.hidden = true;
                }
                toggleCustomTimes();
                renderCalendarGrid();
            }

            function fillCalendarForm(entry, dateValue) {
                document.getElementById('calendarEventDate').value = dateValue;
                if (!entry) {
                    document.getElementById('calendarEntryId').value = '';
                    document.getElementById('calendarEventType').value = 'holiday';
                    document.getElementById('calendarTitle').value = '';
                    document.getElementById('calendarCoverage').value = 'whole_day';
                    document.getElementById('calendarWorkStart').value = '07:00';
                    document.getElementById('calendarWorkEnd').value = '18:00';
                    document.getElementById('calendarNotes').value = '';
                    if (calendarDeleteBtn) {
                        calendarDeleteBtn.hidden = true;
                    }
                    toggleCustomTimes();
                    renderCalendarGrid();
                    return;
                }
                document.getElementById('calendarEntryId').value = String(entry.id || '');
                document.getElementById('calendarEventType').value = entry.event_type || 'holiday';
                document.getElementById('calendarTitle').value = entry.title || '';
                document.getElementById('calendarCoverage').value = entry.coverage || 'whole_day';
                document.getElementById('calendarWorkStart').value = (entry.work_start || '07:00:00').slice(0, 5);
                document.getElementById('calendarWorkEnd').value = (entry.work_end || '18:00:00').slice(0, 5);
                document.getElementById('calendarNotes').value = entry.notes || '';
                if (calendarDeleteBtn) {
                    calendarDeleteBtn.hidden = !CAN_EDIT_CALENDAR;
                }
                toggleCustomTimes();
                renderCalendarGrid();
            }

            function renderCalendarGrid() {
                if (!calendarGrid || !calendarMonthTitle) {
                    return;
                }
                const monthDate = new Date(calendarYear, calendarMonth - 1, 1);
                calendarMonthTitle.textContent = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                const firstWeekday = (monthDate.getDay() + 6) % 7;
                const daysInMonth = new Date(calendarYear, calendarMonth, 0).getDate();
                const selectedDate = document.getElementById('calendarEventDate').value;
                calendarGrid.innerHTML = '';
                for (let i = 0; i < firstWeekday; i++) {
                    const cell = document.createElement('div');
                    cell.className = 'processing-calendar-day is-outside';
                    calendarGrid.appendChild(cell);
                }
                for (let day = 1; day <= daysInMonth; day++) {
                    const dateValue = ymd(calendarYear, calendarMonth, day);
                    const weekday = new Date(calendarYear, calendarMonth - 1, day).getDay();
                    const isWeekend = weekday === 0 || weekday === 5 || weekday === 6;
                    const entry = calendarDays[dateValue];
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'processing-calendar-day';
                    if (isWeekend) {
                        btn.classList.add('is-weekend');
                    }
                    if (entry) {
                        btn.classList.add('is-' + (entry.event_type || 'other'));
                    }
                    if (selectedDate === dateValue) {
                        btn.classList.add('is-selected');
                    }
                    const mark = entry
                        ? (entry.title || 'Marked') + (entry.work_label ? ' — ' + entry.work_label : '')
                        : (isWeekend ? 'Excluded' : '');
                    btn.innerHTML = '<span class="processing-calendar-day__num">' + day + '</span>'
                        + (mark ? '<span class="processing-calendar-day__mark">' + mark + '</span>' : '');
                    btn.addEventListener('click', () => {
                        fillCalendarForm(entry || null, dateValue);
                        setCalendarStatus('');
                    });
                    calendarGrid.appendChild(btn);
                }
            }

            function loadProcessingCalendar() {
                const params = new URLSearchParams({
                    year: String(calendarYear),
                    month: String(calendarMonth),
                    _: String(Date.now())
                });
                fetch(CALENDAR_API + '?' + params.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store'
                })
                    .then(res => parseFetchResponse(res))
                    .then(data => {
                        if (!data || data.ok !== true) {
                            setCalendarStatus((data && data.error) || 'Could not load calendar.', 'error');
                            return;
                        }
                        calendarDays = {};
                        (data.days || []).forEach(row => {
                            if (row && row.event_date) {
                                calendarDays[row.event_date] = row;
                            }
                        });
                        calendarLoaded = true;
                        renderCalendarGrid();
                    })
                    .catch(err => {
                        console.error('Calendar load failed:', err);
                        setCalendarStatus('Could not load calendar.', 'error');
                    });
            }

            document.getElementById('calendarPrevMonth')?.addEventListener('click', () => {
                calendarMonth -= 1;
                if (calendarMonth < 1) {
                    calendarMonth = 12;
                    calendarYear -= 1;
                }
                loadProcessingCalendar();
            });
            document.getElementById('calendarNextMonth')?.addEventListener('click', () => {
                calendarMonth += 1;
                if (calendarMonth > 12) {
                    calendarMonth = 1;
                    calendarYear += 1;
                }
                loadProcessingCalendar();
            });
            document.getElementById('calendarCoverage')?.addEventListener('change', toggleCustomTimes);
            document.getElementById('calendarClearBtn')?.addEventListener('click', () => {
                clearCalendarForm('');
                setCalendarStatus('');
            });
            calendarDeleteBtn?.addEventListener('click', () => {
                const id = document.getElementById('calendarEntryId').value;
                if (!id || !CAN_EDIT_CALENDAR) {
                    return;
                }
                if (!confirm('Remove this calendar day? Processing time will treat it as a regular office day again.')) {
                    return;
                }
                fetch(CALENDAR_API, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CALENDAR_CSRF
                    },
                    body: JSON.stringify({ action: 'delete', id: Number(id), token: CALENDAR_CSRF })
                })
                    .then(res => parseFetchResponse(res))
                    .then(data => {
                        if (!data || data.ok !== true) {
                            setCalendarStatus((data && data.error) || 'Delete failed.', 'error');
                            return;
                        }
                        clearCalendarForm('');
                        setCalendarStatus('Calendar day removed.', 'ok');
                        loadProcessingCalendar();
                    })
                    .catch(err => {
                        console.error(err);
                        setCalendarStatus('Delete failed.', 'error');
                    });
            });
            calendarForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                if (!CAN_EDIT_CALENDAR) {
                    return;
                }
                const payload = {
                    action: 'save',
                    token: CALENDAR_CSRF,
                    id: document.getElementById('calendarEntryId').value,
                    event_date: document.getElementById('calendarEventDate').value,
                    event_type: document.getElementById('calendarEventType').value,
                    title: document.getElementById('calendarTitle').value,
                    coverage: document.getElementById('calendarCoverage').value,
                    work_start: document.getElementById('calendarWorkStart').value,
                    work_end: document.getElementById('calendarWorkEnd').value,
                    notes: document.getElementById('calendarNotes').value
                };
                fetch(CALENDAR_API, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CALENDAR_CSRF
                    },
                    body: JSON.stringify(payload)
                })
                    .then(res => parseFetchResponse(res))
                    .then(data => {
                        if (!data || data.ok !== true) {
                            setCalendarStatus((data && data.error) || 'Save failed.', 'error');
                            return;
                        }
                        setCalendarStatus('Saved. Processing time will use this day on the next calculation.', 'ok');
                        loadProcessingCalendar();
                    })
                    .catch(err => {
                        console.error(err);
                        setCalendarStatus('Save failed.', 'error');
                    });
            });
            toggleCustomTimes();
        });
    </script>



    </body>

    </html>