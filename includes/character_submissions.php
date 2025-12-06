<?php
/**
 * Character Submissions System
 *
 * Allows users to submit character names for books
 * Users receive 15 points for each approved character
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create the character submissions table on activation
 */
function hs_character_submissions_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_character_submissions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        book_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        characters_data LONGTEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending' NOT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        reviewed_by BIGINT(20) UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        rejection_reason TEXT NULL,
        PRIMARY KEY (id),
        KEY book_id_index (book_id),
        KEY user_id_index (user_id),
        KEY status_index (status)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Submit characters for a book
 */
function hs_submit_characters($user_id, $book_id, $characters) {
    global $wpdb;

    if (!$user_id || !get_userdata($user_id)) {
        return ['success' => false, 'message' => 'Invalid user.'];
    }

    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return ['success' => false, 'message' => 'Invalid book.'];
    }

    if (empty($characters) || !is_array($characters)) {
        return ['success' => false, 'message' => 'Please provide at least one character.'];
    }

    // Validate each character
    foreach ($characters as $character) {
        if (!isset($character['name']) || empty(trim($character['name']))) {
            return ['success' => false, 'message' => 'Character names cannot be empty.'];
        }
    }

    // Check for pending submission
    $table_name = $wpdb->prefix . 'hs_character_submissions';
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE user_id = %d AND book_id = %d AND status = 'pending'",
        $user_id,
        $book_id
    ));

    if ($existing) {
        return ['success' => false, 'message' => 'You already have a pending character submission for this book.'];
    }

    $characters_json = wp_json_encode($characters);

    $result = $wpdb->insert(
        $table_name,
        [
            'book_id' => $book_id,
            'user_id' => $user_id,
            'characters_data' => $characters_json,
            'status' => 'pending',
            'submitted_at' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%s', '%s']
    );

    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to submit characters.'];
    }

    return [
        'success' => true,
        'message' => 'Characters submitted successfully!',
        'submission_id' => $wpdb->insert_id
    ];
}

/**
 * Get character submission by ID
 */
function hs_get_character_submission($submission_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_character_submissions';

    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $submission_id
    ));

    if ($submission && !empty($submission->characters_data)) {
        $submission->characters = json_decode($submission->characters_data, true);
    }

    return $submission;
}

/**
 * Approve a character submission
 */
function hs_approve_character_submission($submission_id, $admin_user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_character_submissions';

    $submission = hs_get_character_submission($submission_id);

    if (!$submission) {
        return ['success' => false, 'message' => 'Submission not found.'];
    }

    if ($submission->status !== 'pending') {
        return ['success' => false, 'message' => 'This submission has already been reviewed.'];
    }

    $result = $wpdb->update(
        $table_name,
        [
            'status' => 'approved',
            'reviewed_by' => $admin_user_id,
            'reviewed_at' => current_time('mysql')
        ],
        ['id' => $submission_id],
        ['%s', '%d', '%s'],
        ['%d']
    );

    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to approve submission.'];
    }

    // Award 15 points to the submitter
    if (function_exists('award_points')) {
        award_points($submission->user_id, 15);
    }

    if (function_exists('hs_increment_user_achievement_stat')) {
        hs_increment_user_achievement_stat($submission->user_id, 'characters_submitted');
    }

    do_action('hs_stats_updated', $submission->user_id);

    return ['success' => true, 'message' => 'Character submission approved successfully.'];
}

/**
 * Reject a character submission
 */
function hs_reject_character_submission($submission_id, $admin_user_id, $reason = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_character_submissions';

    $submission = hs_get_character_submission($submission_id);

    if (!$submission) {
        return ['success' => false, 'message' => 'Submission not found.'];
    }

    if ($submission->status !== 'pending') {
        return ['success' => false, 'message' => 'This submission has already been reviewed.'];
    }

    $result = $wpdb->update(
        $table_name,
        [
            'status' => 'rejected',
            'reviewed_by' => $admin_user_id,
            'reviewed_at' => current_time('mysql'),
            'rejection_reason' => sanitize_textarea_field($reason)
        ],
        ['id' => $submission_id],
        ['%s', '%d', '%s', '%s'],
        ['%d']
    );

    if ($result === false) {
        return ['success' => false, 'message' => 'Failed to reject submission.'];
    }

    return ['success' => true, 'message' => 'Character submission rejected.'];
}

/**
 * Get approved characters for a book
 */
function hs_get_approved_characters($book_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_character_submissions';

    $submissions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE book_id = %d AND status = 'approved' ORDER BY submitted_at DESC",
        $book_id
    ));

    if (empty($submissions)) {
        return null;
    }

    // Combine all approved characters
    $all_characters = [];
    foreach ($submissions as $submission) {
        if (!empty($submission->characters_data)) {
            $chars = json_decode($submission->characters_data, true);
            if (is_array($chars)) {
                $all_characters = array_merge($all_characters, $chars);
            }
        }
    }

    // Remove duplicates based on name
    $unique_characters = [];
    $seen_names = [];
    foreach ($all_characters as $char) {
        $name_lower = strtolower(trim($char['name']));
        if (!in_array($name_lower, $seen_names)) {
            $unique_characters[] = $char;
            $seen_names[] = $name_lower;
        }
    }

    return !empty($unique_characters) ? $unique_characters : null;
}
