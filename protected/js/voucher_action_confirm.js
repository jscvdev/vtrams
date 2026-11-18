(function (window, document) {
    'use strict';

    var onConfirmFn = null;

    function setText(el, value) {
        if (!el) return;
        el.textContent = value == null ? '' : String(value);
    }

    function setRowVisible(row, value) {
        if (!row) return;
        var text = String(value || '').trim();
        row.hidden = text === '';
        var dd = row.querySelector('dd');
        if (dd) dd.textContent = text || '—';
    }

    function ensureModal() {
        var popup = document.getElementById('voucherActionConfirmPopup');
        if (popup) return popup;

        var overlay = document.createElement('div');
        overlay.className = 'overlay voucher-premium-overlay';
        overlay.id = 'voucherActionConfirmOverlay';
        overlay.style.display = 'none';

        popup = document.createElement('div');
        popup.className = 'popup-form voucher-premium-modal popup-form--compact voucher-action-confirm-modal';
        popup.id = 'voucherActionConfirmPopup';
        popup.style.display = 'none';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-modal', 'true');
        popup.setAttribute('aria-labelledby', 'voucherActionConfirmTitle');
        popup.innerHTML =
            '<div class="popupForm-box__container">' +
                '<div class="popupForm-header__container">' +
                    '<p id="voucherActionConfirmTitle">Confirm Action</p>' +
                    '<i class="ri-close-fill close-icon" id="voucherActionConfirmClose" aria-label="Close"></i>' +
                '</div>' +
                '<div class="f-container">' +
                    '<div class="box-body__container">' +
                        '<div class="voucher-action-confirm">' +
                            '<p class="voucher-action-confirm__lead" id="voucherActionConfirmLead"></p>' +
                            '<dl class="voucher-action-confirm__details">' +
                                '<div class="voucher-action-confirm__row" data-confirm-row="processingNo">' +
                                    '<dt>Processing No.</dt><dd></dd>' +
                                '</div>' +
                                '<div class="voucher-action-confirm__row" data-confirm-row="user">' +
                                    '<dt>User</dt><dd></dd>' +
                                '</div>' +
                                '<div class="voucher-action-confirm__row" data-confirm-row="unit">' +
                                    '<dt>Section / Unit</dt><dd></dd>' +
                                '</div>' +
                            '</dl>' +
                        '</div>' +
                    '</div>' +
                    '<div class="popupForm-footer__container">' +
                        '<div class="footer-button__container">' +
                            '<button class="btn warning" type="button" id="voucherActionConfirmYes">Confirm</button>' +
                            '<button class="btn secondary transparent" type="button" id="voucherActionConfirmNo">CANCEL</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(overlay);
        document.body.appendChild(popup);

        function hide() {
            popup.style.display = 'none';
            overlay.style.display = 'none';
            onConfirmFn = null;
        }

        overlay.addEventListener('click', hide);
        popup.querySelector('#voucherActionConfirmClose').addEventListener('click', hide);
        popup.querySelector('#voucherActionConfirmNo').addEventListener('click', hide);
        popup.querySelector('#voucherActionConfirmYes').addEventListener('click', function () {
            var fn = onConfirmFn;
            hide();
            if (typeof fn === 'function') fn();
        });

        return popup;
    }

    window.showVoucherActionConfirm = function (options) {
        var opts = options || {};
        var popup = ensureModal();
        var overlay = document.getElementById('voucherActionConfirmOverlay');
        var yesBtn = document.getElementById('voucherActionConfirmYes');

        setText(document.getElementById('voucherActionConfirmTitle'), opts.title || 'Confirm Action');
        setText(document.getElementById('voucherActionConfirmLead'), opts.lead || '');
        setRowVisible(popup.querySelector('[data-confirm-row="processingNo"]'), opts.processingNo);
        setRowVisible(popup.querySelector('[data-confirm-row="user"]'), opts.user);
        setRowVisible(popup.querySelector('[data-confirm-row="unit"]'), opts.unit);

        if (yesBtn) {
            yesBtn.textContent = opts.confirmLabel || 'Confirm';
            yesBtn.className = 'btn ' + (opts.confirmClass || 'warning');
        }

        onConfirmFn = typeof opts.onConfirm === 'function' ? opts.onConfirm : null;
        if (overlay) overlay.style.display = 'block';
        popup.style.display = 'block';
    };
})(window, document);
