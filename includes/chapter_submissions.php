<?php
/**
 * Chapter Submissions System
 *
 * Allows users to submit chapter information (names/titles) for books
 * Submissions require admin approval
 * Users receive 10 points for each approved submission
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create the chapter submissions table on activation
 */
function hs_chapter_submissions_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        book_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        chapters_data LONGTEXT NOT NULL,
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
 * Submit chapter information for a book
 *
 * @param int $user_id User submitting the chapters
 * @param int $book_id Book ID
 * @param array $chapters Array of chapters, each with 'number' and 'title'
 * @return array Result with success status and data
 */
function hs_submit_chapters($user_id, $book_id, $chapters) {
    global $wpdb;

    // Validate user
    if (!$user_id || !get_userdata($user_id)) {
        return [
            'success' => false,
            'message' => 'Invalid user.'
        ];
    }

    // Validate book
    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return [
            'success' => false,
            'message' => 'Invalid book.'
        ];
    }

    // Validate chapters data
    if (empty($chapters) || !is_array($chapters)) {
        return [
            'success' => false,
            'message' => 'Please provide at least one chapter.'
        ];
    }

    // Validate each chapter
    foreach ($chapters as $chapter) {
        if (!isset($chapter['number']) || !isset($chapter['title'])) {
            return [
                'success' => false,
                'message' => 'Each chapter must have a number and title.'
            ];
        }

        // Sanitize and validate chapter number
        $chapter_num = intval($chapter['number']);
        if ($chapter_num <= 0) {
            return [
                'success' => false,
                'message' => 'Chapter numbers must be positive integers.'
            ];
        }

        // Validate chapter title
        if (empty(trim($chapter['title']))) {
            return [
                'success' => false,
                'message' => 'Chapter titles cannot be empty.'
            ];
        }
    }

    // Check if user has already submitted chapters for this book (pending)
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';
    $existing_submission = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$table_name}
        WHERE user_id = %d AND book_id = %d AND status = 'pending'",
        $user_id,
        $book_id
    ));

    if ($existing_submission) {
        return [
            'success' => false,
            'message' => 'You already have a pending submission for this book. Please wait for it to be reviewed.'
        ];
    }

    // Encode chapters data as JSON
    $chapters_json = wp_json_encode($chapters);

    // Insert submission
    $result = $wpdb->insert(
        $table_name,
        [
            'book_id' => $book_id,
            'user_id' => $user_id,
            'chapters_data' => $chapters_json,
            'status' => 'pending',
            'submitted_at' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%s', '%s']
    );

    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Failed to submit chapters. Please try again.'
        ];
    }

    return [
        'success' => true,
        'message' => 'Chapter information submitted successfully! It will be reviewed by an admin.',
        'submission_id' => $wpdb->insert_id
    ];
}

/**
 * Get chapter submission by ID
 *
 * @param int $submission_id Submission ID
 * @return object|null Submission object or null
 */
function hs_get_chapter_submission($submission_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';

    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $submission_id
    ));

    if ($submission && !empty($submission->chapters_data)) {
        $submission->chapters = json_decode($submission->chapters_data, true);
    }

    return $submission;
}

/**
 * Get all chapter submissions for a book (approved only by default)
 *
 * @param int $book_id Book ID
 * @param string $status Status filter ('approved', 'pending', 'rejected', or 'all')
 * @return array Array of submissions
 */
function hs_get_book_chapter_submissions($book_id, $status = 'approved') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';

    if ($status === 'all') {
        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE book_id = %d ORDER BY submitted_at DESC",
            $book_id
        ));
    } else {
        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE book_id = %d AND status = %s ORDER BY submitted_at DESC",
            $book_id,
            $status
        ));
    }

    // Decode chapters data for each submission
    foreach ($submissions as $submission) {
        if (!empty($submission->chapters_data)) {
            $submission->chapters = json_decode($submission->chapters_data, true);
        }
    }

    return $submissions;
}

/**
 * Get user's chapter submissions
 *
 * @param int $user_id User ID
 * @param string $status Optional status filter
 * @return array Array of submissions
 */
function hs_get_user_chapter_submissions($user_id, $status = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';

    if ($status) {
        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d AND status = %s ORDER BY submitted_at DESC",
            $user_id,
            $status
        ));
    } else {
        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY submitted_at DESC",
            $user_id
        ));
    }

    // Decode chapters data and add book info
    foreach ($submissions as $submission) {
        if (!empty($submission->chapters_data)) {
            $submission->chapters = json_decode($submission->chapters_data, true);
        }

        // Add book title
        $book = get_post($submission->book_id);
        $submission->book_title = $book ? $book->post_title : 'Unknown Book';
    }

    return $submissions;
}

/**
 * Approve a chapter submission
 *
 * @param int $submission_id Submission ID
 * @param int $admin_user_id Admin user approving
 * @return array Result with success status
 */
function hs_approve_chapter_submission($submission_id, $admin_user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';

    // Get submission
    $submission = hs_get_chapter_submission($submission_id);

    if (!$submission) {
        return [
            'success' => false,
            'message' => 'Submission not found.'
        ];
    }

    if ($submission->status !== 'pending') {
        return [
            'success' => false,
            'message' => 'This submission has already been reviewed.'
        ];
    }

    // Update submission status
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
        return [
            'success' => false,
            'message' => 'Failed to approve submission.'
        ];
    }

    // Award 10 points to the submitter
    if (function_exists('award_points')) {
        award_points($submission->user_id, 10);
    }

    // Increment user achievement stat for chapter submissions
    if (function_exists('hs_increment_user_achievement_stat')) {
        hs_increment_user_achievement_stat($submission->user_id, 'chapters_submitted');
    }

    // Trigger stats update for potential achievements
    do_action('hs_stats_updated', $submission->user_id);

    return [
        'success' => true,
        'message' => 'Chapter submission approved successfully.'
    ];
}

/**
 * Reject a chapter submission
 *
 * @param int $submission_id Submission ID
 * @param int $admin_user_id Admin user rejecting
 * @param string $reason Optional rejection reason
 * @return array Result with success status
 */
function hs_reject_chapter_submission($submission_id, $admin_user_id, $reason = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';

    // Get submission
    $submission = hs_get_chapter_submission($submission_id);

    if (!$submission) {
        return [
            'success' => false,
            'message' => 'Submission not found.'
        ];
    }

    if ($submission->status !== 'pending') {
        return [
            'success' => false,
            'message' => 'This submission has already been reviewed.'
        ];
    }

    // Update submission status
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
        return [
            'success' => false,
            'message' => 'Failed to reject submission.'
        ];
    }

    return [
        'success' => true,
        'message' => 'Chapter submission rejected.'
    ];
}

/**
 * Get approved chapters for a book (for display)
 * Returns the most recent approved submission
 *
 * @param int $book_id Book ID
 * @return array|null Array of chapters or null
 */
function hs_get_approved_chapters($book_id) {
    $submissions = hs_get_book_chapter_submissions($book_id, 'approved');

    if (empty($submissions)) {
        return null;
    }

    // Return the most recent approved submission
    $latest = $submissions[0];
    return isset($latest->chapters) ? $latest->chapters : null;
}

/**
 * Delete a chapter submission
 *
 * @param int $submission_id Submission ID
 * @return bool Success status
 */
function hs_delete_chapter_submission($submission_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';

    $result = $wpdb->delete(
        $table_name,
        ['id' => $submission_id],
        ['%d']
    );

    return $result !== false;
}
