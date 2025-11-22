<?php
/**
 * REST API endpoints for book merging and ISBN management
 */

// Register REST API routes
add_action('rest_api_init', function() {

    /* Search books
    register_rest_route('gread/v1', '/books/search', array(
        'methods' => 'GET',
        'callback' => 'gread_api_search_books',
        'permission_callback' => 'is_user_logged_in',
        'args' => array(
            'q' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description' => 'Search query'
            ),
            'limit' => array(
                'required' => false,
                'type' => 'integer',
                'default' => 20,
                'sanitize_callback' => 'absint'
            )
        )
    ));
*/
    // Get book details including ISBNs
    register_rest_route('gread/v1', '/books/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_book',
        'permission_callback' => 'is_user_logged_in',
        'args' => array(
            'id' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            )
        )
    ));

    // Merge books
    register_rest_route('gread/v1', '/books/merge', array(
        'methods' => 'POST',
        'callback' => 'gread_api_merge_books',
        'permission_callback' => 'gread_can_manage_books',
        'args' => array(
            'from_book_id' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ),
            'to_book_id' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ),
            'sync_metadata' => array(
                'required' => false,
                'type' => 'boolean',
                'default' => true
            ),
            'reason' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field'
            )
        )
    ));

    // Add ISBN to book
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/isbn', array(
        'methods' => 'POST',
        'callback' => 'gread_api_add_isbn',
        'permission_callback' => 'is_user_logged_in',
        'args' => array(
            'id' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ),
            'isbn' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'edition' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ),
            'year' => array(
                'required' => false,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ),
            'is_primary' => array(
                'required' => false,
                'type' => 'boolean',
                'default' => false
            )
        )
    ));

    // Get ISBNs for a book
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/isbns', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_book_isbns',
        'permission_callback' => 'is_user_logged_in',
        'args' => array(
            'id' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            )
        )
    ));

    // Remove ISBN
    register_rest_route('gread/v1', '/books/isbn/(?P<isbn>[^/]+)', array(
        'methods' => 'DELETE',
        'callback' => 'gread_api_remove_isbn',
        'permission_callback' => 'gread_can_manage_books',
        'args' => array(
            'isbn' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));

    // Report duplicate
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/report-duplicate', array(
        'methods' => 'POST',
        'callback' => 'gread_api_report_duplicate',
        'permission_callback' => 'is_user_logged_in',
        'args' => array(
            'id' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ),
            'reason' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default' => ''
            )
        )
    ));

    // Get books by GID
    register_rest_route('gread/v1', '/books/gid/(?P<gid>\d+)', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_books_by_gid',
        'permission_callback' => 'is_user_logged_in',
        'args' => array(
            'gid' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            )
        )
    ));

    // Set user's preferred ISBN for a book
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/my-isbn', array(
        'methods' => 'POST',
        'callback' => 'gread_api_set_user_isbn',
        'permission_callback' => 'is_user_logged_in',
        'args' => array(
            'id' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ),
            'isbn' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));

    // Get book details for current user (includes their preferred ISBN)
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/for-me', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_book_for_user',
        'permission_callback' => 'is_user_logged_in',
        'args' => array(
            'id' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            )
        )
    ));
});

/**
 * Permission callback for managing books
 */
function gread_can_manage_books()
{
    return current_user_can('edit_posts') || current_user_can('manage_options');
}

/**
 * Search books API endpoint
 */
function gread_api_search_books($request)
{
    $query = $request->get_param('q');
    $limit = $request->get_param('limit');

    $books = hs_search_books($query, $limit);

    return rest_ensure_response(array(
        'success' => true,
        'count' => count($books),
        'books' => $books
    ));
}

/**
 * Get book details API endpoint
 */
function gread_api_get_book($request)
{
    $book_id = $request->get_param('id');
    $post = get_post($book_id);

    if (!$post || $post->post_type !== 'book') {
        return new WP_Error('book_not_found', 'Book not found', array('status' => 404));
    }

    $isbns = hs_get_book_isbns($book_id);
    $gid = hs_get_gid($book_id);
    $is_canonical = hs_is_canonical_book($book_id);

    $book_data = array(
        'id' => $post->ID,
        'title' => $post->post_title,
        'author' => get_field('book_author', $post->ID),
        'page_count' => get_field('nop', $post->ID),
        'publication_year' => get_field('publication_year', $post->ID),
        'isbn' => get_field('book_isbn', $post->ID),
        'description' => $post->post_content,
        'gid' => $gid,
        'is_canonical' => $is_canonical,
        'isbns' => array()
    );

    foreach ($isbns as $isbn) {
        $book_data['isbns'][] = array(
            'isbn' => $isbn->isbn,
            'edition' => $isbn->edition,
            'year' => $isbn->publication_year,
            'is_primary' => (bool) $isbn->is_primary,
            'post_id' => $isbn->post_id
        );
    }

    // Get thumbnail
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    if ($thumbnail_id) {
        $book_data['cover_url'] = wp_get_attachment_image_url($thumbnail_id, 'full');
    }

    return rest_ensure_response(array(
        'success' => true,
        'book' => $book_data
    ));
}

/**
 * Merge books API endpoint
 */
function gread_api_merge_books($request)
{
    $from_book_id = $request->get_param('from_book_id');
    $to_book_id = $request->get_param('to_book_id');
    $sync_metadata = $request->get_param('sync_metadata');
    $reason = $request->get_param('reason');

    $result = hs_merge_books($from_book_id, $to_book_id, $sync_metadata, $reason);

    if (is_wp_error($result)) {
        return new WP_Error(
            $result->get_error_code(),
            $result->get_error_message(),
            array('status' => 400)
        );
    }

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Books merged successfully',
        'from_book_id' => $from_book_id,
        'to_book_id' => $to_book_id
    ));
}

/**
 * Add ISBN API endpoint
 */
function gread_api_add_isbn($request)
{
    $book_id = $request->get_param('id');
    $isbn = $request->get_param('isbn');
    $edition = $request->get_param('edition');
    $year = $request->get_param('year');
    $is_primary = $request->get_param('is_primary');

    $result = hs_add_isbn_to_book($book_id, $isbn, $edition, $year, $is_primary);

    if (is_wp_error($result)) {
        return new WP_Error(
            $result->get_error_code(),
            $result->get_error_message(),
            array('status' => 400)
        );
    }

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'ISBN added successfully',
        'isbn' => $isbn
    ));
}

/**
 * Get book ISBNs API endpoint
 */
function gread_api_get_book_isbns($request)
{
    $book_id = $request->get_param('id');
    $isbns = hs_get_book_isbns($book_id);

    $isbn_data = array();
    foreach ($isbns as $isbn) {
        $isbn_data[] = array(
            'isbn' => $isbn->isbn,
            'edition' => $isbn->edition,
            'year' => $isbn->publication_year,
            'is_primary' => (bool) $isbn->is_primary,
            'post_id' => $isbn->post_id
        );
    }

    return rest_ensure_response(array(
        'success' => true,
        'book_id' => $book_id,
        'isbns' => $isbn_data
    ));
}

/**
 * Remove ISBN API endpoint
 */
function gread_api_remove_isbn($request)
{
    $isbn = $request->get_param('isbn');
    $result = hs_remove_isbn($isbn);

    if (!$result) {
        return new WP_Error('remove_failed', 'Failed to remove ISBN', array('status' => 400));
    }

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'ISBN removed successfully'
    ));
}

/**
 * Report duplicate API endpoint
 */
function gread_api_report_duplicate($request)
{
    global $wpdb;

    $book_id = $request->get_param('id');
    $reason = $request->get_param('reason');
    $user_id = get_current_user_id();

    // Verify book exists
    $post = get_post($book_id);
    if (!$post || $post->post_type !== 'book') {
        return new WP_Error('book_not_found', 'Book not found', array('status' => 404));
    }

    // Insert report
    $result = $wpdb->insert(
        $wpdb->prefix . 'hs_duplicate_reports',
        array(
            'reporter_id' => $user_id,
            'primary_book_id' => $book_id,
            'reason' => $reason,
            'status' => 'pending',
            'date_reported' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s', '%s')
    );

    if ($result === false) {
        return new WP_Error('report_failed', 'Failed to submit report', array('status' => 500));
    }

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Duplicate report submitted successfully'
    ));
}

/**
 * Get books by GID API endpoint
 */
function gread_api_get_books_by_gid($request)
{
    $gid = $request->get_param('gid');
    $books = hs_get_books_by_gid($gid);

    $books_data = array();
    foreach ($books as $book) {
        $books_data[] = array(
            'id' => $book->ID,
            'title' => $book->post_title,
            'author' => get_field('book_author', $book->ID),
            'isbn' => get_field('book_isbn', $book->ID),
            'is_canonical' => hs_is_canonical_book($book->ID)
        );
    }

    return rest_ensure_response(array(
        'success' => true,
        'gid' => $gid,
        'count' => count($books_data),
        'books' => $books_data
    ));
}

/**
 * Set user's preferred ISBN API endpoint
 */
function gread_api_set_user_isbn($request)
{
    $book_id = $request->get_param('id');
    $isbn = $request->get_param('isbn');
    $user_id = get_current_user_id();

    $result = hs_set_user_book_isbn($user_id, $book_id, $isbn);

    if (is_wp_error($result)) {
        return new WP_Error(
            $result->get_error_code(),
            $result->get_error_message(),
            array('status' => 400)
        );
    }

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Your edition preference has been saved',
        'book_id' => $book_id,
        'isbn' => $isbn
    ));
}

/**
 * Get book details for user API endpoint
 */
function gread_api_get_book_for_user($request)
{
    $book_id = $request->get_param('id');
    $user_id = get_current_user_id();

    $book_data = hs_get_book_for_user($book_id, $user_id);

    if (!$book_data) {
        return new WP_Error('book_not_found', 'Book not found', array('status' => 404));
    }

    // Format available ISBNs for response
    $formatted_isbns = array();
    foreach ($book_data['available_isbns'] as $isbn_record) {
        $formatted_isbns[] = array(
            'isbn' => $isbn_record->isbn,
            'edition' => $isbn_record->edition,
            'year' => $isbn_record->publication_year,
            'is_primary' => (bool) $isbn_record->is_primary,
            'is_users' => $isbn_record->isbn === $book_data['user_isbn']
        );
    }

    $book_data['available_isbns'] = $formatted_isbns;

    // Add cover URL
    $thumbnail_id = get_post_thumbnail_id($book_id);
    if ($thumbnail_id) {
        $book_data['cover_url'] = wp_get_attachment_image_url($thumbnail_id, 'full');
    }

    return rest_ensure_response(array(
        'success' => true,
        'book' => $book_data
    ));
}
