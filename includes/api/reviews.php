<?php
/**
 * Reviews API Endpoints
 *
 * Provides REST API endpoints for book reviews and ratings.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register reviews API endpoints
 */
function gread_register_reviews_endpoints() {
    // Get/Create reviews for a book
    register_rest_route('gread/v1', '/books/(?P<book_id>\d+)/reviews', array(
        array(
            'methods' => 'GET',
            'callback' => 'gread_api_get_book_reviews',
            'permission_callback' => '__return_true',
            'args' => array(
                'page' => array(
                    'required' => false,
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'per_page' => array(
                    'required' => false,
                    'default' => 20,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    }
                )
            )
        ),
        array(
            'methods' => 'POST',
            'callback' => 'gread_api_create_review',
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => array(
                'rating' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 1 && $param <= 5;
                    },
                    'description' => 'Rating from 1 to 5'
                ),
                'review_text' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'description' => 'Optional review text'
                )
            )
        )
    ));

    // Get/Update/Delete single review
    register_rest_route('gread/v1', '/reviews/(?P<review_id>\d+)', array(
        array(
            'methods' => 'GET',
            'callback' => 'gread_api_get_review',
            'permission_callback' => '__return_true'
        ),
        array(
            'methods' => 'PUT',
            'callback' => 'gread_api_update_review',
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => array(
                'rating' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 1 && $param <= 5;
                    }
                ),
                'review_text' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field'
                )
            )
        ),
        array(
            'methods' => 'DELETE',
            'callback' => 'gread_api_delete_review',
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        )
    ));

    // Get current user's reviews
    register_rest_route('gread/v1', '/user/reviews', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_user_reviews',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
        'args' => array(
            'page' => array(
                'required' => false,
                'default' => 1
            ),
            'per_page' => array(
                'required' => false,
                'default' => 20
            )
        )
    ));

    // Get rating summary for a book
    register_rest_route('gread/v1', '/books/(?P<book_id>\d+)/rating', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_book_rating',
        'permission_callback' => '__return_true'
    ));
}

add_action('rest_api_init', 'gread_register_reviews_endpoints');

/**
 * Get all reviews for a book
 */
function gread_api_get_book_reviews($request) {
    $book_id = intval($request['book_id']);
    $page = isset($request['page']) ? intval($request['page']) : 1;
    $per_page = isset($request['per_page']) ? intval($request['per_page']) : 20;

    $offset = ($page - 1) * $per_page;

    $reviews = hs_get_book_reviews($book_id, $per_page, $offset);

    // Get total count
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE book_id = %d",
        $book_id
    ));

    return rest_ensure_response(array(
        'reviews' => $reviews,
        'total' => intval($total),
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ));
}

/**
 * Create or update a review
 */
function gread_api_create_review($request) {
    $book_id = intval($request['book_id']);
    $rating = intval($request['rating']);
    $review_text = isset($request['review_text']) ? sanitize_textarea_field($request['review_text']) : '';

    $user_id = get_current_user_id();

    // Check if book exists
    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return new WP_Error('invalid_book', 'Invalid book ID', array('status' => 404));
    }

    $review_id = hs_create_review($book_id, $user_id, $rating, $review_text);

    if (!$review_id) {
        return new WP_Error('create_failed', 'Failed to create review', array('status' => 500));
    }

    $review = hs_get_review($review_id);

    return rest_ensure_response(array(
        'success' => true,
        'review' => $review
    ));
}

/**
 * Get single review
 */
function gread_api_get_review($request) {
    $review_id = intval($request['review_id']);

    $review = hs_get_review($review_id);

    if (!$review) {
        return new WP_Error('review_not_found', 'Review not found', array('status' => 404));
    }

    // Add user info
    $user = get_userdata($review->user_id);
    if ($user) {
        $review->display_name = $user->display_name;
        $review->user_login = $user->user_login;
    }

    return rest_ensure_response($review);
}

/**
 * Update a review
 */
function gread_api_update_review($request) {
    $review_id = intval($request['review_id']);
    $rating = intval($request['rating']);
    $review_text = isset($request['review_text']) ? sanitize_textarea_field($request['review_text']) : '';

    $user_id = get_current_user_id();

    // Check if review exists and belongs to user
    $review = hs_get_review($review_id);
    if (!$review) {
        return new WP_Error('review_not_found', 'Review not found', array('status' => 404));
    }

    if ($review->user_id != $user_id) {
        return new WP_Error('unauthorized', 'You can only edit your own reviews', array('status' => 403));
    }

    $result = hs_update_review($review_id, $rating, $review_text);

    if (!$result) {
        return new WP_Error('update_failed', 'Failed to update review', array('status' => 500));
    }

    $review = hs_get_review($review_id);

    return rest_ensure_response(array(
        'success' => true,
        'review' => $review
    ));
}

/**
 * Delete a review
 */
function gread_api_delete_review($request) {
    $review_id = intval($request['review_id']);
    $user_id = get_current_user_id();

    // Check if review exists and belongs to user
    $review = hs_get_review($review_id);
    if (!$review) {
        return new WP_Error('review_not_found', 'Review not found', array('status' => 404));
    }

    if ($review->user_id != $user_id && !current_user_can('manage_options')) {
        return new WP_Error('unauthorized', 'You can only delete your own reviews', array('status' => 403));
    }

    $result = hs_delete_review($review_id);

    if (!$result) {
        return new WP_Error('delete_failed', 'Failed to delete review', array('status' => 500));
    }

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Review deleted successfully'
    ));
}

/**
 * Get current user's reviews
 */
function gread_api_get_user_reviews($request) {
    $page = isset($request['page']) ? intval($request['page']) : 1;
    $per_page = isset($request['per_page']) ? intval($request['per_page']) : 20;
    $user_id = get_current_user_id();

    $offset = ($page - 1) * $per_page;

    $reviews = hs_get_user_reviews($user_id, $per_page, $offset);

    // Get total count
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    return rest_ensure_response(array(
        'reviews' => $reviews,
        'total' => intval($total),
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ));
}

/**
 * Get rating summary for a book
 */
function gread_api_get_book_rating($request) {
    $book_id = intval($request['book_id']);

    // Check if book exists
    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return new WP_Error('invalid_book', 'Invalid book ID', array('status' => 404));
    }

    $rating_info = hs_get_book_average_rating($book_id);
    $distribution = hs_get_book_rating_distribution($book_id);

    return rest_ensure_response(array(
        'book_id' => $book_id,
        'average_rating' => $rating_info['average_rating'],
        'review_count' => $rating_info['review_count'],
        'rating_distribution' => $distribution
    ));
}
