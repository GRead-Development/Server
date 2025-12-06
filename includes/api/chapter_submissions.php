<?php
/**
 * REST API Endpoints for Chapter Submissions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST API routes for chapter submissions
 */
function hs_register_chapter_submissions_api_routes() {
    // Submit chapters for a book
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/chapters/submit', [
        'methods' => 'POST',
        'callback' => 'hs_api_submit_chapters',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'chapters' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_array($param) && !empty($param);
                }
            ]
        ]
    ]);

    // Get user's chapter submissions
    register_rest_route('gread/v1', '/chapters/my-submissions', [
        'methods' => 'GET',
        'callback' => 'hs_api_get_my_chapter_submissions',
        'permission_callback' => 'is_user_logged_in'
    ]);

    // Get a specific submission
    register_rest_route('gread/v1', '/chapters/submissions/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'hs_api_get_chapter_submission',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // Get approved chapters for a book (public)
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/chapters', [
        'methods' => 'GET',
        'callback' => 'hs_api_get_book_chapters',
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

    // Admin: Get all pending chapter submissions
    register_rest_route('gread/v1', '/admin/chapters/submissions', [
        'methods' => 'GET',
        'callback' => 'hs_api_admin_get_chapter_submissions',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => [
            'status' => [
                'required' => false,
                'default' => 'pending',
                'validate_callback' => function($param) {
                    return in_array($param, ['pending', 'approved', 'rejected', 'all']);
                }
            ]
        ]
    ]);

    // Admin: Approve a chapter submission
    register_rest_route('gread/v1', '/admin/chapters/submissions/(?P<id>\d+)/approve', [
        'methods' => 'POST',
        'callback' => 'hs_api_admin_approve_chapter_submission',
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

    // Admin: Reject a chapter submission
    register_rest_route('gread/v1', '/admin/chapters/submissions/(?P<id>\d+)/reject', [
        'methods' => 'POST',
        'callback' => 'hs_api_admin_reject_chapter_submission',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'reason' => [
                'required' => false,
                'default' => ''
            ]
        ]
    ]);
}
add_action('rest_api_init', 'hs_register_chapter_submissions_api_routes');

/**
 * API: Submit chapters for a book
 */
function hs_api_submit_chapters($request) {
    $book_id = intval($request['id']);
    $user_id = get_current_user_id();
    $chapters = $request['chapters'];

    // Sanitize chapters data
    $sanitized_chapters = [];
    foreach ($chapters as $chapter) {
        $sanitized_chapters[] = [
            'number' => intval($chapter['number']),
            'title' => sanitize_text_field($chapter['title'])
        ];
    }

    // Sort chapters by number
    usort($sanitized_chapters, function($a, $b) {
        return $a['number'] - $b['number'];
    });

    $result = hs_submit_chapters($user_id, $book_id, $sanitized_chapters);

    if ($result['success']) {
        return new WP_REST_Response($result, 200);
    } else {
        return new WP_REST_Response($result, 400);
    }
}

/**
 * API: Get user's chapter submissions
 */
function hs_api_get_my_chapter_submissions($request) {
    $user_id = get_current_user_id();
    $submissions = hs_get_user_chapter_submissions($user_id);

    return new WP_REST_Response([
        'success' => true,
        'submissions' => $submissions
    ], 200);
}

/**
 * API: Get a specific chapter submission
 */
function hs_api_get_chapter_submission($request) {
    $submission_id = intval($request['id']);
    $user_id = get_current_user_id();

    $submission = hs_get_chapter_submission($submission_id);

    if (!$submission) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Submission not found.'
        ], 404);
    }

    // Check if user owns this submission or is admin
    if ($submission->user_id != $user_id && !current_user_can('manage_options')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'You do not have permission to view this submission.'
        ], 403);
    }

    // Add book info
    $book = get_post($submission->book_id);
    $submission->book_title = $book ? $book->post_title : 'Unknown Book';

    return new WP_REST_Response([
        'success' => true,
        'submission' => $submission
    ], 200);
}

/**
 * API: Get approved chapters for a book
 */
function hs_api_get_book_chapters($request) {
    $book_id = intval($request['id']);

    $chapters = hs_get_approved_chapters($book_id);

    if (!$chapters) {
        return new WP_REST_Response([
            'success' => true,
            'chapters' => [],
            'message' => 'No chapter information available for this book yet.'
        ], 200);
    }

    return new WP_REST_Response([
        'success' => true,
        'chapters' => $chapters
    ], 200);
}

/**
 * API: Admin - Get all chapter submissions with optional status filter
 */
function hs_api_admin_get_chapter_submissions($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_chapter_submissions';
    $status = $request->get_param('status');

    if ($status === 'all') {
        $submissions = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY submitted_at DESC"
        );
    } else {
        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE status = %s ORDER BY submitted_at DESC",
            $status
        ));
    }

    // Add book and user info to each submission
    foreach ($submissions as $submission) {
        // Decode chapters data
        if (!empty($submission->chapters_data)) {
            $submission->chapters = json_decode($submission->chapters_data, true);
        }

        // Add book info
        $book = get_post($submission->book_id);
        $submission->book_title = $book ? $book->post_title : 'Unknown Book';
        $submission->book_author = $book ? get_post_meta($book->ID, 'book_author', true) : '';

        // Add submitter info
        $user = get_userdata($submission->user_id);
        $submission->submitter_name = $user ? $user->display_name : 'Unknown User';

        // Add reviewer info if reviewed
        if ($submission->reviewed_by) {
            $reviewer = get_userdata($submission->reviewed_by);
            $submission->reviewer_name = $reviewer ? $reviewer->display_name : 'Unknown';
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'submissions' => $submissions
    ], 200);
}

/**
 * API: Admin - Approve a chapter submission
 */
function hs_api_admin_approve_chapter_submission($request) {
    $submission_id = intval($request['id']);
    $admin_user_id = get_current_user_id();

    $result = hs_approve_chapter_submission($submission_id, $admin_user_id);

    if ($result['success']) {
        return new WP_REST_Response($result, 200);
    } else {
        return new WP_REST_Response($result, 400);
    }
}

/**
 * API: Admin - Reject a chapter submission
 */
function hs_api_admin_reject_chapter_submission($request) {
    $submission_id = intval($request['id']);
    $admin_user_id = get_current_user_id();
    $reason = $request->get_param('reason');

    $result = hs_reject_chapter_submission($submission_id, $admin_user_id, $reason);

    if ($result['success']) {
        return new WP_REST_Response($result, 200);
    } else {
        return new WP_REST_Response($result, 400);
    }
}
