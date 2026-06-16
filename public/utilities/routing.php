<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_special_access_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_office_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/sort_order_helper.inc.php';
require_once __DIR__ . '/../vouchers/checklist_config.php';
AuditHelper::logPageView('Routing');

$target = explode(',', $_SESSION['logged_user_designation'] ?? '');
$can_view = in_array('System Admin', $target);
if (!$can_view) {
    echo "<script>process_functionAlert('Access denied!', 'dashboard_redirect')</script>";
    die();
}

utilities_special_access_ensure_schema($pdo);
utilities_office_ensure_schema($pdo);

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
                    $flash = ['type' => 'success', 'msg' => 'Routing rule added.'];
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
                    $flash = ['type' => 'success', 'msg' => 'Routing rule updated.'];
                }
            } elseif ($action === 'rule_delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                } else {
                    $stmt = $pdo->prepare('DELETE FROM voucher_special_access WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    utilities_special_access_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Routing rule deleted.'];
                }
            } elseif ($action === 'office_add') {
                $officeName = utilities_office_normalize_name((string) ($_POST['office_name'] ?? ''));
                $parentId = (int) ($_POST['parent_office_id'] ?? 0);
                $parentId = $parentId > 0 ? $parentId : null;
                $isProcessing = isset($_POST['is_processing_office']) ? 1 : 0;
                $sort = (int) ($_POST['sort_order'] ?? 0);
                if ($officeName === '') {
                    $flash = ['type' => 'error', 'msg' => 'Office name is required.'];
                } elseif (utilities_office_find_by_name($pdo, $officeName) !== null) {
                    $flash = ['type' => 'warning', 'msg' => 'That office already exists.'];
                } elseif ($parentId !== null && utilities_office_find_by_id($pdo, $parentId) === null) {
                    $flash = ['type' => 'error', 'msg' => 'Selected parent office was not found.'];
                } else {
                    $pdo->beginTransaction();
                    if ($isProcessing === 1) {
                        utilities_office_clear_processing_flag($pdo);
                    }
                    $stmt = $pdo->prepare("
                        INSERT INTO system_offices (office_name, parent_office_id, is_processing_office, sort_order, is_active)
                        VALUES (:name, :parent_id, :processing, 0, 1)
                    ");
                    $stmt->execute([
                        ':name' => $officeName,
                        ':parent_id' => $parentId,
                        ':processing' => $isProcessing,
                    ]);
                    sort_order_place_at_position($pdo, 'system_offices', (int) $pdo->lastInsertId(), $sort);
                    $pdo->commit();
                    if ($parentId !== null && $isProcessing !== 1) {
                        utilities_office_register_for_liaison($pdo, $officeName);
                    }
                    utilities_office_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Office added. Assign Liaison Officer users to sub-offices in Devtool.'];
                }
            } elseif ($action === 'office_update') {
                $id = (int) ($_POST['id'] ?? 0);
                $officeName = utilities_office_normalize_name((string) ($_POST['office_name'] ?? ''));
                $parentId = (int) ($_POST['parent_office_id'] ?? 0);
                $parentId = $parentId > 0 ? $parentId : null;
                $isProcessing = isset($_POST['is_processing_office']) ? 1 : 0;
                $sort = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                $existing = utilities_office_find_by_id($pdo, $id);
                if ($id <= 0 || $officeName === '' || $existing === null) {
                    $flash = ['type' => 'error', 'msg' => 'Invalid office update payload.'];
                } elseif ($parentId === $id) {
                    $flash = ['type' => 'error', 'msg' => 'An office cannot be its own parent.'];
                } elseif ($parentId !== null && in_array($parentId, utilities_office_descendant_ids($pdo, $id), true)) {
                    $flash = ['type' => 'error', 'msg' => 'Parent office cannot be a child of this office.'];
                } elseif ($parentId !== null && utilities_office_find_by_id($pdo, $parentId) === null) {
                    $flash = ['type' => 'error', 'msg' => 'Selected parent office was not found.'];
                } else {
                    $duplicate = utilities_office_find_by_name($pdo, $officeName);
                    if ($duplicate !== null && (int) ($duplicate['id'] ?? 0) !== $id) {
                        $flash = ['type' => 'warning', 'msg' => 'Another office already uses that name.'];
                    } else {
                        $pdo->beginTransaction();
                        if ($isProcessing === 1) {
                            utilities_office_clear_processing_flag($pdo, $id);
                        }
                        sort_order_handle_update($pdo, 'system_offices', $id, $sort);
                        $stmt = $pdo->prepare("
                            UPDATE system_offices
                            SET office_name = :name,
                                parent_office_id = :parent_id,
                                is_processing_office = :processing,
                                sort_order = :sort,
                                is_active = :active
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            ':name' => $officeName,
                            ':parent_id' => $parentId,
                            ':processing' => $isProcessing,
                            ':sort' => $sort,
                            ':active' => $active,
                            ':id' => $id,
                        ]);
                        $pdo->commit();
                        if ($parentId !== null && $isProcessing !== 1) {
                            utilities_office_register_for_liaison($pdo, $officeName);
                        }
                        utilities_office_invalidate_cache();
                        $flash = ['type' => 'success', 'msg' => 'Office updated.'];
                    }
                }
            } elseif ($action === 'office_delete') {
                $id = (int) ($_POST['id'] ?? 0);
                $existing = utilities_office_find_by_id($pdo, $id);
                if ($id <= 0 || $existing === null) {
                    $flash = ['type' => 'error', 'msg' => 'Invalid office delete payload.'];
                } elseif (utilities_office_descendant_ids($pdo, $id) !== []) {
                    $flash = ['type' => 'error', 'msg' => 'Remove or reassign child offices before deleting this office.'];
                } else {
                    $stmt = $pdo->prepare('DELETE FROM system_offices WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    utilities_office_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Office removed from registry. Existing user accounts are unchanged.'];
                }
            } else {
                $flash = ['type' => 'error', 'msg' => 'Unknown action.'];
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                $flash = ['type' => 'warning', 'msg' => 'That voucher type already has a routing rule.'];
            } else {
                $flash = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
            }
        }
    }
}

$rules = utilities_special_access_fetch_all($pdo);
$rule_count = count($rules);
$active_count = 0;
foreach ($rules as $rule) {
    if ((int) ($rule['is_active'] ?? 1) === 1) {
        $active_count++;
    }
}

$offices = utilities_office_fetch_all($pdo);
$office_tree = utilities_office_build_tree($offices);
$office_count = count($offices);
$processing_office = utilities_office_get_processing($pdo);
$processing_office_name = (string) ($processing_office['office_name'] ?? '');

/**
 * @param list<array<string, mixed>> $nodes
 */
function routing_render_office_tree(PDO $pdo, array $nodes, array $allOffices, int $depth = 0): void
{
    foreach ($nodes as $node) {
        $id = (int) ($node['id'] ?? 0);
        $name = (string) ($node['office_name'] ?? '');
        $parentId = (int) ($node['parent_office_id'] ?? 0);
        $isProcessing = (int) ($node['is_processing_office'] ?? 0) === 1;
        $isActive = (int) ($node['is_active'] ?? 1) === 1;
        $sort = (int) ($node['sort_order'] ?? 0);
        $userCount = utilities_office_user_count($pdo, $name);
        $indent = max(0, $depth) * 1.25;
        ?>
        <div class="util-routing-block util-office-node" style="margin-left: <?= $indent ?>rem;">
            <div class="util-routing-block__main">
                <div class="util-office-node__meta">
                    <?php if ($isProcessing): ?>
                        <span class="util-office-badge util-office-badge--processing">Processing office</span>
                    <?php elseif ($parentId > 0): ?>
                        <span class="util-office-badge util-office-badge--sub">Sub-office</span>
                    <?php endif; ?>
                    <?php if (!$isActive): ?>
                        <span class="util-office-badge util-office-badge--inactive">Inactive</span>
                    <?php endif; ?>
                    <span class="util-office-users"><?= (int) $userCount ?> user<?= $userCount === 1 ? '' : 's' ?> in user_group</span>
                </div>
                <div class="util-inline util-inline--edit-row">
                    <form method="post" class="util-inline util-inline--edit-row" style="flex:1; min-width:0;">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="office_update">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input class="form-custom-input" type="text" name="office_name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>
                        <select class="form-custom-input" name="parent_office_id">
                            <option value="">No parent (top level)</option>
                            <?php foreach ($allOffices as $candidate):
                                $candidateId = (int) ($candidate['id'] ?? 0);
                                if ($candidateId === $id) {
                                    continue;
                                }
                            ?>
                                <option value="<?= $candidateId ?>"<?= $parentId === $candidateId ? ' selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($candidate['office_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input class="form-custom-input" type="number" name="sort_order" value="<?= $sort ?>">
                        <label class="chk" title="Main office where vouchers are processed (e.g. PENRO)">
                            <input type="checkbox" name="is_processing_office" <?= $isProcessing ? 'checked' : '' ?>>
                            <span>Processing</span>
                        </label>
                        <label class="chk">
                            <input type="checkbox" name="is_active" <?= $isActive ? 'checked' : '' ?>>
                            <span>Active</span>
                        </label>
                        <button class="btn success" type="submit">Save</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this office from the registry?');" class="util-row-actions">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="office_delete">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button class="btn danger" type="submit">Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        $children = $node['children'] ?? [];
        if (is_array($children) && $children !== []) {
            routing_render_office_tree($pdo, $children, $allOffices, $depth + 1);
        }
    }
}
?>

<style>
    .rt-page {
        --util-bg: #f8fafc;
        --util-card: #fff;
        --util-border: #e2e8f0;
        --util-text: #0f172a;
        --util-muted: #64748b;
        --util-accent: #4f46e5;
        --util-radius: 14px;
        --util-shadow: 0 1px 3px rgba(15, 23, 42, .06);
        --util-shadow-lg: 0 10px 40px -10px rgba(15, 23, 42, .12);
    }

    .rt-page .voucher-dashboard-header { margin-bottom: 1.5rem; }
    .rt-page .voucher-dashboard-title {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--util-text);
    }

    .rt-page .voucher-card {
        background: var(--util-card);
        border: 1px solid var(--util-border);
        border-radius: var(--util-radius);
        box-shadow: var(--util-shadow);
        overflow: hidden;
    }

    .rt-page .voucher-card-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--util-text);
        padding: 1rem 1.25rem;
        margin: 0;
        border-bottom: 1px solid var(--util-border);
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .rt-page .voucher-card-title__label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .rt-page .voucher-card-title .ri-icon {
        font-size: 1.25rem;
        color: var(--util-accent);
    }

    .rt-page .content-wrapper { padding: 1.25rem; }

    .rt-page .util-alert {
        padding: 0.875rem 1rem;
        border-radius: 10px;
        margin-bottom: 1.25rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid transparent;
    }

    .rt-page .util-alert.success { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .rt-page .util-alert.error { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
    .rt-page .util-alert.warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }

    .rt-page .util-section-title {
        font-size: 0.8125rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--util-muted);
        margin: 0 0 1rem 0;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--util-border);
    }

    .rt-page .util-dv-desc {
        color: var(--util-muted);
        font-size: 0.875rem;
        margin: -0.5rem 0 1.25rem 0;
        line-height: 1.5;
    }

    .rt-page .util-card {
        background: var(--util-card);
        border: 1px solid var(--util-border);
        border-radius: var(--util-radius);
        overflow: hidden;
        box-shadow: var(--util-shadow);
    }

    .rt-page .util-card__head {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--util-border);
        background: linear-gradient(180deg, #fafafa 0%, #f1f5f9 100%);
    }

    .rt-page .util-card__head h3 {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--util-text);
    }

    .rt-page .util-card__body { padding: 1.25rem; }

    .rt-page .util-add {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: flex-end;
        margin-bottom: 1rem;
    }

    .rt-page .util-add .field { flex: 1; min-width: 160px; }

    .rt-page .util-add label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--util-muted);
        margin-bottom: 0.375rem;
    }

    .rt-page .util-add .form-custom-input,
    .rt-page .util-inline .form-custom-input {
        border-radius: 8px;
        border: 1px solid var(--util-border);
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        width: 100%;
    }

    .rt-page .util-add .field.util-add-btn-field {
        flex: 0 0 auto;
        min-width: auto;
    }

    .rt-page .util-add .field.util-add-btn-field label.util-field-spacer {
        display: block;
        visibility: hidden;
        height: 0;
        margin: 0 0 0.375rem 0;
        padding: 0;
        overflow: hidden;
        line-height: 0;
    }

    .rt-page .util-add .btn.util-btn-add {
        width: 2.5rem;
        height: 2.5rem;
        min-width: 2.5rem;
        padding: 0;
        border-radius: 8px;
        font-weight: 700;
        font-size: 1.375rem;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .rt-page .util-stats {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .rt-page .util-stat {
        background: #f8fafc;
        border: 1px solid var(--util-border);
        border-radius: 10px;
        padding: 0.625rem 1rem;
        font-size: 0.8125rem;
        color: var(--util-muted);
    }

    .rt-page .util-stat strong { color: var(--util-text); }

    .rt-page .util-routing-block {
        border: 1px solid var(--util-border);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1rem;
        background: #fff;
    }

    .rt-page .util-routing-block__main {
        padding: 0.875rem 1rem;
        background: linear-gradient(180deg, #fafafa 0%, #f8fafc 100%);
    }

    .rt-page .util-inline {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .rt-page .util-inline select.form-custom-input { min-width: 180px; flex: 1; }
    .rt-page .util-inline input[type="number"] { width: 72px; }

    .rt-page .util-inline .chk {
        display: flex;
        gap: 0.375rem;
        align-items: center;
        font-size: 0.8125rem;
        color: var(--util-muted);
        white-space: nowrap;
    }

    .rt-page .util-inline .btn.success,
    .rt-page .util-inline .btn.danger {
        border-radius: 8px;
        font-size: 0.75rem;
        padding: 0.4rem 0.75rem;
    }

    .rt-page .util-row-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-shrink: 0;
    }

    .rt-page .util-empty {
        color: var(--util-muted);
        font-size: 0.8125rem;
        padding: 1rem;
        text-align: center;
    }

    .rt-page .util-inline.util-inline--edit-row {
        align-items: flex-end;
    }

    .rt-page .util-inline.util-inline--edit-row .util-row-actions {
        align-self: flex-end;
    }

    .rt-page .util-inline.util-inline--edit-row .btn.success,
    .rt-page .util-inline.util-inline--edit-row .btn.danger {
        height: 2.5rem;
        min-height: 2.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
    }

    .rt-page .util-inline.util-inline--edit-row select.form-custom-input,
    .rt-page .util-inline.util-inline--edit-row input[type="number"] {
        height: 2.5rem;
        min-height: 2.5rem;
        box-sizing: border-box;
    }

    @media (max-width: 768px) {
        .rt-page .util-inline select.form-custom-input { width: 100%; }
    }

    .rt-page .util-office-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.5rem;
        border-radius: 999px;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .rt-page .util-office-badge--processing {
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #c7d2fe;
    }

    .rt-page .util-office-badge--sub {
        background: #ecfeff;
        color: #0e7490;
        border: 1px solid #a5f3fc;
    }

    .rt-page .util-office-badge--inactive {
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .rt-page .util-office-node__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
        margin-bottom: 0.625rem;
    }

    .rt-page .util-office-users {
        font-size: 0.75rem;
        color: var(--util-muted);
    }

    .rt-page .util-office-flow {
        background: #f8fafc;
        border: 1px dashed var(--util-border);
        border-radius: 10px;
        padding: 0.875rem 1rem;
        margin-bottom: 1rem;
        font-size: 0.8125rem;
        color: var(--util-muted);
        line-height: 1.6;
    }

    .rt-page .util-office-flow strong { color: var(--util-text); }

    .rt-page .voucher-card + .voucher-card { margin-top: 1.5rem; }
</style>

<div class="main main--dashboard rt-page" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Routing</h1>
    </header>

    <?php if ($flash): ?>
        <div class="util-alert <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" style="margin-bottom:1.25rem;">
            <?= $flash['type'] === 'success' ? '<i class="ri-checkbox-circle-fill"></i>' : ($flash['type'] === 'error' ? '<i class="ri-error-warning-fill"></i>' : '<i class="ri-information-fill"></i>') ?>
            <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">
            <span class="voucher-card-title__label">
                <i class="ri-route-line ri-icon"></i>
                Direct Forward Routing
            </span>
        </h2>
        <div class="content-wrapper">
            <p class="util-section-title">All routing configurations</p>
            <p class="util-dv-desc">
                Configure voucher types that skip the standard workflow when forwarded from the Voucher page
                (e.g. e-NGP types sent directly to Accounting Unit or another unit). Each voucher type can have one active rule.
            </p>

            <div class="util-stats">
                <div class="util-stat"><strong><?= (int) $rule_count ?></strong> routing rule<?= $rule_count === 1 ? '' : 's' ?></div>
                <div class="util-stat"><strong><?= (int) $active_count ?></strong> active</div>
            </div>

            <div class="util-card">
                <div class="util-card__head">
                    <h3>Add routing rule</h3>
                </div>
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
                        <div class="field util-add-btn-field">
                            <label class="util-field-spacer" aria-hidden="true">&nbsp;</label>
                            <button class="btn primary util-btn-add" type="submit" title="Add routing rule" aria-label="Add routing rule">+</button>
                        </div>
                    </form>

                    <?php if (!$rules): ?>
                        <p class="util-empty">No routing rules yet. Add one above.</p>
                    <?php endif; ?>

                    <?php foreach ($rules as $rule):
                        $ruleId = (int) ($rule['id'] ?? 0);
                        $storedType = (string) ($rule['voucher_type'] ?? '');
                    ?>
                        <div class="util-routing-block">
                            <div class="util-routing-block__main">
                                <div class="util-inline util-inline--edit-row">
                                    <form method="post" class="util-inline util-inline--edit-row" style="flex:1; min-width:0;">
                                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="rule_update">
                                        <input type="hidden" name="id" value="<?= $ruleId ?>">
                                        <select class="form-custom-input" name="voucher_type" required>
                                            <?php foreach ($voucher_types as $type_value => $label): ?>
                                                <option value="<?= htmlspecialchars((string) $type_value, ENT_QUOTES, 'UTF-8') ?>"<?= $storedType === (string) $type_value ? ' selected' : '' ?>>
                                                    <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="form-custom-input" name="forward_designation" required>
                                            <?php foreach ($destinations as $destination): ?>
                                                <option value="<?= htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') ?>"<?= ($rule['forward_designation'] ?? '') === $destination ? ' selected' : '' ?>>
                                                    <?= htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input class="form-custom-input" type="number" name="sort_order" value="<?= (int) ($rule['sort_order'] ?? 0) ?>">
                                        <label class="chk">
                                            <input type="checkbox" name="is_active" <?= ((int) ($rule['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                                            <span>Active</span>
                                        </label>
                                        <button class="btn success" type="submit">Save</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Delete this routing rule?');" class="util-row-actions">
                                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="rule_delete">
                                        <input type="hidden" name="id" value="<?= $ruleId ?>">
                                        <button class="btn danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">
            <span class="voucher-card-title__label">
                <i class="ri-building-4-line ri-icon"></i>
                Office Hierarchy
            </span>
        </h2>
        <div class="content-wrapper">
            <p class="util-section-title">Office registry and forwarding relationships</p>
            <p class="util-dv-desc">
                Register offices used in user accounts and define parent-child relationships for voucher forwarding.
                Mark one office as the <strong>processing office</strong> (main PENRO). Sub-offices forward encoders to a
                Liaison Officer; nested sub-offices forward to the Liaison Officer of their parent sub-office.
            </p>

            <div class="util-office-flow">
                <strong>Forwarding flow:</strong>
                Processing office (e.g. <?= htmlspecialchars($processing_office_name !== '' ? $processing_office_name : 'Main PENRO', ENT_QUOTES, 'UTF-8') ?>)
                &rarr; sub-offices with Liaison Officers &rarr; sub-sub-offices send vouchers to the parent sub-office Liaison,
                who then forwards upstream to ICU at the processing office.
            </div>

            <div class="util-stats">
                <div class="util-stat"><strong><?= (int) $office_count ?></strong> registered office<?= $office_count === 1 ? '' : 's' ?></div>
                <div class="util-stat">
                    Processing:
                    <strong><?= htmlspecialchars($processing_office_name !== '' ? $processing_office_name : 'Not set', ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            </div>

            <div class="util-card">
                <div class="util-card__head">
                    <h3>Add office</h3>
                </div>
                <div class="util-card__body">
                    <form method="post" class="util-add">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="office_add">
                        <div class="field">
                            <label for="add_office_name">Office name</label>
                            <input class="form-custom-input" type="text" name="office_name" id="add_office_name" required placeholder="e.g. CENRO BORONGAN">
                        </div>
                        <div class="field">
                            <label for="add_parent_office_id">Parent office</label>
                            <select class="form-custom-input" name="parent_office_id" id="add_parent_office_id">
                                <option value="">No parent (top level)</option>
                                <?php foreach ($offices as $officeRow): ?>
                                    <option value="<?= (int) ($officeRow['id'] ?? 0) ?>">
                                        <?= htmlspecialchars((string) ($officeRow['office_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field" style="max-width:110px;">
                            <label for="add_office_sort_order">Sort</label>
                            <input class="form-custom-input" type="number" name="sort_order" id="add_office_sort_order" value="0">
                        </div>
                        <div class="field" style="min-width:140px;">
                            <label class="util-field-spacer" aria-hidden="true">&nbsp;</label>
                            <label class="chk" style="height:2.5rem;">
                                <input type="checkbox" name="is_processing_office" id="add_is_processing_office">
                                <span>Processing office</span>
                            </label>
                        </div>
                        <div class="field util-add-btn-field">
                            <label class="util-field-spacer" aria-hidden="true">&nbsp;</label>
                            <button class="btn primary util-btn-add" type="submit" title="Add office" aria-label="Add office">+</button>
                        </div>
                    </form>

                    <?php if (!$offices): ?>
                        <p class="util-empty">No offices registered yet. Add one above or they will be seeded from existing user accounts on first load.</p>
                    <?php else: ?>
                        <?php routing_render_office_tree($pdo, $office_tree, $offices); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../protected/js/main.js"></script>
</body>

</html>
