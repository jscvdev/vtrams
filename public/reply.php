<?php
include('includes/header.php');
include('../protected/handler/forward_module/forward_errhandler.inc.php');
include('../protected/handler/encode_module/encode_errhandler.inc.php');
include('../protected/handler/encode_module/delete_module/pending_delete_errhandler.inc.php');
include('../protected/core/components/notifications/err_handler_custom_alert.php');
include('../protected/core/components/notifications/custom_alert.php');
require '../protected/core/components/notifications/err_handler_custom_alert.php';
include('gen_routing_slip.php');
check_encode_errors();
check_forward_errors();
check_pending_delete_errors();

$query = "SELECT * FROM oic";
$statement = $pdo->prepare($query);
$statement->execute();

$focal_specific_phrase = 'Focal Person';
$msd_division_chief_specific_phrase = 'Management Services Division Chief';
$tsd_division_chief_specific_phrase = 'Technical Services Division Chief';
$section_chief_specific_phrase = 'Section Chief';
$unit_chief_specific_phrase = 'Unit Chief';
$penr_officer_specific_phrase = 'PENR Officer';

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
?>
<!--=============== MAIN ===============!-->
<div class="main" id="main">
    <div class="filter-download_container">
        <div class="filter_options_container">
            <div class="filter-container">
                <input type="text" id="filterInput" placeholder="Search">
            </div>
        </div>
        <a class="btn-add popupForm-add" id="openPopup">Reply document</a>
    </div>
    <div class="popup-form" id="popupForm2">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p>Reply Document</p>
                <i class="ri-close-fill close-icon" id="close_popup"></i>
            </div>
            <form action="../../protected/handler/encode_module/encode_handler.php" class="f-container" method="post">
                <div class="box-body__container">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Title</label>
                                <input type="text" name="document_title" id="encoding_document_title" value="" placeholder="Title/Subject" required>
                            </div>
                            <div class="label-input__container">
                                <label for="">Type</label>
                                <select name="document_type" id="" class="form-custom__input target_select" required>
                                    <option value="Memorandum">Memorandum</option>
                                    <option value="Special Order">Special Order</option>
                                    <option value="Letter">Letter</option>
                                    <option value="Contracts">Contracts</option>
                                    <option value="Advisory">Advisory</option>
                                    <option value="Department Memorandum Order">Department Memorandum Order</option>
                                    <option value="Department Memorandum Circular">Department Memorandum Circular</option>
                                    <option value="Disbursement Voucher">Disbursement Voucher</option>
                                    <option value="Leave Form">Leave Form</option>
                                    <option value="Daily Time Record">Daily Time Record</option>
                                    <option value="Executive Order">Executive Order</option>
                                    <option value="Notice of Meeting">Notice of Meeting</option>
                                    <option value="Payroll">Payroll</option>
                                    <option value="Purchase Request">Purchase Request</option>
                                    <option value="Purchase Order">Purchase Order</option>
                                    <option value="BAC Resolution (First)">BAC Resolution (First)</option>
                                    <option value="BAC Resolution (Second)">BAC Resolution (Second)</option>
                                    <option value="Notice of Award">Notice of Award</option>
                                    <option value="Notice to Proceed">Notice to Proceed</option>
                                    <option value="Abstract of Bids">Abstract of Bids</option>
                                    <option value="Request for Quotation">Request for Quotation</option>
                                    <option value="Travel Order">Travel Order</option>
                                    <option value="Itinerary of Travel">Itinerary of Travel</option>
                                </select>
                            </div>
                            <div class="label-input__container">
                                <label for="">Received Thru</label>
                                <select name="document_receive_type" class="form-custom__input" required>
                                    <option value="Email">Email</option>
                                    <option value="Hand Carry">Hand Carry</option>
                                    <option value="Registered Mail">Registered Mail</option>
                                    <option value="Courier">Courier</option>
                                </select>
                            </div>
                            <div class="label-input__container">
                                <label for="">Document Date</label>
                                <input type="date" name="document_date" id="encoding_document_date" value="" required>
                            </div>
                            <?php if ($_SESSION['logged_user_section'] != "RECORDS") : ?>
                                <div class="label-input__container">
                                    <label for="">Replying To</label>
                                    <input type="text" name="target_document" placeholder="Document ID" required>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Description</label>
                                <textarea name="document_desc" id="encoding_document_desc" cols="30" rows="10" class="multi-line-input" placeholder="Description" required></textarea>
                            </div>

                            <div class="label-input__container">
                                <label for="">Sender</label>
                                <input type="text" name="document_sender" id="encoding_document_sender" value="" placeholder="Sender">
                            </div>
                            <div class="label-input__container">
                                <label for="">Intended Receiver</label>
                                <input type="text" name="document_receiver" id="encoding_document_receiver" value="" placeholder="Intended Receiver">
                            </div>
                            <div class="label-input__container number-input">
                                <label for="">No. of Pages</label>
                                <input type="number" min="1" oninput="this.value =
 !!this.value && Math.abs(this.value) >= 1 ? Math.abs(this.value) : null" name="document_pages" value="1" placeholder="No. of Pages" required>
                            </div>
                            <?php if ($_SESSION['acl'] >= 4): ?>
                                <div class='label-input__container check-container hidden_input' id="priority-container">
                                    <label for=''>Priority</label>
                                    <select name='priority_status' class='form-custom__input priority_status' disabled>
                                        <option value="Urgent">Urgent</option>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <?php if ($_SESSION['acl'] >= 4): ?>
                                <div class="custom-checkbox">
                                    <div class="checkbox-span__container">
                                        <label for=""><input type="checkbox" id="checker_priority" onclick="setSession()"><span>High Priority</span></label>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <?php if ($_SESSION['acl'] != 888) : ?>
                            <button class="custom-btn btn-save" name="reply_document" type="submit">Save</button>
                            <button class="custom-btn btn-clear" id="btn-clear" name="" type="button">Clear</button>
                        <?php endif; ?>
                        <button class="custom-btn btn-close" id="close_popup2" type="button">Close</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="popup-form" id="popupForm">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="form_title">Forward Document</p>
                <i class="ri-close-fill close-icon" id="close_popup3"></i>
            </div>
            <form action="#" class="f-container" method="post" id="encoded_pending_form">
                <div class="box-body__container">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Document ID</label>
                                <input class="encoded_document_id" type="text" name="encoded_document_id" value="" placeholder="Document ID" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Title</label>
                                <input type="text" class="encoded_document_title" name="encoded_document_title" value="" placeholder="Title/Subject" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Type</label>
                                <select name="encoded_document_type" id="encoded_document_type" class="form-custom__input target_select" required>
                                    <option value="Memorandum">Memorandum</option>
                                    <option value="Special Order">Special Order</option>
                                    <option value="Letter">Letter</option>
                                    <option value="Contracts">Contracts</option>
                                    <option value="Advisory">Advisory</option>
                                    <option value="Department Memorandum Order">Department Memorandum Order</option>
                                    <option value="Department Memorandum Circular">Department Memorandum Circular</option>
                                    <option value="Disbursement Voucher">Disbursement Voucher</option>
                                    <option value="Leave Form">Leave Form</option>
                                    <option value="Daily Time Record">Daily Time Record</option>
                                    <option value="Executive Order">Executive Order</option>
                                    <option value="Notice of Meeting">Notice of Meeting</option>
                                    <option value="Payroll">Payroll</option>
                                    <option value="Purchase Request">Purchase Request</option>
                                    <option value="Purchase Order">Purchase Order</option>
                                    <option value="BAC Resolution (First)">BAC Resolution (First)</option>
                                    <option value="BAC Resolution (Second)">BAC Resolution (Second)</option>
                                    <option value="Notice of Award">Notice of Award</option>
                                    <option value="Notice to Proceed">Notice to Proceed</option>
                                    <option value="Abstract of Bids">Abstract of Bids</option>
                                    <option value="Request for Quotation">Request for Quotation</option>
                                    <option value="Travel Order">Travel Order</option>
                                    <option value="Itinerary of Travel">Itinerary of Travel</option>
                                </select>
                            </div>
                            <div class="label-input__container">
                                <label for="">Intended Receiver</label>
                                <input type="text" class="encoded_document_receiver" name="encoded_document_receiver" value="" placeholder="Intended Receiver" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Received Thru</label>
                                <select name="encoded_document_receive_type" id="encoded_document_receive_type" class="form-custom__input" aria-readonly="true">
                                    <option value="Email">Email</option>
                                    <option value="Hand Carry">Hand Carry</option>
                                    <option value="Registered Mail">Registered Mail</option>
                                    <option value="Courier">Courier</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Description</label>
                                <textarea name="encoded_document_description" id="doc_desc" cols="30" rows="10" class="multi-line-input encoded_document_description" placeholder="Description" readonly></textarea>
                            </div>

                            <div class="label-input__container">
                                <label for="">Sender</label>
                                <input type="text" class="encoded_document_sender" name="encoded_document_sender" id="doc_sender" value="" placeholder="Sender" readonly>
                            </div>
                            <div class="label-input__container number-input">
                                <label for="">No. of Pages</label>
                                <input type="number" class="encoded_document_no_pages" name="encoded_document_no_pages" value="1" placeholder="No. of Pages" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Document Date</label>
                                <input type="date" class="encoded_document_date" name="encoded_document_date" id="document_date" value="" readonly>
                            </div>

                            <!--FOR OPENRO-->
                            <?php if ($_SESSION['acl'] >= 3 and $_SESSION['logged_user_designation'] == "Office of the PENRO")
                                echo
                                "                            
                               <div class='label-input__container input-dynamic'>
                                <label for=''>Document To</label>
                                <select name='document_to' class='form-custom__input target_select' required>
                                    <option value='Management Services Division'>Management Services Division</option>
                                    <option value='Technical Services Division'>Technical Services Division</option>
                                    <option value='PENR Officer'>PENR Officer</option>
                                </select>
                            </div>";
                            ?>

                            <!--FOR MSD UNITS-->
                            <?php
                            if ($_SESSION['acl'] == 4 and $_SESSION['logged_user_division'] == "MSD" and $_SESSION['logged_user_section'] != "RECORDS" or $_SESSION['acl'] <= 6 and $_SESSION['logged_user_division'] == "MSD" and $_SESSION['logged_user_section'] != "RECORDS" and $_SESSION['logged_user_office'] === "DENR-PENRO EASTERN SAMAR") {
                                echo
                                "                            
                               <div class='label-input__container input-dynamic'>
                                <label for=''>Document To</label>
                                <select name='document_to' class='form-custom__input' required>
                                    <optgroup label='Division Chief'>
                                        <option value='Management Services Division Chief'>Division Chief</option>
                                    </optgroup>
                                    <optgroup label='Planning Section'>
                                        <option value='Planning Section'>a. Planning</option>
                                        <option value='ICT Unit'>b. ICT Unit</option>
                                    </optgroup>
                                    <optgroup label='Admin and Finance Section'>
                                        <option value='Accounting Unit'>a. Accounting Unit</option>
                                        <option value='Budget Unit'>b. Budget Unit</option>
                                        <option value='Personnel & General Services Unit'>c. Personnel & Gen. Services Unit</option>
                                        <option value='Procurement & Supply Unit'>d. Procurement & Supply Unit</option>
                                        <option value='Cashier Unit'>e. Cashier Unit</option>
                                    </optgroup>
                                    <optgroup label='Focal Persons under MSD'>
                                        <option value='8888 Focal Person'>1. 8888 Focal Person</option>
                                        <option value='Citizens Charter Focal Person'>2. CSC Focal Person</option>
                                        <option value='GSIS AAO Focal Person'>3. GSIS AAO Focal Person</option>
                                    </optgroup>
                                    <optgroup label='Other'>
                                        <option value='Management Services Division'>Management Services Division</option>
                                        <option value='Technical Services Division'>Technical Services Division</option>
                                    </optgroup>
                                </select>
                            </div>";
                            }
                            ?>

                            <!--FOR TSD UNITS-->
                            <?php
                            if ($_SESSION['acl'] >= 4 and $_SESSION['logged_user_division'] == "TSD" or $_SESSION['acl'] <= 6 and $_SESSION['logged_user_division'] == "TSD" and $_SESSION['logged_user_office'] === "DENR-PENRO EASTERN SAMAR") {
                                echo
                                "                            
                               <div class='label-input__container input-dynamic'>
                                <label for=''>Document To</label>
                                <select name='document_to' class='form-custom__input' required>
                                <optgroup label='Division Chief'>
                                    <option value='Technical Services Division Chief'>Division Chief</option>
                                </optgroup>
                                <optgroup label='Section/Units under TSD'>
                                    <option value='Conservation & Development Section'>Conservation & Development Section</option>
                                    <option value='Regulation & Permitting Section'>Regulation & Permitting Section</option>
                                    <option value='Monitoring & Enforcement Section'>Monitoring & Enforcement Section</option>
                                </optgroup>
                                <optgroup label='Focal Persons under TSD'>
                                    <option value='ENGP Focal Person'>ENGP Focal Person</option>
                                    <option value='CSS Focal Person'>CSS Focal Person</option>
                                    <option value='SPICS Focal Person'>SPICS Focal Person</option>
                                    <option value='GAD Focal Person'>GAD Focal Person</option>
                                    <option value='HDMF (Pagibig Fund) Focal Person'>HDMF (Pagibig Fund)</option>
                                    <option value='PASu - GMRPLS'>GMRPLS (PASu)</option>
                                </optgroup>
                                <optgroup label='Other'>
                                    <option value='Management Services Division'>Management Services Division</option>
                                    <option value='Technical Services Division'>Technical Services Division</option>
                                </optgroup>
                                </select>
                            </div>";
                            }
                            ?>

                            <!--FOR PENR OFFICER-->
                            <?php
                            if ($_SESSION['acl'] == 5 and session_contains_phrase($penr_officer_specific_phrase) and $_SESSION['logged_user_office'] === "DENR-PENRO EASTERN SAMAR") {
                                echo
                                "                            
                               <div class='label-input__container input-dynamic'>
                                <label for=''>Document To</label>
                                <select name='document_to' class='form-custom__input target_select' required>
                                    <optgroup label='MSD'>
                                        <option value='Management Services Division'>Management Services Division</option>
                                    </optgroup>
                                    <optgroup label='TSD'>
                                        <option value='Technical Services Division'>Technical Services Division</option>
                                    </optgroup>
                                </select>
                            </div>";
                            }
                            ?>

                            <!--FOR MSD CHIEF-->
                            <?php
                            if ($_SESSION['acl'] == 7 and session_contains_phrase($msd_division_chief_specific_phrase) and $_SESSION['logged_user_office'] === "DENR-PENRO EASTERN SAMAR") {
                                echo
                                "                            
                               <div class='label-input__container input-dynamic'>
                                <label for=''>Document To</label>
                                <select name='document_to' class='form-custom__input target_select' required>
                                    <optgroup label='Planning Section'>
                                        <option value='Planning Section'>a. Planning</option>
                                        <option value='ICT Unit'>b. ICT Unit</option>
                                    </optgroup>
                                    <optgroup label='Admin and Finance Section'>
                                        <option value='Accounting Unit'>a. Accounting Unit</option>
                                        <option value='Budget Unit'>b. Budget Unit</option>
                                        <option value='Personnel & General Services Unit'>c. Personnel & Gen. Services Unit</option>
                                        <option value='Procurement & Supply Unit'>d. Procurement & Supply Unit</option>
                                        <option value='Cashier Unit'>e. Cashier Unit</option>
                                        <option value='Records Unit'>f. Records Unit</option>
                                    </optgroup>
                                    <optgroup label='Focal Persons under MSD'>
                                        <option value='8888 Focal Person'>1. 8888 Focal Person</option>
                                        <option value='Citizens Charter Focal Person'>2. CSC Focal Person</option>
                                        <option value='GSIS AAO Focal Person'>3. GSIS AAO Focal Person</option>
                                    </optgroup>
                                    <optgroup label='Other'>
                                        <option value='Technical Services Division'>Technical Services Division</option>
                                        <option value='Office of the PENRO'>Office of the PENRO</option>
                                    </optgroup>
                                </select>
                            </div>";
                            }
                            ?>

                            <!--FOR TSD CHIEF-->
                            <?php
                            if ($_SESSION['acl'] == 7 and session_contains_phrase($tsd_division_chief_specific_phrase) and $_SESSION['logged_user_office'] === "DENR-PENRO EASTERN SAMAR") {
                                echo
                                "                            
                               <div class='label-input__container input-dynamic'>
                                <label for=''>Document To</label>
                                <select name='document_to' class='form-custom__input' required>
                                <optgroup label='Section/Units under TSD'>
                                    <option value='Conservation & Development Section'>Conservation & Development Section</option>
                                    <option value='Regulation & Permitting Section'>Regulation & Permitting Section</option>
                                    <option value='Monitoring & Enforcement Section'>Monitoring & Enforcement Section</option>
                                </optgroup>
                                <optgroup label='Focal Persons under TSD'>
                                    <option value='ENGP Focal Person'>ENGP Focal Person</option>
                                    <option value='CSS Focal Person'>CSS Focal Person</option>
                                    <option value='SPICS Focal Person'>SPICS Focal Person</option>
                                    <option value='GAD Focal Person'>GAD Focal Person</option>
                                    <option value='HDMF (Pagibig Fund) Focal Person'>HDMF (Pagibig Fund)</option>
                                    <option value='PASu - GMRPLS'>GMRPLS (PASu)</option>
                                </optgroup>
                                <optgroup label='Other'>
                                    <option value='Management Services Division'>Management Services Division</option>
                                    <option value='Office of the PENRO'>Office of the PENRO</option>
                                    <option value='Records Unit'>Records Unit</option>
                                </optgroup>
                                </select>
                            </div>";
                            }
                            ?>

                            <?php
                            if ($_SESSION['acl'] >= 3) {
                                echo "
                                 <div class='label-input__container input-dynamic'>
                                 <label for=''>Remarks</label>
                                 <input type='text' class='encoded_remarks' name='encoded_remarks' id='remarks' value='' placeholder='Remarks'>
                                 </div>
                                ";
                            }
                            ?>

                            <?php if ($_SESSION['acl'] >= 5 and $_SESSION['acl'] <= 6): ?>
                                <div class='label-input__container check-container hidden_input' id="check-container">
                                    <label for=''>Document To (OIC)</label>
                                    <select name='document_to_oic' class='form-custom__input document_to_oic' disabled>
                                        <?php
                                        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                                        ?>
                                            <option value='Officer-In-Charge (PENR Office)'><?php echo $row['oic']; ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <?php if ($_SESSION['acl'] >= 5): ?>
                                <div class="label-input__container hidden_input" id="justification">
                                    <label for="">Justification</label>
                                    <input type="text" class="justification" name="justification" value="" id="justify_input" placeholder="Justification">
                                </div>
                            <?php endif; ?>

                            <?php if ($_SESSION['acl'] >= 5): ?>
                                <div class="custom-checkbox checkbox-oic">
                                    <div class="checkbox-span__container">
                                        <label for=""><input type="checkbox" id="oic_checker"><span>Via OIC</span></label>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="label-input__container hidden_input">
                                <label for="">Purpose</label>
                                <input type="text" class="encoded_purpose" name="encoded_purpose" id="purpose" value="" readonly>
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded From</label>
                                <input type="text" class="document_encoded_from" name="document_encoded_from" value="" placeholder="Encoded From">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded By</label>
                                <input type="text" class="document_encoded_by" name="document_encoded_by" value="" placeholder="Encoded By">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Date/Time Encoded</label>
                                <input type="text" class="encoded_datetime_encoded" name="encoded_datetime_encoded" value="" placeholder="Date/Time Encoded">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Priority</label>
                                <input type="text" class="encoded_priority" name="encoded_priority" value="" placeholder="Priority Level">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="custom-btn btn-dynamic" name="forward_document" type="submit">Forward</button>
                        <button class="custom-btn btn-close" id="close_popup4" type="button">Close</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="overlay" id="overlay"></div>
    <div class="content-wrapper">
        <table class="table content_table" id="my-Table">
            <thead>
                <th>Document ID</th>
                <th>Title</th>
                <th>Details</th>
                <th>Type</th>
                <th>No. Pages</th>
                <th>Receiver</th>
                <th>Sender</th>
                <th>Received Thru</th>
                <th>Document Date</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Forward</th>
                <th>Edit</th>
                <th>Routing Slip</th>
            </thead>
            <tbody id="tableBody">
                <!-- Table rows will be dynamically loaded here -->
            </tbody>
            <tr>
                <?php
                while ($row = $fetch_reply_pending_data->fetch(PDO::FETCH_ASSOC)) {
                    $dataList = [];
                ?>
                    <form action="#" method="POST">
                        <td data-label="document_id"><?php echo $row['document_id']; ?></td>
                        <td data-label="document_title"><?php echo $row['document_title']; ?></td>
                        <td data-label="document_desc"><?php echo $row['document_desc']; ?></td>
                        <td data-label="document_type"><?php echo $row['document_type']; ?></td>
                        <td data-label="no_pages"><?php echo $row['no_pages']; ?></td>
                        <td data-label="document_receiver"><?php echo $row['document_receiver']; ?></td>
                        <td data-label="document_sender"><?php echo $row['document_sender']; ?></td>
                        <td data-label="document_receive_type"><?php echo $row['document_receive_type']; ?></td>
                        <td data-label="document_date"><?php echo $row['document_date']; ?></td>
                        <td data-label="document_status"><?php echo $row['document_status']; ?></td>
                        <td data-label="priority" class="prioritized"><?php echo $row['priority']; ?></td>

                        <td data-label="encoded_by" class="hidden"><?php echo $row['encoded_by']; ?></td>
                        <td data-label="encoded_from" class="hidden"><?php echo $row['encoded_from']; ?></td>
                        <td data-label="datetime_encoded" class="hidden"><?php echo $row['datetime_encoded']; ?></td>
                        <td data-label="priority" class="hidden"><?php echo $row['priority']; ?></td>
                        <td data-label="remarks" class="hidden"><?php echo $row['remarks']; ?></td>
                        <td data-label="purpose" class="hidden"><?php echo $row['purpose']; ?></td>
                        <td data-label=""><button class="btn btn-forward pPop" id="openPopup" name="btn-forward" type="button">Forward</button></td>
                        <td data-label=""><button class="btn btn-edit pPop" id="openPopup" name="btn-edit" type="button">Edit</button></td>

                        <?php
                        // Assuming $row['document_id'] is constant for all rows, fetch it once outside the loop
                        $document_id = $row['document_id'];

                        // Initialize an array for the current document_id if it doesn't exist yet
                        if (!isset($dataList[$document_id])) {
                            $dataList[$document_id] = [];
                        }

                        foreach ($row as $key => $value) {
                            // Check if the key-value pair already exists in $dataList for the current document_id
                            $found = false;
                            foreach ($dataList[$document_id] as $item) {
                                if ($item['key'] === $key && $item['value'] === $value) {
                                    $found = true;
                                    break;
                                }
                            }

                            // If not found, add it to $dataList for the current document_id
                            if (!$found) {
                                $dataList[$document_id][] = array('key' => $key, 'value' => htmlspecialchars($value));
                            }
                        }
                        ?>
                        <td data-label="">
                            <button class="btn-print" name="btn-gen-slip" value="" type="button" onclick='passItem(<?php echo json_encode($dataList); ?>, "<?php echo $row['document_id']; ?>"); window.print();'>Print</button>
                        </td>
                    </form>
            </tr>
        <?php
                }
        ?>
        </table>
        <div class="pagination">
            <div class="pagination_container">
                <?php
                if ($fetch_reply_pending_data->rowCount() < 1) {
                    echo "NO DATA TO DISPLAY";
                }
                ?>
            </div>
        </div>
    </div>
</div>
<script>
    const selectElements = document.querySelectorAll(".target_select"); // Get all select elements
    const sessionTest = "<?php echo $_SESSION['logged_user_designation']; ?>";

    const sessionArray = sessionTest.split(','); // Convert to an array

    selectElements.forEach(selectElement => {
        Array.from(selectElement.options).forEach(option => { // Loop through each option
            if (sessionArray.includes(option.value)) {
                option.classList.add('hidden'); // Add 'hidden' class if value matches
            }
        });
    });
</script>

<script>
    const oic_checkbox = document.getElementById('oic_checker');

    if (oic_checkbox) {
        oic_checkbox.addEventListener('change', e => {
            if (e.target.checked) {
                document.querySelector('.input-dynamic').style.display = "none";
                document.querySelector('#check-container').style.display = "flex";
                document.querySelector('.document_to_oic').disabled = false;
                document.querySelector('#justification').style.display = "flex";
                document.querySelector('#justify_input').required = true;
            } else {
                document.querySelector('.input-dynamic').style.display = "flex";
                document.querySelector('.document_to_oic').disabled = true;
                document.querySelector('#check-container').style.display = "none";
                document.querySelector('#justification').style.display = "none";
                document.querySelector('#justify_input').required = false;
            }
        });
    }
</script>

<script>
    $(document).ready(function() {
        $(".prioritized").each(function() {
            if ($(this).text() == "Urgent") {
                $(this).parent().css("background-color", "lightyellow");
                $(this).parent().children('td').css("color", "orangered");
            }
        })
    });
</script>
<!--=============== MAIN.JS ===============!-->
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    // Get all buttons with class 'btn-forward'
    var buttons = document.querySelectorAll('.btn');

    // Loop through each button and attach click event listener
    buttons.forEach(function(button) {
        button.addEventListener('click', function() {
            // Get the row associated with the clicked button
            var row = this.closest('tr');

            var name = this.getAttribute('name');

            // Extract data from the row
            var document_ID = row.querySelector('[data-label="document_id"]').textContent;
            var document_title = row.querySelector('[data-label="document_title"]').textContent;
            var document_desc = row.querySelector('[data-label="document_desc"]').textContent;
            var document_type = row.querySelector('[data-label="document_type"]').textContent;
            var no_pages = row.querySelector('[data-label="no_pages"]').textContent;
            var document_receiver = row.querySelector('[data-label="document_receiver"]').textContent;
            var document_sender = row.querySelector('[data-label="document_sender"]').textContent;
            var document_receive_type = row.querySelector('[data-label="document_receive_type"]').textContent;
            var document_date = row.querySelector('[data-label="document_date"]').textContent;
            var encoded_by = row.querySelector('[data-label="encoded_by"]').textContent;
            var encoded_from = row.querySelector('[data-label="encoded_from"]').textContent;
            var datetime_encoded = row.querySelector('[data-label="datetime_encoded"]').textContent;
            var priority = row.querySelector('[data-label="priority"]').textContent;
            var purpose = row.querySelector('[data-label="purpose"]').textContent;

            // Send it via AJAX to the server
            document.querySelector('.encoded_document_id').value = document_ID;
            document.querySelector('.encoded_document_title').value = document_title;
            document.querySelector('.encoded_document_description').value = document_desc;
            document.querySelector('.encoded_document_no_pages').value = no_pages;
            document.querySelector('.encoded_document_receiver').value = document_receiver;
            document.querySelector('.encoded_document_sender').value = document_sender;
            document.querySelector('.encoded_document_date').value = document_date;
            document.querySelector('.document_encoded_from').value = encoded_from;
            document.querySelector('.document_encoded_by').value = encoded_by;
            document.querySelector('.encoded_datetime_encoded').value = datetime_encoded;
            document.querySelector('.encoded_priority').value = priority;
            document.querySelector('.encoded_purpose').value = purpose;

            const encoded_document_receive_type = document.getElementById('encoded_document_receive_type');
            const encoded_document_receive_type_options = encoded_document_receive_type.options;
            for (var i = 0; i < encoded_document_receive_type_options.length; i++) {
                if (encoded_document_receive_type_options[i].text === document_receive_type) {
                    encoded_document_receive_type_options[i].selected = document_receive_type;
                    break;
                }
            }
            const encoded_document_type = document.getElementById('encoded_document_type');
            const encoded_document_type_options = encoded_document_type.options;
            for (var i = 0; i < encoded_document_type_options.length; i++) {
                if (encoded_document_type_options[i].text === document_type) {
                    encoded_document_type_options[i].selected = document_type;
                    break;
                }
            }

            if (name === "btn-forward") {
                document.getElementById("encoded_pending_form").setAttribute('action', '../../protected/handler/forward_module/forward_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Forward Document";
                document.getElementById("form_title").textContent = "Forward Document";
                document.querySelector(".btn-dynamic").setAttribute("name", "forward_document");
                document.querySelector(".btn-dynamic").classList.add("btn-forward");
                document.querySelector(".btn-dynamic").classList.remove("btn-edit");
                if (document.querySelector(".checkbox-oic")) {
                    document.querySelector(".checkbox-oic").classList.remove("hidden_input");
                }

            } else if (name === "btn-edit") {
                document.getElementById("encoded_pending_form").setAttribute('action', '../../protected/handler/edit_module/edit_reply.php');
                document.querySelector(".btn-dynamic").textContent = "Edit Document";
                document.getElementById("form_title").textContent = "Edit Document";
                document.querySelector(".btn-dynamic").setAttribute("name", "edit_document");
                document.querySelector(".btn-dynamic").classList.add("btn-edit");
                document.querySelector(".btn-dynamic").classList.remove("btn-forward");
                if (document.querySelector(".checkbox-oic")) {
                    document.querySelector(".checkbox-oic").classList.remove("hidden_input");
                }

                document.querySelectorAll('.encoded_document_title, .encoded_document_type, .encoded_document_receiver, .encoded_document_receive_type, .encoded_document_description, .encoded_document_sender, .encoded_document_date, .encoded_document_no_pages').forEach(function(input) {
                    input.removeAttribute("readonly");
                });
            }

        });
    });
</script>

<script>
    const checkbox2 = document.getElementById('checker_priority');

    if (checkbox2) {
        checkbox2.addEventListener('change', e => {
            if (e.target.checked) {
                document.getElementById('priority-container').style.display = "flex";
                document.querySelector('.priority_status').removeAttribute('disabled');
            } else {
                document.getElementById('priority-container').style.display = "none";
                document.querySelector('.priority_status').setAttribute('disabled', "true");
            }
        });
    }

    function setSession() {
        sessionStorage.setItem("checked2", "true");
        console.log(sessionStorage.getItem("checked2"));
    }
</script>

</body>

</html>