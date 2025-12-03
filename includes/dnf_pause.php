<?php
/**
 * DNF (Did Not Finish) and Pause functionality for books
 * Allows users to mark books as DNF with reasons and pause books
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create the DNF books table on activation
 */
function hs_dnf_books_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dnf_books';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        book_id BIGINT(20) UNSIGNED NOT NULL,
        reason TEXT NOT NULL,
        pages_read MEDIUMINT(9) DEFAULT 0,
        date_dnf DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_book_dnf (user_id, book_id),
        KEY user_id (user_id),
        KEY book_id (book_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Mark a book as DNF (Did Not Finish) with a reason
 *
 * @param int $user_id User ID
 * @param int $book_id Book ID
 * @param string $reason Reason for DNF
 * @return array Result with success status and message
 */
function hs_mark_book_dnf($user_id, $book_id, $reason) {
    global $wpdb;

    if (empty($reason)) {
        return array(
            'success' => false,
            'message' => 'Please provide a reason for not finishing this book.'
        );
    }

    $user_books_table = $wpdb->prefix . 'user_books';
    $dnf_table = $wpdb->prefix . 'dnf_books';

    // Check if book is in user's library
    $book_entry = $wpdb->get_row($wpdb->prepare(
        "SELECT current_page, status FROM $user_books_table WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
    ));

    if (!$book_entry) {
        return array(
            'success' => false,
            'message' => 'This book is not in your library.'
        );
    }

    $pages_read = (int)$book_entry->current_page;

    // Update status to 'dnf'
    $result = $wpdb->update(
        $user_books_table,
        array('status' => 'dnf'),
        array('user_id' => $user_id, 'book_id' => $book_id),
        array('%s'),
        array('%d', '%d')
    );

    if ($result === false) {
        return array(
            'success' => false,
            'message' => 'Failed to update book status.'
        );
    }

    // Store DNF reason (use REPLACE to handle updates)
    $dnf_result = $wpdb->replace(
        $dnf_table,
        array(
            'user_id' => $user_id,
            'book_id' => $book_id,
            'reason' => sanitize_textarea_field($reason),
            'pages_read' => $pages_read,
            'date_dnf' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%d', '%s')
    );

    if ($dnf_result === false) {
        return array(
            'success' => false,
            'message' => 'Failed to save DNF reason.'
        );
    }

    // Track activity
    if (function_exists('hs_track_library_activity')) {
        hs_track_library_activity($user_id, $book_id, 'dnf', array(
            'reason' => $reason,
            'pages_read' => $pages_read
        ));
    }

    // Update user stats
    if (function_exists('hs_update_user_stats')) {
        hs_update_user_stats($user_id);
    }

    return array(
        'success' => true,
        'message' => 'Book marked as DNF (Did Not Finish).',
        'pages_read' => $pages_read
    );
}

/**
 * Pause a book
 *
 * @param int $user_id User ID
 * @param int $book_id Book ID
 * @return array Result with success status and message
 */
function hs_pause_book($user_id, $book_id) {
    global $wpdb;

    $user_books_table = $wpdb->prefix . 'user_books';

    // Check if book is in user's library
    $book_entry = $wpdb->get_row($wpdb->prepare(
        "SELECT current_page, status FROM $user_books_table WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
    ));

    if (!$book_entry) {
        return array(
            'success' => false,
            'message' => 'This book is not in your library.'
        );
    }

    if ($book_entry->status === 'paused') {
        return array(
            'success' => false,
            'message' => 'This book is already paused.'
        );
    }

    // Check if book is completed
    $total_pages = (int)get_post_meta($book_id, 'nop', true);
    if ($total_pages > 0 && (int)$book_entry->current_page >= $total_pages) {
        return array(
            'success' => false,
            'message' => 'Cannot pause a completed book.'
        );
    }

    // Update status to 'paused'
    $result = $wpdb->update(
        $user_books_table,
        array('status' => 'paused'),
        array('user_id' => $user_id, 'book_id' => $book_id),
        array('%s'),
        array('%d', '%d')
    );

    if ($result === false) {
        return array(
            'success' => false,
            'message' => 'Failed to pause book.'
        );
    }

    // Track activity
    if (function_exists('hs_track_library_activity')) {
        hs_track_library_activity($user_id, $book_id, 'paused', array(
            'pages_read' => (int)$book_entry->current_page
        ));
    }

    return array(
        'success' => true,
        'message' => 'Book paused successfully.',
        'status' => 'paused'
    );
}

/**
 * Resume a paused book
 *
 * @param int $user_id User ID
 * @param int $book_id Book ID
 * @return array Result with success status and message
 */
function hs_resume_book($user_id, $book_id) {
    global $wpdb;

    $user_books_table = $wpdb->prefix . 'user_books';

    // Check if book is in user's library
    $book_entry = $wpdb->get_row($wpdb->prepare(
        "SELECT current_page, status FROM $user_books_table WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
    ));

    if (!$book_entry) {
        return array(
            'success' => false,
            'message' => 'This book is not in your library.'
        );
    }

    if ($book_entry->status !== 'paused') {
        return array(
            'success' => false,
            'message' => 'This book is not paused.'
        );
    }

    // Update status back to 'reading'
    $result = $wpdb->update(
        $user_books_table,
        array('status' => 'reading'),
        array('user_id' => $user_id, 'book_id' => $book_id),
        array('%s'),
        array('%d', '%d')
    );

    if ($result === false) {
        return array(
            'success' => false,
            'message' => 'Failed to resume book.'
        );
    }

    // Track activity
    if (function_exists('hs_track_library_activity')) {
        hs_track_library_activity($user_id, $book_id, 'resumed', array(
            'pages_read' => (int)$book_entry->current_page
        ));
    }

    return array(
        'success' => true,
        'message' => 'Book resumed successfully.',
        'status' => 'reading'
    );
}

/**
 * Get DNF reason for a book
 *
 * @param int $user_id User ID
 * @param int $book_id Book ID
 * @return array|null DNF data or null if not found
 */
function hs_get_dnf_reason($user_id, $book_id) {
    global $wpdb;

    $dnf_table = $wpdb->prefix . 'dnf_books';

    $dnf_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $dnf_table WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
    ), ARRAY_A);

    return $dnf_data;
}

/**
 * Get all DNF books for a user
 *
 * @param int $user_id User ID
 * @return array Array of DNF books
 */
function hs_get_user_dnf_books($user_id) {
    global $wpdb;

    $dnf_table = $wpdb->prefix . 'dnf_books';

    $dnf_books = $wpdb->get_results($wpdb->prepare(
        "SELECT d.*, p.post_title as book_title
        FROM $dnf_table d
        LEFT JOIN {$wpdb->posts} p ON d.book_id = p.ID
        WHERE d.user_id = %d
        ORDER BY d.date_dnf DESC",
        $user_id
    ), ARRAY_A);

    return $dnf_books;
}
