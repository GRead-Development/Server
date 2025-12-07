<?php
/**
 * Tag Archive - Filters books by tag
 * Displays all books that have a specific tag
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modify the main query to filter books by tag when hs_tag parameter is present
 */
function hs_filter_books_by_tag($query) {
    // Only modify main query on archive pages for books, not admin or other queries
    if (!is_admin() && $query->is_main_query() && $query->is_post_type_archive('book') && isset($_GET['hs_tag'])) {
        $tag_slug = sanitize_text_field($_GET['hs_tag']);

        // Get all book IDs that have this tag
        global $wpdb;
        $tags_table = $wpdb->prefix . 'hs_book_tags';
        $book_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT book_id FROM {$tags_table} WHERE tag_slug = %s",
            $tag_slug
        ));

        if (!empty($book_ids)) {
            $query->set('post__in', $book_ids);
        } else {
            // No books with this tag, set impossible condition
            $query->set('post__in', [0]);
        }
    }
}
add_action('pre_get_posts', 'hs_filter_books_by_tag');

/**
 * Add tag filter info to the page title
 */
function hs_tag_archive_title($title) {
    if (is_post_type_archive('book') && isset($_GET['hs_tag'])) {
        $tag_slug = sanitize_text_field($_GET['hs_tag']);

        // Get the tag name
        global $wpdb;
        $tags_table = $wpdb->prefix . 'hs_book_tags';
        $tag = $wpdb->get_row($wpdb->prepare(
            "SELECT tag_name FROM {$tags_table} WHERE tag_slug = %s LIMIT 1",
            $tag_slug
        ));

        if ($tag) {
            $title = 'Books tagged: ' . esc_html($tag->tag_name);
        }
    }

    return $title;
}
add_filter('get_the_archive_title', 'hs_tag_archive_title');

/**
 * Add tag filter description
 */
function hs_tag_archive_description($description) {
    if (is_post_type_archive('book') && isset($_GET['hs_tag'])) {
        global $wpdb;
        $tag_slug = sanitize_text_field($_GET['hs_tag']);
        $tags_table = $wpdb->prefix . 'hs_book_tags';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT book_id) FROM {$tags_table} WHERE tag_slug = %s",
            $tag_slug
        ));

        $description = sprintf('Showing %d book%s with this tag.', $count, $count !== 1 ? 's' : '');

        // Add link to clear filter
        $clear_url = remove_query_arg('hs_tag');
        $description .= ' <a href="' . esc_url($clear_url) . '" style="margin-left: 10px;">Clear filter</a>';
    }

    return $description;
}
add_filter('get_the_archive_description', 'hs_tag_archive_description');
