<?php
/**
 * GRead Achievements REST API Endpoints
 * Provides REST endpoints for achievement management and progress tracking
 * Available at: gread.fun/wp-json/gread/v1/achievements/*
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register achievement REST API routes
 */
function gread_register_achievements_routes() {

    // Get all achievements
    register_rest_route('gread/v1', '/achievements', array(
        'methods' => 'GET',
        'callback' => 'gread_get_all_achievements',
        'permission_callback' => '__return_true',
        'args' => array(
            'show_hidden' => array(
                'default' => false,
                'type' => 'boolean'
            )
        )
    ));

    // Get specific achievement details
    register_rest_route('gread/v1', '/achievements/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'gread_get_achievement_by_id',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            )
        )
    ));

    // Get achievement by slug
    register_rest_route('gread/v1', '/achievements/slug/(?P<slug>[a-z0-9\-]+)', array(
        'methods' => 'GET',
        'callback' => 'gread_get_achievement_by_slug',
        'permission_callback' => '__return_true'
    ));

    // Get all achievements for a specific user with progress
    register_rest_route('gread/v1', '/user/(?P<id>\d+)/achievements', array(
        'methods' => 'GET',
        'callback' => 'gread_get_user_achievements_with_progress',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
            'filter' => array(
                'default' => 'all',
                'enum' => array('all', 'unlocked', 'locked')
            )
        )
    ));


    // Get current user's achievements
    register_rest_route('gread/v1', '/me/achievements', array(
        'methods' => 'GET',
        'callback' => 'gread_get_current_user_achievements',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'filter' => array(
                'default' => 'all',
                'enum' => array('all', 'unlocked', 'locked')
            )
        )
    ));

    // Check and unlock achievements for current user
    register_rest_route('gread/v1', '/me/achievements/check', array(
        'methods' => 'POST',
        'callback' => 'gread_check_current_user_achievements',
        'permission_callback' => 'gread_check_user_permission'
    ));

    // Get achievement statistics
    register_rest_route('gread/v1', '/achievements/stats', array(
        'methods' => 'GET',
        'callback' => 'gread_get_achievements_statistics',
        'permission_callback' => '__return_true'
    ));

    // Get achievement leaderboard (users with most achievements)
    register_rest_route('gread/v1', '/achievements/leaderboard', array(
        'methods' => 'GET',
        'callback' => 'gread_get_achievements_leaderboard',
        'permission_callback' => '__return_true',
        'args' => array(
            'limit' => array(
                'default' => 10,
                'type' => 'integer',
                'validate_callback' => function($param) {
                    return intval($param) > 0 && intval($param) <= 100;
                }
            ),
            'offset' => array(
                'default' => 0,
                'type' => 'integer'
            )
        )
    ));

}
add_action('rest_api_init', 'gread_register_achievements_routes');


/**
 * Get all achievements
 */
function gread_get_all_achievements($request) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'hs_achievements';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return new WP_Error('table_not_found', 'Achievements table not found', array('status' => 500));
    }

    $show_hidden = $request->get_param('show_hidden');

    // Don't show hidden achievements to non-authenticated users
    $where = '';
    if (!is_user_logged_in() && !$show_hidden) {
        $where = "WHERE is_hidden = 0";
    }

    $achievements = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY display_order ASC, name ASC");

    if ($wpdb->last_error) {
        return new WP_Error('db_error', $wpdb->last_error, array('status' => 500));
    }

    $result = array();
    foreach ($achievements as $achievement) {
        $result[] = gread_format_achievement($achievement);
    }

    return rest_ensure_response($result);
}


/**
 * Get specific achievement by ID
 */
function gread_get_achievement_by_id($request) {
    global $wpdb;

    $achievement_id = intval($request['id']);
    $table_name = $wpdb->prefix . 'hs_achievements';

    $achievement = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $achievement_id
    ));

    if (!$achievement) {
        return new WP_Error('not_found', 'Achievement not found', array('status' => 404));
    }

    // Hide hidden achievements from non-authenticated users
    if ($achievement->is_hidden && !is_user_logged_in()) {
        return new WP_Error('forbidden', 'This achievement is hidden', array('status' => 403));
    }

    return rest_ensure_response(gread_format_achievement($achievement));
}


/**
 * Get achievement by slug
 */
function gread_get_achievement_by_slug($request) {
    global $wpdb;

    $slug = sanitize_key($request['slug']);
    $table_name = $wpdb->prefix . 'hs_achievements';

    $achievement = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE slug = %s",
        $slug
    ));

    if (!$achievement) {
        return new WP_Error('not_found', 'Achievement not found', array('status' => 404));
    }

    // Hide hidden achievements from non-authenticated users
    if ($achievement->is_hidden && !is_user_logged_in()) {
        return new WP_Error('forbidden', 'This achievement is hidden', array('status' => 403));
    }

    return rest_ensure_response(gread_format_achievement($achievement));
}


/**
 * Get all achievements for a user with progress info
 */
function gread_get_user_achievements_with_progress($request) {
    global $wpdb;

    $user_id = intval($request['id']);
    $filter = $request->get_param('filter') ?: 'all';

    // Verify user exists
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found', array('status' => 404));
    }

    $achievements_table = $wpdb->prefix . 'hs_achievements';
    $user_achievements_table = $wpdb->prefix . 'hs_user_achievements';

    // Build the query
    $query = "SELECT a.*, ua.date_unlocked, ua.id IS NOT NULL as is_unlocked
              FROM {$achievements_table} a
              LEFT JOIN {$user_achievements_table} ua ON a.id = ua.achievement_id AND ua.user_id = %d
              WHERE a.is_hidden = 0
              ORDER BY a.display_order ASC, a.name ASC";

    $achievements = $wpdb->get_results($wpdb->prepare($query, $user_id));

    if ($wpdb->last_error) {
        return new WP_Error('db_error', $wpdb->last_error, array('status' => 500));
    }

    // Get user stats
    $user_stats = gread_get_user_stats_for_achievements($user_id);

    $result = array();
    foreach ($achievements as $achievement) {
        // Filter by unlock status
        if ($filter === 'unlocked' && !$achievement->is_unlocked) {
            continue;
        }
        if ($filter === 'locked' && $achievement->is_unlocked) {
            continue;
        }

        $result[] = gread_format_achievement_with_progress($achievement, $user_stats);
    }

    // Count unlocked achievements
    $unlocked = 0;
    foreach ($result as $achievement) {
        if ($achievement['is_unlocked']) {
            $unlocked++;
        }
    }

    return rest_ensure_response(array(
        'user_id' => $user_id,
        'total' => count($result),
        'unlocked_count' => $unlocked,
        'achievements' => $result
    ));
}



/**
 * Get current user's achievements
 */
function gread_get_current_user_achievements($request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    // Use the same endpoint but for current user
    $new_request = new WP_REST_Request('GET', "/gread/v1/user/$user_id/achievements");
    $new_request->set_param('filter', $request->get_param('filter') ?: 'all');

    return gread_get_user_achievements_with_progress($new_request);
}


/**
 * Check and unlock achievements for current user
 */
function gread_check_current_user_achievements($request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    // Call the existing function from achievements_manager.php
    if (function_exists('hs_check_user_achievements')) {
        hs_check_user_achievements($user_id);
    }

    // Return updated achievements
    $new_request = new WP_REST_Request('GET', "/gread/v1/user/$user_id/achievements");
    $new_request->set_param('filter', 'unlocked');

    return gread_get_user_achievements_with_progress($new_request);
}


/**
 * Get achievement statistics
 */
function gread_get_achievements_statistics($request) {
    global $wpdb;

    $achievements_table = $wpdb->prefix . 'hs_achievements';
    $user_achievements_table = $wpdb->prefix . 'hs_user_achievements';

    // Total achievements
    $total_achievements = intval($wpdb->get_var("SELECT COUNT(*) FROM $achievements_table"));

    // Total unlocks across all users
    $total_unlocks = intval($wpdb->get_var("SELECT COUNT(*) FROM $user_achievements_table"));

    // Average unlocks per achievement
    $avg_unlocks = $total_achievements > 0 ? round($total_unlocks / $total_achievements, 2) : 0;

    // Most unlocked achievement
    $most_unlocked = $wpdb->get_row(
        "SELECT a.id, a.name, a.slug, COUNT(ua.id) as unlock_count
         FROM {$achievements_table} a
         LEFT JOIN {$user_achievements_table} ua ON a.id = ua.achievement_id
         GROUP BY a.id
         ORDER BY unlock_count DESC
         LIMIT 1"
    );

    // Least unlocked achievement
    $least_unlocked = $wpdb->get_row(
        "SELECT a.id, a.name, a.slug, COUNT(ua.id) as unlock_count
         FROM {$achievements_table} a
         LEFT JOIN {$user_achievements_table} ua ON a.id = ua.achievement_id
         GROUP BY a.id
         ORDER BY unlock_count ASC
         LIMIT 1"
    );

    // Users with the most achievements
    $top_achievers = $wpdb->get_results(
        "SELECT user_id, COUNT(achievement_id) as achievement_count
         FROM {$user_achievements_table}
         GROUP BY user_id
         ORDER BY achievement_count DESC
         LIMIT 5"
    );

    // Format top achievers with user info
    $top_achievers_formatted = array();
    foreach ($top_achievers as $achiever) {
        $user = get_userdata($achiever->user_id);
        if ($user) {
            $top_achievers_formatted[] = array(
                'user_id' => $achiever->user_id,
                'user_name' => $user->display_name,
                'achievement_count' => intval($achiever->achievement_count)
            );
        }
    }

    return rest_ensure_response(array(
        'total_achievements' => $total_achievements,
        'total_unlocks' => $total_unlocks,
        'average_unlocks_per_achievement' => $avg_unlocks,
        'most_unlocked' => $most_unlocked ? array(
            'id' => intval($most_unlocked->id),
            'name' => $most_unlocked->name,
            'slug' => $most_unlocked->slug,
            'unlock_count' => intval($most_unlocked->unlock_count)
        ) : null,
        'least_unlocked' => $least_unlocked ? array(
            'id' => intval($least_unlocked->id),
            'name' => $least_unlocked->name,
            'slug' => $least_unlocked->slug,
            'unlock_count' => intval($least_unlocked->unlock_count)
        ) : null,
        'top_achievers' => $top_achievers_formatted
    ));
}


/**
 * Get achievements leaderboard
 */
function gread_get_achievements_leaderboard($request) {
    global $wpdb;

    $user_achievements_table = $wpdb->prefix . 'hs_user_achievements';
    $limit = intval($request->get_param('limit')) ?: 10;
    $offset = intval($request->get_param('offset')) ?: 0;

    $leaderboard = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, COUNT(achievement_id) as achievement_count
         FROM {$user_achievements_table}
         GROUP BY user_id
         ORDER BY achievement_count DESC
         LIMIT %d OFFSET %d",
        $limit,
        $offset
    ));

    $result = array();
    $rank = $offset + 1;
    foreach ($leaderboard as $entry) {
        $user = get_userdata($entry->user_id);
        if ($user) {
            $result[] = array(
                'rank' => $rank,
                'user_id' => $entry->user_id,
                'user_name' => $user->display_name,
                'user_avatar_url' => get_avatar_url($entry->user_id),
                'achievement_count' => intval($entry->achievement_count)
            );
            $rank++;
        }
    }

    return rest_ensure_response($result);
}


/**
 * Helper function to get user statistics for achievements
 */
function gread_get_user_stats_for_achievements($user_id) {
    $metric_map = array(
        'points' => 'user_points',
        'books_read' => 'hs_completed_books_count',
        'pages_read' => 'hs_total_pages_read',
        'books_added' => 'hs_books_added_count',
        'approved_reports' => 'hs_approved_reports_count',
    );

    $user_stats = array();
    foreach ($metric_map as $metric => $meta_key) {
        $user_stats[$metric] = intval(get_user_meta($user_id, $meta_key, true));
    }

    return $user_stats;
}


/**
 * Format achievement data for API response
 */
function gread_format_achievement($achievement) {
    return array(
        'id' => intval($achievement->id),
        'slug' => $achievement->slug,
        'name' => $achievement->name,
        'description' => $achievement->description,
        'icon' => array(
            'type' => $achievement->icon_type,
            'color' => $achievement->icon_color,
            'symbol' => function_exists('hs_get_icon_symbol') ? hs_get_icon_symbol($achievement->icon_type) : '⭐'
        ),
        'unlock_requirements' => array(
            'metric' => $achievement->unlock_metric,
            'value' => intval($achievement->unlock_value),
            'condition' => $achievement->unlock_condition
        ),
        'reward' => intval($achievement->points_reward),
        'is_hidden' => boolval($achievement->is_hidden),
        'display_order' => intval($achievement->display_order)
    );
}


/**
 * Format achievement with user progress
 */
function gread_format_achievement_with_progress($achievement, $user_stats) {
    $current_value = isset($user_stats[$achievement->unlock_metric]) ? $user_stats[$achievement->unlock_metric] : 0;
    $progress_percentage = $achievement->unlock_value > 0 ? min(100, ($current_value / $achievement->unlock_value) * 100) : 0;

    return array(
        'id' => intval($achievement->id),
        'slug' => $achievement->slug,
        'name' => $achievement->name,
        'description' => $achievement->description,
        'icon' => array(
            'type' => $achievement->icon_type,
            'color' => $achievement->icon_color,
            'symbol' => function_exists('hs_get_icon_symbol') ? hs_get_icon_symbol($achievement->icon_type) : '⭐'
        ),
        'unlock_requirements' => array(
            'metric' => $achievement->unlock_metric,
            'value' => intval($achievement->unlock_value),
            'condition' => $achievement->unlock_condition
        ),
        'progress' => array(
            'current' => intval($current_value),
            'required' => intval($achievement->unlock_value),
            'percentage' => round($progress_percentage, 2)
        ),
        'is_unlocked' => boolval($achievement->is_unlocked),
        'date_unlocked' => $achievement->is_unlocked ? $achievement->date_unlocked : null,
        'reward' => intval($achievement->points_reward),
        'is_hidden' => boolval($achievement->is_hidden),
        'display_order' => intval($achievement->display_order)
    );
}
