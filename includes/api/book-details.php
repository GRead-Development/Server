<?php
/**
 * Book Details API Endpoint
 *
 * Provides comprehensive book information for display in the iOS app.
 * Endpoint: GET /wp-json/gread/v1/book/{id}
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the book details endpoints
 */
function gread_register_book_details_endpoint() {
    // Legacy endpoint /book/{id}
    register_rest_route('gread/v1', '/book/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'gread_get_book_details',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
                'description' => 'The book post ID'
            )
        )
    ));

    // New endpoint /books/{id} - more comprehensive
    register_rest_route('gread/v1', '/books/(?P<book_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'gread_get_book_details_enhanced',
        'permission_callback' => '__return_true',
        'args' => array(
            'book_id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
                'description' => 'The book post ID'
            )
        )
    ));
}

add_action('rest_api_init', 'gread_register_book_details_endpoint');

/**
 * Get detailed book information
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response|WP_Error
 */
function gread_get_book_details($request) {
    global $wpdb;

    $book_id = intval($request['id']);

    // Get the book post
    $book = get_post($book_id);

    if (!$book || $book->post_type !== 'book' || $book->post_status !== 'publish') {
        return new WP_Error(
            'book_not_found',
            'Book not found',
            array('status' => 404)
        );
    }

    // Get book metadata
    $author = get_post_meta($book_id, 'book_author', true);
    $isbn = get_post_meta($book_id, 'book_isbn', true);
    $page_count = intval(get_post_meta($book_id, 'nop', true));
    $publication_year = get_post_meta($book_id, 'publication_year', true);
    $average_rating = floatval(get_post_meta($book_id, 'hs_average_rating', true));
    $review_count = intval(get_post_meta($book_id, 'hs_review_count', true));

    // Get number of readers
    $user_books_table = $wpdb->prefix . 'hs_user_books';
    $total_readers = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(user_id) FROM {$user_books_table} WHERE book_id = %d",
        $book_id
    )));

    // Get featured image
    $cover_image_id = get_post_thumbnail_id($book_id);
    $cover_image_url = '';
    if ($cover_image_id) {
        $cover_image_url = wp_get_attachment_url($cover_image_id);
    }

    // Build response
    $response = array(
        'id' => $book_id,
        'title' => $book->post_title,
        'author' => $author ?: '',
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

    return rest_ensure_response($response);
}

/**
 * Get comprehensive book details (enhanced version)
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response|WP_Error
 */
function gread_get_book_details_enhanced($request) {
    $book_id = intval($request['book_id']);

    // Get book post
    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return new WP_Error('book_not_found', 'Book not found', array('status' => 404));
    }

    // Build comprehensive response
    $response = array(
        'id' => $book_id,
        'title' => $book->post_title,
        'content' => $book->post_content,
        'description' => $book->post_excerpt,
        'permalink' => get_permalink($book_id),
        'publication_year' => get_post_meta($book_id, 'publication_year', true),
        'page_count' => intval(get_post_meta($book_id, 'nop', true)),
        'isbn' => get_post_meta($book_id, 'book_isbn', true),
        'created_at' => $book->post_date,
        'modified_at' => $book->post_modified
    );

    // Get authors using the new author system
    $authors = array();
    if (function_exists('hs_get_book_authors')) {
        $book_authors = hs_get_book_authors($book_id);
        if ($book_authors) {
            foreach ($book_authors as $author) {
                $authors[] = array(
                    'id' => $author->author_id,
                    'name' => $author->author_name,
                    'slug' => $author->author_slug,
                    'order' => isset($author->author_order) ? $author->author_order : 1
                );
            }
        }
    }

    // Fallback to old author meta field if no authors found
    if (empty($authors)) {
        $legacy_author = get_post_meta($book_id, 'book_author', true);
        if ($legacy_author) {
            $authors[] = array(
                'id' => null,
                'name' => $legacy_author,
                'slug' => sanitize_title($legacy_author),
                'order' => 1
            );
        }
    }
    $response['authors'] = $authors;

    // Get series
    $series = array();
    if (function_exists('hs_get_book_series')) {
        $book_series = hs_get_book_series($book_id);
        if ($book_series) {
            foreach ($book_series as $s) {
                $series[] = array(
                    'id' => $s->series_id,
                    'name' => $s->series_name,
                    'slug' => $s->series_slug,
                    'position' => $s->position,
                    'description' => isset($s->description) ? $s->description : ''
                );
            }
        }
    }
    $response['series'] = $series;

    // Get rating information
    if (function_exists('hs_get_book_average_rating')) {
        $rating_info = hs_get_book_average_rating($book_id);
        $response['rating'] = array(
            'average' => floatval($rating_info['average_rating']),
            'count' => intval($rating_info['review_count'])
        );
    } else {
        // Fallback to meta fields
        $response['rating'] = array(
            'average' => floatval(get_post_meta($book_id, 'hs_average_rating', true)),
            'count' => intval(get_post_meta($book_id, 'hs_review_count', true))
        );
    }

    // Get user's review if logged in
    $response['user_review'] = null;
    if (is_user_logged_in() && function_exists('hs_get_user_review')) {
        $user_id = get_current_user_id();
        $user_review = hs_get_user_review($book_id, $user_id);
        if ($user_review) {
            $response['user_review'] = array(
                'id' => $user_review->id,
                'rating' => intval($user_review->rating),
                'review_text' => $user_review->review_text,
                'created_at' => $user_review->created_at,
                'updated_at' => $user_review->updated_at
            );
        }
    }

    // Get ISBNs
    if (function_exists('hs_get_book_isbns')) {
        $isbns = hs_get_book_isbns($book_id);
        $response['isbns'] = $isbns ? $isbns : array();
    } else {
        $response['isbns'] = array();
    }

    // Get tags
    if (function_exists('hs_get_book_tags')) {
        $tags = hs_get_book_tags($book_id);
        $response['tags'] = $tags ? $tags : array();
    } else {
        $response['tags'] = array();
    }

    // Get thumbnail/cover
    $thumbnail_id = get_post_thumbnail_id($book_id);
    if ($thumbnail_id) {
        $response['cover_image'] = array(
            'thumbnail' => wp_get_attachment_image_url($thumbnail_id, 'thumbnail'),
            'medium' => wp_get_attachment_image_url($thumbnail_id, 'medium'),
            'large' => wp_get_attachment_image_url($thumbnail_id, 'large'),
            'full' => wp_get_attachment_image_url($thumbnail_id, 'full')
        );
    } else {
        $response['cover_image'] = null;
    }

    // Get number of readers
    global $wpdb;
    $user_books_table = $wpdb->prefix . 'user_books';
    if ($wpdb->get_var("SHOW TABLES LIKE '$user_books_table'") == $user_books_table) {
        $total_readers = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$user_books_table} WHERE book_id = %d",
            $book_id
        )));
        $response['statistics'] = array(
            'total_readers' => $total_readers
        );
    }

    return rest_ensure_response($response);
}
