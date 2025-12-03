<?php
/**
 * Migration to update activity_type enum in wp_hs_library_activity table
 * Adds 'dnf', 'paused', and 'resumed' to the enum values
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Update the activity_type enum to include new values
 */
function hs_migrate_activity_enum() {
    global $wpdb;
    $table = $wpdb->prefix . 'hs_library_activity';

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if (!$table_exists) {
        return;
    }

    // Check current enum values
    $column_info = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'activity_type'");

    if (!$column_info) {
        return;
    }

    // Check if new values already exist in enum
    $enum_values = $column_info->Type;

    // If dnf, paused, or resumed are not in the enum, update it
    if (strpos($enum_values, 'dnf') === false ||
        strpos($enum_values, 'paused') === false ||
        strpos($enum_values, 'resumed') === false) {

        // Update the enum to include new values
        $sql = "ALTER TABLE $table
                MODIFY COLUMN activity_type
                ENUM('added', 'started', 'completed', 'removed', 'progress_update', 'dnf', 'paused', 'resumed')
                NOT NULL";

        $result = $wpdb->query($sql);

        if ($result !== false) {
            update_option('hs_activity_enum_migrated', '1.0');
            return true;
        }
    }

    return false;
}

/**
 * Check if migration needs to run and execute it
 */
function hs_check_and_run_activity_enum_migration() {
    $migrated = get_option('hs_activity_enum_migrated');

    if ($migrated !== '1.0') {
        hs_migrate_activity_enum();
    }
}

// Run migration check on admin init
add_action('admin_init', 'hs_check_and_run_activity_enum_migration');
// Also run on plugins_loaded to ensure it runs even if accessed via API
add_action('plugins_loaded', 'hs_check_and_run_activity_enum_migration');
