# COA Options Implementation Summary

## Overview
This document summarizes the implementation of COA (Commission on Audit) options storage and retrieval in the voucher system. COA options are stored as JSON strings containing the selected checklist items from the COA requirements modal.

## Database Migration

### Migration Script
**File:** `SQL/migration_add_coa_options.sql`

**Changes:**
- Added `coa_options` column (TEXT, nullable) to the following tables:
  - `vouchers`
  - `voucher_incoming`
  - `voucher_receiving`
  - `voucher_sent`
  - `voucher_tracking`
  - `voucher_archives`
  - `voucher_action_logs`

**To Apply:**
```sql
-- Run the migration script in your database
SOURCE SQL/migration_add_coa_options.sql;
```

## Code Changes

### Model Files Updated

1. **`protected/handler/voucher_module/voucher.model.inc.php`**
   - Updated `voucher_log_to_document_tracking()` to accept `$coa_options` parameter (optional, defaults to null)

2. **`protected/handler/voucher_forward_module/voucher_forward.model.inc.php`**
   - Updated `voucher_pending_to_incoming()` to accept and store `$coa_options`
   - Updated `voucher_pending_to_sent()` to accept and store `$coa_options`

3. **`protected/handler/voucher_incoming_module/voucher_incoming.model.inc.php`**
   - Updated `voucher_insert_into_receving()` to accept and store `$coa_options`

4. **`protected/handler/action_module/voucher_action.model.inc.php`**
   - Updated `voucher_document_user_action()` to accept `$coa_options` parameter (optional, defaults to null)

### Controller Files Updated

1. **`protected/handler/voucher_module/voucher.ctrl.inc.php`**
   - Updated `voucher_document_tracking_logging()` to pass `$coa_options` parameter

2. **`protected/handler/voucher_forward_module/voucher_forward.ctrl.inc.php`**
   - Updated `voucher_move_to_incoming()` to pass `$coa_options` parameter
   - Updated `voucher_move_to_sent()` to pass `$coa_options` parameter

3. **`protected/handler/voucher_incoming_module/voucher_incoming.ctrl.inc.php`**
   - Updated `voucher_move_to_receiving()` to pass `$coa_options` parameter

4. **`protected/handler/action_module/voucher_action.ctrl.inc.php`**
   - Updated `voucher_log_user_action()` to accept and pass `$coa_options` parameter (optional)

### Handler Files Updated

1. **`protected/handler/voucher_module/voucher_handler.php`**
   - Updated to pass `null` for `coa_options` when creating vouchers (COA options are only added when forwarding)

2. **`protected/handler/voucher_forward_module/voucher_forward_handler.php`**
   - Added `selected_coa_options_forward` to `$keyList` and `$variable_map`
   - Updated to capture `coa_options` from POST data (`selected_coa_options_forward`)
   - Updated to pass `coa_options` to `voucher_move_to_incoming()` and `voucher_move_to_sent()`

3. **`protected/handler/voucher_incoming_module/voucher_incoming_handler.php`**
   - Added `selected_coa_options` to `$keyList` and `$variable_map`
   - Updated to capture `coa_options` from POST data or fetch from database if not provided
   - Updated to pass `coa_options` to `voucher_move_to_receiving()`

### Frontend Files Updated

1. **`public/vouchers/voucher_incoming.php`**
   - Added hidden `<td>` element to store `coa_options` from database
   - Updated JavaScript to extract `coa_options` from row data
   - Updated JavaScript to populate COA options display fields when a row is clicked
   - COA options are displayed as comma-separated labels in the read-only input field

## Data Flow

### When Forwarding a Voucher (voucher.php)
1. User selects Category → Subsection → Options (all options must be checked)
2. Selected options are stored as JSON in `selected_coa_options_forward` hidden input
3. On form submission, `voucher_forward_handler.php` captures the JSON string
4. COA options are saved to:
   - `voucher_incoming` table
   - `voucher_sent` table
   - `voucher_tracking` table (if updated)

### When Receiving a Voucher (voucher_incoming.php)
1. Data is fetched from `voucher_incoming` table (includes `coa_options`)
2. When a row is clicked, COA options are extracted and displayed in the form
3. If COA options exist, they are preserved when receiving
4. COA options are saved to `voucher_receiving` table

### COA Options JSON Format
```json
[
  {
    "id": "1",
    "value": "Authority of the accountable officer...",
    "label": "Authority of the accountable officer..."
  },
  {
    "id": "2",
    "value": "Another requirement...",
    "label": "Another requirement..."
  }
]
```

## Testing Checklist

- [ ] Run database migration script
- [ ] Test forwarding voucher with COA options selected
- [ ] Verify COA options are saved to `voucher_incoming` table
- [ ] Test receiving voucher and verify COA options are displayed
- [ ] Verify COA options are preserved when receiving
- [ ] Test forwarding voucher without COA options (should work with null)
- [ ] Verify existing vouchers without COA options still work

## Notes

- COA options are optional - vouchers can exist without them
- When forwarding, COA options are required (validation is handled in frontend)
- When receiving, COA options are preserved from the incoming record
- COA options are stored as JSON strings in the database for flexibility
- The display format shows comma-separated labels for readability







