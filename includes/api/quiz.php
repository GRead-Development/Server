<?php
/**
 * Quiz API Endpoints
 *
 * REST API endpoints for the GRead Quiz recommendation system
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register REST API routes
add_action('rest_api_init', function() {
    // Submit quiz and get recommendations
    register_rest_route('gread/v1', '/quiz/submit', array(
        'methods' => 'POST',
        'callback' => 'hs_api_quiz_submit',
        'permission_callback' => '__return_true' // Allow anonymous users
    ));

    // Get recommendations from previous session
    register_rest_route('gread/v1', '/quiz/recommendations/(?P<session_token>[a-zA-Z0-9]+)', array(
        'methods' => 'GET',
        'callback' => 'hs_api_quiz_get_recommendations',
        'permission_callback' => '__return_true'
    ));

    // Get quiz options (for frontend)
    register_rest_route('gread/v1', '/quiz/options', array(
        'methods' => 'GET',
        'callback' => 'hs_api_quiz_get_options',
        'permission_callback' => '__return_true'
    ));

    // Get book metadata
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/metadata', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_book_metadata',
        'permission_callback' => '__return_true'
    ));

    // Update book metadata (admin only)
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/metadata', array(
        'methods' => 'POST',
        'callback' => 'hs_api_update_book_metadata',
        'permission_callback' => 'hs_api_admin_permission'
    ));

    // Get book purchase links
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/purchase-links', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_book_links',
        'permission_callback' => '__return_true'
    ));

    // Add/update book purchase link (admin only)
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/purchase-links', array(
        'methods' => 'POST',
        'callback' => 'hs_api_add_book_link',
        'permission_callback' => 'hs_api_admin_permission'
    ));

    // Generate affiliate links automatically (admin only)
    register_rest_route('gread/v1', '/books/(?P<id>\d+)/generate-links', array(
        'methods' => 'POST',
        'callback' => 'hs_api_generate_book_links',
        'permission_callback' => 'hs_api_admin_permission'
    ));
});

/**
 * Check if user is admin
 */
function hs_api_admin_permission() {
    return current_user_can('manage_options');
}

/**
 * Submit quiz and get recommendations
 */
function hs_api_quiz_submit($request) {
    $params = $request->get_json_params();

    if (empty($params['responses'])) {
        return new WP_Error('missing_responses', 'Quiz responses are required', array('status' => 400));
    }

    $responses = $params['responses'];

    // Validate and sanitize responses
    $clean_responses = array();

    // Genres (array)
    if (!empty($responses['genres']) && is_array($responses['genres'])) {
        $clean_responses['genres'] = array_map('sanitize_text_field', $responses['genres']);
    }

    // Themes (array)
    if (!empty($responses['themes']) && is_array($responses['themes'])) {
        $clean_responses['themes'] = array_map('sanitize_text_field', $responses['themes']);
    }

    // Single select fields
    $single_fields = array('mood', 'reading_level', 'pacing', 'time_period', 'reading_frequency', 'time_available');
    foreach ($single_fields as $field) {
        if (!empty($responses[$field])) {
            $clean_responses[$field] = sanitize_text_field($responses[$field]);
        }
    }

    // Calculate max pages based on time_available
    if (!empty($clean_responses['time_available'])) {
        switch ($clean_responses['time_available']) {
            case 'short':
                $clean_responses['max_pages'] = 200;
                break;
            case 'moderate':
                $clean_responses['max_pages'] = 400;
                break;
            case 'long':
                $clean_responses['max_pages'] = 600;
                break;
            case 'no_limit':
                $clean_responses['max_pages'] = 9999;
                break;
        }
    }

    // Get user ID if logged in
    $user_id = get_current_user_id();
    $user_id = $user_id > 0 ? $user_id : null;

    // Get recommendations
    require_once plugin_dir_path(dirname(__FILE__)) . 'quiz_recommendation.php';
    $recommendations = hs_get_quiz_recommendations($clean_responses, $user_id, 10);

    // Generate session token
    $session_token = bin2hex(random_bytes(16));

    // Save session
    $session_data = array(
        'user_id' => $user_id,
        'session_token' => $session_token,
        'responses' => $clean_responses,
        'recommended_books' => array_column($recommendations, 'book_id')
    );

    hs_save_quiz_session($session_data);

    // Return recommendations
    return rest_ensure_response(array(
        'success' => true,
        'session_token' => $session_token,
        'recommendations' => $recommendations,
        'count' => count($recommendations)
    ));
}

/**
 * Get recommendations from a previous session
 */
function hs_api_quiz_get_recommendations($request) {
    $session_token = $request['session_token'];

    if (empty($session_token)) {
        return new WP_Error('missing_token', 'Session token is required', array('status' => 400));
    }

    $session = hs_get_quiz_session($session_token);

    if (!$session) {
        return new WP_Error('invalid_token', 'Session not found', array('status' => 404));
    }

    // Get full book details for the recommended books
    $recommendations = array();
    if (!empty($session['recommended_books'])) {
        foreach ($session['recommended_books'] as $book_id) {
            $book = hs_get_book_for_quiz($book_id);
            if ($book) {
                $recommendations[] = $book;
            }
        }
    }

    return rest_ensure_response(array(
        'success' => true,
        'session_token' => $session_token,
        'responses' => $session['responses'],
        'recommendations' => $recommendations,
        'count' => count($recommendations)
    ));
}

/**
 * Get a single book with all quiz-relevant data
 */
function hs_get_book_for_quiz($book_id) {
    global $wpdb;

    $post = get_post($book_id);
    if (!$post || $post->post_type !== 'book') {
        return null;
    }

    // Get ACF fields
    $isbn = get_field('book_isbn', $book_id);
    $page_count = get_field('nop', $book_id);
    $publication_year = get_field('publication_year', $book_id);

    // Get metadata
    $metadata = hs_get_book_metadata($book_id);

    // Get authors
    require_once plugin_dir_path(dirname(__FILE__)) . 'quiz_recommendation.php';
    $authors = hs_get_book_authors_array($book_id);

    // Get purchase links
    $links = hs_get_book_links($book_id, $isbn);

    // Get thumbnail
    $thumbnail = get_the_post_thumbnail_url($book_id, 'medium');

    return array(
        'book_id' => $book_id,
        'title' => $post->post_title,
        'description' => $post->post_content,
        'isbn' => $isbn,
        'page_count' => $page_count,
        'publication_year' => $publication_year,
        'authors' => $authors,
        'thumbnail' => $thumbnail,
        'permalink' => get_permalink($book_id),
        'metadata' => $metadata,
        'purchase_links' => $links
    );
}

/**
 * Get quiz options for frontend
 * Now returns dynamic questions from database
 */
function hs_api_quiz_get_options($request) {
    // Get dynamic questions from database
    $questions = hs_get_quiz_questions();

    // Add dynamic tags for tag questions
    foreach ($questions as &$question) {
        if ($question['question_key'] === 'tags' && empty($question['options'])) {
            $question['options'] = hs_get_popular_book_tags(50);
        }
    }

    // Also return legacy format for backwards compatibility
    require_once plugin_dir_path(dirname(__FILE__)) . 'quiz_recommendation.php';
    $legacy_options = hs_get_quiz_options();

    return rest_ensure_response(array(
        'success' => true,
        'questions' => $questions,  // New format (dynamic)
        'options' => $legacy_options,  // Legacy format (static)
        'version' => '2.0'
    ));
}

/**
 * Get book metadata
 */
function hs_api_get_book_metadata($request) {
    $book_id = (int) $request['id'];

    $metadata = hs_get_book_metadata($book_id);

    if (!$metadata) {
        return rest_ensure_response(array(
            'success' => true,
            'metadata' => null,
            'message' => 'No metadata found for this book'
        ));
    }

    return rest_ensure_response(array(
        'success' => true,
        'metadata' => $metadata
    ));
}

/**
 * Update book metadata (admin only)
 */
function hs_api_update_book_metadata($request) {
    $book_id = (int) $request['id'];
    $params = $request->get_json_params();

    if (empty($params['metadata'])) {
        return new WP_Error('missing_metadata', 'Metadata is required', array('status' => 400));
    }

    // Verify book exists
    $post = get_post($book_id);
    if (!$post || $post->post_type !== 'book') {
        return new WP_Error('invalid_book', 'Book not found', array('status' => 404));
    }

    $metadata = $params['metadata'];

    // Save metadata
    $result = hs_save_book_metadata($book_id, $metadata);

    if ($result) {
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Metadata updated successfully'
        ));
    } else {
        return new WP_Error('save_failed', 'Failed to save metadata', array('status' => 500));
    }
}

/**
 * Get book purchase links
 */
function hs_api_get_book_links($request) {
    $book_id = (int) $request['id'];
    $isbn = $request->get_param('isbn');

    $links = hs_get_book_links($book_id, $isbn);

    return rest_ensure_response(array(
        'success' => true,
        'links' => $links
    ));
}

/**
 * Add/update book purchase link (admin only)
 */
function hs_api_add_book_link($request) {
    $book_id = (int) $request['id'];
    $params = $request->get_json_params();

    if (empty($params['link_type']) || empty($params['url'])) {
        return new WP_Error('missing_fields', 'link_type and url are required', array('status' => 400));
    }

    $link_data = array(
        'book_id' => $book_id,
        'link_type' => sanitize_text_field($params['link_type']),
        'url' => esc_url_raw($params['url']),
        'isbn' => !empty($params['isbn']) ? sanitize_text_field($params['isbn']) : null,
        'is_affiliate' => !empty($params['is_affiliate']),
        'affiliate_program' => !empty($params['affiliate_program']) ? sanitize_text_field($params['affiliate_program']) : null,
        'display_text' => !empty($params['display_text']) ? sanitize_text_field($params['display_text']) : null,
        'priority' => !empty($params['priority']) ? (int) $params['priority'] : 0
    );

    if (!empty($params['id'])) {
        $link_data['id'] = (int) $params['id'];
    }

    $link_id = hs_save_book_link($link_data);

    return rest_ensure_response(array(
        'success' => true,
        'link_id' => $link_id,
        'message' => 'Link saved successfully'
    ));
}

/**
 * Generate affiliate links automatically (admin only)
 */
function hs_api_generate_book_links($request) {
    $book_id = (int) $request['id'];
    $params = $request->get_json_params();

    $isbn = !empty($params['isbn']) ? sanitize_text_field($params['isbn']) : get_field('book_isbn', $book_id);

    if (empty($isbn)) {
        return new WP_Error('missing_isbn', 'ISBN is required to generate links', array('status' => 400));
    }

    $links = hs_generate_affiliate_links($book_id, $isbn);

    return rest_ensure_response(array(
        'success' => true,
        'generated_links' => count($links),
        'links' => $links,
        'message' => 'Affiliate links generated successfully'
    ));
}
