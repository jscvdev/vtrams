-- =====================================================
-- Database Migration Rollback: system_settings Table
-- Created: 2026-01-27
-- Purpose: Rollback system_settings table migration
-- =====================================================

-- WARNING: This will delete all system settings data
-- Only run this if you need to completely remove the table

-- Drop the system_settings table
DROP TABLE IF EXISTS `system_settings`;

-- =====================================================
-- Rollback Complete
-- =====================================================
