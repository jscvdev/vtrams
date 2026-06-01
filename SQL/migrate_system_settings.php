<?php
/**
 * Database Migration Script: system_settings Table
 * 
 * This script creates the system_settings table required for the Settings page.
 * Run this script once to set up the database table.
 * 
 * Usage: php migrate_system_settings.php
 * Or access via browser: http://your-domain/vtrams/SQL/migrate_system_settings.php
 */

// Database configuration - adjust these to match your setup
require_once '../protected/dbconnection.inc.php';

// Check if database connection is available
if (!isset($pdo)) {
    die("Error: Database connection not available. Please check dbconnection.inc.php\n");
}

try {
    echo "Starting migration: system_settings table...\n\n";
    
    // Create table SQL
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS `system_settings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `system_name` varchar(255) NOT NULL DEFAULT 'PENRO Disbursement Voucher System',
      `page_title` varchar(255) NOT NULL DEFAULT 'PENRO Disbursement Voucher System',
      `company_name` varchar(255) NOT NULL DEFAULT 'Provincial Environment and Natural Resources Office',
      `browser_title` varchar(255) NOT NULL DEFAULT 'PENRO-DVS',
      `header_text` varchar(255) NOT NULL DEFAULT 'PENRO Disbursement Voucher System v1.0',
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    // Execute table creation
    $pdo->exec($createTableSQL);
    echo "✓ Table 'system_settings' created successfully.\n";
    
    // Check if default record exists
    $checkQuery = "SELECT COUNT(*) FROM system_settings WHERE id = 1";
    $stmt = $pdo->query($checkQuery);
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Insert default settings
        $insertSQL = "
        INSERT INTO `system_settings` 
            (`id`, `system_name`, `page_title`, `company_name`, `browser_title`, `header_text`)
        VALUES 
            (1, 
            'PENRO Disbursement Voucher System',
            'PENRO Disbursement Voucher System',
            'Provincial Environment and Natural Resources Office',
            'PENRO-DVS',
            'PENRO Disbursement Voucher System v1.0')
        ";
        
        $pdo->exec($insertSQL);
        echo "✓ Default settings inserted successfully.\n";
    } else {
        echo "✓ Default settings already exist (skipping insert).\n";
    }
    
    // Verify the migration
    $verifyQuery = "SELECT * FROM system_settings WHERE id = 1";
    $stmt = $pdo->query($verifyQuery);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($settings) {
        echo "\n✓ Migration completed successfully!\n\n";
        echo "Current settings:\n";
        echo "  - System Name: " . htmlspecialchars($settings['system_name']) . "\n";
        echo "  - Page Title: " . htmlspecialchars($settings['page_title']) . "\n";
        echo "  - Company Name: " . htmlspecialchars($settings['company_name']) . "\n";
        echo "  - Browser Title: " . htmlspecialchars($settings['browser_title']) . "\n";
        echo "  - Header Text: " . htmlspecialchars($settings['header_text']) . "\n";
    } else {
        echo "\n⚠ Warning: Migration completed but could not verify settings.\n";
    }
    
} catch (PDOException $e) {
    echo "\n✗ Migration failed with error:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "\nError Code: " . $e->getCode() . "\n";
    exit(1);
}

echo "\nYou can now access the Settings page at: /public/vouchers/settings.php\n";
?>
