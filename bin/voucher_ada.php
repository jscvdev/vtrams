<?php
/**
 * LDDAP-ADA voucher UI. Loaded via public/vouchers/voucher_ada.php so session, router, and asset URLs stay correct.
 */
include __DIR__ . '/../public/includes/header.php';
require_once __DIR__ . '/../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Voucher ADA');
include __DIR__ . '/../protected/handler/voucher_ada_module/voucher_ada_add/voucher_ada_add_errhandler.php';
include __DIR__ . '/../protected/handler/voucher_ada_module/voucher_ada_remove/voucher_ada_remove_errhandler.php';
include __DIR__ . '/../protected/handler/voucher_ada_multi/multi_errhandler.php';
require __DIR__ . '/../protected/core/components/notifications/err_handler_custom_alert.php';
require __DIR__ . '/../protected/core/components/notifications/custom_process_alert.php';
require_once __DIR__ . '/../protected/core/components/notifications/custom_alert.php';
require_once __DIR__ . '/../protected/core/components/notifications/notification.inc.php';
// include 'lddap.php';

check_ada_errors();
check_voucher_add_errors();
check_voucher_remove_errors();

// Load LDDAP-ADA signatory dropdown options from Utilities table (no hardcoded fallback)
$ada_options = [];
$ada_option_defaults = [];
try {
    require_once __DIR__ . '/../protected/core/components/helpers/utilities_signatory_helper.inc.php';
    utilities_signatory_ensure_schema($pdo);
    $adaOffice = utilities_signatory_default_office();
    $ada_options = utilities_fetch_ada_options($pdo, $adaOffice);
    $ada_option_defaults = utilities_fetch_ada_option_defaults($pdo, $adaOffice);
} catch (Throwable $e) {
    // Ignore and show empty dropdowns; options are managed in Utilities (System Admin).
}

function session_contains_phrase($phrase)
{
    foreach ($_SESSION as $key => $value) {
        // Use stripos for case-insensitive search
        if (stripos($value, $phrase) !== false) {
            return true;
        }
    }
    return false;
}

// Define the number of rows per page
$rowsPerPage = 500;

// Fetch total number of rows
$voucher_action_logs_queryCount = "SELECT COUNT(*) AS total FROM voucher_action_logs";
$voucher_action_logs_statementCount = $pdo->query($voucher_action_logs_queryCount);
$totalRows = $voucher_action_logs_statementCount->fetch(PDO::FETCH_ASSOC)['total'];

// Check the current page
$currentPage = isset($_GET['page']) ? $_GET['page'] : 1;

// Calculate the offset for the query
$offset = ($currentPage - 1) * $rowsPerPage;

// Prepare and execute the query with pagination
$fetch_received_vouchers_query = "SELECT * FROM voucher_receiving WHERE receiver_udc LIKE :udc ORDER BY processing_no DESC LIMIT :offset, :rowsPerPage";
$fetch_received_vouchers = $pdo->prepare($fetch_received_vouchers_query);
$udc_param = '%' . $_SESSION["logged_user_udc"] . '%'; // Prepare the parameter with '%' wildcards
$fetch_received_vouchers->bindParam(":udc", $udc_param);
$fetch_received_vouchers->bindValue(':offset', $offset, PDO::PARAM_INT);
$fetch_received_vouchers->bindValue(':rowsPerPage', $rowsPerPage, PDO::PARAM_INT);
$fetch_received_vouchers->execute();

$fetch_voucher_temp_query = "SELECT * FROM voucher_temp ORDER BY processing_no DESC LIMIT :offset, :rowsPerPage";
$fetch_voucher_temp = $pdo->prepare($fetch_voucher_temp_query);
$fetch_voucher_temp->bindValue(':offset', $offset, PDO::PARAM_INT);
$fetch_voucher_temp->bindValue(':rowsPerPage', $rowsPerPage, PDO::PARAM_INT);
$fetch_voucher_temp->execute();

$target = explode(",", $_SESSION['logged_user_designation']);
?>
<!--=============== MAIN ===============!-->
<div class="main main--dashboard ada-wrapper" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Voucher ADA</h1>
        <?php if ($fetch_voucher_temp->rowCount() > 0) : ?>
            <div class="user_options_container">
                <button class="btn primary pPopAda voucher-dashboard-btn-primary" id="" name="" type="button">Process</button>
            </div>
        <?php endif; ?>
    </header>
    <div class="voucher-card voucher-card--filter">
        <div class="filter-download_container">
            <div class="filter_options_container">
                <div class="filter-container">
                    <input type="text" id="filterInput" placeholder="Search for DV no., payee, particulars, etc">
                </div>
            </div>
        </div>
    </div>
    <div class="popup-form" id="popupForm">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="form_title">Forward Document</p>
                <i class="ri-close-fill close-icon" id="close_popup4"></i>
            </div>
            <form action="../../protected/handler/voucher_archiving_module/voucher_archiving_handler.php" class="f-container" method="post" id="myForm_Forwarding">
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
                            <div class="label-input__container number-input">
                                <label for="">Amount</label>
                                <input type="text" name="string_amount" class="string_amount form-custom-input" placeholder="Amount" required readonly>
                            </div>
                            <div class="label-input__container number-input">
                                <label for="">Amount2</label>
                                <input type="number"
                                    min="0.01"
                                    step="0.01"
                                    oninput="this.value = !!this.value && Math.abs(this.value) >= 0.01 ? Math.abs(this.value) : null"
                                    name="amount" class="amount form-custom-input" placeholder="Amount" required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Voucher Date</label>
                                <input type="date" name="voucher_date" class="voucher_date form-custom-input" id="voucher_date" value="" required readonly>
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Office From</label>
                                <input type="text" name="office_from" class="office_from form-custom-input" id="office_from" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Office To</label>
                                <input type="text" name="office_to" class="office_to form-custom-input" id="office_to" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded By</label>
                                <input type="text" name="encoded_by" class="encoded_by form-custom-input" id="encoded_by" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded From</label>
                                <input type="text" name="encoded_from" class="encoded_from form-custom-input" id="encoded_from" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Date/Time Encoded</label>
                                <input type="text" name="datetime_encoded" class="datetime_encoded form-custom-input" id="datetime_encoded" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Receiver UDC</label>
                                <input type="text" name="receiver_udc" class="receiver_udc form-custom-input" id="receiver_udc" value="">
                            </div>
                            <div class="label-input__container">
                                <label for="">Voucher Type</label>
                                <input type="text" name="voucher_type" class="voucher_type form-custom-input" id="voucher_type" value="">
                            </div>
                            <div class="label-input__container">
                                <label for="">Remarks</label>
                                <input type="text" name="combined_remarks" class="combined_remarks form-custom-input" id="combined_remarks" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Process History</label>
                                <!-- Use textarea to preserve newline-delimited process history -->
                                <textarea name="process_history" class="process_history form-custom-input" id="process_history" rows="6" style="resize: none;"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn transparent btn-dynamic" name="" type="submit"></button>
                        <button class="btn secondary transparent" id="close_popup3" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="popup-form" id="adaForm">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="form_title">Process Document</p>
                <i class="ri-close-fill close-icon" id="close_popup4"></i>
            </div>
            <form class="f-container targetForm" id="myForm_Processing">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Certified Correct:</label>
                                <select name="certified_correct" class="form-custom-input" required>
                                    <?php
                                    $list = $ada_options['certified_correct'] ?? [];
                                    $defaultVal = $ada_option_defaults['certified_correct'] ?? '';
                                    $hasDefault = $defaultVal !== '' && in_array($defaultVal, $list, true);
                                    ?>
                                    <option value="" disabled <?= $hasDefault ? '' : 'selected' ?>>Please Select:</option>
                                    <?php
                                    if (!$list) :
                                    ?>
                                        <option value="" disabled>(No options configured — ask System Admin)</option>
                                    <?php
                                    else :
                                        foreach ($list as $v):
                                            $selected = ($hasDefault && $v === $defaultVal) ? ' selected' : '';
                                    ?>
                                        <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>"<?= $selected ?>><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                            </div>
                            <div class="label-input__container">
                                <label for="">Approved By:</label>
                                <select name="approved_by" class="form-custom-input" required>
                                    <?php
                                    $list = $ada_options['approved_by'] ?? [];
                                    $defaultVal = $ada_option_defaults['approved_by'] ?? '';
                                    $hasDefault = $defaultVal !== '' && in_array($defaultVal, $list, true);
                                    ?>
                                    <option value="" disabled <?= $hasDefault ? '' : 'selected' ?>>Please Select:</option>
                                    <?php
                                    if (!$list) :
                                    ?>
                                        <option value="" disabled>(No options configured — ask System Admin)</option>
                                    <?php
                                    else :
                                        foreach ($list as $v):
                                            $selected = ($hasDefault && $v === $defaultVal) ? ' selected' : '';
                                    ?>
                                        <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>"<?= $selected ?>><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                            </div>
                            <div class="label-input__container">
                                <label for="">Agency Authorized Signatory:</label>
                                <select name="agency_authorized_signatory" class="form-custom-input" required>
                                    <?php
                                    $list = $ada_options['agency_authorized_signatory'] ?? [];
                                    $defaultVal = $ada_option_defaults['agency_authorized_signatory'] ?? '';
                                    $hasDefault = $defaultVal !== '' && in_array($defaultVal, $list, true);
                                    ?>
                                    <option value="" disabled <?= $hasDefault ? '' : 'selected' ?>>Please Select:</option>
                                    <?php
                                    if (!$list) :
                                    ?>
                                        <option value="" disabled>(No options configured — ask System Admin)</option>
                                    <?php
                                    else :
                                        foreach ($list as $v):
                                            $selected = ($hasDefault && $v === $defaultVal) ? ' selected' : '';
                                    ?>
                                        <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>"<?= $selected ?>><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Check No:</label>
                                <input type="text" class="form-custom-input" name="check_no" required>
                            </div>
                            <div class="label-input__container">
                                <label for="">ADA No:</label>
                                <input type="text" class="form-custom-input" name="ada_no" required>
                            </div>
                            <div class="label-input__container">
                                <label for="">Date:</label>
                                <input type="date" class="form-custom-input" name="ada_check_date" id="ada_date" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn transparent primary" id="saveData" name="" type="button">SAVE</button>
                        <button class="btn transparent warning" id="passData" type="submit" onclick="checkInputs(event)">PRINT</button>
                        <button class="btn secondary transparent" id="close_popup_ada" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="overlay" id="overlay"></div>
    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">ADA Summary</h2>
        <div class="ada content-wrapper">
            <div class="main-table-wrapper">
                <div class="main-tables">
                    <table class="table content_table content_table--dashboard" id="my-Table">
                        <thead class="sticky-th">
                            <tr>
                                <th>DV No.</th>
                                <th>Payee</th>
                                <th>Particulars</th>
                                <th>Amount</th>
                                <th>Date/Time Received</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tr>
                            <?php
                            while ($row = $fetch_received_vouchers->fetch(PDO::FETCH_ASSOC)) {
                            ?>
                                <td data-label="processing_no" class="hidden"><?php echo $row['processing_no']; ?></td>
                                <td data-label="ors_no" class="hidden"><?php echo $row['ors_no']; ?></td>
                                <td data-label="dv_no"><?php echo $row['dv_no']; ?></td>
                                <td data-label="payee"><?php echo $row['payee']; ?></td>
                                <td data-label="address" class="hidden"><?php echo $row['address']; ?></td>
                                <td data-label="particulars"><?php echo $row['particulars']; ?></td>
                                <td data-label="amount" class="amount"><?php echo $row['amount']; ?></td>
                                <td data-label="voucher_date"><?php echo $row['voucher_date']; ?></td>
                                <td data-label="remarks" class="hidden"><?php echo $row['remarks']; ?></td>
                                <td data-label="tin_employee_no" class="hidden"><?php echo $row['tin_employee_no']; ?></td>
                                <td data-label="receiver_udc" class="hidden"><?php echo $row['receiver_udc']; ?></td>
                                <td data-label="office_to" class="hidden"><?php echo $row['office_to']; ?></td>
                                <td data-label="office_from" class="hidden"><?php echo $row['office_from']; ?></td>
                                <td data-label="encoded_by" class="hidden"><?php echo $row['encoded_by']; ?></td>
                                <td data-label="encoded_from" class="hidden"><?php echo $row['encoded_from']; ?></td>
                                <td data-label="datetime_encoded" class="hidden"><?php echo $row['datetime_encoded']; ?></td>
                                <td data-label="voucher_type" class="hidden"><?php echo $row['voucher_type']; ?></td>
                                <td data-label="process_history" class="hidden"><?php echo isset($row['process_history']) ? htmlspecialchars($row['process_history']) : ''; ?></td>

                                <td data-label="upload_id"><button class="btn warning pPop" id="openPopup" name="btn-add" type="button">Add</button></td>
                        </tr>
                    <?php
                            }
                    ?>
                    </table>
                    <?php if ($fetch_received_vouchers->rowCount() < 1) : ?>
                        <div class="no-display" style="display:flex; width: 100%; height: inherit; justify-content: center; align-items: center;">
                            <p>NO DATA TO DISPLAY</p>
                        </div>
                    <?php else : ?>
                        <div class="pagination">
                            <div class="pagination_container">

                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <hr>
                <div class="main-tables">
                    <table class="table remove-table" id="my-Table">
                        <thead class="sticky-th">
                            <tr>
                                <th>DV No.</th>
                                <th>Payee</th>
                                <th>Particulars</th>
                                <th>Amount</th>
                                <th>Date/Time Received</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tr>
                            <?php
                            while ($row = $fetch_voucher_temp->fetch(PDO::FETCH_ASSOC)) {
                            ?>
                                <td data-label="processing_no" class="hidden"><?php echo $row['processing_no']; ?></td>
                                <td data-label="ors_no" class="hidden"><?php echo $row['ors_no']; ?></td>
                                <td data-label="dv_no"><?php echo $row['dv_no']; ?></td>
                                <td data-label="payee"><?php echo $row['payee']; ?></td>
                                <td data-label="address" class="hidden"><?php echo $row['address']; ?></td>
                                <td data-label="particulars"><?php echo $row['particulars']; ?></td>
                                <td data-label="amount" class="amount"><?php echo $row['amount']; ?></td>
                                <td data-label="final_amount" class="final_amount"><?php echo $row['amount']; ?></td>
                                <td data-label="voucher_date"><?php echo $row['voucher_date']; ?></td>
                                <td data-label="remarks" class="hidden"><?php echo $row['remarks']; ?></td>
                                <td data-label="tin_employee_no" class="hidden"><?php echo $row['tin_employee_no']; ?></td>
                                <td data-label="receiver_udc" class="hidden"><?php echo $row['receiver_udc']; ?></td>
                                <td data-label="office_to" class="hidden"><?php echo $row['office_to']; ?></td>
                                <td data-label="office_from" class="hidden"><?php echo $row['office_from']; ?></td>
                                <td data-label="encoded_by" class="hidden"><?php echo $row['encoded_by']; ?></td>
                                <td data-label="encoded_from" class="hidden"><?php echo $row['encoded_from']; ?></td>
                                <td data-label="datetime_encoded" class="hidden"><?php echo $row['datetime_encoded']; ?></td>
                                <td data-label="priority" class="hidden"><?php echo $row['priority']; ?></td>
                                <td data-label="voucher_type" class="hidden"><?php echo $row['voucher_type']; ?></td>
                                <td data-label="process_history" class="hidden"><?php echo isset($row['process_history']) ? htmlspecialchars($row['process_history']) : ''; ?></td>

                                <td data-label="upload_id"><button class="btn danger pPop" id="openPopup" name="btn-remove" type="button">Remove</button></td>
                        </tr>
                    <?php
                            }
                    ?>
                    </table>
                    <?php if ($fetch_voucher_temp->rowCount() < 1) : ?>
                        <div class="no-display" style="display:flex; width: 100%; height: inherit; justify-content: center; align-items: center;">
                            <p>NO DATA TO DISPLAY</p>
                        </div>
                    <?php else : ?>
                        <div class="pagination">
                            <div class="pagination_container">

                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const today = new Date().toISOString().split('T')[0]
    document.getElementById('ada_date').value = today;
</script>

<?php if ($fetch_voucher_temp->rowCount() > 0) : ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to collect table data
            function collectTableData() {
                let rows = document.querySelectorAll('.remove-table tr');
                let data = [];

                rows.forEach(row => {
                    let cells = row.querySelectorAll('td');
                    if (cells.length > 0) { // Skip empty rows
                        let rowData = {};
                        cells.forEach(cell => {
                            let label = cell.getAttribute('data-label');
                            let value = cell.textContent.trim();
                            if (label) {
                                rowData[label] = value;
                            }
                        });
                        if (Object.keys(rowData).length > 0) {
                            data.push(rowData);
                        }
                    }
                });

                console.log('Table Data:', data); // Debugging line

                return data;
            }

            // Function to collect form data
            function collectFormData() {
                let formData = new FormData(document.querySelector('.targetForm'));
                let data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });

                console.log('Form Data:', data); // Debugging line

                return data;
            }

            // Function to send data to the server
            function sendData() {
                let tableData = collectTableData();
                let formData = collectFormData();

                // Insert form data into each row of table data
                tableData.forEach(row => {
                    Object.assign(row, formData);
                });

                // Combine table and form data
                let combinedData = {
                    data: tableData
                };

                console.log('Combined Data:', combinedData); // Debugging line

                // Confirm before sending data
                functionAlert('Are you sure you want to submit the data?', 'ada-submit-confirm', function() {
                    fetch('../../protected/handler/voucher_ada_multi/multi_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(combinedData)
                        })
                        .then(response => response.text())
                        .then(result => {
                            console.log('Success:', result);
                            window.location.href = 'voucher_ada.php'; // Redirect after success
                        })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                });
            }
            const saveBtn = document.querySelector('button#saveData');
            if (saveBtn) {
                saveBtn.addEventListener('click', function(e) {
                    // Ensure the browser doesn't submit/reload and interrupt fetch()
                    if (e && typeof e.preventDefault === 'function') e.preventDefault();
                    sendData();
                });
            }
        });
    </script>
<?php endif ?>


<?php if ($fetch_voucher_temp->rowCount() > 0) : ?>
    <script>
        function checkInputs(event) {
            const inputs = document.querySelectorAll('#myForm_Processing input, #myForm_Processing select');
            const allFilled = Array.from(inputs).every(input => input.value.trim() !== '');

            const button = document.getElementById('passData');
            console.log(allFilled);

            // Prevent form submission if all inputs and selects are filled
            if (allFilled) {
                event.preventDefault(); // Prevent form submission
                button.type = 'button'; // Change to button type
                sendData();
            } else {
                console.log("function called");
                document.querySelector('button#passData').removeEventListener('click', sendData);
                button.type = 'submit'; // Keep as submit type
            }
        }

        function collectTableData() {
            let rows = document.querySelectorAll('.remove-table tr');
            let data = [];

            rows.forEach(row => {
                let cells = row.querySelectorAll('td');
                if (cells.length > 0) { // Skip empty rows
                    let rowData = {};
                    cells.forEach(cell => {
                        let label = cell.getAttribute('data-label');
                        let value = cell.textContent.trim();
                        if (label) {
                            rowData[label] = value;
                        }
                    });
                    if (Object.keys(rowData).length > 0) {
                        data.push(rowData);
                    }
                }
            });

            console.log('Table Data:', data); // Debugging line

            return data;
        }

        // Function to collect form data
        function collectFormData() {
            let formData = new FormData(document.querySelector('.targetForm'));
            let data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });

            console.log('Form Data:', data); // Debugging line

            return data;
        }

        // Function to send data to the server
        function sendData() {
            let tableData = collectTableData();
            let formData = collectFormData();

            // Insert form data into each row of table data
            tableData.forEach(row => {
                Object.assign(row, formData);
            });

            // Combine table and form data
            let combinedData = {
                data: tableData
            };

            console.log('Combined Data:', combinedData); // Debugging line

            // Confirm before sending data
            functionAlert('Are you sure you want to print the data?', 'ada-print-confirm', function() {
                fetch('lddap.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(combinedData)
                    })
                    .then(response => response.text())
                    .then(result => {
                        // console.log('Success:', result);
                        document.getElementById("adaForm").style.display = "block";
                        document.getElementById("adaForm").style.animation = "slideIn 0.5s ease"
                        document.getElementById("overlay").style.display = "block";
                        const resultDiv = document.getElementById('result');
                        if (resultDiv) {
                            resultDiv.innerHTML = result; // Update existing
                        } else {
                            document.body.insertAdjacentHTML('beforeend', `<div id="result">${result}</div>`); // Create new
                        }
                        // use insertAdjacentHTML to not affect current body event listeners
                        // window.location.href = 'voucher_ada.php'; // Redirect after success
                        window.print();
                    })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                });
            }
    </script>
<?php endif ?>



<script>
    const selectElements2 = document.querySelectorAll(".form-custom__input"); // Get all select elements
    const target2 = "<?php echo $_SESSION['logged_user_designation']; ?>";

    const targetArray2 = target2.split(','); // Convert to an array

    selectElements2.forEach(selectElement => {
        Array.from(selectElement.options).forEach(option => { // Loop through each option
            if (targetArray2.includes(option.value)) {
                option.classList.add('hidden'); // Add 'hidden' class if value matches
            }
        });
    });
</script>

<!--=============== MAIN.JS ===============!-->
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/amount_helper.js"></script>
<script src="../../protected/js/voucher.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    // Get all buttons with class 'btn-forward'
    var buttons = document.querySelectorAll('#my-Table .btn');

    // Loop through each button and attach click event listener
    buttons.forEach(function(button) {
        button.addEventListener('click', function() {
            // Get the row associated with the clicked button
            var row = this.closest('tr');
            var name = this.name.toString();

            // Extract data from the row
            var processing_no = row.querySelector('[data-label="processing_no"]').textContent;
            var ors_no = row.querySelector('[data-label="ors_no"]').textContent;
            var dv_no = row.querySelector('[data-label="dv_no"]').textContent;
            var payee = row.querySelector('[data-label="payee"]').textContent;
            var address = row.querySelector('[data-label="address"]').textContent;
            var particulars = row.querySelector('[data-label="particulars"]').textContent;
            var amount = row.querySelector('[data-label="amount"]').textContent;
            var voucher_date = row.querySelector('[data-label="voucher_date"]').textContent;
            var office_to = row.querySelector('[data-label="office_to"]').textContent;
            var office_from = row.querySelector('[data-label="office_from"]').textContent;
            var encoded_by = row.querySelector('[data-label="encoded_by"]').textContent;
            var encoded_from = row.querySelector('[data-label="encoded_from"]').textContent;
            var datetime_encoded = row.querySelector('[data-label="datetime_encoded"]').textContent;
            var receiver_udc = row.querySelector('[data-label="receiver_udc"]').textContent;
            var tin_employee_no = row.querySelector('[data-label="tin_employee_no"]').textContent;
            var remarks = row.querySelector('[data-label="remarks"]').textContent;
            var voucher_type = row.querySelector('[data-label="voucher_type"]').textContent;
            var process_history_cell = row.querySelector('[data-label="process_history"]');
            var process_history = process_history_cell ? process_history_cell.textContent.trim() : '';

            const convertedBack = normalizeAmountInput(String(amount));

            // Send it via AJAX to the server
            document.querySelector('.processing_no').value = processing_no;
            document.querySelector('.ors_no').value = ors_no;
            document.querySelector('.dv_no').value = dv_no;
            document.querySelector('.payee').value = payee;
            document.querySelector('.address').value = address;
            document.querySelector('.particulars').value = particulars;
            document.querySelector('.amount').value = convertedBack;
            document.querySelector('.string_amount').value = amount;
            document.querySelector('.voucher_date').value = voucher_date;
            document.querySelector('.office_from').value = office_from;
            document.querySelector('.office_to').value = office_to;
            document.querySelector('.encoded_by').value = encoded_by;
            document.querySelector('.encoded_from').value = encoded_from;
            document.querySelector('.datetime_encoded').value = datetime_encoded;
            document.querySelector('.receiver_udc').value = receiver_udc;
            document.querySelector('.tin_employee_no').value = tin_employee_no;
            document.querySelector('.combined_remarks').value = remarks;
            document.querySelector('.voucher_type').value = voucher_type;
            document.querySelector('.process_history').value = process_history;


            document.querySelectorAll('.hidden_input').forEach(function(input) {
                input.style.display = 'none';
            });

            if (name === "btn-add") {
                document.getElementById("myForm_Forwarding").setAttribute('action', '../../protected/handler/voucher_ada_module/voucher_ada_add/voucher_ada_add_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Add";
                document.getElementById("form_title").textContent = "Add Voucher";
                document.querySelector(".btn-dynamic").setAttribute("name", "add_voucher");
                document.querySelector(".btn-dynamic").classList.add("warning");
                document.querySelector(".btn-dynamic").classList.remove("danger");

                if (document.querySelector(".btn-dynamic")) {
                    const buttonName = document.querySelector(".btn-dynamic").getAttribute("Name");

                    if (targetArray.includes("Records Unit") && buttonName === "forward_voucher") {
                        document.querySelectorAll(".document_to").required = false;
                    }
                }

            }

            if (name === "btn-remove") {
                document.getElementById("myForm_Forwarding").setAttribute('action', '../../protected/handler/voucher_ada_module/voucher_ada_remove/voucher_ada_remove_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Remove";
                document.getElementById("form_title").textContent = "Remove Voucher";
                document.querySelector(".btn-dynamic").setAttribute("name", "remove_voucher");
                document.querySelector(".btn-dynamic").classList.add("danger");
                document.querySelector(".btn-dynamic").classList.remove("warning");

                if (document.querySelector(".btn-dynamic")) {
                    const buttonName = document.querySelector(".btn-dynamic").getAttribute("Name");

                    if (targetArray.includes("Records Unit") && buttonName === "forward_voucher") {
                        document.querySelectorAll(".document_to").required = false;
                    }
                }

            }
        });
    });
</script>
</body>

</html>