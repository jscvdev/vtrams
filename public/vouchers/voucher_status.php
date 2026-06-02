<?php
include '../includes/header.php';
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/cursor_pagination_helper.php';
require_once __DIR__ . '/../../protected/core/components/helpers/amount_helper.inc.php';
require_once __DIR__ . '/../../protected/handler/voucher_module/voucher.model.inc.php';
require_once __DIR__ . '/checklist_config.php';
AuditHelper::logPageView('Voucher Status');

vouchers_amount_ensure_string_column($pdo);

$rawSearch = (string) ($_GET['searchTerm'] ?? '');
$q = filterInput($rawSearch);
$invalidSearch = (trim($rawSearch) !== '' && $q === '');
$rowsPerPage = clamp_int($_GET['rowsPerPage'] ?? null, 1, 50, 50);
$maxBrowse = 100;

$searchParams = [];
$searchSql = '';
if (!$invalidSearch && $q !== '') {
    $pat = '%' . $q . '%';
    $cols = ['processing_no', 'ors_no', 'dv_no', 'ada_check_no', 'payee', 'address', 'particulars', 'voucher_type', 'voucher_status', 'status', 'remarks', 'datetime_encoded', 'datetime_status', 'total_processing_time'];
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
    $document_status_queryCount = 'SELECT COUNT(*) AS total FROM voucher_tracking vt WHERE vt.office_from = :office_from' . str_replace(
        ['`processing_no`', '`ors_no`', '`dv_no`', '`ada_check_no`', '`payee`', '`address`', '`particulars`', '`voucher_type`', '`voucher_status`', '`status`', '`remarks`', '`datetime_encoded`', '`datetime_status`', '`total_processing_time`'],
        ['`vt`.`processing_no`', '`vt`.`ors_no`', '`vt`.`dv_no`', '`vt`.`ada_check_no`', '`vt`.`payee`', '`vt`.`address`', '`vt`.`particulars`', '`vt`.`voucher_type`', '`vt`.`voucher_status`', '`vt`.`status`', '`vt`.`remarks`', '`vt`.`datetime_encoded`', '`vt`.`datetime_status`', '`vt`.`total_processing_time`'],
        $searchSql
    );
    $document_status_statementCount = $pdo->prepare($document_status_queryCount);
    $document_status_statementCount->bindParam(':office_from', $_SESSION['logged_user_office'], PDO::PARAM_STR);
    foreach ($searchParams as $key => $pair) {
        $document_status_statementCount->bindValue($key, $pair[0], $pair[1]);
    }
    $document_status_statementCount->execute();
    $dbCount = (int) $document_status_statementCount->fetch(PDO::FETCH_ASSOC)['total'];
}

$displayTotal = min($dbCount, $maxBrowse);
$totalPages = $displayTotal > 0 ? (int) ceil($displayTotal / $rowsPerPage) : 1;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $rowsPerPage;
$fetchLimit = $displayTotal > 0 ? min($rowsPerPage, max(0, $maxBrowse - $offset)) : 0;

$fetch_voucher_status_log_query = 'SELECT vt.*, COALESCE(NULLIF(TRIM(CAST(v.amount AS CHAR)), \'\'), CAST(vt.amount AS CHAR)) AS amount_resolved
    FROM voucher_tracking vt
    LEFT JOIN vouchers v ON v.processing_no = vt.processing_no
    WHERE vt.office_from = :office_from' . str_replace(
    ['`processing_no`', '`ors_no`', '`dv_no`', '`ada_check_no`', '`payee`', '`address`', '`particulars`', '`voucher_type`', '`voucher_status`', '`status`', '`remarks`', '`datetime_encoded`', '`datetime_status`', '`total_processing_time`'],
    ['`vt`.`processing_no`', '`vt`.`ors_no`', '`vt`.`dv_no`', '`vt`.`ada_check_no`', '`vt`.`payee`', '`vt`.`address`', '`vt`.`particulars`', '`vt`.`voucher_type`', '`vt`.`voucher_status`', '`vt`.`status`', '`vt`.`remarks`', '`vt`.`datetime_encoded`', '`vt`.`datetime_status`', '`vt`.`total_processing_time`'],
    $searchSql
) . ' ORDER BY vt.processing_no DESC LIMIT :lim OFFSET :off';
$fetch_voucher_status_log = $pdo->prepare($fetch_voucher_status_log_query);
$fetch_voucher_status_log->bindParam(':office_from', $_SESSION['logged_user_office'], PDO::PARAM_STR);
foreach ($searchParams as $key => $pair) {
    $fetch_voucher_status_log->bindValue($key, $pair[0], $pair[1]);
}
$fetch_voucher_status_log->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$fetch_voucher_status_log->bindValue(':off', $offset, PDO::PARAM_INT);
$fetch_voucher_status_log->execute();

$totalRows = $displayTotal;
$qsSearch = $rawSearch !== '' ? ('&searchTerm=' . rawurlencode($rawSearch)) : '';
?>
<script src="../../protected/js/set_print_time.js"></script>
<div id="searchResults"></div> <!-- This is where the search results will be displayed -->
<!--=============== MAIN ===============!-->
<div class="main main--dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Voucher Status</h1>
    </header>
    <div class="voucher-card voucher-card--filter">
        <div class="filter-download_container">
            <div class="filter_options_container">
                <div class="filter-container">
                    <input type="text" id="filterInput" name="searchTerm" value="<?php echo htmlspecialchars($rawSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by payee, processing no., status, etc" autocomplete="off">
                </div>
            </div>
        </div>
    </div>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Status Summary</h2>
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
            <div class="" id="document_status_table">
                <table class="table content_table content_table--dashboard" id="my-Table">
                    <thead>
                        <tr>
                            <th>Processing No.</th>
                            <th>ORS No.</th>
                            <th>DV No.</th>
                            <th>Ada/Check No.</th>
                            <th>Payee Name</th>
                            <th>Address</th>
                            <th>Particulars</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Date/Time Encoded</th>
                            <th>Status</th>
                            <th>Date/Time Status</th>
                            <th>Remarks</th>
                            <th>Total Processing Time</th>
                        </tr>
                    </thead>
                    <tbody id="target_body">
                        <?php while ($row = $fetch_voucher_status_log->fetch(PDO::FETCH_ASSOC)) : ?>
                            <tr> <?php
                                    $trimmed_remarks = explode(",", $row['remarks']);
                                    $last_element = end($trimmed_remarks);
                                    ?>
                                <td data-label="processing_no"><?php echo $row['processing_no']; ?></td>
                                <td data-label="ors_no"><?php echo $row['ors_no']; ?></td>
                                <td data-label="dv_no"><?php echo $row['dv_no']; ?></td>
                                <td data-label="ada_check_no"><?php echo $row['ada_check_no']; ?></td>
                                <td data-label="payee"><?php echo $row['payee']; ?></td>
                                <td data-label="address" class="status"><?php echo $row['address']; ?></td>
                                <td data-label="particulars"><?php echo $row['particulars']; ?></td>
                                <?php
                                    $amountRaw = amount_pdo_value_to_string($row['amount_resolved'] ?? $row['amount'] ?? '');
                                    $amountNormalized = normalize_amount_string($amountRaw);
                                    $amountShown = format_amount_display($amountRaw);
                                ?>
                                <td data-label="amount" class="amount" data-amount="<?php echo htmlspecialchars($amountNormalized, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($amountShown, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="voucher_type_display" class="voucher-type-cell"><?php echo voucher_type_badge_html((string)($row['voucher_type'] ?? '')); ?></td>
                                <td data-label="datetime_encoded"><?php echo $row['datetime_encoded']; ?></td>
                                <td data-label="voucher_status"><?php echo $row['voucher_status']; ?></td>
                                <td data-label="datetime_status"><?php echo $row['datetime_status']; ?></td>
                                <td data-label="status"><?php echo $row['status']; ?></td>
                                <td data-label="remarks" class="hidden"><?php echo $last_element; ?></td>
                                <td data-label="total_processing_time"><?php echo $row['total_processing_time']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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
<!--SORTING-->
<script>
    var sortDirection = {};

    function sortTable(columnIndex) {
        var table, rows, switching, i, x, y, shouldSwitch;
        table = document.getElementById("target_body");
        switching = true;

        // Determine current sort direction or set initial direction
        var currentDirection = sortDirection[columnIndex] || 'asc';

        // Toggle the sort direction
        currentDirection = (currentDirection === 'asc') ? 'desc' : 'asc';
        sortDirection[columnIndex] = currentDirection;

        // Make a loop that will continue until no switching has been done
        while (switching) {
            switching = false;
            rows = table.rows;

            // Loop through all table rows (except the first, which contains table headers)
            for (i = 0; i < (rows.length - 1); i++) {
                shouldSwitch = false;

                // Get the two elements you want to compare, one from current row and one from the next
                x = rows[i].getElementsByTagName("td")[columnIndex];
                y = rows[i + 1].getElementsByTagName("td")[columnIndex];

                // Extract the numeric part after the hyphen
                var xNum = parseInt(x.innerHTML.split('-')[1]);
                var yNum = parseInt(y.innerHTML.split('-')[1]);

                // Check if the two rows should switch place, based on the direction and content
                if (currentDirection === 'asc') {
                    if (xNum > yNum) {
                        shouldSwitch = true;
                        break;
                    }
                } else if (currentDirection === 'desc') {
                    if (xNum < yNum) {
                        shouldSwitch = true;
                        break;
                    }
                }
            }

            if (shouldSwitch) {
                // If a switch has been marked, make the switch and mark that a switch has been done
                rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                switching = true;
            }
        }
    }

    //FOR TURNAROUND TIME

    function sortTableTT(columnIndex) {
        var table, rows, switching, i, x, y, shouldSwitch;
        table = document.getElementById("target_body");
        switching = true;

        // Determine current sort direction or set initial direction
        var currentDirection = sortDirection[columnIndex] || 'asc';

        // Toggle the sort direction
        currentDirection = (currentDirection === 'asc') ? 'desc' : 'asc';
        sortDirection[columnIndex] = currentDirection;

        // Make a loop that will continue until no switching has been done
        while (switching) {
            switching = false;
            rows = table.rows;

            // Loop through all table rows (including the first one now)
            for (i = 0; i < (rows.length - 1); i++) {
                shouldSwitch = false;

                // Get the two elements you want to compare, one from current row and one from the next
                x = rows[i].getElementsByTagName("td")[columnIndex];
                y = rows[i + 1].getElementsByTagName("td")[columnIndex];

                // Extract hours, minutes, and seconds from innerHTML
                var xTime = x.innerHTML.trim().split(' ');
                var yTime = y.innerHTML.trim().split(' ');

                // Convert hours, minutes, and seconds to total seconds for comparison
                var xTotalSeconds = calculateTotalSeconds(xTime);
                var yTotalSeconds = calculateTotalSeconds(yTime);

                // Check if the two rows should switch place, based on the direction and content
                if (currentDirection === 'asc') {
                    if (xTotalSeconds > yTotalSeconds) {
                        shouldSwitch = true;
                        break;
                    }
                } else if (currentDirection === 'desc') {
                    if (xTotalSeconds < yTotalSeconds) {
                        shouldSwitch = true;
                        break;
                    }
                }
            }

            if (shouldSwitch) {
                // If a switch has been marked, make the switch and mark that a switch has been done
                rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                switching = true;
            }
        }
    }

    // Function to calculate total seconds from "hours minutes seconds" format
    function calculateTotalSeconds(timeParts) {
        var totalSeconds = 0;
        if (timeParts.length >= 3) {
            var hours = parseInt(timeParts[0]) || 0;
            var minutes = parseInt(timeParts[1]) || 0;
            var seconds = parseInt(timeParts[2]) || 0;
            totalSeconds = hours * 3600 + minutes * 60 + seconds;
        }
        return totalSeconds;
    }
</script>
<script>
    $(document).ready(function() {
        $(".status").each(function() {
            if ($(this).text().includes("Archived")) {
                $(this).parent().css("background-color", "lightyellow");
                $(this).parent().children('td').css("color", "#00000");
            }
        })
    });
</script>
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
                window.location.href = '?page=1&rowsPerPage=50&searchTerm=' + encodeURIComponent(v);
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
            showNotify('No matching vouchers for your search.', 'warning', 2200);
        }
    });
</script>
<?php endif; ?>
<!--=============== MAIN.JS ===============!-->
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/amount_helper.js"></script>
<script src="../../protected/js/voucher.js"></script>
</body>

</html>