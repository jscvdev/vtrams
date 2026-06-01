<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Developer Tool');
include('../../protected/handler/devtool_module/edit_user_module/edit_account_errhandler.inc.php');
include('../../protected/handler/devtool_module/add_user_module/add_user_errhandler.inc.php');
include('../../protected/core/components/notifications/err_handler_custom_alert.php');
require_once __DIR__ . '/../../protected/core/components/notifications/custom_alert.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/cursor_pagination_helper.php';
check_update_account_errors();
check_add_user_errors();

$rawQ = (string) ($_GET['q'] ?? '');
$q = filterInput($rawQ);
$invalidSearch = (trim($rawQ) !== '' && $q === '');
$rowsPerPage = clamp_int($_GET['rowsPerPage'] ?? null, 1, 50, 50);
$maxBrowse = 100;

$searchSql = '';
$searchParams = [];
if (!$invalidSearch && $q !== '') {
    $pat = '%' . $q . '%';
    $cols = ['emp_id', 'emp_fn', 'emp_mi', 'emp_ln', 'office', 'section', 'division', 'designation', 'udc'];
    $parts = [];
    foreach ($cols as $i => $col) {
        $ph = ':sq' . $i;
        $parts[] = '`' . $col . '` LIKE ' . $ph;
        $searchParams[$ph] = [$pat, PDO::PARAM_STR];
    }
    $searchSql = ' WHERE (' . implode(' OR ', $parts) . ')';
}

if ($invalidSearch) {
    $dbCount = 0;
} else {
    $document_status_queryCount = 'SELECT COUNT(*) AS total FROM user_group' . $searchSql;
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

$fetch_users_query = 'SELECT * FROM user_group' . $searchSql . ' ORDER BY id ASC LIMIT :lim OFFSET :off';
$fetch_users = $pdo->prepare($fetch_users_query);
foreach ($searchParams as $k => $pair) {
    $fetch_users->bindValue($k, $pair[0], $pair[1]);
}
$fetch_users->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$fetch_users->bindValue(':off', $offset, PDO::PARAM_INT);
$fetch_users->execute();

$totalRows = $displayTotal;
$qsDevtool = $rawQ !== '' ? ('&q=' . rawurlencode($rawQ)) : '';

$fetch_designations_query = "SELECT designation FROM designation_limit ORDER BY designation ASC";
$fetch_designations = $pdo->prepare($fetch_designations_query);
$fetch_designations->execute();

$nextEmpId = '1';
try {
    $lastEmpIdStmt = $pdo->prepare("SELECT emp_id FROM user_group ORDER BY id DESC LIMIT 1");
    $lastEmpIdStmt->execute();
    $lastEmpIdRaw = $lastEmpIdStmt->fetchColumn();
    $lastEmpIdRaw = is_string($lastEmpIdRaw) ? trim($lastEmpIdRaw) : $lastEmpIdRaw;

    if ($lastEmpIdRaw === null || $lastEmpIdRaw === '') {
        $nextEmpId = '1';
    } elseif (preg_match('/^(.*?)(\d+)$/', (string)$lastEmpIdRaw, $m)) {
        $prefix = $m[1];
        $lastNumRaw = $m[2];
        $nextNum = ((int) $lastNumRaw) + 1;
        $nextEmpId = $prefix . str_pad((string) $nextNum, strlen($lastNumRaw), '0', STR_PAD_LEFT);
    } elseif (is_numeric($lastEmpIdRaw)) {
        $nextEmpId = (string) (((int) $lastEmpIdRaw) + 1);
    } else {
        // Fallback: best-effort increment for non-numeric formats.
        $nextEmpId = (string) (((int) $lastEmpIdRaw) + 1);
    }
} catch (Exception $e) {
    // Keep default $nextEmpId = '1'
}

require_once __DIR__ . '/../../protected/core/components/helpers/udc_generator_helper.inc.php';
$nextUdc = '';
try {
    $nextUdc = generate_unique_udc($pdo);
} catch (Exception $e) {
    // Handler regenerates on submit if empty
}

?>
<!--=============== MAIN ===============!-->
<link rel="stylesheet" href="../styles/css/ppop.css">
<div class="main main--dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">User Management</h1>
        <div class="btn warning btn-flex btn-nowrap popupForm-add voucher-dashboard-btn-primary">
            <img src="../assets/icons/add-icon.png" alt="">
            <a class="btn-target" name="" id="btn_add_user">Add New User</a>
        </div>
    </header>
    <style>
        #devtoolFilterForm {
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            width: 100%;
            gap: 10px;
        }

        #devtoolFilterForm .filter-chips {
            flex: 0 0 auto;
            flex-wrap: nowrap !important;
        }

        #devtoolFilterForm .filter-search {
            flex: 1 1 auto;
            min-width: 0 !important;
        }
    </style>
    <div class="voucher-card voucher-card--filter">
        <div class="filter-toolbar">
            <div class="filter-left">
                <form method="GET" action="" id="devtoolFilterForm" class="filter-toolbar-form" onsubmit="return false;">
                    <div class="filter-chips" aria-label="Filter tools">
                        <a class="filter-icon-btn" href="devtool.php" aria-label="Home">
                        </a>
                        <button type="button" class="filter-icon-btn" aria-label="Copy">
                        </button>
                    </div>
                    <div class="filter-search">
                        <input type="text" id="filterInput" name="q" value="<?php echo htmlspecialchars($rawQ, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search for name, office, designation, etc" autocomplete="off">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="popup-form" id="popupForm2">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="form_title">Edit User</p>
                <i class="ri-close-fill close-icon" id="close_popup"></i>
            </div>
            <form action="" id="account_form_container" class="f-container" method="post">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Employee ID</label>
                                <input type="text" class="emp_id form-custom-input" name="emp_id" id="emp_id" value="<?php echo htmlspecialchars($nextEmpId, ENT_QUOTES, 'UTF-8'); ?>" placeholder="emp_id" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">First Name</label>
                                <input type="text" class="emp_fn form-custom-input" name="emp_fn" placeholder="emp_fn">
                            </div>
                            <div class="label-input__container">
                                <label for="">Middle Initial (optional)</label>
                                <input type="text" class="emp_mi form-custom-input" name="emp_mi" placeholder="emp_mi">
                            </div>
                            <div class="label-input__container">
                                <label for="">Last Name</label>
                                <input type="text" class="emp_ln form-custom-input" name="emp_ln" placeholder="emp_ln">
                            </div>
                            <div class="label-input__container">
                                <label for="">Access Level</label>
                                <input type="text" class="acl form-custom-input" name="acl" placeholder="access level">
                            </div>
                            <div class="label-input__container dynamic_input_udc">
                                <label for="">UDC</label>
                                <input type="text" class="udc form-custom-input" name="udc" id="udc" value="<?php echo htmlspecialchars($nextUdc, ENT_QUOTES, 'UTF-8'); ?>" data-default-udc="<?php echo htmlspecialchars($nextUdc, ENT_QUOTES, 'UTF-8'); ?>" placeholder="user designation code" readonly>
                            </div>
                            <div class="label-input__container tag_input_container">
                                <label for="tag">Tag</label>
                                <select class="tag form-custom-input" name="tag" id="tag">
                                    <option value="Other Professional Services" selected>Other Professional Services</option>
                                    <option value="Janitorial Services">Janitorial Services</option>
                                    <option value="Security Services">Security Services</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Section/Unit</label>
                                <input type="text" class="section form-custom-input" name="section" placeholder="section">
                            </div>
                            <div class="label-input__container">
                                <label for="">Division</label>
                                <input type="text" class="division form-custom-input" name="division" placeholder="division">
                            </div>
                            <div class="label-input__container">
                                <label for="">Designation</label>
                                <!--                   <input multiple type="text" class="position" name="position" placeholder="section">-->
                                <select multiple class="designation form-custom-multi-input" name="designation[]" id="designation" onchange="check_selected(this)">
                                    <?php
                                    while ($row = $fetch_designations->fetch(PDO::FETCH_ASSOC)) {
                                    ?>
                                        <option value="<?php echo $row['designation']; ?>"><?php echo $row['designation']; ?></option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="label-input__container dynamic_input_office hidden_input">
                                <label for="">Office</label>
                                <input type="text" class="office form-custom-input" name="office" placeholder="office">
                            </div>

                            <?php
                            ?>
                            <div class="label-input__container">
                                <label for="">Password</label>
                                <input type="text" class="password form-custom-input" name="password" placeholder="password">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn transparent success btn-dynamic" name="" type="submit">SAVE</button>
                        <button class="btn secondary transparent" id="close_popup2" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">User Summary</h2>
        <div class="content-wrapper">
            <table class="table content_table content_table--dashboard" id="my-Table">
                <thead>
                    <th>ID</th>
                    <th>EMP ID</th>
                    <th>FN</th>
                    <th>MI</th>
                    <th>LN</th>
                    <th>Office</th>
                    <th>Section</th>
                    <th>Division</th>
                    <th>Designation</th>
                    <th>UDC</th>
                    <th>Tag</th>
                    <th>Access Level</th>
                    <th>Created At</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </thead>
                <tr>
                    <?php while ($row = $fetch_users->fetch(PDO::FETCH_ASSOC)) : ?>
                        <td data-label="id"><?php echo $row['id']; ?></td>
                        <td data-label="emp_id"><?php echo $row['emp_id']; ?></td>
                        <td data-label="emp_fn"><?php echo $row['emp_fn']; ?></td>
                        <td data-label="emp_mi"><?php echo $row['emp_mi']; ?></td>
                        <td data-label="emp_ln"><?php echo $row['emp_ln']; ?></td>
                        <td data-label="office"><?php echo $row['office']; ?></td>
                        <td data-label="section"><?php echo $row['section']; ?></td>
                        <td data-label="division"><?php echo $row['division']; ?></td>
                        <td data-label="designation"><?php echo $row['designation']; ?></td>
                        <td data-label="udc"><?php echo $row['udc']; ?></td>
                        <td data-label="emp_tag"><?php echo htmlspecialchars((string) ($row['emp_tag'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="acl"><?php echo $row['access_level']; ?></td>
                        <td data-label="created_at"><?php echo $row['created_at']; ?></td>

                        <td data-label="password" class="hidden"><?php echo $row['password']; ?></td>

                        <td data-label=""><button class="btn-target btn success popupForm-edit_user" id="btn_edit" name="btn-edit" type="button">Edit</button></td>
                        <td data-label="Delete"><a onclick="if(!confirm('Are you sure to delete this user?')) return false;"
                                href='<?php echo '../../protected/handler/devtool_module/delete_user_module/delete_user_handler.inc.php?deleteid=' . htmlspecialchars($row['emp_id']) . ''; ?>'
                                class="btn danger">Delete</a></td>
                </tr>
            <?php endwhile; ?>
            </table>
        </div>
        <!-- Pagination links -->
        <div class="pagination">
            <div class="pagination_container pagination_container--modern">
                <div class="pagination_navigation pagination_navigation--modern">
                    <?php if ($currentPage > 1): ?>
                        <a class="pagination_btn_modern" href="?page=<?php echo ($currentPage - 1); ?>&rowsPerPage=<?php echo $rowsPerPage; ?><?php echo $qsDevtool; ?>">Previous</a>
                    <?php endif; ?>

                    <div class="pagination_pages pagination_pages--modern">
                        <?php
                        $pageRange = 5;
                        $startPage = max(1, $currentPage - floor($pageRange / 2));
                        $endPage = min($totalPages, $startPage + $pageRange - 1);

                        if ($endPage - $startPage + 1 < $pageRange) {
                            $startPage = max(1, $endPage - $pageRange + 1);
                        }

                        if ($startPage > 1): ?>
                            <a href="?page=1&rowsPerPage=<?php echo $rowsPerPage; ?><?php echo $qsDevtool; ?>">1</a>
                            <?php if ($startPage > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&rowsPerPage=<?php echo $rowsPerPage; ?><?php echo $qsDevtool; ?>" <?php if ($i == $currentPage) echo 'class="active"'; ?>><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $totalPages; ?>&rowsPerPage=<?php echo $rowsPerPage; ?><?php echo $qsDevtool; ?>"><?php echo $totalPages; ?></a>
                        <?php endif; ?>
                    </div>

                    <?php if ($currentPage < $totalPages): ?>
                        <a class="pagination_btn_modern" href="?page=<?php echo ($currentPage + 1); ?>&rowsPerPage=<?php echo $rowsPerPage; ?><?php echo $qsDevtool; ?>">Next</a>
                    <?php endif; ?>
                </div>
                <div class="pagination_info">
                    <?php
                    $startEntry = $displayTotal > 0 ? ($currentPage - 1) * $rowsPerPage + 1 : 0;
                    $endEntry = $displayTotal > 0 ? min($startEntry + $rowsPerPage - 1, $displayTotal) : 0;
                    echo $displayTotal ? "Showing $endEntry of $displayTotal results" : "NO DATA TO DISPLAY";
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!--=============== MAIN.JS ===============!-->
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/popscript.js"></script>
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
                if (v === '') u.searchParams.delete('q');
                else u.searchParams.set('q', v);
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
<?php elseif (trim($rawQ) !== '' && $q !== '' && $displayTotal < 1): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showNotify === 'function') {
            showNotify('No matching users for your search.', 'warning', 2200);
        }
    });
</script>
<?php endif; ?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script>
    var nextEmpId = <?php echo json_encode($nextEmpId); ?>;
    var nextUdc = <?php echo json_encode($nextUdc); ?>;
    var nextUdcFetchUrl = '../../protected/handler/devtool_module/add_user_module/next_udc.php';
    var nextUdcFetchInFlight = null;

    function setUdcField(value, readOnly) {
        var udcEl = document.getElementById('udc');
        if (!udcEl) return;
        var resolved = (value || nextUdc || udcEl.getAttribute('data-default-udc') || '').trim();
        udcEl.value = resolved;
        udcEl.readOnly = !!readOnly;
        if (readOnly && resolved) {
            udcEl.setAttribute('data-default-udc', resolved);
        }
    }

    function prefetchNextUdc() {
        if (nextUdcFetchInFlight) return nextUdcFetchInFlight;
        nextUdcFetchInFlight = fetch(nextUdcFetchUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function(res) {
            if (!res.ok) throw new Error('UDC fetch failed');
            return res.json();
        }).then(function(data) {
            if (data && data.udc) {
                nextUdc = data.udc;
            }
        }).catch(function() {
            // Keep existing nextUdc / data-default-udc
        }).finally(function() {
            nextUdcFetchInFlight = null;
        });
        return nextUdcFetchInFlight;
    }

    function prepareAddUserForm() {
        var empIdInput = document.getElementById('emp_id');
        document.getElementById('form_title').textContent = 'Add User';
        document.getElementById('account_form_container').setAttribute(
            'action',
            '../../protected/handler/devtool_module/add_user_module/add_user_handler.inc.php'
        );
        if (empIdInput) empIdInput.readOnly = true;

        setUdcField(nextUdc, true);
        document.querySelector('.emp_id').value = nextEmpId;
        document.querySelector('.emp_fn').value = '';
        document.querySelector('.emp_mi').value = '';
        document.querySelector('.emp_ln').value = '';
        document.querySelector('.office').value = '';
        document.querySelector('.section').value = '';
        document.querySelector('.division').value = '';
        document.querySelector('.password').value = '';
        document.querySelector('.acl').value = '';
        document.querySelector('.tag').value = 'Other Professional Services';

        var designationEl = document.querySelector('.designation');
        if (designationEl) {
            Array.from(designationEl.options).forEach(function(opt) { opt.selected = false; });
            designationEl.dispatchEvent(new Event('change', { bubbles: true }));
        }

        document.querySelector('.btn-dynamic').setAttribute('name', 'add_new_user');
        document.querySelector('.dynamic_input_udc').style.display = '';
        document.querySelector('.dynamic_input_office').classList.remove('hidden_input');
    }

    function prepareEditUserForm(row) {
        var empIdInput = document.getElementById('emp_id');
        document.getElementById('account_form_container').setAttribute(
            'action',
            '../../protected/handler/devtool_module/edit_user_module/edit_account_handler.php'
        );
        document.getElementById('form_title').textContent = 'Edit User';
        if (empIdInput) empIdInput.readOnly = true;

        var empId = row.querySelector('[data-label="emp_id"]').textContent.trim();
        var emp_fn = row.querySelector('[data-label="emp_fn"]').textContent.trim();
        var emp_mi = row.querySelector('[data-label="emp_mi"]').textContent.trim();
        var emp_ln = row.querySelector('[data-label="emp_ln"]').textContent.trim();
        var office = row.querySelector('[data-label="office"]').textContent.trim();
        var section = row.querySelector('[data-label="section"]').textContent.trim();
        var division = row.querySelector('[data-label="division"]').textContent.trim();
        var designation = row.querySelector('[data-label="designation"]').textContent.trim();
        var password = row.querySelector('[data-label="password"]').textContent.trim();
        var acl = row.querySelector('[data-label="acl"]').textContent.trim();
        var udc = row.querySelector('[data-label="udc"]').textContent.trim();
        var emp_tag_el = row.querySelector('[data-label="emp_tag"]');
        var emp_tag = emp_tag_el ? emp_tag_el.textContent.trim() : '';
        if (!emp_tag) emp_tag = 'Other Professional Services';

        document.querySelector('.emp_id').value = empId;
        document.querySelector('.emp_fn').value = emp_fn;
        document.querySelector('.emp_mi').value = emp_mi;
        document.querySelector('.emp_ln').value = emp_ln;
        document.querySelector('.office').value = office;
        document.querySelector('.section').value = section;
        document.querySelector('.division').value = division;
        document.querySelector('.password').value = password;
        document.querySelector('.acl').value = acl;
        setUdcField(udc, false);
        document.querySelector('.tag').value = emp_tag;

        var formattedPosition = designation.split(',');
        var designationEl = document.querySelector('.designation');
        if (designationEl) {
            Array.from(designationEl.options).forEach(function(opt) {
                opt.selected = formattedPosition.indexOf(opt.value) !== -1;
            });
            designationEl.dispatchEvent(new Event('change', { bubbles: true }));
        }

        document.querySelector('.btn-dynamic').setAttribute('name', 'edit_account');
        document.querySelector('.dynamic_input_udc').style.display = '';
        document.querySelector('.dynamic_input_office').classList.remove('hidden_input');
    }

    // Run before popscript.js openPopup2 (bubble) so fields are ready when the modal appears.
    var addUserWrap = document.querySelector('.popupForm-add');
    if (addUserWrap) {
        addUserWrap.addEventListener('click', function(e) {
            e.preventDefault();
            prepareAddUserForm();
            prefetchNextUdc();
        }, true);
    }

    document.addEventListener('DOMContentLoaded', function() {
        prefetchNextUdc();
    });

    document.querySelectorAll('.btn-target').forEach(function(button) {
        if (button.id === 'btn_add_user') return;

        button.addEventListener('click', function() {
            if (this.getAttribute('id') !== 'btn_edit') return;
            var row = this.closest('tr');
            if (!row) return;
            prepareEditUserForm(row);
        }, true);
    });
</script>

<script>
    function check_selected(selectObject) {
        let selectedOptions = selectObject.selectedOptions;

        for (let i = 0; i < selectedOptions.length; i++) {
            // console.log(selectedOptions[i]);

            if (selectedOptions[i].text === "Officer-In-Charge (PENR Office)") {
                // Remove classes to show inputs
                if (document.querySelector('.dynamic_input')) {
                    document.querySelector('.dynamic_input').classList.remove("hidden_input");
                }
                if (document.querySelector('.dynamic_input2')) {
                    document.querySelector('.dynamic_input2').classList.remove("hidden_input");
                }
                if (document.querySelector('.dynamic_input3')) {
                    document.querySelector('.dynamic_input3').classList.remove("hidden_input");
                }

                // Exit the loop early if match is found
                break;
            } else {
                // Add classes to hide inputs
                if (document.querySelector('.dynamic_input')) {
                    document.querySelector('.dynamic_input').classList.add("hidden_input");
                }
                if (document.querySelector('.dynamic_input2')) {
                    document.querySelector('.dynamic_input2').classList.add("hidden_input");
                }
                if (document.querySelector('.dynamic_input3')) {
                    document.querySelector('.dynamic_input3').classList.add("hidden_input");
                }
            }
        }
    }
</script>
</body>

</html>