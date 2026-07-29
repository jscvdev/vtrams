<?php
/** @var array<string, mixed> $list_filter */
/** @var int $list_total */
/** @var int $list_visible */
/** @var string $list_placeholder */
/** @var array<string, string>|null $list_voucher_types */
$list_filter = $list_filter ?? utilities_list_filter_params();
$list_total = (int) ($list_total ?? 0);
$list_visible = (int) ($list_visible ?? 0);
$list_placeholder = (string) ($list_placeholder ?? 'search');
$list_form_id = (string) ($list_form_id ?? 'utilitiesListFilterForm');
$list_filter_mode = (string) ($list_filter_mode ?? 'status');
$list_voucher_types = is_array($list_voucher_types ?? null) ? $list_voucher_types : null;
$list_type_filter_label = (string) ($list_type_filter_label ?? 'Voucher type');
$list_hidden_fields = is_array($list_hidden_fields ?? null) ? $list_hidden_fields : [];
?>
<div class="voucher-card voucher-card--filter util-list-filter-card">
    <div class="filter-toolbar">
        <form method="get" id="<?= htmlspecialchars($list_form_id, ENT_QUOTES, 'UTF-8') ?>" class="filter-toolbar-form util-list-filter-form">
            <?php foreach ($list_hidden_fields as $hiddenName => $hiddenValue): ?>
                <input type="hidden" name="<?= htmlspecialchars((string) $hiddenName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $hiddenValue, ENT_QUOTES, 'UTF-8') ?>" data-util-tab-sync>
            <?php endforeach; ?>
            <div class="filter-search">
                <input
                    type="text"
                    name="q"
                    value="<?= htmlspecialchars((string) ($list_filter['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="<?= htmlspecialchars($list_placeholder, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="off"
                >
            </div>
            <?php if ($list_filter_mode === 'voucher_type' && $list_voucher_types !== null): ?>
                <div class="util-list-filter-status">
                    <label for="<?= htmlspecialchars($list_form_id, ENT_QUOTES, 'UTF-8') ?>_voucher_type"><?= htmlspecialchars($list_type_filter_label, ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="voucher_type" id="<?= htmlspecialchars($list_form_id, ENT_QUOTES, 'UTF-8') ?>_voucher_type" class="form-custom-input">
                        <option value=""<?= (($list_filter['voucher_type'] ?? '') === '') ? ' selected' : '' ?>>All types</option>
                        <?php foreach ($list_voucher_types as $typeValue => $typeLabel): ?>
                            <option value="<?= htmlspecialchars((string) $typeValue, ENT_QUOTES, 'UTF-8') ?>"<?= ((string) ($list_filter['voucher_type'] ?? '') === (string) $typeValue) ? ' selected' : '' ?>>
                                <?= htmlspecialchars((string) $typeLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="util-list-filter-status">
                    <label for="<?= htmlspecialchars($list_form_id, ENT_QUOTES, 'UTF-8') ?>_status">Status</label>
                    <select name="status" id="<?= htmlspecialchars($list_form_id, ENT_QUOTES, 'UTF-8') ?>_status" class="form-custom-input">
                        <option value="all"<?= (($list_filter['status'] ?? 'all') === 'all') ? ' selected' : '' ?>>All</option>
                        <option value="active"<?= (($list_filter['status'] ?? '') === 'active') ? ' selected' : '' ?>>Active</option>
                        <option value="inactive"<?= (($list_filter['status'] ?? '') === 'inactive') ? ' selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            <?php endif; ?>
            <button class="btn primary util-list-filter-btn" type="submit">Apply</button>
            <?php if (!empty($list_filter['is_filtered'])): ?>
                <a class="btn secondary util-list-filter-clear" href="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '.', ENT_QUOTES, 'UTF-8') ?>">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <?php if ($list_total > 0): ?>
        <p class="util-list-filter-meta">
            Showing <strong><?= $list_visible ?></strong> of <strong><?= $list_total ?></strong>
            <?php if (empty($list_filter['is_filtered']) && $list_total > $list_visible): ?>
                — search or filter to view more
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>
