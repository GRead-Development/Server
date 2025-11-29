<?php
/**
 * Series API Endpoints
 *
 * Provides REST API endpoints for managing book series.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register series API endpoints
 */
function gread_register_series_endpoints() {
    // List/Search series
    register_rest_route('gread/v1', '/series', array(
        array(
            'methods' => 'GET',
            'callback' => 'gread_api_get_series_list',
            'permission_callback' => '__return_true',
            'args' => array(
                'search' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Search term for series name'
                ),
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
            'callback' => 'gread_api_create_series',
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => array(
                'name' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'description' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field'
                )
            )
        )
    ));

    // Get/Update/Delete single series
    register_rest_route('gread/v1', '/series/(?P<series_id>\d+)', array(
        array(
            'methods' => 'GET',
            'callback' => 'gread_api_get_series',
            'permission_callback' => '__return_true'
        ),
        array(
            'methods' => 'PUT',
            'callback' => 'gread_api_update_series',
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ),
        array(
            'methods' => 'DELETE',
            'callback' => 'gread_api_delete_series',
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        )
    ));

    // Get books in a series
    register_rest_route('gread/v1', '/series/(?P<series_id>\d+)/books', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_series_books',
        'permission_callback' => '__return_true'
    ));
}

add_action('rest_api_init', 'gread_register_series_endpoints');

/**
 * Get list of series with optional search
 */
function gread_api_get_series_list($request) {
    $search = isset($request['search']) ? sanitize_text_field($request['search']) : '';
    $page = isset($request['page']) ? intval($request['page']) : 1;
    $per_page = isset($request['per_page']) ? intval($request['per_page']) : 20;

    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_series';
    $offset = ($page - 1) * $per_page;

    if (!empty($search)) {
        $series = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE name LIKE %s
             ORDER BY name ASC
             LIMIT %d OFFSET %d",
            '%' . $wpdb->esc_like($search) . '%',
            $per_page,
            $offset
        ));

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE name LIKE %s",
            '%' . $wpdb->esc_like($search) . '%'
        ));
    } else {
        $series = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
             ORDER BY name ASC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    return rest_ensure_response(array(
        'series' => $series,
        'total' => intval($total),
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ));
}

/**
 * Get single series details
 */
function gread_api_get_series($request) {
    $series_id = intval($request['series_id']);
    $series = hs_get_series($series_id);

    if (!$series) {
        return new WP_Error('series_not_found', 'Series not found', array('status' => 404));
    }

    return rest_ensure_response($series);
}

/**
 * Create new series
 */
function gread_api_create_series($request) {
    $name = sanitize_text_field($request['name']);
    $description = isset($request['description']) ? sanitize_textarea_field($request['description']) : '';

    $user_id = get_current_user_id();

    $series_id = hs_create_series($name, $description, $user_id);

    if (!$series_id) {
        return new WP_Error('create_failed', 'Failed to create series', array('status' => 500));
    }

    $series = hs_get_series($series_id);

    return rest_ensure_response(array(
        'success' => true,
        'series' => $series
    ));
}

/**
 * Update series
 */
function gread_api_update_series($request) {
    $series_id = intval($request['series_id']);
    $name = isset($request['name']) ? sanitize_text_field($request['name']) : null;
    $description = isset($request['description']) ? sanitize_textarea_field($request['description']) : null;

    $series = hs_get_series($series_id);
    if (!$series) {
        return new WP_Error('series_not_found', 'Series not found', array('status' => 404));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_series';

    $update_data = array();
    $update_format = array();

    if ($name !== null) {
        $update_data['name'] = $name;
        $update_data['slug'] = sanitize_title($name);
        $update_format[] = '%s';
        $update_format[] = '%s';
    }

    if ($description !== null) {
        $update_data['description'] = $description;
        $update_format[] = '%s';
    }

    if (!empty($update_data)) {
        $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $series_id),
            $update_format,
            array('%d')
        );
    }

    $series = hs_get_series($series_id);

    return rest_ensure_response(array(
        'success' => true,
        'series' => $series
    ));
}

/**
 * Delete series
 */
function gread_api_delete_series($request) {
    $series_id = intval($request['series_id']);

    $result = hs_delete_series($series_id);

    if (!$result) {
        return new WP_Error('delete_failed', 'Failed to delete series', array('status' => 500));
    }

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Series deleted successfully'
    ));
}

/**
 * Get books in a series
 */
function gread_api_get_series_books($request) {
    $series_id = intval($request['series_id']);

    $books = hs_get_series_books($series_id);

    if ($books === false) {
        return new WP_Error('series_not_found', 'Series not found', array('status' => 404));
    }

    // Enhance books with additional data
    foreach ($books as &$book) {
        $post = get_post($book->book_id);
        if ($post) {
            $book->title = $post->post_title;
            $book->permalink = get_permalink($book->book_id);

            // Get authors
            if (function_exists('hs_get_book_authors')) {
                $authors = hs_get_book_authors($book->book_id);
                $book->authors = $authors;
            }

            // Get cover image
            $thumbnail_id = get_post_thumbnail_id($book->book_id);
            if ($thumbnail_id) {
                $book->cover_image = wp_get_attachment_image_url($thumbnail_id, 'medium');
            }
        }
    }

    return rest_ensure_response(array(
        'books' => $books,
        'total' => count($books)
    ));
}
