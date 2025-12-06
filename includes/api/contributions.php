<?php
/**
 * REST API Endpoints for All User Contributions
 * Handles: Characters, Tags, and Chapter Summaries
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST API routes for all contributions
 */
function hs_register_contributions_api_routes() {
    // ===== CHARACTER SUBMISSIONS =====
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/characters/submit', [
        'methods' => 'POST',
        'callback' => 'hs_api_submit_characters',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'id' => ['required' => true, 'validate_callback' => function($p) { return is_numeric($p); }]
        ]
    ]);

    register_rest_route('gread/v1', '/books/(?P<id>\d+)/characters', [
        'methods' => 'GET',
        'callback' => 'hs_api_get_book_characters',
        'permission_callback' => '__return_true',
        'args' => ['id' => ['required' => true, 'validate_callback' => function($p) { return is_numeric($p); }]]
    ]);

    // ===== TAG SUGGESTIONS =====
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/tags/suggest', [
        'methods' => 'POST',
        'callback' => 'hs_api_submit_tag_suggestions',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'id' => ['required' => true, 'validate_callback' => function($p) { return is_numeric($p); }]
        ]
    ]);

    // ===== CHAPTER SUMMARIES =====
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/summaries/submit', [
        'methods' => 'POST',
        'callback' => 'hs_api_submit_chapter_summary',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'id' => ['required' => true, 'validate_callback' => function($p) { return is_numeric($p); }]
        ]
    ]);

    register_rest_route('gread/v1', '/books/(?P<id>\d+)/summaries', [
        'methods' => 'GET',
        'callback' => 'hs_api_get_book_summaries',
        'permission_callback' => '__return_true',
        'args' => ['id' => ['required' => true, 'validate_callback' => function($p) { return is_numeric($p); }]]
    ]);

    // ===== ADMIN ENDPOINTS =====
    // Characters
    register_rest_route('gread/v1', '/admin/characters/submissions/(?P<id>\d+)/approve', [
        'methods' => 'POST',
        'callback' => 'hs_api_admin_approve_characters',
        'permission_callback' => function() { return current_user_can('manage_options'); }
    ]);

    register_rest_route('gread/v1', '/admin/characters/submissions/(?P<id>\d+)/reject', [
        'methods' => 'POST',
        'callback' => 'hs_api_admin_reject_characters',
        'permission_callback' => function() { return current_user_can('manage_options'); }
    ]);

    // Tags
    register_rest_route('gread/v1', '/admin/tags/suggestions/(?P<id>\d+)/approve', [
        'methods' => 'POST',
        'callback' => 'hs_api_admin_approve_tag',
        'permission_callback' => function() { return current_user_can('manage_options'); }
    ]);

    register_rest_route('gread/v1', '/admin/tags/suggestions/(?P<id>\d+)/reject', [
        'methods' => 'POST',
        'callback' => 'hs_api_admin_reject_tag',
        'permission_callback' => function() { return current_user_can('manage_options'); }
    ]);

    // Summaries
    register_rest_route('gread/v1', '/admin/summaries/submissions/(?P<id>\d+)/approve', [
        'methods' => 'POST',
        'callback' => 'hs_api_admin_approve_summary',
        'permission_callback' => function() { return current_user_can('manage_options'); }
    ]);

    register_rest_route('gread/v1', '/admin/summaries/submissions/(?P<id>\d+)/reject', [
        'methods' => 'POST',
        'callback' => 'hs_api_admin_reject_summary',
        'permission_callback' => function() { return current_user_can('manage_options'); }
    ]);
}
add_action('rest_api_init', 'hs_register_contributions_api_routes');

// ===== API CALLBACKS =====

function hs_api_submit_characters($request) {
    $book_id = intval($request['id']);
    $user_id = get_current_user_id();

    // Get JSON body
    $json_params = $request->get_json_params();

    if (!isset($json_params['characters']) || !is_array($json_params['characters'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Characters data is required and must be an array.'
        ], 400);
    }

    $characters = [];
    foreach ($json_params['characters'] as $char) {
        $characters[] = [
            'name' => sanitize_text_field($char['name']),
            'description' => isset($char['description']) ? sanitize_text_field($char['description']) : ''
        ];
    }

    $result = hs_submit_characters($user_id, $book_id, $characters);
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

function hs_api_get_book_characters($request) {
    $book_id = intval($request['id']);
    $characters = hs_get_approved_characters($book_id);

    return new WP_REST_Response([
        'success' => true,
        'characters' => $characters ?: []
    ], 200);
}

function hs_api_submit_tag_suggestions($request) {
    $book_id = intval($request['id']);
    $user_id = get_current_user_id();

    // Get JSON body
    $json_params = $request->get_json_params();

    // Better error handling with debugging info
    if (!$json_params) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid JSON in request body.',
            'debug' => 'json_params is null - check Content-Type header'
        ], 400);
    }

    if (!isset($json_params['tags'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Tags parameter is missing.',
            'debug' => 'Available params: ' . implode(', ', array_keys($json_params))
        ], 400);
    }

    if (!is_array($json_params['tags'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Tags must be an array.',
            'debug' => 'Tags type: ' . gettype($json_params['tags'])
        ], 400);
    }

    if (empty($json_params['tags'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Please provide at least one tag.'
        ], 400);
    }

    $tags = array_map('sanitize_text_field', $json_params['tags']);

    $result = hs_submit_tag_suggestions($user_id, $book_id, $tags);
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

function hs_api_submit_chapter_summary($request) {
    $book_id = intval($request['id']);
    $user_id = get_current_user_id();

    // Get JSON body
    $json_params = $request->get_json_params();

    if (!isset($json_params['chapter_number']) || !isset($json_params['summary'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Chapter number and summary are required.'
        ], 400);
    }

    $chapter_number = intval($json_params['chapter_number']);
    $chapter_title = isset($json_params['chapter_title']) ? sanitize_text_field($json_params['chapter_title']) : '';
    $summary = sanitize_textarea_field($json_params['summary']);

    $result = hs_submit_chapter_summary($user_id, $book_id, $chapter_number, $chapter_title, $summary);
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

function hs_api_get_book_summaries($request) {
    $book_id = intval($request['id']);
    $summaries = hs_get_approved_chapter_summaries($book_id);

    return new WP_REST_Response([
        'success' => true,
        'summaries' => $summaries ?: []
    ], 200);
}

function hs_api_admin_approve_characters($request) {
    $result = hs_approve_character_submission(intval($request['id']), get_current_user_id());
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

function hs_api_admin_reject_characters($request) {
    $reason = $request->get_param('reason');
    $result = hs_reject_character_submission(intval($request['id']), get_current_user_id(), $reason);
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

function hs_api_admin_approve_tag($request) {
    $result = hs_approve_tag_suggestion(intval($request['id']), get_current_user_id());
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

function hs_api_admin_reject_tag($request) {
    $reason = $request->get_param('reason');
    $result = hs_reject_tag_suggestion(intval($request['id']), get_current_user_id(), $reason);
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

function hs_api_admin_approve_summary($request) {
    $result = hs_approve_chapter_summary(intval($request['id']), get_current_user_id());
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}

function hs_api_admin_reject_summary($request) {
    $reason = $request->get_param('reason');
    $result = hs_reject_chapter_summary(intval($request['id']), get_current_user_id(), $reason);
    return new WP_REST_Response($result, $result['success'] ? 200 : 400);
}
