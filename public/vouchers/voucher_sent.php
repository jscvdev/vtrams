<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Sent');
include('../../protected/handler/voucher_sent_module/voucher_sent_errhandler.inc.php');
require '../../protected/core/components/notifications/err_handler_custom_alert.php';
check_voucher_sent_errors();

require_once __DIR__ . '/checklist_config.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/cursor_pagination_helper.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_portal_query_helper.php';
$dashboard_voucher_types = checklist_types_with_labels();
$voucher_type_filter = isset($_GET['voucher_type']) && $_GET['voucher_type'] !== 'all' ? trim((string) $_GET['voucher_type']) : 'all';

$rowsPerPage = clamp_int($_GET['rowsPerPage'] ?? null, 1, 50, 50);
$maxBrowse = 100;
$rawQ = (string) ($_GET['q'] ?? '');
$q = filterInput($rawQ);
$invalidSearch = (trim($rawQ) !== '' && $q === '');

$sentSearchCols = [
    'processing_no', 'ors_no', 'dv_no', 'ada_check_no', 'payee', 'address', 'particulars',
    'tin_employee_no', 'voucher_type', 'remarks', 'sender_remarks', 'datetime_forwarded',
    'datetime_encoded', 'sender_udc', 'receiver_udc', 'encoded_by', 'encoded_from',
    'forwarded_by', 'process_status', 'process_history', 'supporting_documents',
];

$searchParams = [];
$searchSql = '';
if (!$invalidSearch && $q !== '') {
    $searchSql = voucher_portal_like_search_fragment($pdo, 'voucher_sent', $q, $sentSearchCols, $searchParams);
}

$udc_param = '%' . $_SESSION['logged_user_udc'] . '%';

if ($invalidSearch) {
    $dbCount = 0;
} else {
    $countSql = 'SELECT COUNT(*) AS total FROM voucher_sent WHERE sender_udc LIKE :udc AND office_to = :office_to';
    if ($voucher_type_filter !== 'all') {
        $countSql .= ' AND voucher_type = :voucher_type';
    }
    $countSql .= $searchSql;
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->bindValue(':udc', $udc_param, PDO::PARAM_STR);
    $stmtCount->bindValue(':office_to', $_SESSION['logged_user_office'], PDO::PARAM_STR);
    if ($voucher_type_filter !== 'all') {
        $stmtCount->bindValue(':voucher_type', $voucher_type_filter, PDO::PARAM_STR);
    }
    foreach ($searchParams as $key => $pair) {
        $stmtCount->bindValue($key, $pair[0], $pair[1]);
    }
    $stmtCount->execute();
    $dbCount = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
}

$displayTotal = min($dbCount, $maxBrowse);
$totalPages = $displayTotal > 0 ? (int) ceil($displayTotal / $rowsPerPage) : 1;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, min($currentPage, $totalPages));
$offset = ($currentPage - 1) * $rowsPerPage;
$fetchLimit = $displayTotal > 0 ? min($rowsPerPage, max(0, $maxBrowse - $offset)) : 0;

$dataSql = 'SELECT * FROM voucher_sent WHERE sender_udc LIKE :udc AND office_to = :office_to';
if ($voucher_type_filter !== 'all') {
    $dataSql .= ' AND voucher_type = :voucher_type';
}
$dataSql .= $searchSql . ' ORDER BY processing_no DESC LIMIT :lim OFFSET :off';
$fetch_voucher_sent_data = $pdo->prepare($dataSql);
$fetch_voucher_sent_data->bindValue(':udc', $udc_param, PDO::PARAM_STR);
$fetch_voucher_sent_data->bindValue(':office_to', $_SESSION['logged_user_office'], PDO::PARAM_STR);
if ($voucher_type_filter !== 'all') {
    $fetch_voucher_sent_data->bindValue(':voucher_type', $voucher_type_filter, PDO::PARAM_STR);
}
foreach ($searchParams as $key => $pair) {
    $fetch_voucher_sent_data->bindValue($key, $pair[0], $pair[1]);
}
$fetch_voucher_sent_data->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$fetch_voucher_sent_data->bindValue(':off', $offset, PDO::PARAM_INT);
$fetch_voucher_sent_data->execute();
$sentRows = $fetch_voucher_sent_data->fetchAll(PDO::FETCH_ASSOC);

$sentHistoryMap = [];
$processingNos = [];
foreach ($sentRows as $sentRow) {
    $pn = isset($sentRow['processing_no']) ? trim((string) $sentRow['processing_no']) : '';
    if ($pn !== '') {
        $processingNos[$pn] = true;
    }
}

if (!empty($processingNos)) {
    $processingNos = array_keys($processingNos);
    $inPlaceholders = implode(',', array_fill(0, count($processingNos), '?'));
    $historySql = "
        SELECT processing_no, action_by, action, action_from, office_from
        FROM voucher_action_logs
        WHERE processing_no IN ($inPlaceholders)
        ORDER BY processing_no ASC, datetime_action ASC
    ";
    $historyStmt = $pdo->prepare($historySql);
    foreach ($processingNos as $i => $pn) {
        $historyStmt->bindValue($i + 1, $pn, PDO::PARAM_STR);
    }
    $historyStmt->execute();
    while ($historyRow = $historyStmt->fetch(PDO::FETCH_ASSOC)) {
        $pn = trim((string) ($historyRow['processing_no'] ?? ''));
        if ($pn === '') {
            continue;
        }
        $historyLine = trim((string) ($historyRow['action_by'] ?? '')) . ' | ' .
            trim((string) ($historyRow['action'] ?? '')) . ' | ' .
            trim((string) ($historyRow['action_from'] ?? '')) . ' | ' .
            trim((string) ($historyRow['office_from'] ?? ''));
        if (!isset($sentHistoryMap[$pn])) {
            $sentHistoryMap[$pn] = [];
        }
        $sentHistoryMap[$pn][] = $historyLine;
    }
}

$totalRows = $displayTotal;

?>
<!--=============== MAIN ===============!-->
<div class="main main--voucher-dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Sent</h1>
    </header>
    <style>
        /* Keep sent filter toolbar in one row */
        #sentFilterForm {
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            width: 100%;
            gap: 10px;
        }

        #sentFilterForm .filter-chips {
            flex: 0 0 auto;
            flex-wrap: nowrap !important;
        }

        #sentFilterForm .filter-search {
            flex: 1 1 auto;
            min-width: 0 !important;
        }

        /* Modernized voucher type dropdown */
        #sentFilterForm .filter-type-select.filter-type-select--modern {
            position: relative;
            display: inline-flex;
            align-items: center;
            min-width: 280px;
            height: 42px;
            padding: 0;
            border: 1px solid #d4dbe6;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            overflow: visible;
            z-index: 20;
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern::after {
            content: "";
            position: absolute;
            right: 14px;
            top: 50%;
            width: 8px;
            height: 8px;
            border-right: 2px solid #6b7280;
            border-bottom: 2px solid #6b7280;
            transform: translateY(-60%) rotate(45deg);
            pointer-events: none;
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom::after {
            transition: transform 120ms ease;
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom.is-open::after {
            transform: translateY(-35%) rotate(-135deg);
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-trigger {
            width: 100%;
            height: 100%;
            border: none;
            background: transparent;
            text-align: left;
            padding: 0 34px 0 14px;
            color: #1f2937;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.25;
            cursor: pointer;
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            z-index: 30;
            max-height: 280px;
            overflow-y: auto;
            padding: 6px;
            border-radius: 12px;
            border: 1px solid #d7dee8;
            background: #ffffff;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
            display: none;
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom.is-open .filter-type-menu {
            display: block;
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option {
            width: 100%;
            border: none;
            background: transparent;
            border-radius: 8px;
            padding: 9px 10px;
            text-align: left;
            font-size: 14px;
            line-height: 1.2;
            color: #1f2937;
            cursor: pointer;
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option:hover {
            background: #f3f6fb;
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option.is-active {
            background: #e8f0ff;
            color: #1d4ed8;
            font-weight: 600;
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern:hover {
            border-color: #c2ccda;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        #sentFilterForm .filter-type-select.filter-type-select--modern:focus-within {
            border-color: #8fb2ff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
    </style>
    <div class="voucher-card voucher-card--filter">
        <div class="filter-toolbar">
            <div class="filter-left">
                <form method="GET" action="" id="sentFilterForm" class="filter-toolbar-form">
                    <div class="filter-chips" aria-label="Voucher filter tools">
                        <a class="filter-icon-btn" href="voucher_sent.php" aria-label="Home">
                        </a>
                        <button type="button" class="filter-icon-btn" aria-label="Copy">
                        </button>
                        <label class="filter-type-select filter-type-select--modern filter-type-select--custom" id="filterTypeDropdown" aria-label="Filter by voucher type">
                            <?php
                            $active_type_label = 'All Types';
                            foreach ($dashboard_voucher_types as $type_value => $type_label) {
                                if ($voucher_type_filter === (string) $type_value) {
                                    $active_type_label = (string) $type_label;
                                    break;
                                }
                            }
                            ?>
                            <input type="hidden" name="voucher_type" id="filterInputType" value="<?= htmlspecialchars((string) $voucher_type_filter, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="button" class="filter-type-trigger" id="filterTypeTrigger" aria-haspopup="listbox" aria-expanded="false">
                                <?= htmlspecialchars((string) $active_type_label, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            <div class="filter-type-menu" id="filterTypeMenu" role="listbox" aria-label="Voucher type options">
                                <button type="button" class="filter-type-option <?= $voucher_type_filter === 'all' ? 'is-active' : '' ?>" data-value="all">All Types</button>
                                <?php foreach ($dashboard_voucher_types as $type_value => $type_label): ?>
                                    <button
                                        type="button"
                                        class="filter-type-option <?= $voucher_type_filter === (string) $type_value ? 'is-active' : '' ?>"
                                        data-value="<?= htmlspecialchars((string) $type_value, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $type_label, ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </label>
                    </div>
                    <div class="filter-search">
                        <input type="text" id="filterInput" name="q" value="<?php echo htmlspecialchars($rawQ, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search for payee, processing no., ORS, DV, etc" autocomplete="off">
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="popup-form" id="popupForm">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="form_title">Forward Document</p>
                <i class="ri-close-fill close-icon" id="close_popup4"></i>
            </div>
            <form action="../../protected/handler/voucher_sent_module/voucher_sent_handler.php" class="f-container" method="post" id="myIncomingForm">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Processing No.</label>
                                <input type="text" name="processing_no" class="processing_no form-custom-input" id="processing_no" value="" placeholder="Processing No." required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">ORS No.</label>
                                <input type="text" name="ors_no" class="ors_no form-custom-input" id="ors_no" value="" placeholder="ORS No." readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">ADA/Check No.</label>
                                <input type="text" name="ada_check_no" class="ada_check_no form-custom-input" id="ada_check_no" value="" placeholder="ADA/Check No." readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">DV No.</label>
                                <input type="text" name="dv_no" class="dv_no form-custom-input" id="dv_no" value="" placeholder="DV No." readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Payee</label>
                                <input type="text" name="payee" class="payee form-custom-input" id="payee" value="" placeholder="Payee" required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Address</label>
                                <input type="text" name="address" class="address form-custom-input" id="address" value="" placeholder="Address" required readonly>
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Particulars</label>
                                <textarea name="particulars" id="particulars" cols="30" rows="10" class="multi-line-input particulars form-custom-multi-input" placeholder="Particulars ...." required readonly></textarea>
                            </div>
                            <div class="label-input__container">
                                <label for="">TIN/Employee No.</label>
                                <input type="text" name="tin_employee_no" class="tin_employee_no form-custom-input" id="tin_employee_no" value="" placeholder="TIN/Employee No." readonly>
                            </div>
                            <div class="label-input__container number-input amount_primary_block">
                                <label for="">Amount</label>
                                <input type="text" name="string_amount" class="string_amount form-custom-input" placeholder="Amount" required readonly>
                            </div>
                            <div class="label-input__container number-input hidden_input">
                                <label for="">Amount</label>
                                <input type="number" min="1" oninput="this.value =
 !!this.value && Math.abs(this.value) >= 1 ? Math.abs(this.value) : null" name="amount" class="amount form-custom-input" value="1" placeholder="Amount" required readonly>
                            </div>
                            <!-- Only shown when an original/charged pair exists -->
                            <div class="label-input__container number-input hidden_input original_charged_container">
                                <label for="">Original Amount</label>
                                <input type="text" name="original_string_amount" class="original_string_amount form-custom-input" id="original_string_amount" placeholder="Original Amount" readonly>
                            </div>
                            <div class="label-input__container number-input charged_amount_container" style="display: none;">
                                <label for="">Charged Amount (Edited)</label>
                                <input type="text" name="charged_string_amount" class="charged_string_amount form-custom-input" id="charged_string_amount" placeholder="Charged Amount (Edited)" style="color: red;" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Voucher Date</label>
                                <input type="date" name="voucher_date" class="voucher_date form-custom-input" id="voucher_date" value="" required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Remarks</label>
                                <input type="text" name="sender_remarks" class="sender_remarks form-custom-input" id="sender_remarks" value="" placeholder="Sender Remarks" readonly>
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Combined Remarks</label>
                                <input type="text" name="combined_remarks" class="combined_remarks form-custom-input" id="combined_remarks" value="" placeholder="Combined Remarks" readonly>
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Office From</label>
                                <input type="text" name="office_from" class="office_from" id="office_from" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Office To</label>
                                <input type="text" name="office_to" class="office_to" id="office_to" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">SUDC</label>
                                <input type="text" name="sender_udc" class="sender_udc" id="sender_udc" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">RUDC</label>
                                <input type="text" name="receiver_udc" class="receiver_udc" id="receiver_udc" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded By</label>
                                <input type="text" name="encoded_by" class="encoded_by" id="encoded_by" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded From</label>
                                <input type="text" name="encoded_from" class="encoded_from" id="encoded_from" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Datetime Encoded</label>
                                <input type="text" name="datetime_encoded" class="datetime_encoded" id="datetime_encoded" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Process Status</label>
                                <input type="text" name="process_status" class="process_status" id="process_status" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Voucher Type</label>
                                <input type="text" name="voucher_type" class="voucher_type" id="voucher_type" value="">
                            </div>
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <?php if ($_SESSION['acl'] != 888) : ?>
                            <button class="btn warning transparent" name="return_voucher" type="submit">RETURN</button>
                        <?php endif; ?>
                        <button class="btn secondary transparent" id="close_popup3" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="overlay" id="overlay"></div>
    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Sent Summary</h2>
        <style>
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

            .voucher-table-empty-hint {
                width: 100%;
                min-height: 220px;
                justify-content: center;
                align-items: center;
                font-weight: 500;
                color: rgb(107 114 128);
                text-transform: uppercase;
                font-size: 12px;
                letter-spacing: 0.04em;
            }

            .voucher-table-empty-hint p {
                margin: 0;
            }

            .voucher-pagination-footer {
                position: static;
                background: #fff;
                border-top: 1px solid rgba(229, 231, 235, 1);
                padding: 10px 0 0;
                margin-top: auto;
            }
        </style>
        <div class="content-wrapper">
            <table class="table content_table content_table--dashboard" id="my-Table">
                <thead>
                    <tr>
                        <th>Processing No.</th>
                        <th>ORS No.</th>
                        <th>DV No.</th>
                        <th>ADA/Check No.</th>
                        <th>Payee Name</th>
                        <th>Address</th>
                        <th>Particulars</th>
                        <th>Amount</th>
                        <th>Voucher Date</th>
                        <th>Type</th>
                        <th>Date/Time Forwarded</th>
                        <th>History</th>
                        <th>Return</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($sentRows as $row) {
                        $rowProcessingNo = isset($row['processing_no']) ? trim((string) $row['processing_no']) : '';
                        $historyFromActionLogs = isset($sentHistoryMap[$rowProcessingNo]) ? implode("\n", $sentHistoryMap[$rowProcessingNo]) : '';
                        $historyForModal = $historyFromActionLogs !== '' ? $historyFromActionLogs : (isset($row['process_history']) ? (string) $row['process_history'] : '');
                    ?>
                <tr>
                                <td data-label="processing_no"><?php echo $row['processing_no']; ?></td>
                                <td data-label="ors_no"><?php echo $row['ors_no']; ?></td>
                                <td data-label="dv_no"><?php echo $row['dv_no']; ?></td>
                                <td data-label="ada_check_no"><?php echo $row['ada_check_no']; ?></td>
                                <td data-label="payee"><?php echo $row['payee']; ?></td>
                                <td data-label="address" class="status"><?php echo $row['address']; ?></td>
                                <td data-label="particulars"><?php echo $row['particulars']; ?></td>
                                <td data-label="amount" class="amount">
                                    <?php
                                    $baseAmount = $row['amount'];
                                    $charged = isset($row['charged_amount']) ? trim((string)$row['charged_amount']) : '';
                                    if ($charged !== '' && $charged !== '0' && $charged !== '0.00') {
                                        echo '<span style="color: red;">' . htmlspecialchars($charged) . '</span>';
                                    } else {
                                        echo htmlspecialchars($baseAmount);
                                    }
                                    ?>
                                </td>
                                <td data-label="amount_original" class="hidden"><?php echo htmlspecialchars((string)$row['amount']); ?></td>
                                <td data-label="voucher_date"><?php echo $row['voucher_date']; ?></td>
                                <td data-label="voucher_type_display" class="voucher-type-cell"><?php echo voucher_type_badge_html((string)($row['voucher_type'] ?? '')); ?></td>
                                <?php if (isset($row['charged_amount'])) : ?>
                                    <td data-label="charged_amount" class="hidden"><?php echo $row['charged_amount']; ?></td>
                                <?php else : ?>
                                    <td data-label="charged_amount" class="hidden"></td>
                                <?php endif; ?>
                                <td data-label="datetime_forwarded"><?php echo $row['datetime_forwarded']; ?></td>
                                <td data-label="office_from" class="hidden"><?php echo $row['office_from']; ?></td>
                                <td data-label="office_to" class="hidden"><?php echo $row['office_to']; ?></td>
                                <td data-label="sender_udc" class="hidden"><?php echo $row['sender_udc']; ?></td>
                                <td data-label="receiver_udc" class="hidden"><?php echo $row['receiver_udc']; ?></td>
                                <td data-label="encoded_by" class="hidden"><?php echo $row['encoded_by']; ?></td>
                                <td data-label="encoded_from" class="hidden"><?php echo $row['encoded_from']; ?></td>
                                <td data-label="datetime_encoded" class="hidden"><?php echo $row['datetime_encoded']; ?></td>
                                <td data-label="process_status" class="hidden"><?php echo $row['process_status']; ?></td>
                                <td data-label="remarks" class="hidden"><?php echo $row['remarks']; ?></td>
                                <td data-label="sender_remarks" class="hidden"><?php echo $row['sender_remarks']; ?></td>
                                <td data-label="tin_employee_no" class="hidden"><?php echo $row['tin_employee_no']; ?></td>
                                <td data-label="voucher_type" class="hidden"><?php echo $row['voucher_type']; ?></td>
                                <td data-label="process_history" class="hidden"><?php echo htmlspecialchars($historyForModal, ENT_QUOTES, 'UTF-8'); ?></td>

                                <td data-label="history">
                                    <button class="btn tertiary" name="btn-history" type="button">View</button>
                                </td>

                                <td data-label="return" class="pPop" id="openPopup"><button class="btn warning" name="btn-return" value="" type="button">Return</button></td>
                </tr>
            <?php
                    }

            ?>
                </tbody>
            </table>
            <div class="no-display voucher-table-empty-hint" style="<?php echo $displayTotal < 1 ? 'display:flex;' : 'display:none;'; ?>">
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
                        <?php elseif ($currentPage > 1): ?>
                            <a class="pagination_btn_modern" href="<?php echo htmlspecialchars(build_voucher_portal_page_url($currentPage - 1, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                        <?php else: ?>
                            <button class="pagination_btn_modern" type="button" disabled>Previous</button>
                        <?php endif; ?>

                        <?php if ($displayTotal >= 1) : ?>
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
                                        echo '<a class="pagination_page_num' . $active . '" href="' . htmlspecialchars(build_voucher_portal_page_url($i, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
                                    }
                                } else {
                                    echo '<a class="pagination_page_num' . (1 == $currentPage ? ' active' : '') . '" href="' . htmlspecialchars(build_voucher_portal_page_url(1, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8') . '">1</a>';
                                    if ($startPage > 2) {
                                        echo '<span class="pagination_ellipsis">...</span>';
                                    }
                                    for ($i = max(2, $startPage); $i <= min($totalPages - 1, $endPage); $i++) {
                                        $active = ($i == $currentPage) ? ' active' : '';
                                        echo '<a class="pagination_page_num' . $active . '" href="' . htmlspecialchars(build_voucher_portal_page_url($i, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
                                    }
                                    if ($endPage < $totalPages - 1) {
                                        echo '<span class="pagination_ellipsis">...</span>';
                                    }
                                    echo '<a class="pagination_page_num' . ($totalPages == $currentPage ? ' active' : '') . '" href="' . htmlspecialchars(build_voucher_portal_page_url($totalPages, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8') . '">' . $totalPages . '</a>';
                                }
                                ?>
                            </div>

                            <?php if ($currentPage < $totalPages): ?>
                                <a class="pagination_btn_modern" href="<?php echo htmlspecialchars(build_voucher_portal_page_url($currentPage + 1, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                            <?php else: ?>
                                <button class="pagination_btn_modern" type="button" disabled>Next</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="pagination_info">
                        <?php echo $displayTotal < 1 ? 'NO DATA TO DISPLAY' : ($totalRows ? ('Showing ' . $endEntry . ' of ' . $totalRows . ' results') : 'NO DATA TO DISPLAY'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var typeFilter = document.getElementById('filterInputType');
        var form = document.getElementById('sentFilterForm');
        var dropdown = document.getElementById('filterTypeDropdown');
        var trigger = document.getElementById('filterTypeTrigger');
        var menu = document.getElementById('filterTypeMenu');
        if (typeFilter && form && dropdown && trigger && menu) {
            function closeDropdown() {
                dropdown.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            }

            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                var willOpen = !dropdown.classList.contains('is-open');
                if (willOpen) {
                    dropdown.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                } else {
                    closeDropdown();
                }
            });

            menu.querySelectorAll('.filter-type-option').forEach(function(optionBtn) {
                optionBtn.addEventListener('click', function() {
                    var selectedValue = this.getAttribute('data-value') || 'all';
                    typeFilter.value = selectedValue;
                    trigger.textContent = this.textContent.trim();
                    menu.querySelectorAll('.filter-type-option').forEach(function(btn) {
                        btn.classList.remove('is-active');
                    });
                    this.classList.add('is-active');
                    closeDropdown();
                    form.submit();
                });
            });

            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target)) {
                    closeDropdown();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDropdown();
                }
            });
        }

        var inp = document.getElementById('filterInput');
        if (!inp) return;
        var initial = String(inp.value || '');
        function applyFilterSearch() {
            var v = String(inp.value || '');
            if (v === initial) return;
            var u = new URL(window.location.href);
            u.searchParams.set('page', '1');
            u.searchParams.set('rowsPerPage', '50');
            if (v === '') {
                u.searchParams.delete('q');
            } else {
                u.searchParams.set('q', v);
            }
            window.location.href = u.toString();
        }
        inp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilterSearch();
            }
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
            showNotify('No matching sent vouchers for your search.', 'warning', 2200);
        }
    });
</script>
<?php endif; ?>

<!-- History / Remarks Modal -->
<style>
    /* History modal (modernized) */
    #historyModal .popupForm-box__container {
        max-width: 920px;
        border-radius: 14px;
        overflow: hidden;
    }

    #historyModal .popupForm-header__container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        border-bottom: 1px solid #e9ecef;
        background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
    }

    #historyModal .popupForm-header__container p {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 0.2px;
        color: #1f2937;
    }

    #historyModal .close-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        background: #f3f4f6;
        color: #374151;
        transition: background 0.15s ease;
    }

    #historyModal .close-icon:hover {
        background: #e5e7eb;
    }

    #historyModal .box-body__container {
        padding: 16px 18px;
        display: block;
        background: #fff;
    }

    #historyModal .hist-topbar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    #historyModal .hist-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #fafafa;
    }

    #historyModal .hist-pill label {
        margin: 0;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
    }

    #historyModal #hist_processing_no {
        width: 170px;
        height: 34px;
        padding: 6px 10px;
        font-weight: 700;
        letter-spacing: 0.3px;
        border-radius: 10px;
        border: 1px solid #d1d5db;
        background: #fff;
    }

    #historyModal .hist-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    #historyModal .hist-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #ffffff;
        padding: 12px;
        box-shadow: 0 1px 0 rgba(17, 24, 39, 0.02);
    }

    #historyModal .hist-card--full {
        grid-column: 1 / -1;
    }

    #historyModal .hist-card-title {
        margin: 0 0 8px 0;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #6b7280;
    }

    #historyModal .hist-content {
        min-height: 56px;
        max-height: 220px;
        overflow: auto;
        padding: 10px 10px;
        border-radius: 10px;
        background: #f9fafb;
        border: 1px solid #eef2f7;
        white-space: pre-wrap;
        line-height: 1.45;
        color: #111827;
        font-size: 13px;
    }

    #historyModal .hist-content--tall {
        max-height: 280px;
        min-height: 140px;
    }

    /* Ensure process history stays readable in a fixed viewport */
    #historyModal #hist_process_history {
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
    }

    /* Process history list (USER : action : section/unit) */
    #historyModal .hist-content--process-list {
        white-space: normal;
        padding: 12px;
    }

    #historyModal .hist-process-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    #historyModal .hist-process-item {
        display: flex;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 6px 10px;
        padding: 10px 12px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        font-size: 13px;
        line-height: 1.4;
        color: #111827;
    }

    #historyModal .hist-process-item__part {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: normal;
    }

    #historyModal .hist-process-item__part i {
        font-size: 15px;
        color: #6b7280;
        flex-shrink: 0;
    }

    #historyModal .hist-process-item__part span {
        white-space: normal;
        word-break: break-word;
    }

    #historyModal .hist-process-item__part--user i {
        color: #4f46e5;
    }

    #historyModal .hist-process-item__part--action i {
        color: #059669;
    }

    #historyModal .hist-process-item__part--section i {
        color: #b45309;
    }

    #historyModal .hist-process-sep {
        color: #d1d5db;
        font-weight: 700;
        user-select: none;
    }

    #historyModal .popupForm-footer__container {
        border-top: 1px solid #eef2f7;
        background: #fbfbfd;
        padding: 12px 18px;
    }

    #historyModal .footer-button__container {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    /* Responsive */
    @media (max-width: 840px) {
        #historyModal .hist-grid {
            grid-template-columns: 1fr;
        }

        #historyModal #hist_processing_no {
            width: 100%;
        }

        #historyModal .hist-pill {
            width: 100%;
            justify-content: space-between;
        }
    }
</style>

<div class="popup-form" id="historyModal" style="display:none;">
    <div class="popupForm-box__container">
        <div class="popupForm-header__container">
            <p>History &amp; Remarks</p>
            <i class="ri-close-fill close-icon" id="close_history_modal"></i>
        </div>
        <div class="f-container">
            <div class="box-body__container">
                <div class="hist-topbar">
                    <div class="hist-pill">
                        <label>Processing No.</label>
                        <input type="text" id="hist_processing_no" class="form-custom-input" readonly>
                    </div>
                </div>

                <div class="hist-grid">
                    <div class="hist-card">
                        <p class="hist-card-title">Sender remarks (latest)</p>
                        <div id="hist_sender_remarks" class="hist-content"></div>
                    </div>
                    <div class="hist-card">
                        <p class="hist-card-title">All remarks (combined)</p>
                        <div id="hist_combined_remarks" class="hist-content"></div>
                    </div>
                    <div class="hist-card hist-card--full">
                        <p class="hist-card-title">Process history</p>
                        <div id="hist_process_history" class="hist-content hist-content--tall"></div>
                    </div>
                </div>
            </div>

            <div class="popupForm-footer__container">
                <div class="footer-button__container">
                    <button class="btn secondary transparent" id="close_history_modal_btn" type="button">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="overlay" id="historyOverlay" style="display:none;"></div>
<!--=============== MAIN.JS ===============!-->
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/amount_helper.js"></script>
<script src="../../protected/js/voucher.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    function escapeHtml(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function renderProcessHistory(raw) {
        if (!raw || !String(raw).trim()) return '';
        // The DB may store newlines as literal "\n" (backslash + n), so normalize first.
        var normalized = String(raw)
            .replace(/\u00A0/g, ' ')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/\\n/g, '\n')
            .trim();

        var lines = normalized
            .split(/\n+/)
            .map(function(l) {
                return String(l).trim();
            })
            .filter(function(l) {
                return l !== '';
            });
        if (lines.length === 0) return '';
        function simplifyActionLabel(actionText) {
            var txt = String(actionText || '').trim();
            var byMatch = txt.match(/^(.+?)\s+by\b\s*:?\s*.*$/i);
            if (byMatch && byMatch[1]) {
                return byMatch[1].trim();
            }
            return txt;
        }

        var parts, user, action, section, office, html = '<ul class="hist-process-list">';
        for (var i = 0; i < lines.length; i++) {
            if (lines[i].indexOf('|') !== -1) {
                parts = lines[i].split(/\s*\|\s*/);
                user = (parts[0] || '').trim();
                action = simplifyActionLabel((parts[1] || '').trim());
                section = (parts[2] || '').trim();
                office = (parts.slice(3).join(' | ')).trim();
            } else {
                parts = lines[i].split(/\s*:\s*/);
                user = (parts[0] || '').trim();
                if (parts.length >= 3) {
                    action = simplifyActionLabel((parts[1] || '').trim());
                    section = (parts.slice(2).join(' : ')).trim();
                } else {
                    action = '';
                    section = (parts[1] || '').trim();
                }
                office = '';
            }
            html += '<li class="hist-process-item">';
            html += '<span class="hist-process-item__part hist-process-item__part--user"><i class="ri-user-line"></i><span>' + escapeHtml(user) + '</span></span>';
            if (action) {
                html += '<span class="hist-process-sep">·</span>';
                html += '<span class="hist-process-item__part hist-process-item__part--action"><i class="ri-check-double-line"></i><span>' + escapeHtml(action) + '</span></span>';
            }
            html += '<span class="hist-process-sep">·</span>';
            html += '<span class="hist-process-item__part hist-process-item__part--section"><i class="ri-building-4-line"></i><span>' + escapeHtml(section) + '</span></span>';
            if (office) {
                html += '<span class="hist-process-sep">·</span>';
                html += '<span class="hist-process-item__part hist-process-item__part--section"><i class="ri-community-line"></i><span>' + escapeHtml(office) + '</span></span>';
            }
            html += '</li>';
        }
        html += '</ul>';
        return html;
    }

    // Get all buttons with class 'btn-forward'
    var buttons = document.querySelectorAll('.btn');

    // Loop through each button and attach click event listener
    buttons.forEach(function(button) {
        button.addEventListener('click', function() {
            // Get the row associated with the clicked button
            var row = this.closest('tr');

            var name = this.getAttribute('name') || '';
            if (name === 'btn-history') {
                const modal = document.getElementById('historyModal');
                const overlay = document.getElementById('historyOverlay');

                const procNo = row.querySelector('[data-label="processing_no"]')?.textContent?.trim() || '';
                const senderRemarks = row.querySelector('[data-label="sender_remarks"]')?.textContent || '';
                const combinedRemarks = row.querySelector('[data-label="remarks"]')?.textContent || '';
                const processHistory = row.querySelector('[data-label="process_history"]')?.textContent || '';

                const procEl = document.getElementById('hist_processing_no');
                const senderEl = document.getElementById('hist_sender_remarks');
                const combinedEl = document.getElementById('hist_combined_remarks');
                const histEl = document.getElementById('hist_process_history');

                if (procEl) procEl.value = procNo;
                if (senderEl) senderEl.textContent = senderRemarks && senderRemarks.trim() !== '' ? senderRemarks.trim() : '';
                if (combinedEl) combinedEl.textContent = combinedRemarks && combinedRemarks.trim() !== '' ? combinedRemarks.trim() : '';
                if (histEl) {
                    histEl.classList.add('hist-content--process-list');
                    histEl.innerHTML = renderProcessHistory(processHistory);
                }

                if (modal) modal.style.display = 'block';
                if (overlay) overlay.style.display = 'block';
                return;
            }

            // Extract data from the row
            var processing_no = row.querySelector('[data-label="processing_no"]').textContent;
            var ors_no = row.querySelector('[data-label="ors_no"]').textContent;
            var dv_no = row.querySelector('[data-label="dv_no"]').textContent;
            var ada_check_no = row.querySelector('[data-label="ada_check_no"]').textContent;
            var payee = row.querySelector('[data-label="payee"]').textContent;
            var address = row.querySelector('[data-label="address"]').textContent;
            var particulars = row.querySelector('[data-label="particulars"]').textContent;
            var amountOriginalCell = row.querySelector('[data-label="amount_original"]');
            var amountOriginal = amountOriginalCell ? amountOriginalCell.textContent : row.querySelector('[data-label="amount"]').textContent;
            var voucher_date = row.querySelector('[data-label="voucher_date"]').textContent;
            var office_to = row.querySelector('[data-label="office_to"]').textContent;
            var office_from = row.querySelector('[data-label="office_from"]').textContent;
            var sender_udc = row.querySelector('[data-label="sender_udc"]').textContent;
            var receiver_udc = row.querySelector('[data-label="receiver_udc"]').textContent;
            var encoded_by = row.querySelector('[data-label="encoded_by"]').textContent;
            var encoded_from = row.querySelector('[data-label="encoded_from"]').textContent;
            var datetime_encoded = row.querySelector('[data-label="datetime_encoded"]').textContent;
            var process_status = row.querySelector('[data-label="process_status"]').textContent;
            var remarks = row.querySelector('[data-label="remarks"]').textContent;
            var sender_remarks = row.querySelector('[data-label="sender_remarks"]').textContent;
            var tin_employee_no = row.querySelector('[data-label="tin_employee_no"]').textContent;
            var voucher_type = row.querySelector('[data-label="voucher_type"]').textContent;
            var charged_amount_cell = row.querySelector('[data-label="charged_amount"]');
            var charged_amount = charged_amount_cell ? charged_amount_cell.textContent : '';

            var amount = isNonZeroAmount(charged_amount) ? charged_amount : amountOriginal;

            const convertedBack = normalizeAmountInput(String(amount));

            // Send it via AJAX to the server
            document.querySelector('.processing_no').value = processing_no;
            document.querySelector('.ors_no').value = ors_no;
            document.querySelector('.dv_no').value = dv_no;
            document.querySelector('.ada_check_no').value = ada_check_no;
            document.querySelector('.payee').value = payee;
            document.querySelector('.address').value = address;
            document.querySelector('.particulars').value = particulars;
            document.querySelector('.amount').value = convertedBack;
            document.querySelector('.string_amount').value = amount;
            document.querySelector('.voucher_date').value = voucher_date;
            document.querySelector('.office_from').value = office_from;
            document.querySelector('.office_to').value = office_to;
            document.querySelector('.sender_udc').value = sender_udc;
            document.querySelector('.receiver_udc').value = receiver_udc;
            document.querySelector('.encoded_by').value = encoded_by;
            document.querySelector('.encoded_from').value = encoded_from;
            document.querySelector('.datetime_encoded').value = datetime_encoded;
            document.querySelector('.process_status').value = process_status;
            document.querySelector('.sender_remarks').value = sender_remarks;
            document.querySelector('.combined_remarks').value = remarks;
            document.querySelector('.tin_employee_no').value = tin_employee_no;
            document.querySelector('.voucher_type').value = voucher_type;

            const amountPrimaryBlock = document.querySelector('.amount_primary_block');
            const originalContainer = document.querySelector('.original_charged_container');
            const chargedContainer = document.querySelector('.charged_amount_container');
            const stringAmountInput = document.querySelector('.string_amount');

            if (originalContainer) originalContainer.style.display = 'none';
            if (chargedContainer) chargedContainer.style.display = 'none';

            const originalStringInput = document.getElementById('original_string_amount');
            const chargedStringInput = document.getElementById('charged_string_amount');

            const hasCharged = isNonZeroAmount(charged_amount);

            if (hasCharged) {
                if (amountPrimaryBlock) amountPrimaryBlock.style.display = 'none';
                if (stringAmountInput) stringAmountInput.removeAttribute('required');
                if (chargedContainer) chargedContainer.style.display = 'flex';
                if (chargedStringInput) chargedStringInput.value = String(charged_amount || '').trim();
                if (originalStringInput) originalStringInput.value = '';
            } else {
                if (amountPrimaryBlock) amountPrimaryBlock.style.display = '';
                if (stringAmountInput) stringAmountInput.setAttribute('required', 'required');
                if (chargedContainer) chargedContainer.style.display = 'none';
                if (originalStringInput) originalStringInput.value = '';
                if (chargedStringInput) chargedStringInput.value = '';
            }

        });
    });
</script>
<script>
    (function() {
        const modal = document.getElementById('historyModal');
        const overlay = document.getElementById('historyOverlay');
        const closeX = document.getElementById('close_history_modal');
        const closeBtn = document.getElementById('close_history_modal_btn');

        function close() {
            if (modal) modal.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
        }

        if (closeX) closeX.addEventListener('click', close);
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (overlay) overlay.addEventListener('click', close);
    })();
</script>
</body>

</html>