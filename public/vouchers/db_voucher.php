<?php
// DV printable template signatories (managed in Utilities -> DV Signatories)
// Expect $pdo to be available from including page.
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        utilities_signatory_ensure_schema($pdo);
    }
} catch (Throwable $e) {
    // ignore
}

function dv_fetch_all_signatories(PDO $pdo, ?string $office = null): array
{
    $resolvedOffice = utilities_signatory_resolve_office($pdo, $office);

    return utilities_fetch_dv_signatory_map($pdo, $resolvedOffice);
}

$dv_signatory_labels = [
    'dv_certified_msd' => 'A. Certified (MSD)',
    'dv_certified_tsd' => 'A. Certified (TSD)',
    'dv_accounting_certified' => 'C. Certified (Accounting)',
    'dv_approved_for_payment' => 'D. Approved for Payment',
];
$dv_signatory_roles = [
    'cert' => ['dv_certified_msd', 'dv_certified_tsd'],
    'accounting' => ['dv_accounting_certified'],
    'approved' => ['dv_approved_for_payment'],
];
$dv_default_cert_key = (($_SESSION['logged_user_division'] ?? '') === 'TSD')
    ? 'dv_certified_tsd'
    : 'dv_certified_msd';
$dv_signatory_options = (isset($pdo) && $pdo instanceof PDO)
    ? dv_fetch_all_signatories($pdo, utilities_signatory_default_office())
    : [];
$dv_can_select_signatory_office = utilities_signatory_can_select_office();
$dv_signatory_offices = ($dv_can_select_signatory_office && isset($pdo) && $pdo instanceof PDO)
    ? utilities_signatory_fetch_offices($pdo)
    : [];

require_once __DIR__ . '/dv_accounting_helper.inc.php';

$dv_emp_tag_lookup = [
    'defaultEmpTag' => 'Other Professional Services',
    'loggedUserName' => (string) ($_SESSION['logged_user_emp_name'] ?? ''),
    'payeeEmpTags' => [],
    'payeeEmpTagsLower' => [],
    'payeeEmpTagsByEmpId' => [],
];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $dv_emp_tag_lookup = dv_build_emp_tag_lookup(
            $pdo,
            (string) ($_SESSION['logged_user_emp_id'] ?? ''),
            (string) ($_SESSION['logged_user_emp_name'] ?? '')
        );
    } catch (Throwable $e) {
        // ignore
    }
}

$dv_contractual_voucher_types = dv_contractual_voucher_types();
$dv_emp_tag_salary_maps = (isset($pdo) && $pdo instanceof PDO)
    ? utilities_emp_tag_build_salary_maps($pdo)
    : [];
$dv_voucher_type_accounting_maps = (isset($pdo) && $pdo instanceof PDO)
    ? dv_build_voucher_type_accounting_maps($pdo)
    : [];

require_once __DIR__ . '/../../protected/core/components/helpers/voucher_tracking_helper.inc.php';
$dv_logged_user_designations = voucher_logged_user_designations();
$dv_can_unlock_payee = voucher_user_can_unlock_payee($dv_logged_user_designations);
?>
    <!--=============== MAIN ===============!-->
    <script src="../../protected/js/amount_helper.js"></script>
    <script src="../../protected/js/fetch_voucher_form_data.js"></script>
    <script>
        window.DV_ACCOUNTING = <?= json_encode([
            'uacsMap' => dv_uacs_code_map($pdo),
            'contractualTypes' => $dv_contractual_voucher_types,
            'knownEmpTags' => dv_known_emp_tags($pdo),
            'defaultEmpTag' => $dv_emp_tag_lookup['defaultEmpTag'],
            'loggedUserName' => $dv_emp_tag_lookup['loggedUserName'],
            'payeeEmpTags' => $dv_emp_tag_lookup['payeeEmpTags'],
            'payeeEmpTagsLower' => $dv_emp_tag_lookup['payeeEmpTagsLower'],
            'payeeEmpTagsByEmpId' => $dv_emp_tag_lookup['payeeEmpTagsByEmpId'],
            'salaryCommonTitles' => dv_salary_common_account_titles($pdo ?? null),
            'empTagSalaryMaps' => $dv_emp_tag_salary_maps,
            'voucherTypeAccountingMaps' => $dv_voucher_type_accounting_maps,
            'accountingMinRows' => 8,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
        window.DV_SIGNATORY = <?= json_encode([
            'options' => array_values($dv_signatory_options),
            'optionsByKey' => $dv_signatory_options,
            'labels' => $dv_signatory_labels,
            'roles' => $dv_signatory_roles,
            'defaultCertKey' => $dv_default_cert_key,
            'office' => utilities_signatory_default_office(),
            'canSelectOffice' => $dv_can_select_signatory_office,
            'offices' => $dv_signatory_offices,
            'penroOffice' => utilities_signatory_penro_office(),
            'fetchUrl' => '../../protected/handler/fetch_handlers/fetch_dv_signatories.php',
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
    </script>
    <style>
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: #fff;
            }

            html.page-loading #main::before,
            html.page-loading #main::after,
            #main::before,
            #main::after {
                display: none !important;
                visibility: hidden !important;
                content: none !important;
            }

            .header,
            .sidebar,
            .overlay,
            .popup-form,
            .popup-form3,
            .main,
            #main,
            .voucher-card,
            .table-loader {
                display: none !important;
            }

            body.dv-printing > :not(#printableTable):not(script):not(style) {
                display: none !important;
            }

            .printableTable,
            #printableTable {
                display: none !important;
            }

            body.dv-printing .printableTable,
            body.dv-printing #printableTable {
                display: block !important;
                visibility: visible !important;
                position: static !important;
                left: auto !important;
                top: auto !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                pointer-events: auto !important;
                overflow: visible !important;
                z-index: auto !important;
            }

            body.dv-printing #printableTable,
            body.dv-printing #printableTable * {
                visibility: visible !important;
            }

            body.dv-printing #printableTable {
                text-align: center;
            }

            body.dv-printing #printableTable th,
            body.dv-printing #printableTable td {
                font-size: 9px;
            }

            body.dv-printing #printableTable .dv-accounting-row td {
                min-height: 20px;
                padding-top: 4px;
                padding-bottom: 4px;
            }

            body.dv-printing #printableTable #dv_accounting_body {
                padding-bottom: 10px;
                min-height: calc(var(--dv-accounting-min-rows, 8) * var(--dv-accounting-row-height, 30px));
            }

            @page {
                size: portrait;
                /* auto is the default value, it will fit the content to the available space */
                margin-top: 5;
            }

            body.dv-printing #printableTable table {
                width: 100%;
                border-collapse: collapse;
            }
        }
    </style>
    <style>
        /* Excel-like styling */
        table {
            border-collapse: collapse;
            width: 100%;
        }

        .printableTable {
            display: none !important;
        }

        #printableTable {
            .text-centered {
                text-align: center;
            }

            .pad-5 {
                padding: 5px;
            }

            .pad-2 {
                padding: 2px;
            }

            tr {
                border: none;
            }
        }

        #printableTable th,
        #printableTable td {
            border: 2px solid #000;
            text-align: left;
        }

        #printableTable .t1 {
            white-space: nowrap;
        }

        #printableTable .header_dv {
            display: flex;
        }

        #printableTable .header_dv_title {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            line-height: 1.2;
        }

        #printableTable .dv_logo {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #printableTable .dv-account-title {
            text-align: left;
            vertical-align: top;
        }

        #printableTable .dv-account-title--indent {
            padding-left: 1.35em !important;
        }

        #printableTable .dv-uacs-code {
            text-align: center;
            vertical-align: top;
        }

        #printableTable #dv_accounting_body td {
            border-top: none !important;
            border-bottom: none !important;
        }

        #printableTable .dv-accounting-row td {
            font-size: 11px;
            line-height: 1.35;
            min-height: 22px;
            padding-top: 4px;
            padding-bottom: 4px;
            vertical-align: top;
        }

        #printableTable #dv_accounting_body {
            padding-bottom: 10px;
            min-height: calc(var(--dv-accounting-min-rows, 8) * var(--dv-accounting-row-height, 30px));
        }

        #printableTable .dv-account-title {
            min-height: var(--dv-accounting-row-height, 30px);
        }

        #printableTable #dv_accounting_body.dv-accounting-body--empty .dv-accounting-row td {
            min-height: 22px;
        }
    </style>
    <div class="printableTable" id="printableTable" style="margin-top: 20px;">
        <table class="db-voucher__table" id="my-Table">
            <thead class="db-voucher__header">
                <tr>
                    <th colspan="4" rowspan="3" sty>
                        <div class="header_dv">
                            <div class="header_dv_title" style="margin-top: 5px;">
                                <p style="font-size: 9px">Republic of the Philippines</p>
                                <p style="font-size: 10px; font-weight: 900;">Department of Environment and Natural Resources</p>
                                <p style="color: limegreen; font-size: 12px; font-weight: 700;">PROVINCIAL ENVIRONMENT AND NATURAL RESOURCES OFFICE</p>
                                <p style="font-size: 9px">Borongan City, Eastern Samar</p>
                                <p style="margin-top: 13px; font-size: 16px">DISBURSEMENT VOUCHER</p>
                            </div>
                        </div>
                    </th>
                </tr>
                <tr>
                    <td class="fixed-size" style="padding: 5px; height: 35px; position: relative;">
                        <div style="max-width: 90%; position: absolute; top: 0; left: 0;">
                            <label for="" style="font-weight: bold; padding-left: 2px">Fund Cluster:</label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="fixed-size" style="min-width: 120px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; max-width: 90%">
                            <label for="" style="font-weight: bold; padding-left: 2px">Date:</label>
                            <p><p>
                            <p id="voucher_form_voucher_date" style="display: none;"></p>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; max-width: 90%">
                            <label for="" style="font-weight: bold; padding-left: 2px">DV No.:</label>
                            <p></p>
                        </div>
                    </td>
                </tr>
            </thead>
            <tbody>
                <thead>
                    <tr>
                        <th class="fixed-title pad-5">Mode of Payment</th>
                        <td colspan="4">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px;">
                                <div style="display: flex; align-items:center; gap: 7%; white-space: nowrap">
                                    <input type="checkbox"> MDS Check
                                </div>
                                <div style="display: flex; align-items:center; gap: 7%; white-space: nowrap">
                                    <input type="checkbox"> Commercial Check
                                </div>
                                <div style="display: flex; align-items:center; gap: 7%; white-space: nowrap">
                                    <input type="checkbox"> ADA
                                </div>
                                <div style="display: flex; align-items:center; gap: 7%; white-space: nowrap">
                                    <input type="checkbox"> Others (Please Specify)
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th class="fixed-title pad-5">Payee</th>
                        <td colspan="2" id="voucher_form_payee" style="padding-left: 5px !important; padding-top: 10px; padding-bottom: 10px; font-size: 13px; font-weight: 800"></td>
                        <td style="position: relative;">
                            <div style="position: absolute; top: 0; height: 20px">
                                <p style="margin: 0; white-space: nowrap;">TIN/Employee No.</p>
                                <p id="voucher_form_tin_employee_no" style="margin: 7px 0 0 5px"></p>
                            </div>
                        </td>
                        <td style="position: relative;">
                            <div style="position: absolute; top: 0; height: 20px">
                                <p style="margin: 0; white-space: nowrap;">ORS/BURS No.</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th class="fixed-title pad-5">Address</th>
                        <td colspan="4" id="voucher_form_address" style="padding-left: 5px !important; padding-top: 10px; padding-bottom: 10px; font-size: 13px; font-weight: 800"></td>
                    </tr>
                </thead>
                <thead>
                    <th class="text-centered" colspan="2">Particulars</th>
                    <th class="text-centered pad-5">Responsibility Center</th>
                    <th class="text-centered">MFO/PAP</th>
                    <th class="text-centered" colspan="2">AMOUNT</th>
                </thead>
                <tr>
                    <td colspan="2" class="pad-5" id="voucher_form_particulars" style="height: 100px !important; font-size: 13px; font-weight: 400; font-style: italic;"></td>
                    <td class="text-centered"></td>
                    <td class="text-centered"></td>
                    <td class="text-centered" id="voucher_form_amount" style="font-size: 13px; font-weight: 400" colspan="2"></td>
                </tr>
                <tr>
                    <th class="text-centered pad-2" colspan="2">Amount Due</th>
                    <td></td>
                    <td></td>
                    <td id="voucher_form_amount2" class="text-centered" style="font-size: 13px; font-weight: 800" colspan="2"></td>
                </tr>
                <tr>
                    <td style="padding: 0; height: 140px !important;" colspan="5">
                        <div style="height: 100%; width: 100%; padding: 0; margin: 0; display: flex; flex-direction: column; justify-content: space-between;">
                            <div style="width: 100%; text-align: left;">
                                <p>A. Certified: Expenses/Cash Advance necessary,
                                    lawful and incurred under my direct supervision.</p>
                            </div>
                            <div style="display:flex; flex-direction: column; gap: 7%; justify-content: center; align-items: center; margin-bottom: 5px;">
                                <p style="text-decoration: underline; font-size: 14px; font-weight: 800" id="dv_sig_cert_name"></p>
                                <p id="dv_sig_cert_pos1"></p>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
            <tbody>
                <tr class="fixed_text_alignment">
                    <th colspan="5" class="pad-2">B. Accounting Entry:</th>
                </tr>
                <tr>
                    <th class="text-centered" colspan="2">Account Title</th>
                    <th class="text-centered">UACS Code</th>
                    <th class="text-centered">Debit</th>
                    <th class="text-centered">Credit</th>
                </tr>
            </tbody>
            <tbody id="dv_accounting_body" class="dv-accounting-body--empty" style="--dv-accounting-min-rows: 8; --dv-accounting-row-height: 30px;">
                <?php for ($i = 0; $i < 8; $i++): ?>
                <tr class="dv-accounting-row dv-accounting-row--empty">
                    <td class="pad-2 dv-account-title" colspan="2">&nbsp;</td>
                    <td class="pad-2 dv-uacs-code text-centered" style="width: 100px !important;">&nbsp;</td>
                    <td class="pad-2 dv-debit">&nbsp;</td>
                    <td class="pad-2 dv-credit">&nbsp;</td>
                </tr>
                <?php endfor; ?>
            </tbody>
            <tbody>
                <tr class="fixed_text_alignment">
                    <th colspan="2" class="fixed-title pad-2">C. Certified</th>
                    <th colspan="3" class="pad-2">D. Approved for Payment</th>
                </tr>
                <tr>
                    <td class="no-line" style="padding: 2px;" colspan="2">
                        <div style="margin-left: 25px;">
                            <div style="display: flex; align-items: center; gap: 2%">
                                <input type="checkbox">
                                <label for="">Cash available</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 2%">
                                <input type="checkbox">
                                <label for="">Subject to Authority to Debit Account (when applicable)</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 2%">
                                <input type="checkbox">
                                <label for="">Supporting documents complete and amount claimed proper</label>
                            </div>
                        </div>
                    </td>
                    <td class="no-line" colspan="4"></td>
                </tr>
                <tr>
                    <th class="fixed-title text-centered">Signature</th>
                    <td class="signature-fixed" colspan="1" style="height: 40px !important;"></td>
                    <th class="fixed-title text-centered">Signature</th>
                    <td class="signature-fixed" colspan="2" style="height: 40px !important;"></td>
                </tr>
                <tr>
                    <th class="fixed-title text-centered pad-5">Printed Name</th>
                    <td class="fixed-ratio text-centered pad-5" style="font-size: 13px; font-weight: 800" id="dv_sig_accounting_name"></td>
                    <th class="fixed-title text-centered pad-5">Printed Name</th>
                    <td class="text-centered pad-5" colspan="2" style="font-size: 13px; font-weight: 800" id="dv_sig_approved_name"></td>
                </tr>
                <tr>
                    <th class="text-centered pad-5">Position</th>
                    <td class="t1 fixed-ratio text-centered pad-5">
                        <div>
                            <p id="dv_sig_accounting_pos1"></p>
                            <p id="dv_sig_accounting_pos2"></p>
                        </div>
                    </td>
                    <th class="text-centered pad-5">Position</th>
                    <td class="t1 text-centered" colspan="2">
                        <div>
                            <p id="dv_sig_approved_pos1"></p>
                            <p id="dv_sig_approved_pos2"></p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th class="text-centered pad-5">Date</th>
                    <td></td>
                    <th class="text-centered pad-5">Date</th>
                    <td colspan="2"></td>
                </tr>
                <tr>
                    <th colspan="4" class="pad-2">E. Receipt of Payment</th>
                    <th style="padding-left: 5px; border-bottom: none !important">JEV No.</th>
                </tr>
                <tr>
                    <th class="text-centered pad-5">Check/ADA No.</th>
                    <td class="pad-5"></td>
                    <td class="pad-5">Date:</td>
                    <td class="pad-5">Bank Name & Account Number:</td>
                    <td class="pad-5" style="border-top: none !important;"></td>
                </tr>
                <tr>
                    <th class="text-centered pad-5">Signature</th>
                    <td class="pad-5" style="height: 40px !important;"></td>
                    <td class="pad-5">Date:</td>
                    <td style="padding: 0; margin: 0;">
                        <div style="height: 100% !important; width: 100%;white-space: nowrap; padding: 0 5px 5px 5px; display: flex; flex-direction: column;">
                            <p style="margin-bottom: 3%">Printed Name:</p>
                            <p id="voucher_form_payee2" style="font-weight: 900;">Test</p>
                        </div>
                    </td>
                    <td class="pad-5">Date</td>
                </tr>
                <tr class="fixed_text_alignment">
                    <th colspan="5" class="pad-2">Official Receipt No. & Date/Other Documents</th>
                </tr>
            </tbody>
        </table>
    </div>
    <script>
        (function () {
            function prepareDvPrint() {
                document.documentElement.classList.remove('page-loading');
                document.documentElement.classList.add('page-loaded');
                document.body.classList.add('dv-printing');

                var printableRoot = document.getElementById('printableTable');
                if (printableRoot) {
                    document.body.appendChild(printableRoot);
                }

                if (typeof setDocumentData === 'function') {
                    setDocumentData();
                }
            }

            function cleanupDvPrint() {
                document.body.classList.remove('dv-printing');
            }

            function isForwardSlipPrint() {
                return document.body.classList.contains('forward-slip-printing');
            }

            window.addEventListener('beforeprint', function () {
                if (isForwardSlipPrint()) {
                    return;
                }
                prepareDvPrint();
            });
            window.addEventListener('afterprint', function () {
                if (isForwardSlipPrint()) {
                    return;
                }
                cleanupDvPrint();
            });
            window.prepareDvPrint = prepareDvPrint;
        })();
    </script>
