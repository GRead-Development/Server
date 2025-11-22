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
 * Register the book details endpoint
 */
function gread_register_book_details_endpoint() {
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
