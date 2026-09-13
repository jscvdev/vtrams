<?php
include '../includes/header.php';
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/handler/voucher_return_module/voucher_return.model.inc.php';
require_once __DIR__ . '/../../protected/core/components/redirects/redirect_config.inc.php';

if (!AccessControl::canAccessRetractApprovals()) {
    echo '<script>window.location.href="' . htmlspecialchars(get_redirect_url('voucher'), ENT_QUOTES, 'UTF-8') . '";</script>';
    echo '<p>Redirecting...</p>';
    exit;
}

AuditHelper::logPageView('Retract Approvals');
voucher_retract_ensure_requests_schema($pdo);

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'pending')));
if (!in_array($statusFilter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $statusFilter = 'pending';
}

$rawSearch = (string) ($_GET['q'] ?? '');
$searchTerm = strtolower(trim($rawSearch));

$requests = voucher_retract_fetch_requests($pdo, $statusFilter);
if ($searchTerm !== '') {
    $requests = array_values(array_filter($requests, static function (array $row) use ($searchTerm): bool {
        $haystack = strtolower(implode(' ', [
            (string) ($row['processing_no'] ?? ''),
            (string) ($row['requested_by'] ?? ''),
            (string) ($row['requested_from'] ?? ''),
            (string) ($row['office_from'] ?? ''),
            (string) ($row['remarks'] ?? ''),
            (string) ($row['status'] ?? ''),
        ]));

        return str_contains($haystack, $searchTerm);
    }));
}

$pendingCount = voucher_retract_count_pending_requests($pdo);
$handlerUrl = '../../protected/handler/voucher_return_module/voucher_retract_approval_handler.php';
?>
<style>
    #retractApprovalsTable .retract-approval-actions {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
        min-width: 220px;
        max-width: 280px;
    }
    #retractApprovalsTable .retract-approval-actions textarea {
        width: 100%;
        min-height: 58px;
        box-sizing: border-box;
        font-size: 13px;
        font-weight: 400;
        padding: 8px 10px;
        border: 1px solid rgb(209 213 219 / 1);
        border-radius: 6px;
        resize: vertical;
    }
    #retractApprovalsTable .retract-approval-actions__btns {
        display: flex;
        flex-wrap: nowrap;
        gap: 8px;
    }
    #retractApprovalsTable .retract-approval-actions .btn {
        width: auto;
        min-width: 0;
        height: 36px;
        padding: 0 12px;
        box-shadow: none;
        font-size: 13px;
        font-weight: 600;
        gap: 6px;
        flex: 1 1 auto;
    }
    #retractApprovalsTable .retract-approval-actions .btn i {
        font-size: 16px;
        line-height: 1;
    }
    .retract-status-pill {
        display: inline-block;
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 12px;
        font-weight: 600;
        text-transform: capitalize;
    }
    .retract-status-pill--pending { background: #fef3c7; color: #92400e; }
    .retract-status-pill--approved { background: #dcfce7; color: #166534; }
    .retract-status-pill--rejected { background: #fee2e2; color: #991b1b; }
</style>
<div class="main main--voucher-dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Retract Approvals</h1>
        <p style="color: rgb(75 85 99 / 0.9); margin: 0.25rem 0 0;">
            Vouchers already acted on by a processing-office unit (Planning, Budget, Accounting, Cashiers, and similar) cannot be retracted immediately. Approve or reject those requests here.
        </p>
    </header>

    <div class="voucher-card status-report-stats-card" style="margin-bottom: 16px;">
        <div class="status-report-stats">
            <div class="status-report-stat">
                <span class="status-report-stat__label">Pending requests</span>
                <strong class="status-report-stat__value"><?php echo (int) $pendingCount; ?></strong>
            </div>
            <div class="status-report-stat">
                <span class="status-report-stat__label">Rows shown</span>
                <strong class="status-report-stat__value"><?php echo count($requests); ?></strong>
            </div>
        </div>
    </div>

    <section class="status-report-filter-bar">
        <form method="GET" action="" class="status-report-filter-bar__form">
            <div class="status-report-filter-bar__field">
                <label for="statusFilter">Status</label>
                <select id="statusFilter" name="status">
                    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label) : ?>
                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $statusFilter === $value ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="status-report-filter-bar__field status-report-filter-bar__field--grow">
                <label for="retractSearch">Search</label>
                <input type="text" id="retractSearch" name="q" value="<?php echo htmlspecialchars($rawSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="search" autocomplete="off">
            </div>
            <button type="submit" class="status-report-filter-bar__apply">Apply Filters</button>
        </form>
    </section>

    <div class="voucher-card voucher-card--table status-report-table-card">
        <div class="status-report-table-head">
            <h2 class="voucher-card-title" style="margin:0;">Retract Requests</h2>
        </div>
        <style><?php include __DIR__ . '/status_report_styles.inc.php'; ?></style>
        <div class="content-wrapper status-report-table-wrap">
            <table class="table content_table content_table--dashboard" id="retractApprovalsTable">
                <thead>
                    <tr>
                        <th>Processing No.</th>
                        <th>Requested By</th>
                        <th>Section</th>
                        <th>Office</th>
                        <th>Remarks</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests === []) : ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color:#6b7280; padding:28px 12px;">No retract requests for this filter.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($requests as $row) :
                            $status = strtolower((string) ($row['status'] ?? 'pending'));
                            $isPending = $status === 'pending';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($row['processing_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['requested_by'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['requested_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['office_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo nl2br(htmlspecialchars((string) ($row['remarks'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['datetime_requested'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="retract-status-pill retract-status-pill--<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <?php if (!$isPending && trim((string) ($row['reviewed_by'] ?? '')) !== '') : ?>
                                        <div style="font-size:12px;color:#6b7280;margin-top:4px;">
                                            <?php echo htmlspecialchars((string) $row['reviewed_by'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if (trim((string) ($row['datetime_reviewed'] ?? '')) !== '') : ?>
                                                · <?php echo htmlspecialchars((string) $row['datetime_reviewed'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isPending) : ?>
                                        <form method="post" action="<?php echo htmlspecialchars($handlerUrl, ENT_QUOTES, 'UTF-8'); ?>" class="retract-approval-actions">
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars((string) ($_SESSION['token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                            <textarea name="review_remarks" placeholder="Review remarks (optional)"></textarea>
                                            <div class="retract-approval-actions__btns">
                                                <button type="submit" name="retract_decision" value="approve" class="btn success btn-flex btn-nowrap" title="Approve">
                                                    <i class="ri-check-line" aria-hidden="true"></i><span>Approve</span>
                                                </button>
                                                <button type="submit" name="retract_decision" value="reject" class="btn danger btn-flex btn-nowrap" title="Reject">
                                                    <i class="ri-close-line" aria-hidden="true"></i><span>Reject</span>
                                                </button>
                                            </div>
                                        </form>
                                    <?php else : ?>
                                        <?php echo htmlspecialchars(trim((string) ($row['review_remarks'] ?? '')) !== '' ? (string) $row['review_remarks'] : '—', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
