<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_uacs_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_emp_tag_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_list_filter_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/sort_order_helper.inc.php';
require_once __DIR__ . '/../vouchers/checklist_config.php';
AuditHelper::logPageView('UACS Codes');

if (!AccessControl::canAccessSystemUtilities()) {
    echo "<script>process_functionAlert('Access denied!', 'dashboard_redirect')</script>";
    die();
}

utilities_uacs_ensure_schema($pdo);
utilities_emp_tag_ensure_schema($pdo);

$voucher_types = checklist_types_with_labels();
$emp_tag_options = utilities_emp_tag_fetch_all($pdo);
$emp_tag_default = utilities_emp_tag_default_value($pdo);
$known_tag_names = [];
foreach ($emp_tag_options as $tagRow) {
    $name = utilities_uacs_normalize_tag_name((string) ($tagRow['tag_value'] ?? ''));
    if ($name !== '') {
        $known_tag_names[$name] = $name;
    }
}

$selected_voucher_type = utilities_uacs_resolve_voucher_type(
    (string) ($_POST['voucher_type'] ?? $_POST['list_voucher_type'] ?? $_GET['voucher_type'] ?? ''),
    $voucher_types
);
if ($selected_voucher_type === '') {
    $selected_voucher_type = utilities_uacs_default_voucher_type($voucher_types);
}

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk = isset($_POST['token'], $_SESSION['token']) && hash_equals((string) $_SESSION['token'], (string) $_POST['token']);
    if (!$tokenOk) {
        $flash = ['type' => 'error', 'msg' => 'Invalid token. Please refresh and try again.'];
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $formVoucherType = utilities_uacs_resolve_voucher_type((string) ($_POST['voucher_type'] ?? ''), $voucher_types);
        if ($formVoucherType !== '') {
            $selected_voucher_type = $formVoucherType;
        }
        try {
            if ($action === 'uacs_add') {
                $tagName = utilities_uacs_normalize_tag_name((string) ($_POST['tag_name'] ?? ''));
                $title = utilities_uacs_normalize_title((string) ($_POST['account_title'] ?? ''));
                $uacsCheck = utilities_emp_tag_validate_uacs((string) ($_POST['uacs_code'] ?? ''), true);
                $indented = isset($_POST['is_indented']) ? 1 : 0;

                if ($formVoucherType === '') {
                    $flash = ['type' => 'error', 'msg' => 'Voucher type is required.'];
                } elseif ($tagName !== '' && !isset($known_tag_names[$tagName])) {
                    $flash = ['type' => 'error', 'msg' => 'Unknown tag. Add the employee tag first, then map UACS.'];
                } elseif (!$uacsCheck['ok']) {
                    $flash = ['type' => 'error', 'msg' => $uacsCheck['error']];
                } elseif ($indented === 1) {
                    if ($title === '') {
                        $flash = ['type' => 'error', 'msg' => 'Account title is required for sub UACS rows.'];
                    } elseif (!utilities_uacs_scope_exists($pdo, $formVoucherType, $tagName)) {
                        $flash = ['type' => 'error', 'msg' => 'Add a primary UACS for this voucher type and tag scope first.'];
                    } else {
                        $parentTitle = utilities_uacs_parent_title_for_scope($pdo, $formVoucherType, $tagName);
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("
                            INSERT INTO uacs_code_options (voucher_type, tag_name, account_title, parent_account_title, uacs_code, is_indented, sort_order, is_active)
                            VALUES (:voucher_type, :tag_name, :title, :parent, :uacs, 1, 0, 1)
                        ");
                        $stmt->execute([
                            ':voucher_type' => $formVoucherType,
                            ':tag_name' => $tagName,
                            ':title' => $title,
                            ':parent' => $parentTitle,
                            ':uacs' => $uacsCheck['uacs'],
                        ]);
                        $newId = (int) $pdo->lastInsertId();
                        $sort = sort_order_next_position($pdo, 'uacs_code_options', [
                            'voucher_type' => $formVoucherType,
                        ]);
                        sort_order_place_at_position($pdo, 'uacs_code_options', $newId, $sort, [
                            'voucher_type' => $formVoucherType,
                        ]);
                        $pdo->commit();
                        utilities_uacs_invalidate_cache();
                        $flash = ['type' => 'success', 'msg' => 'Sub UACS added.'];
                    }
                } elseif (utilities_uacs_scope_exists($pdo, $formVoucherType, $tagName)) {
                    $flash = ['type' => 'warning', 'msg' => 'A primary UACS already exists for this voucher type and tag scope.'];
                } elseif ($tagName === '' && $title === '') {
                    $flash = ['type' => 'error', 'msg' => 'Account title is required for voucher-type-only mappings.'];
                } else {
                    $accountTitle = utilities_uacs_resolve_primary_account_title($tagName, $title);
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("
                        INSERT INTO uacs_code_options (voucher_type, tag_name, account_title, parent_account_title, uacs_code, is_indented, sort_order, is_active)
                        VALUES (:voucher_type, :tag_name, :title, '', :uacs, 0, 0, 1)
                    ");
                    $stmt->execute([
                        ':voucher_type' => $formVoucherType,
                        ':tag_name' => $tagName,
                        ':title' => $accountTitle,
                        ':uacs' => $uacsCheck['uacs'],
                    ]);
                    $newId = (int) $pdo->lastInsertId();
                    $sort = sort_order_next_position($pdo, 'uacs_code_options', [
                        'voucher_type' => $formVoucherType,
                    ]);
                    sort_order_place_at_position($pdo, 'uacs_code_options', $newId, $sort, [
                        'voucher_type' => $formVoucherType,
                    ]);
                    $pdo->commit();
                    utilities_uacs_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'UACS mapping added.'];
                }
            } elseif ($action === 'uacs_update') {
                $id = (int) ($_POST['id'] ?? 0);
                $tagName = utilities_uacs_normalize_tag_name((string) ($_POST['tag_name'] ?? ''));
                $title = utilities_uacs_normalize_title((string) ($_POST['account_title'] ?? ''));
                $uacsCheck = utilities_emp_tag_validate_uacs((string) ($_POST['uacs_code'] ?? ''), true);
                $sort = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                $indented = isset($_POST['is_indented']) ? 1 : 0;

                if ($id <= 0 || $formVoucherType === '' || !$uacsCheck['ok']) {
                    $flash = ['type' => 'error', 'msg' => 'Invalid update payload.'];
                } elseif ($indented === 1 && $title === '') {
                    $flash = ['type' => 'error', 'msg' => 'Account title is required.'];
                } elseif ($indented === 0 && $tagName === '' && $title === '') {
                    $flash = ['type' => 'error', 'msg' => 'Account title is required for voucher-type-only mappings.'];
                } else {
                    $pdo->beginTransaction();
                    $oldParentTitle = '';
                    if ($indented === 0) {
                        $oldParentTitle = utilities_uacs_parent_title_for_scope($pdo, $formVoucherType, $tagName);
                    }
                    $parentTitle = $indented === 1
                        ? utilities_uacs_parent_title_for_scope($pdo, $formVoucherType, $tagName)
                        : '';
                    $accountTitle = $indented === 1
                        ? $title
                        : utilities_uacs_resolve_primary_account_title($tagName, $title);
                    sort_order_handle_update($pdo, 'uacs_code_options', $id, $sort, [
                        'voucher_type' => $formVoucherType,
                    ]);
                    $stmt = $pdo->prepare("
                        UPDATE uacs_code_options
                        SET voucher_type = :voucher_type,
                            tag_name = :tag_name,
                            account_title = :title,
                            parent_account_title = :parent,
                            uacs_code = :uacs,
                            is_indented = :indented,
                            sort_order = :sort,
                            is_active = :active
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':voucher_type' => $formVoucherType,
                        ':tag_name' => $tagName,
                        ':title' => $accountTitle,
                        ':parent' => $parentTitle,
                        ':uacs' => $uacsCheck['uacs'],
                        ':indented' => $indented,
                        ':sort' => $sort,
                        ':active' => $active,
                        ':id' => $id,
                    ]);
                    if ($indented === 0 && $oldParentTitle !== '' && $oldParentTitle !== $accountTitle) {
                        utilities_uacs_sync_sub_parent_titles($pdo, $formVoucherType, $tagName, $oldParentTitle, $accountTitle);
                    }
                    $pdo->commit();
                    utilities_uacs_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'UACS mapping updated.'];
                }
            } elseif ($action === 'uacs_delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                } else {
                    $stmt = $pdo->prepare('DELETE FROM uacs_code_options WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    utilities_uacs_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'UACS row deleted.'];
                }
            } elseif ($action === 'uacs_delete_scope') {
                $tagName = utilities_uacs_normalize_tag_name((string) ($_POST['tag_name'] ?? ''));
                if ($formVoucherType === '') {
                    $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                } else {
                    utilities_uacs_delete_scope($pdo, $formVoucherType, $tagName);
                    utilities_uacs_invalidate_cache();
                    $flash = ['type' => 'success', 'msg' => 'UACS scope deleted.'];
                }
            } elseif ($action === 'emp_tag_add') {
                $value = utilities_emp_tag_normalize_value((string) ($_POST['tag_value'] ?? ''));
                $isDefault = isset($_POST['is_default']) ? 1 : 0;
                if ($value === '') {
                    $flash = ['type' => 'error', 'msg' => 'Tag name is required.'];
                } else {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("
                        INSERT INTO emp_tag_options (tag_value, uacs_code, sort_order, is_active, is_default)
                        VALUES (:v, '', 0, 1, :d)
                    ");
                    $stmt->execute([':v' => $value, ':d' => $isDefault]);
                    $newTagId = (int) $pdo->lastInsertId();
                    $sort = sort_order_next_position($pdo, 'emp_tag_options');
                    sort_order_place_at_position($pdo, 'emp_tag_options', $newTagId, $sort);
                    if ($isDefault === 1) {
                        utilities_emp_tag_set_default($pdo, $newTagId);
                    }
                    $pdo->commit();
                    $flash = ['type' => 'success', 'msg' => 'Employee tag added. Map UACS above.'];
                }
            } elseif ($action === 'emp_tag_update') {
                $id = (int) ($_POST['id'] ?? 0);
                $value = utilities_emp_tag_normalize_value((string) ($_POST['tag_value'] ?? ''));
                $sort = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                $isDefault = isset($_POST['is_default']) ? 1 : 0;
                if ($id <= 0 || $value === '') {
                    $flash = ['type' => 'error', 'msg' => 'Invalid update payload.'];
                } elseif ($active === 0 && $isDefault === 1) {
                    $flash = ['type' => 'error', 'msg' => 'The default tag must stay active.'];
                } else {
                    $pdo->beginTransaction();
                    sort_order_handle_update($pdo, 'emp_tag_options', $id, $sort);
                    $stmt = $pdo->prepare("
                        UPDATE emp_tag_options
                        SET tag_value = :v, sort_order = :s, is_active = :a, is_default = :d
                        WHERE id = :id
                    ");
                    $stmt->execute([':v' => $value, ':s' => $sort, ':a' => $active, ':d' => $isDefault, ':id' => $id]);
                    if ($isDefault === 1) {
                        utilities_emp_tag_set_default($pdo, $id);
                    }
                    $pdo->commit();
                    $flash = ['type' => 'success', 'msg' => 'Employee tag updated.'];
                }
            } elseif ($action === 'emp_tag_delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                } else {
                    $check = $pdo->prepare('SELECT is_default FROM emp_tag_options WHERE id = :id LIMIT 1');
                    $check->execute([':id' => $id]);
                    $row = $check->fetch(PDO::FETCH_ASSOC);
                    if (!$row) {
                        $flash = ['type' => 'error', 'msg' => 'Tag not found.'];
                    } elseif ((int) ($row['is_default'] ?? 0) === 1) {
                        $flash = ['type' => 'error', 'msg' => 'Cannot delete the default tag. Set another tag as default first.'];
                    } else {
                        $stmt = $pdo->prepare('DELETE FROM emp_tag_options WHERE id = :id');
                        $stmt->execute([':id' => $id]);
                        $flash = ['type' => 'success', 'msg' => 'Employee tag deleted.'];
                    }
                }
            } elseif ($action === 'emp_tag_fill_empty') {
                $updated = utilities_emp_tag_fill_empty($pdo);
                $flash = ['type' => 'success', 'msg' => "Updated {$updated} user(s) with empty emp_tag to \"{$emp_tag_default}\"."];
            } else {
                $flash = ['type' => 'error', 'msg' => 'Unknown action.'];
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                $flash = ['type' => 'warning', 'msg' => 'That UACS mapping already exists for this scope.'];
            } else {
                $flash = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
            }
        }
    }
}

$all_uacs_rows = utilities_uacs_fetch_all($pdo);
$list_uacs_type_options = utilities_uacs_type_options_from_rows($all_uacs_rows, $voucher_types);
$list_filter = utilities_list_filter_voucher_type_params($list_uacs_type_options);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postQ = trim((string) ($_POST['list_q'] ?? ''));
    $list_filter['q'] = $postQ;
    if (array_key_exists('list_voucher_type', $_POST)) {
        $postType = trim((string) $_POST['list_voucher_type']);
        if ($postType !== '' && isset($list_uacs_type_options[$postType])) {
            $list_filter['voucher_type'] = $postType;
        } else {
            $list_filter['voucher_type'] = '';
        }
    }
}
$list_filter['is_filtered'] = ($list_filter['q'] !== '' || $list_filter['voucher_type'] !== '');
$filter_voucher_type = (string) ($list_filter['voucher_type'] ?? '');
$form_list_voucher_type = $filter_voucher_type;
$form_list_q = (string) ($list_filter['q'] ?? '');
$add_form_voucher_type = $filter_voucher_type !== '' ? $filter_voucher_type : $selected_voucher_type;
$next_uacs_sort = sort_order_next_position($pdo, 'uacs_code_options', [
    'voucher_type' => $add_form_voucher_type,
]);
$next_emp_tag_sort = sort_order_next_position($pdo, 'emp_tag_options');

$filtered_uacs_rows = utilities_uacs_filter_rows(
    $all_uacs_rows,
    $list_filter['q'],
    $filter_voucher_type,
    ['tag_name', 'account_title', 'uacs_code', 'voucher_type']
);
$uacs_grouped = utilities_uacs_group_rows($filtered_uacs_rows);
$all_uacs_grouped = utilities_uacs_group_rows($all_uacs_rows);
if ($filter_voucher_type !== '' && utilities_uacs_is_salary_voucher_type($filter_voucher_type)) {
    $uacs_grouped = utilities_uacs_merge_emp_tag_scopes($pdo, $uacs_grouped, $known_tag_names, $filter_voucher_type);
}
$scope_total = utilities_uacs_count_scopes($all_uacs_grouped);
$scope_visible = utilities_uacs_count_scopes($uacs_grouped);
$list_total = $scope_total;
$list_visible = $scope_visible;
$active_type_label = $filter_voucher_type !== ''
    ? ($voucher_types[$filter_voucher_type] ?? $filter_voucher_type)
    : 'All types';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_tag_options = utilities_emp_tag_fetch_all($pdo);
    $known_tag_names = [];
    foreach ($emp_tag_options as $tagRow) {
        $name = utilities_uacs_normalize_tag_name((string) ($tagRow['tag_value'] ?? ''));
        if ($name !== '') {
            $known_tag_names[$name] = $name;
        }
    }
}
?>

<style>
    .uacs-page {
        --util-border: #e2e8f0;
        --util-text: #0f172a;
        --util-muted: #64748b;
        --util-accent: #4f46e5;
        --util-radius: 14px;
        --util-shadow: 0 1px 3px rgba(15, 23, 42, .06);
        --util-pad-x: 1.375rem;
        --util-pad-y: 1.125rem;
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
    }
    .uacs-page .voucher-dashboard-header { margin-bottom: 1.5rem; }
    .uacs-page .voucher-dashboard-title { font-size: 1.75rem; font-weight: 800; color: var(--util-text); }
    .uacs-page .voucher-card { background: #fff; border: 1px solid var(--util-border); border-radius: var(--util-radius); box-shadow: var(--util-shadow); margin-bottom: 1.25rem; max-width: 100%; box-sizing: border-box; }
    .uacs-page .voucher-card-title { font-size: 1.125rem; font-weight: 700; padding: 1rem 1.25rem; margin: 0; border-bottom: 1px solid var(--util-border); background: linear-gradient(180deg, #fff 0%, #f8fafc 100%); display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .uacs-page .voucher-card-title__label { display: inline-flex; align-items: center; gap: 0.5rem; }
    .uacs-page .voucher-card-title .ri-icon { font-size: 1.25rem; color: var(--util-accent); }
    .uacs-page .content-wrapper { padding: var(--util-pad-y) var(--util-pad-x); max-width: 100%; box-sizing: border-box; }
    .util-alert { padding: 0.875rem 1rem; border-radius: 10px; margin-bottom: 1.25rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; border: 1px solid transparent; }
    .util-alert.success { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .util-alert.error { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
    .util-alert.warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }
    .util-desc, .util-section-title { color: var(--util-muted); font-size: 0.8125rem; line-height: 1.5; }
    .util-section-title { font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin: 1.5rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--util-border); }
    .util-empty { color: var(--util-muted); font-size: 0.8125rem; padding: 1rem; text-align: center; }
    .uacs-stat { font-size: 0.8125rem; color: var(--util-muted); font-weight: 500; }
    .util-emp-tag-block { border: 1px solid var(--util-border); border-radius: 12px; margin-bottom: 1rem; background: #fff; max-width: 100%; box-sizing: border-box; }
    .util-emp-tag-block__main { padding: var(--util-pad-y) var(--util-pad-x); background: linear-gradient(180deg, #fafafa 0%, #f8fafc 100%); border-bottom: 1px solid var(--util-border); }
    .util-emp-tag-block__subs { padding: var(--util-pad-y) var(--util-pad-x); background: #fafbfc; }
    .util-emp-tag-block__subs-title { margin: 0 0 0.75rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--util-muted); overflow-wrap: anywhere; }
    .util-add-group { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.75rem; margin-bottom: 1rem; }
    .util-add { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; flex: 1; min-width: min(100%, 520px); }
    .util-add .field { flex: 1; min-width: 140px; }
    .util-add .field.util-uacs-field { max-width: 160px; }
    .util-inline { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
    .util-inline .form-custom-input { border-radius: 8px; border: 1px solid var(--util-border); padding: 0.4rem 0.6rem; font-size: 0.8125rem; }
    .util-inline input[type="number"] { width: 72px; }
    .util-row-actions { display: flex; gap: 0.5rem; align-items: center; flex-shrink: 0; }
    .uacs-field-hint { font-weight: 400; color: var(--util-muted); }
    .uacs-primary-row { display: contents; }
    .uacs-primary-row__form { display: contents; }
    .uacs-primary-row__delete { display: contents; }
    .utl-subuacs,
    .utl-subuacs--primary {
        margin-top: 0.5rem;
        width: 100%;
        min-width: 0;
        --utl-subuacs-cols: minmax(180px, 2fr) 118px 58px minmax(130px, 1fr) 88px;
    }
    .utl-subuacs-wrap--primary { padding-top: 0; }
    .util-emp-tag-block__subs { min-height: 10rem; }
    .utl-subuacs__row {
        display: grid;
        grid-template-columns: var(--utl-subuacs-cols);
        gap: 0.5rem 0.75rem;
        align-items: center;
        padding: 0.625rem 0.125rem;
        border-bottom: 1px solid #f1f5f9;
        box-sizing: border-box;
        min-height: 2.75rem;
    }
    .uacs-page .btn {
        width: auto;
        min-width: 4.5rem;
        padding: 0.5rem 0.875rem;
        white-space: nowrap;
        box-sizing: border-box;
    }
    .uacs-page .btn.btn-flex { width: fit-content; }
    .uacs-page .form-custom-input { border-radius: 8px; border: 1px solid var(--util-border); padding: 0.5rem 0.75rem; font-size: 0.875rem; width: 100%; box-sizing: border-box; }
    .uacs-uacs-input { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .uacs-page .chk { display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; color: var(--util-muted); }
    .utl-subuacs-wrap {
        margin: 0;
        padding: 0.125rem 0 0.25rem;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .utl-subuacs__row--head { padding: 0 0 0.5rem; border-bottom: 1px solid var(--util-border); font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--util-muted); }
    .utl-subuacs__row form { display: contents; }
    .utl-subuacs__cell .form-custom-input { width: 100%; box-sizing: border-box; border-radius: 8px; border: 1px solid var(--util-border); padding: 0.4rem 0.6rem; font-size: 0.8125rem; }
    .utl-subuacs__cell .form-custom-input[readonly] { background: #f8fafc; color: var(--util-muted); cursor: default; }
    .utl-subuacs__cell--actions { display: flex; gap: 0.5rem; justify-content: flex-end; flex-shrink: 0; }
    .utl-subuacs__cell .chk { white-space: nowrap; }
    .utl-subuacs__cell--active { display: flex; align-items: center; gap: 0.5rem; flex-wrap: nowrap; min-width: 0; }
    .utl-subuacs__cell--active .btn { flex-shrink: 0; }
    .utl-subuacs__empty { grid-column: 1 / -1; color: var(--util-muted); font-size: 0.8125rem; padding: 1rem; text-align: center; }
    .utl-btn-add { width: 2.5rem; height: 2.5rem; min-width: 2.5rem; padding: 0; border-radius: 8px; font-weight: 700; font-size: 1.375rem; line-height: 1; display: inline-flex; align-items: center; justify-content: center; }
</style>
<?php require __DIR__ . '/partials/list_filter_styles.php'; ?>

<div class="main main--dashboard uacs-page" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">UACS Codes</h1>
    </header>

    <?php
    $list_filter_mode = 'voucher_type';
    $list_voucher_types = $list_uacs_type_options;
    $list_placeholder = 'Search voucher type, tag, UACS, or account title';
    $list_form_id = 'uacsListFilterForm';
    require __DIR__ . '/partials/list_filter_toolbar.php';
    ?>

    <?php if ($flash): ?>
        <div class="util-alert <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
            <?= $flash['type'] === 'success' ? '<i class="ri-checkbox-circle-fill"></i>' : ($flash['type'] === 'error' ? '<i class="ri-error-warning-fill"></i>' : '<i class="ri-information-fill"></i>') ?>
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <div class="voucher-card">
        <h2 class="voucher-card-title">
            <span class="voucher-card-title__label">
                <i class="ri-barcode-box-line ri-icon"></i>
                UACS Mappings — <?= htmlspecialchars($active_type_label, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="uacs-stat"><?= (int) $scope_visible ?> scope<?= $scope_visible === 1 ? '' : 's' ?></span>
        </h2>
        <div class="content-wrapper">
            <p class="util-desc">
                Map 10-digit UACS codes by <strong>voucher type</strong>. Leave tag blank for a type-wide default, or pick an
                <strong>employee tag</strong> to scope the mapping to that tag on the same voucher type. Sub UACS rows
                (Pag-ibig, PhilHealth, cash, etc.) attach under each scope.
            </p>

            <form method="post" class="util-add" style="margin-bottom: 1rem;">
                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                <input type="hidden" name="action" value="uacs_add">
                <input type="hidden" name="list_voucher_type" value="<?= htmlspecialchars($form_list_voucher_type, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="list_q" value="<?= htmlspecialchars($form_list_q, ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label for="add_voucher_type">Voucher type</label>
                    <select class="form-custom-input" name="voucher_type" id="add_voucher_type" required>
                        <?php foreach ($voucher_types as $typeValue => $typeLabel): ?>
                            <option value="<?= htmlspecialchars((string) $typeValue, ENT_QUOTES, 'UTF-8') ?>"<?= $add_form_voucher_type === (string) $typeValue ? ' selected' : '' ?>>
                                <?= htmlspecialchars((string) $typeLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="add_tag_name">Tag name <span style="font-weight:400;">(optional)</span></label>
                    <select class="form-custom-input" name="tag_name" id="add_tag_name">
                        <option value="">— Voucher type only —</option>
                        <?php foreach ($known_tag_names as $tagName): ?>
                            <option value="<?= htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" id="add_account_title_field">
                    <label for="add_account_title">Account title <span class="uacs-field-hint" id="add_account_title_hint">(type-only)</span></label>
                    <input class="form-custom-input" type="text" name="account_title" id="add_account_title" placeholder="e.g., Construction in Progress">
                </div>
                <div class="field util-uacs-field">
                    <label for="add_uacs_code">UACS code</label>
                    <input class="form-custom-input uacs-uacs-input" type="text" name="uacs_code" id="add_uacs_code" placeholder="5021202000" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required>
                </div>
                <div class="field" style="max-width:110px;">
                    <label for="add_sort_order">Sort</label>
                    <input class="form-custom-input" type="number" id="add_sort_order" value="<?= (int) $next_uacs_sort ?>" readonly title="Assigned automatically on add">
                </div>
                <button class="btn primary utl-btn-add" type="submit" title="Add UACS mapping">+</button>
            </form>

            <?php
            $blocks_visible = 0;
            foreach ($uacs_grouped as $typeKey => $tagGroups):
                $typeLabel = $voucher_types[$typeKey] ?? $typeKey;
                foreach ($tagGroups as $tagKey => $group):
                    $hasScopeData = $group['primary'] || $group['subs'];
                    if (!$hasScopeData && $list_filter['q'] !== '') {
                        continue;
                    }
                    if (!$hasScopeData && $list_filter['is_filtered'] && !utilities_uacs_is_salary_voucher_type($filter_voucher_type)) {
                        continue;
                    }
                    $blocks_visible++;
                    $blockVoucherType = (string) $typeKey;
                    $blockTypeLabel = (string) $typeLabel;
                    $blockTagName = (string) $tagKey;
                    $primaryRow = $group['primary'];
                    $subRows = $group['subs'];
                    require __DIR__ . '/partials/uacs_scope_block.php';
                endforeach;
            endforeach;
            ?>
            <?php if ($blocks_visible === 0): ?>
                <p class="util-empty"><?= ($list_filter['is_filtered'] || $list_filter['q'] !== '') ? 'No UACS mappings match your filter.' : 'No UACS mappings yet. Add one above.' ?></p>
            <?php endif; ?>

            <h3 class="util-section-title">Employee Tags</h3>
            <p class="util-desc">
                Tags appear in User Management. UACS codes are configured above per voucher type and optional tag.
                Default tag: <strong><?= htmlspecialchars($emp_tag_default, ENT_QUOTES, 'UTF-8') ?></strong>.
            </p>

            <div class="util-add-group">
                <form method="post" class="util-add">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                    <input type="hidden" name="action" value="emp_tag_add">
                    <input type="hidden" name="list_voucher_type" value="<?= htmlspecialchars($form_list_voucher_type, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="list_q" value="<?= htmlspecialchars($form_list_q, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field">
                        <label>Tag name</label>
                        <input class="form-custom-input" type="text" name="tag_value" placeholder="e.g., Janitorial Services" required>
                    </div>
                    <div class="field" style="max-width:110px;">
                        <label>Sort</label>
                        <input class="form-custom-input" type="number" value="<?= (int) $next_emp_tag_sort ?>" readonly title="Assigned automatically on add">
                    </div>
                    <label class="chk">
                        <input type="checkbox" name="is_default">
                        <span>Default</span>
                    </label>
                    <button class="btn primary utl-btn-add" type="submit" title="Add tag">+</button>
                </form>
                <form method="post" onsubmit="return confirm('Set empty emp_tag values to the default tag for all users?');">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                    <input type="hidden" name="action" value="emp_tag_fill_empty">
                    <input type="hidden" name="list_voucher_type" value="<?= htmlspecialchars($form_list_voucher_type, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="list_q" value="<?= htmlspecialchars($form_list_q, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn secondary" type="submit">Fill default</button>
                </form>
            </div>

            <?php foreach ($emp_tag_options as $r):
                $tagId = (int) ($r['id'] ?? 0);
            ?>
                <div class="util-inline" style="margin-bottom:0.75rem;">
                    <form method="post" class="util-inline" style="flex:1; min-width:0;">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <input type="hidden" name="action" value="emp_tag_update">
                        <input type="hidden" name="list_voucher_type" value="<?= htmlspecialchars($form_list_voucher_type, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="list_q" value="<?= htmlspecialchars($form_list_q, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= $tagId ?>">
                        <input class="form-custom-input" type="text" name="tag_value" value="<?= htmlspecialchars((string) $r['tag_value'], ENT_QUOTES, 'UTF-8') ?>" required>
                        <input class="form-custom-input" type="number" name="sort_order" value="<?= (int) $r['sort_order'] ?>">
                        <label class="chk">
                            <input type="checkbox" name="is_active" <?= ((int) $r['is_active'] === 1) ? 'checked' : '' ?>>
                            <span>Active</span>
                        </label>
                        <label class="chk">
                            <input type="checkbox" name="is_default" <?= ((int) $r['is_default'] === 1) ? 'checked' : '' ?>>
                            <span>Default</span>
                        </label>
                        <button class="btn success" type="submit">Save</button>
                    </form>
                    <?php if ((int) ($r['is_default'] ?? 0) !== 1): ?>
                        <form method="post" onsubmit="return confirm('Delete this employee tag?');" class="util-row-actions">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                            <input type="hidden" name="action" value="emp_tag_delete">
                            <input type="hidden" name="list_voucher_type" value="<?= htmlspecialchars($form_list_voucher_type, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="list_q" value="<?= htmlspecialchars($form_list_q, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="id" value="<?= $tagId ?>">
                            <button class="btn danger" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var tagSelect = document.getElementById('add_tag_name');
    var titleInput = document.getElementById('add_account_title');
    var titleField = document.getElementById('add_account_title_field');
    var titleHint = document.getElementById('add_account_title_hint');
    if (!tagSelect || !titleInput || !titleField) {
        return;
    }
    function syncAddAccountTitleField() {
        var typeOnly = tagSelect.value === '';
        titleInput.required = typeOnly;
        titleField.style.display = typeOnly ? '' : 'none';
        if (!typeOnly) {
            titleInput.value = '';
        }
        if (titleHint) {
            titleHint.textContent = typeOnly ? '(required for type-only)' : '';
        }
    }
    tagSelect.addEventListener('change', syncAddAccountTitleField);
    syncAddAccountTitleField();
})();
</script>
<script src="../../protected/js/main.js"></script>
</body>
</html>
