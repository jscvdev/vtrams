<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/sort_order_helper.inc.php';
AuditHelper::logPageView('Signatories');

// Utilities: System Admin with ACL >= 999
$target = explode(",", $_SESSION['logged_user_designation'] ?? '');
if (!AccessControl::canAccessSystemUtilities()) {
    echo "<script>process_functionAlert('Access denied!', 'dashboard_redirect')</script>";
    die();
}

// Ensure signatory tables support per-office configuration.
utilities_signatory_ensure_schema($pdo);

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
        $scope = $_POST['scope'] ?? 'ada'; // 'ada' or 'dv'
        $formOffice = utilities_signatory_resolve_office($pdo, $_POST['office'] ?? $selected_office);
        $selected_office = $formOffice;
        $allowedTypes = [ADA_OPT_CERTIFIED, ADA_OPT_APPROVED, ADA_OPT_SIGNATORY];
        $allowedDvKeys = [
            'dv_certified_msd',
            'dv_certified_tsd',
            'dv_certified_penro',
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
                        $sort = sort_order_next_position($pdo, 'ada_signatory_options', [
                            'option_type' => $type,
                            'office' => $formOffice,
                        ]);
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
                } elseif ($scope === 'dv' && $action === 'add') {
                    $name = normalize_opt_value((string)($_POST['display_name'] ?? ''));
                    $pos1 = normalize_opt_value((string)($_POST['position_line1'] ?? ''));
                    $pos2 = normalize_opt_value((string)($_POST['position_line2'] ?? ''));
                    $isDefault = isset($_POST['is_default']) ? 1 : 0;
                    if ($name === '') {
                        $flash = ['type' => 'error', 'msg' => 'Printed name is required.'];
                    } else {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("
                            INSERT INTO voucher_signatories (signatory_key, office, display_name, position_line1, position_line2, is_active, is_default, sort_order)
                            VALUES (:k, :office, :n, :p1, :p2, 1, :d, 0)
                        ");
                        $stmt->execute([':k' => $type, ':office' => $formOffice, ':n' => $name, ':p1' => $pos1, ':p2' => $pos2, ':d' => $isDefault]);
                        $newId = (int) $pdo->lastInsertId();
                        $sort = sort_order_next_position($pdo, 'voucher_signatories', [
                            'signatory_key' => $type,
                            'office' => $formOffice,
                        ]);
                        sort_order_place_at_position($pdo, 'voucher_signatories', $newId, $sort, [
                            'signatory_key' => $type,
                            'office' => $formOffice,
                        ]);
                        if ($isDefault === 1) {
                            utilities_dv_signatory_set_default($pdo, $newId, $type, $formOffice);
                        }
                        $pdo->commit();
                        $flash = ['type' => 'success', 'msg' => 'DV signatory added.'];
                    }
                } elseif ($scope === 'dv' && $action === 'update') {
                    $id = (int)($_POST['id'] ?? 0);
                    $name = normalize_opt_value((string)($_POST['display_name'] ?? ''));
                    $pos1 = normalize_opt_value((string)($_POST['position_line1'] ?? ''));
                    $pos2 = normalize_opt_value((string)($_POST['position_line2'] ?? ''));
                    $sort = (int)($_POST['sort_order'] ?? 0);
                    $active = isset($_POST['is_active']) ? 1 : 0;
                    $isDefault = isset($_POST['is_default']) ? 1 : 0;
                    if ($id <= 0 || $name === '') {
                        $flash = ['type' => 'error', 'msg' => 'Invalid update payload.'];
                    } elseif ($active === 0 && $isDefault === 1) {
                        $flash = ['type' => 'error', 'msg' => 'The default signatory must stay active.'];
                    } else {
                        $pdo->beginTransaction();
                        sort_order_handle_update($pdo, 'voucher_signatories', $id, $sort, [
                            'signatory_key' => $type,
                            'office' => $formOffice,
                        ]);
                        $stmt = $pdo->prepare("
                            UPDATE voucher_signatories
                            SET display_name = :n, position_line1 = :p1, position_line2 = :p2,
                                sort_order = :s, is_active = :a, is_default = :d
                            WHERE id = :id AND signatory_key = :k AND office = :office
                        ");
                        $stmt->execute([
                            ':n' => $name, ':p1' => $pos1, ':p2' => $pos2,
                            ':s' => $sort, ':a' => $active, ':d' => $isDefault,
                            ':id' => $id, ':k' => $type, ':office' => $formOffice,
                        ]);
                        if ($isDefault === 1) {
                            utilities_dv_signatory_set_default($pdo, $id, $type, $formOffice);
                        }
                        $pdo->commit();
                        $flash = ['type' => 'success', 'msg' => 'DV signatory updated.'];
                    }
                } elseif ($scope === 'dv' && $action === 'delete') {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        $flash = ['type' => 'error', 'msg' => 'Invalid delete payload.'];
                    } else {
                        $check = $pdo->prepare('SELECT is_default FROM voucher_signatories WHERE id = :id AND signatory_key = :k AND office = :office LIMIT 1');
                        $check->execute([':id' => $id, ':k' => $type, ':office' => $formOffice]);
                        $row = $check->fetch(PDO::FETCH_ASSOC);
                        if (!$row) {
                            $flash = ['type' => 'error', 'msg' => 'Signatory not found.'];
                        } elseif ((int)($row['is_default'] ?? 0) === 1) {
                            $flash = ['type' => 'error', 'msg' => 'Cannot delete the default signatory. Set another signatory as default first.'];
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM voucher_signatories WHERE id = :id AND signatory_key = :k AND office = :office");
                            $stmt->execute([':id' => $id, ':k' => $type, ':office' => $formOffice]);
                            $flash = ['type' => 'success', 'msg' => 'DV signatory deleted.'];
                        }
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
$ada_next_sort = [
    ADA_OPT_CERTIFIED => sort_order_next_position($pdo, 'ada_signatory_options', [
        'option_type' => ADA_OPT_CERTIFIED,
        'office' => $selected_office,
    ]),
    ADA_OPT_APPROVED => sort_order_next_position($pdo, 'ada_signatory_options', [
        'option_type' => ADA_OPT_APPROVED,
        'office' => $selected_office,
    ]),
    ADA_OPT_SIGNATORY => sort_order_next_position($pdo, 'ada_signatory_options', [
        'option_type' => ADA_OPT_SIGNATORY,
        'office' => $selected_office,
    ]),
];

// DV signatories (multiple active entries per role)
$dv_keys = [
    'dv_certified_msd' => 'DV A. Certified (MSD)',
    'dv_certified_tsd' => 'DV A. Certified (TSD)',
    'dv_certified_penro' => 'DV A. Certified (PENRO)',
    'dv_accounting_certified' => 'DV C. Certified (Accounting)',
    'dv_approved_for_payment' => 'DV D. Approved for Payment',
];
$dv_signatories = utilities_fetch_dv_signatories($pdo, $selected_office);
$dv_next_sort = [];
foreach (array_keys($dv_keys) as $dvKey) {
    $dv_next_sort[$dvKey] = sort_order_next_position($pdo, 'voucher_signatories', [
        'signatory_key' => $dvKey,
        'office' => $selected_office,
    ]);
}

$active_util_tab = 'ada';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_scope = (string) ($_POST['scope'] ?? 'ada');
    $active_util_tab = ($post_scope === 'dv') ? 'dv' : 'ada';
} else {
    $tab_param = (string) ($_GET['tab'] ?? '');
    if (in_array($tab_param, ['ada', 'dv'], true)) {
        $active_util_tab = $tab_param;
    }
}
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

    .utl-page .util-dv-section {
        margin-top: 0;
        padding-top: 0;
        border-top: none;
    }

    .util-signatory-add-panel .util-add label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 0.3rem;
    }

    .util-signatory-add-panel .form-custom-input {
        background: #fff;
        border-color: #dbe3ef;
    }

    .util-signatory-add-panel .util-ada-add:not(.util-dv-add) {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 64px auto auto;
        gap: 0.625rem 0.75rem;
        align-items: end;
    }

    .util-signatory-add-panel .util-ada-add:not(.util-dv-add) .field {
        min-width: 0;
        margin: 0;
    }

    .util-signatory-add-panel .util-ada-add:not(.util-dv-add) .util-ada-sort-field {
        max-width: none;
    }

    .util-signatory-add-panel .util-ada-add:not(.util-dv-add) .chk {
        align-self: end;
        padding-bottom: 0.45rem;
        white-space: nowrap;
    }

    .util-signatory-add-panel .util-ada-add:not(.util-dv-add) .utl-add-btn-field {
        align-self: end;
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
        transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }

    .util-card:hover,
    .util-signatory-card:hover {
        box-shadow: 0 8px 24px -8px rgba(15, 23, 42, 0.12);
        border-color: #cbd5e1;
    }

    .util-signatory-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 16px -4px rgba(15, 23, 42, 0.08);
    }

    .util-signatory-card .util-card__head {
        padding: 0.875rem 1.125rem;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 55%, #eef2ff 100%);
        position: relative;
    }

    .util-signatory-card .util-card__head::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #6366f1 0%, #4f46e5 100%);
        border-radius: 0 2px 2px 0;
    }

    .util-signatory-card .util-card__head h3 {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.01em;
        padding-left: 0.375rem;
    }

    .util-signatory-card .util-card__body {
        padding: 1rem 1.125rem 1.125rem;
        background: #fff;
    }

    .util-signatory-add-panel {
        padding: 0.875rem;
        margin-bottom: 1rem;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .util-signatory-add-panel .util-add {
        margin-bottom: 0;
    }

    .util-signatory-add-label {
        display: block;
        margin: 0 0 0.625rem;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
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

    .util-signatory-card .util-empty {
        padding: 1.25rem;
        font-size: 0.8125rem;
        color: #94a3b8;
        font-style: italic;
    }

    .util-dv-section {
        margin-top: 0;
        padding-top: 0;
        border-top: none;
    }

    .util-dv-desc {
        margin: 0 0 1.25rem 0;
        color: var(--util-muted);
        font-size: 0.8125rem;
        line-height: 1.5;
    }

    .util-dv-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.25rem;
    }

    .util-dv-card.util-signatory-card {
        background: #fff;
    }

    .util-dv-card.util-signatory-card .util-card__head h3 {
        font-size: 0.875rem;
        line-height: 1.35;
    }

    .util-dv-card .util-card__body {
        padding: 1rem 1.125rem 1.125rem;
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
        padding: 0.375rem 0.625rem;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
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
        width: 2.375rem;
        height: 2.375rem;
        min-width: 2.375rem;
        padding: 0;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.25rem;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: linear-gradient(180deg, #6366f1 0%, #4f46e5 100%);
        border: none;
        color: #fff;
        box-shadow: 0 1px 2px rgba(79, 70, 229, 0.25);
        transition: filter 0.15s ease, transform 0.15s ease;
    }

    .utl-btn-add:hover {
        filter: brightness(1.06);
        transform: translateY(-1px);
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

    .util-dv-add {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr) minmax(0, 1fr);
        grid-template-areas:
            "name name name"
            "pos1 pos2 sort"
            "default default add";
        gap: 0.625rem 0.75rem;
        align-items: end;
    }

    .util-dv-add .util-dv-field--name { grid-area: name; }
    .util-dv-add .util-dv-field--pos1 { grid-area: pos1; }
    .util-dv-add .util-dv-field--pos2 { grid-area: pos2; }
    .util-dv-add .util-dv-field--sort { grid-area: sort; max-width: none; }
    .util-dv-add .util-dv-field--default { grid-area: default; align-self: end; padding-bottom: 0.45rem; }
    .util-dv-add .util-dv-field--add { grid-area: add; justify-self: end; }

    .util-dv-add .field {
        min-width: 0;
        margin: 0;
    }

    .util-signatory-card .util-table-wrap {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow-x: auto;
        overflow-y: hidden;
        background: #fff;
        -webkit-overflow-scrolling: touch;
    }

    .util-signatory-card .util-table {
        margin: 0;
    }

    .util-signatory-card .util-table thead th {
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
        padding: 0.625rem 0.5rem;
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.07em;
        color: #64748b;
    }

    .util-signatory-card .util-table tbody td {
        padding: 0.5rem;
        vertical-align: middle;
        background: #fff;
    }

    .util-signatory-card .util-table tbody tr:not(:last-child) td {
        border-bottom: 1px solid #f1f5f9;
    }

    .util-signatory-card .util-table tbody tr:hover td {
        background: #fafbff;
    }

    .util-signatory-card .form-custom-input {
        min-height: 34px;
        padding: 0.4rem 0.625rem;
        font-size: 0.8125rem;
        border-radius: 8px;
        border-color: #e2e8f0;
        background: #f8fafc;
    }

    .util-signatory-card .form-custom-input:focus {
        background: #fff;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
    }

    .util-signatory-card .util-ada-sort {
        text-align: center;
        padding-left: 0.35rem;
        padding-right: 0.35rem;
    }

    .util-signatory-card .util-ada-chk {
        position: relative;
        justify-content: center;
        font-size: 0.6875rem;
        color: #64748b;
    }

    .util-signatory-card .util-ada-chk span {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .util-signatory-card .util-row-actions .btn {
        min-width: 0;
        padding: 0.35rem 0.625rem;
        font-size: 0.6875rem;
        font-weight: 600;
        border-radius: 8px;
        letter-spacing: 0.02em;
    }

    .util-signatory-card .util-row-actions .btn.success {
        background: linear-gradient(180deg, #10b981 0%, #059669 100%);
        border: none;
        color: #fff;
        box-shadow: 0 1px 2px rgba(5, 150, 105, 0.2);
    }

    .util-signatory-card .util-row-actions .btn.danger {
        background: #fff;
        color: #dc2626;
        border: 1px solid #fecaca;
        box-shadow: none;
    }

    .util-signatory-card .util-row-actions .btn.danger:hover {
        background: #fef2f2;
        border-color: #fca5a5;
    }

    .util-signatory-card .util-ada-value {
        min-width: 100px;
    }

    .util-signatory-card .util-table.util-ada-table {
        min-width: 640px;
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

        .util-dv-add {
            grid-template-columns: 1fr 1fr;
            grid-template-areas:
                "name name"
                "pos1 pos2"
                "sort default"
                "add add";
        }

        .util-dv-add .util-dv-field--add {
            justify-self: start;
        }

        .util-signatory-add-panel .util-ada-add:not(.util-dv-add) {
            grid-template-columns: 1fr 1fr;
        }

        .util-signatory-add-panel .util-ada-add:not(.util-dv-add) .field:first-of-type {
            grid-column: 1 / -1;
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

    @media (max-width: 720px) {
        .util-dv-add {
            grid-template-columns: 1fr;
            grid-template-areas:
                "name"
                "pos1"
                "pos2"
                "sort"
                "default"
                "add";
        }
    }
</style>

<?php require __DIR__ . '/partials/list_filter_styles.php'; ?>

<div class="main main--voucher-dashboard util-premium-page utl-page" id="main">
    <header class="voucher-dashboard-header">
        <div class="voucher-dashboard-header__text">
            <h1 class="voucher-dashboard-title">Signatories</h1>
            <p class="voucher-dashboard-subtitle">Manage LDDAP-ADA signatory options and voucher defaults per office.</p>
        </div>
    </header>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">
            <span class="voucher-card-title__label">
                <i class="ri-file-list-3-line ri-icon"></i>
                LDDAP-ADA Signatory | Voucher Options
            </span>
            <form method="get" class="util-office-switch" id="utilitiesOfficeForm">
                <input type="hidden" name="tab" id="utilitiesOfficeTab" value="<?= htmlspecialchars($active_util_tab, ENT_QUOTES, 'UTF-8') ?>" data-util-tab-sync>
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
        <div class="content-wrapper util-content-with-subtabs">
            <?php if ($flash): ?>
                <div class="util-flash-wrap">
                    <div class="util-alert <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= $flash['type'] === 'success' ? '<i class="ri-checkbox-circle-fill"></i>' : ($flash['type'] === 'error' ? '<i class="ri-error-warning-fill"></i>' : '<i class="ri-information-fill"></i>') ?>
                        <?= htmlspecialchars($flash['msg']) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="util-subtabs-toolbar">
                <div class="util-subtabs-bar" role="tablist" aria-label="Signatory sections">
                    <button type="button" class="util-subtab-btn<?= $active_util_tab === 'ada' ? ' is-active' : '' ?>" role="tab" id="utilSubtabAda" data-util-tab="ada" aria-selected="<?= $active_util_tab === 'ada' ? 'true' : 'false' ?>" aria-controls="utilSubtabPanelAda">
                        LDDAP-ADA options
                    </button>
                    <button type="button" class="util-subtab-btn<?= $active_util_tab === 'dv' ? ' is-active' : '' ?>" role="tab" id="utilSubtabDv" data-util-tab="dv" aria-selected="<?= $active_util_tab === 'dv' ? 'true' : 'false' ?>" aria-controls="utilSubtabPanelDv">
                        DV printed template
                    </button>
                </div>
                <p class="util-subtabs-hint">Each tab uses the full panel — switch tabs for an unobstructed view.</p>
            </div>

            <div class="util-subtab-panels">
                <div class="util-subtab-panel<?= $active_util_tab === 'ada' ? ' is-active' : '' ?>" id="utilSubtabPanelAda" role="tabpanel" aria-labelledby="utilSubtabAda" data-util-panel="ada">
                    <div class="util-subtab-panel__body">
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
                    $nextAdaSort = (int) ($ada_next_sort[$type] ?? 0);
                ?>
                    <div class="util-card util-signatory-card">
                        <div class="util-card__head">
                            <h3>
                                <?= htmlspecialchars(ada_opt_label($type)) ?>
                                <?php if ($sectionDefault !== ''): ?>
                                    <span style="font-size:0.85rem; font-weight:500; color:var(--util-muted);">— Default: <?= htmlspecialchars($sectionDefault, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="util-card__body">
                            <div class="util-signatory-add-panel">
                                <span class="util-signatory-add-label">Add new option</span>
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
                                    <input class="form-custom-input" type="number" value="<?= $nextAdaSort ?>" readonly title="Assigned automatically on add">
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
                            </div>

                            <div class="util-table-wrap">
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
                    </div>
                <?php endforeach; ?>
            </div>
                    </div>
                </div>

                <div class="util-subtab-panel<?= $active_util_tab === 'dv' ? ' is-active' : '' ?>" id="utilSubtabPanelDv" role="tabpanel" aria-labelledby="utilSubtabDv" data-util-panel="dv">
                    <div class="util-subtab-panel__body">
            <div class="util-dv-section">
                <p class="util-section-title">Disbursement Voucher (DV) printed template</p>
                <p class="util-dv-desc">Configure multiple active signatories per DV section (A, C, and D). Users choose from these options when printing. Mark one signatory per section as default.</p>
                <div class="util-dv-grid">
                    <?php foreach ($dv_keys as $k => $label):
                        $rows = $dv_signatories[$k] ?? [];
                        $nextDvSort = (int) ($dv_next_sort[$k] ?? 0);
                    ?>
                        <div class="util-dv-card util-card util-signatory-card">
                            <div class="util-card__head">
                                <h3><?= htmlspecialchars($label) ?></h3>
                            </div>
                            <div class="util-card__body">
                                <div class="util-signatory-add-panel">
                                    <span class="util-signatory-add-label">Add signatory</span>
                                <form method="post" class="util-add util-ada-add util-dv-add">
                                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                    <input type="hidden" name="office" value="<?= htmlspecialchars($selected_office, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="scope" value="dv">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="option_type" value="<?= htmlspecialchars($k) ?>">
                                    <div class="field util-dv-field--name">
                                        <label>Printed name</label>
                                        <input class="form-custom-input" type="text" name="display_name" placeholder="e.g., JUAN D. DELA CRUZ" required>
                                    </div>
                                    <div class="field util-dv-field--pos1">
                                        <label>Position line 1</label>
                                        <input class="form-custom-input" type="text" name="position_line1" placeholder="e.g., Accountant III">
                                    </div>
                                    <div class="field util-dv-field--pos2">
                                        <label>Position line 2</label>
                                        <input class="form-custom-input" type="text" name="position_line2" placeholder="e.g., Head Accounting Unit">
                                    </div>
                                    <div class="field util-ada-sort-field util-dv-field--sort">
                                        <label>Sort</label>
                                        <input class="form-custom-input" type="number" value="<?= $nextDvSort ?>" readonly title="Assigned automatically on add">
                                    </div>
                                    <label class="chk util-dv-field--default">
                                        <input type="checkbox" name="is_default">
                                        <span>Default</span>
                                    </label>
                                    <div class="field utl-add-btn-field util-dv-field--add">
                                        <label class="utl-field-spacer" aria-hidden="true">&nbsp;</label>
                                        <button class="btn primary utl-btn-add" type="submit" title="Add signatory" aria-label="Add signatory">+</button>
                                    </div>
                                </form>
                                </div>

                                <div class="util-table-wrap">
                                <table class="util-table util-ada-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Position 1</th>
                                            <th>Position 2</th>
                                            <th style="width:56px;">Sort</th>
                                            <th style="width:58px;">Active</th>
                                            <th style="width:62px;">Default</th>
                                            <th style="width:96px; text-align:right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$rows): ?>
                                            <tr>
                                                <td colspan="7" class="util-empty">No signatories yet. Add one above.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($rows as $r):
                                            $rowId = (int) $r['id'];
                                            $updateFormId = 'dv-opt-update-' . preg_replace('/[^a-z0-9_-]/i', '-', $k) . '-' . $rowId;
                                        ?>
                                            <tr>
                                                <td>
                                                    <form method="post" id="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" class="util-ada-update-form">
                                                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                                        <input type="hidden" name="office" value="<?= htmlspecialchars($selected_office, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="scope" value="dv">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="option_type" value="<?= htmlspecialchars($k) ?>">
                                                        <input type="hidden" name="id" value="<?= $rowId ?>">
                                                    </form>
                                                    <input form="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" class="form-custom-input util-ada-value" type="text" name="display_name" value="<?= htmlspecialchars($r['display_name'] ?? '') ?>" required>
                                                </td>
                                                <td>
                                                    <input form="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" class="form-custom-input util-ada-value" type="text" name="position_line1" value="<?= htmlspecialchars($r['position_line1'] ?? '') ?>">
                                                </td>
                                                <td>
                                                    <input form="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" class="form-custom-input util-ada-value" type="text" name="position_line2" value="<?= htmlspecialchars($r['position_line2'] ?? '') ?>">
                                                </td>
                                                <td>
                                                    <input form="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" class="form-custom-input util-ada-sort" type="number" name="sort_order" value="<?= (int)($r['sort_order'] ?? 0) ?>">
                                                </td>
                                                <td>
                                                    <label class="chk util-ada-chk">
                                                        <input form="<?= htmlspecialchars($updateFormId, ENT_QUOTES, 'UTF-8') ?>" type="checkbox" name="is_active" <?= ((int)($r['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
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
                                                        <form method="post" onsubmit="return confirm('Delete this signatory?');">
                                                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                                                            <input type="hidden" name="office" value="<?= htmlspecialchars($selected_office, ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="scope" value="dv">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="option_type" value="<?= htmlspecialchars($k) ?>">
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
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../protected/js/main.js"></script>
<?php
$util_subtabs_initial_tab = $active_util_tab;
$util_subtabs_valid_tabs = ['ada', 'dv'];
$util_subtabs_sync_input_id = 'utilitiesOfficeTab';
require __DIR__ . '/partials/util_subtabs_script.php';
?>
</body>

</html>