<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_checklist_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_list_filter_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/sort_order_helper.inc.php';
AuditHelper::logPageView('Checklist');

$target = explode(',', $_SESSION['logged_user_designation'] ?? '');
if (!AccessControl::canAccessSystemUtilities()) {
    echo "<script>process_functionAlert('Access denied!', 'dashboard_redirect')</script>";
    die();
}

utilities_checklist_ensure_schema($pdo);

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk = isset($_POST['token'], $_SESSION['token']) && hash_equals((string) $_SESSION['token'], (string) $_POST['token']);
    if (!$tokenOk) {
        $flash = ['type' => 'error', 'msg' => 'Invalid token. Please refresh and try again.'];
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'type_add') {
                $typeKey = utilities_checklist_normalize_value((string) ($_POST['type_key'] ?? ''));
                $title = utilities_checklist_normalize_value((string) ($_POST['title'] ?? ''));
                $displayLabel = utilities_checklist_normalize_value((string) ($_POST['display_label'] ?? ''));
                $sort = (int) ($_POST['sort_order'] ?? 0);
                if ($typeKey === '') {
                    $flash = ['type' => 'error', 'msg' => 'Voucher type name is required.'];
                } else {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("
                        INSERT INTO checklist_type_options (type_key, title, display_label, sort_order, is_active)
                        VALUES (:type_key, :title, :display_label, 0, 1)
                    ");
                    $stmt->execute([
                        ':type_key' => $typeKey,
                        ':title' => $title !== '' ? $title : strtoupper($typeKey),
                        ':display_label' => $displayLabel !== '' ? $displayLabel : null,
                    ]);
                    sort_order_place_at_position($pdo, 'checklist_type_options', (int) $pdo->lastInsertId(), $sort);
                    $pdo->commit();
                    utilities_checklist_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Voucher type added.'];
                }
            } elseif ($action === 'type_update') {
                $id = (int) ($_POST['id'] ?? 0);
                $typeKey = utilities_checklist_normalize_value((string) ($_POST['type_key'] ?? ''));
                $title = utilities_checklist_normalize_value((string) ($_POST['title'] ?? ''));
                $displayLabel = utilities_checklist_normalize_value((string) ($_POST['display_label'] ?? ''));
                $sort = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($id <= 0 || $typeKey === '') {
                    $flash = ['type' => 'error', 'msg' => 'Invalid update payload.'];
                } else {
                    $pdo->beginTransaction();
                    sort_order_handle_update($pdo, 'checklist_type_options', $id, $sort);
                    $stmt = $pdo->prepare("
                        UPDATE checklist_type_options
                        SET type_key = :type_key, title = :title, display_label = :display_label,
                            sort_order = :sort, is_active = :active
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':type_key' => $typeKey,
                        ':title' => $title !== '' ? $title : strtoupper($typeKey),
                        ':display_label' => $displayLabel !== '' ? $displayLabel : null,
                        ':sort' => $sort,
                        ':active' => $active,
                        ':id' => $id,
                    ]);
                    $pdo->commit();
                    utilities_checklist_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Voucher type updated.'];
                }
            } elseif ($action === 'type_delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                } else {
                    $stmt = $pdo->prepare('DELETE FROM checklist_type_options WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    utilities_checklist_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Voucher type deleted.'];
                }
            } elseif ($action === 'item_add') {
                $typeId = (int) ($_POST['checklist_type_id'] ?? 0);
                $label = utilities_checklist_normalize_value((string) ($_POST['item_label'] ?? ''));
                $subitems = utilities_checklist_parse_subitems_text((string) ($_POST['subitems_text'] ?? ''));
                $sort = (int) ($_POST['sort_order'] ?? 0);
                if (!utilities_checklist_type_exists($pdo, $typeId)) {
                    $flash = ['type' => 'error', 'msg' => 'Voucher type not found.'];
                } elseif ($label === '') {
                    $flash = ['type' => 'error', 'msg' => 'Requirement label is required.'];
                } else {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("
                        INSERT INTO checklist_type_items (checklist_type_id, item_label, subitems_json, sort_order, is_active)
                        VALUES (:type_id, :label, :subitems, 0, 1)
                    ");
                    $stmt->execute([
                        ':type_id' => $typeId,
                        ':label' => $label,
                        ':subitems' => utilities_checklist_encode_subitems($subitems),
                    ]);
                    sort_order_place_at_position(
                        $pdo,
                        'checklist_type_items',
                        (int) $pdo->lastInsertId(),
                        $sort,
                        ['checklist_type_id' => $typeId]
                    );
                    $pdo->commit();
                    utilities_checklist_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Checklist requirement added.'];
                }
            } elseif ($action === 'item_update') {
                $id = (int) ($_POST['id'] ?? 0);
                $typeId = (int) ($_POST['checklist_type_id'] ?? 0);
                $label = utilities_checklist_normalize_value((string) ($_POST['item_label'] ?? ''));
                $subitems = utilities_checklist_parse_subitems_text((string) ($_POST['subitems_text'] ?? ''));
                $sort = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($id <= 0 || $typeId <= 0 || $label === '') {
                    $flash = ['type' => 'error', 'msg' => 'Invalid requirement update payload.'];
                } else {
                    $pdo->beginTransaction();
                    sort_order_handle_update(
                        $pdo,
                        'checklist_type_items',
                        $id,
                        $sort,
                        ['checklist_type_id' => $typeId]
                    );
                    $stmt = $pdo->prepare("
                        UPDATE checklist_type_items
                        SET item_label = :label, subitems_json = :subitems, sort_order = :sort, is_active = :active
                        WHERE id = :id AND checklist_type_id = :type_id
                    ");
                    $stmt->execute([
                        ':label' => $label,
                        ':subitems' => utilities_checklist_encode_subitems($subitems),
                        ':sort' => $sort,
                        ':active' => $active,
                        ':id' => $id,
                        ':type_id' => $typeId,
                    ]);
                    $pdo->commit();
                    utilities_checklist_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Checklist requirement updated.'];
                }
            } elseif ($action === 'item_delete') {
                $id = (int) ($_POST['id'] ?? 0);
                $typeId = (int) ($_POST['checklist_type_id'] ?? 0);
                if ($id <= 0 || $typeId <= 0) {
                    $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                } else {
                    $stmt = $pdo->prepare('DELETE FROM checklist_type_items WHERE id = :id AND checklist_type_id = :type_id');
                    $stmt->execute([':id' => $id, ':type_id' => $typeId]);
                    utilities_checklist_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Checklist requirement deleted.'];
                }
            } else {
                $flash = ['type' => 'error', 'msg' => 'Unknown action.'];
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                $flash = ['type' => 'warning', 'msg' => 'That value already exists.'];
            } else {
                $flash = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
            }
        }
    }
}

$all_checklist_types = utilities_checklist_fetch_all($pdo);
$list_type_options = utilities_list_type_options_from_rows($all_checklist_types, 'type_key', 'display_label');
$list_filter = utilities_list_filter_voucher_type_params($list_type_options);
$type_count = count($all_checklist_types);
$item_count = 0;
foreach ($all_checklist_types as $t) {
    $item_count += count($t['items'] ?? []);
}
$checklist_rows_for_filter = utilities_list_filter_by_field_value($all_checklist_types, 'type_key', $list_filter['voucher_type']);
$filtered_checklist_types = utilities_list_filter_rows(
    $checklist_rows_for_filter,
    $list_filter['q'],
    'all',
    ['type_key', 'title', 'display_label']
);
$checklist_types = utilities_list_limit_initial($filtered_checklist_types, $list_filter['is_filtered']);
$checklist_visible = count($checklist_types);
?>

<style>
    .chk-page {
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

    .chk-page .voucher-dashboard-header { margin-bottom: 1.5rem; }
    .chk-page .voucher-dashboard-title {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--util-text);
    }

    .chk-page .voucher-card {
        background: var(--util-card);
        border: 1px solid var(--util-border);
        border-radius: var(--util-radius);
        box-shadow: var(--util-shadow);
        overflow: hidden;
    }

    .chk-page .voucher-card-title {
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

    .chk-page .voucher-card-title__label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .chk-page .voucher-card-title .ri-icon {
        font-size: 1.25rem;
        color: var(--util-accent);
    }

    .chk-page .content-wrapper { padding: 1.25rem; }

    .util-alert {
        padding: 0.875rem 1rem;
        border-radius: 10px;
        margin-bottom: 1.25rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid transparent;
    }

    .util-alert.success { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .util-alert.error { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
    .util-alert.warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }

    .util-section-title {
        font-size: 0.8125rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--util-muted);
        margin: 0 0 1rem 0;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--util-border);
    }

    .util-dv-desc {
        color: var(--util-muted);
        font-size: 0.875rem;
        margin: -0.5rem 0 1.25rem 0;
        line-height: 1.5;
    }

    .util-card {
        background: var(--util-card);
        border: 1px solid var(--util-border);
        border-radius: var(--util-radius);
        overflow: hidden;
        box-shadow: var(--util-shadow);
    }

    .util-card__head {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--util-border);
        background: linear-gradient(180deg, #fafafa 0%, #f1f5f9 100%);
    }

    .util-card__head h3 {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--util-text);
    }

    .util-card__body { padding: 1.25rem; }

    .util-add {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: flex-end;
        margin-bottom: 1rem;
    }

    .util-add .field { flex: 1; min-width: 160px; }

    .util-add label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--util-muted);
        margin-bottom: 0.375rem;
    }

    .util-add .form-custom-input {
        border-radius: 8px;
        border: 1px solid var(--util-border);
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        width: 100%;
    }

    .util-add .field.util-field-subitems {
        flex: 1 1 220px;
        min-width: 220px;
        max-width: 320px;
    }

    .util-add .field.util-field-subitems label {
        white-space: nowrap;
    }

    .util-add .field.util-add-btn-field {
        flex: 0 0 auto;
        min-width: auto;
    }

    .util-add .field.util-add-btn-field label.util-field-spacer {
        display: block;
        visibility: hidden;
        height: 0;
        margin: 0 0 0.375rem 0;
        padding: 0;
        overflow: hidden;
        line-height: 0;
    }

    .util-add .btn.util-btn-add {
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

    .util-add-hint {
        margin: -0.5rem 0 1rem 0;
        width: 100%;
    }

    .util-checklist-block {
        border: 1px solid var(--util-border);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1rem;
        background: #fff;
    }

    .util-checklist-block__main {
        padding: 0.875rem 1rem;
        background: linear-gradient(180deg, #fafafa 0%, #f8fafc 100%);
        border-bottom: 1px solid var(--util-border);
    }

    .util-checklist-block__items {
        padding: 1rem;
        background: #fafbfc;
    }

    .util-checklist-block__items-title {
        margin: 0 0 0.75rem 0;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--util-muted);
    }

    .util-inline {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .util-inline .form-custom-input {
        border-radius: 8px;
        border: 1px solid var(--util-border);
        padding: 0.4rem 0.6rem;
        font-size: 0.8125rem;
    }

    .util-inline input[type="text"] { min-width: 140px; flex: 1; }
    .util-inline input[type="number"] { width: 72px; }

    .util-inline .chk {
        display: flex;
        gap: 0.375rem;
        align-items: center;
        font-size: 0.8125rem;
        color: var(--util-muted);
        white-space: nowrap;
    }

    .util-inline .btn.success,
    .util-inline .btn.danger {
        border-radius: 8px;
        font-size: 0.75rem;
        padding: 0.4rem 0.75rem;
    }

    .util-row-actions { display: flex; gap: 0.5rem; align-items: center; }

    .util-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .util-table th,
    .util-table td {
        border-bottom: 1px solid #f1f5f9;
        padding: 0.625rem 0.75rem;
        text-align: left;
    }

    .util-table th {
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--util-muted);
    }

    .util-empty {
        color: var(--util-muted);
        font-size: 0.8125rem;
        padding: 1rem;
        text-align: center;
    }

    .util-subitems-input {
        min-width: 180px;
        height: 2.5rem;
        min-height: 2.5rem;
        max-height: 4.5rem;
        resize: vertical;
        font-family: inherit;
        line-height: 1.35;
        box-sizing: border-box;
    }

    .util-subitems-hint {
        font-size: 0.6875rem;
        color: var(--util-muted);
        margin: 0;
        line-height: 1.4;
    }

    .util-inline.util-inline--edit-row {
        align-items: flex-end;
    }

    .util-inline.util-inline--edit-row .util-row-actions {
        align-self: flex-end;
        flex-shrink: 0;
        padding-bottom: 0;
    }

    .util-inline.util-inline--edit-row .btn.success,
    .util-inline.util-inline--edit-row .btn.danger {
        height: 2.5rem;
        min-height: 2.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
    }

    .util-inline.util-inline--edit-row input[type="text"],
    .util-inline.util-inline--edit-row input[type="number"],
    .util-inline.util-inline--edit-row .util-subitems-input {
        height: 2.5rem;
        min-height: 2.5rem;
        box-sizing: border-box;
    }

    .util-inline.util-inline--edit-row .util-subitems-input {
        max-height: 2.5rem;
        padding-top: 0.35rem;
        padding-bottom: 0.35rem;
    }

    .util-stats {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .util-stat {
        background: #f8fafc;
        border: 1px solid var(--util-border);
        border-radius: 10px;
        padding: 0.625rem 1rem;
        font-size: 0.8125rem;
        color: var(--util-muted);
    }

    .util-stat strong { color: var(--util-text); }

    .util-type-key-input { font-weight: 600; }
    .util-title-input { min-width: 220px; }

    @media (max-width: 768px) {
        .util-inline input[type="text"] { width: 100%; }
    }
</style>
<?php require __DIR__ . '/partials/list_filter_styles.php'; ?>

<div class="main main--dashboard chk-page" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Checklist</h1>
    </header>

    <?php
    $list_total = $type_count;
    $list_visible = $checklist_visible;
    $list_placeholder = 'Search voucher type, title, or display label';
    $list_form_id = 'checklistListFilterForm';
    $list_filter_mode = 'voucher_type';
    $list_voucher_types = $list_type_options;
    $list_type_filter_label = 'Voucher type';
    require __DIR__ . '/partials/list_filter_toolbar.php';
    ?>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">
            <span class="voucher-card-title__label">
                <i class="ri-checkbox-multiple-line ri-icon"></i>
                Voucher Types &amp; Checklist Requirements
            </span>
        </h2>
        <div class="content-wrapper">
            <?php if ($flash): ?>
                <div class="util-alert <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= $flash['type'] === 'success' ? '<i class="ri-checkbox-circle-fill"></i>' : ($flash['type'] === 'error' ? '<i class="ri-error-warning-fill"></i>' : '<i class="ri-information-fill"></i>') ?>
                    <?= htmlspecialchars($flash['msg']) ?>
                </div>
            <?php endif; ?>

            <p class="util-section-title">All checklist configurations</p>
            <p class="util-dv-desc">
                Manage voucher types and their mandatory supporting document checklists used on forward slips and voucher forms.
                The stored type name must match the value saved on vouchers. Optional display label overrides how the type appears in dropdowns.
            </p>

            <div class="util-stats">
                <div class="util-stat"><strong><?= (int) $type_count ?></strong> voucher type<?= $type_count === 1 ? '' : 's' ?></div>
                <div class="util-stat"><strong><?= (int) $item_count ?></strong> checklist requirement<?= $item_count === 1 ? '' : 's' ?></div>
            </div>

            <div class="util-card">
                <div class="util-card__head">
                    <h3>Add voucher type</h3>
                </div>
                <div class="util-card__body">
                    <form method="post" class="util-add">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <input type="hidden" name="action" value="type_add">
                        <div class="field">
                            <label>Type name (stored value)</label>
                            <input class="form-custom-input util-type-key-input" type="text" name="type_key" placeholder="e.g., Traveling Expenses" required>
                        </div>
                        <div class="field util-title-input">
                            <label>Checklist title (forward slip header)</label>
                            <input class="form-custom-input" type="text" name="title" placeholder="e.g., MANDATORY SUPPORTING DOCUMENTS FOR TRAVELING EXPENSES">
                        </div>
                        <div class="field">
                            <label>Display label (optional)</label>
                            <input class="form-custom-input" type="text" name="display_label" placeholder="Shown in dropdowns">
                        </div>
                        <div class="field" style="max-width:110px;">
                            <label>Sort</label>
                            <input class="form-custom-input" type="number" name="sort_order" value="0">
                        </div>
                        <div class="field util-add-btn-field">
                            <label class="util-field-spacer" aria-hidden="true">&nbsp;</label>
                            <button class="btn primary util-btn-add" type="submit" title="Add voucher type" aria-label="Add voucher type">+</button>
                        </div>
                    </form>

                    <?php if (!$checklist_types): ?>
                        <p class="util-empty"><?= $list_filter['is_filtered'] ? 'No voucher types match your search.' : 'No voucher types yet. Add one above.' ?></p>
                    <?php endif; ?>

                    <?php foreach ($checklist_types as $type):
                        $typeId = (int) ($type['id'] ?? 0);
                        $items = $type['items'] ?? [];
                        $displayLabel = trim((string) ($type['display_label'] ?? ''));
                    ?>
                        <div class="util-checklist-block">
                            <div class="util-checklist-block__main">
                                <div class="util-inline">
                                    <form method="post" class="util-inline" style="flex:1; min-width:0;">
                                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                        <input type="hidden" name="action" value="type_update">
                                        <input type="hidden" name="id" value="<?= $typeId ?>">
                                        <input class="form-custom-input util-type-key-input" type="text" name="type_key" value="<?= htmlspecialchars((string) $type['type_key'], ENT_QUOTES, 'UTF-8') ?>" required title="Stored voucher type value">
                                        <input class="form-custom-input util-title-input" type="text" name="title" value="<?= htmlspecialchars((string) $type['title'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Checklist title">
                                        <input class="form-custom-input" type="text" name="display_label" value="<?= htmlspecialchars($displayLabel, ENT_QUOTES, 'UTF-8') ?>" placeholder="Display label">
                                        <input class="form-custom-input" type="number" name="sort_order" value="<?= (int) $type['sort_order'] ?>">
                                        <label class="chk">
                                            <input type="checkbox" name="is_active" <?= ((int) ($type['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                                            <span>Active</span>
                                        </label>
                                        <button class="btn success" type="submit">Save type</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Delete this voucher type and all checklist requirements?');" class="util-row-actions">
                                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                        <input type="hidden" name="action" value="type_delete">
                                        <input type="hidden" name="id" value="<?= $typeId ?>">
                                        <button class="btn danger" type="submit">Delete type</button>
                                    </form>
                                </div>
                            </div>

                            <div class="util-checklist-block__items">
                                <p class="util-checklist-block__items-title">
                                    Requirements for <?= htmlspecialchars((string) $type['type_key'], ENT_QUOTES, 'UTF-8') ?>
                                </p>

                                <form method="post" class="util-add" style="margin-bottom:0.75rem;">
                                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                    <input type="hidden" name="action" value="item_add">
                                    <input type="hidden" name="checklist_type_id" value="<?= $typeId ?>">
                                    <div class="field">
                                        <label>Requirement</label>
                                        <input class="form-custom-input" type="text" name="item_label" placeholder="e.g., Disbursement Voucher" required>
                                    </div>
                                    <div class="field util-field-subitems">
                                        <label>Sub-items</label>
                                        <textarea class="form-custom-input util-subitems-input" name="subitems_text" rows="1" placeholder="One per line (e.g. Plane, Van)"></textarea>
                                    </div>
                                    <div class="field" style="max-width:110px;">
                                        <label>Sort</label>
                                        <input class="form-custom-input" type="number" name="sort_order" value="0">
                                    </div>
                                    <div class="field util-add-btn-field">
                                        <label class="util-field-spacer" aria-hidden="true">&nbsp;</label>
                                        <button class="btn primary util-btn-add" type="submit" title="Add requirement" aria-label="Add requirement">+</button>
                                    </div>
                                </form>
                                <p class="util-subitems-hint util-add-hint">Optional sub-items (one per line) appear indented on the forward slip under the parent requirement.</p>

                                <table class="util-table">
                                    <thead>
                                        <tr>
                                            <th>Requirement</th>
                                            <th style="width:180px;">Sub-items</th>
                                            <th style="width:70px;">Sort</th>
                                            <th style="width:90px;">Active</th>
                                            <th style="width:110px; text-align:right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$items): ?>
                                            <tr>
                                                <td colspan="5" class="util-empty">No checklist requirements yet.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($items as $item):
                                            $subitemsText = implode("\n", $item['subitems'] ?? []);
                                        ?>
                                            <tr>
                                                <td colspan="5">
                                                    <div class="util-inline util-inline--edit-row">
                                                        <form method="post" class="util-inline util-inline--edit-row" style="flex:1; min-width:0;">
                                                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                                            <input type="hidden" name="action" value="item_update">
                                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                            <input type="hidden" name="checklist_type_id" value="<?= $typeId ?>">
                                                            <input class="form-custom-input" type="text" name="item_label" value="<?= htmlspecialchars((string) $item['item_label'], ENT_QUOTES, 'UTF-8') ?>" required>
                                                            <textarea class="form-custom-input util-subitems-input" name="subitems_text" rows="2"><?= htmlspecialchars($subitemsText, ENT_QUOTES, 'UTF-8') ?></textarea>
                                                            <input class="form-custom-input" type="number" name="sort_order" value="<?= (int) $item['sort_order'] ?>">
                                                            <label class="chk">
                                                                <input type="checkbox" name="is_active" <?= ((int) ($item['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                                                                <span>Active</span>
                                                            </label>
                                                            <button class="btn success" type="submit">Save</button>
                                                        </form>
                                                        <form method="post" onsubmit="return confirm('Delete this checklist requirement?');" class="util-row-actions">
                                                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                                            <input type="hidden" name="action" value="item_delete">
                                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                            <input type="hidden" name="checklist_type_id" value="<?= $typeId ?>">
                                                            <button class="btn danger" type="submit">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../protected/js/main.js"></script>
</body>

</html>
