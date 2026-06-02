<?php
/**
 * Cloudflare Web Analytics injects beacon.min.js on proxied sites.
 * Ad blockers block it (ERR_BLOCKED_BY_CLIENT). The app does not use it.
 * This guard removes injected tags and stops those failures from breaking app JS.
 */
?>
<script>
(function () {
    'use strict';

    var BLOCKED_URL = /cloudflareinsights\.com|static\.cloudflareinsights|beacon\.min\.js|cf-beacon|cfanalytics/i;
    /** DevTools often shows only the beacon token as the script "file" name. */
    var BLOCKED_TOKEN = /^[a-f0-9]{20,}(:\d+)?$/i;

    function isBlockedSrc(src) {
        var s = String(src || '');
        if (!s) {
            return false;
        }
        if (BLOCKED_URL.test(s)) {
            return true;
        }
        var last = s.split('/').pop().split('?')[0];
        return BLOCKED_TOKEN.test(last) || BLOCKED_TOKEN.test(s);
    }

    function neutralizeBlockedScript(el) {
        if (!el || el.tagName !== 'SCRIPT') {
            return;
        }
        var src = el.src || el.getAttribute('src') || '';
        if (!isBlockedSrc(src)) {
            return;
        }
        el.type = 'text/blocked-analytics';
        el.removeAttribute('src');
        try {
            el.remove();
        } catch (ignore) {}
    }

    function removeBeaconScripts(root) {
        if (!root || !root.querySelectorAll) {
            return;
        }
        root.querySelectorAll('script[src]').forEach(neutralizeBlockedScript);
        root.querySelectorAll('script').forEach(function (el) {
            var text = el.textContent || '';
            if (text && BLOCKED_URL.test(text)) {
                el.remove();
            }
        });
    }

    function swallowBlockedEvent(e) {
        var src = e.filename
            || (e.target && (e.target.src || e.target.href))
            || (e.message || '');
        if (isBlockedSrc(src) || (e.message && isBlockedSrc(e.message))) {
            if (typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            return true;
        }
        return false;
    }

    removeBeaconScripts(document.documentElement);

    if (document.documentElement && typeof MutationObserver !== 'undefined') {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) {
                        return;
                    }
                    if (node.tagName === 'SCRIPT') {
                        neutralizeBlockedScript(node);
                        return;
                    }
                    removeBeaconScripts(node);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    window.addEventListener('error', swallowBlockedEvent, true);

    window.addEventListener('unhandledrejection', function (e) {
        var reason = e.reason;
        var msg = String((reason && reason.message) || reason || '');
        if (isBlockedSrc(msg) || BLOCKED_URL.test(msg)) {
            e.preventDefault();
        }
    }, true);
})();
</script>
