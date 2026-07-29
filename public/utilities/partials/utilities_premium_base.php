<style>
    .util-premium-page.main--voucher-dashboard,
    .main--voucher-dashboard.util-premium-page {
        height: calc(100dvh - 4rem);
        max-height: calc(100dvh - 4rem);
        overflow: hidden;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        min-width: 0;
    }

    .util-premium-page .voucher-dashboard-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0;
        flex-shrink: 0;
    }

    .util-premium-page .voucher-dashboard-title {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: #0f172a;
        margin: 0;
    }

    .util-premium-page .voucher-dashboard-subtitle {
        color: rgb(75 85 99 / 0.9);
        margin: 0.25rem 0 0;
        font-size: 0.9375rem;
        line-height: 1.45;
        max-width: 52rem;
    }

    .util-premium-page .voucher-dashboard-header__text {
        flex: 1 1 240px;
        min-width: 0;
    }

    .util-premium-page .voucher-dashboard-header__actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .util-premium-page .voucher-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    .util-premium-page .voucher-card--filter {
        flex-shrink: 0;
    }

    .util-premium-page .voucher-card--table {
        position: relative;
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }

    .util-premium-page .voucher-card-title {
        font-size: 1.0625rem;
        font-weight: 700;
        color: #0f172a;
        padding: 1rem 1.25rem;
        margin: 0;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .util-premium-page .voucher-card--table .content-wrapper {
        flex: 1;
        min-height: 0;
        overflow: auto;
        max-height: none;
    }

    .util-premium-page .voucher-card--table .content-wrapper.content-wrapper--flush {
        padding: 0;
    }

    .util-premium-page .voucher-card--table .content-wrapper.content-wrapper--padded {
        padding: 1.25rem;
    }

    .util-premium-page .voucher-pagination-footer {
        position: static;
        background: #fff;
        border-top: 1px solid rgba(229, 231, 235, 1);
        padding: 10px 0 0;
        margin-top: auto;
        flex-shrink: 0;
    }

    .util-premium-page .table.content_table--dashboard {
        border-collapse: separate;
        border-spacing: 0;
    }

    .util-premium-page .table.content_table--dashboard thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.75rem 0.875rem;
        white-space: nowrap;
    }

    .util-premium-page .table.content_table--dashboard tbody td {
        padding: 0.625rem 0.875rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.875rem;
        color: #1e293b;
        vertical-align: middle;
    }

    .util-premium-page .table.content_table--dashboard tbody tr:hover td {
        background: #f8fbff;
    }

    .util-premium-page .filter-toolbar-form .filter-search input[type="text"],
    .util-premium-page .filter-toolbar-form .filter-search input[type="search"] {
        border-radius: 10px;
        border: 1px solid #d4dbe6;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        min-height: 42px;
        padding: 0 0.875rem;
    }

    .util-premium-page .filter-icon-btn {
        border-radius: 10px;
    }

    .util-premium-page #popupForm2 .popupForm-box__container,
    .util-premium-page #popupForm .popupForm-box__container {
        max-width: 960px;
        max-height: 85vh;
        border-radius: 14px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .util-premium-page #popupForm2 .popupForm-header__container,
    .util-premium-page #popupForm .popupForm-header__container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        border-bottom: 1px solid #e9ecef;
        background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
    }

    .util-premium-page #popupForm2 .popupForm-header__container p,
    .util-premium-page #popupForm .popupForm-header__container p {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #1f2937;
    }

    .util-premium-page .util-header-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 10px;
        padding: 0.5rem 1rem;
        font-weight: 600;
        font-size: 0.875rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        border: none;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }

    .util-premium-page .util-header-btn--primary {
        background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
        color: #fff;
    }

    .util-premium-page .util-header-btn--primary:hover {
        filter: brightness(1.05);
    }

    .util-premium-page .calc-filter-grid {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 0.875rem 1rem;
        padding: 1rem 1.25rem;
    }

    .util-premium-page .calc-filter-field label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 0.375rem;
    }

    .util-premium-page .calc-filter-field select,
    .util-premium-page .calc-filter-field input[type="search"],
    .util-premium-page .calc-filter-field input[type="text"] {
        border-radius: 10px;
        border: 1px solid #d4dbe6;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        min-height: 38px;
        box-sizing: border-box;
        background: #fff;
    }

    .util-premium-page .calc-filter-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
    }

    .util-premium-page .calc-filter-search {
        flex: 1 1 280px;
        min-width: 0;
    }

    .util-premium-page .calc-filter-search .calc-filter-field {
        width: 100%;
    }

    .util-premium-page .calc-filter-search-row {
        display: flex;
        gap: 0.5rem;
        align-items: flex-end;
    }

    .util-premium-page .calc-filter-search-row input {
        flex: 1;
        min-width: 0;
    }

    .util-premium-page .calc-meta {
        margin: 0 0 0.75rem;
        color: #64748b;
        font-size: 0.8125rem;
        line-height: 1.5;
    }

    .util-premium-page .calc-rules-list {
        margin: 0 0 1rem;
        padding-left: 1.125rem;
        color: #64748b;
        font-size: 0.8125rem;
        line-height: 1.55;
    }

    .util-premium-page .calc-detail-block {
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
    }

    .util-premium-page .calc-detail-block h4 {
        margin: 0 0 0.5rem;
        font-size: 0.9375rem;
        font-weight: 700;
        color: #0f172a;
    }

    .util-premium-page #calculationBreakdownTable th,
    .util-premium-page #calculationBreakdownTable td,
    .util-premium-page #calculationEventsTable th,
    .util-premium-page #calculationEventsTable td,
    .util-premium-page #calculationSegmentsTable th,
    .util-premium-page #calculationSegmentsTable td {
        border-bottom: 1px solid #f1f5f9;
        padding: 0.625rem 0.75rem;
        font-size: 0.8125rem;
        vertical-align: top;
    }

    .util-premium-page #calculationBreakdownTable thead th,
    .util-premium-page #calculationEventsTable thead th,
    .util-premium-page #calculationSegmentsTable thead th {
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        color: #475569;
        font-weight: 700;
        font-size: 0.6875rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .util-premium-page .calculation-trace-btn {
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        padding: 0.35rem 0.75rem;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.75rem;
        font-weight: 600;
        transition: background 120ms ease, border-color 120ms ease, color 120ms ease;
    }

    .util-premium-page .calculation-trace-btn:hover {
        background: #f8fbff;
        border-color: #c7d7fe;
        color: #1d4ed8;
    }

    .util-premium-page .section-table-scroll {
        overflow-x: auto;
        width: 100%;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    .util-premium-page .section-table-scroll table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
    }

    .util-premium-page #dashboardRefreshStatus {
        font-size: 0.75rem;
        color: #64748b;
        margin: 0;
    }

    .util-premium-page .util-alert {
        padding: 0.875rem 1rem;
        border-radius: 10px;
        margin-bottom: 1rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid transparent;
        flex-shrink: 0;
    }

    .util-premium-page .util-alert.success {
        background: #ecfdf5;
        color: #065f46;
        border-color: #a7f3d0;
    }

    .util-premium-page .util-alert.error {
        background: #fef2f2;
        color: #991b1b;
        border-color: #fecaca;
    }

    .util-premium-page .util-alert.warning {
        background: #fffbeb;
        color: #92400e;
        border-color: #fde68a;
    }

    .util-premium-page .voucher-card-title__label .ri-icon {
        font-size: 1.25rem;
        color: #4f46e5;
    }

    .util-premium-page .util-list-filter-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }

    .util-premium-page .content-wrapper.util-content-with-subtabs {
        padding: 0;
        overflow: hidden;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    .util-premium-page .util-subtabs-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        flex-shrink: 0;
    }

    .util-premium-page .util-subtabs-bar {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.375rem;
        padding: 0.25rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .util-premium-page .util-subtab-btn {
        border: none;
        background: transparent;
        color: #64748b;
        padding: 0.5rem 0.875rem;
        border-radius: 9px;
        cursor: pointer;
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1.2;
        transition: background 120ms ease, color 120ms ease, box-shadow 120ms ease;
        white-space: nowrap;
    }

    .util-premium-page .util-subtab-btn:hover {
        background: #e2e8f0;
        color: #334155;
    }

    .util-premium-page .util-subtab-btn.is-active {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: #fff;
        box-shadow: 0 2px 8px rgba(79, 70, 229, 0.28);
    }

    .util-premium-page .util-subtabs-hint {
        margin: 0;
        font-size: 0.75rem;
        color: #64748b;
        line-height: 1.4;
    }

    .util-premium-page .util-subtab-panels {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    .util-premium-page .util-subtab-panel {
        display: none;
        flex: 1;
        min-height: 0;
        flex-direction: column;
    }

    .util-premium-page .util-subtab-panel.is-active {
        display: flex;
    }

    .util-premium-page .util-subtab-panel__body {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 1.25rem;
        -webkit-overflow-scrolling: touch;
    }

    .util-premium-page .util-flash-wrap {
        padding: 1rem 1.25rem 0;
        flex-shrink: 0;
    }

    .util-premium-page .util-flash-wrap .util-alert {
        margin-bottom: 0;
    }

    @media (max-width: 900px) {
        .util-premium-page .util-subtabs-toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .util-premium-page .util-subtabs-hint {
            text-align: center;
        }
    }
</style>
