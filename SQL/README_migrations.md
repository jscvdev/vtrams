# Database Migrations

This directory contains SQL migration files for the DVS (Disbursement Voucher System) database.

## Migration Files

### system_settings Migration

**Files:**
- `migration_system_settings.sql` - SQL migration file
- `migrate_system_settings.php` - PHP migration script (recommended)
- `migration_system_settings_rollback.sql` - Rollback SQL file

**Purpose:** Creates the `system_settings` table required for the Settings page (`public/vouchers/settings.php`).

**Table Structure:**
- `id` (int, primary key) - Unique identifier (always 1)
- `system_name` (varchar) - Full system name
- `page_title` (varchar) - Default page title
- `company_name` (varchar) - Organization/company name
- `browser_title` (varchar) - Browser tab title
- `header_text` (varchar) - Header display text
- `created_at` (timestamp) - Record creation timestamp
- `updated_at` (timestamp) - Last update timestamp

**Default Values:**
- system_name: 'PENRO Disbursement Voucher System'
- page_title: 'PENRO Disbursement Voucher System'
- company_name: 'Provincial Environment and Natural Resources Office'
- browser_title: 'PENRO-DVS'
- header_text: 'PENRO Disbursement Voucher System v1.0'

**Usage:**
```sql
-- Run this in your database (phpMyAdmin, MySQL CLI, etc.)
SOURCE migration_system_settings.sql;
```

Or copy and paste the contents into your SQL client.

**Rollback:**
If you need to remove the table, use `migration_system_settings_rollback.sql`

## How to Apply Migrations

### Option 1: PHP Migration Script (Recommended)
The easiest way to run the migration is using the PHP script:

1. **Via Browser:**
   - Navigate to: `http://your-domain/vtrams/SQL/migrate_system_settings.php`
   - The script will automatically create the table and insert default values

2. **Via Command Line:**
   ```bash
   cd c:\xampp\htdocs\vtrams\SQL
   php migrate_system_settings.php
   ```

### Option 2: SQL File Migration

1. **Via phpMyAdmin:**
   - Select your database
   - Go to SQL tab
   - Copy and paste the contents of `migration_system_settings.sql`
   - Click "Go"

2. **Via MySQL Command Line:**
   ```bash
   mysql -u username -p database_name < migration_system_settings.sql
   ```

3. **Via MySQL Workbench:**
   - Open the migration file
   - Execute the script

## Notes

- The migration uses `CREATE TABLE IF NOT EXISTS` to prevent errors if the table already exists
- The `INSERT` statement uses a `WHERE NOT EXISTS` clause to prevent duplicate entries
- The table is designed to have only one record (id = 1)
- All fields are required and have default values
