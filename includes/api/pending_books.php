<?php
/**
 * REST API Endpoints for Pending Books
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST API routes for pending books
 */
function hs_register_pending_books_routes()
{
    // Submit a new book without ISBN
    register_rest_route('gread/v1', '/books/submit', [
        'methods' => 'POST',
        'callback' => 'hs_rest_submit_pending_book',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);

    // Add pending book to user's library
    register_rest_route('gread/v1', '/pending-books/(?P<id>\d+)/add-to-library', [
        'methods' => 'POST',
        'callback' => 'hs_rest_add_pending_book_to_library',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // Get user's submitted books
    register_rest_route('gread/v1', '/pending-books/my-submissions', [
        'methods' => 'GET',
        'callback' => 'hs_rest_get_user_pending_books',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);

    // Get a specific pending book
    register_rest_route('gread/v1', '/pending-books/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'hs_rest_get_pending_book',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // Admin: Get all pending books for review
    register_rest_route('gread/v1', '/admin/pending-books', [
        'methods' => 'GET',
        'callback' => 'hs_rest_get_pending_books_admin',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);

    // Admin: Approve a pending book
    register_rest_route('gread/v1', '/admin/pending-books/(?P<id>\d+)/approve', [
        'methods' => 'POST',
        'callback' => 'hs_rest_approve_pending_book',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // Admin: Reject a pending book
    register_rest_route('gread/v1', '/admin/pending-books/(?P<id>\d+)/reject', [
        'methods' => 'POST',
        'callback' => 'hs_rest_reject_pending_book',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);
}
add_action('rest_api_init', 'hs_register_pending_books_routes');

/**
 * REST: Submit a new book without ISBN
 */
function hs_rest_submit_pending_book($request)
{
    $user_id = get_current_user_id();

    $book_data = [
        'title' => $request->get_param('title'),
        'author' => $request->get_param('author'),
        'page_count' => $request->get_param('page_count'),
        'description' => $request->get_param('description'),
        'cover_url' => $request->get_param('cover_url'),
        'external_id' => $request->get_param('external_id'),
        'external_id_type' => $request->get_param('external_id_type'),
        'publication_year' => $request->get_param('publication_year'),
        'publisher' => $request->get_param('publisher')
    ];

    $result = hs_submit_pending_book($user_id, $book_data);

    if (!$result['success']) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    return new WP_REST_Response($result, 200);
}

/**
 * REST: Add pending book to user's library
 */
function hs_rest_add_pending_book_to_library($request)
{
    $user_id = get_current_user_id();
    $pending_book_id = $request->get_param('id');

    $result = hs_add_pending_book_to_library($user_id, $pending_book_id);

    if (!$result['success']) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    return new WP_REST_Response($result, 200);
}

/**
 * REST: Get user's submitted books
 */
function hs_rest_get_user_pending_books($request)
{
    $user_id = get_current_user_id();
    $books = hs_get_user_pending_books($user_id);

    return new WP_REST_Response([
        'success' => true,
        'data' => $books
    ], 200);
}

/**
 * REST: Get a specific pending book
 */
function hs_rest_get_pending_book($request)
{
    $pending_book_id = $request->get_param('id');
    $book = hs_get_pending_book($pending_book_id);

    if (!$book) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Pending book not found'
        ], 404);
    }

    // Hide sensitive information for non-admins
    if (!current_user_can('manage_options')) {
        unset($book->reviewed_by);
        unset($book->rejection_reason);
    }

    return new WP_REST_Response([
        'success' => true,
        'data' => $book
    ], 200);
}

/**
 * REST: Get all pending books for admin review
 */
function hs_rest_get_pending_books_admin($request)
{
    $status = $request->get_param('status') ?? 'pending';
    $limit = $request->get_param('limit') ?? 50;
    $offset = $request->get_param('offset') ?? 0;

    $books = hs_get_pending_books($status, $limit, $offset);
    $count = hs_get_pending_books_count($status);

    return new WP_REST_Response([
        'success' => true,
        'data' => [
            'books' => $books,
            'total' => $count,
            'limit' => $limit,
            'offset' => $offset
        ]
    ], 200);
}

/**
 * REST: Approve a pending book
 */
function hs_rest_approve_pending_book($request)
{
    $pending_book_id = $request->get_param('id');
    $admin_user_id = get_current_user_id();

    $result = hs_approve_pending_book($pending_book_id, $admin_user_id);

    if (!$result['success']) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    return new WP_REST_Response($result, 200);
}

/**
 * REST: Reject a pending book
 */
function hs_rest_reject_pending_book($request)
{
    $pending_book_id = $request->get_param('id');
    $admin_user_id = get_current_user_id();
    $reason = $request->get_param('reason') ?? '';

    $result = hs_reject_pending_book($pending_book_id, $admin_user_id, $reason);

    if (!$result['success']) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    return new WP_REST_Response($result, 200);
}
