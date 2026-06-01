(function () {
  const printBtn = document.getElementById('print_forward_slip');
  if (!printBtn) return;

  const natureModal = document.getElementById('natureOfClaimModal');
  const natureOverlay = document.getElementById('nature_of_claim_modal_overlay');
  const natureModalInput = document.getElementById('nature_of_claim_modal_input');
  const natureFormInput = document.getElementById('nature_of_claim');
  const natureModalConfirm = document.getElementById('nature_of_claim_modal_confirm');
  const natureModalCancel = document.getElementById('nature_of_claim_modal_cancel');
  const natureModalClose = document.getElementById('close_nature_of_claim_modal');

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatCurrency(val) {
    const n = parseFloat(String(val ?? '').replace(/,/g, ''));
    if (Number.isFinite(n)) {
      return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    return String(val ?? '');
  }

  function formatDateLong(d = new Date()) {
    try {
      return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: '2-digit' });
    } catch (e) {
      return d.toDateString();
    }
  }

  function getForwardSlipData() {
    const selectedLabels = (function () {
      const hidden = document.getElementById('selected_coa_options_forward');
      if (!hidden) return [];

      const raw = String(hidden.value || '').trim();
      if (!raw) return [];

      try {
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];

        return parsed
          .map((opt) => {
            if (opt && typeof opt === 'object') return opt.label || opt.value;
            return opt;
          })
          .map((x) => String(x || '').trim())
          .filter(Boolean);
      } catch (e) {
        return [];
      }
    })();

    const claimant = document.getElementById('encoded_payee')?.value || '';
    const amount = document.getElementById('string_amount')?.value || '';
    const voucherType =
      document.getElementById('voucher_type')?.value ||
      document.getElementById('encoded_type_hidden')?.value ||
      '';

    const remarks = document.getElementById('remarks')?.value || '';

    // Per requirement: leave PROCESSED BY and DATE empty on the printed slip.
    const processedBy = '';
    const signatureName = (window.__loggedUserEmpName || '').toString();

    const nature = String(natureFormInput?.value || '').trim();

    return {
      claimant,
      amount,
      nature,
      remarks,
      processedBy,
      processedDate: '',
      voucher_type: voucherType,
      signature_name: signatureName,
      selected_coa_labels: JSON.stringify(selectedLabels),
    };
  }

  function hasSelectedCoaRequirements() {
    const hidden = document.getElementById('selected_coa_options_forward');
    if (!hidden) return true; // If the page doesn't use COA gating, don't block printing.

    const raw = String(hidden.value || '').trim();
    if (!raw) return false;

    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) && parsed.length > 0;
    } catch (e) {
      // If it's not valid JSON but it's non-empty, treat as selected.
      return true;
    }
  }

  function setPrintBtnEnabled(enabled) {
    printBtn.disabled = !enabled;
    if (!enabled) printBtn.classList.add('btn-disabled-forward');
    else printBtn.classList.remove('btn-disabled-forward');
  }

  function syncPrintButtonState() {
    setPrintBtnEnabled(hasSelectedCoaRequirements());
  }

  function serializeForPost(data) {
    const params = new URLSearchParams();
    Object.keys(data || {}).forEach((key) => {
      params.append(key, data[key] == null ? '' : String(data[key]));
    });
    return params.toString();
  }

  async function fetchSlipHtml(data) {
    const body = serializeForPost(data);
    const response = await fetch('forward_slip_template.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
      },
      body,
    });

    if (!response.ok) {
      throw new Error('Failed to load slip template');
    }

    return await response.text();
  }

  function ensurePrintCss() {
    const id = 'forward-slip-print-css';
    if (document.getElementById(id)) return;

    const style = document.createElement('style');
    style.id = id;
    style.textContent = `
@page { size: A5 landscape; margin: 8mm; }

/* On-screen: keep it hidden */
#forward-slip-print-root { display: none; }

/* Print: show slip only */
@media print {
  body.forward-slip-printing * { visibility: hidden !important; }
  body.forward-slip-printing #forward-slip-print-root,
  body.forward-slip-printing #forward-slip-print-root * { visibility: visible !important; }
  body.forward-slip-printing #forward-slip-print-root {
    display: block !important;
    position: fixed;
    left: 0;
    top: 0;
    width: 210mm;
    height: 148mm;
    background: #fff;
    z-index: 2147483647;
    overflow: visible;
    /* center slip horizontally; align to top so fit scaling keeps bottom on-page */
    display: flex !important;
    align-items: flex-start;
    justify-content: center;
    padding-top: 0.3in;
    box-sizing: border-box;
  }
}

/* Slip styling (matches reference) */
#forward-slip-print-root * { box-sizing: border-box; }
.forward-slip-sheet {
  /* slightly smaller than A5 landscape */
  width: 198mm;
  min-height: 138mm;
  border: 1px solid #000;
  padding: 9mm 9mm 7mm 9mm;
  font-family: Arial, Helvetica, sans-serif;
  color: #000;
  display: flex;
  flex-direction: column;
  gap: 6mm;
  /* allow content to grow without clipping */
  /* Base scale; beforeprint may reduce further so long slips fit the page */
  transform: scale(0.8);
  transform-origin: top center;
}
.forward-slip-sheet > .row,
.forward-slip-sheet > .heading { flex-shrink: 0; }
.forward-slip-sheet > table {
  flex: 0 1 auto;
  min-height: 0;
  overflow: visible;
}
.forward-slip-sheet .row { display: flex; gap: 8mm; align-items: baseline; }
.forward-slip-sheet .row.space { justify-content: space-between; }
.forward-slip-sheet .label { font-size: 11px; }
.forward-slip-sheet .field { flex: 1; border-bottom: 1px solid #000; height: 14px; }
.forward-slip-sheet .field.text { border-bottom: 1px solid #000; padding: 0 2px; }
.forward-slip-sheet .field.text span { font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
.forward-slip-sheet .small { width: 90px; }
.forward-slip-sheet .heading { font-size: 11px; font-weight: bold; margin-top: 1mm; }
.forward-slip-sheet table { width: 100%; border-collapse: collapse; }
.forward-slip-sheet td { font-size: 11px; padding: 2px 4px; vertical-align: middle; }
.forward-slip-sheet .checkcol { width: 18mm; }
.forward-slip-sheet .numcol { width: 8mm; text-align: right; padding-right: 6px; }
.forward-slip-sheet .doccol { width: auto; }
.forward-slip-sheet .doccol-sub { padding-left: 6mm; font-size: 10px; }
.forward-slip-sheet tr.checklist-subrow td { padding-top: 1px; padding-bottom: 1px; }
.forward-slip-sheet .remarkscol { width: 75mm; }
.forward-slip-sheet .remarksTitle { text-align: center; font-weight: bold; font-size: 11px; padding-bottom: 2px; }
.forward-slip-sheet .line { display: inline-block; width: 100%; border-bottom: 1px solid #000; height: 14px; }

/* Mark a row as selected (draw a tick mark) */
.forward-slip-sheet .checkline.checked { position: relative; }
.forward-slip-sheet .checkline.checked:after {
  content: '';
  position: absolute;
  left: 50%;
  top: -1px;
  width: 7px;
  height: 11px;
  border-right: 2px solid #000;
  border-bottom: 2px solid #000;
  transform: translateX(-50%) rotate(45deg);
}

.forward-slip-sheet .bottomSection {
  margin-top: 4mm;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 2mm;
}
.forward-slip-sheet .bottomGrid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8mm;
  align-items: end;
}
.forward-slip-sheet .bottomRow { display: flex; align-items: baseline; gap: 4mm; }
.forward-slip-sheet .bottomRow .field { height: 16px; }
.forward-slip-sheet .certGrid {
  display: grid;
  grid-template-columns: 1fr 72mm;
  gap: 6mm;
  align-items: end;
}
.forward-slip-sheet .certText { font-size: 10px; line-height: 1.2; }
.forward-slip-sheet .sigBox { margin-top: 20px; }
.forward-slip-sheet .sigBox .field.text span { text-align: center; }
.forward-slip-sheet .sigBox .sigLabel { font-size: 10px; margin-bottom: 2px; text-align: center; margin-top: 5px }
    `;
    document.head.appendChild(style);
  }

  function getOrCreatePrintRoot() {
    let root = document.getElementById('forward-slip-print-root');
    if (root) return root;
    root = document.createElement('div');
    root.id = 'forward-slip-print-root';
    document.body.appendChild(root);
    return root;
  }

  function adjustSlipFitForPrint() {
    if (!document.body.classList.contains('forward-slip-printing')) return;
    const root = document.getElementById('forward-slip-print-root');
    const sheet = root?.querySelector('.forward-slip-sheet');
    if (!root || !sheet) return;

    const baseScale = 0.8;
    const pad = 0.94;

    sheet.style.removeProperty('transform');
    sheet.style.removeProperty('transform-origin');
    sheet.style.transform = 'scale(1)';
    sheet.style.transformOrigin = 'top center';
    void sheet.offsetHeight;

    const contentH = sheet.scrollHeight;
    const rs = getComputedStyle(root);
    const padY =
      (parseFloat(rs.paddingTop) || 0) + (parseFloat(rs.paddingBottom) || 0);
    const availableH = root.clientHeight - padY;
    if (!contentH || !availableH) {
      sheet.style.transform = `scale(${baseScale})`;
      sheet.style.transformOrigin = 'top center';
      return;
    }

    let scale = Math.min(baseScale, (availableH * pad) / contentH);
    if (!Number.isFinite(scale) || scale <= 0) scale = baseScale;

    sheet.style.transform = `scale(${scale})`;
    sheet.style.transformOrigin = 'top center';
  }

  window.addEventListener('beforeprint', adjustSlipFitForPrint);

  function cleanupAfterPrint() {
    document.body.classList.remove('forward-slip-printing');
    const root = document.getElementById('forward-slip-print-root');
    if (root) root.innerHTML = '';

    // Note: we intentionally do NOT reset slip_printed_flag here;
    // the flag should stay "1" once PRINT SLIP has been triggered
    // for the current voucher. It is reset when a new voucher row
    // is loaded into the form.
  }

  // Disable printing until COA requirements are selected (if applicable).
  syncPrintButtonState();
  const coaHiddenInput = document.getElementById('selected_coa_options_forward');
  const coaDisplayInput = document.getElementById('selected_coa_options_display_forward');
  if (coaHiddenInput) {
    coaHiddenInput.addEventListener('input', syncPrintButtonState);
    coaHiddenInput.addEventListener('change', syncPrintButtonState);
  }
  if (coaDisplayInput) {
    coaDisplayInput.addEventListener('input', syncPrintButtonState);
    coaDisplayInput.addEventListener('change', syncPrintButtonState);
  }

  function closeNatureOfClaimModal() {
    if (natureModal) natureModal.style.display = 'none';
    if (natureOverlay) natureOverlay.style.display = 'none';
  }

  function openNatureOfClaimModal() {
    if (!natureModal || !natureOverlay) {
      printForwardSlip();
      return;
    }
    const existing = String(natureFormInput?.value || '').trim();
    if (natureModalInput) {
      natureModalInput.value = existing;
    }
    natureModal.style.display = 'block';
    natureOverlay.style.display = 'block';
    if (natureModalInput) {
      setTimeout(function () {
        natureModalInput.focus();
        natureModalInput.select();
      }, 50);
    }
  }

  async function printForwardSlip() {
    if (!hasSelectedCoaRequirements()) {
      if (typeof showNotify === 'function') {
        showNotify('Please select COA requirements before printing the slip.', 'warning', 3500);
      } else {
        alert('Please select COA requirements before printing the slip.');
      }
      syncPrintButtonState();
      return;
    }

    const data = getForwardSlipData();
    ensurePrintCss();
    const root = getOrCreatePrintRoot();

    let html = '';
    try {
      html = await fetchSlipHtml(data);
    } catch (e) {
      console.error(e);
      if (typeof showNotify === 'function') {
        showNotify('Unable to load slip template.', 'error', 3500);
      } else {
        alert('Unable to load slip template.');
      }
      return;
    }

    // Mark slip as printed for the current voucher (client-side flag + hidden input)
    const slipFlagInput = document.getElementById('slip_printed_flag');
    if (slipFlagInput) {
      slipFlagInput.value = '1';
    }

    // Enable Forward button now that slip has been printed
    const dynamicBtn = document.querySelector('.btn-dynamic');
    if (dynamicBtn) {
      dynamicBtn.disabled = false;
      dynamicBtn.classList.remove('btn-disabled-forward');
    }

    // Inform user using existing notification system
    if (typeof showNotify === 'function') {
      showNotify('Slip generated. You may now forward this voucher.', 'success', 2500);
    }

    root.innerHTML = html;

    // Enter print mode (same page) and call window.print()
    document.body.classList.add('forward-slip-printing');

    // Ensure DOM updates apply before opening print dialog
    requestAnimationFrame(function () {
      setTimeout(function () {
        try {
          window.print();
        } finally {
          // Some browsers don't fire afterprint reliably; do both.
          const afterPrintOnce = function () {
            window.removeEventListener('afterprint', afterPrintOnce);
            cleanupAfterPrint();
          };
          window.addEventListener('afterprint', afterPrintOnce);
          setTimeout(cleanupAfterPrint, 15000);
        }
      }, 50);
    });
  }

  printBtn.addEventListener('click', function () {
    if (!hasSelectedCoaRequirements()) {
      if (typeof showNotify === 'function') {
        showNotify('Please select COA requirements before printing the slip.', 'warning', 3500);
      } else {
        alert('Please select COA requirements before printing the slip.');
      }
      syncPrintButtonState();
      return;
    }
    openNatureOfClaimModal();
  });

  function confirmNatureOfClaimAndPrint() {
    const value = String(natureModalInput?.value || '').trim();
    if (!value) {
      if (typeof showNotify === 'function') {
        showNotify('Please enter the nature of claim.', 'warning', 3000);
      } else {
        alert('Please enter the nature of claim.');
      }
      if (natureModalInput) natureModalInput.focus();
      return;
    }
    if (natureFormInput) {
      natureFormInput.value = value;
    }
    closeNatureOfClaimModal();
    printForwardSlip();
  }

  if (natureModalConfirm) {
    natureModalConfirm.addEventListener('click', confirmNatureOfClaimAndPrint);
  }
  if (natureModalCancel) {
    natureModalCancel.addEventListener('click', closeNatureOfClaimModal);
  }
  if (natureModalClose) {
    natureModalClose.addEventListener('click', closeNatureOfClaimModal);
  }
  if (natureOverlay) {
    natureOverlay.addEventListener('click', closeNatureOfClaimModal);
  }
  if (natureModalInput) {
    natureModalInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        confirmNatureOfClaimAndPrint();
      }
    });
  }

  // Enforce: slip must be printed before allowing "Forward" submit
  const forwardForm = document.getElementById('encoded_voucher_form');
  if (forwardForm) {
    forwardForm.addEventListener('submit', function (e) {
      const dynamicBtn = document.querySelector('.btn-dynamic');
      const isForward =
        dynamicBtn &&
        (dynamicBtn.getAttribute('name') === 'forward_voucher' ||
          dynamicBtn.getAttribute('name') === 'forward_voucher'.toUpperCase());

      if (!isForward) {
        return true; // Editing, not forwarding
      }

      const slipFlagInput = document.getElementById('slip_printed_flag');
      const printed = slipFlagInput && slipFlagInput.value === '1';

      if (!printed) {
        e.preventDefault();
        if (typeof showNotify === 'function') {
          showNotify('Please print the slip before forwarding.', 'warning', 3500);
        } else {
          alert('Please print the slip before forwarding.');
        }
        return false;
      }

      return true;
    });
  }
})();



