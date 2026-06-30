.status-report-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    padding: 4px 0;
}

.status-report-stat {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 14px;
    background: #fff;
}

.status-report-stat__label {
    display: block;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 4px;
}

.status-report-stat__value {
    font-size: 24px;
    color: #111827;
}

.status-report-stat--returned {
    background: linear-gradient(180deg, #fef2f2 0%, #fff 100%);
}

.status-report-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
    background-color: #fff;
    padding: 16px 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    color: rgb(75 85 99 / 0.9);
}

.status-report-filter-bar__form {
    width: 100%;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.status-report-filter-bar__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 180px;
}

.status-report-filter-bar__field--grow {
    flex: 1;
    min-width: 240px;
}

.status-report-filter-bar__field label {
    font-size: 14px;
    font-weight: 500;
}

.status-report-filter-bar__field select,
.status-report-filter-bar__field input[type="text"] {
    padding: 8px 10px;
    border-radius: 5px;
    border: 1px solid rgb(209 213 219 / 1);
    font-size: 14px;
    min-height: 38px;
}

.status-report-filter-bar__apply,
.status-report-filter-bar__print {
    border: none;
    border-radius: 5px;
    padding: 8px 16px;
    height: 38px;
    font-size: 14px;
    cursor: pointer;
}

.status-report-filter-bar__apply {
    background-color: #0d6efd;
    color: #fff;
}

.status-report-filter-bar__print {
    background-color: #fff;
    color: rgb(75 85 99 / 0.9);
    border: 1px solid rgb(209 213 219 / 1);
}

.status-report-table-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.status-report-table-meta {
    margin: 0;
    font-size: 12px;
    color: rgb(75 85 99 / 0.75);
}

.status-report-print-header {
    display: none;
    margin-bottom: 18px;
}

.status-report-print-banner {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 18px 20px;
    border: 2px solid #111827;
    border-radius: 10px;
    background: #fff;
}

.status-report-print-banner__eyebrow {
    margin: 0 0 4px;
    font-size: 11px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #6b7280;
}

.status-report-print-banner h1 {
    margin: 0;
    font-size: 22px;
    line-height: 1.2;
    color: #111827;
}

.status-report-print-banner__subtitle {
    margin: 6px 0 0;
    font-size: 13px;
    color: #4b5563;
}

.status-report-print-banner__meta {
    display: grid;
    gap: 8px;
    min-width: 210px;
}

.status-report-print-banner__meta div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 12px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 4px;
}

.status-report-print-banner__meta span {
    color: #6b7280;
}

.status-report-print-banner__meta strong {
    color: #111827;
    font-weight: 700;
}

.status-report-print-search {
    margin: 10px 0 0;
    font-size: 12px;
    color: #374151;
}

.status-report-print-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin-top: 14px;
}

.status-report-print-summary__item {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 10px 12px;
    background: #f9fafb;
}

.status-report-print-summary__item span {
    display: block;
    font-size: 11px;
    color: #6b7280;
    margin-bottom: 4px;
}

.status-report-print-summary__item strong {
    font-size: 20px;
    color: #111827;
}

.status-report-print-summary__item--returned {
    background: #fef2f2;
}

.status-report-print-table-title {
    display: none;
    margin: 0 0 10px;
    font-size: 15px;
    font-weight: 700;
    color: #111827;
}

.status-report-print-footer {
    display: none;
    margin-top: 12px;
    font-size: 11px;
    color: #6b7280;
    text-align: right;
}

.print-only {
    display: none;
}

.status-report-table-wrap {
    overflow: auto;
    max-height: 70vh;
}

@media print {
    @page {
        size: A4 landscape;
        margin: 12mm;
    }

    html,
    body {
        height: auto !important;
        overflow: visible !important;
        position: static !important;
    }

    .sidebar,
    .header,
    .no-print {
        display: none !important;
    }

    .print-only,
    .status-report-print-header,
    .status-report-print-table-title,
    .status-report-print-footer {
        display: block !important;
    }

    .status-report-print-summary {
        display: grid !important;
    }

    .main,
    .main--voucher-dashboard,
    .voucher-card,
    .status-report-table-card,
    .voucher-card--table {
        position: static !important;
        width: 100% !important;
        height: auto !important;
        max-height: none !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        flex: none !important;
        box-shadow: none !important;
        border: none !important;
        background: #fff !important;
    }

    body {
        background: #fff !important;
        color: #111827 !important;
        font-family: "Segoe UI", Arial, sans-serif !important;
    }

    .content-wrapper,
    .status-report-table-wrap,
    .content_table,
    .content_table--dashboard {
        position: static !important;
        height: auto !important;
        max-height: none !important;
        min-height: 0 !important;
        overflow: visible !important;
    }

    #statusReportTable,
    #returnedVouchersTable {
        display: table !important;
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        border: none;
        overflow: visible !important;
    }

    #statusReportTable thead,
    #returnedVouchersTable thead {
        display: table-header-group;
    }

    #statusReportTable tbody,
    #returnedVouchersTable tbody {
        display: table-row-group;
    }

    #statusReportTable tr,
    #returnedVouchersTable tr {
        display: table-row !important;
        page-break-inside: avoid;
        break-inside: avoid-page;
    }

    #statusReportTable th,
    #statusReportTable td,
    #returnedVouchersTable th,
    #returnedVouchersTable td {
        display: table-cell !important;
        border: none;
        border-bottom: 1px solid #e5e7eb;
        padding: 7px 10px;
        vertical-align: top;
    }

    #statusReportTable th,
    #returnedVouchersTable th {
        background: transparent !important;
        color: #111827 !important;
        border-bottom: 2px solid #111827;
        font-weight: 700;
    }
}
