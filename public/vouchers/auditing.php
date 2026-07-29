<?php
include('../includes/header.php');

require_once __DIR__ . '/../../protected/core/components/redirects/redirect_config.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/access_control.inc.php';
if (!AccessControl::hasRole('System Admin')) {
    echo '<script>window.location.href="' . htmlspecialchars(get_redirect_url('voucher'), ENT_QUOTES, 'UTF-8') . '";</script>';
    echo '<p>Redirecting...</p>';
    exit;
}

require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/audit.model.inc.php';
require_once __DIR__ . '/../../protected/core/components/notifications/custom_alert.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/cursor_pagination_helper.php';

AuditHelper::logPageView('Audit Trail');

$auditModel = new AuditModel($pdo);
$auditLogs = [];
$stats = ['total_logs' => 0, 'today_logs' => 0, 'active_users' => 0, 'error_logs' => 0];

try {
    $stats = $auditModel->getAuditStatistics();
} catch (PDOException $e) {
    // audit_logs table may not exist yet
    $auditLogs = [];
}
$auditCtrlUrl = '../../protected/core/components/helpers/audit.ctrl.inc.php';

$rawQ = (string) ($_GET['q'] ?? '');
$q = filterInput($rawQ);
$invalidSearch = (trim($rawQ) !== '' && $q === '');
$rowsPerPage = clamp_int($_GET['rowsPerPage'] ?? null, 1, 50, 50);
$maxBrowse = 100;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, $currentPage);

$baseWhere = "NOT (al.action_type = 'view' AND LOWER(al.description) LIKE '%page%')";
$searchParams = [];
$searchSql = '';
if (!$invalidSearch && $q !== '') {
    $pat = '%' . $q . '%';
    $searchSql = ' AND (
        al.description LIKE :sq0 OR al.action_type LIKE :sq1 OR al.ip_address LIKE :sq2 OR al.request_uri LIKE :sq3
        OR CAST(al.user_id AS CHAR) LIKE :sq4
        OR u.emp_id LIKE :sq5
        OR TRIM(CONCAT(COALESCE(u.emp_fn,\'\'), \' \', COALESCE(u.emp_mi,\'\'), \' \', COALESCE(u.emp_ln,\'\'))) LIKE :sq6
    )';
    for ($i = 0; $i <= 6; $i++) {
        $searchParams[':sq' . $i] = [$pat, PDO::PARAM_STR];
    }
}

try {
    $countSql = "
        SELECT COUNT(*) AS total
        FROM audit_logs al
        LEFT JOIN user_group u ON al.user_id = u.id
        WHERE $baseWhere
        $searchSql
    ";
    $countStmt = $pdo->prepare($countSql);
    foreach ($searchParams as $k => $pair) {
        $countStmt->bindValue($k, $pair[0], $pair[1]);
    }
    $countStmt->execute();
    $dbCount = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $displayTotal = min($dbCount, $maxBrowse);
    $totalPages = $displayTotal > 0 ? (int) ceil($displayTotal / $rowsPerPage) : 1;
    $currentPage = min($currentPage, max(1, $totalPages));
    $offset = ($currentPage - 1) * $rowsPerPage;
    $fetchLimit = $displayTotal > 0 ? min($rowsPerPage, max(0, $maxBrowse - $offset)) : 0;

    $pageSql = "
        SELECT al.*,
            u.emp_id AS username,
            TRIM(CONCAT(COALESCE(u.emp_fn,''), ' ', COALESCE(u.emp_mi,''), ' ', COALESCE(u.emp_ln,''))) AS user_display_name
        FROM audit_logs al
        LEFT JOIN user_group u ON al.user_id = u.id
        WHERE $baseWhere
        $searchSql
        ORDER BY al.created_at DESC
        LIMIT :lim OFFSET :off
    ";
    $pageStmt = $pdo->prepare($pageSql);
    foreach ($searchParams as $k => $pair) {
        $pageStmt->bindValue($k, $pair[0], $pair[1]);
    }
    $pageStmt->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
    $pageStmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $pageStmt->execute();
    $auditLogs = $pageStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalRows = $displayTotal;
} catch (PDOException $e) {
    $auditLogs = [];
    $totalRows = 0;
    $dbCount = 0;
    $displayTotal = 0;
    $totalPages = 1;
}

$qsAudit = $rawQ !== '' ? ('&q=' . rawurlencode($rawQ)) : '';
if (!isset($displayTotal)) {
    $displayTotal = 0;
}
$startEntry = $displayTotal > 0 ? (($currentPage - 1) * $rowsPerPage) + 1 : 0;
$endEntry = $displayTotal > 0 ? min($currentPage * $rowsPerPage, $displayTotal) : 0;

$auditActionPillClass = static function (string $action): string {
    $normalized = strtolower(trim($action));
    if ($normalized === '') {
        return 'audit-pill--neutral';
    }
    if (
        str_contains($normalized, 'error')
        || str_contains($normalized, 'delete')
        || str_contains($normalized, 'clear')
        || str_contains($normalized, 'fail')
    ) {
        return 'audit-pill--danger';
    }
    if (str_contains($normalized, 'login') || str_contains($normalized, 'logout')) {
        return 'audit-pill--info';
    }
    if (str_contains($normalized, 'create') || str_contains($normalized, 'add') || str_contains($normalized, 'insert')) {
        return 'audit-pill--success';
    }
    if (str_contains($normalized, 'update') || str_contains($normalized, 'edit') || str_contains($normalized, 'export')) {
        return 'audit-pill--warning';
    }

    return 'audit-pill--neutral';
};
?>
<?php require __DIR__ . '/../utilities/partials/utilities_premium_base.php'; ?>
<style>
    .audit-page .audit-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        padding: 1rem 1.25rem;
    }

    .audit-page .audit-stat {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px 16px;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .audit-page .audit-stat__label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 8px;
    }

    .audit-page .audit-stat__label i {
        font-size: 14px;
        color: #6366f1;
    }

    .audit-page .audit-stat__value {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: #0f172a;
        line-height: 1.1;
    }

    .audit-page .audit-stat--today {
        background: linear-gradient(180deg, #eff6ff 0%, #fff 100%);
    }

    .audit-page .audit-stat--users {
        background: linear-gradient(180deg, #f0fdf4 0%, #fff 100%);
    }

    .audit-page .audit-stat--errors {
        background: linear-gradient(180deg, #fef2f2 0%, #fff 100%);
    }

    .audit-page .util-header-btn--export {
        background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff;
    }

    .audit-page .util-header-btn--export:hover {
        filter: brightness(1.05);
    }

    .audit-page .util-header-btn--clear30 {
        background: linear-gradient(180deg, #fb923c 0%, #ea580c 100%);
        color: #fff;
    }

    .audit-page .util-header-btn--clear30:hover {
        filter: brightness(1.05);
    }

    .audit-page .util-header-btn--clearall {
        background: linear-gradient(180deg, #ef4444 0%, #dc2626 100%);
        color: #fff;
    }

    .audit-page .util-header-btn--clearall:hover {
        filter: brightness(1.05);
    }

    .audit-page .audit-filter-card {
        padding: 1rem 1.25rem;
    }

    .audit-page .audit-filter-row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 0.75rem 1rem;
    }

    .audit-page .audit-filter-field {
        flex: 1 1 280px;
        min-width: 0;
    }

    .audit-page .audit-filter-field label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 0.375rem;
    }

    .audit-page .audit-filter-field input[type="text"] {
        width: 100%;
        border-radius: 10px;
        border: 1px solid #d4dbe6;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        min-height: 42px;
        box-sizing: border-box;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .audit-page .audit-filter-field input[type="text"]:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }

    .audit-page .audit-filter-hint {
        margin: 0.75rem 0 0;
        font-size: 0.8125rem;
        color: #64748b;
    }

    .audit-page .audit-table-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
        padding: 1rem 1.25rem 0;
        flex-wrap: wrap;
    }

    .audit-page .audit-table-meta {
        margin: 0;
        font-size: 0.8125rem;
        color: #64748b;
    }

    .audit-page .voucher-card--table {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    .audit-page .voucher-card--table .content-wrapper {
        flex: 1;
        min-height: 0;
        overflow: auto;
        max-height: none;
        padding: 0 1.25rem;
    }

    .audit-page .audit-pill {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }

    .audit-page .audit-pill--neutral {
        background: #eef2ff;
        color: #3730a3;
    }

    .audit-page .audit-pill--info {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .audit-page .audit-pill--success {
        background: #dcfce7;
        color: #166534;
    }

    .audit-page .audit-pill--warning {
        background: #fef3c7;
        color: #92400e;
    }

    .audit-page .audit-pill--danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .audit-page .audit-cell-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.8125rem;
        color: #334155;
    }

    .audit-page .audit-cell-desc {
        max-width: 320px;
        white-space: normal;
        line-height: 1.45;
        color: #334155;
    }

    .audit-page .audit-cell-uri {
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.8125rem;
        color: #64748b;
    }

    .audit-page .audit-table-empty {
        display: flex;
        width: 100%;
        min-height: 220px;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        gap: 8px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.06em;
    }

    .audit-page .audit-table-empty i {
        font-size: 2rem;
        color: #cbd5e1;
    }

    .audit-page .voucher-pagination-footer {
        padding: 0.75rem 1.25rem 1rem;
        margin-top: auto;
        flex-shrink: 0;
    }
</style>
<div class="main main--voucher-dashboard util-premium-page audit-page" id="main">
    <header class="voucher-dashboard-header">
        <div class="voucher-dashboard-header__text">
            <h1 class="voucher-dashboard-title">Auditing</h1>
            <p class="voucher-dashboard-subtitle">System audit trail — user actions, page views, and security events across the application.</p>
        </div>
        <div class="voucher-dashboard-header__actions">
            <a href="<?php echo htmlspecialchars($auditCtrlUrl, ENT_QUOTES, 'UTF-8'); ?>?action=export" class="util-header-btn util-header-btn--export" download>
                <i class="ri-download-line" aria-hidden="true"></i> Export CSV
            </a>
            <button type="button" class="util-header-btn util-header-btn--clear30" id="btnClear30" title="Delete logs older than 30 days">
                <i class="ri-delete-bin-6-line" aria-hidden="true"></i> Clear 30 days
            </button>
            <button type="button" class="util-header-btn util-header-btn--clearall" id="btnClearAll" title="Delete all audit logs">
                <i class="ri-delete-bin-2-line" aria-hidden="true"></i> Clear all
            </button>
        </div>
    </header>

    <div class="voucher-card audit-stats-card">
        <div class="audit-stats">
            <div class="audit-stat">
                <span class="audit-stat__label"><i class="ri-database-2-line" aria-hidden="true"></i>Total logs</span>
                <strong class="audit-stat__value"><?php echo number_format((int) $stats['total_logs']); ?></strong>
            </div>
            <div class="audit-stat audit-stat--today">
                <span class="audit-stat__label"><i class="ri-calendar-check-line" aria-hidden="true"></i>Today</span>
                <strong class="audit-stat__value"><?php echo number_format((int) $stats['today_logs']); ?></strong>
            </div>
            <div class="audit-stat audit-stat--users">
                <span class="audit-stat__label"><i class="ri-group-line" aria-hidden="true"></i>Active users (7d)</span>
                <strong class="audit-stat__value"><?php echo number_format((int) $stats['active_users']); ?></strong>
            </div>
            <div class="audit-stat audit-stat--errors">
                <span class="audit-stat__label"><i class="ri-error-warning-line" aria-hidden="true"></i>Errors</span>
                <strong class="audit-stat__value"><?php echo number_format((int) $stats['error_logs']); ?></strong>
            </div>
        </div>
    </div>

    <div class="voucher-card voucher-card--filter audit-filter-card">
        <div class="audit-filter-row">
            <div class="audit-filter-field">
                <label for="filterInput">Search audit trail</label>
                <input type="text" id="filterInput" name="q" value="<?php echo htmlspecialchars($rawQ, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Description, action, user, IP, request URI…" autocomplete="off">
            </div>
        </div>
        <p class="audit-filter-hint">Press Enter to search. Showing up to <?php echo (int) $maxBrowse; ?> most recent matching entries.</p>
    </div>

    <div class="voucher-card voucher-card--table">
        <div class="audit-table-head">
            <h2 class="voucher-card-title" style="margin:0;">Audit Trail</h2>
            <p class="audit-table-meta">
                <?php if ($displayTotal < 1) : ?>
                    No entries to display
                <?php else : ?>
                    <?php echo number_format($endEntry); ?> of <?php echo number_format($totalRows); ?> shown
                <?php endif; ?>
            </p>
        </div>
        <div class="content-wrapper">
            <table class="table content_table content_table--dashboard" id="auditTable">
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <th>User (ID)</th>
                        <th>Name</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Processing No</th>
                        <th>IP</th>
                        <th>Request</th>
                    </tr>
                </thead>
                <tbody id="auditTableBody">
                    <?php if (!empty($auditLogs)) : ?>
                        <?php foreach ($auditLogs as $log) :
                            $processingNo = $log['processing_no'] ?? null;
                            if (empty($processingNo) && !empty($log['additional_data'])) {
                                $additionalData = json_decode($log['additional_data'], true);
                                if (is_array($additionalData) && isset($additionalData['processing_no'])) {
                                    $processingNo = $additionalData['processing_no'];
                                }
                            }
                            $actionType = (string) ($log['action_type'] ?? '');
                            $pillClass = $auditActionPillClass($actionType);
                        ?>
                            <tr>
                                <td data-label="created_at" class="audit-cell-mono"><?php echo htmlspecialchars($log['created_at'] ?? ''); ?></td>
                                <td data-label="username"><?php echo htmlspecialchars($log['username'] ?? '—'); ?></td>
                                <td data-label="user_display_name"><?php echo htmlspecialchars($log['user_display_name'] ?? '—'); ?></td>
                                <td data-label="action_type">
                                    <span class="audit-pill <?php echo htmlspecialchars($pillClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($actionType); ?></span>
                                </td>
                                <td data-label="description" class="audit-cell-desc"><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                                <td data-label="processing_no" class="audit-cell-mono"><?php echo !empty($processingNo) ? htmlspecialchars((string) $processingNo) : '—'; ?></td>
                                <td data-label="ip_address" class="audit-cell-mono"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                                <td data-label="request_uri" class="audit-cell-uri" title="<?php echo htmlspecialchars($log['request_uri'] ?? ''); ?>"><?php echo htmlspecialchars($log['request_uri'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (count($auditLogs) < 1) : ?>
                <div class="audit-table-empty">
                    <i class="ri-file-search-line" aria-hidden="true"></i>
                    <p>No data to display</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="voucher-pagination-footer">
            <div class="pagination">
                <div class="pagination_container pagination_container--modern">
                    <div class="pagination_navigation pagination_navigation--modern">
                        <?php if ($displayTotal < 1) : ?>
                            <button class="pagination_btn_modern" type="button" disabled>Previous</button>
                            <div class="pagination_pages pagination_pages--modern"></div>
                            <button class="pagination_btn_modern" type="button" disabled>Next</button>
                        <?php else: ?>
                            <?php if ($currentPage > 1): ?>
                                <a class="pagination_btn_modern" href="?page=<?php echo ($currentPage - 1); ?>&rowsPerPage=<?php echo (int)$rowsPerPage; ?><?php echo $qsAudit; ?>">Previous</a>
                            <?php else: ?>
                                <button class="pagination_btn_modern" type="button" disabled>Previous</button>
                            <?php endif; ?>

                            <div class="pagination_pages pagination_pages--modern">
                                <?php
                                $pageRange = 5;
                                $startPage = max(1, $currentPage - (int)floor($pageRange / 2));
                                $endPage = min($totalPages, $startPage + $pageRange - 1);
                                if ($endPage - $startPage + 1 < $pageRange) {
                                    $startPage = max(1, $endPage - $pageRange + 1);
                                }

                                if ($totalPages <= 7) {
                                    for ($i = 1; $i <= $totalPages; $i++) {
                                        $active = ($i == $currentPage) ? ' active' : '';
                                        echo '<a class="pagination_page_num' . $active . '" href="?page=' . $i . '&rowsPerPage=' . (int)$rowsPerPage . $qsAudit . '">' . $i . '</a>';
                                    }
                                } else {
                                    echo '<a class="pagination_page_num' . (1 == $currentPage ? ' active' : '') . '" href="?page=1&rowsPerPage=' . (int)$rowsPerPage . $qsAudit . '">1</a>';
                                    if ($startPage > 2) echo '<span class="pagination_ellipsis">...</span>';
                                    for ($i = max(2, $startPage); $i <= min($totalPages - 1, $endPage); $i++) {
                                        $active = ($i == $currentPage) ? ' active' : '';
                                        echo '<a class="pagination_page_num' . $active . '" href="?page=' . $i . '&rowsPerPage=' . (int)$rowsPerPage . $qsAudit . '">' . $i . '</a>';
                                    }
                                    if ($endPage < $totalPages - 1) echo '<span class="pagination_ellipsis">...</span>';
                                    echo '<a class="pagination_page_num' . ($totalPages == $currentPage ? ' active' : '') . '" href="?page=' . $totalPages . '&rowsPerPage=' . (int)$rowsPerPage . $qsAudit . '">' . $totalPages . '</a>';
                                }
                                ?>
                            </div>

                            <?php if ($currentPage < $totalPages): ?>
                                <a class="pagination_btn_modern" href="?page=<?php echo ($currentPage + 1); ?>&rowsPerPage=<?php echo (int)$rowsPerPage; ?><?php echo $qsAudit; ?>">Next</a>
                            <?php else: ?>
                                <button class="pagination_btn_modern" type="button" disabled>Next</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="pagination_info">
                        <?php echo $displayTotal < 1 ? 'NO DATA TO DISPLAY' : ('Showing ' . $endEntry . ' of ' . $totalRows . ' results'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var auditCtrlUrl = <?php echo json_encode($auditCtrlUrl); ?>;

    (function() {
        var inp = document.getElementById('filterInput');
        if (!inp) return;
        var initial = String(inp.value || '');
        function applyFilterSearch() {
            var v = String(inp.value || '');
            if (v === initial) return;
            var u = new URL(window.location.href);
            u.searchParams.set('page', '1');
            u.searchParams.set('rowsPerPage', '50');
            if (v === '') u.searchParams.delete('q');
            else u.searchParams.set('q', v);
            window.location.href = u.toString();
        }
        inp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilterSearch();
            }
        });
    })();

    function doClear(action, label, confirmMsg) {
        functionAlert(confirmMsg, 'audit-clear-confirm', function() {
            var btn = action === 'clear_all' ? document.getElementById('btnClearAll') : document.getElementById('btnClear30');
            if (btn) { btn.disabled = true; btn.style.opacity = '0.7'; }
            var url = auditCtrlUrl + '?action=' + encodeURIComponent(action);
            if (action === 'clear_old') url += '&days=30';

            fetch(url, { method: 'GET', credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'success') {
                        showNotify(data.message || (label + ' completed.'), 'success', 3000);
                        setTimeout(function() { window.location.reload(); }, 1000);
                    } else {
                        showNotify('Error: ' + (data.message || 'Request failed'), 'error', 4000);
                        if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
                    }
                })
                .catch(function(err) {
                    showNotify('Error: ' + (err.message || 'Request failed'), 'error', 4000);
                    if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
                });
        });
    }

    document.getElementById('btnClear30').addEventListener('click', function() {
        doClear('clear_old', 'Clear 30 days', 'Delete all audit logs older than 30 days? This cannot be undone.');
    });
    document.getElementById('btnClearAll').addEventListener('click', function() {
        doClear('clear_all', 'Clear all', 'Delete ALL audit logs? This cannot be undone.');
    });
</script>
<?php if ($invalidSearch): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showNotify === 'function') {
            showNotify('Invalid search: remove special characters or shorten your query.', 'warning', 2600);
        }
    });
</script>
<?php elseif (trim($rawQ) !== '' && $q !== '' && ($displayTotal ?? 0) < 1): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showNotify === 'function') {
            showNotify('No matching audit log entries for your search.', 'warning', 2200);
        }
    });
</script>
<?php endif; ?>