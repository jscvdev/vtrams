<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Designations');

if (!AccessControl::canAccessSystemUtilities()) {
    echo "<script>process_functionAlert('Access denied!', 'dashboard_redirect')</script>";
    die();
}
include('../../protected/core/components/notifications/err_handler_custom_alert.php');
require_once __DIR__ . '/../../protected/core/components/notifications/custom_alert.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/cursor_pagination_helper.php';

if (isset($_SESSION['designation_success'])) {
    echo "<script>err_handler_functionAlert('" . addslashes($_SESSION['designation_success']) . "', 'designation_success')</script>";
    unset($_SESSION['designation_success']);
}
if (isset($_SESSION['designation_error'])) {
    echo "<script>err_handler_functionAlert('" . addslashes($_SESSION['designation_error']) . "', 'designation_err')</script>";
    unset($_SESSION['designation_error']);
}

$rawQ = (string) ($_GET['q'] ?? '');
$q = filterInput($rawQ);
$invalidSearch = (trim($rawQ) !== '' && $q === '');
$rowsPerPage = clamp_int($_GET['rowsPerPage'] ?? null, 1, 50, 50);
$maxBrowse = 100;

$searchSql = '';
$searchParams = [];
if (!$invalidSearch && $q !== '') {
    $pat = '%' . $q . '%';
    $searchSql = ' WHERE (`designation` LIKE :sq0 OR `designated_udc` LIKE :sq1)';
    $searchParams[':sq0'] = [$pat, PDO::PARAM_STR];
    $searchParams[':sq1'] = [$pat, PDO::PARAM_STR];
}

if ($invalidSearch) {
    $dbCount = 0;
} else {
    $document_status_queryCount = 'SELECT COUNT(*) AS total FROM designation_limit' . $searchSql;
    $document_status_statementCount = $pdo->prepare($document_status_queryCount);
    foreach ($searchParams as $k => $pair) {
        $document_status_statementCount->bindValue($k, $pair[0], $pair[1]);
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

$designation_limit_query = 'SELECT * FROM designation_limit' . ($searchSql !== '' ? $searchSql : '') . ' ORDER BY id ASC LIMIT :lim OFFSET :off';
$fetch_designation_limit = $pdo->prepare($designation_limit_query);
foreach ($searchParams as $k => $pair) {
    $fetch_designation_limit->bindValue($k, $pair[0], $pair[1]);
}
$fetch_designation_limit->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$fetch_designation_limit->bindValue(':off', $offset, PDO::PARAM_INT);
$fetch_designation_limit->execute();

$totalRows = $displayTotal;
$qsDesignations = $rawQ !== '' ? ('&q=' . rawurlencode($rawQ)) : '';

// Check if designated_office and visibility columns exist (for older DBs)
$has_extra_columns = false;
try {
    $check = $pdo->query("SELECT designation, designated_office, visibility FROM designation_limit LIMIT 1");
    $has_extra_columns = true;
} catch (PDOException $e) {
    // Columns may not exist
}
?>
<link rel="stylesheet" href="../styles/css/ppop.css">
<style>
    .hidden-on-add {
        display: none;
    }
</style>
<div class="main main--dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Designations</h1>
        <div class="btn warning btn-flex btn-nowrap popupForm-add voucher-dashboard-btn-primary">
            <img src="../assets/icons/add-icon.png" alt="">
            <a class="btn-target" name="" id="btn_add_designation">Add New Designation</a>
        </div>
    </header>
    <style>
        #designationsFilterForm {
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            width: 100%;
            gap: 10px;
        }

        #designationsFilterForm .filter-chips {
            flex: 0 0 auto;
            flex-wrap: nowrap !important;
        }

        #designationsFilterForm .filter-search {
            flex: 1 1 auto;
            min-width: 0 !important;
        }
    </style>
    <div class="voucher-card voucher-card--filter">
        <div class="filter-toolbar">
            <div class="filter-left">
                <form method="GET" action="" id="designationsFilterForm" class="filter-toolbar-form" onsubmit="return false;">
                    <div class="filter-chips" aria-label="Filter tools">
                        <a class="filter-icon-btn" href="designations.php" aria-label="Home">
                        </a>
                        <button type="button" class="filter-icon-btn" aria-label="Copy">
                        </button>
                    </div>
                    <div class="filter-search">
                        <input type="text" id="filterInput" name="q" value="<?php echo htmlspecialchars($rawQ, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search for designation, UDC, etc" autocomplete="off">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="popup-form" id="popupForm2">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="designation_form_title">Edit Designation</p>
                <i class="ri-close-fill close-icon" id="close_popup"></i>
            </div>
            <form action="" id="designation_form_container" class="f-container" method="post">
                <input type="hidden" name="designation_id" id="designation_id" value="">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="designation_name">Designation</label>
                                <input type="text" class="form-custom-input" name="designation" id="designation_name" placeholder="Designation name" required>
                            </div>
                            <div class="label-input__container hidden-on-add">
                                <label for="designated_udc">Designated UDC</label>
                                <input type="text" class="form-custom-input" name="designated_udc" id="designated_udc" placeholder="User designation code(s)">
                            </div>
                            <div class="label-input__container hidden-on-add">
                                <label for="current_designated">Current Designated</label>
                                <input type="number" class="form-custom-input" name="current_designated" id="current_designated" placeholder="0" min="0" value="0">
                            </div>
                            <div class="label-input__container">
                                <label for="max_designated">Max Designated</label>
                                <input type="number" class="form-custom-input" name="max_designated" id="max_designated" placeholder="0" min="0" value="0" required>
                            </div>
                            <?php if ($has_extra_columns) : ?>
                                <div class="label-input__container hidden-on-add">
                                    <label for="designated_office">Designated Office</label>
                                    <input type="text" class="form-custom-input" name="designated_office" id="designated_office" placeholder="Comma-separated offices">
                                </div>
                                <div class="label-input__container hidden-on-add">
                                    <label for="visibility">Visibility</label>
                                    <select class="form-custom-input" name="visibility" id="visibility">
                                        <option value="1">Visible (1)</option>
                                        <option value="0">Hidden (0)</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn transparent success btn-designation-save" name="edit_designation" type="submit">SAVE</button>
                        <button class="btn secondary transparent" id="close_popup2" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Designation Summary</h2>
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
                        <th>ID</th>
                        <th>Designation</th>
                        <th>Designated UDC</th>
                        <?php if ($has_extra_columns) : ?><th>Designated Office</th><?php endif; ?>
                        <th>Current</th>
                        <th>Max</th>
                        <?php if ($has_extra_columns) : ?><th>Visibility</th><?php endif; ?>
                        <th>Edit</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $fetch_designation_limit->fetch(PDO::FETCH_ASSOC)) : ?>
                        <tr>
                            <td data-label="id"><?php echo htmlspecialchars($row['id']); ?></td>
                            <td data-label="designation"><?php echo htmlspecialchars($row['designation'] ?? ''); ?></td>
                            <td data-label="designated_udc"><?php echo htmlspecialchars($row['designated_udc'] ?? ''); ?></td>
                            <?php if ($has_extra_columns) : ?>
                                <td data-label="designated_office"><?php echo htmlspecialchars(substr($row['designated_office'] ?? '', 0, 50)); ?><?php echo (isset($row['designated_office']) && strlen($row['designated_office']) > 50) ? '...' : ''; ?></td>
                            <?php endif; ?>
                            <td data-label="current_designated"><?php echo htmlspecialchars($row['current_designated'] ?? ''); ?></td>
                            <td data-label="max_designated"><?php echo htmlspecialchars($row['max_designated'] ?? ''); ?></td>
                            <?php if ($has_extra_columns) : ?>
                                <td data-label="visibility"><?php echo htmlspecialchars($row['visibility'] ?? ''); ?></td>
                            <?php endif; ?>
                            <td data-label="designated_office_raw" class="hidden"><?php echo htmlspecialchars($row['designated_office'] ?? ''); ?></td>
                            <td data-label="visibility_raw" class="hidden"><?php echo htmlspecialchars($row['visibility'] ?? '1'); ?></td>
                            <td data-label=""><button class="btn-target btn success popupForm-edit_user" type="button" id="btn_edit_designation">Edit</button></td>
                            <td data-label="Delete">
                                <a onclick="if(!confirm('Are you sure you want to delete this designation?')) return false;"
                                    href="../../protected/handler/designation_module/delete_designation_handler.inc.php?deleteid=<?php echo (int)$row['id']; ?>"
                                    class="btn danger">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="voucher-pagination-footer">
            <div class="pagination">
                <?php
                $startEntry = $displayTotal > 0 ? (($currentPage - 1) * $rowsPerPage) + 1 : 0;
                $endEntry = $displayTotal > 0 ? min($currentPage * $rowsPerPage, $displayTotal) : 0;
                ?>
                <div class="pagination_container pagination_container--modern">
                    <div class="pagination_navigation pagination_navigation--modern">
                        <?php if ($currentPage > 1): ?>
                            <a class="pagination_btn_modern" href="?page=<?php echo ($currentPage - 1); ?>&rowsPerPage=<?php echo $rowsPerPage; ?><?php echo $qsDesignations; ?>">Previous</a>
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
                                    echo '<a class="pagination_page_num' . $active . '" href="?page=' . $i . '&rowsPerPage=' . $rowsPerPage . $qsDesignations . '">' . $i . '</a>';
                                }
                            } else {
                                echo '<a class="pagination_page_num' . (1 == $currentPage ? ' active' : '') . '" href="?page=1&rowsPerPage=' . $rowsPerPage . $qsDesignations . '">1</a>';
                                if ($startPage > 2) echo '<span class="pagination_ellipsis">...</span>';
                                for ($i = max(2, $startPage); $i <= min($totalPages - 1, $endPage); $i++) {
                                    $active = ($i == $currentPage) ? ' active' : '';
                                    echo '<a class="pagination_page_num' . $active . '" href="?page=' . $i . '&rowsPerPage=' . $rowsPerPage . $qsDesignations . '">' . $i . '</a>';
                                }
                                if ($endPage < $totalPages - 1) echo '<span class="pagination_ellipsis">...</span>';
                                echo '<a class="pagination_page_num' . ($totalPages == $currentPage ? ' active' : '') . '" href="?page=' . $totalPages . '&rowsPerPage=' . $rowsPerPage . $qsDesignations . '">' . $totalPages . '</a>';
                            }
                            ?>
                        </div>

                        <?php if ($currentPage < $totalPages): ?>
                            <a class="pagination_btn_modern" href="?page=<?php echo ($currentPage + 1); ?>&rowsPerPage=<?php echo $rowsPerPage; ?><?php echo $qsDesignations; ?>">Next</a>
                        <?php else: ?>
                            <button class="pagination_btn_modern" type="button" disabled>Next</button>
                        <?php endif; ?>
                    </div>

                    <div class="pagination_info">
                        <?php echo $totalRows ? ('Showing ' . $endEntry . ' of ' . $totalRows . ' results') : 'NO DATA TO DISPLAY'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var filterInput = document.getElementById('filterInput');
        if (filterInput) {
            var initial = String(filterInput.value || '');
            function applyFilterSearch() {
                var v = String(filterInput.value || '');
                if (v === initial) return;
                var u = new URL(window.location.href);
                u.searchParams.set('page', '1');
                u.searchParams.set('rowsPerPage', '50');
                if (v === '') u.searchParams.delete('q');
                else u.searchParams.set('q', v);
                window.location.href = u.toString();
            }
            filterInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyFilterSearch();
                }
            });
        }
        var form = document.getElementById('designation_form_container');
        var formTitle = document.getElementById('designation_form_title');
        var hasExtraColumns = <?php echo $has_extra_columns ? 'true' : 'false'; ?>;

        // Handle Add button click
        document.getElementById('btn_add_designation').addEventListener('click', function() {
            formTitle.textContent = 'Add New Designation';
            form.setAttribute('action', '../../protected/handler/designation_module/add_designation_handler.php');
            document.querySelector('.btn-designation-save').setAttribute('name', 'add_designation');
            document.getElementById('designation_id').value = '';
            document.getElementById('designation_name').value = '';
            document.getElementById('designated_udc').value = '';
            document.getElementById('current_designated').value = '0';
            document.getElementById('max_designated').value = '0';
            if (hasExtraColumns) {
                document.getElementById('designated_office').value = '';
                document.getElementById('visibility').value = '1';
            }
            document.getElementById('designation_name').readOnly = false;
            document.querySelectorAll('.hidden-on-add').forEach(function(el) {
                el.style.display = 'none';
            });
        });

        // Handle Edit button clicks
        var buttons = document.querySelectorAll('.btn-target');
        buttons.forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('id');
                if (id === 'btn_edit_designation') {
                    var row = this.closest('tr');
                    formTitle.textContent = 'Edit Designation';
                    form.setAttribute('action', '../../protected/handler/designation_module/edit_designation_handler.php');
                    document.querySelector('.btn-designation-save').setAttribute('name', 'edit_designation');
                    document.getElementById('designation_id').value = row.querySelector('[data-label="id"]').textContent.trim();
                    document.getElementById('designation_name').value = row.querySelector('[data-label="designation"]').textContent.trim();
                    document.getElementById('designated_udc').value = row.querySelector('[data-label="designated_udc"]').textContent.trim();
                    document.getElementById('current_designated').value = row.querySelector('[data-label="current_designated"]').textContent.trim() || '0';
                    document.getElementById('max_designated').value = row.querySelector('[data-label="max_designated"]').textContent.trim() || '0';
                    if (hasExtraColumns) {
                        var officeCell = row.querySelector('[data-label="designated_office_raw"]');
                        var visCell = row.querySelector('[data-label="visibility_raw"]');
                        document.getElementById('designated_office').value = officeCell ? officeCell.textContent.trim() : '';
                        document.getElementById('visibility').value = visCell ? visCell.textContent.trim() : '1';
                    }
                    document.getElementById('designation_name').readOnly = false;
                    document.querySelectorAll('.hidden-on-add').forEach(function(el) {
                        el.style.display = 'block';
                    });
                }
            });
        });
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
<?php elseif (trim($rawQ) !== '' && $q !== '' && $displayTotal < 1): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showNotify === 'function') {
            showNotify('No matching designations for your search.', 'warning', 2200);
        }
    });
</script>
<?php endif; ?>
</body>

</html>