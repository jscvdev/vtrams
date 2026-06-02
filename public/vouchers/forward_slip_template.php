<?php
// Template for the printable forward slip.
// Expects POST data: claimant, amount, nature, remarks, processedBy, processedDate, voucher_type.
// Checklist is selected by voucher_type via centralized checklist_config.php.

require_once __DIR__ . '/checklist_config.php';
require_once __DIR__ . '/../../protected/core/components/helpers/amount_helper.inc.php';

function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatAmount($val)
{
    $formatted = format_amount_display((string) $val);
    if ($formatted === '') {
        return h($val);
    }
    return h($formatted);
}

$claimant = h($_POST['claimant'] ?? '');
$amount = formatAmount($_POST['amount'] ?? '');
$nature = h($_POST['nature'] ?? '');
$remarks = h($_POST['remarks'] ?? '');
$processedBy = h($_POST['processedBy'] ?? '');
$processedDate = h($_POST['processedDate'] ?? '');
$voucherType = trim((string)($_POST['voucher_type'] ?? ''));
$signatureName = h($_POST['signature_name'] ?? '');

$checklist = checklist_for_type($voucherType);
$checklistTitle = $checklist['title'];
$docs = $checklist['items'];

// Selected requirements are passed from the frontend as JSON array of labels.
$selectedRaw = $_POST['selected_coa_labels'] ?? '[]';
$selectedSet = [];
if (is_string($selectedRaw) && trim($selectedRaw) !== '') {
    $decoded = json_decode($selectedRaw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $lbl) {
            if (is_string($lbl)) {
                $clean = trim($lbl);
                if ($clean !== '') $selectedSet[$clean] = true;
            }
        }
    }
}

/**
 * Find a selected label that matches a base label, allowing appended detail like:
 * - "etc." to match "etc. - Taxi"
 *
 * @param string $base
 * @param array<string,bool> $set
 * @return string|null  The matched selected label (full text), or null if not selected.
 */
function selected_match_label($base, $set)
{
    $base = trim((string)$base);
    if ($base === '') return null;

    if (isset($set[$base])) return $base;

    $prefix = $base . ' - ';
    foreach ($set as $k => $_v) {
        if (!is_string($k)) continue;
        if (strpos($k, $prefix) === 0) {
            return $k;
        }
    }
    return null;
}
?>

<div class="forward-slip-sheet">
    <div class="row space">
        <div class="row" style="flex:1;">
            <div class="label">NAME OF CLAIMANT:</div>
            <div class="field text"><span><?= $claimant ?></span></div>
        </div>
        <div class="row" style="width: 80mm;">
            <div class="label">AMOUNT:</div>
            <div class="field text small"><span><?= $amount ?></span></div>
        </div>
    </div>

    <div class="row">
        <div class="label">NATURE OF CLAIM:</div>
        <div class="field text"><span><?= $nature ?></span></div>
    </div>

    <div class="heading"><?= h($checklistTitle) ?></div>

    <table>
        <tr>
            <td colspan="3"></td>
            <td class="remarksTitle">REMARKS</td>
        </tr>
        <?php
        $mainRowIndex = 0;
        foreach ($docs as $rawItem) {
            $parsed = checklist_parse_item($rawItem);
            $docLabel = $parsed['label'];
            if ($docLabel === '') {
                continue;
            }
            // If any sub-item is selected, also mark the parent row as checked.
            $parentMatch = selected_match_label($docLabel, $selectedSet);
            $isChecked = ($parentMatch !== null);
            if (!$isChecked && !empty($parsed['subitems'])) {
                foreach ($parsed['subitems'] as $s) {
                    if ($s !== '' && selected_match_label($s, $selectedSet) !== null) {
                        $isChecked = true;
                        break;
                    }
                }
            }
            $isFirstMainRow = ($mainRowIndex === 0);
            $rowNum = $mainRowIndex + 1;
            $mainRowIndex++;
            ?>
            <tr>
                <td class="checkcol">
                    <span class="line checkline<?= $isChecked ? ' checked' : '' ?>"></span>
                </td>
                <td class="numcol"><?= $rowNum ?></td>
                <td class="doccol"><?= h($parentMatch ?? $docLabel) ?></td>
                <td class="remarkscol">
                    <span class="line remarksline">
                        <?php if ($isFirstMainRow && $remarks !== ''): ?>&nbsp;<?php endif; ?>
                    </span>
                </td>
            </tr>
            <?php
            foreach ($parsed['subitems'] as $subLabel) {
                $subMatch = ($subLabel !== '') ? selected_match_label($subLabel, $selectedSet) : null;
                $isSubChecked = ($subMatch !== null);
                ?>
            <tr class="checklist-subrow">
                <td class="checkcol">
                    <span class="line checkline<?= $isSubChecked ? ' checked' : '' ?>"></span>
                </td>
                <td class="numcol"></td>
                <td class="doccol doccol-sub"><?= h($subMatch ?? $subLabel) ?></td>
                <td class="remarkscol">
                    <span class="line remarksline"></span>
                </td>
            </tr>
                <?php
            }
        }
        ?>
    </table>

    <div class="bottomSection">
        <div class="bottomGrid">
            <div class="bottomRow">
                <div class="label">PROCESSED BY:</div>
                <div class="field text"><span><?= $processedBy ?></span></div>
            </div>
            <div class="bottomRow">
                <div class="label">DATE:</div>
                <div class="field text"><span><?= $processedDate ?></span></div>
            </div>
        </div>

        <div class="certGrid">
            <div class="certText">
                I certify that all required attachments have been submitted and acknowledge that incomplete submissions may result in processing delays
            </div>
            <div class="sigBox">
                <div class="field text"><span><?= $signatureName ?></span></div>
                <div class="sigLabel">Name &amp; Signature</div>
            </div>
        </div>
    </div>
</div>


