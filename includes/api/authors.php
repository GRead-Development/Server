<?php
/**
 * Author API Endpoints
 *
 * Provides comprehensive author and author page information for mobile apps.
 *
 * Available Endpoints:
 * - GET  /wp-json/gread/v1/authors                    - List all authors (paginated)
 * - GET  /wp-json/gread/v1/authors/{id}               - Get author details
 * - GET  /wp-json/gread/v1/authors/by-name/{name}     - Get author by name/slug
 * - GET  /wp-json/gread/v1/authors/{id}/books         - Get author's books with full metadata
 * - GET  /wp-json/gread/v1/authors/{id}/page          - Get complete author page (author info + books)
 * - POST /wp-json/gread/v1/authors                    - Create new author
 * - PUT  /wp-json/gread/v1/authors/{id}               - Update author
 */

function gread_api_get_authors($request) {
    global $wpdb;

    $search = isset($request['search']) ? sanitize_text_field($request['search']) : '';
    $page = intval($request['page']);
    $per_page = intval($request['per_page']);
    $offset = ($page - 1) * $per_page;

    $where = "1=1";
    $params = array();

    if (!empty($search)) {
        $where .= " AND (a.name LIKE %s OR a.canonical_name LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}hs_authors a WHERE {$where}";
    $total = empty($params) ? $wpdb->get_var($count_query) : $wpdb->get_var($wpdb->prepare($count_query, $params));

    $query_params = array_merge($params, array($per_page, $offset));
    $authors = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*,
         (SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_authors ba WHERE ba.author_id = a.id) as book_count
        FROM {$wpdb->prefix}hs_authors a
        WHERE {$where}
        ORDER BY a.name ASC
        LIMIT %d OFFSET %d",
        $query_params
    ));

    return new WP_REST_Response(array(
        'success' => true,
        'authors' => $authors,
        'pagination' => array(
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        )
    ), 200);
}

function gread_api_get_author($request) {
    $author_id = intval($request['author_id']);

    $author = hs_get_author($author_id);

    if (!$author) {
        return new WP_Error('not_found', 'Author not found', array('status' => 404));
    }

    $book_count = hs_get_author_book_count($author_id);
    $author->book_count = $book_count;

    $aliases = hs_get_author_aliases($author_id);
    $author->aliases = $aliases;

    return new WP_REST_Response(array(
        'success' => true,
        'author' => $author
    ), 200);
}

function gread_api_get_author_by_name($request) {
    $name = sanitize_text_field($request['name']);

    $author = hs_get_author_by_name($name);

    if (!$author) {
        return new WP_Error('not_found', 'Author not found', array('status' => 404));
    }

    $book_count = hs_get_author_book_count($author->id);
    $author->book_count = $book_count;

    $aliases = hs_get_author_aliases($author->id);
    $author->aliases = $aliases;

    return new WP_REST_Response(array(
        'success' => true,
        'author' => $author
    ), 200);
}

function gread_api_get_author_books($request) {
    global $wpdb;

    $author_id = intval($request['author_id']);

    $author = hs_get_author($author_id);
    if (!$author) {
        return new WP_Error('not_found', 'Author not found', array('status' => 404));
    }

    $books = hs_get_author_books($author_id);
    $total = hs_get_author_book_count($author_id);

    // Format books with full metadata for mobile apps
    $formatted_books = array();
    foreach ($books as $book) {
        $book_id = $book->ID;

        // Get book metadata
        $author_names = get_post_meta($book_id, 'book_author', true);
        $isbn = get_post_meta($book_id, 'book_isbn', true);
        $page_count = intval(get_post_meta($book_id, 'nop', true));
        $publication_year = get_post_meta($book_id, 'publication_year', true);
        $average_rating = floatval(get_post_meta($book_id, 'hs_average_rating', true));
        $review_count = intval(get_post_meta($book_id, 'hs_review_count', true));

        // Get cover image
        $cover_image_id = get_post_thumbnail_id($book_id);
        $cover_image_url = '';
        if ($cover_image_id) {
            $cover_image_url = wp_get_attachment_url($cover_image_id);
        }

        // Get number of readers
        $user_books_table = $wpdb->prefix . 'hs_user_books';
        $total_readers = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$user_books_table} WHERE book_id = %d",
            $book_id
        )));

        // Build formatted book data
        $formatted_books[] = array(
            'id' => $book_id,
            'title' => $book->post_title,
            'author' => $author_names ?: '',
            'description' => $book->post_excerpt ?: $book->post_content,
            'isbn' => $isbn ?: '',
            'page_count' => $page_count,
            'publication_year' => $publication_year ?: '',
            'cover_image' => $cover_image_url,
            'statistics' => array(
                'total_readers' => $total_readers,
                'average_rating' => $average_rating,
                'review_count' => $review_count
            )
        );
    }

    return new WP_REST_Response(array(
        'success' => true,
        'books' => $formatted_books,
        'author' => $author,
        'total' => intval($total)
    ), 200);
}

function gread_api_create_author($request) {
    $name = sanitize_text_field($request['name']);
    $bio = isset($request['bio']) ? sanitize_textarea_field($request['bio']) : '';
    $user_id = get_current_user_id();

    if (empty(trim($name))) {
        return new WP_Error('invalid_name', 'Author name cannot be empty', array('status' => 400));
    }

    $existing = hs_get_author_by_name($name);
    if ($existing) {
        return new WP_Error('duplicate', 'Author already exists', array('status' => 409));
    }

    $args = array('created_by' => $user_id);
    if (!empty($bio)) {
        $args['bio'] = $bio;
    }

    $author_id = hs_create_author($name, $args);

    if (!$author_id) {
        return new WP_Error('db_error', 'Failed to create author', array('status' => 500));
    }

    $author = hs_get_author($author_id);

    return new WP_REST_Response(array(
        'success' => true,
        'author' => $author,
        'message' => 'Author created successfully'
    ), 201);
}

function gread_api_update_author($request) {
    global $wpdb;

    $author_id = intval($request['author_id']);

    $author = hs_get_author($author_id);
    if (!$author) {
        return new WP_Error('not_found', 'Author not found', array('status' => 404));
    }

    $update_data = array('updated_at' => current_time('mysql'));
    $update_format = array('%s');

    if (isset($request['name'])) {
        $name = sanitize_text_field($request['name']);
        if (empty(trim($name))) {
            return new WP_Error('invalid_name', 'Author name cannot be empty', array('status' => 400));
        }
        $update_data['name'] = $name;
        $update_format[] = '%s';
    }

    if (isset($request['bio'])) {
        $update_data['bio'] = sanitize_textarea_field($request['bio']);
        $update_format[] = '%s';
    }

    $result = $wpdb->update(
        $wpdb->prefix . 'hs_authors',
        $update_data,
        array('id' => $author_id),
        $update_format,
        array('%d')
    );

    if ($result === false) {
        return new WP_Error('db_error', 'Failed to update author', array('status' => 500));
    }

    $updated_author = hs_get_author($author_id);

    return new WP_REST_Response(array(
        'success' => true,
        'author' => $updated_author,
        'message' => 'Author updated successfully'
    ), 200);
}

/**
 * Get comprehensive author page data
 * This endpoint combines author info with all their books in one response
 * Optimized for mobile apps to reduce API calls
 */
function gread_api_get_author_page($request) {
    global $wpdb;

    $author_id = intval($request['author_id']);

    // Get author details
    $author = hs_get_author($author_id);
    if (!$author) {
        return new WP_Error('not_found', 'Author not found', array('status' => 404));
    }

    // Add book count and aliases to author object
    $author->book_count = hs_get_author_book_count($author_id);
    $author->aliases = hs_get_author_aliases($author_id);

    // Get author's books
    $books = hs_get_author_books($author_id);

    // Format books with full metadata
    $formatted_books = array();
    foreach ($books as $book) {
        $book_id = $book->ID;

        // Get book metadata
        $author_names = get_post_meta($book_id, 'book_author', true);
        $isbn = get_post_meta($book_id, 'book_isbn', true);
        $page_count = intval(get_post_meta($book_id, 'nop', true));
        $publication_year = get_post_meta($book_id, 'publication_year', true);
        $average_rating = floatval(get_post_meta($book_id, 'hs_average_rating', true));
        $review_count = intval(get_post_meta($book_id, 'hs_review_count', true));

        // Get cover image
        $cover_image_id = get_post_thumbnail_id($book_id);
        $cover_image_url = '';
        if ($cover_image_id) {
            $cover_image_url = wp_get_attachment_url($cover_image_id);
        }

        // Get number of readers
        $user_books_table = $wpdb->prefix . 'hs_user_books';
        $total_readers = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$user_books_table} WHERE book_id = %d",
            $book_id
        )));

        // Build formatted book data
        $formatted_books[] = array(
            'id' => $book_id,
            'title' => $book->post_title,
            'author' => $author_names ?: '',
            'description' => $book->post_excerpt ?: $book->post_content,
            'isbn' => $isbn ?: '',
            'page_count' => $page_count,
            'publication_year' => $publication_year ?: '',
            'cover_image' => $cover_image_url,
            'statistics' => array(
                'total_readers' => $total_readers,
                'average_rating' => $average_rating,
                'review_count' => $review_count
            )
        );
    }

    return new WP_REST_Response(array(
        'success' => true,
        'author' => $author,
        'books' => $formatted_books,
        'total_books' => intval($author->book_count)
    ), 200);
}

