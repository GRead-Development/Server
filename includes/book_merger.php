<?php
/**
 * Book Merge System
 *
 * Similar to the author merge system, this allows merging multiple book posts
 * with different ISBNs into a single canonical book record.
 */

/**
 * Merge one book into another
 *
 * Moves all ISBNs from the source book to the target book,
 * updates metadata, and marks the source book as merged.
 *
 * @param int $from_book_id The book post ID to merge FROM (will be marked as merged)
 * @param int $to_book_id The book post ID to merge TO (canonical book)
 * @param bool $sync_metadata Whether to sync metadata from canonical to merged book
 * @param string $reason Optional reason for the merge
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function hs_merge_books($from_book_id, $to_book_id, $sync_metadata = true, $reason = '')
{
    global $wpdb;

    // Validate inputs
    if (!$from_book_id || !$to_book_id) {
        return new WP_Error('invalid_book_ids', 'Both book IDs are required');
    }

    if ($from_book_id === $to_book_id) {
        return new WP_Error('same_book', 'Cannot merge a book into itself');
    }

    // Verify both posts exist and are books
    $from_post = get_post($from_book_id);
    $to_post = get_post($to_book_id);

    if (!$from_post || $from_post->post_type !== 'book') {
        return new WP_Error('invalid_from_book', 'Source book not found');
    }

    if (!$to_post || $to_post->post_type !== 'book') {
        return new WP_Error('invalid_to_book', 'Target book not found');
    }

    // Start transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Get or create GIDs for both books
        $from_gid = hs_get_or_create_gid($from_book_id);
        $to_gid = hs_get_or_create_gid($to_book_id);

        // Ensure both books' ISBNs are in the table (migrate from ACF if needed)
        hs_ensure_isbn_in_table($from_book_id, $from_gid);
        hs_ensure_isbn_in_table($to_book_id, $to_gid);

        // Update all posts with from_gid to use to_gid
        $updated = $wpdb->update(
            $wpdb->prefix . 'hs_gid',
            array(
                'gid' => $to_gid,
                'is_canonical' => 0,
                'merged_by' => get_current_user_id(),
                'merge_reason' => sanitize_text_field($reason),
                'date_merged' => current_time('mysql')
            ),
            array('post_id' => $from_book_id),
            array('%d', '%d', '%d', '%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            throw new Exception('Failed to update GID mapping');
        }

        // Move all ISBNs from from_gid to to_gid
        $isbn_updated = $wpdb->update(
            $wpdb->prefix . 'hs_book_isbns',
            array('gid' => $to_gid),
            array('gid' => $from_gid),
            array('%d'),
            array('%d')
        );

        // Update post_id for ISBNs that were associated with from_book_id
        $wpdb->update(
            $wpdb->prefix . 'hs_book_isbns',
            array('post_id' => $to_book_id),
            array('post_id' => $from_book_id),
            array('%d'),
            array('%d')
        );

        // Sync metadata if requested
        if ($sync_metadata) {
            hs_sync_book_metadata($to_book_id, $from_book_id);
        }

        // Update any duplicate reports
        $wpdb->update(
            $wpdb->prefix . 'hs_duplicate_reports',
            array('status' => 'merged', 'reviewed_by' => get_current_user_id()),
            array('primary_book_id' => $from_book_id),
            array('%s', '%d'),
            array('%d')
        );

        // Commit transaction
        $wpdb->query('COMMIT');

        // Fire action hook for other plugins
        do_action('hs_books_merged', $from_book_id, $to_book_id, $to_gid);

        return true;

    } catch (Exception $e) {
        // Rollback on error
        $wpdb->query('ROLLBACK');
        return new WP_Error('merge_failed', $e->getMessage());
    }
}

/**
 * Sync metadata from canonical book to merged book
 * Updates the merged book's title, author, and page count to match the canonical book
 *
 * @param int $canonical_book_id The canonical book (source of metadata)
 * @param int $merged_book_id The merged book (destination)
 * @return bool True on success
 */
function hs_sync_book_metadata($canonical_book_id, $merged_book_id)
{
    // Get metadata from canonical book
    $title = get_the_title($canonical_book_id);
    $author = get_field('book_author', $canonical_book_id);
    $page_count = get_field('nop', $canonical_book_id);
    $pub_year = get_field('publication_year', $canonical_book_id);
    $description = get_post_field('post_content', $canonical_book_id);

    // Update merged book post
    wp_update_post(array(
        'ID' => $merged_book_id,
        'post_title' => $title,
        'post_content' => $description
    ));

    // Update ACF fields
    if ($author) {
        update_field('book_author', $author, $merged_book_id);
    }

    if ($page_count) {
        update_field('nop', $page_count, $merged_book_id);
    }

    if ($pub_year) {
        update_field('publication_year', $pub_year, $merged_book_id);
    }

    // Copy featured image if exists
    $thumbnail_id = get_post_thumbnail_id($canonical_book_id);
    if ($thumbnail_id) {
        set_post_thumbnail($merged_book_id, $thumbnail_id);
    }

    return true;
}

/**
 * Get all books in a GID group
 *
 * @param int $gid The group ID
 * @return array Array of post objects
 */
function hs_get_books_by_gid($gid)
{
    $post_ids = hs_get_posts_by_gid($gid);
    if (empty($post_ids)) {
        return array();
    }

    $books = array();
    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'book') {
            $books[] = $post;
        }
    }

    return $books;
}

/**
 * Search for books by title, author, or ISBN
 *
 * @param string $search_term The search query
 * @param int $limit Maximum number of results
 * @return array Array of book data
 */
function hs_search_books($search_term, $limit = 20)
{
    global $wpdb;

    $search_term = sanitize_text_field($search_term);
    $search_like = '%' . $wpdb->esc_like($search_term) . '%';

    // Search in post titles and content
    $args = array(
        'post_type' => 'book',
        'posts_per_page' => $limit,
        's' => $search_term,
        'post_status' => 'publish'
    );

    $query = new WP_Query($args);
    $results = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            // Skip merged books (not canonical)
            $is_canonical = hs_is_canonical_book($post_id);
            $gid_entry = $wpdb->get_row($wpdb->prepare(
                "SELECT is_canonical FROM {$wpdb->prefix}hs_gid WHERE post_id = %d",
                $post_id
            ));

            // If book has a GID entry and is not canonical (is_canonical = 0), skip it
            if ($gid_entry && $gid_entry->is_canonical == 0) {
                continue;
            }

            $results[] = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'author' => get_field('book_author', $post_id),
                'isbn' => get_field('book_isbn', $post_id),
                'page_count' => get_field('nop', $post_id),
                'gid' => hs_get_gid($post_id),
                'is_canonical' => $is_canonical
            );
        }
        wp_reset_postdata();
    }

    // Also search by ISBN - return canonical book for each GID
    $isbn_results = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT bi.isbn, bi.gid
        FROM {$wpdb->prefix}hs_book_isbns bi
        WHERE bi.isbn LIKE %s
        LIMIT %d",
        $search_like,
        $limit
    ));

    foreach ($isbn_results as $isbn_result) {
        // Get the canonical book for this GID
        $canonical_post_id = hs_get_canonical_post($isbn_result->gid);

        if (!$canonical_post_id) {
            continue;
        }

        // Check if we already have this book in results
        $already_added = false;
        foreach ($results as $result) {
            if ($result['id'] == $canonical_post_id) {
                $already_added = true;
                break;
            }
        }

        if (!$already_added) {
            $post = get_post($canonical_post_id);
            if ($post && $post->post_status === 'publish') {
                $results[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'author' => get_field('book_author', $post->ID),
                    'isbn' => $isbn_result->isbn,
                    'page_count' => get_field('nop', $post->ID),
                    'gid' => $isbn_result->gid,
                    'is_canonical' => true
                );
            }
        }
    }

    return array_slice($results, 0, $limit);
}

/**
 * Check if a book is the canonical book in its GID group
 *
 * @param int $post_id The book post ID
 * @return bool True if canonical
 */
function hs_is_canonical_book($post_id)
{
    global $wpdb;

    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT is_canonical FROM {$wpdb->prefix}hs_gid WHERE post_id = %d",
        intval($post_id)
    ));

    return (bool) $result;
}

/**
 * Set a book as the canonical book in its GID group
 *
 * @param int $post_id The book post ID
 * @return bool True on success
 */
function hs_set_canonical_book($post_id)
{
    global $wpdb;

    $gid = hs_get_gid($post_id);
    if (!$gid) {
        return false;
    }

    // Unset all canonical flags for this GID
    $wpdb->update(
        $wpdb->prefix . 'hs_gid',
        array('is_canonical' => 0),
        array('gid' => $gid),
        array('%d'),
        array('%d')
    );

    // Set this book as canonical
    $result = $wpdb->update(
        $wpdb->prefix . 'hs_gid',
        array('is_canonical' => 1),
        array('post_id' => $post_id),
        array('%d'),
        array('%d')
    );

    return $result !== false;
}

/**
 * Get merge history for a book
 *
 * @param int $post_id The book post ID
 * @return array Array of merge records
 */
function hs_get_book_merge_history($post_id)
{
    global $wpdb;

    $gid = hs_get_gid($post_id);
    if (!$gid) {
        return array();
    }

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT g.*, p.post_title, u.display_name as merged_by_name
        FROM {$wpdb->prefix}hs_gid g
        LEFT JOIN {$wpdb->posts} p ON g.post_id = p.ID
        LEFT JOIN {$wpdb->users} u ON g.merged_by = u.ID
        WHERE g.gid = %d AND g.is_canonical = 0
        ORDER BY g.date_merged DESC",
        intval($gid)
    ));

    return $results;
}

/**
 * Add an ISBN to a book
 *
 * @param int $book_id The book post ID
 * @param string $isbn The ISBN to add
 * @param string $edition Edition information
 * @param int $year Publication year
 * @param bool $is_primary Whether this is the primary ISBN
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function hs_add_isbn_to_book($book_id, $isbn, $edition = '', $year = null, $is_primary = false)
{
    global $wpdb;

    // Validate book exists
    $post = get_post($book_id);
    if (!$post || $post->post_type !== 'book') {
        return new WP_Error('invalid_book', 'Book not found');
    }

    // Get or create GID
    $gid = hs_get_or_create_gid($book_id);

    // Clean ISBN
    $isbn = sanitize_text_field($isbn);

    // Check if ISBN already exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}hs_book_isbns WHERE isbn = %s",
        $isbn
    ));

    if ($exists) {
        return new WP_Error('isbn_exists', 'This ISBN is already associated with a book');
    }

    // If this is primary, unset other primary ISBNs for this GID
    if ($is_primary) {
        $wpdb->update(
            $wpdb->prefix . 'hs_book_isbns',
            array('is_primary' => 0),
            array('gid' => $gid),
            array('%d'),
            array('%d')
        );
    }

    // Insert the ISBN
    $result = $wpdb->insert(
        $wpdb->prefix . 'hs_book_isbns',
        array(
            'gid' => intval($gid),
            'post_id' => intval($book_id),
            'isbn' => $isbn,
            'edition' => sanitize_text_field($edition),
            'publication_year' => $year ? intval($year) : null,
            'is_primary' => $is_primary ? 1 : 0,
            'created_at' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s', '%d', '%d', '%s')
    );

    if ($result === false) {
        return new WP_Error('insert_failed', 'Failed to add ISBN');
    }

    return true;
}

/**
 * Remove an ISBN from a book
 *
 * @param string $isbn The ISBN to remove
 * @return bool True on success
 */
function hs_remove_isbn($isbn)
{
    global $wpdb;

    $result = $wpdb->delete(
        $wpdb->prefix . 'hs_book_isbns',
        array('isbn' => sanitize_text_field($isbn)),
        array('%s')
    );

    return $result !== false;
}

/**
 * Ensure a book's ISBN is in the hs_book_isbns table
 * Migrates from ACF field if needed
 *
 * @param int $book_id The book post ID
 * @param int $gid The book's GID
 * @return bool True if ISBN was ensured/added
 */
function hs_ensure_isbn_in_table($book_id, $gid)
{
    global $wpdb;

    // Check if this book already has ISBNs in the table
    $existing_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_isbns WHERE post_id = %d",
        $book_id
    ));

    // If ISBNs already exist, we're done
    if ($existing_count > 0) {
        return true;
    }

    // Get ISBN from ACF field
    $isbn = get_field('book_isbn', $book_id);
    if (empty($isbn)) {
        return false;
    }

    // Check if this ISBN already exists globally
    $existing_isbn = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_book_isbns WHERE isbn = %s",
        sanitize_text_field($isbn)
    ));

    if ($existing_isbn) {
        // ISBN already exists in the system
        // Just update it to point to this book's GID if needed
        if ($existing_isbn->gid != $gid) {
            $wpdb->update(
                $wpdb->prefix . 'hs_book_isbns',
                array('gid' => intval($gid)),
                array('isbn' => sanitize_text_field($isbn)),
                array('%d'),
                array('%s')
            );
        }
        return true;
    }

    // ISBN doesn't exist yet, insert it
    $year = get_field('publication_year', $book_id);
    $result = $wpdb->insert(
        $wpdb->prefix . 'hs_book_isbns',
        array(
            'gid' => intval($gid),
            'post_id' => intval($book_id),
            'isbn' => sanitize_text_field($isbn),
            'edition' => '',
            'publication_year' => $year ? intval($year) : null,
            'is_primary' => 1,
            'created_at' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s', '%d', '%d', '%s')
    );

    return $result !== false;
}

/**
 * Set a user's preferred ISBN for a book
 *
 * @param int $user_id The user ID
 * @param int $book_id The book post ID
 * @param string $isbn The ISBN they own
 * @return bool|WP_Error True on success
 */
function hs_set_user_book_isbn($user_id, $book_id, $isbn)
{
    global $wpdb;

    // Get the book's GID
    $book_gid = hs_get_gid($book_id);
    if (!$book_gid) {
        $book_gid = hs_get_or_create_gid($book_id);
    }

    // Verify the ISBN exists in the same GID group
    $isbn_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_book_isbns WHERE gid = %d AND isbn = %s",
        $book_gid,
        sanitize_text_field($isbn)
    ));

    if (!$isbn_record) {
        return new WP_Error('invalid_isbn', 'This ISBN is not associated with this book');
    }

    // Insert or update the user's preference
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}hs_user_book_isbns WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
    ));

    if ($existing) {
        // Update existing preference
        $result = $wpdb->update(
            $wpdb->prefix . 'hs_user_book_isbns',
            array(
                'isbn' => sanitize_text_field($isbn),
                'selected_at' => current_time('mysql')
            ),
            array(
                'user_id' => $user_id,
                'book_id' => $book_id
            ),
            array('%s', '%s'),
            array('%d', '%d')
        );
    } else {
        // Insert new preference
        $result = $wpdb->insert(
            $wpdb->prefix . 'hs_user_book_isbns',
            array(
                'user_id' => $user_id,
                'book_id' => $book_id,
                'isbn' => sanitize_text_field($isbn),
                'selected_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s')
        );
    }

    if ($result === false) {
        return new WP_Error('update_failed', 'Failed to update ISBN preference');
    }

    return true;
}

/**
 * Get a user's preferred ISBN for a book
 *
 * @param int $user_id The user ID
 * @param int $book_id The book post ID
 * @return string|null The ISBN or null if not set
 */
function hs_get_user_book_isbn($user_id, $book_id)
{
    global $wpdb;

    $isbn = $wpdb->get_var($wpdb->prepare(
        "SELECT isbn FROM {$wpdb->prefix}hs_user_book_isbns WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
    ));

    return $isbn;
}

/**
 * Get the page count for a specific ISBN
 *
 * @param string $isbn The ISBN
 * @return int|null Page count or null
 */
function hs_get_isbn_page_count($isbn)
{
    global $wpdb;

    // Get the post_id for this ISBN
    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->prefix}hs_book_isbns WHERE isbn = %s LIMIT 1",
        sanitize_text_field($isbn)
    ));

    if (!$post_id) {
        return null;
    }

    // Get page count from ACF field
    $page_count = get_field('nop', $post_id);
    return $page_count ? intval($page_count) : null;
}

/**
 * Get book details for a user including their preferred ISBN and page count
 *
 * @param int $book_id The book post ID
 * @param int $user_id The user ID
 * @return array|null Book details with user's edition info
 */
function hs_get_book_for_user($book_id, $user_id)
{
    $post = get_post($book_id);
    if (!$post || $post->post_type !== 'book') {
        return null;
    }

    // Get the book's GID to fetch all ISBNs in the group
    $gid = hs_get_gid($book_id);
    if (!$gid) {
        $gid = hs_get_or_create_gid($book_id);
    }

    // Get all available ISBNs for this GID (includes merged books)
    global $wpdb;
    $available_isbns = $wpdb->get_results($wpdb->prepare(
        "SELECT isbn, edition, publication_year, is_primary, post_id
        FROM {$wpdb->prefix}hs_book_isbns
        WHERE gid = %d
        ORDER BY is_primary DESC, created_at ASC",
        $gid
    ));

    // Get user's preferred ISBN
    $user_isbn = hs_get_user_book_isbn($user_id, $book_id);

    // Determine which ISBN data to use
    $active_isbn_data = null;
    if ($user_isbn) {
        // Find the user's preferred ISBN in the list
        foreach ($available_isbns as $isbn_record) {
            if ($isbn_record->isbn === $user_isbn) {
                $active_isbn_data = $isbn_record;
                break;
            }
        }
    }

    // If no user preference or not found, use primary ISBN
    if (!$active_isbn_data && !empty($available_isbns)) {
        foreach ($available_isbns as $isbn_record) {
            if ($isbn_record->is_primary) {
                $active_isbn_data = $isbn_record;
                break;
            }
        }
    }

    // If still no ISBN, use the first one
    if (!$active_isbn_data && !empty($available_isbns)) {
        $active_isbn_data = $available_isbns[0];
    }

    // Get page count - try from the active ISBN's post first
    $page_count = null;
    if ($active_isbn_data) {
        $page_count = get_field('nop', $active_isbn_data->post_id);
    }

    // Fallback to main book's page count
    if (!$page_count) {
        $page_count = get_field('nop', $book_id);
    }

    return array(
        'id' => $book_id,
        'title' => $post->post_title,
        'author' => get_field('book_author', $book_id),
        'description' => $post->post_content,
        'page_count' => $page_count ? intval($page_count) : 0,
        'user_isbn' => $user_isbn,
        'active_isbn' => $active_isbn_data ? $active_isbn_data->isbn : get_field('book_isbn', $book_id),
        'active_edition' => $active_isbn_data ? $active_isbn_data->edition : '',
        'active_year' => $active_isbn_data ? $active_isbn_data->publication_year : null,
        'available_isbns' => $available_isbns,
        'has_multiple_editions' => count($available_isbns) > 1
    );
}
