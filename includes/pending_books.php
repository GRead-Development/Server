<?php
/**
 * Pending Books System
 *
 * Allows users to submit books without ISBN for admin review.
 * Users can track submitted books in their library before approval.
 * Approved books become publicly available.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create pending books table on activation
 */
function hs_create_pending_books_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'pending_books';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        gid VARCHAR(50) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        title VARCHAR(500) NOT NULL,
        author VARCHAR(500) NOT NULL,
        page_count INT NOT NULL,
        description TEXT DEFAULT NULL,
        cover_url VARCHAR(1000) DEFAULT NULL,
        external_id VARCHAR(255) DEFAULT NULL,
        external_id_type VARCHAR(50) DEFAULT NULL,
        publication_year INT DEFAULT NULL,
        publisher VARCHAR(255) DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'pending' NOT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        reviewed_by BIGINT(20) UNSIGNED DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        rejection_reason TEXT DEFAULT NULL,
        approved_book_id BIGINT(20) UNSIGNED DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY gid (gid),
        KEY user_id (user_id),
        KEY status (status),
        KEY approved_book_id (approved_book_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Update user_books table to support pending books
 */
function hs_update_user_books_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_books';

    // Add pending_book_id column if it doesn't exist
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = '{$table_name}' AND column_name = 'pending_book_id'");

    if (empty($row)) {
        $wpdb->query("ALTER TABLE {$table_name}
            ADD COLUMN pending_book_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER book_id,
            ADD KEY pending_book_id (pending_book_id)");
    }

    // Modify the unique constraint to allow either book_id or pending_book_id
    $wpdb->query("ALTER TABLE {$table_name} DROP INDEX user_book_unique");
    $wpdb->query("ALTER TABLE {$table_name}
        ADD UNIQUE KEY user_book_unique (user_id, book_id, pending_book_id)");
}

/**
 * Generate a unique GID for a pending book
 * Format: PB-{timestamp}-{random}
 */
function hs_generate_pending_book_gid()
{
    return 'PB-' . time() . '-' . wp_generate_password(8, false);
}

/**
 * Submit a new book without ISBN
 *
 * @param int $user_id User ID submitting the book
 * @param array $book_data Book data (title, author, page_count, etc.)
 * @return array Result with success status and data
 */
function hs_submit_pending_book($user_id, $book_data)
{
    global $wpdb;

    // Validate required fields
    $required = ['title', 'author', 'page_count'];
    foreach ($required as $field) {
        if (empty($book_data[$field])) {
            return [
                'success' => false,
                'message' => "Missing required field: {$field}"
            ];
        }
    }

    // Validate page count is a positive integer
    $page_count = intval($book_data['page_count']);
    if ($page_count <= 0) {
        return [
            'success' => false,
            'message' => 'Page count must be a positive number'
        ];
    }

    // Generate unique GID
    $gid = hs_generate_pending_book_gid();

    // Prepare data for insertion
    $insert_data = [
        'gid' => $gid,
        'user_id' => $user_id,
        'title' => sanitize_text_field($book_data['title']),
        'author' => sanitize_text_field($book_data['author']),
        'page_count' => $page_count,
        'description' => !empty($book_data['description']) ? wp_kses_post($book_data['description']) : null,
        'cover_url' => !empty($book_data['cover_url']) ? esc_url_raw($book_data['cover_url']) : null,
        'external_id' => !empty($book_data['external_id']) ? sanitize_text_field($book_data['external_id']) : null,
        'external_id_type' => !empty($book_data['external_id_type']) ? sanitize_text_field($book_data['external_id_type']) : null,
        'publication_year' => !empty($book_data['publication_year']) ? intval($book_data['publication_year']) : null,
        'publisher' => !empty($book_data['publisher']) ? sanitize_text_field($book_data['publisher']) : null,
        'status' => 'pending'
    ];

    $table_name = $wpdb->prefix . 'pending_books';
    $result = $wpdb->insert($table_name, $insert_data);

    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Failed to submit book: ' . $wpdb->last_error
        ];
    }

    $pending_book_id = $wpdb->insert_id;

    return [
        'success' => true,
        'message' => 'Book submitted successfully and is pending review',
        'data' => [
            'id' => $pending_book_id,
            'gid' => $gid,
            'status' => 'pending'
        ]
    ];
}

/**
 * Get a pending book by ID
 */
function hs_get_pending_book($pending_book_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'pending_books';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $pending_book_id
    ));
}

/**
 * Get pending book by GID
 */
function hs_get_pending_book_by_gid($gid)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'pending_books';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE gid = %s",
        $gid
    ));
}

/**
 * Get all pending books (for admin review)
 */
function hs_get_pending_books($status = 'pending', $limit = 50, $offset = 0)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'pending_books';

    if ($status === 'all') {
        $books = $wpdb->get_results($wpdb->prepare(
            "SELECT pb.*, u.display_name as submitter_name
            FROM {$table_name} pb
            LEFT JOIN {$wpdb->users} u ON pb.user_id = u.ID
            ORDER BY pb.submitted_at DESC
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    } else {
        $books = $wpdb->get_results($wpdb->prepare(
            "SELECT pb.*, u.display_name as submitter_name
            FROM {$table_name} pb
            LEFT JOIN {$wpdb->users} u ON pb.user_id = u.ID
            WHERE pb.status = %s
            ORDER BY pb.submitted_at DESC
            LIMIT %d OFFSET %d",
            $status,
            $limit,
            $offset
        ));
    }

    return $books;
}

/**
 * Get pending books submitted by a specific user
 */
function hs_get_user_pending_books($user_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'pending_books';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name}
        WHERE user_id = %d
        ORDER BY submitted_at DESC",
        $user_id
    ));
}

/**
 * Add a pending book to user's library
 */
function hs_add_pending_book_to_library($user_id, $pending_book_id)
{
    global $wpdb;

    // Verify pending book exists
    $pending_book = hs_get_pending_book($pending_book_id);
    if (!$pending_book) {
        return [
            'success' => false,
            'message' => 'Pending book not found'
        ];
    }

    // Check if already in library
    $table_name = $wpdb->prefix . 'user_books';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table_name}
        WHERE user_id = %d AND pending_book_id = %d",
        $user_id,
        $pending_book_id
    ));

    if ($exists) {
        return [
            'success' => false,
            'message' => 'This book is already in your library'
        ];
    }

    // Add to library
    $result = $wpdb->insert($table_name, [
        'user_id' => $user_id,
        'book_id' => 0, // No book_id yet
        'pending_book_id' => $pending_book_id,
        'current_page' => 0,
        'status' => 'reading'
    ]);

    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Failed to add to library: ' . $wpdb->last_error
        ];
    }

    return [
        'success' => true,
        'message' => 'Book added to your library',
        'data' => [
            'library_id' => $wpdb->insert_id
        ]
    ];
}

/**
 * Approve a pending book and create public book post
 */
function hs_approve_pending_book($pending_book_id, $admin_user_id)
{
    global $wpdb;

    $pending_book = hs_get_pending_book($pending_book_id);
    if (!$pending_book) {
        return [
            'success' => false,
            'message' => 'Pending book not found'
        ];
    }

    if ($pending_book->status !== 'pending') {
        return [
            'success' => false,
            'message' => 'Book has already been reviewed'
        ];
    }

    // Create the book post
    $post_data = [
        'post_title' => $pending_book->title,
        'post_content' => $pending_book->description ?? '',
        'post_status' => 'publish',
        'post_type' => 'book',
        'post_author' => $admin_user_id
    ];

    $book_id = wp_insert_post($post_data);

    if (is_wp_error($book_id)) {
        return [
            'success' => false,
            'message' => 'Failed to create book post: ' . $book_id->get_error_message()
        ];
    }

    // Update ACF fields
    update_field('book_author', $pending_book->author, $book_id);
    update_field('nop', $pending_book->page_count, $book_id);

    if ($pending_book->publication_year) {
        update_field('publication_year', $pending_book->publication_year, $book_id);
    }

    // Handle cover image if provided
    if ($pending_book->cover_url) {
        hs_download_and_set_book_cover($book_id, $pending_book->cover_url);
    }

    // Create GID entry for the book
    if (function_exists('hs_get_or_create_book_gid')) {
        $book_gid = hs_get_or_create_book_gid($book_id);
    }

    // If there's an external ID, store it in a custom field
    if ($pending_book->external_id) {
        update_post_meta($book_id, '_external_id', $pending_book->external_id);
        update_post_meta($book_id, '_external_id_type', $pending_book->external_id_type);
    }

    // Update pending book status
    $table_name = $wpdb->prefix . 'pending_books';
    $wpdb->update(
        $table_name,
        [
            'status' => 'approved',
            'reviewed_by' => $admin_user_id,
            'reviewed_at' => current_time('mysql'),
            'approved_book_id' => $book_id
        ],
        ['id' => $pending_book_id]
    );

    // Migrate user library entries
    hs_migrate_pending_book_to_approved($pending_book_id, $book_id);

    // Award achievement points to submitter
    if (function_exists('hs_increment_user_achievement_stat')) {
        hs_increment_user_achievement_stat($pending_book->user_id, 'books_added');
    }

    return [
        'success' => true,
        'message' => 'Book approved and published',
        'data' => [
            'book_id' => $book_id,
            'book_gid' => $book_gid ?? null
        ]
    ];
}

/**
 * Reject a pending book
 */
function hs_reject_pending_book($pending_book_id, $admin_user_id, $reason = '')
{
    global $wpdb;

    $pending_book = hs_get_pending_book($pending_book_id);
    if (!$pending_book) {
        return [
            'success' => false,
            'message' => 'Pending book not found'
        ];
    }

    if ($pending_book->status !== 'pending') {
        return [
            'success' => false,
            'message' => 'Book has already been reviewed'
        ];
    }

    $table_name = $wpdb->prefix . 'pending_books';
    $wpdb->update(
        $table_name,
        [
            'status' => 'rejected',
            'reviewed_by' => $admin_user_id,
            'reviewed_at' => current_time('mysql'),
            'rejection_reason' => sanitize_textarea_field($reason)
        ],
        ['id' => $pending_book_id]
    );

    return [
        'success' => true,
        'message' => 'Book rejected'
    ];
}

/**
 * Migrate user library entries from pending book to approved book
 */
function hs_migrate_pending_book_to_approved($pending_book_id, $book_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_books';

    // Update all user library entries
    $wpdb->update(
        $table_name,
        [
            'book_id' => $book_id,
            'pending_book_id' => null
        ],
        ['pending_book_id' => $pending_book_id]
    );
}

/**
 * Download and set book cover from URL
 */
function hs_download_and_set_book_cover($book_id, $image_url)
{
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $image_id = media_sideload_image($image_url, $book_id, '', 'id');

    if (!is_wp_error($image_id)) {
        set_post_thumbnail($book_id, $image_id);
    }
}

/**
 * Get count of pending books by status
 */
function hs_get_pending_books_count($status = 'pending')
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'pending_books';

    if ($status === 'all') {
        return $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }

    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
        $status
    ));
}

// Register activation hooks
register_activation_hook(HOTSOUP_PLUGIN_FILE, 'hs_create_pending_books_table');
register_activation_hook(HOTSOUP_PLUGIN_FILE, 'hs_update_user_books_table');

// Also run on init to catch updates
add_action('init', function() {
    $version = get_option('hs_pending_books_version', '0');
    if ($version < '1.0') {
        hs_create_pending_books_table();
        hs_update_user_books_table();
        update_option('hs_pending_books_version', '1.0');
    }
});
