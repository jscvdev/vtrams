<?php
/** @var string $blockVoucherType */
/** @var string $blockTypeLabel */
/** @var string $blockTagName */
/** @var array<string, mixed>|null $primaryRow */
/** @var list<array<string, mixed>> $subRows */
/** @var string $form_list_voucher_type */
/** @var string $form_list_q */
$primaryRow = $primaryRow ?? null;
$subRows = $subRows ?? [];
$primaryId = (int) ($primaryRow['id'] ?? 0);
$tagName = utilities_uacs_normalize_tag_name($blockTagName ?? '');
$scopeLabel = $tagName !== '' ? $tagName : 'All payees (voucher type only)';
$isTypeOnlyScope = $tagName === '';
$primaryAccountTitle = trim((string) ($primaryRow['account_title'] ?? ''));
if ($primaryAccountTitle === '' && $tagName !== '') {
    $primaryAccountTitle = $tagName;
}
?>
<div class="util-emp-tag-block">
    <div class="util-emp-tag-block__main">
        <p class="util-emp-tag-block__subs-title" style="margin-bottom: 0.75rem;">
            <?= htmlspecialchars($blockTypeLabel, ENT_QUOTES, 'UTF-8') ?>
            — <?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php if (!$primaryRow): ?>
            <p class="util-empty" style="padding: 0.5rem 0;">No primary UACS for this scope. Add one above.</p>
        <?php else: ?>
            <div class="utl-subuacs-wrap utl-subuacs-wrap--primary">
            <div class="utl-subuacs utl-subuacs--primary">
                <div class="utl-subuacs__row utl-subuacs__row--head">
                    <div class="utl-subuacs__cell">Account title</div>
                    <div class="utl-subuacs__cell">UACS</div>
                    <div class="utl-subuacs__cell">Sort</div>
                    <div class="utl-subuacs__cell">Active</div>
                    <div class="utl-subuacs__cell utl-subuacs__cell--actions">Actions</div>
                </div>
                <div class="utl-subuacs__row uacs-primary-row">
                <form method="post" class="uacs-primary-row__form">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                    <input type="hidden" name="action" value="uacs_update">
                    <input type="hidden" name="list_voucher_type" value="<?= htmlspecialchars($form_list_voucher_type, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="list_q" value="<?= htmlspecialchars($form_list_q, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" value="<?= $primaryId ?>">
                    <input type="hidden" name="voucher_type" value="<?= htmlspecialchars($blockVoucherType, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tag_name" value="<?= htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="utl-subuacs__cell">
                        <?php if ($isTypeOnlyScope): ?>
                            <input class="form-custom-input" type="text" name="account_title" value="<?= htmlspecialchars($primaryAccountTitle, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g., Construction in Progress" required>
                        <?php else: ?>
                            <input class="form-custom-input" type="text" value="<?= htmlspecialchars($primaryAccountTitle, ENT_QUOTES, 'UTF-8') ?>" readonly tabindex="-1" aria-readonly="true">
                            <input type="hidden" name="account_title" value="<?= htmlspecialchars($primaryAccountTitle, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                    </div>
                    <div class="utl-subuacs__cell">
                        <input class="form-custom-input uacs-uacs-input" type="text" name="uacs_code" value="<?= htmlspecialchars((string) ($primaryRow['uacs_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="UACS" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required>
                    </div>
                    <div class="utl-subuacs__cell">
                        <input class="form-custom-input" type="number" name="sort_order" value="<?= (int) ($primaryRow['sort_order'] ?? 0) ?>">
                    </div>
                    <div class="utl-subuacs__cell utl-subuacs__cell--active">
                        <label class="chk">
                            <input type="checkbox" name="is_active" <?= !empty($primaryRow['is_active']) ? 'checked' : '' ?>>
                            <span>Active</span>
                        </label>
                        <button class="btn success btn-flex btn-nowrap" type="submit">Save</button>
                    </div>
                </form>
                <form method="post" onsubmit="return confirm('Delete this UACS scope and all sub rows?');" class="uacs-primary-row__delete">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                    <input type="hidden" name="action" value="uacs_delete_scope">
                    <input type="hidden" name="list_voucher_type" value="<?= htmlspecialchars($form_list_voucher_type, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="list_q" value="<?= htmlspecialchars($form_list_q, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="voucher_type" value="<?= htmlspecialchars($blockVoucherType, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tag_name" value="<?= htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="utl-subuacs__cell utl-subuacs__cell--actions">
                        <button class="btn danger btn-flex btn-nowrap" type="submit">Delete scope</button>
                    </div>
                </form>
                </div>
            </div>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($primaryRow): ?>
    <div class="util-emp-tag-block__subs">
        <p class="util-emp-tag-block__subs-title">Sub UACS for <?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="utl-subuacs-wrap">
        <div class="utl-subuacs">
            <div class="utl-subuacs__row utl-subuacs__row--head">
                <div class="utl-subuacs__cell">Account title</div>
                <div class="utl-subuacs__cell">UACS</div>
                <div class="utl-subuacs__cell">Sort</div>
                <div class="utl-subuacs__cell">Active</div>
                <div class="utl-subuacs__cell utl-subuacs__cell--actions">Actions</div>
            </div>
            <form method="post" class="utl-subuacs__row">
                <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                <input type="hidden" name="action" value="uacs_add">
                <input type="hidden" name="list_voucher_type" value="<?= htmlspecialchars($form_list_voucher_type, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="list_q" value="<?= htmlspecialchars($form_list_q, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="voucher_type" value="<?= htmlspecialchars($blockVoucherType, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="tag_name" value="<?= htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="is_indented" value="1">
                <div class="utl-subuacs__cell">
                    <input class="form-custom-input" type="text" name="account_title" placeholder="e.g., Due to PhilHealth" required>
                </div>
                <div class="utl-subuacs__cell">
                    <input class="form-custom-input uacs-uacs-input" type="text" name="uacs_code" placeholder="2020104000" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required>
                </div>
                <div class="utl-subuacs__cell">
                    <input class="form-custom-input" type="number" name="sort_order" value="0">
                </div>
                <div class="utl-subuacs__cell"></div>
                <div class="utl-subuacs__cell utl-subuacs__cell--actions">
                    <button class="btn primary utl-btn-add" type="submit" title="Add sub UACS">+</button>
                </div>
            </form>
            <?php if (!$subRows): ?>
                <div class="utl-subuacs__empty">No sub UACS rows yet.</div>
            <?php endif; ?>
            <?php foreach ($subRows as $sub):
                $subId = (int) ($sub['id'] ?? 0);
            ?>
                <div class="utl-subuacs__row">
                    <form method="post">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <input type="hidden" name="action" value="uacs_update">
                        <input type="hidden" name="list_voucher_type" value="<?= htmlspecialchars($form_list_voucher_type, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="list_q" value="<?= htmlspecialchars($form_list_q, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= $subId ?>">
                        <input type="hidden" name="voucher_type" value="<?= htmlspecialchars($blockVoucherType, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="tag_name" value="<?= htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="is_indented" value="1">
                        <div class="utl-subuacs__cell">
                            <input class="form-custom-input" type="text" name="account_title" value="<?= htmlspecialchars((string) ($sub['account_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="utl-subuacs__cell">
                            <input class="form-custom-input uacs-uacs-input" type="text" name="uacs_code" value="<?= htmlspecialchars((string) ($sub['uacs_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required>
                        </div>
                        <div class="utl-subuacs__cell">
                            <input class="form-custom-input" type="number" name="sort_order" value="<?= (int) ($sub['sort_order'] ?? 0) ?>">
                        </div>
                        <div class="utl-subuacs__cell utl-subuacs__cell--active">
                            <label class="chk">
                                <input type="checkbox" name="is_active" <?= !empty($sub['is_active']) ? 'checked' : '' ?>>
                                <span>Active</span>
                            </label>
                            <button class="btn success btn-flex btn-nowrap" type="submit">Save</button>
                        </div>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this sub UACS row?');">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <input type="hidden" name="action" value="uacs_delete">
                        <input type="hidden" name="list_voucher_type" value="<?= htmlspecialchars($form_list_voucher_type, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="list_q" value="<?= htmlspecialchars($form_list_q, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= $subId ?>">
                        <div class="utl-subuacs__cell utl-subuacs__cell--actions">
                            <button class="btn danger btn-flex btn-nowrap" type="submit">Delete</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
    <?php endif; ?>
</div>
