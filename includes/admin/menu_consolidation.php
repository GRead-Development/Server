<?php
/**
 * HotSoup Admin Menu Consolidation
 *
 * This file consolidates all HotSoup admin pages under a single parent menu
 * to reduce clutter in the WordPress admin sidebar.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create the main HotSoup admin menu and consolidate all submenus
 */
function hs_create_consolidated_admin_menu() {
    // Create the parent menu page
    add_menu_page(
        'HotSoup Admin',           // Page title
        'HotSoup',                 // Menu title
        'manage_options',          // Capability
        'hotsoup-admin',           // Menu slug
        'hs_admin_dashboard',      // Callback function
        'dashicons-book',          // Icon
        30                         // Position
    );

    // Add Dashboard as first submenu (replaces parent)
    add_submenu_page(
        'hotsoup-admin',
        'HotSoup Dashboard',
        'Dashboard',
        'manage_options',
        'hotsoup-admin',
        'hs_admin_dashboard'
    );

    // Note: Individual admin pages will add themselves as submenus
    // This is just the structure - the actual pages are loaded from their respective files
}
add_action('admin_menu', 'hs_create_consolidated_admin_menu', 5); // Priority 5 to run before other menu additions

/**
 * Dashboard page content
 */
function hs_admin_dashboard() {
    global $wpdb;

    // Get some basic stats
    $books_table = $wpdb->prefix . 'user_books';
    $dnf_table = $wpdb->prefix . 'dnf_books';

    $total_books = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'book' AND post_status = 'publish'");
    $total_user_books = $wpdb->get_var("SELECT COUNT(*) FROM {$books_table}");
    $total_dnf = $wpdb->get_var("SELECT COUNT(*) FROM {$dnf_table}");
    $total_paused = $wpdb->get_var("SELECT COUNT(*) FROM {$books_table} WHERE status = 'paused'");

    ?>
    <div class="wrap">
        <h1>HotSoup Admin Dashboard</h1>

        <div class="hs-admin-dashboard">
            <h2>Quick Stats</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                <div class="hs-stat-card">
                    <h3>Total Books</h3>
                    <p style="font-size: 2em; margin: 0; color: #0073aa;"><?php echo number_format($total_books); ?></p>
                </div>
                <div class="hs-stat-card">
                    <h3>Books in Libraries</h3>
                    <p style="font-size: 2em; margin: 0; color: #28a745;"><?php echo number_format($total_user_books); ?></p>
                </div>
                <div class="hs-stat-card">
                    <h3>Paused Books</h3>
                    <p style="font-size: 2em; margin: 0; color: #ffc107;"><?php echo number_format($total_paused); ?></p>
                </div>
                <div class="hs-stat-card">
                    <h3>DNF Books</h3>
                    <p style="font-size: 2em; margin: 0; color: #dc3545;"><?php echo number_format($total_dnf); ?></p>
                </div>
            </div>

            <h2 style="margin-top: 40px;">Quick Links</h2>
            <ul>
                <li><a href="<?php echo admin_url('admin.php?page=hotsoup-pending-books'); ?>">Review Pending Books</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=hotsoup-achievements'); ?>">Manage Achievements</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=hotsoup-themes'); ?>">Manage Themes</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=hotsoup-book-merger'); ?>">Book Merger</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=hs-authors-series'); ?>">Authors & Series</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=hotsoup-tags'); ?>">Manage Tags</a></li>
            </ul>
        </div>

        <style>
            .hs-admin-dashboard {
                max-width: 1200px;
            }
            .hs-stat-card {
                background: white;
                padding: 20px;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .hs-stat-card h3 {
                margin-top: 0;
                color: #666;
                font-size: 14px;
                text-transform: uppercase;
            }
        </style>
    </div>
    <?php
}

/**
 * Update existing admin pages to use consolidated menu
 * This filter runs after individual pages register themselves
 */
function hs_consolidate_existing_menus() {
    global $menu, $submenu;

    // List of menu slugs to consolidate under HotSoup
    $hotsoup_menus = array(
        'hotsoup-chimera',
        'hotsoup-support',
        'hotsoup-api-endpoints',
        'hotsoup-themes',
        'hotsoup-achievements'
    );

    // Move these menus to HotSoup submenu
    foreach ($hotsoup_menus as $menu_slug) {
        if (isset($submenu[$menu_slug])) {
            // If it has submenus, move them all
            foreach ($submenu[$menu_slug] as $submenu_item) {
                // Skip if it's already under hotsoup-admin
                if ($submenu_item[2] !== $menu_slug) {
                    add_submenu_page(
                        'hotsoup-admin',
                        $submenu_item[0],
                        $submenu_item[1],
                        $submenu_item[3],
                        $submenu_item[2]
                    );
                }
            }
        }

        // Remove the top-level menu
        remove_menu_page($menu_slug);
    }
}
add_action('admin_menu', 'hs_consolidate_existing_menus', 999); // Run last
