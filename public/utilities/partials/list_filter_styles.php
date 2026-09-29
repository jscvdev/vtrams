<style>
    .util-list-filter-embed {
        padding: 0.75rem 1.25rem 0.875rem;
        border-bottom: 1px solid #e2e8f0;
        background: #fff;
        flex-shrink: 0;
    }

    .util-list-filter-embed .filter-toolbar {
        padding: 0;
        margin: 0;
        border: none;
        background: transparent;
        box-shadow: none;
    }

    .util-list-filter-embed .util-list-filter-meta {
        padding: 0;
    }

    .util-list-filter-embed--compact {
        padding: 0;
        border: none;
        background: transparent;
        min-width: 0;
        max-width: 36rem;
        flex: 1 1 22rem;
    }

    .util-list-filter-embed--compact .filter-toolbar {
        width: 100%;
    }

    .util-list-filter-embed--compact .util-list-filter-form {
        flex-wrap: nowrap;
        align-items: center;
        gap: 0.4rem;
        width: 100%;
    }

    .util-list-filter-embed--compact .util-list-filter-form .filter-search {
        flex: 1 1 16rem;
        min-width: 12rem;
    }

    .util-list-filter-embed--compact .util-list-filter-form .filter-search input[type="text"] {
        min-height: 2rem;
        height: 2rem;
        padding: 0.25rem 0.55rem;
        font-size: 0.8125rem;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        box-sizing: border-box;
    }

    .util-list-filter-embed--compact .util-list-filter-status {
        min-width: 7.5rem;
        max-width: 9.5rem;
        flex: 0 1 8.5rem;
        gap: 0;
    }

    .util-list-filter-embed--compact .util-list-filter-status .form-custom-input {
        min-height: 2rem;
        height: 2rem;
        padding: 0.15rem 0.45rem;
        font-size: 0.75rem;
    }

    .util-list-filter-embed--compact .util-list-filter-btn,
    .util-list-filter-embed--compact .util-list-filter-clear {
        min-height: 2rem;
        height: 2rem;
        padding: 0 0.75rem;
        font-size: 0.75rem;
        align-self: center;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .util-list-filter-embed--compact .visually-hidden {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .util-list-filter-card {
        margin-bottom: 1.25rem;
    }

    .util-list-filter-form {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 0.75rem;
        width: 100%;
    }

    .util-list-filter-form .filter-search {
        flex: 1 1 220px;
        min-width: 0;
    }

    .util-list-filter-form .filter-search input[type="text"] {
        width: 100%;
        box-sizing: border-box;
    }

    .util-list-filter-status {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        flex: 0 0 auto;
        min-width: 130px;
    }

    .util-list-filter-status label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
    }

    .util-list-filter-status .form-custom-input {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        min-height: 38px;
        box-sizing: border-box;
    }

    .util-list-filter-btn,
    .util-list-filter-clear {
        flex-shrink: 0;
        align-self: flex-end;
        min-height: 38px;
    }

    .util-list-filter-meta {
        margin: 0.75rem 0 0 0;
        padding: 0 0.25rem;
        font-size: 0.8125rem;
        color: #64748b;
    }

    .util-list-filter-meta strong {
        color: #0f172a;
    }
</style>
<?php require __DIR__ . '/utilities_premium_base.php'; ?>
