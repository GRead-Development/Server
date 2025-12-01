<?php

// API endpoints for random book selection

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register random book REST route
 */
function hs_register_random_book_route() {
    register_rest_route('gread/v1', '/books/random', array(
        'methods' => 'GET',
        'callback' => 'hs_rest_get_random_book',
        'permission_callback' => 'gread_check_user_permission',
    ));
}
add_action('rest_api_init', 'hs_register_random_book_route');

/**
 * REST API: Get random unread book
 *
 * GET /wp-json/gread/v1/books/random
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function hs_rest_get_random_book($request) {
    $user_id = get_current_user_id();

    // Use core function
    $book = hs_get_random_unread_book($user_id);

    if (!$book) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'No unread books in your library',
        ), 200);
    }

    return new WP_REST_Response(array(
        'success' => true,
        'book' => $book,
        'message' => 'How about reading ' . $book['title'] . '?',
    ), 200);
}
