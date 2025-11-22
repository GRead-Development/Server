<?php
/**
 * Database Repair Utilities
 *
 * This file provides tools to repair and update database tables
 * for the HotSoup plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu item
add_action('admin_menu', 'hs_database_repair_menu');

function hs_database_repair_menu() {
    add_submenu_page(
        'tools.php',
        'HotSoup Database Repair',
        'HotSoup DB Repair',
        'manage_options',
        'hs-database-repair',
        'hs_database_repair_page'
    );
}

function hs_database_repair_page() {
    // Handle form submission
    if (isset($_POST['hs_repair_db']) && wp_verify_nonce($_POST['hs_repair_nonce'], 'hs_repair_db_action')) {
        $result = hs_repair_database_tables();

        if ($result['success']) {
            echo '<div class="notice notice-success"><p><strong>Success!</strong> ' . esc_html($result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p><strong>Error:</strong> ' . esc_html($result['message']) . '</p></div>';
        }
    }

    global $wpdb;

    // Check current table status
    $series_table = $wpdb->prefix . 'hs_series';
    $book_series_table = $wpdb->prefix . 'hs_book_series';

    $series_exists = $wpdb->get_var("SHOW TABLES LIKE '$series_table'") === $series_table;
    $book_series_exists = $wpdb->get_var("SHOW TABLES LIKE '$book_series_table'") === $book_series_table;

    $series_columns = array();
    $book_series_columns = array();

    if ($series_exists) {
        $series_columns = $wpdb->get_col("SHOW COLUMNS FROM $series_table");
    }

    if ($book_series_exists) {
        $book_series_columns = $wpdb->get_col("SHOW COLUMNS FROM $book_series_table");
    }

    ?>
    <div class="wrap">
        <h1>HotSoup Database Repair</h1>

        <div class="card" style="max-width: 800px;">
            <h2>Current Database Status</h2>

            <h3>Series Table (<?php echo $series_table; ?>)</h3>
            <?php if ($series_exists): ?>
                <p><span class="dashicons dashicons-yes" style="color: green;"></span> Table exists</p>
                <p><strong>Columns:</strong> <?php echo implode(', ', $series_columns); ?></p>

                <?php
                $expected_columns = array('id', 'name', 'slug', 'description', 'total_books', 'created_at', 'updated_at', 'created_by');
                $has_name = in_array('name', $series_columns);
                $has_slug = in_array('slug', $series_columns);
                $has_book_id = in_array('book_id', $series_columns); // Wrong column

                if (!$has_name || !$has_slug || $has_book_id): ?>
                    <p style="color: red;"><span class="dashicons dashicons-warning"></span> <strong>Table structure is incorrect!</strong></p>
                    <?php if ($has_book_id): ?>
                        <p style="color: orange;">The table appears to be a book-series relationship table instead of a series metadata table.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: green;"><span class="dashicons dashicons-yes"></span> Table structure looks correct</p>
                <?php endif; ?>
            <?php else: ?>
                <p><span class="dashicons dashicons-no" style="color: red;"></span> Table does not exist</p>
            <?php endif; ?>

            <h3>Book-Series Table (<?php echo $book_series_table; ?>)</h3>
            <?php if ($book_series_exists): ?>
                <p><span class="dashicons dashicons-yes" style="color: green;"></span> Table exists</p>
                <p><strong>Columns:</strong> <?php echo implode(', ', $book_series_columns); ?></p>
            <?php else: ?>
                <p><span class="dashicons dashicons-no" style="color: red;"></span> Table does not exist</p>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Repair Database Tables</h2>
            <p>This will:</p>
            <ul>
                <li>Backup and drop the incorrectly structured series table (if needed)</li>
                <li>Create the correct series metadata table</li>
                <li>Create the book-series relationship table (if it doesn't exist)</li>
                <li>Flush WordPress permalinks to fix 404 errors on book pages</li>
            </ul>

            <p><strong>Warning:</strong> This will delete any existing series data in the incorrectly structured table. Make sure you have a database backup!</p>

            <form method="post">
                <?php wp_nonce_field('hs_repair_db_action', 'hs_repair_nonce'); ?>
                <p>
                    <button type="submit" name="hs_repair_db" class="button button-primary button-large"
                            onclick="return confirm('Are you sure you want to repair the database? This will delete existing series data if the table structure is wrong. Make sure you have a backup!');">
                        Repair Database Tables
                    </button>
                </p>
            </form>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Manual Flush Permalinks</h2>
            <p>If book pages are showing 404 errors, you can manually flush permalinks here.</p>
            <form method="post">
                <?php wp_nonce_field('hs_flush_permalinks_action', 'hs_flush_nonce'); ?>
                <p>
                    <button type="submit" name="hs_flush_permalinks" class="button">Flush Permalinks</button>
                </p>
            </form>

            <?php if (isset($_POST['hs_flush_permalinks']) && wp_verify_nonce($_POST['hs_flush_nonce'], 'hs_flush_permalinks_action')): ?>
                <?php
                flush_rewrite_rules();
                echo '<div class="notice notice-success inline"><p>Permalinks flushed! Try accessing a book page now.</p></div>';
                ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Repair database tables
 */
function hs_repair_database_tables() {
    global $wpdb;

    $series_table = $wpdb->prefix . 'hs_series';
    $book_series_table = $wpdb->prefix . 'hs_book_series';

    try {
        // Check if series table exists and has wrong structure
        $series_exists = $wpdb->get_var("SHOW TABLES LIKE '$series_table'") === $series_table;

        if ($series_exists) {
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $series_table");
            $has_book_id = in_array('book_id', $columns);
            $has_name = in_array('name', $columns);

            // If it has book_id but no name, it's the wrong structure
            if ($has_book_id && !$has_name) {
                // Backup data first (just in case)
                $backup_table = $series_table . '_backup_' . time();
                $wpdb->query("CREATE TABLE $backup_table LIKE $series_table");
                $wpdb->query("INSERT INTO $backup_table SELECT * FROM $series_table");

                // Drop the incorrect table
                $wpdb->query("DROP TABLE $series_table");
            }
        }

        // Recreate tables using the correct functions
        require_once(plugin_dir_path(__FILE__) . '../authors_series.php');

        hs_series_create_table();
        hs_book_series_create_table();

        // Also recreate other author/series tables if needed
        hs_authors_create_table();
        hs_author_aliases_create_table();
        hs_book_authors_create_table();
        hs_author_merges_create_table();

        // Flush permalinks
        flush_rewrite_rules();

        return array(
            'success' => true,
            'message' => 'Database tables repaired successfully! Permalinks have been flushed.'
        );

    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'Error repairing database: ' . $e->getMessage()
        );
    }
}
