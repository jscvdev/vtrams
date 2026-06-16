<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_special_access_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/sort_order_helper.inc.php';
require_once __DIR__ . '/../vouchers/checklist_config.php';
AuditHelper::logPageView('Special Access');

$target = explode(',', $_SESSION['logged_user_designation'] ?? '');
$can_view = in_array('System Admin', $target);
if (!$can_view) {
    echo "<script>process_functionAlert('Access denied!', 'dashboard_redirect')</script>";
    die();
}

utilities_special_access_ensure_schema($pdo);

$voucher_types = checklist_types_with_labels();
$destinations = utilities_special_access_forward_destinations($pdo);

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk = isset($_POST['token'], $_SESSION['token']) && hash_equals((string) $_SESSION['token'], (string) $_POST['token']);
    if (!$tokenOk) {
        $flash = ['type' => 'error', 'msg' => 'Invalid token. Please refresh and try again.'];
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'rule_add') {
                $voucherType = utilities_special_access_normalize_value((string) ($_POST['voucher_type'] ?? ''));
                $forwardDesignation = utilities_special_access_normalize_value((string) ($_POST['forward_designation'] ?? ''));
                $sort = (int) ($_POST['sort_order'] ?? 0);
                if ($voucherType === '') {
                    $flash = ['type' => 'error', 'msg' => 'Voucher type is required.'];
                } elseif ($forwardDesignation === '' || !utilities_special_access_destination_is_allowed($pdo, $forwardDesignation)) {
                    $flash = ['type' => 'error', 'msg' => 'Please select a valid forward destination.'];
                } elseif (!isset($voucher_types[$voucherType])) {
                    $flash = ['type' => 'error', 'msg' => 'Unknown voucher type. Add it under Checklist first.'];
                } else {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("
                        INSERT INTO voucher_special_access (voucher_type, forward_designation, sort_order, is_active)
                        VALUES (:voucher_type, :forward_designation, 0, 1)
                    ");
                    $stmt->execute([
                        ':voucher_type' => $voucherType,
                        ':forward_designation' => $forwardDesignation,
                    ]);
                    sort_order_place_at_position($pdo, 'voucher_special_access', (int) $pdo->lastInsertId(), $sort);
                    $pdo->commit();
                    utilities_special_access_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Special access rule added.'];
                }
            } elseif ($action === 'rule_update') {
                $id = (int) ($_POST['id'] ?? 0);
                $voucherType = utilities_special_access_normalize_value((string) ($_POST['voucher_type'] ?? ''));
                $forwardDesignation = utilities_special_access_normalize_value((string) ($_POST['forward_designation'] ?? ''));
                $sort = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($id <= 0 || $voucherType === '') {
                    $flash = ['type' => 'error', 'msg' => 'Invalid update payload.'];
                } elseif ($forwardDesignation === '' || !utilities_special_access_destination_is_allowed($pdo, $forwardDesignation)) {
                    $flash = ['type' => 'error', 'msg' => 'Please select a valid forward destination.'];
                } elseif (!isset($voucher_types[$voucherType])) {
                    $flash = ['type' => 'error', 'msg' => 'Unknown voucher type. Add it under Checklist first.'];
                } else {
                    $pdo->beginTransaction();
                    sort_order_handle_update($pdo, 'voucher_special_access', $id, $sort);
                    $stmt = $pdo->prepare("
                        UPDATE voucher_special_access
                        SET voucher_type = :voucher_type,
                            forward_designation = :forward_designation,
                            sort_order = :sort,
                            is_active = :active
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':voucher_type' => $voucherType,
                        ':forward_designation' => $forwardDesignation,
                        ':sort' => $sort,
                        ':active' => $active,
                        ':id' => $id,
                    ]);
                    $pdo->commit();
                    utilities_special_access_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Special access rule updated.'];
                }
            } elseif ($action === 'rule_delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                } else {
                    $stmt = $pdo->prepare('DELETE FROM voucher_special_access WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    utilities_special_access_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Special access rule deleted.'];
                }
            } else {
                $flash = ['type' => 'error', 'msg' => 'Unknown action.'];
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                $flash = ['type' => 'warning', 'msg' => 'That voucher type already has a special access rule.'];
            } else {
                $flash = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
            }
        }
    }
}

$rules = utilities_special_access_fetch_all($pdo);
$rule_count = count($rules);
?>

<style>
    .sa-page {
        --util-bg: #f8fafc;
        --util-card: #fff;
        --util-border: #e2e8f0;
        --util-text: #0f172a;
        --util-muted: #64748b;
        --util-accent: #4f46e5;
        --util-radius: 14px;
        --util-shadow: 0 1px 3px rgba(15, 23, 42, .06);
    }

    .sa-page .util-alert {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 14px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 14px;
    }

    .sa-page .util-alert.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .sa-page .util-alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .sa-page .util-alert.warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

    .sa-page .util-section-title {
        margin: 0 0 8px;
        font-size: 15px;
        font-weight: 600;
        color: var(--util-text);
    }

    .sa-page .util-dv-desc {
        margin: 0 0 16px;
        color: var(--util-muted);
        font-size: 13px;
        line-height: 1.5;
    }

    .sa-page .util-card {
        background: var(--util-card);
        border: 1px solid var(--util-border);
        border-radius: var(--util-radius);
        box-shadow: var(--util-shadow);
        margin-bottom: 18px;
    }

    .sa-page .util-card__head {
        padding: 14px 16px;
        border-bottom: 1px solid var(--util-border);
    }

    .sa-page .util-card__head h3 {
        margin: 0;
        font-size: 15px;
        color: var(--util-text);
    }

    .sa-page .util-card__body { padding: 16px; }

    .sa-page .util-add {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }

    .sa-page .util-add .field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 180px;
    }

    .sa-page .util-add label {
        font-size: 13px;
        color: var(--util-muted);
    }

    .sa-page .util-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .sa-page .util-table th,
    .sa-page .util-table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--util-border);
        text-align: left;
        vertical-align: middle;
    }

    .sa-page .util-table th {
        background: #f8fafc;
        color: var(--util-muted);
        font-weight: 600;
    }

    .sa-page .util-inline {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    .sa-page .util-row-actions { flex: 0 0 auto; }

    .sa-page .chk {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        white-space: nowrap;
    }

    .sa-page .util-empty {
        color: var(--util-muted);
        font-size: 13px;
        margin: 0;
    }
</style>

<div class="main main--dashboard sa-page" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Special Access</h1>
    </header>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">
            <span class="voucher-card-title__label">
                <i class="ri-route-line ri-icon"></i>
                Direct Forward Routing
            </span>
        </h2>
        <div class="content-wrapper">
            <?php if ($flash): ?>
                <div class="util-alert <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <p class="util-section-title">Encoder forward overrides</p>
            <p class="util-dv-desc">
                Configure voucher types that should skip the standard workflow when forwarded from the Voucher page
                (e.g. e-NGP types sent directly to Accounting Unit or another unit). Each voucher type can have one active rule.
            </p>

            <div class="util-card">
                <div class="util-card__head"><h3>Add rule</h3></div>
                <div class="util-card__body">
                    <form method="post" class="util-add">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="rule_add">
                        <div class="field">
                            <label for="add_voucher_type">Voucher type</label>
                            <select class="form-custom-input" name="voucher_type" id="add_voucher_type" required>
                                <option value="" disabled selected>Select type</option>
                                <?php foreach ($voucher_types as $type_value => $type_label): ?>
                                    <option value="<?= htmlspecialchars((string) $type_value, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $type_label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="add_forward_designation">Forward to</label>
                            <select class="form-custom-input" name="forward_designation" id="add_forward_designation" required>
                                <option value="" disabled selected>Select unit</option>
                                <?php foreach ($destinations as $destination): ?>
                                    <option value="<?= htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field" style="max-width:110px;">
                            <label for="add_sort_order">Sort</label>
                            <input class="form-custom-input" type="number" name="sort_order" id="add_sort_order" value="0">
                        </div>
                        <div class="field">
                            <label class="util-field-spacer" aria-hidden="true">&nbsp;</label>
                            <button class="btn primary" type="submit">Add rule</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="util-card">
                <div class="util-card__head"><h3>Configured rules (<?= (int) $rule_count ?>)</h3></div>
                <div class="util-card__body">
                    <?php if (!$rules): ?>
                        <p class="util-empty">No special access rules yet.</p>
                    <?php else: ?>
                        <table class="util-table">
                            <thead>
                                <tr>
                                    <th>Voucher type</th>
                                    <th>Forward to</th>
                                    <th>Sort</th>
                                    <th>Active</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rules as $rule):
                                    $ruleId = (int) ($rule['id'] ?? 0);
                                    $storedType = (string) ($rule['voucher_type'] ?? '');
                                ?>
                                    <tr>
                                        <td>
                                            <form method="post" id="sa-update-<?= $ruleId ?>">
                                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="rule_update">
                                                <input type="hidden" name="id" value="<?= $ruleId ?>">
                                                <select class="form-custom-input" name="voucher_type" form="sa-update-<?= $ruleId ?>" required>
                                                    <?php foreach ($voucher_types as $type_value => $label): ?>
                                                        <option value="<?= htmlspecialchars((string) $type_value, ENT_QUOTES, 'UTF-8') ?>"<?= $storedType === (string) $type_value ? ' selected' : '' ?>>
                                                            <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <select class="form-custom-input" name="forward_designation" form="sa-update-<?= $ruleId ?>" required>
                                                <?php foreach ($destinations as $destination): ?>
                                                    <option value="<?= htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') ?>"<?= ($rule['forward_designation'] ?? '') === $destination ? ' selected' : '' ?>>
                                                        <?= htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input class="form-custom-input" type="number" name="sort_order" form="sa-update-<?= $ruleId ?>" value="<?= (int) ($rule['sort_order'] ?? 0) ?>" style="width:80px;">
                                        </td>
                                        <td>
                                            <label class="chk">
                                                <input type="checkbox" name="is_active" form="sa-update-<?= $ruleId ?>" <?= ((int) ($rule['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                                                <span>Active</span>
                                            </label>
                                        </td>
                                        <td class="util-row-actions">
                                            <button class="btn success" type="submit" form="sa-update-<?= $ruleId ?>">Save</button>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this special access rule?');">
                                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="rule_delete">
                                                <input type="hidden" name="id" value="<?= $ruleId ?>">
                                                <button class="btn danger" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../protected/js/main.js"></script>
</body>

</html>