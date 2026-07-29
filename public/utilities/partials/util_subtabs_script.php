<?php
/** @var string $util_subtabs_initial_tab */
/** @var list<string> $util_subtabs_valid_tabs */
/** @var string|null $util_subtabs_sync_input_id */
$util_subtabs_initial_tab = (string) ($util_subtabs_initial_tab ?? 'ada');
$util_subtabs_valid_tabs = is_array($util_subtabs_valid_tabs ?? null) ? $util_subtabs_valid_tabs : ['ada', 'dv'];
$util_subtabs_sync_input_id = isset($util_subtabs_sync_input_id) ? (string) $util_subtabs_sync_input_id : '';
?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var validTabs = <?= json_encode(array_values($util_subtabs_valid_tabs), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
        var tabButtons = document.querySelectorAll('.util-subtab-btn[data-util-tab]');
        var tabPanels = document.querySelectorAll('.util-subtab-panel[data-util-panel]');
        var syncInput = <?= $util_subtabs_sync_input_id !== '' ? ('document.getElementById(' . json_encode($util_subtabs_sync_input_id) . ')') : 'null' ?>;

        function activateUtilTab(tabId, updateUrl) {
            if (validTabs.indexOf(tabId) === -1) {
                tabId = validTabs[0] || '';
            }

            tabButtons.forEach(function (btn) {
                var isActive = btn.getAttribute('data-util-tab') === tabId;
                btn.classList.toggle('is-active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            tabPanels.forEach(function (panel) {
                var isActive = panel.getAttribute('data-util-panel') === tabId;
                panel.classList.toggle('is-active', isActive);
            });

            if (syncInput) {
                syncInput.value = tabId;
            }

            document.querySelectorAll('input[name="tab"][data-util-tab-sync]').forEach(function (input) {
                input.value = tabId;
            });

            if (updateUrl !== false && window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', tabId);
                window.history.replaceState({}, '', url.toString());
            }
        }

        tabButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                activateUtilTab(btn.getAttribute('data-util-tab'), true);
            });
        });

        activateUtilTab(<?= json_encode($util_subtabs_initial_tab, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>, false);
    });
</script>
