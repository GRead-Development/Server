<?php
/**
 * Mobile Quiz API Endpoints
 *
 * Optimized endpoints for mobile apps (iOS/Android)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register mobile-specific REST API routes
add_action('rest_api_init', function() {
    // Get quiz questions (mobile-optimized)
    register_rest_route('gread/v1', '/mobile/quiz/questions', array(
        'methods' => 'GET',
        'callback' => 'hs_mobile_api_get_questions',
        'permission_callback' => '__return_true'
    ));

    // Submit quiz (mobile-optimized with minimal response)
    register_rest_route('gread/v1', '/mobile/quiz/submit', array(
        'methods' => 'POST',
        'callback' => 'hs_mobile_api_submit_quiz',
        'permission_callback' => '__return_true'
    ));

    // Get book recommendations by session
    register_rest_route('gread/v1', '/mobile/quiz/recommendations/(?P<session_token>[a-zA-Z0-9]+)', array(
        'methods' => 'GET',
        'callback' => 'hs_mobile_api_get_recommendations',
        'permission_callback' => '__return_true'
    ));

    // Get single book details (mobile-optimized)
    register_rest_route('gread/v1', '/mobile/books/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'hs_mobile_api_get_book',
        'permission_callback' => '__return_true'
    ));

    // Get popular tags for tag selection question
    register_rest_route('gread/v1', '/mobile/quiz/tags', array(
        'methods' => 'GET',
        'callback' => 'hs_mobile_api_get_tags',
        'permission_callback' => '__return_true'
    ));

    // Health check for mobile apps
    register_rest_route('gread/v1', '/mobile/quiz/status', array(
        'methods' => 'GET',
        'callback' => 'hs_mobile_api_status',
        'permission_callback' => '__return_true'
    ));
});

/**
 * Get quiz questions (mobile-optimized)
 * Returns dynamic questions from database
 */
function hs_mobile_api_get_questions($request) {
    $questions = hs_get_quiz_questions();

    // Add dynamic tags option if tags question is enabled
    foreach ($questions as &$question) {
        if ($question['question_key'] === 'tags' && empty($question['options'])) {
            $question['options'] = hs_get_popular_book_tags(50);
        }
    }

    return rest_ensure_response(array(
        'success' => true,
        'questions' => $questions,
        'total_questions' => count($questions),
        'version' => '1.0'
    ));
}

/**
 * Submit quiz (mobile-optimized)
 * Smaller response payload for mobile bandwidth
 */
function hs_mobile_api_submit_quiz($request) {
    $params = $request->get_json_params();

    if (empty($params['responses'])) {
        return new WP_Error('missing_responses', 'Quiz responses are required', array('status' => 400));
    }

    $responses = $params['responses'];

    // Get user ID if logged in
    $user_id = get_current_user_id();
    $user_id = $user_id > 0 ? $user_id : null;

    // Process time_available to max_pages
    if (!empty($responses['time_available'])) {
        switch ($responses['time_available']) {
            case 'short':
                $responses['max_pages'] = 200;
                break;
            case 'moderate':
                $responses['max_pages'] = 400;
                break;
            case 'long':
                $responses['max_pages'] = 600;
                break;
            case 'no_limit':
                $responses['max_pages'] = 9999;
                break;
        }
    }

    // Get recommendations
    require_once plugin_dir_path(dirname(__FILE__)) . 'quiz_recommendation.php';
    $recommendations = hs_get_quiz_recommendations($responses, $user_id, 10);

    // Generate session token
    $session_token = bin2hex(random_bytes(16));

    // Save session
    $session_data = array(
        'user_id' => $user_id,
        'session_token' => $session_token,
        'responses' => $responses,
        'recommended_books' => array_column($recommendations, 'book_id')
    );

    hs_save_quiz_session($session_data);

    // Return minimal response for mobile
    $mobile_recommendations = array();
    foreach ($recommendations as $book) {
        $mobile_recommendations[] = array(
            'book_id' => $book['book_id'],
            'title' => $book['title'],
            'authors' => array_map(function($author) {
                return $author['name'];
            }, $book['authors'] ?? array()),
            'match_score' => round($book['match_score'], 1),
            'thumbnail' => $book['thumbnail'] ?? null,
            'page_count' => $book['page_count'] ?? null,
            'publication_year' => $book['publication_year'] ?? null,
            'description_short' => !empty($book['description']) ?
                mb_substr(strip_tags($book['description']), 0, 150) . '...' : null,
            'purchase_links' => $book['purchase_links'] ?? array()
        );
    }

    return rest_ensure_response(array(
        'success' => true,
        'session_token' => $session_token,
        'recommendations' => $mobile_recommendations,
        'count' => count($mobile_recommendations)
    ));
}

/**
 * Get recommendations from previous session
 */
function hs_mobile_api_get_recommendations($request) {
    $session_token = $request['session_token'];

    if (empty($session_token)) {
        return new WP_Error('missing_token', 'Session token is required', array('status' => 400));
    }

    $session = hs_get_quiz_session($session_token);

    if (!$session) {
        return new WP_Error('invalid_token', 'Session not found', array('status' => 404));
    }

    // Get full book details
    $recommendations = array();
    if (!empty($session['recommended_books'])) {
        foreach ($session['recommended_books'] as $book_id) {
            $book = hs_mobile_get_book_data($book_id);
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
 * Get single book details (mobile-optimized)
 */
function hs_mobile_api_get_book($request) {
    $book_id = (int) $request['id'];

    $book = hs_mobile_get_book_data($book_id);

    if (!$book) {
        return new WP_Error('book_not_found', 'Book not found', array('status' => 404));
    }

    return rest_ensure_response(array(
        'success' => true,
        'book' => $book
    ));
}

/**
 * Get mobile-optimized book data
 */
function hs_mobile_get_book_data($book_id) {
    $post = get_post($book_id);
    if (!$post || $post->post_type !== 'book') {
        return null;
    }

    // Get ACF fields
    $isbn = get_field('book_isbn', $book_id);
    $page_count = get_field('nop', $book_id);
    $publication_year = get_field('publication_year', $book_id);

    // Get authors
    require_once plugin_dir_path(dirname(__FILE__)) . 'quiz_recommendation.php';
    $authors = hs_get_book_authors_array($book_id);

    // Get purchase links
    $links = hs_get_book_links($book_id, $isbn);

    // Get thumbnail
    $thumbnail = get_the_post_thumbnail_url($book_id, 'medium');

    // Get metadata
    $metadata = hs_get_book_metadata($book_id);

    return array(
        'book_id' => $book_id,
        'title' => $post->post_title,
        'description' => $post->post_content,
        'description_short' => mb_substr(strip_tags($post->post_content), 0, 150) . '...',
        'isbn' => $isbn,
        'page_count' => $page_count,
        'publication_year' => $publication_year,
        'authors' => array_map(function($author) {
            return array(
                'id' => $author['id'],
                'name' => $author['name']
            );
        }, $authors),
        'thumbnail' => $thumbnail,
        'thumbnail_large' => get_the_post_thumbnail_url($book_id, 'large'),
        'permalink' => get_permalink($book_id),
        'metadata' => $metadata,
        'purchase_links' => $links
    );
}

/**
 * Get popular tags for tag selection
 */
function hs_mobile_api_get_tags($request) {
    $limit = $request->get_param('limit') ?? 50;
    $tags = hs_get_popular_book_tags($limit);

    return rest_ensure_response(array(
        'success' => true,
        'tags' => $tags,
        'count' => count($tags)
    ));
}

/**
 * Health check endpoint for mobile apps
 */
function hs_mobile_api_status($request) {
    global $wpdb;

    // Check if quiz tables exist
    $questions_table = $wpdb->prefix . 'hs_quiz_questions';
    $questions_exist = $wpdb->get_var("SHOW TABLES LIKE '$questions_table'") === $questions_table;

    $metadata_table = $wpdb->prefix . 'hs_book_metadata';
    $metadata_exist = $wpdb->get_var("SHOW TABLES LIKE '$metadata_table'") === $metadata_table;

    // Get stats
    $total_questions = $questions_exist ? $wpdb->get_var("SELECT COUNT(*) FROM $questions_table WHERE is_active = 1") : 0;
    $total_books_with_metadata = $metadata_exist ? $wpdb->get_var("SELECT COUNT(*) FROM $metadata_table") : 0;

    return rest_ensure_response(array(
        'success' => true,
        'status' => 'operational',
        'version' => '1.0',
        'quiz_available' => $questions_exist && $total_questions > 0,
        'stats' => array(
            'total_questions' => (int)$total_questions,
            'books_with_metadata' => (int)$total_books_with_metadata,
            'quiz_ready' => $total_questions > 0 && $total_books_with_metadata >= 10
        ),
        'endpoints' => array(
            'questions' => rest_url('gread/v1/mobile/quiz/questions'),
            'submit' => rest_url('gread/v1/mobile/quiz/submit'),
            'recommendations' => rest_url('gread/v1/mobile/quiz/recommendations/{session_token}'),
            'book' => rest_url('gread/v1/mobile/books/{book_id}'),
            'tags' => rest_url('gread/v1/mobile/quiz/tags')
        )
    ));
}
