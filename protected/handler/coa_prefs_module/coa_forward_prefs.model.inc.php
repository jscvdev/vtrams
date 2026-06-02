<?php

function coa_forward_prefs_get(PDO $pdo, string $empId, string $voucherType): ?array
{
    $stmt = $pdo->prepare(
        'SELECT selected_options FROM user_coa_forward_prefs
         WHERE emp_id = :emp_id AND voucher_type = :voucher_type
         LIMIT 1'
    );
    $stmt->execute([
        ':emp_id' => $empId,
        ':voucher_type' => $voucherType,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !isset($row['selected_options'])) {
        return null;
    }

    $raw = trim((string)$row['selected_options']);
    if ($raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function coa_forward_prefs_save(PDO $pdo, string $empId, string $voucherType, string $selectedOptionsJson): bool
{
    $stmt = $pdo->prepare(
        'INSERT INTO user_coa_forward_prefs (emp_id, voucher_type, selected_options)
         VALUES (:emp_id, :voucher_type, :selected_options)
         ON DUPLICATE KEY UPDATE
            selected_options = VALUES(selected_options),
            updated_at = CURRENT_TIMESTAMP'
    );

    return $stmt->execute([
        ':emp_id' => $empId,
        ':voucher_type' => $voucherType,
        ':selected_options' => $selectedOptionsJson,
    ]);
}
