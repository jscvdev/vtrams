<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_voucher_type_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_list_filter_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/sort_order_helper.inc.php';
AuditHelper::logPageView('Types');

if (!AccessControl::canAccessSystemUtilities()) {
    echo "<script>process_functionAlert('Access denied!', 'dashboard_redirect')</script>";
    die();
}

utilities_voucher_type_ensure_schema($pdo);

$lockableFields = utilities_voucher_type_lockable_fields();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk = isset($_POST['token'], $_SESSION['token']) && hash_equals((string) $_SESSION['token'], (string) $_POST['token']);
    if (!$tokenOk) {
        $flash = ['type' => 'error', 'msg' => 'Invalid token. Please refresh and try again.'];
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'type_add') {
                $typeKey = utilities_voucher_type_normalize_value((string) ($_POST['type_key'] ?? ''));
                $displayLabel = utilities_voucher_type_normalize_value((string) ($_POST['display_label'] ?? ''));
                $particulars = trim((string) ($_POST['default_particulars'] ?? ''));
                $lockedFields = utilities_voucher_type_parse_locked_fields_from_post($_POST);
                $sort = (int) ($_POST['sort_order'] ?? 0);
                $requireEdit = isset($_POST['require_particulars_edit']) ? 1 : 0;

                if ($typeKey === '') {
                    $flash = ['type' => 'error', 'msg' => 'Voucher type key is required.'];
                } else {
                    if ($particulars === '') {
                        $particulars = utilities_voucher_type_default_particulars();
                    }
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("
                        INSERT INTO voucher_type_settings
                            (type_key, display_label, default_particulars, locked_fields_json, require_particulars_edit, sort_order, is_active)
                        VALUES
                            (:type_key, :display_label, :default_particulars, :locked_fields_json, :require_edit, 0, 1)
                    ");
                    $stmt->execute([
                        ':type_key' => $typeKey,
                        ':display_label' => $displayLabel !== '' ? $displayLabel : null,
                        ':default_particulars' => $particulars,
                        ':locked_fields_json' => utilities_voucher_type_encode_locked_fields($lockedFields),
                        ':require_edit' => $requireEdit,
                    ]);
                    sort_order_place_at_position($pdo, 'voucher_type_settings', (int) $pdo->lastInsertId(), $sort);
                    $pdo->commit();
                    utilities_voucher_type_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Voucher type added.'];
                }
            } elseif ($action === 'type_update') {
                $id = (int) ($_POST['id'] ?? 0);
                $typeKey = utilities_voucher_type_normalize_value((string) ($_POST['type_key'] ?? ''));
                $displayLabel = utilities_voucher_type_normalize_value((string) ($_POST['display_label'] ?? ''));
                $particulars = trim((string) ($_POST['default_particulars'] ?? ''));
                $lockedFields = utilities_voucher_type_parse_locked_fields_from_post($_POST);
                $sort = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                $requireEdit = isset($_POST['require_particulars_edit']) ? 1 : 0;

                if ($id <= 0 || $typeKey === '') {
                    $flash = ['type' => 'error', 'msg' => 'Invalid update payload.'];
                } else {
                    if ($particulars === '') {
                        $particulars = utilities_voucher_type_default_particulars();
                    }
                    $pdo->beginTransaction();
                    sort_order_handle_update($pdo, 'voucher_type_settings', $id, $sort);
                    $stmt = $pdo->prepare("
                        UPDATE voucher_type_settings
                        SET type_key = :type_key,
                            display_label = :display_label,
                            default_particulars = :default_particulars,
                            locked_fields_json = :locked_fields_json,
                            require_particulars_edit = :require_edit,
                            sort_order = :sort,
                            is_active = :active
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':type_key' => $typeKey,
                        ':display_label' => $displayLabel !== '' ? $displayLabel : null,
                        ':default_particulars' => $particulars,
                        ':locked_fields_json' => utilities_voucher_type_encode_locked_fields($lockedFields),
                        ':require_edit' => $requireEdit,
                        ':sort' => $sort,
                        ':active' => $active,
                        ':id' => $id,
                    ]);
                    $pdo->commit();
                    utilities_voucher_type_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Voucher type updated.'];
                }
            } elseif ($action === 'type_delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                } else {
                    $stmt = $pdo->prepare('DELETE FROM voucher_type_settings WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    utilities_voucher_type_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'Voucher type deleted.'];
                }
            } else {
                $flash = ['type' => 'error', 'msg' => 'Unknown action.'];
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                $flash = ['type' => 'warning', 'msg' => 'That voucher type key already exists.'];
            } else {
                $flash = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
            }
        }
    }
}

$all_type_rows = utilities_voucher_type_fetch_all($pdo);
$known_type_keys = utilities_voucher_type_known_type_keys($pdo);
$list_type_options = utilities_list_type_options_from_rows($all_type_rows, 'type_key', 'display_label');
$list_filter = utilities_list_filter_voucher_type_params($list_type_options);
$type_count = count($all_type_rows);
$type_rows_for_filter = utilities_list_filter_by_field_value($all_type_rows, 'type_key', $list_filter['voucher_type']);
$filtered_type_rows = utilities_list_filter_rows(
    $type_rows_for_filter,
    $list_filter['q'],
    'all',
    ['type_key', 'display_label', 'default_particulars']
);
$type_rows = utilities_list_limit_initial($filtered_type_rows, $list_filter['is_filtered']);
$type_visible = count($type_rows);
?>

<style>
    .typ-page {
        --util-bg: #f8fafc;
        --util-card: #fff;
        --util-border: #e2e8f0;
        --util-text: #0f172a;
        --util-muted: #64748b;
        --util-accent: #4f46e5;
        --util-radius: 14px;
        --util-shadow: 0 1px 3px rgba(15, 23, 42, .06);
    }

    .typ-page .voucher-dashboard-header { margin-bottom: 1.5rem; }
    .typ-page .voucher-dashboard-title {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--util-text);
    }

    .typ-page .voucher-card {
        background: var(--util-card);
        border: 1px solid var(--util-border);
        border-radius: var(--util-radius);
        box-shadow: var(--util-shadow);
        overflow: hidden;
    }

    .typ-page .voucher-card-title {
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

    .typ-page .voucher-card-title__label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .typ-page .voucher-card-title .ri-icon {
        font-size: 1.25rem;
        color: var(--util-accent);
    }

    .typ-page .content-wrapper { padding: 1.25rem; }

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

    .util-desc {
        margin: 0 0 1.25rem 0;
        color: var(--util-muted);
        font-size: 0.8125rem;
        line-height: 1.5;
    }

    .typ-add-form {
        display: grid;
        grid-template-columns: minmax(160px, 1fr) minmax(160px, 1fr) minmax(280px, 2fr);
        gap: 0.75rem;
        align-items: end;
        margin-bottom: 1.25rem;
        padding: 1rem;
        border: 1px solid var(--util-border);
        border-radius: 12px;
        background: #fafbfc;
    }

    .typ-add-form .field label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--util-muted);
        margin-bottom: 0.375rem;
    }

    .typ-add-form .field--wide { grid-column: 1 / -1; }

    .typ-lock-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.35rem 0.75rem;
        margin-top: 0.35rem;
    }

    .typ-lock-grid .chk {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.8125rem;
        color: var(--util-muted);
        margin: 0;
    }

    .typ-type-block {
        border: 1px solid var(--util-border);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1rem;
        background: #fff;
    }

    .typ-type-block:last-child { margin-bottom: 0; }

    .typ-type-block__head {
        padding: 0.875rem 1rem;
        background: linear-gradient(180deg, #fafafa 0%, #f8fafc 100%);
        border-bottom: 1px solid var(--util-border);
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--util-text);
    }

    .typ-type-block__body { padding: 1rem; }

    .typ-edit-grid {
        display: grid;
        grid-template-columns: minmax(160px, 1fr) minmax(160px, 1fr) 72px;
        gap: 0.75rem;
        align-items: end;
        margin-bottom: 0.75rem;
    }

    .typ-edit-grid .field label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--util-muted);
        margin-bottom: 0.375rem;
    }

    .typ-edit-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        flex-wrap: wrap;
        margin-top: 0.75rem;
    }

    .typ-page .form-custom-input {
        border-radius: 8px;
        border: 1px solid var(--util-border);
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        width: 100%;
        box-sizing: border-box;
    }

    .typ-page textarea.form-custom-input {
        min-height: 72px;
        resize: vertical;
    }

    .typ-page .chk {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.8125rem;
        color: var(--util-muted);
    }

    .util-empty {
        color: var(--util-muted);
        font-size: 0.8125rem;
        padding: 1rem;
        text-align: center;
    }

    .typ-stat {
        font-size: 0.8125rem;
        color: var(--util-muted);
        font-weight: 500;
    }

    @media (max-width: 900px) {
        .typ-add-form,
        .typ-edit-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
<?php require __DIR__ . '/partials/list_filter_styles.php'; ?>

<div class="main main--voucher-dashboard util-premium-page typ-page" id="main">
    <header class="voucher-dashboard-header">
        <div class="voucher-dashboard-header__text">
            <h1 class="voucher-dashboard-title">Types</h1>
            <p class="voucher-dashboard-subtitle">Configure default particulars and locked fields for each voucher type on the New Voucher form.</p>
        </div>
    </header>

    <?php
    $list_total = $type_count;
    $list_visible = $type_visible;
    $list_placeholder = 'search';
    $list_form_id = 'typesListFilterForm';
    $list_filter_mode = 'voucher_type';
    $list_voucher_types = $list_type_options;
    $list_type_filter_label = 'Type';
    require __DIR__ . '/partials/list_filter_toolbar.php';
    ?>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">
            <span class="voucher-card-title__label">
                <i class="ri-list-settings-line ri-icon"></i>
                Voucher Type Setup
            </span>
            <span class="typ-stat"><?= (int) $type_count ?> type<?= $type_count === 1 ? '' : 's' ?></span>
        </h2>
        <div class="content-wrapper">
            <?php if ($flash): ?>
                <div class="util-alert <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= $flash['type'] === 'success' ? '<i class="ri-checkbox-circle-fill"></i>' : ($flash['type'] === 'error' ? '<i class="ri-error-warning-fill"></i>' : '<i class="ri-information-fill"></i>') ?>
                    <?= htmlspecialchars($flash['msg']) ?>
                </div>
            <?php endif; ?>

            <p class="util-desc">
                Configure default particulars and read-only fields per voucher type for the New Voucher form.
                Placeholders such as <code>&lt;MONTH YEAR&gt;</code>, <code>&lt;QUARTER&gt;</code>, and <code>&lt;YEAR&gt;</code>
                are replaced when applicable (TEV / eNGP).
            </p>

            <form method="post" class="typ-add-form">
                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                <input type="hidden" name="action" value="type_add">
                <div class="field">
                    <label for="add_type_key">Type key</label>
                    <input class="form-custom-input" type="text" name="type_key" id="add_type_key" list="known-type-keys" placeholder="e.g., Traveling Expenses" required>
                </div>
                <div class="field">
                    <label for="add_display_label">Display label (optional)</label>
                    <input class="form-custom-input" type="text" name="display_label" id="add_display_label" placeholder="Dropdown label override">
                </div>
                <div class="field">
                    <label for="add_sort_order">Sort</label>
                    <input class="form-custom-input" type="number" name="sort_order" id="add_sort_order" value="0">
                </div>
                <div class="field field--wide">
                    <label for="add_default_particulars">Default particulars</label>
                    <textarea class="form-custom-input" name="default_particulars" id="add_default_particulars" rows="3" placeholder="<?= htmlspecialchars(utilities_voucher_type_default_particulars(), ENT_QUOTES, 'UTF-8') ?>"></textarea>
                </div>
                <div class="field field--wide">
                    <label>Locked / read-only fields</label>
                    <div class="typ-lock-grid">
                        <?php foreach ($lockableFields as $fieldKey => $fieldLabel): ?>
                            <label class="chk">
                                <input type="checkbox" name="locked_fields[]" value="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>">
                                <span><?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="field field--wide">
                    <label class="chk">
                        <input type="checkbox" name="require_particulars_edit" checked>
                        <span>Require user to edit particulars before submit</span>
                    </label>
                </div>
                <div class="field field--wide">
                    <button class="btn primary" type="submit">Add type</button>
                </div>
            </form>

            <datalist id="known-type-keys">
                <?php foreach ($known_type_keys as $key): ?>
                    <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <?php if (!$type_rows): ?>
                <p class="util-empty"><?= $list_filter['is_filtered'] ? 'No types match your search.' : 'No voucher type settings yet. Add one above or seed from checklist types on first load.' ?></p>
            <?php endif; ?>

            <?php foreach ($type_rows as $row):
                $rowId = (int) ($row['id'] ?? 0);
                $locked = $row['locked_fields'] ?? [];
            ?>
                <div class="typ-type-block">
                    <div class="typ-type-block__head">
                        <?= htmlspecialchars((string) ($row['type_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($row['display_label'])): ?>
                            <span style="font-weight:500;color:var(--util-muted);"> — <?= htmlspecialchars((string) $row['display_label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="typ-type-block__body">
                        <form method="post">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                            <input type="hidden" name="action" value="type_update">
                            <input type="hidden" name="id" value="<?= $rowId ?>">
                            <div class="typ-edit-grid">
                                <div class="field">
                                    <label>Type key</label>
                                    <input class="form-custom-input" type="text" name="type_key" value="<?= htmlspecialchars((string) ($row['type_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="field">
                                    <label>Display label</label>
                                    <input class="form-custom-input" type="text" name="display_label" value="<?= htmlspecialchars((string) ($row['display_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="field">
                                    <label>Sort</label>
                                    <input class="form-custom-input" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                                </div>
                            </div>
                            <div class="field">
                                <label>Default particulars</label>
                                <textarea class="form-custom-input" name="default_particulars" rows="3"><?= htmlspecialchars((string) ($row['default_particulars'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="field" style="margin-top:0.75rem;">
                                <label>Locked / read-only fields</label>
                                <div class="typ-lock-grid">
                                    <?php foreach ($lockableFields as $fieldKey => $fieldLabel): ?>
                                        <label class="chk">
                                            <input type="checkbox" name="locked_fields[]" value="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($fieldKey, $locked, true) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div style="margin-top:0.75rem; display:flex; gap:1rem; flex-wrap:wrap;">
                                <label class="chk">
                                    <input type="checkbox" name="require_particulars_edit" <?= !empty($row['require_particulars_edit']) ? 'checked' : '' ?>>
                                    <span>Require particulars edit</span>
                                </label>
                                <label class="chk">
                                    <input type="checkbox" name="is_active" <?= !empty($row['is_active']) ? 'checked' : '' ?>>
                                    <span>Active</span>
                                </label>
                            </div>
                            <div class="typ-edit-actions">
                                <button class="btn success" type="submit">Save</button>
                            </div>
                        </form>
                        <form method="post" onsubmit="return confirm('Delete this voucher type setting?');" class="typ-edit-actions">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                            <input type="hidden" name="action" value="type_delete">
                            <input type="hidden" name="id" value="<?= $rowId ?>">
                            <button class="btn danger" type="submit">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="../../protected/js/main.js"></script>
</body>
</html>
