<?php
/**
 * Find Registration Page
 *
 * This script will help locate your registration page.
 * Delete this file after use.
 */

require_once('./wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Find Registration Page</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; padding: 40px; max-width: 1000px; margin: 0 auto; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: 600; }
        .edit-link { color: #2271b1; text-decoration: none; }
        .edit-link:hover { text-decoration: underline; }
        .shortcode { background: #f5f5f5; padding: 3px 8px; border-radius: 3px; font-family: monospace; font-size: 12px; }
        .has-shortcode { background: #d4edda; }
        .no-shortcode { background: #fff3cd; }
        .info-box { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0; }
        .setting-box { background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🔍 Registration Page Finder</h1>

    <div class="setting-box">
        <h3>Current Settings</h3>
        <?php
        $custom_registration_enabled = get_option('hs_custom_registration_enabled', false);
        $custom_registration_page = get_option('hs_custom_registration_page', home_url('/register'));
        ?>
        <p><strong>Custom Registration Enabled:</strong> <?php echo $custom_registration_enabled ? 'Yes ✓' : 'No ✗'; ?></p>
        <p><strong>Custom Registration Page URL:</strong> <code><?php echo esc_html($custom_registration_page); ?></code></p>
    </div>

    <h2>Pages with "register" in the title or slug:</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Slug</th>
                <th>URL</th>
                <th>Has Shortcode?</th>
                <th>Edit</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Search for pages with "register" in title or slug
            $args = array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                's' => 'register',
            );

            $pages = get_posts($args);

            // Also search by slug
            $slug_args = array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'name' => 'register',
            );

            $slug_pages = get_posts($slug_args);

            // Merge and remove duplicates
            $all_pages = array_merge($pages, $slug_pages);
            $unique_pages = array();
            foreach ($all_pages as $page) {
                $unique_pages[$page->ID] = $page;
            }

            if (empty($unique_pages)) {
                echo '<tr><td colspan="6">No pages found with "register" in the title or slug.</td></tr>';
            } else {
                foreach ($unique_pages as $page) {
                    $has_shortcode = has_shortcode($page->post_content, 'hs_registration_form');
                    $row_class = $has_shortcode ? 'has-shortcode' : 'no-shortcode';

                    echo '<tr class="' . $row_class . '">';
                    echo '<td>' . $page->ID . '</td>';
                    echo '<td>' . esc_html($page->post_title) . '</td>';
                    echo '<td><code>' . esc_html($page->post_name) . '</code></td>';
                    echo '<td><a href="' . get_permalink($page->ID) . '" target="_blank">' . get_permalink($page->ID) . '</a></td>';
                    echo '<td>' . ($has_shortcode ? '✓ Yes' : '✗ No') . '</td>';
                    echo '<td><a href="' . admin_url('post.php?post=' . $page->ID . '&action=edit') . '" class="edit-link">Edit Page</a></td>';
                    echo '</tr>';
                }
            }
            ?>
        </tbody>
    </table>

    <div class="info-box">
        <h3>📝 How to Add the Registration Shortcode</h3>
        <ol>
            <li>Click "Edit Page" next to your registration page above</li>
            <li>Add this shortcode to the page content: <span class="shortcode">[hs_registration_form]</span></li>
            <li>Click "Update" to save the page</li>
            <li>The Apple sign-in button should now appear on that page!</li>
        </ol>
    </div>

    <h2>All Page Content Preview:</h2>
    <?php
    if (!empty($unique_pages)) {
        foreach ($unique_pages as $page) {
            ?>
            <div style="background: white; border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px;">
                <h3><?php echo esc_html($page->post_title); ?> (ID: <?php echo $page->ID; ?>)</h3>
                <p><strong>URL:</strong> <a href="<?php echo get_permalink($page->ID); ?>" target="_blank"><?php echo get_permalink($page->ID); ?></a></p>
                <p><strong>Content Preview:</strong></p>
                <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; border-radius: 4px;"><?php echo esc_html(substr($page->post_content, 0, 500)); ?><?php echo strlen($page->post_content) > 500 ? '...' : ''; ?></pre>
                <?php if (has_shortcode($page->post_content, 'hs_registration_form')): ?>
                    <p style="color: green; font-weight: bold;">✓ This page has the [hs_registration_form] shortcode!</p>
                <?php else: ?>
                    <p style="color: orange; font-weight: bold;">⚠ This page does NOT have the [hs_registration_form] shortcode yet.</p>
                    <p>To add Apple sign-in to this page, <a href="<?php echo admin_url('post.php?post=' . $page->ID . '&action=edit'); ?>">edit the page</a> and add: <span class="shortcode">[hs_registration_form]</span></p>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    ?>

    <div style="margin-top: 40px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
        <strong>🗑️ Delete this file after use!</strong><br>
        This file should not be left on your server.
    </div>

</body>
</html>
