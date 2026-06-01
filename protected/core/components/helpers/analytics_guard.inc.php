<?php
/**
 * Cloudflare Web Analytics injects beacon.min.js on proxied sites.
 * Ad blockers block it (ERR_BLOCKED_BY_CLIENT). The app does not use it.
 * This guard removes injected tags and prevents those failures from surfacing as app errors.
 */
?>
<script>
(function () {
    'use strict';
    var BLOCKED = /cloudflareinsights\.com/i;

    function isBlockedSrc(src) {
        return BLOCKED.test(String(src || ''));
    }

    function removeBeaconScripts(root) {
        if (!root || !root.querySelectorAll) {
            return;
        }
        root.querySelectorAll('script[src]').forEach(function (el) {
            if (isBlockedSrc(el.src)) {
                el.remove();
            }
        });
    }

    removeBeaconScripts(document.documentElement);

    if (document.documentElement) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) {
                        return;
                    }
                    if (node.tagName === 'SCRIPT' && isBlockedSrc(node.src)) {
                        node.remove();
                        return;
                    }
                    removeBeaconScripts(node);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    window.addEventListener('error', function (e) {
        var src = e.filename || (e.target && e.target.src) || '';
        if (isBlockedSrc(src)) {
            e.stopImmediatePropagation();
            e.preventDefault();
            return true;
        }
    }, true);
})();
</script>
