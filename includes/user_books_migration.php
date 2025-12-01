<?php

// Migration to add timestamp columns to user_books table

if (!defined('ABSPATH')) {
    exit;
}

// Add timestamp columns to existing user_books table
function hs_migrate_user_books_timestamps() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_books';

    // Check if the columns already exist
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '" . DB_NAME . "'
        AND TABLE_NAME = '$table_name'
        AND COLUMN_NAME = 'date_added'");

    if (empty($row)) {
        // Add date_added column
        $wpdb->query("ALTER TABLE $table_name
            ADD COLUMN date_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL AFTER status");
    }

    // Check if date_updated column exists
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '" . DB_NAME . "'
        AND TABLE_NAME = '$table_name'
        AND COLUMN_NAME = 'date_updated'");

    if (empty($row)) {
        // Add date_updated column
        $wpdb->query("ALTER TABLE $table_name
            ADD COLUMN date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL AFTER date_added");
    }

    update_option('hs_user_books_db_version', '1.1');
}

// Run migration on admin init
add_action('admin_init', function() {
    if (get_option('hs_user_books_db_version') !== '1.1') {
        hs_migrate_user_books_timestamps();
    }
});
