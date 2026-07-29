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
?>
<style>
.audit-header-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-left: auto;
}
.audit-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background 0.2s, box-shadow 0.2s;
    white-space: nowrap;
}
.audit-btn i { font-size: 1.1rem; }
.audit-btn--export {
    background: #0B5ED7;
    color: #fff;
    box-shadow: 0 2px 4px rgba(11, 94, 215, 0.3);
}
.audit-btn--export:hover {
    background: #0a52c0;
    box-shadow: 0 3px 8px rgba(11, 94, 215, 0.35);
}
.audit-btn--clear30 {
    background: #FF7043;
    color: #fff;
    box-shadow: 0 2px 4px rgba(255, 112, 67, 0.3);
}
.audit-btn--clear30:hover {
    background: #e86335;
    box-shadow: 0 3px 8px rgba(255, 112, 67, 0.35);
}
.audit-btn--clearall {
    background: #BB2D3B;
    color: #fff;
    box-shadow: 0 2px 4px rgba(187, 45, 59, 0.3);
}
.audit-btn--clearall:hover {
    background: #a02632;
    box-shadow: 0 3px 8px rgba(187, 45, 59, 0.35);
}
</style>
<div class="main main--dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Auditing</h1>
        <div class="audit-header-actions">
            <a href="<?php echo htmlspecialchars($auditCtrlUrl); ?>?action=export" class="audit-btn audit-btn--export" download>
                <i class="ri-download-line"></i> Export CSV
            </a>
            <button type="button" class="audit-btn audit-btn--clear30" id="btnClear30" title="Delete logs older than 30 days">
                <i class="ri-delete-bin-6-line"></i> Clear logs (30 days)
            </button>
            <button type="button" class="audit-btn audit-btn--clearall" id="btnClearAll" title="Delete all audit logs">
                <i class="ri-delete-bin-2-line"></i> Clear all logs
            </button>
        </div>
    </header>

    <div class="voucher-card voucher-card--filter">
        <div class="filter-download_container">
            <div class="filter_options_container">
                <div class="filter-container">
                    <input type="text" id="filterInput" name="q" value="<?php echo htmlspecialchars($rawQ, ENT_QUOTES, 'UTF-8'); ?>" placeholder="search" autocomplete="off">
                </div>
            </div>
            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <span class="text-xsm">Total: <strong><?php echo (int) $stats['total_logs']; ?></strong></span>
                <span class="text-xsm">Today: <strong><?php echo (int) $stats['today_logs']; ?></strong></span>
                <span class="text-xsm">Active users (7d): <strong><?php echo (int) $stats['active_users']; ?></strong></span>
                <span class="text-xsm">Errors: <strong><?php echo (int) $stats['error_logs']; ?></strong></span>
            </div>
        </div>
    </div>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Audit Trail</h2>
        <style>
            /* Make the table area scroll, keep pager stuck to bottom of card */
            .voucher-card--table {
                position: relative;
                display: flex;
                flex-direction: column;
            }

            .voucher-card--table .content-wrapper {
                flex: 1;
                min-height: 0;
                overflow: auto;
                max-height: 70vh;
            }

            .voucher-pagination-footer {
                position: sticky;
                bottom: 0;
                z-index: 5;
                background: #fff;
                border-top: 1px solid rgba(229, 231, 235, 1);
                padding: 10px 0 0;
            }
        </style>
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
                    <?php if (!empty($auditLogs)): ?>
                        <?php foreach ($auditLogs as $log): 
                            // Extract processing_no from column or additional_data JSON
                            $processingNo = $log['processing_no'] ?? null;
                            if (empty($processingNo) && !empty($log['additional_data'])) {
                                $additionalData = json_decode($log['additional_data'], true);
                                if (is_array($additionalData) && isset($additionalData['processing_no'])) {
                                    $processingNo = $additionalData['processing_no'];
                                }
                            }
                            $processingNoDisplay = !empty($processingNo) ? htmlspecialchars($processingNo) : '—';
                        ?>
                            <tr>
                                <td data-label="created_at"><?php echo htmlspecialchars($log['created_at'] ?? ''); ?></td>
                                <td data-label="username"><?php echo htmlspecialchars($log['username'] ?? '—'); ?></td>
                                <td data-label="user_display_name"><?php echo htmlspecialchars($log['user_display_name'] ?? '—'); ?></td>
                                <td data-label="action_type"><?php echo htmlspecialchars($log['action_type'] ?? ''); ?></td>
                                <td data-label="description" class="status"><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                                <td data-label="processing_no"><?php echo $processingNoDisplay; ?></td>
                                <td data-label="ip_address"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                                <td data-label="request_uri" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($log['request_uri'] ?? ''); ?>"><?php echo htmlspecialchars($log['request_uri'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="no-display" style="<?php echo (count($auditLogs) < 1) ? 'display:flex; width: 100%; height: 80px; justify-content: center; align-items: center; font-weight: 500; color: dimgray;' : 'display:none;'; ?>">
                <p>NO DATA TO DISPLAY</p>
            </div>
        </div>
        <?php
        $startEntry = $displayTotal > 0 ? (($currentPage - 1) * $rowsPerPage) + 1 : 0;
        $endEntry = $displayTotal > 0 ? min($currentPage * $rowsPerPage, $displayTotal) : 0;
        ?>
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