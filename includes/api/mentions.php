<?php
/**
 * GRead BuddyPress User Mentions REST API Endpoints
 * Provides REST endpoints for user mentions and tagging through BuddyPress integration
 * Available at: gread.fun/wp-json/gread/v1/mentions/*
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register BuddyPress mentions REST API routes
 */
function gread_register_mentions_routes() {

    // Search users for mentions
    register_rest_route('gread/v1', '/mentions/search', array(
        'methods' => 'GET',
        'callback' => 'gread_mentions_search_users',
        'permission_callback' => '__return_true',
        'args' => array(
            'query' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'limit' => array(
                'default' => 10,
                'type' => 'integer'
            )
        )
    ));

    // Get user details for mention
    register_rest_route('gread/v1', '/mentions/user/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'gread_get_mention_user',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'required' => true,
                'type' => 'integer'
            )
        )
    ));

    // Get all mentionable users (for autocomplete)
    register_rest_route('gread/v1', '/mentions/users', array(
        'methods' => 'GET',
        'callback' => 'gread_get_mentionable_users',
        'permission_callback' => '__return_true',
        'args' => array(
            'limit' => array(
                'default' => 50,
                'type' => 'integer'
            ),
            'offset' => array(
                'default' => 0,
                'type' => 'integer'
            )
        )
    ));

    // Get activity mentions for current user
    register_rest_route('gread/v1', '/me/mentions', array(
        'methods' => 'GET',
        'callback' => 'gread_get_user_mentions',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'limit' => array(
                'default' => 20,
                'type' => 'integer'
            ),
            'offset' => array(
                'default' => 0,
                'type' => 'integer'
            ),
            'unread_only' => array(
                'default' => false,
                'type' => 'boolean'
            )
        )
    ));

    // Get user mentions for specific user
    register_rest_route('gread/v1', '/user/(?P<id>\d+)/mentions', array(
        'methods' => 'GET',
        'callback' => 'gread_get_user_mentions_by_id',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
            'limit' => array(
                'default' => 20,
                'type' => 'integer'
            ),
            'offset' => array(
                'default' => 0,
                'type' => 'integer'
            )
        )
    ));

    // Search activity by mention
    register_rest_route('gread/v1', '/mentions/activity', array(
        'methods' => 'GET',
        'callback' => 'gread_get_mentions_activity',
        'permission_callback' => '__return_true',
        'args' => array(
            'user_id' => array(
                'required' => false,
                'type' => 'integer'
            ),
            'limit' => array(
                'default' => 20,
                'type' => 'integer'
            ),
            'offset' => array(
                'default' => 0,
                'type' => 'integer'
            )
        )
    ));

    // Mark mentions as read
    register_rest_route('gread/v1', '/me/mentions/read', array(
        'methods' => 'POST',
        'callback' => 'gread_mark_mentions_as_read',
        'permission_callback' => 'gread_check_user_permission'
    ));

}
add_action('rest_api_init', 'gread_register_mentions_routes');


/**
 * Search for users for mentions/tagging
 */
function gread_mentions_search_users($request) {

    // Check if BuddyPress is active
    if (!function_exists('bp_is_active')) {
        return new WP_Error('buddypress_not_active', 'BuddyPress is not active', array('status' => 400));
    }

    $query = $request->get_param('query');
    $limit = intval($request->get_param('limit')) ?: 10;

    if (strlen($query) < 2) {
        return new WP_Error('query_too_short', 'Query must be at least 2 characters', array('status' => 400));
    }

    // Use WordPress user search
    $args = array(
        'search' => '*' . $query . '*',
        'search_columns' => array('user_login', 'user_email', 'display_name'),
        'number' => $limit,
        'exclude' => array(get_current_user_id())
    );

    $users = get_users($args);

    $result = array();
    foreach ($users as $user) {
        $result[] = gread_format_mention_user($user);
    }

    return rest_ensure_response(array(
        'query' => $query,
        'total' => count($result),
        'users' => $result
    ));
}


/**
 * Get details for a specific user for mention
 */
function gread_get_mention_user($request) {
    $user_id = intval($request['id']);

    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found', array('status' => 404));
    }

    return rest_ensure_response(gread_format_mention_user($user));
}


/**
 * Get all mentionable users
 */
function gread_get_mentionable_users($request) {

    $limit = intval($request->get_param('limit')) ?: 50;
    $offset = intval($request->get_param('offset')) ?: 0;

    $args = array(
        'number' => $limit,
        'offset' => $offset,
        'orderby' => 'user_nicename'
    );

    $users = get_users($args);

    $result = array();
    foreach ($users as $user) {
        $result[] = gread_format_mention_user($user);
    }

    return rest_ensure_response(array(
        'total' => count_users()['total_users'],
        'limit' => $limit,
        'offset' => $offset,
        'users' => $result
    ));
}


/**
 * Get mentions for current user
 */
function gread_get_user_mentions($request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    // Check if BuddyPress is active
    if (!function_exists('bp_activity_get')) {
        return new WP_Error('buddypress_not_active', 'BuddyPress is not active', array('status' => 400));
    }

    $limit = intval($request->get_param('limit')) ?: 20;
    $offset = intval($request->get_param('offset')) ?: 0;
    $unread_only = $request->get_param('unread_only') ? 1 : 0;

    // Get activity mentions using BuddyPress
    $args = array(
        'user_id' => $user_id,
        'search_terms' => '@' . $user_id,
        'per_page' => $limit,
        'page' => ($offset / $limit) + 1,
        'sort' => 'DESC'
    );

    $mentions = bp_activity_get($args);

    $result = array();
    if ($mentions['activities']) {
        foreach ($mentions['activities'] as $activity) {
            $result[] = gread_format_mention_activity($activity);
        }
    }

    return rest_ensure_response(array(
        'user_id' => $user_id,
        'total' => intval($mentions['total']),
        'limit' => $limit,
        'offset' => $offset,
        'mentions' => $result
    ));
}


/**
 * Get mentions for a specific user
 */
function gread_get_user_mentions_by_id($request) {
    $user_id = intval($request['id']);

    // Verify user exists
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found', array('status' => 404));
    }

    // Check if BuddyPress is active
    if (!function_exists('bp_activity_get')) {
        return new WP_Error('buddypress_not_active', 'BuddyPress is not active', array('status' => 400));
    }

    $limit = intval($request->get_param('limit')) ?: 20;
    $offset = intval($request->get_param('offset')) ?: 0;

    // Get activity mentions using BuddyPress
    $args = array(
        'user_id' => $user_id,
        'search_terms' => '@' . $user_id,
        'per_page' => $limit,
        'page' => ($offset / $limit) + 1,
        'sort' => 'DESC'
    );

    $mentions = bp_activity_get($args);

    $result = array();
    if ($mentions['activities']) {
        foreach ($mentions['activities'] as $activity) {
            $result[] = gread_format_mention_activity($activity);
        }
    }

    return rest_ensure_response(array(
        'user_id' => $user_id,
        'user_name' => $user->display_name,
        'total' => intval($mentions['total']),
        'limit' => $limit,
        'offset' => $offset,
        'mentions' => $result
    ));
}


/**
 * Get activity containing mentions
 */
function gread_get_mentions_activity($request) {

    // Check if BuddyPress is active
    if (!function_exists('bp_activity_get')) {
        return new WP_Error('buddypress_not_active', 'BuddyPress is not active', array('status' => 400));
    }

    $limit = intval($request->get_param('limit')) ?: 20;
    $offset = intval($request->get_param('offset')) ?: 0;
    $user_id = $request->get_param('user_id');

    // Build args
    $args = array(
        'per_page' => $limit,
        'page' => ($offset / $limit) + 1,
        'sort' => 'DESC'
    );

    // If user_id provided, get mentions for that user
    if ($user_id) {
        $args['user_id'] = intval($user_id);
        $args['search_terms'] = '@' . intval($user_id);
    } else {
        // Get all activity with @ mentions
        $args['search_terms'] = '@';
    }

    $activities = bp_activity_get($args);

    $result = array();
    if ($activities['activities']) {
        foreach ($activities['activities'] as $activity) {
            $result[] = gread_format_mention_activity($activity);
        }
    }

    return rest_ensure_response(array(
        'total' => intval($activities['total']),
        'limit' => $limit,
        'offset' => $offset,
        'activities' => $result
    ));
}


/**
 * Mark mentions as read
 */
function gread_mark_mentions_as_read($request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    // Check if BuddyPress Notifications is active
    if (!function_exists('bp_notifications_mark_notifications_by_item_id')) {
        return new WP_Error('buddypress_not_active', 'BuddyPress Notifications is not active', array('status' => 400));
    }

    // Mark all mention notifications as read for current user
    bp_notifications_mark_all_notifications_as_read(
        $user_id,
        'activity',
        'new_at_mention'
    );

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Mentions marked as read',
        'user_id' => $user_id
    ));
}


/**
 * Format user data for mention response
 */
function gread_format_mention_user($user) {
    $user_id = $user->ID;

    return array(
        'id' => intval($user_id),
        'username' => $user->user_login,
        'display_name' => $user->display_name,
        'email' => $user->user_email,
        'avatar_url' => get_avatar_url($user_id),
        'profile_url' => function_exists('bp_core_get_user_domain') ? bp_core_get_user_domain($user_id) : get_author_posts_url($user_id),
        'mention_text' => '@' . $user->user_login
    );
}


/**
 * Format activity for mention response
 */
function gread_format_mention_activity($activity) {
    $user = get_userdata($activity->user_id);

    return array(
        'activity_id' => intval($activity->id),
        'user_id' => intval($activity->user_id),
        'user_name' => $user ? $user->display_name : 'Unknown User',
        'user_avatar' => $user ? get_avatar_url($activity->user_id) : '',
        'content' => wp_strip_all_tags($activity->content),
        'content_raw' => $activity->content,
        'type' => $activity->type,
        'date' => $activity->date_recorded,
        'time_ago' => human_time_diff(strtotime($activity->date_recorded), current_time('timestamp')) . ' ago',
        'activity_url' => function_exists('bp_activity_get_permalink') ? bp_activity_get_permalink($activity->id) : '',
        'reply_count' => intval($activity->item_id ? bp_activity_get_comment_count(array('activity_id' => $activity->id)) : 0)
    );
}
