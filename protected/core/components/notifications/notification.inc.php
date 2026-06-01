<?php
/**
 * Centralized Notification System
 * Modern toast-style notifications for the DVSYS application
 * 
 * Usage:
 *   require_once '../../protected/core/components/notifications/notification.inc.php';
 *   showNotify('Message here', 'success', 2500);
 */

// Prevent direct access
if (!defined('DVSYS_NOTIFICATION_LOADED')) {
    define('DVSYS_NOTIFICATION_LOADED', true);
}
?>

<!-- Notification System CSS -->
<style>
    /* Notification (Top Right) */
    .notify-top-right {
        position: fixed;
        top: 70px; /* just below header */
        right: 20px;
        z-index: 99999;
        padding: 14px 22px;
        border-radius: 8px;
        color: #fff;
        font-size: 15px;
        font-weight: 500;
        display: none;
        max-width: 300px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        background: linear-gradient(135deg, #00c6ff, #0072ff);
        opacity: 0;
        transform: translateX(20px);
        transition: all 0.3s ease;
        word-wrap: break-word;
    }
    .notify-top-right.show {
        display: block;
        opacity: 1;
        transform: translateX(0);
    }
    .notify-top-right.success {
        background: linear-gradient(135deg, #00b09b, #96c93d);
    }
    .notify-top-right.error {
        background: linear-gradient(135deg, #ff416c, #ff4b2b);
    }
    .notify-top-right.processing {
        background: linear-gradient(135deg, #36d1dc, #5b86e5);
    }
    .notify-top-right.warning {
        background: linear-gradient(135deg, #f093fb, #f5576c);
    }
    .notify-top-right.info {
        background: linear-gradient(135deg, #4facfe, #00f2fe);
    }
</style>

<!-- Notification HTML Element -->
<div id="notifyTopRight" class="notify-top-right" role="alert" aria-live="polite"></div>

<!-- Notification System JavaScript -->
<script>
    /**
     * Show a toast notification in the top-right corner
     * @param {string} message - The message to display
     * @param {string} type - Notification type: 'success', 'error', 'processing', 'warning', 'info' (default: 'success')
     * @param {number} ms - Duration in milliseconds (default: 2500)
     */
    function showNotify(message, type = 'success', ms = 2500) {
        const n = document.getElementById('notifyTopRight');
        if (!n) {
            console.error('Notification element not found. Make sure notification.inc.php is included.');
            return;
        }
        
        n.className = 'notify-top-right ' + type + ' show';
        n.textContent = message;
        n.style.display = 'block';

        clearTimeout(n._t);
        n._t = setTimeout(() => {
            n.classList.remove('show');
            setTimeout(() => {
                n.style.display = 'none';
            }, 300); // Wait for fade-out transition
        }, ms);
    }

    /**
     * Hide the current notification immediately
     */
    function hideNotify() {
        const n = document.getElementById('notifyTopRight');
        if (n) {
            n.classList.remove('show');
            setTimeout(() => {
                n.style.display = 'none';
            }, 300);
        }
    }
</script>







