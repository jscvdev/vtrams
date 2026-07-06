<style>
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
