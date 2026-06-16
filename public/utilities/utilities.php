<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_emp_tag_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/sort_order_helper.inc.php';
AuditHelper::logPageView('Utilities');

// Utilities: System Admin with ACL >= 999
$target = explode(",", $_SESSION['logged_user_designation'] ?? '');
if (!AccessControl::canAccessSystemUtilities()) {
    echo "<script>process_functionAlert('Access denied!', 'dashboard_redirect')</script>";
    die();
}

// Ensure signatory tables support per-office configuration.
utilities_signatory_ensure_schema($pdo);
utilities_emp_tag_ensure_schema($pdo);

const ADA_OPT_CERTIFIED = 'certified_correct';
const ADA_OPT_APPROVED = 'approved_by';
const ADA_OPT_SIGNATORY = 'agency_authorized_signatory';

function ada_opt_label(string $type): string
{
    return match ($type) {
        ADA_OPT_CERTIFIED => 'Certified Correct',
        ADA_OPT_APPROVED => 'Approved By',
        ADA_OPT_SIGNATORY => 'Agency Authorized Signatory',
        default => $type,
    };
}

function normalize_opt_value(string $v): string
{
    $v = trim(preg_replace('/\s+/', ' ', $v));
    return $v;
}

// Handle CRUD
$flash = null;
$available_offices = utilities_signatory_fetch_offices($pdo);
$selected_office = utilities_signatory_resolve_office(
    $pdo,
    $_POST['office'] ?? $_GET['office'] ?? null
);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk = isset($_POST['token'], $_SESSION['token']) && hash_equals((string)$_SESSION['token'], (string)$_POST['token']);
    if (!$tokenOk) {
        $flash = ['type' => 'error', 'msg' => 'Invalid token. Please refresh and try again.'];
    } else {
        $action = $_POST['action'] ?? '';
        $type = $_POST['option_type'] ?? '';
        $scope = $_POST['scope'] ?? 'ada'; // 'ada', 'dv', or 'emp_tag'
        $formOffice = utilities_signatory_resolve_office($pdo, $_POST['office'] ?? $selected_office);
        $selected_office = $formOffice;
        $allowedTypes = [ADA_OPT_CERTIFIED, ADA_OPT_APPROVED, ADA_OPT_SIGNATORY];
        $allowedDvKeys = [
            'dv_certified_msd',
            'dv_certified_tsd',
            'dv_accounting_certified',
            'dv_approved_for_payment',
        ];
        if ($scope === 'ada' && !in_array($type, $allowedTypes, true)) {
            $flash = ['type' => 'error', 'msg' => 'Invalid option type.'];
        } elseif ($scope === 'dv' && !in_array($type, $allowedDvKeys, true)) {
            $flash = ['type' => 'error', 'msg' => 'Invalid DV signatory key.'];
        } else {
            try {
                if ($scope === 'ada' && $action === 'add') {
                    $value = normalize_opt_value((string)($_POST['option_value'] ?? ''));
                    $sort = (int)($_POST['sort_order'] ?? 0);
                    $isDefault = isset($_POST['is_default']) ? 1 : 0;
                    if ($value === '') {
                        $flash = ['type' => 'error', 'msg' => 'Value is required.'];
                    } else {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("
                            INSERT INTO ada_signatory_options (option_type, office, option_value, sort_order, is_active, is_default)
                            VALUES (:t, :office, :v, 0, 1, :d)
                        ");
                        $stmt->execute([':t' => $type, ':office' => $formOffice, ':v' => $value, ':d' => $isDefault]);
                        $newId = (int) $pdo->lastInsertId();
                        sort_order_place_at_position($pdo, 'ada_signatory_options', $newId, $sort, [
                            'option_type' => $type,
                            'office' => $formOffice,
                        ]);
                        if ($isDefault === 1) {
                            utilities_ada_signatory_set_default($pdo, $newId, $type, $formOffice);
                        }
                        $pdo->commit();
                        $flash = ['type' => 'success', 'msg' => 'Added successfully.'];
                    }
                } elseif ($scope === 'ada' && $action === 'update') {
                    $id = (int)($_POST['id'] ?? 0);
                    $value = normalize_opt_value((string)($_POST['option_value'] ?? ''));
                    $sort = (int)($_POST['sort_order'] ?? 0);
                    $active = isset($_POST['is_active']) ? 1 : 0;
                    $isDefault = isset($_POST['is_default']) ? 1 : 0;
                    if ($id <= 0 || $value === '') {
                        $flash = ['type' => 'error', 'msg' => 'Invalid update payload.'];
                    } elseif ($active === 0 && $isDefault === 1) {
                        $flash = ['type' => 'error', 'msg' => 'The default option must stay active.'];
                    } else {
                        $pdo->beginTransaction();
                        sort_order_handle_update($pdo, 'ada_signatory_options', $id, $sort, [
                            'option_type' => $type,
                            'office' => $formOffice,
                        ]);
                        $stmt = $pdo->prepare("
                            UPDATE ada_signatory_options
                            SET option_value = :v, sort_order = :s, is_active = :a, is_default = :d
                            WHERE id = :id AND option_type = :t AND office = :office
                        ");
                        $stmt->execute([':v' => $value, ':s' => $sort, ':a' => $active, ':d' => $isDefault, ':id' => $id, ':t' => $type, ':office' => $formOffice]);
                        if ($isDefault === 1) {
                            utilities_ada_signatory_set_default($pdo, $id, $type, $formOffice);
                        }
                        $pdo->commit();
                        $flash = ['type' => 'success', 'msg' => 'Updated successfully.'];
                    }
                } elseif ($scope === 'ada' && $action === 'delete') {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                    } else {
                        $check = $pdo->prepare('SELECT is_default FROM ada_signatory_options WHERE id = :id AND option_type = :t AND office = :office LIMIT 1');
                        $check->execute([':id' => $id, ':t' => $type, ':office' => $formOffice]);
                        $row = $check->fetch(PDO::FETCH_ASSOC);
                        if (!$row) {
                            $flash = ['type' => 'error', 'msg' => 'Option not found.'];
                        } elseif ((int)($row['is_default'] ?? 0) === 1) {
                            $flash = ['type' => 'error', 'msg' => 'Cannot delete the default option. Set another option as default first.'];
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM ada_signatory_options WHERE id = :id AND option_type = :t AND office = :office");
                            $stmt->execute([':id' => $id, ':t' => $type, ':office' => $formOffice]);
                            $flash = ['type' => 'success', 'msg' => 'Deleted successfully.'];
                        }
                    }
                } elseif ($scope === 'emp_tag' && $action === 'emp_tag_add') {
                    $value = utilities_emp_tag_normalize_value((string)($_POST['tag_value'] ?? ''));
                    $uacsCheck = utilities_emp_tag_validate_uacs((string)($_POST['uacs_code'] ?? ''), false);
                    $sort = (int)($_POST['sort_order'] ?? 0);
                    $isDefault = isset($_POST['is_default']) ? 1 : 0;
                    if ($value === '') {
                        $flash = ['type' => 'error', 'msg' => 'Tag value is required.'];
                    } elseif (!$uacsCheck['ok']) {
                        $flash = ['type' => 'error', 'msg' => $uacsCheck['error']];
                    } else {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("
                            INSERT INTO emp_tag_options (tag_value, uacs_code, sort_order, is_active, is_default)
                            VALUES (:v, :uacs, 0, 1, :d)
                        ");
                        $stmt->execute([':v' => $value, ':uacs' => $uacsCheck['uacs'], ':d' => $isDefault]);
                        $newTagId = (int) $pdo->lastInsertId();
                        sort_order_place_at_position($pdo, 'emp_tag_options', $newTagId, $sort);
                        utilities_emp_tag_seed_sub_uacs($pdo, $newTagId);
                        if ($isDefault === 1) {
                            utilities_emp_tag_set_default($pdo, $newTagId);
                        }
                        $pdo->commit();
                        $flash = ['type' => 'success', 'msg' => 'Employee tag added.'];
                    }
                } elseif ($scope === 'emp_tag' && $action === 'emp_tag_update') {
                    $id = (int)($_POST['id'] ?? 0);
                    $value = utilities_emp_tag_normalize_value((string)($_POST['tag_value'] ?? ''));
                    $uacsCheck = utilities_emp_tag_validate_uacs((string)($_POST['uacs_code'] ?? ''), false);
                    $sort = (int)($_POST['sort_order'] ?? 0);
                    $active = isset($_POST['is_active']) ? 1 : 0;
                    $isDefault = isset($_POST['is_default']) ? 1 : 0;
                    if ($id <= 0 || $value === '') {
                        $flash = ['type' => 'error', 'msg' => 'Invalid update payload.'];
                    } elseif (!$uacsCheck['ok']) {
                        $flash = ['type' => 'error', 'msg' => $uacsCheck['error']];
                    } elseif ($active === 0 && $isDefault === 1) {
                        $flash = ['type' => 'error', 'msg' => 'The default tag must stay active.'];
                    } else {
                        $pdo->beginTransaction();
                        sort_order_handle_update($pdo, 'emp_tag_options', $id, $sort);
                        $stmt = $pdo->prepare("
                            UPDATE emp_tag_options
                            SET tag_value = :v, uacs_code = :uacs, sort_order = :s, is_active = :a, is_default = :d
                            WHERE id = :id
                        ");
                        $stmt->execute([':v' => $value, ':uacs' => $uacsCheck['uacs'], ':s' => $sort, ':a' => $active, ':d' => $isDefault, ':id' => $id]);
                        if ($isDefault === 1) {
                            utilities_emp_tag_set_default($pdo, $id);
                        }
                        $pdo->commit();
                        $flash = ['type' => 'success', 'msg' => 'Employee tag updated.'];
                    }
                } elseif ($scope === 'emp_tag' && $action === 'emp_tag_delete') {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                    } else {
                        $check = $pdo->prepare('SELECT is_default FROM emp_tag_options WHERE id = :id LIMIT 1');
                        $check->execute([':id' => $id]);
                        $row = $check->fetch(PDO::FETCH_ASSOC);
                        if (!$row) {
                            $flash = ['type' => 'error', 'msg' => 'Tag not found.'];
                        } elseif ((int)($row['is_default'] ?? 0) === 1) {
                            $flash = ['type' => 'error', 'msg' => 'Cannot delete the default tag. Set another tag as default first.'];
                        } else {
                            $stmt = $pdo->prepare('DELETE FROM emp_tag_options WHERE id = :id');
                            $stmt->execute([':id' => $id]);
                            $flash = ['type' => 'success', 'msg' => 'Employee tag deleted.'];
                        }
                    }
                } elseif ($scope === 'emp_tag' && $action === 'emp_tag_fill_empty') {
                    $updated = utilities_emp_tag_fill_empty($pdo);
                    $defaultTag = utilities_emp_tag_default_value($pdo);
                    $flash = ['type' => 'success', 'msg' => "Updated {$updated} user(s) with empty emp_tag to \"{$defaultTag}\"."];
                } elseif ($scope === 'emp_tag' && $action === 'emp_tag_sub_add') {
                    $tagId = (int)($_POST['emp_tag_id'] ?? 0);
                    $title = utilities_emp_tag_normalize_value((string)($_POST['account_title'] ?? ''));
                    $uacsCheck = utilities_emp_tag_validate_uacs((string)($_POST['uacs_code'] ?? ''), false);
                    $sort = (int)($_POST['sort_order'] ?? 0);
                    if (!utilities_emp_tag_sub_uacs_tag_exists($pdo, $tagId)) {
                        $flash = ['type' => 'error', 'msg' => 'Employee tag not found.'];
                    } elseif ($title === '') {
                        $flash = ['type' => 'error', 'msg' => 'Account title is required.'];
                    } elseif (!$uacsCheck['ok']) {
                        $flash = ['type' => 'error', 'msg' => $uacsCheck['error']];
                    } else {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("
                            INSERT INTO emp_tag_sub_uacs (emp_tag_id, account_title, uacs_code, sort_order, is_active)
                            VALUES (:tag_id, :title, :uacs, 0, 1)
                        ");
                        $stmt->execute([':tag_id' => $tagId, ':title' => $title, ':uacs' => $uacsCheck['uacs']]);
                        sort_order_place_at_position(
                            $pdo,
                            'emp_tag_sub_uacs',
                            (int) $pdo->lastInsertId(),
                            $sort,
                            ['emp_tag_id' => $tagId]
                        );
                        $pdo->commit();
                        $flash = ['type' => 'success', 'msg' => 'Sub UACS added.'];
                    }
                } elseif ($scope === 'emp_tag' && $action === 'emp_tag_sub_update') {
                    $id = (int)($_POST['id'] ?? 0);
                    $tagId = (int)($_POST['emp_tag_id'] ?? 0);
                    $title = utilities_emp_tag_normalize_value((string)($_POST['account_title'] ?? ''));
                    $uacsCheck = utilities_emp_tag_validate_uacs((string)($_POST['uacs_code'] ?? ''), false);
                    $sort = (int)($_POST['sort_order'] ?? 0);
                    $active = isset($_POST['is_active']) ? 1 : 0;
                    if ($id <= 0 || $tagId <= 0 || $title === '') {
                        $flash = ['type' => 'error', 'msg' => 'Invalid sub UACS update payload.'];
                    } elseif (!$uacsCheck['ok']) {
                        $flash = ['type' => 'error', 'msg' => $uacsCheck['error']];
                    } else {
                        $pdo->beginTransaction();
                        sort_order_handle_update($pdo, 'emp_tag_sub_uacs', $id, $sort, ['emp_tag_id' => $tagId]);
                        $stmt = $pdo->prepare("
                            UPDATE emp_tag_sub_uacs
                            SET account_title = :title, uacs_code = :uacs, sort_order = :s, is_active = :a
                            WHERE id = :id AND emp_tag_id = :tag_id
                        ");
                        $stmt->execute([
                            ':title' => $title,
                            ':uacs' => $uacsCheck['uacs'],
                            ':s' => $sort,
                            ':a' => $active,
                            ':id' => $id,
                            ':tag_id' => $tagId,
                        ]);
                        $pdo->commit();
                        $flash = ['type' => 'success', 'msg' => 'Sub UACS updated.'];
                    }
                } elseif ($scope === 'emp_tag' && $action === 'emp_tag_sub_delete') {
                    $id = (int)($_POST['id'] ?? 0);
                    $tagId = (int)($_POST['emp_tag_id'] ?? 0);
                    if ($id <= 0 || $tagId <= 0) {
                        $flash = ['type' => 'error', 'msg' => 'Invalid sub UACS delete payload.'];
                    } else {
                        $stmt = $pdo->prepare('DELETE FROM emp_tag_sub_uacs WHERE id = :id AND emp_tag_id = :tag_id');
                        $stmt->execute([':id' => $id, ':tag_id' => $tagId]);
                        $flash = ['type' => 'success', 'msg' => 'Sub UACS deleted.'];
                    }
                } elseif ($scope === 'dv' && $action === 'dv_upsert') {
                    $name = normalize_opt_value((string)($_POST['display_name'] ?? ''));
                    $pos1 = normalize_opt_value((string)($_POST['position_line1'] ?? ''));
                    $pos2 = normalize_opt_value((string)($_POST['position_line2'] ?? ''));
                    $active = isset($_POST['is_active']) ? 1 : 0;
                    if ($name === '') {
                        $flash = ['type' => 'error', 'msg' => 'Name is required.'];
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO voucher_signatories (signatory_key, office, display_name, position_line1, position_line2, is_active)
                            VALUES (:k, :office, :n, :p1, :p2, :a)
                            ON DUPLICATE KEY UPDATE
                                display_name = VALUES(display_name),
                                position_line1 = VALUES(position_line1),
                                position_line2 = VALUES(position_line2),
                                is_active = VALUES(is_active)
                        ");
                        $stmt->execute([':k' => $type, ':office' => $formOffice, ':n' => $name, ':p1' => $pos1, ':p2' => $pos2, ':a' => $active]);
                        $flash = ['type' => 'success', 'msg' => 'DV signatory saved.'];
                    }
                } else {
                    $flash = ['type' => 'error', 'msg' => 'Unknown action.'];
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Duplicate key => already exists
                if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                    $flash = ['type' => 'warning', 'msg' => 'That value already exists.'];
                } else {
                    $flash = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
                }
            }
        }
    }
}

function fetch_opts(PDO $pdo, string $type, string $office): array
{
    $stmt = $pdo->prepare("
        SELECT id, option_type, option_value, is_active, is_default, sort_order
        FROM ada_signatory_options
        WHERE option_type = :t
          AND office = :office
        ORDER BY sort_order ASC, option_value ASC
    ");
    $stmt->execute([':t' => $type, ':office' => $office]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$opts_certified = fetch_opts($pdo, ADA_OPT_CERTIFIED, $selected_office);
$opts_approved = fetch_opts($pdo, ADA_OPT_APPROVED, $selected_office);
$opts_signatory = fetch_opts($pdo, ADA_OPT_SIGNATORY, $selected_office);
$ada_option_defaults = utilities_fetch_ada_option_defaults($pdo, $selected_office);

// DV signatories (single row per key)
$dv_keys = [
    'dv_certified_msd' => 'DV A. Certified (MSD)',
    'dv_certified_tsd' => 'DV A. Certified (TSD)',
    'dv_accounting_certified' => 'DV C. Certified (Accounting)',
    'dv_approved_for_payment' => 'DV D. Approved for Payment',
];
$dv_signatories = utilities_fetch_dv_signatories($pdo, $selected_office);
$emp_tag_options = utilities_emp_tag_fetch_all($pdo);
$emp_tag_default = utilities_emp_tag_default_value($pdo);
?>

<style>
    /* Utilities — scoped as utl-page (separate from checklist chk-page) */
    .utl-page {
        --util-bg: #f8fafc;
        --util-card: #fff;
        --util-border: #e2e8f0;
        --util-text: #0f172a;
        --util-muted: #64748b;
        --util-accent: #4f46e5;
        --util-accent-hover: #4338ca;
        --util-radius: 14px;
        --util-shadow: 0 1px 3px rgba(15, 23, 42, .06);
        --util-shadow-lg: 0 10px 40px -10px rgba(15, 23, 42, .12);
    }

    .utl-page .voucher-dashboard-header {
        margin-bottom: 1.5rem;
    }

    .utl-page .voucher-dashboard-title {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--util-text);
    }

    .utl-page .voucher-card {
        background: var(--util-card);
        border: 1px solid var(--util-border);
        border-radius: var(--util-radius);
        box-shadow: var(--util-shadow);
        overflow: hidden;
    }

    .utl-page .voucher-card-title {
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

    .utl-page .voucher-card-title__label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .utl-page .voucher-card-title .ri-icon {
        font-size: 1.25rem;
        color: var(--util-accent);
    }

    .utl-page .content-wrapper {
        padding: 1.25rem;
    }

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

    .util-alert.success {
        background: #ecfdf5;
        color: #065f46;
        border-color: #a7f3d0;
    }

    .util-alert.error {
        background: #fef2f2;
        color: #991b1b;
        border-color: #fecaca;
    }

    .util-alert.warning {
        background: #fffbeb;
        color: #92400e;
        border-color: #fde68a;
    }

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

    .util-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(260px, 1fr));
        gap: 1.25rem;
    }

    .util-card {
        background: var(--util-card);
        border: 1px solid var(--util-border);
        border-radius: var(--util-radius);
        overflow: hidden;
        box-shadow: var(--util-shadow);
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .util-card:hover {
        box-shadow: var(--util-shadow-lg);
        border-color: #cbd5e1;
    }

    .util-card__head {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--util-border);
        background: linear-gradient(180deg, #fafafa 0%, #f1f5f9 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .util-card__head h3 {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--util-text);
        letter-spacing: -0.01em;
    }

    .util-card__body {
        padding: 1.25rem;
    }

    .util-add {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: flex-end;
        margin-bottom: 1rem;
    }

    .util-add .field {
        flex: 1;
        min-width: 160px;
    }

    .util-add label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--util-muted);
        margin-bottom: 0.375rem;
        letter-spacing: 0.02em;
    }

    .util-add .form-custom-input {
        border-radius: 8px;
        border: 1px solid var(--util-border);
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .util-add .form-custom-input:focus {
        outline: none;
        border-color: var(--util-accent);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, .15);
    }

    .util-add .btn.primary {
        border-radius: 8px;
        font-weight: 600;
        padding: 0.5rem 1rem;
        font-size: 0.8125rem;
    }

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

    .util-table tbody tr {
        transition: background 0.15s;
    }

    .util-table tbody tr:hover {
        background: #f8fafc;
    }

    .util-row-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        align-items: center;
    }

    .util-row-actions form {
        margin: 0;
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

    .util-inline input[type="text"] {
        width: 200px;
    }

    .util-inline input[type="number"] {
        width: 72px;
    }

    .util-inline .chk {
        display: flex;
        gap: 0.375rem;
        align-items: center;
        font-size: 0.8125rem;
        color: var(--util-muted);
    }

    .util-inline .btn.success {
        border-radius: 8px;
        font-size: 0.75rem;
        padding: 0.4rem 0.75rem;
    }

    .util-inline .btn.danger {
        border-radius: 8px;
        font-size: 0.75rem;
        padding: 0.4rem 0.75rem;
    }

    .util-empty {
        color: var(--util-muted);
        font-size: 0.8125rem;
        padding: 1rem;
        text-align: center;
    }

    .util-dv-section {
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--util-border);
    }

    .util-dv-desc {
        margin: 0 0 1.25rem 0;
        color: var(--util-muted);
        font-size: 0.8125rem;
        line-height: 1.5;
    }

    .util-dv-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(280px, 1fr));
        gap: 1rem;
    }

    .util-dv-card {
        background: #fafbfc;
        border: 1px solid var(--util-border);
        border-radius: 12px;
        overflow: hidden;
    }

    .util-dv-card .util-card__head {
        background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
        padding: 0.875rem 1rem;
    }

    .util-dv-card .util-card__head h3 {
        font-size: 0.8125rem;
    }

    .util-dv-card .util-card__body {
        padding: 1rem;
    }

    .util-dv-card .field {
        margin-bottom: 0.75rem;
    }

    .util-dv-card .field:last-of-type {
        margin-bottom: 0;
    }

    .util-dv-card .btn.success {
        border-radius: 8px;
        font-weight: 600;
        margin-top: 0.5rem;
    }

    .utl-page .form-custom-input {
        border-radius: 8px;
        border: 1px solid var(--util-border);
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        transition: border-color 0.15s, box-shadow 0.15s;
        width: 100%;
        box-sizing: border-box;
    }

    .utl-page .form-custom-input:focus {
        outline: none;
        border-color: var(--util-accent);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, .12);
    }

    .utl-page .form-custom-input::placeholder {
        color: #94a3b8;
    }

    .util-office-switch {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        flex-wrap: wrap;
        margin: 0;
        margin-left: auto;
    }

    .util-office-switch label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--util-muted);
        white-space: nowrap;
    }

    .util-office-switch .field {
        min-width: 220px;
        max-width: 320px;
    }

    .util-office-switch .form-custom-input {
        min-height: 36px;
        padding-top: 0.4rem;
        padding-bottom: 0.4rem;
    }

    .util-add-group {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .util-add-group .util-add {
        flex: 1;
        min-width: min(100%, 520px);
        margin-bottom: 0;
    }

    .util-add-group .util-emp-tag-fill {
        margin: 0;
        flex-shrink: 0;
    }

    .util-add .field.util-uacs-field {
        max-width: 160px;
    }

    .util-inline input[type="text"].util-uacs-input,
    .util-add .util-uacs-input {
        width: 130px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        letter-spacing: 0.02em;
    }

    .util-emp-tag-block {
        border: 1px solid var(--util-border);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1rem;
        background: #fff;
    }

    .util-emp-tag-block:last-child {
        margin-bottom: 0;
    }

    .util-emp-tag-block__main {
        padding: 0.875rem 1rem;
        background: linear-gradient(180deg, #fafafa 0%, #f8fafc 100%);
        border-bottom: 1px solid var(--util-border);
    }

    .util-emp-tag-block__subs {
        padding: 1rem;
        background: #fafbfc;
    }

    .util-emp-tag-block__subs-title {
        margin: 0 0 0.75rem 0;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--util-muted);
    }

    .utl-subuacs {
        margin-top: 0.5rem;
        --utl-subuacs-cols: minmax(200px, 1fr) 140px 72px minmax(120px, auto) 100px;
    }

    .utl-subuacs__row {
        display: grid;
        grid-template-columns: var(--utl-subuacs-cols);
        gap: 0.75rem;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .utl-subuacs__row--head {
        padding: 0 0 0.5rem 0;
        border-bottom: 1px solid var(--util-border);
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--util-muted);
    }

    .utl-subuacs__row--head .utl-subuacs__cell--actions {
        text-align: right;
    }

    .utl-subuacs__row form {
        display: contents;
    }

    .utl-subuacs__cell {
        min-width: 0;
    }

    .utl-subuacs__cell--active {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .utl-subuacs__cell--actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        align-items: center;
    }

    .utl-subuacs__cell .form-custom-input {
        width: 100%;
        box-sizing: border-box;
        border-radius: 8px;
        border: 1px solid var(--util-border);
        padding: 0.4rem 0.6rem;
        font-size: 0.8125rem;
    }

    .utl-subuacs__cell .util-uacs-input {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        letter-spacing: 0.02em;
    }

    .utl-subuacs__cell .chk {
        display: flex;
        gap: 0.375rem;
        align-items: center;
        font-size: 0.8125rem;
        color: var(--util-muted);
        white-space: nowrap;
    }

    .utl-subuacs__cell .btn.success,
    .utl-subuacs__cell .btn.danger {
        border-radius: 8px;
        font-size: 0.75rem;
        padding: 0.4rem 0.75rem;
        height: 2.5rem;
        box-sizing: border-box;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .utl-subuacs__add .field {
        margin: 0;
    }

    .utl-subuacs__add label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--util-muted);
        margin-bottom: 0.375rem;
    }

    .utl-subuacs__empty {
        grid-column: 1 / -1;
        color: var(--util-muted);
        font-size: 0.8125rem;
        padding: 1rem;
        text-align: center;
    }

    .utl-add-btn-field {
        flex: 0 0 auto;
        min-width: auto;
        align-self: flex-end;
    }

    .utl-add-btn-field label.utl-field-spacer {
        display: block;
        visibility: hidden;
        height: 0;
        margin: 0 0 0.375rem 0;
        padding: 0;
        overflow: hidden;
        line-height: 0;
    }

    .utl-btn-add {
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

    .util-inline input[type="text"].util-sub-title-input {
        width: min(240px, 100%);
    }

    .util-ada-add {
        flex-wrap: nowrap;
        gap: 0.5rem;
    }

    .util-ada-add .field {
        min-width: 0;
    }

    .util-ada-add .field:first-child {
        flex: 1;
    }

    .util-ada-add .util-ada-sort-field {
        flex: 0 0 64px;
        max-width: 64px;
    }

    .util-ada-add .chk {
        flex-shrink: 0;
        white-space: nowrap;
        margin-bottom: 0;
        align-self: flex-end;
        padding-bottom: 0.4rem;
    }

    .util-ada-table .util-ada-value {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .util-ada-table .util-ada-sort {
        width: 56px;
        box-sizing: border-box;
    }

    .util-ada-table th,
    .util-ada-table td {
        padding: 0.5rem 0.375rem;
        vertical-align: middle;
    }

    .utl-page .util-ada-table .util-ada-sort {
        width: 56px;
        max-width: 56px;
        padding-left: 0.35rem;
        padding-right: 0.35rem;
    }

    .util-ada-table .util-ada-chk {
        white-space: nowrap;
        font-size: 0.75rem;
        margin: 0;
    }

    .util-ada-table .util-row-actions {
        flex-wrap: nowrap;
        white-space: nowrap;
        gap: 0.375rem;
    }

    .util-ada-table .btn.success,
    .util-ada-table .btn.danger {
        padding: 0.35rem 0.5rem;
        font-size: 0.6875rem;
    }

    .util-ada-update-form {
        display: none;
    }

    @media (max-width: 1050px) {
        .util-grid {
            grid-template-columns: 1fr;
        }

        .util-inline input[type="text"] {
            width: 100%;
        }

        .util-dv-grid {
            grid-template-columns: 1fr;
        }

        .util-office-switch {
            width: 100%;
            margin-left: 0;
        }

        .util-office-switch .field {
            flex: 1;
            min-width: 0;
            max-width: none;
        }
    }
</style>

<div class="main main--dashboard utl-page" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Utilities</h1>
    </header>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">
            <span class="voucher-card-title__label">
                <i class="ri-file-list-3-line ri-icon"></i>
                LDDAP-ADA Signatory | Voucher Options
            </span>
            <form method="get" class="util-office-switch" id="utilitiesOfficeForm">
                <label for="utilities_office_select">Office</label>
                <div class="field">
                    <select class="form-custom-input" name="office" id="utilities_office_select" onchange="this.form.submit()">
                        <?php if (!$available_offices): ?>
                            <option value="<?= htmlspecialchars($selected_office, ENT_QUOTES, 'UTF-8') ?>" selected>
                                <?= htmlspecialchars($selected_office, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php else: ?>
                            <?php foreach ($available_offices as $officeName): ?>
                                <option value="<?= htmlspecialchars($officeName, ENT_QUOTES, 'UTF-8') ?>" <?= $officeName === $selected_office ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($officeName, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </form>
        </h2>
        <div class="content-wrapper">
            <?php if ($flash): ?>
                <div class="util-alert <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= $flash['type'] === 'success' ? '<i class="ri-checkbox-circle-fill"></i>' : ($flash['type'] === 'error' ? '<i class="ri-error-warning-fill"></i>' : '<i class="ri-information-fill"></i>') ?>
                    <?= htmlspecialchars($flash['msg']) ?>
                </div>
            <?php endif; ?>

            <p class="util-section-title">Dropdown options for LDDAP-ADA process</p>
            <p class="util-dv-desc">Mark one option per dropdown as default. Defaults are pre-selected when processing vouchers for this office.</p>
            <div class="util-grid">
                <?php
                $sections = [
                    ADA_OPT_CERTIFIED => $opts_certified,
                    ADA_OPT_APPROVED => $opts_approved,
                    ADA_OPT_SIGNATORY => $opts_signatory,
                ];
                foreach ($sections as $type => $rows):
                    $sectionDefault = $ada_option_defaults[$type] ?? '';
                ?>
                    <div class="util-card">
                        <div class="util-card__head">
                            <h3>
                                <?= htmlspecialchars(ada_opt_label($type)) ?>
                                <?php if ($sectionDefault !== ''): ?>
                                    <span style="font-size:0.85rem; font-weight:500; color:var(--util-muted);">— Default: <?= htmlspecialchars($sectionDefault, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="util-card__body">
                            <form method="post" class="util-add util-ada-add">
                                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                <input type="hidden" name="office" value="<?= htmlspecialchars($selected_office, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="option_type" value="<?= htmlspecialchars($type) ?>">
                                <div class="field">
                                    <label>Value</label>
                                    <input class="form-custom-input" type="text" name="option_value" placeholder="e.g., JUAN D. DELA CRUZ" required>
                                </div>
                                <div class="field util-ada-sort-field">
                                    <label>Sort</label>
                                    <input class="form-custom-input" type="number" name="sort_order" value="0">
                                </div>
                                <label class="chk">
                                    <input type="checkbox" name="is_default">
                                    <span>Default</span>
                                </label>
                                <div class="field utl-add-btn-field">
                                    <label class="utl-field-spacer" aria-hidden="true">&nbsp;</label>
                                    <button class="btn primary utl-btn-add" type="submit" title="Add entry" aria-label="Add entry">+</button>
                                </div>
                            </form>

                            <table class="util-table util-ada-table">
                                <thead>
                                    <tr>
                                        <th>Value</th>
                                        <th style="width:56px;">Sort</th>
                                        <th style="width:58px;">Active</th>
                                        <th style="width:62px;">Default</th>
                                        <th style="width:96px; text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$rows): ?>
                                        <tr>
                                            <td colspan="5" class="util-empty">No entries yet. Add one above.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($rows as $r):
                                        $rowId = (int) $r['id'];
                                        $updateFormId = 'ada-opt-update-' . preg_replace('/[^a-z0-9_-]/i', '-', $type) . '-' . $rowId;
                                    ?>
                                        <tr>
                                            <td>
                                                <form method="post" id="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" class="util-ada-update-form">
                                                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                                    <input type="hidden" name="office" value="<?= htmlspecialchars($selected_office, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="option_type" value="<?= htmlspecialchars($type) ?>">
                                                    <input type="hidden" name="id" value="<?= $rowId ?>">
                                                </form>
                                                <input form="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" class="form-custom-input util-ada-value" type="text" name="option_value" value="<?= htmlspecialchars($r['option_value']) ?>" required>
                                            </td>
                                            <td>
                                                <input form="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" class="form-custom-input util-ada-sort" type="number" name="sort_order" value="<?= (int)$r['sort_order'] ?>">
                                            </td>
                                            <td>
                                                <label class="chk util-ada-chk">
                                                    <input form="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" type="checkbox" name="is_active" <?= ((int)$r['is_active'] === 1) ? 'checked' : '' ?>>
                                                    <span>Active</span>
                                                </label>
                                            </td>
                                            <td>
                                                <label class="chk util-ada-chk">
                                                    <input form="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" type="checkbox" name="is_default" <?= ((int)($r['is_default'] ?? 0) === 1) ? 'checked' : '' ?>>
                                                    <span>Default</span>
                                                </label>
                                            </td>
                                            <td>
                                                <div class="util-row-actions">
                                                    <button form="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" class="btn success" type="submit">Save</button>
                                                    <?php if ((int)($r['is_default'] ?? 0) !== 1): ?>
                                                    <form method="post" onsubmit="return confirm('Delete this entry?');">
                                                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                                        <input type="hidden" name="office" value="<?= htmlspecialchars($selected_office, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="option_type" value="<?= htmlspecialchars($type) ?>">
                                                        <input type="hidden" name="id" value="<?= $rowId ?>">
                                                        <button class="btn danger" type="submit">Delete</button>
                                                    </form>
                                                    <?php endif; ?>
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

            <div class="util-dv-section">
                <p class="util-section-title">Disbursement Voucher (DV) printed template</p>
                <p class="util-dv-desc">These values populate the printed DV template for the selected office. One active record per role.</p>
                <div class="util-dv-grid">
                    <?php foreach ($dv_keys as $k => $label):
                        $row = $dv_signatories[$k] ?? ['display_name' => '', 'position_line1' => '', 'position_line2' => '', 'is_active' => 1];
                    ?>
                        <div class="util-dv-card util-card">
                            <div class="util-card__head">
                                <h3><?= htmlspecialchars($label) ?></h3>
                            </div>
                            <div class="util-card__body">
                                <form method="post">
                                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                    <input type="hidden" name="office" value="<?= htmlspecialchars($selected_office, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="scope" value="dv">
                                    <input type="hidden" name="action" value="dv_upsert">
                                    <input type="hidden" name="option_type" value="<?= htmlspecialchars($k) ?>">
                                    <div class="field">
                                        <label>Printed name</label>
                                        <input class="form-custom-input" type="text" name="display_name" value="<?= htmlspecialchars($row['display_name'] ?? '') ?>" placeholder="e.g., JUAN D. DELA CRUZ" required>
                                    </div>
                                    <div class="field">
                                        <label>Position line 1</label>
                                        <input class="form-custom-input" type="text" name="position_line1" value="<?= htmlspecialchars($row['position_line1'] ?? '') ?>" placeholder="e.g., Accountant III">
                                    </div>
                                    <div class="field">
                                        <label>Position line 2</label>
                                        <input class="form-custom-input" type="text" name="position_line2" value="<?= htmlspecialchars($row['position_line2'] ?? '') ?>" placeholder="e.g., Head Accounting Unit/Authorized Representative">
                                    </div>
                                    <label class="chk">
                                        <input type="checkbox" name="is_active" <?= ((int)($row['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                                        <span>Active</span>
                                    </label>
                                    <button class="btn success" type="submit">Save</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="util-dv-section">
                <p class="util-section-title">Employee tags &amp; UACS codes</p>
                <p class="util-dv-desc">
                    Tags appear in User Management (devtool). Each tag has a primary service UACS row plus sub UACS rows
                    (Pag-ibig, PhilHealth, cash, etc.) on salary disbursement vouchers.
                    Default tag: <strong><?= htmlspecialchars($emp_tag_default, ENT_QUOTES, 'UTF-8') ?></strong>.
                </p>

                <div class="util-card util-emp-tag-card">
                    <div class="util-card__head">
                        <h3>Employee Tags</h3>
                    </div>
                    <div class="util-card__body">
                        <div class="util-add-group">
                        <form method="post" class="util-add">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                            <input type="hidden" name="scope" value="emp_tag">
                            <input type="hidden" name="action" value="emp_tag_add">
                            <div class="field">
                                <label>Tag name</label>
                                <input class="form-custom-input" type="text" name="tag_value" placeholder="e.g., Janitorial Services" required>
                            </div>
                            <div class="field util-uacs-field">
                                <label>Primary UACS code</label>
                                <input class="form-custom-input util-uacs-input" type="text" name="uacs_code" placeholder="5021202000" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" title="10-digit UACS code">
                            </div>
                            <div class="field" style="max-width:110px;">
                                <label>Sort</label>
                                <input class="form-custom-input" type="number" name="sort_order" value="0">
                            </div>
                            <label class="chk">
                                <input type="checkbox" name="is_default">
                                <span>Default</span>
                            </label>
                            <div class="field utl-add-btn-field">
                                <label class="utl-field-spacer" aria-hidden="true">&nbsp;</label>
                                <button class="btn primary utl-btn-add" type="submit" title="Add tag" aria-label="Add tag">+</button>
                            </div>
                        </form>
                        <form method="post" class="util-emp-tag-fill" onsubmit="return confirm('Set empty emp_tag values to the default tag for all users?');">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                            <input type="hidden" name="scope" value="emp_tag">
                            <input type="hidden" name="action" value="emp_tag_fill_empty">
                            <button class="btn secondary" type="submit">Fill default</button>
                        </form>
                        </div>

                        <?php if (!$emp_tag_options): ?>
                            <p class="util-empty">No employee tags yet. Add one above.</p>
                        <?php endif; ?>

                        <?php foreach ($emp_tag_options as $r):
                            $tagId = (int) ($r['id'] ?? 0);
                            $subRows = $r['sub_uacs'] ?? [];
                        ?>
                            <div class="util-emp-tag-block">
                                <div class="util-emp-tag-block__main">
                                    <div class="util-inline">
                                        <form method="post" class="util-inline" style="flex:1; min-width:0;">
                                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                            <input type="hidden" name="scope" value="emp_tag">
                                            <input type="hidden" name="action" value="emp_tag_update">
                                            <input type="hidden" name="id" value="<?= $tagId ?>">
                                            <input class="form-custom-input" type="text" name="tag_value" value="<?= htmlspecialchars((string)$r['tag_value']) ?>" required>
                                            <input class="form-custom-input util-uacs-input" type="text" name="uacs_code" value="<?= htmlspecialchars((string)($r['uacs_code'] ?? '')) ?>" placeholder="Primary UACS" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" title="10-digit UACS code">
                                            <input class="form-custom-input" type="number" name="sort_order" value="<?= (int)$r['sort_order'] ?>">
                                            <label class="chk">
                                                <input type="checkbox" name="is_active" <?= ((int)$r['is_active'] === 1) ? 'checked' : '' ?>>
                                                <span>Active</span>
                                            </label>
                                            <label class="chk">
                                                <input type="checkbox" name="is_default" <?= ((int)$r['is_default'] === 1) ? 'checked' : '' ?>>
                                                <span>Default</span>
                                            </label>
                                            <button class="btn success" type="submit">Save tag</button>
                                        </form>
                                        <?php if ((int)($r['is_default'] ?? 0) !== 1): ?>
                                            <form method="post" onsubmit="return confirm('Delete this employee tag and all sub UACS rows?');" class="util-row-actions">
                                                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                                <input type="hidden" name="scope" value="emp_tag">
                                                <input type="hidden" name="action" value="emp_tag_delete">
                                                <input type="hidden" name="id" value="<?= $tagId ?>">
                                                <button class="btn danger" type="submit">Delete tag</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="util-emp-tag-block__subs">
                                    <p class="util-emp-tag-block__subs-title">Sub UACS codes for <?= htmlspecialchars((string)$r['tag_value'], ENT_QUOTES, 'UTF-8') ?></p>

                                    <div class="utl-subuacs">
                                        <div class="utl-subuacs__row utl-subuacs__row--head">
                                            <div class="utl-subuacs__cell utl-subuacs__cell--title">Account title</div>
                                            <div class="utl-subuacs__cell utl-subuacs__cell--uacs">UACS</div>
                                            <div class="utl-subuacs__cell utl-subuacs__cell--sort">Sort</div>
                                            <div class="utl-subuacs__cell utl-subuacs__cell--active">Active</div>
                                            <div class="utl-subuacs__cell utl-subuacs__cell--actions">Actions</div>
                                        </div>

                                        <form method="post" class="utl-subuacs__row utl-subuacs__add">
                                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                            <input type="hidden" name="scope" value="emp_tag">
                                            <input type="hidden" name="action" value="emp_tag_sub_add">
                                            <input type="hidden" name="emp_tag_id" value="<?= $tagId ?>">
                                            <div class="utl-subuacs__cell utl-subuacs__cell--title">
                                                <label>Account title</label>
                                                <input class="form-custom-input util-sub-title-input" type="text" name="account_title" placeholder="e.g., Due to PhilHealth" required>
                                            </div>
                                            <div class="utl-subuacs__cell utl-subuacs__cell--uacs">
                                                <label>UACS code</label>
                                                <input class="form-custom-input util-uacs-input" type="text" name="uacs_code" placeholder="2020104000" inputmode="numeric" pattern="[0-9]{10}" maxlength="10">
                                            </div>
                                            <div class="utl-subuacs__cell utl-subuacs__cell--sort">
                                                <label>Sort</label>
                                                <input class="form-custom-input" type="number" name="sort_order" value="0">
                                            </div>
                                            <div class="utl-subuacs__cell utl-subuacs__cell--active"></div>
                                            <div class="utl-subuacs__cell utl-subuacs__cell--actions">
                                                <label class="utl-field-spacer" aria-hidden="true">&nbsp;</label>
                                                <button class="btn primary utl-btn-add" type="submit" title="Add sub UACS" aria-label="Add sub UACS">+</button>
                                            </div>
                                        </form>

                                        <?php if (!$subRows): ?>
                                            <div class="utl-subuacs__empty">No sub UACS rows yet.</div>
                                        <?php endif; ?>

                                        <?php foreach ($subRows as $sub): ?>
                                            <div class="utl-subuacs__row">
                                                <form method="post">
                                                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                                    <input type="hidden" name="scope" value="emp_tag">
                                                    <input type="hidden" name="action" value="emp_tag_sub_update">
                                                    <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
                                                    <input type="hidden" name="emp_tag_id" value="<?= $tagId ?>">
                                                    <div class="utl-subuacs__cell utl-subuacs__cell--title">
                                                        <input class="form-custom-input util-sub-title-input" type="text" name="account_title" value="<?= htmlspecialchars((string)$sub['account_title']) ?>" required>
                                                    </div>
                                                    <div class="utl-subuacs__cell utl-subuacs__cell--uacs">
                                                        <input class="form-custom-input util-uacs-input" type="text" name="uacs_code" value="<?= htmlspecialchars((string)($sub['uacs_code'] ?? '')) ?>" inputmode="numeric" pattern="[0-9]{10}" maxlength="10">
                                                    </div>
                                                    <div class="utl-subuacs__cell utl-subuacs__cell--sort">
                                                        <input class="form-custom-input" type="number" name="sort_order" value="<?= (int)$sub['sort_order'] ?>">
                                                    </div>
                                                    <div class="utl-subuacs__cell utl-subuacs__cell--active">
                                                        <label class="chk">
                                                            <input type="checkbox" name="is_active" <?= ((int)$sub['is_active'] === 1) ? 'checked' : '' ?>>
                                                            <span>Active</span>
                                                        </label>
                                                        <button class="btn success" type="submit">Save</button>
                                                    </div>
                                                </form>
                                                <form method="post" onsubmit="return confirm('Delete this sub UACS row?');">
                                                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                                    <input type="hidden" name="scope" value="emp_tag">
                                                    <input type="hidden" name="action" value="emp_tag_sub_delete">
                                                    <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
                                                    <input type="hidden" name="emp_tag_id" value="<?= $tagId ?>">
                                                    <div class="utl-subuacs__cell utl-subuacs__cell--actions">
                                                        <button class="btn danger" type="submit">Delete</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../protected/js/main.js"></script>
</body>

</html>