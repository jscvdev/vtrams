<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VTRAMS Patch Console</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --panel: #111827;
            --card: #1e293b;
            --line: #334155;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #10b981;
            --accent-dim: #064e3b;
            --warn: #f59e0b;
            --danger: #f87171;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100%;
            background: radial-gradient(1200px 600px at 10% -10%, #14532d55, transparent 50%),
                        radial-gradient(900px 500px at 100% 0%, #1e3a8a33, transparent 45%),
                        var(--bg);
            color: var(--text);
            font-family: "Segoe UI", system-ui, sans-serif;
        }
        .wrap {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px 20px 40px;
        }
        .hero h1 {
            margin: 0 0 6px;
            font-size: 28px;
            letter-spacing: -0.03em;
        }
        .hero p {
            margin: 0;
            color: var(--muted);
            max-width: 62ch;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 14px;
            margin-top: 22px;
        }
        .card {
            background: linear-gradient(180deg, #1e293bcc, #0f172acc);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-height: 170px;
        }
        .card h2 {
            margin: 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card h2 i { color: var(--accent); }
        .card p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
            flex: 1;
        }
        .actions {
            display: flex;
            gap: 8px;
        }
        button {
            border: 0;
            border-radius: 10px;
            padding: 9px 12px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-preview {
            background: #334155;
            color: #fff;
        }
        .btn-apply {
            background: var(--accent);
            color: #042f22;
        }
        .btn-preview:hover { background: #475569; }
        .btn-apply:hover { filter: brightness(1.05); }
        button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .console-wrap {
            margin-top: 22px;
            background: #020617;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
        }
        .console-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border-bottom: 1px solid var(--line);
            color: var(--muted);
            font-size: 12px;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 8px;
            border-radius: 999px;
            background: var(--accent-dim);
            color: #6ee7b7;
            font-weight: 600;
        }
        .pill.is-apply {
            background: #7c2d12;
            color: #fdba74;
        }
        iframe, pre#fallback {
            display: block;
            width: 100%;
            height: 420px;
            border: 0;
            background: #020617;
            color: #bbf7d0;
            margin: 0;
            padding: 14px;
            font: 12px/1.5 ui-monospace, Consolas, monospace;
            white-space: pre-wrap;
        }
        .hint {
            margin-top: 10px;
            color: var(--muted);
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1>VTRAMS patch console</h1>
        <p>Preview first, then apply. Apply writes to the database. Use one phase at a time when you only need a specific fix.</p>
    </div>
    <div class="grid">
        <article class="card">
            <h2><i class="ri-stack-line"></i>All phases</h2>
            <p>Run every maintenance step in order: processed logs, receive-after-return, duplicate receives, offices, amounts, remarks.</p>
            <div class="actions">
                <button class="btn-preview" type="button" data-only="all" data-apply="0">Preview</button>
                <button class="btn-apply" type="button" data-only="all" data-apply="1">Apply</button>
            </div>
        </article>
        <article class="card">
            <h2><i class="ri-checkbox-circle-line"></i>Processed by logs</h2>
            <p>Backfill missing cashier Processed by action logs from archives and sync tracking history / processing time.</p>
            <div class="actions">
                <button class="btn-preview" type="button" data-only="processed" data-apply="0">Preview</button>
                <button class="btn-apply" type="button" data-only="processed" data-apply="1">Apply</button>
            </div>
        </article>
        <article class="card">
            <h2><i class="ri-inbox-archive-line"></i>Receive after return</h2>
            <p>Insert missing Received by after Returned by, and convert Return-after-Return (wrong re-forward) to Receive + Forward.</p>
            <div class="actions">
                <button class="btn-preview" type="button" data-only="receive" data-apply="0">Preview</button>
                <button class="btn-apply" type="button" data-only="receive" data-apply="1">Apply</button>
            </div>
        </article>
        <article class="card">
            <h2><i class="ri-filter-off-line"></i>Duplicate receives</h2>
            <p>Remove consecutive Received by the same person, and orphan Received by lines after Processed/Paid (PN-26-06-0067 Sol Opena).</p>
            <div class="actions">
                <button class="btn-preview" type="button" data-only="dup_receive" data-apply="0">Preview</button>
                <button class="btn-apply" type="button" data-only="dup_receive" data-apply="1">Apply</button>
            </div>
        </article>
        <article class="card">
            <h2><i class="ri-building-line"></i>Office labels</h2>
            <p>Correct process_history office labels using the actor’s registered office in user_group.</p>
            <div class="actions">
                <button class="btn-preview" type="button" data-only="offices" data-apply="0">Preview</button>
                <button class="btn-apply" type="button" data-only="offices" data-apply="1">Apply</button>
            </div>
        </article>
        <article class="card">
            <h2><i class="ri-money-dollar-circle-line"></i>Amount decimals</h2>
            <p>Normalize whole-number amounts to two decimal places across voucher tables.</p>
            <div class="actions">
                <button class="btn-preview" type="button" data-only="amounts" data-apply="0">Preview</button>
                <button class="btn-apply" type="button" data-only="amounts" data-apply="1">Apply</button>
            </div>
        </article>
        <article class="card">
            <h2><i class="ri-chat-off-line"></i>Empty remarks</h2>
            <p>Clear empty Name: segments in combined remarks, then rebuild remaining remarks from action logs.</p>
            <div class="actions">
                <button class="btn-preview" type="button" data-only="remarks" data-apply="0">Preview</button>
                <button class="btn-apply" type="button" data-only="remarks" data-apply="1">Apply</button>
            </div>
        </article>
    </div>
    <div class="console-wrap">
        <div class="console-bar">
            <span>Output</span>
            <span class="pill" id="modePill">Idle</span>
        </div>
        <iframe id="runFrame" title="Patch output"></iframe>
    </div>
    <p class="hint">Engine file remains <code>SQL/patch_fix.php</code>. This page only starts a preview or apply run.</p>
</div>
<script>
    const frame = document.getElementById('runFrame');
    const pill = document.getElementById('modePill');
    const buttons = Array.from(document.querySelectorAll('button[data-only]'));

    function setBusy(busy) {
        buttons.forEach(function(btn) { btn.disabled = busy; });
    }

    function runPatch(only, apply) {
        const params = new URLSearchParams();
        params.set('embed', '1');
        if (only && only !== 'all') {
            params.set('only', only);
        }
        if (apply) {
            params.set('apply', '1');
        }
        pill.textContent = apply ? 'Applying ' + only : 'Preview ' + only;
        pill.classList.toggle('is-apply', !!apply);
        setBusy(true);
        frame.src = 'patch_fix.php?' + params.toString();
    }

    frame.addEventListener('load', function() {
        setBusy(false);
    });

    document.querySelector('.grid').addEventListener('click', function(event) {
        const btn = event.target.closest('button[data-only]');
        if (!btn) {
            return;
        }
        const only = btn.getAttribute('data-only') || 'all';
        const apply = btn.getAttribute('data-apply') === '1';
        if (apply && !window.confirm('Apply "' + only + '" to the database? Preview first if you have not.')) {
            return;
        }
        runPatch(only, apply);
    });
</script>
</body>
</html>
