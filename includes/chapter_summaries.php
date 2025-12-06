<?php
/**
 * Chapter Summaries System
 *
 * Allows users to submit summaries for specific chapters
 * Users receive 25 points for each approved summary
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create the chapter summaries table on activation
 */
function hs_chapter_summaries_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_summaries';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        book_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        chapter_number INT NOT NULL,
        chapter_title VARCHAR(255) NULL,
        summary TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending' NOT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        reviewed_by BIGINT(20) UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        rejection_reason TEXT NULL,
        PRIMARY KEY (id),
        KEY book_id_index (book_id),
        KEY user_id_index (user_id),
        KEY status_index (status),
        KEY chapter_index (book_id, chapter_number)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Submit a chapter summary
 */
function hs_submit_chapter_summary($user_id, $book_id, $chapter_number, $chapter_title, $summary) {
    global $wpdb;

    if (!$user_id || !get_userdata($user_id)) {
        return ['success' => false, 'message' => 'Invalid user.'];
    }

    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return ['success' => false, 'message' => 'Invalid book.'];
    }

    if (!$chapter_number || $chapter_number < 1) {
        return ['success' => false, 'message' => 'Invalid chapter number.'];
    }

    $summary = trim($summary);
    if (empty($summary)) {
        return ['success' => false, 'message' => 'Summary cannot be empty.'];
    }

    if (strlen($summary) < 50) {
        return ['success' => false, 'message' => 'Summary must be at least 50 characters.'];
    }

    // Check if user already has a pending or approved summary for this chapter
    $table_name = $wpdb->prefix . 'hs_chapter_summaries';
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$table_name}
        WHERE user_id = %d AND book_id = %d AND chapter_number = %d AND status IN ('pending', 'approved')",
        $user_id,
        $book_id,
        $chapter_number
    ));

    if ($existing) {
        return ['success' => false, 'message' => 'You already have a summary for this chapter.'];
    }

    $result = $wpdb->insert(
        $table_name,
        [
            'book_id' => $book_id,
            'user_id' => $user_id,
            'chapter_number' => $chapter_number,
            'chapter_title' => sanitize_text_field($chapter_title),
            'summary' => sanitize_textarea_field($summary),
            'status' => 'pending',
            'submitted_at' => current_time('mysql')
        ],
        ['%d', '%d', '%d', '%s', '%s', '%s', '%s']
    );

    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to submit summary.'];
    }

    return [
        'success' => true,
        'message' => 'Chapter summary submitted successfully!',
        'submission_id' => $wpdb->insert_id
    ];
}

/**
 * Get chapter summary by ID
 */
function hs_get_chapter_summary($summary_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_summaries';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $summary_id
    ));
}

/**
 * Approve a chapter summary
 */
function hs_approve_chapter_summary($summary_id, $admin_user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_summaries';

    $summary = hs_get_chapter_summary($summary_id);

    if (!$summary) {
        return ['success' => false, 'message' => 'Summary not found.'];
    }

    if ($summary->status !== 'pending') {
        return ['success' => false, 'message' => 'This summary has already been reviewed.'];
    }

    $result = $wpdb->update(
        $table_name,
        [
            'status' => 'approved',
            'reviewed_by' => $admin_user_id,
            'reviewed_at' => current_time('mysql')
        ],
        ['id' => $summary_id],
        ['%s', '%d', '%s'],
        ['%d']
    );

    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to approve summary.'];
    }

    // Award 25 points to the submitter
    if (function_exists('award_points')) {
        award_points($summary->user_id, 25);
    }

    if (function_exists('hs_increment_user_achievement_stat')) {
        hs_increment_user_achievement_stat($summary->user_id, 'summaries_submitted');
    }

    do_action('hs_stats_updated', $summary->user_id);

    return ['success' => true, 'message' => 'Chapter summary approved successfully.'];
}

/**
 * Reject a chapter summary
 */
function hs_reject_chapter_summary($summary_id, $admin_user_id, $reason = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_summaries';

    $summary = hs_get_chapter_summary($summary_id);

    if (!$summary) {
        return ['success' => false, 'message' => 'Summary not found.'];
    }

    if ($summary->status !== 'pending') {
        return ['success' => false, 'message' => 'This summary has already been reviewed.'];
    }

    $result = $wpdb->update(
        $table_name,
        [
            'status' => 'rejected',
            'reviewed_by' => $admin_user_id,
            'reviewed_at' => current_time('mysql'),
            'rejection_reason' => sanitize_textarea_field($reason)
        ],
        ['id' => $summary_id],
        ['%s', '%d', '%s', '%s'],
        ['%d']
    );

    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to reject summary.'];
    }

    return ['success' => true, 'message' => 'Chapter summary rejected.'];
}

/**
 * Get approved summaries for a book
 */
function hs_get_approved_chapter_summaries($book_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_summaries';

    $summaries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name}
        WHERE book_id = %d AND status = 'approved'
        ORDER BY chapter_number ASC",
        $book_id
    ));

    return !empty($summaries) ? $summaries : null;
}

/**
 * Get summary for a specific chapter
 */
function hs_get_chapter_summary_by_chapter($book_id, $chapter_number) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_summaries';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name}
        WHERE book_id = %d AND chapter_number = %d AND status = 'approved'
        ORDER BY reviewed_at DESC
        LIMIT 1",
        $book_id,
        $chapter_number
    ));
}
