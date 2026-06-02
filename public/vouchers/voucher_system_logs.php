<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/cursor_pagination_helper.php';
require_once __DIR__ . '/../../protected/core/components/helpers/amount_helper.inc.php';
require_once __DIR__ . '/../../protected/handler/voucher_module/voucher.model.inc.php';
require_once __DIR__ . '/checklist_config.php';
AuditHelper::logPageView('Voucher System Logs');

vouchers_amount_ensure_string_column($pdo);

$rawSearch = (string) ($_GET['searchTerm'] ?? '');
$q = filterInput($rawSearch);
$invalidSearch = (trim($rawSearch) !== '' && $q === '');
$rowsPerPage = clamp_int($_GET['rowsPerPage'] ?? null, 1, 50, 50);
$maxBrowse = 100;
$office = (string) ($_SESSION['logged_user_office'] ?? '');

$searchParams = [];
$searchSql = '';
if (!$invalidSearch && $q !== '') {
    $pat = '%' . $q . '%';
    $cols = ['processing_no', 'payee', 'address', 'particulars', 'action', 'action_by', 'dv_no', 'ors_no', 'ada_check_no'];
    $parts = [];
    foreach ($cols as $i => $col) {
        $ph = ':sq' . $i;
        $parts[] = '`' . $col . '` LIKE ' . $ph;
        $searchParams[$ph] = [$pat, PDO::PARAM_STR];
    }
    $searchSql = ' AND (' . implode(' OR ', $parts) . ')';
}

if ($invalidSearch) {
    $dbCount = 0;
} else {
    $countSql = 'SELECT COUNT(*) AS total FROM voucher_action_logs val WHERE val.office_from = :office_from' . str_replace(
        ['`processing_no`', '`payee`', '`address`', '`particulars`', '`action`', '`action_by`', '`dv_no`', '`ors_no`', '`ada_check_no`'],
        ['`val`.`processing_no`', '`val`.`payee`', '`val`.`address`', '`val`.`particulars`', '`val`.`action`', '`val`.`action_by`', '`val`.`dv_no`', '`val`.`ors_no`', '`val`.`ada_check_no`'],
        $searchSql
    );
    $voucher_action_logs_statementCount = $pdo->prepare($countSql);
    $voucher_action_logs_statementCount->bindParam(':office_from', $_SESSION['logged_user_office'], PDO::PARAM_STR);
    foreach ($searchParams as $key => $pair) {
        $voucher_action_logs_statementCount->bindValue($key, $pair[0], $pair[1]);
    }
    $voucher_action_logs_statementCount->execute();
    $dbCount = (int) $voucher_action_logs_statementCount->fetch(PDO::FETCH_ASSOC)['total'];
}

$displayTotal = min($dbCount, $maxBrowse);
$totalPages = $displayTotal > 0 ? (int) ceil($displayTotal / $rowsPerPage) : 1;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $rowsPerPage;
$fetchLimit = $displayTotal > 0 ? min($rowsPerPage, max(0, $maxBrowse - $offset)) : 0;

// Each log row keeps its own amount snapshot; do not join vouchers (current amount).
$fetch_voucher_action_logs_query = 'SELECT val.*, CAST(val.amount AS CHAR) AS amount_log
    FROM voucher_action_logs val
    WHERE val.office_from = :office_from' . str_replace(
    ['`processing_no`', '`payee`', '`address`', '`particulars`', '`action`', '`action_by`', '`dv_no`', '`ors_no`', '`ada_check_no`'],
    ['`val`.`processing_no`', '`val`.`payee`', '`val`.`address`', '`val`.`particulars`', '`val`.`action`', '`val`.`action_by`', '`val`.`dv_no`', '`val`.`ors_no`', '`val`.`ada_check_no`'],
    $searchSql
) . ' ORDER BY val.id DESC LIMIT :lim OFFSET :off';
$fetch_voucher_action_logs = $pdo->prepare($fetch_voucher_action_logs_query);
$fetch_voucher_action_logs->bindParam(':office_from', $_SESSION['logged_user_office'], PDO::PARAM_STR);
foreach ($searchParams as $key => $pair) {
    $fetch_voucher_action_logs->bindValue($key, $pair[0], $pair[1]);
}
$fetch_voucher_action_logs->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$fetch_voucher_action_logs->bindValue(':off', $offset, PDO::PARAM_INT);
$fetch_voucher_action_logs->execute();

$totalRows = $displayTotal;
$qsSearch = $rawSearch !== '' ? ('&searchTerm=' . rawurlencode($rawSearch)) : '';

?>
<!--=============== MAIN ===============!-->
<div class="main main--dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">System Logs</h1>
    </header>
    <style>
        #systemLogsFilterForm {
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            width: 100%;
            gap: 10px;
        }

        #systemLogsFilterForm .filter-chips {
            flex: 0 0 auto;
            flex-wrap: nowrap !important;
        }

        #systemLogsFilterForm .filter-search {
            flex: 1 1 auto;
            min-width: 0 !important;
        }
    </style>
    <div class="voucher-card voucher-card--filter">
        <div class="filter-toolbar">
            <div class="filter-left">
                <form method="GET" action="" id="systemLogsFilterForm" class="filter-toolbar-form" onsubmit="return false;">
                    <div class="filter-chips" aria-label="Filter tools">
                        <a class="filter-icon-btn" href="voucher_system_logs.php" aria-label="Home">
                        </a>
                        <button type="button" class="filter-icon-btn" aria-label="Copy">
                        </button>
                    </div>
                    <div class="filter-search">
                        <input type="text" id="filterInput" name="searchTerm" value="<?php echo htmlspecialchars($rawSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by payee, processing no., action, etc" autocomplete="off">
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Action Logs Summary</h2>
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
            <table class="table content_table content_table--dashboard" id="my-Table">
                <thead>
                    <tr>
                        <th>Processing No.</th>
                        <th>Payee Name</th>
                        <th>Address</th>
                        <th>Particulars</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Action</th>
                        <th>Date/Time Action</th>
                        <th>Action By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $fetch_voucher_action_logs->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td data-label="processing_no"><?php echo $row['processing_no']; ?></td>
                            <td data-label="payee"><?php echo $row['payee']; ?></td>
                            <td data-label="address"><?php echo $row['address']; ?></td>
                            <td data-label="particulars" class="status"><?php echo $row['particulars']; ?></td>
                            <?php
                                $amountRaw = amount_pdo_value_to_string($row['amount_log'] ?? $row['amount'] ?? '');
                                $amountNormalized = normalize_amount_string($amountRaw);
                                $amountShown = format_amount_display($amountRaw);
                            ?>
                            <td data-label="amount" class="amount-cell" data-amount="<?php echo htmlspecialchars($amountNormalized, ENT_QUOTES, 'UTF-8'); ?>" data-amount-formatted="php" data-amount-skip="1"><?php echo htmlspecialchars($amountShown, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="voucher_type_display" class="voucher-type-cell"><?php echo voucher_type_badge_html((string)($row['voucher_type'] ?? '')); ?></td>
                            <td data-label="action"><?php echo $row['action']; ?></td>
                            <td data-label="datetime_action"><?php echo $row['datetime_action']; ?></td>
                            <td data-label="action_by"><?php echo $row['action_by']; ?></td>

                            <td data-label="dv_no" class="hidden"><?php echo $row['dv_no']; ?></td>
                            <td data-label="ors_no" class="hidden"><?php echo $row['ors_no']; ?></td>
                            <td data-label="ada_check_no" class="hidden"><?php echo $row['ada_check_no']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php
        $totalPages = $totalRows ? (int)ceil($totalRows / $rowsPerPage) : 1;
        $startEntry = $totalRows ? (($currentPage - 1) * $rowsPerPage) + 1 : 0;
        $endEntry = $totalRows ? min($currentPage * $rowsPerPage, $totalRows) : 0;
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
                                <a class="pagination_btn_modern" href="?page=<?php echo ($currentPage - 1); ?>&rowsPerPage=<?php echo (int)$rowsPerPage; ?><?php echo $qsSearch; ?>">Previous</a>
                            <?php else: ?>
                                <button class="pagination_btn_modern" type="button" disabled>Previous</button>
                            <?php endif; ?>

                            <div class="pagination_pages pagination_pages--modern">
                                <?php
                                $pageRange = 5;
                                $startPage = max(1, $currentPage - (int)floor($pageRange / 2));
                                $endPage2 = min($totalPages, $startPage + $pageRange - 1);
                                if ($endPage2 - $startPage + 1 < $pageRange) {
                                    $startPage = max(1, $endPage2 - $pageRange + 1);
                                }

                                if ($totalPages <= 7) {
                                    for ($i = 1; $i <= $totalPages; $i++) {
                                        $active = ($i == $currentPage) ? ' active' : '';
                                        echo '<a class="pagination_page_num' . $active . '" href="?page=' . $i . '&rowsPerPage=' . (int)$rowsPerPage . $qsSearch . '">' . $i . '</a>';
                                    }
                                } else {
                                    echo '<a class="pagination_page_num' . (1 == $currentPage ? ' active' : '') . '" href="?page=1&rowsPerPage=' . (int)$rowsPerPage . $qsSearch . '">1</a>';
                                    if ($startPage > 2) echo '<span class="pagination_ellipsis">...</span>';
                                    for ($i = max(2, $startPage); $i <= min($totalPages - 1, $endPage2); $i++) {
                                        $active = ($i == $currentPage) ? ' active' : '';
                                        echo '<a class="pagination_page_num' . $active . '" href="?page=' . $i . '&rowsPerPage=' . (int)$rowsPerPage . $qsSearch . '">' . $i . '</a>';
                                    }
                                    if ($endPage2 < $totalPages - 1) echo '<span class="pagination_ellipsis">...</span>';
                                    echo '<a class="pagination_page_num' . ($totalPages == $currentPage ? ' active' : '') . '" href="?page=' . $totalPages . '&rowsPerPage=' . (int)$rowsPerPage . $qsSearch . '">' . $totalPages . '</a>';
                                }
                                ?>
                            </div>

                            <?php if ($currentPage < $totalPages): ?>
                                <a class="pagination_btn_modern" href="?page=<?php echo ($currentPage + 1); ?>&rowsPerPage=<?php echo (int)$rowsPerPage; ?><?php echo $qsSearch; ?>">Next</a>
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
    (function() {
        var inp = document.getElementById('filterInput');
        if (!inp) return;
        var initial = String(inp.value || '');
        var t = null;
        inp.addEventListener('input', function() {
            clearTimeout(t);
            t = setTimeout(function() {
                var v = String(inp.value || '');
                if (v === initial) return;
                var u = new URL(window.location.href);
                u.searchParams.set('page', '1');
                u.searchParams.set('rowsPerPage', '50');
                if (v === '') {
                    u.searchParams.delete('searchTerm');
                } else {
                    u.searchParams.set('searchTerm', v);
                }
                window.location.href = u.toString();
            }, 400);
        });
    })();
</script>
<?php if ($invalidSearch): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showNotify === 'function') {
            showNotify('Invalid search: remove special characters or shorten your query.', 'warning', 2600);
        }
    });
</script>
<?php elseif (trim($rawSearch) !== '' && $q !== '' && $displayTotal < 1): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showNotify === 'function') {
            showNotify('No matching action logs for your search.', 'warning', 2200);
        }
    });
</script>
<?php endif; ?>
<!--=============== MAIN.JS ===============!-->
<script src="../../protected/js/main.js"></script>
</body>

</html>