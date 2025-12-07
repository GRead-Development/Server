<?php
/**
 * Tag Suggestions System
 *
 * Allows users to suggest tags for books
 * Users receive 3 points for each approved tag
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create the tag suggestions table on activation
 */
function hs_tag_suggestions_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_tag_suggestions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        book_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        tag_name VARCHAR(100) NOT NULL,
        tag_slug VARCHAR(100) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending' NOT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        reviewed_by BIGINT(20) UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        rejection_reason TEXT NULL,
        PRIMARY KEY (id),
        KEY book_id_index (book_id),
        KEY user_id_index (user_id),
        KEY status_index (status),
        KEY tag_slug_index (tag_slug)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Submit tag suggestions for a book
 */
function hs_submit_tag_suggestions($user_id, $book_id, $tags) {
    global $wpdb;

    if (!$user_id || !get_userdata($user_id)) {
        return ['success' => false, 'message' => 'Invalid user.'];
    }

    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return ['success' => false, 'message' => 'Invalid book.'];
    }

    if (empty($tags) || !is_array($tags)) {
        return ['success' => false, 'message' => 'Please provide at least one tag.'];
    }

    $table_name = $wpdb->prefix . 'hs_tag_suggestions';
    $submitted_count = 0;
    $duplicate_count = 0;

    foreach ($tags as $tag_name) {
        $tag_name = trim($tag_name);
        if (empty($tag_name)) {
            continue;
        }

        $tag_slug = sanitize_title($tag_name);

        // Check if this tag already exists (pending or approved) from this user for this book
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name}
            WHERE user_id = %d AND book_id = %d AND tag_slug = %s AND status IN ('pending', 'approved')",
            $user_id,
            $book_id,
            $tag_slug
        ));

        if ($existing) {
            $duplicate_count++;
            continue;
        }

        // Check if tag is already applied to the book in the main tags table
        $tags_table = $wpdb->prefix . 'hs_book_tags';
        $already_tagged = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$tags_table} WHERE book_id = %d AND tag_slug = %s",
            $book_id,
            $tag_slug
        ));

        if ($already_tagged) {
            $duplicate_count++;
            continue;
        }

        // Insert the tag suggestion
        $result = $wpdb->insert(
            $table_name,
            [
                'book_id' => $book_id,
                'user_id' => $user_id,
                'tag_name' => sanitize_text_field($tag_name),
                'tag_slug' => $tag_slug,
                'status' => 'pending',
                'submitted_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );

        if ($result !== false) {
            $submitted_count++;
        }
    }

    if ($submitted_count === 0) {
        if ($duplicate_count > 0) {
            return ['success' => false, 'message' => 'All tags have already been submitted or applied to this book.'];
        }
        return ['success' => false, 'message' => 'Failed to submit tags.'];
    }

    $message = "Successfully submitted {$submitted_count} tag(s) for review.";
    if ($duplicate_count > 0) {
        $message .= " {$duplicate_count} duplicate(s) were skipped.";
    }

    return [
        'success' => true,
        'message' => $message,
        'submitted_count' => $submitted_count
    ];
}

/**
 * Get tag suggestion by ID
 */
function hs_get_tag_suggestion($suggestion_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_tag_suggestions';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $suggestion_id
    ));
}

/**
 * Approve a tag suggestion
 */
function hs_approve_tag_suggestion($suggestion_id, $admin_user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_tag_suggestions';

    $suggestion = hs_get_tag_suggestion($suggestion_id);

    if (!$suggestion) {
        return ['success' => false, 'message' => 'Suggestion not found.'];
    }

    if ($suggestion->status !== 'pending') {
        return ['success' => false, 'message' => 'This suggestion has already been reviewed.'];
    }

    // Add the tag to the book's tags table
    $tags_table = $wpdb->prefix . 'hs_book_tags';

    // Check if tag already exists
    $existing_tag = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$tags_table} WHERE book_id = %d AND tag_slug = %s",
        $suggestion->book_id,
        $suggestion->tag_slug
    ));

    if ($existing_tag) {
        // Tag already exists, just mark as approved
        $wpdb->update(
            $table_name,
            [
                'status' => 'approved',
                'reviewed_by' => $admin_user_id,
                'reviewed_at' => current_time('mysql')
            ],
            ['id' => $suggestion_id],
            ['%s', '%d', '%s'],
            ['%d']
        );
    } else {
        // Insert the tag
        $wpdb->insert(
            $tags_table,
            [
                'book_id' => $suggestion->book_id,
                'tag_name' => $suggestion->tag_name,
                'tag_slug' => $suggestion->tag_slug,
                'usage_count' => 1
            ],
            ['%d', '%s', '%s', '%d']
        );

        // Mark as approved
        $wpdb->update(
            $table_name,
            [
                'status' => 'approved',
                'reviewed_by' => $admin_user_id,
                'reviewed_at' => current_time('mysql')
            ],
            ['id' => $suggestion_id],
            ['%s', '%d', '%s'],
            ['%d']
        );
    }

    // Award 3 points to the submitter
    if (function_exists('award_points')) {
        award_points($suggestion->user_id, 3);
    }

    if (function_exists('hs_increment_user_achievement_stat')) {
        hs_increment_user_achievement_stat($suggestion->user_id, 'tags_suggested');
    }

    do_action('hs_stats_updated', $suggestion->user_id);

    return ['success' => true, 'message' => 'Tag suggestion approved successfully.'];
}

/**
 * Reject a tag suggestion
 */
function hs_reject_tag_suggestion($suggestion_id, $admin_user_id, $reason = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_tag_suggestions';

    $suggestion = hs_get_tag_suggestion($suggestion_id);

    if (!$suggestion) {
        return ['success' => false, 'message' => 'Suggestion not found.'];
    }

    if ($suggestion->status !== 'pending') {
        return ['success' => false, 'message' => 'This suggestion has already been reviewed.'];
    }

    $result = $wpdb->update(
        $table_name,
        [
            'status' => 'rejected',
            'reviewed_by' => $admin_user_id,
            'reviewed_at' => current_time('mysql'),
            'rejection_reason' => sanitize_textarea_field($reason)
        ],
        ['id' => $suggestion_id],
        ['%s', '%d', '%s', '%s'],
        ['%d']
    );

    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to reject suggestion.'];
    }

    return ['success' => true, 'message' => 'Tag suggestion rejected.'];
}

/**
 * Get all approved tags for a book
 */
function hs_get_book_tags($book_id) {
    global $wpdb;
    $tags_table = $wpdb->prefix . 'hs_book_tags';

    $tags = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$tags_table} WHERE book_id = %d ORDER BY tag_name ASC",
        $book_id
    ));

    return $tags ?: [];
}
