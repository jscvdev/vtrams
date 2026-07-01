<?php
include '../includes/header.php';
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/amount_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_liaison_returned_helper.inc.php';
require_once __DIR__ . '/checklist_config.php';

if (!AccessControl::canAccessLiaisonReturnedVouchers()) {
    header('Location: ../documents/index.php');
    die();
}

AuditHelper::logPageView('Returned Vouchers');

utilities_signatory_ensure_schema($pdo);
$loggedOffice = trim((string) ($_SESSION['logged_user_office'] ?? ''));
$isSysAdmin = AccessControl::hasRole('System Admin');
$scopeOffices = voucher_liaison_returned_scope_offices($pdo, $loggedOffice, $isSysAdmin);

$rawOffice = trim((string) ($_GET['office'] ?? 'all'));
$officeFilter = ($rawOffice === '' || strcasecmp($rawOffice, 'all') === 0) ? null : utilities_signatory_resolve_office($pdo, $rawOffice);

$entries = voucher_liaison_returned_fetch_entries($pdo, $scopeOffices, $officeFilter);
$summary = voucher_liaison_returned_summarize($entries);

$rawSearch = (string) ($_GET['q'] ?? '');
$searchTerm = strtolower(trim($rawSearch));
if ($searchTerm !== '') {
    $entries = array_values(array_filter($entries, static function (array $entry) use ($searchTerm): bool {
        $haystack = strtolower(implode(' ', [
            (string) ($entry['processing_no'] ?? ''),
            (string) ($entry['payee'] ?? ''),
            (string) ($entry['dv_no'] ?? ''),
            (string) ($entry['origin_office'] ?? ''),
            (string) ($entry['office_from'] ?? ''),
            (string) ($entry['returned_by'] ?? ''),
            (string) ($entry['voucher_type'] ?? ''),
        ]));

        return str_contains($haystack, $searchTerm);
    }));
    $summary = voucher_liaison_returned_summarize($entries);
}

$selectableOffices = $isSysAdmin ? $scopeOffices : voucher_liaison_returned_scope_offices($pdo, $loggedOffice, false);
sort($selectableOffices);
$selectedOfficeValue = $officeFilter ?? 'all';
$printOfficeLabel = $selectedOfficeValue === 'all'
    ? ($isSysAdmin ? 'All Liaison Offices' : $loggedOffice)
    : (string) $selectedOfficeValue;
$printGeneratedAt = date('Y-m-d H:i:s');
$pageTitleHelperName = $header_text ?? 'Returned Vouchers';
?>
<div class="main main--voucher-dashboard" id="main">
    <header class="voucher-dashboard-header no-print">
        <h1 class="voucher-dashboard-title">Returned Vouchers</h1>
        <p style="color: rgb(75 85 99 / 0.9); margin: 0.25rem 0 0;">
            View-only list of vouchers still at returned status within your liaison office and sub-offices (excludes paid and vouchers already re-received by the returner).
        </p>
    </header>

    <div class="status-report-print-header print-only">
        <div class="status-report-print-banner">
            <div>
                <p class="status-report-print-banner__eyebrow">Voucher Tracking</p>
                <h1><?php echo htmlspecialchars((string) $pageTitleHelperName, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="status-report-print-banner__subtitle">Liaison Returned Vouchers Report</p>
            </div>
            <div class="status-report-print-banner__meta">
                <div><span>Office</span><strong><?php echo htmlspecialchars($printOfficeLabel, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div><span>Generated</span><strong><?php echo htmlspecialchars($printGeneratedAt, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div><span>Rows</span><strong><?php echo count($entries); ?></strong></div>
            </div>
        </div>
        <?php if ($rawSearch !== '') : ?>
            <p class="status-report-print-search">Search filter: <?php echo htmlspecialchars($rawSearch, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <div class="status-report-print-summary">
            <div class="status-report-print-summary__item"><span>Returned</span><strong><?php echo (int) $summary['total']; ?></strong></div>
            <div class="status-report-print-summary__item status-report-print-summary__item--returned"><span>Total Amount</span><strong><?php echo htmlspecialchars((string) $summary['total_amount'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
        </div>
    </div>

    <div class="voucher-card status-report-stats-card no-print" style="margin-bottom: 16px;">
        <div class="status-report-stats">
            <div class="status-report-stat status-report-stat--returned">
                <span class="status-report-stat__label">Returned vouchers</span>
                <strong class="status-report-stat__value"><?php echo (int) $summary['total']; ?></strong>
            </div>
            <div class="status-report-stat">
                <span class="status-report-stat__label">Total amount</span>
                <strong class="status-report-stat__value"><?php echo htmlspecialchars((string) $summary['total_amount'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </div>
    </div>

    <section class="status-report-filter-bar no-print">
        <form method="GET" action="" class="status-report-filter-bar__form">
            <?php if (count($selectableOffices) > 1) : ?>
                <div class="status-report-filter-bar__field">
                    <label for="officeFilter">Office</label>
                    <select id="officeFilter" name="office">
                        <option value="all"<?php echo $selectedOfficeValue === 'all' ? ' selected' : ''; ?>>All Offices</option>
                        <?php foreach ($selectableOffices as $office_name) : ?>
                            <option value="<?php echo htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedOfficeValue !== 'all' && utilities_signatory_offices_match((string) $selectedOfficeValue, (string) $office_name) ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="status-report-filter-bar__field status-report-filter-bar__field--grow">
                <label for="returnedSearch">Search</label>
                <input type="text" id="returnedSearch" name="q" value="<?php echo htmlspecialchars($rawSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Processing no., payee, office, returned by…" autocomplete="off">
            </div>
            <button type="submit" class="status-report-filter-bar__apply">Apply Filters</button>
            <button type="button" class="status-report-filter-bar__print" id="returnedVouchersPrintBtn">Print Report</button>
        </form>
    </section>

    <div class="voucher-card voucher-card--table status-report-table-card">
        <div class="status-report-table-head no-print">
            <h2 class="voucher-card-title" style="margin:0;">Returned Vouchers</h2>
            <p class="status-report-table-meta"><?php echo count($entries); ?> voucher<?php echo count($entries) === 1 ? '' : 's'; ?> shown</p>
        </div>
        <style><?php include __DIR__ . '/status_report_styles.inc.php'; ?></style>
        <div class="content-wrapper status-report-table-wrap">
            <h3 class="status-report-print-table-title print-only">Returned Voucher Listing</h3>
            <table class="table content_table content_table--dashboard" id="returnedVouchersTable">
                <thead>
                    <tr>
                        <th>Processing No.</th>
                        <th>Payee</th>
                        <th>Amount</th>
                        <th>Origin Office</th>
                        <th>Returned By</th>
                        <th>Last Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($entries === []) : ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:#6b7280; padding:28px 12px;">No returned vouchers in your liaison office scope.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($entries as $entry) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $entry['processing_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $entry['payee'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(format_amount_display((string) $entry['amount']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($entry['origin_office'] ?: $entry['office_from'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($entry['returned_by'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($entry['datetime_status'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p class="status-report-print-footer print-only">End of report · <?php echo count($entries); ?> record<?php echo count($entries) === 1 ? '' : 's'; ?> · <?php echo htmlspecialchars($printGeneratedAt, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
</div>

<script>
    (function() {
        const printBtn = document.getElementById('returnedVouchersPrintBtn');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                window.print();
            });
        }
    })();
</script>
<?php require_once __DIR__ . '/../../protected/core/components/notifications/notification_flash.inc.php'; ?>
</body>
</html>
