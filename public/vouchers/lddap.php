<?php
require_once __DIR__ . '/../../protected/core/components/helpers/amount_helper.inc.php';
// Retrieve JSON data from the request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);
// Extract table data (which now includes form data in each row)
$tableData = $data['data'] ?? [];

$row = $tableData[0];
$total_amount = '0';

foreach ($tableData as $row) {
    $part = normalize_amount_string((string) ($row['amount'] ?? ''));
    if ($part === '') {
        continue;
    }
    if (function_exists('bcadd')) {
        $total_amount = bcadd($total_amount, $part, 12);
    } else {
        $total_amount = normalize_amount_string((string) ((float) $total_amount + (float) $part));
    }
}
$formatted_total = format_amount_display($total_amount);

echo "<script>console.log('.$total_amount.')</script>";

function numberToWords($num) {
    $words = [
        'zero', 'one', 'two', 'three', 'four', 'five', 
        'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 
        'twelve', 'thirteen', 'fourteen', 'fifteen', 
        'sixteen', 'seventeen', 'eighteen', 'nineteen', 
        'twenty', 'thirty', 'forty', 'fifty', 'sixty', 
        'seventy', 'eighty', 'ninety'
    ];

    if ($num < 0) return 'MINUS ' . numberToWords(abs($num));
    if ($num < 21) return strtoupper($words[$num]);
    if ($num < 100) return strtoupper($words[20 + floor(($num - 20) / 10)]) . ($num % 10 ? ' ' . strtoupper($words[$num % 10]) : '');
    if ($num < 1000) return strtoupper($words[floor($num / 100)]) . ' HUNDRED' . ($num % 100 ? ' AND ' . numberToWords($num % 100) : '');

    if ($num < 1000000) {
        return numberToWords(floor($num / 1000)) . ' THOUSAND' . ($num % 1000 ? ' ' . numberToWords($num % 1000) : '') . ' PESOS';
    }
    else if ($num < 1000000000) {
        return numberToWords(floor($num / 1000000)) . ' MILLION' . ($num % 1000000 ? ' ' . numberToWords($num % 1000000) : '') . ' PESOS';
    }

    return 'NUMBER TOO LARGE';
}


$word_amount = numberToWords($total_amount)
?>

<!--=============== MAIN ===============!-->
<style>
    @media print {
        body * {
            visibility: hidden;
            margin: 0;
            /* Set margin of the entire page to zero */
            padding: 0;
        }

        .sidebar {
            display: none;
        }

        .main {
            width: 100%;
        }

        #printableTable {
            display: flex;
            flex-direction: column;
        }

        #printableTable,
        #printableTable * {
            visibility: visible;

        }

        #printableTable {
            position: absolute;
            left: 0;
            /* Align to the left */
            text-align: center;
            /* Center the table horizontally */
            margin: 0;
            padding: 0;
            /* Remove default padding */
            width: 100%;
        }

        #printableTable th,
        #printableTable td {
            font-size: 9px;
        }

        @page {
            size: portrait;
            /* auto is the default value, it will fit the content to the available space */
            margin-top: 0;
        }

        #printableTable table {
            width: 100%;
            /* Set table width to 100% */
            border-collapse: collapse;
            /* Collapse table borders */
        }
    }
</style>
<style>
    .printableTable {
        display: none;
        z-index: -99;

        * {
            padding: 0;
            margin: 0;
        }

        table {
            border-collapse: collapse;
        }

        table,
        tr,
        td {
            border: thin solid #000000;
        }


        .flex-centered {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .text-centered {
            text-align: center;
        }

        .w-full {
            width: 100%;
        }

        .flexed {
            flex: 1;
        }

        .flex-2 {
            flex: 2;
        }

        .flex-3 {
            flex: 3;
        }

        .flex-8 {
            flex: 8;
        }

        .flex-col {
            display: flex;
            flex-direction: column;
        }

        .flex-row {
            display: flex;
            flex-direction: row;
        }

        .custom-flex {
            flex: 1 1 16.75%;
        }

        .space-justified {
            justify-content: space-between;
        }

        .text-left {
            text-align: left;
        }

        .text-xl {
            font-size: 35px;
        }

        .text-sm {
            font-size: 15px;
        }

        .text-xsm {
            font-size: 10px;
        }

        .text-xm {
            font-size: 9px;
        }

        .text-bold {
            font-weight: bold;
        }

        .pad-5 {
            padding: 5px;
        }

        .gap-5 {
            gap: 50px;
        }

        .gap-1 {
            gap: 10px;
        }

        .ml-90 {
            margin-left: 90px;
        }

        .underlined {
            text-decoration: underline;
        }

        .border-none {
            border: none;
        }

        .bordered {
            border: thin solid #000000;
        }

        .border-bottom {
            border-bottom: thin solid #000000;
        }

        .border-right {
            border-right: thin solid #000000;
        }

        tbody {
            display: flex;
            flex-direction: column;

            tr {
                display: flex;

                td {
                    flex: 1;
                }
            }

            .data-container {
                display: flex;
            }
        }
    }
</style>
<div class="printableTable bordered" id="printableTable" style="margin-top: 50px">
    <table>
        <thead class="flex-col" style="padding: 20px;">
            <tr class="title flex-centered border-none" style="margin-bottom: 25px;">
                <th class="flexed w-full text-bold" style="font-size: 18px;">LIST OF DUE AND DEMANDABLE ACCOUNTS PAYABLE - ADVICE TO DEBIT ACCOUNTS (LDDAP-ADA)</th>
            </tr>
            <tr class="text-sm border-none flex-row space-justified text-left" style="width: 900px;">
                <td class="border-none flexed text-left">DEPARTMENT: </td>
                <td class="border-none text-left flexed">DENR</td>
            </tr>
            <tr class="text-sm border-none flex-row space-justified" style="width: 900px;">
                <td class="border-none flexed text-left">AGENCY: </td>
                <td class="border-none text-left flexed">DENR</td>
            </tr>
            <tr class="text-sm border-none flex-row space-justified" style="width: 900px;">
                <td class="border-none flexed text-left">OPERATING UNIT: </td>
                <td class="border-none text-left flexed">PENRO EASTERN SAMAR</td>
            </tr>
            <tr class="text-sm border-none flex-row space-justified" style="width: 900px;">
                <td class="border-none flexed text-left">FUND CODE: </td>
                <td class="border-none text-left flexed">101</td>
            </tr>
            <tr class="text-sm border-none flex-row space-justified" style="width: 900px;">
                <td class="border-none flexed text-left">MDS-GSB BRANCH/MDS SUB ACCOUNT NO. </td>
                <td class="border-none text-left flexed">LBP BORONGAN 002120-9002-88</td>
            </tr>
        </thead>
        <tbody>
            <tr class="flex-centered">
                <th class="border-none" style="font-size: 15px;">I. LIST OF DUE AND DEMANDABLE ACCOUNTS PAYABLE (LDDAP-ADA)</th>
            </tr>
            <tr class="text-sm">
                <td class="flex-centered flex-col flex-2">
                    <div class="flex-centered border-bottom w-full">
                        <h4 class="text-sm">CREDITOR</h4>
                    </div>
                    <div class="data-container space-justified w-full text-centered">
                        <h5 class="flex-centered border-right text-sm">NAME</h5>
                        <h5 class="flex-centered text-xsm">PREFERRED SERVICING BANK/SAVINGS/CURRENT ACCT. NO.</h5>
                    </div>
                </td>
                <td class="flex-centered">
                    <div class="data-container text-sm">
                        <h5 class="flex-centered">OBLIGATION REQUEST NO.</h5>
                    </div>
                </td>
                <td class="flex-centered flex-col flex-2">
                    <div class="flex-centered border-bottom w-full text-sm">
                        <h4>IN PESOS</h4>
                    </div>
                    <div class="data-container flexed w-full space-justified text-centered text-sm">
                        <h5 class="flex-centered border-right">GROSS AMOUNT</h5>
                        <h5 class="flex-centered border-right">WITHHOLDING TAX</h5>
                        <h5 class="flex-centered">NET AMOUNT</h5>
                    </div>
                </td>
                <td class="flex-centered">
                    <div class="data-container text-sm">
                        <h5 class="flex-centered">REMARKS</h5>
                    </div>
                </td>
            <tr>
                <td class="flex-row adjust-target custom-flex">
                    <div class="flexed flex-col text-xsm border-right space-justified" style="padding-left: 5px; margin-right: 5px;">
                        <div class="text-left">II. Prior Year's A/Ps</div>
                        <?php foreach ($tableData as $row) { ?>
                            <div class="text-left"><?php echo $row['payee']; ?></div>
                        <?php } ?>
                    </div>
                    <div class="flexed flex-row space-justified">
                        <div class="flexed flex-centered border-right">LBP</div>
                        <div class="flexed flex-centered"></div>
                    </div>
                </td>
                <td></td>
                <td class="flex-row" style="flex: 1 1 16.6%;">
                    <div class="flexed flex-row space-justified">
                        <div class="flexed flex-centered border-right flex-col">
                            <?php foreach ($tableData as $row) { ?>
                                <div><?php echo $row['amount']; ?></div>
                            <?php }
                            ?>
                        </div>
                    </div>
                    <div class="flexed flex-row space-justified">
                        <div class="flexed flex-centered border-right"></div>
                    </div>
                    <div class="flexed flex-row space-justified">
                        <div class="flexed flex-centered flex-col">
                            <?php foreach ($tableData as $row) { ?>
                                <div><?php echo $row['amount']; ?></div>
                            <?php }
                            ?>
                        </div>
                    </div>
                </td>
                <td class="flex-row">
                    <div class="flexed flex-row space-justified">
                        <div class="flexed flex-centered text-xsm">FOR MDS-GSB USE ONLY</div>
                    </div>
                </td>
            </tr>

            <tr>
                <td class="flexed flex-centered text-bold text-sm" style="padding: 10px;">*** NOTHING FOLLOWS ***</td>
            </tr>
            <tr>
                <td class="flex-row adjust-target custom-flex">
                    <div class="flexed flex-col text-sm pad-5">
                        <div>Total</div>
                    </div>
                </td>
                <td></td>
                <td class="flex-row" style="flex: 1 1 16.4%;">
                    <div class="flexed flex-row space-justified">
                        <div class="flexed flex-centered text-bold"><?php echo $formatted_total ?></div>
                    </div>
                    <div class="flexed flex-row space-justified">
                        <div class="flexed flex-centered"></div>
                    </div>
                    <div class="flexed flex-row space-justified">
                        <div class="flexed flex-centered text-bold"><?php echo $formatted_total ?></div>
                    </div>
                </td>
                <td class="flex-row">
                    <div class="flexed flex-row space-justified">
                        <div class="flexed flex-centered text-sm"></div>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="flexed">
                    <div class="flexed flex-col gap-5">
                        <div class="flexed flex-row">
                            <div class="flexed flex-col pad-5 gap-5">
                                <div class="text-xsm text-bold text-left">I hereby warrant that the above list of Due and Demandable A/Ps was prepared in accordance with existing budgeting, accounting and auditing rules and regulations</div>
                                <div class="text-sm text-left">Certified Correct:</div>
                                <div class="ml-90 text-centered">
                                    <p class="underlined text-sm"><?php echo $row['certified_correct'] ?></p>
                                    <?php if($row['certified_correct'] === "AMOR A. ROBREDILLO") : ?>
                                        <p>Chief, MSD</p>
                                    <?php elseif($row['certified_correct'] === "ERIC P. LAGUNZAD") : ?>
                                        <p>Budget Officer II</p>
                                    <?php endif?>
                                </div>
                            </div>
                            <div class="flexed flex-col pad-5 gap-5">
                                <div class="text-xsm text-bold text-left">I hereby assume full responsibility for the veracity and accuracy of the listed claims, and the Authencity of the supporting of documents as submitted by the claimants</div>
                                <div class="text-sm text-left">Approved:</div>
                                <div class="ml-90 text-centered">
                                    <p class="underlined text-sm"><?php echo $row['approved_by'] ?></p>
                                    <?php if($row['approved_by'] === "LEA O. TORRES") : ?>
                                        <p>PENR Officer</p>
                                    <?php elseif($row['approved_by'] === "JENNY ROSE T. CORAL") : ?>
                                        <p>Chief, TSD</p>
                                    <?php endif?>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="pad-5">
                    <div class="flexed flex-col" style="margin-bottom: 40px;">
                        <div class="flexed flex-col text-xm gap-1 pad-5">
                            <div class="text-left">To MDS_GSB of the Agency</div>
                            <div class="text-left">Please debit MDS Sub-Account Number 00212D-9002-88</div>
                            <div class="text-left">Please credit the accounts of the above listed creditors payment of accounts payable (A/Ps)</div>
                        </div>
                    </div>
                    <div class="flexed flex-row space-justified">
                        <div class="text-left text-xsm">TOTAL AMOUNT:</div>
                        <div class="flexed flex-centered flex-col flex-8">
                            <div class="text-bold underlined" style="margin-bottom: 5px; font-size: 15px;"><?php echo $word_amount?></div>
                            <p class="text-xsm">(In Words)</p>
                        </div>
                        <div class="flexed flex-centered flex-row gap-1">
                            <div class="text-sm">₱</div>
                            <div class="underlined text-sm" style="margin-right: 20px;"><?php echo $formatted_total ?></div>
                        </div>
                    </div>
                    <div class="flexed flex-col flex-centered">
                        <div class="flexed flex-centered text-bold" style="margin-top: 30px;">Agency Authorized Signatories</div>
                        <div class="flexed flex-row" style="justify-content: center; width: 100%; gap: 30%; margin-top: 50px; margin-bottom: 30px; white-space: nowrap">
                            <div class="text-centered">
                                <p class="underlined text-sm"><?php echo $row['approved_by'] ?></p>
                                <?php if($row['approved_by'] === "LEA O. TORRES") : ?>
                                        <p>PENR Officer</p>
                                <?php elseif($row['approved_by'] === "JENNY ROSE T. CORAL") : ?>
                                        <p>Chief, TSD</p>
                                <?php endif?>
                            </div>
                            <div class="text-centered">
                                <p class="underlined text-sm"><?php echo $row['agency_authorized_signatory'] ?></p>
                                <?php if($row['agency_authorized_signatory'] === "ANTONIETTE C. DE LOS SANTOS") : ?>
                                        <p>Cashier</p>
                                <?php elseif($row['agency_authorized_signatory'] === "JOCELYN M. OSTREA") : ?>
                                        <p>Admin. Officer IV</p>
                                <?php endif?>
                            </div>
                        </div>
                        <div class="flexed flex-centered text-xm">(Erasures shall invalidate this document)</div>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="text-sm pad-5 text-left">FOR MDS-GSB USE ONLY:</td>
            </tr>
            <tr>
                <td>
                    <div class="flexed flex-col text-xm pad-5">
                        <div class="text-left">Instructions:</div>
                        <div class="text-left">1. Agency shall arrange the creditors on a "first in, first out" basis, that is according to the date of receipt of supplier's/creditor's billing duly supported.</div>
                        <div class="text-left">2. MDS-GSB branch concered shall indicate under 'Remarks' column, non-payments made to concerned creditors due to inconsistency in information.</div>
                        <div class="text-left">( creditor account name, number ) between LDDAP-ADA and bank records.</div>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="flexed flex-row space-justified">
                    <div class="flexed flex-col text-xsm pad-5 gap-1 flex-2 text-left">
                        <div class="text-bold">NOTES:</div>
                        <div>The LDDAP-ADA is an accountable form</div>
                        <div>*Indicate the description/name and UACS code</div>
                    </div>
                    <div class="flexed flex-row text-xsm pad-5 gap-1">
                        <div class="flexed flex-col space-justified">
                            <div class="text-bold text-left">Check No.:</div>
                            <div class="text-bold text-left" style="font-size: 9px;">LDDAP-ADA No.:</div>
                            <div class="text-left">Date of Issue:</div>
                        </div>
                        <div class="flexed flex-col space-justified flex-3">
                            <div class="text-bold underlined text-left"><?php echo $row['check_no'];?></div>
                            <div class="text-bold underlined text-left"><?php echo $row['ada_no'];?></div>
                            <div class="text-left"><?php echo $row['ada_check_date']; ?></div>
                        </div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>