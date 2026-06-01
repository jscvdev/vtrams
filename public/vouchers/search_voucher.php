<?php
require '../../protected/core/components/security/err_blocker.inc.php';
require '../../protected/dbconnection.inc.php';
require '../../protected/core/components/security/config_session.inc.php';
require '../../protected/core/components/security/router.inc.php';
require '../../protected/core/components/security/user_check.php';
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Search Voucher');
?>
<link rel="stylesheet" href="../styles/css/gen_report.css">
<!-- <script src="../protected/js/set_print_time.js"></script> -->
<style>
    @media print {
        body * {
            visibility: hidden;
            margin: 0;
            /* Set margin of the entire page to zero */
            padding: 0;
        }

        #generated_routing_slip {
            display: block;
        }

        .sidebar {
            display: none;
        }

        .main {
            width: 100%;
        }

        #generated_routing_slip {
            display: block;
        }

        #generated_routing_slip,
        #generated_routing_slip * {
            visibility: visible;
        }

        #generated_routing_slip {
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

        #generated_routing_slip th,
        #generated_routing_slip td {
            font-size: 9px;
        }

        @page {
            size: portrait;
            /* auto is the default value, it will fit the content to the available space */
            margin-top: 0;
        }

        #generated_routing_slip table {
            width: 100%;
            /* Set table width to 100% */
            border-collapse: collapse;
            /* Collapse table borders */
        }
    }
</style>
<style>
    /* Excel-like styling */
    table {
        border-collapse: collapse;
        width: 100%;
    }

    .full-bordered-table th,
    full-bordered-table td,
    .full-bordered-data td {
        border: 1px solid #000000;
        text-align: left;
        padding: 8px;
    }

    .no-bordered_data_info th,
    .no-bordered_data_info td {
        border: none;
        text-align: left;
        padding: 8px;
    }

    th {
        background-color: #f2f2f2;
    }

    .generated_routing__table {

        .db-voucher__table th,
        .db-voucher__table td {
            border: 1px solid #000000;
        }

        .text-centered {
            text-align: center;
        }

        tr .fixed-size {
            width: 5px;
        }

        th.fixed-size-top {
            width: 10px;
        }

        .db-voucher__table .db-voucher__header tr th {
            text-align: center;
            line-height: 2rem;
        }

        th.fixed-title,
        td.fixed-title {
            width: 20px;
        }

        td.fixed-ratio {
            width: 150px;
        }

        img.fixed-img-ratio {
            width: 100px;
            height: 100px;
            float: left;
            margin-top: 20px;
            margin-left: 20px;
        }

        h3.green-text {
            color: limegreen;
        }

        .fixed-margin {
            margin-left: 110px;
        }

        td.signature-fixed {
            height: 60px;
        }

        td.no-line {
            border-top: none;
            border-bottom: none;
            text-align: left;
        }

        .fixed-height_A {
            height: 150px;
        }

        .fixed-height_B {
            height: 60px;
        }

        .fixed-height_C {
            height: 50px;
        }

        .fixed_text_alignment th {
            text-align: left;
        }
    }
</style>
<?php

if (isset($_GET['query'])) {
    $search_query = $_GET['query'];
    $query33 = "SELECT * FROM voucher_action_logs WHERE processing_no = :search_query";
    $statement33 = $pdo->prepare($query33);
    $statement33->bindParam(":search_query", $search_query, PDO::PARAM_STR);
    $statement33->execute();

    $row = $statement33->fetch(PDO::FETCH_ASSOC)
?>
    <?php if ($statement33->rowCount() > 0) : ?>
        <div class="generated_routing__table" id="generated_routing_slip" style="margin-top: 30px;">
            <div class="table_container1">
                <table class="routing__table" id="my-Table">
                    <thead class="routing__header">
                        <tr>
                            <th colspan="5">
                                <img class="fixed-img-ratio" src="../assets/img/DENR-test.png" alt="">
                                <h4>Republic of the Philippines</h4>
                                <h4>Department of Environment and Natural Resources</h4>
                                <h3 class="green-text">PROVINCIAL ENVIRONMENT AND NATURAL RESOURCES OFFICE</h3>
                                <p>Borongan City, Eastern Samar</p>
                                <h1 class="fixed-margin">DISBURSEMENT VOUCHER MONITORING SYSTEM</h1>
                            </th>
                            <th>
                                <img style="float: right" class="" src="../assets/img/qr.png" alt="">
                            </th>
                        </tr>
                    </thead>
                </table>
                <table class="routing_slip_info" id="my-Table" style="margin-top: 40px;">
                    <thead class="no-bordered_data_info">
                        <tr>
                            <th style="font-size: 13px" class="fixed-gen-title">Processing No.:</th>
                            <td style="font-size: 13px" colspan="5" id="processing_no"><?php echo $row['processing_no'] ?></td>
                        </tr>
                        <tr>
                            <th style="font-size: 13px" class="fixed-gen-title">Payee:</th>
                            <td style="font-size: 13px" colspan="5" id="payee"><?php echo $row['payee'] ?></td>
                        </tr>
                        <tr>
                            <th style="font-size: 13px">Amount:</th>
                            <td style="font-size: 13px" colspan="5" id="amount" class="amount2"><?php echo $formattedAmount = number_format($row['amount'], 2, '.', ','); ?></td>
                        </tr>

                        <tr>
                            <th style="font-size: 13px">Particulars:</th>
                            <td style="font-size: 13px" colspan="5" id="particulars"><?php echo $row['particulars'] ?></td>
                        </tr>
                        <tr>
                            <th style="font-size: 13px">Date/Time Printed:</th>
                            <td style="font-size: 13px" colspan="5" id="print_time"></td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="full-bordered-table">
                            <th colspan="6">DOCUMENT TRACKING INFORMATION</th>
                        </tr>
                        <tr class="full-bordered-table">
                            <th class="fixed-size">FROM</th>
                            <th class="fixed-size">USER</th>
                            <th class="fixed-size">ACTION</th>
                            <th class="fixed-size">REMARKS</th>
                            <th colspan="2">DATE AND TIME OF ACTION</th>
                        </tr>

                        <?php
                        if (isset($_GET['query'])) {
                            $search_query = $_GET['query'];
                            $query33 = "SELECT * FROM voucher_action_logs WHERE processing_no = :search_query";
                            $statement33 = $pdo->prepare($query33);
                            $statement33->bindParam(":search_query", $search_query, PDO::PARAM_STR);
                            $statement33->execute();

                            while ($row = $statement33->fetch(PDO::FETCH_ASSOC)) {
                                $datetime = new DateTime($row['datetime_action']);
                                $date_component = $datetime->format('Y-m-d');
                                $time_component = $datetime->format('H:i:s');
                        ?>
                                <tr class="full-bordered-data">
                                    <td style="text-align: center;"><?php echo $row['action_from'] ?></td>
                                    <td style="width: 150px"><?php echo $row['action_by'] ?></td>
                                    <td style="width: 150px"><?php echo $row['action'] ?></td>
                                    <td style="width: 300px;"><?php echo $row['remarks'] ?></td>
                                    <td><?php echo $date_component ?></td>
                                    <td><?php echo $time_component ?></td>
                                </tr>
                        <?php
                            }
                        }
                        unset($_GET['query']);
                        ?>
                    </tbody>
                </table>
            </div>
        <?php else:
        echo  "<span style='top: 50%; position: absolute; font-size: 18px; font-weight: 700 ;left: 40%'>NO DOCUMENT FOUND</span>";
    endif; ?>
    <?php
}
    ?>
    <?php
    $x = explode(" ", $_SESSION['logged_user_emp_name']);
    $firstName = $x[0];
    $lastName = end($x);
    $lastInitial = substr($lastName, 0, 1) . ".";
    ?>
    <div class="table_container2" style="margin-top: 20px; text-align: left">
        <div>
            <span style="color: #000000; font-size: 14px;">Printed By: <?php echo $x[0] . " " . $lastInitial ?></span>
        </div>
        <?php
        $query34 = "SELECT * FROM voucher_tracking WHERE processing_no = :search_query";
        $statement34 = $pdo->prepare($query34);
        $statement34->bindParam(":search_query", $search_query, PDO::PARAM_STR);
        $statement34->execute();
        ?>
        <?php while ($row = $statement34->fetch(PDO::FETCH_ASSOC)) {
        ?>
            <?php if ($row['total_processing_time'] != "TBD") : ?>
                <div>
                    <span style="color: #000000; font-size: 14px;">Total Processing Time: <?php echo $row['total_processing_time'] ?></span>
                </div>
            <?php endif; ?>
        <?php
        }
        ?>
    </div>
        </div>