<?php

declare(strict_types=1);

$tpt_formula_variant = $tpt_formula_variant ?? 'card';
?>
<style>
    .tpt-formula {
        display: grid;
        gap: 14px;
    }
    .tpt-formula__eq {
        margin: 0;
        padding: 14px 16px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #0f172a;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: clamp(0.9rem, 1.5vw, 1.05rem);
        font-weight: 650;
        letter-spacing: 0.01em;
        line-height: 1.55;
        overflow-x: auto;
    }
    .tpt-formula__eq span {
        color: #1e3a8a;
    }
    .tpt-formula__grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 10px;
    }
    .tpt-formula__item {
        margin: 0;
        padding: 12px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
    }
    .tpt-formula__item dt {
        margin: 0 0 4px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
    }
    .tpt-formula__item dd {
        margin: 0;
        font-size: 13px;
        line-height: 1.5;
        color: #0f172a;
    }
    .tpt-formula__item code {
        font-size: 12px;
        background: #eef2ff;
        color: #3730a3;
        padding: 1px 5px;
        border-radius: 4px;
    }
    .tpt-formula__steps {
        margin: 0;
        padding-left: 1.2rem;
        color: #334155;
        font-size: 13px;
        line-height: 1.55;
    }
    .tpt-formula__steps li + li {
        margin-top: 6px;
    }
    .tpt-formula__eq + .tpt-formula__eq {
        margin-top: 8px;
    }
</style>
<div class="tpt-formula">
    <pre class="tpt-formula__eq">TPT = <span>W(Start, End)</span> − Σ <span>W(Returnᵢ, NextReceiveᵢ)</span></pre>
    <pre class="tpt-formula__eq">PAPT = <span>W(AcctReceive, End)</span> − Σ <span>W(Returnᵢ, NextReceiveᵢ)</span></pre>
    <dl class="tpt-formula__grid">
        <div class="tpt-formula__item">
            <dt>Start</dt>
            <dd>First <code>Received by</code> at Planning, or TSD-ENGP for e-NGP. If that section has no receive, the first process-section receive is used. Encoding time before that receive is not counted.</dd>
        </div>
        <div class="tpt-formula__item">
            <dt>End</dt>
            <dd><code>voucher_tracking.datetime_status</code> when the voucher is paid / processed. The clock does not keep running after pay.</dd>
        </div>
        <div class="tpt-formula__item">
            <dt>W(a, b)</dt>
            <dd>Work-hour seconds from <em>a</em> to <em>b</em>. Only Monday–Thursday, 7:00 AM–6:00 PM (counted until 7:00 PM max). Friday–Sunday = 0.</dd>
        </div>
        <div class="tpt-formula__item">
            <dt>Calendar</dt>
            <dd>Days on the dashboard Calendar tab subtract or shorten <em>W</em>: whole-day holidays = 0; half day / custom hours use the remaining window.</dd>
        </div>
        <div class="tpt-formula__item">
            <dt>Return pause</dt>
            <dd>Each <code>Returned by</code> pauses TPT until the next processor (not the returner) logs <code>Received by</code>. That wait is subtracted.</dd>
        </div>
        <div class="tpt-formula__item">
            <dt>PAPT start</dt>
            <dd>First <code>Received by</code> at <strong>Accounting Unit</strong> for processing (Processor / Accountant III included). <strong>ICU receive does not start PAPT</strong> — ICU is a checking hop before accounting processing.</dd>
        </div>
        <div class="tpt-formula__item">
            <dt>Paid only</dt>
            <dd>Dashboard and status-report totals show TPT/PAPT for paid vouchers. Section times use the same <em>W</em> windows but only while that section holds the voucher.</dd>
        </div>
    </dl>
    <ol class="tpt-formula__steps">
        <li>Measure gross work seconds from Start to End (TPT) or from Accounting processing receive to End (PAPT).</li>
        <li>Subtract every return-wait interval (Return → next processor Receive) that falls in that window.</li>
        <li>If still waiting to be received after a return, freeze at the return time (do not count through “now”).</li>
        <li>Format the remaining seconds as days / hours / minutes / seconds (24-hour day units).</li>
    </ol>
</div>
