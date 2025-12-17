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
 *
 * LEGACY ENDPOINTS (v1) - REMOVE AFTER iOS APP MIGRATION TO V2
 * These endpoints maintain backward compatibility with the current iOS app
 */
function gread_register_achievements_routes() {

    // ============================================================================
    // LEGACY V1 ENDPOINTS - REMOVE AFTER iOS APP UPDATES TO V2
    // ============================================================================

    // Get all achievements
    register_rest_route('gread/v1', '/achievements', array(
        'methods' => 'GET',
        'callback' => 'gread_get_all_achievements',
        'permission_callback' => '__return_true',
        'args' => array(
            'show_hidden' => array(
                'default' => false,
                'type' => 'boolean'
            ),
            'category' => array(
                'default' => '',
                'type' => 'string'
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
            ),
            'category' => array(
                'default' => '',
                'type' => 'string'
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
            ),
            'category' => array(
                'default' => '',
                'type' => 'string'
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

    // ============================================================================
    // V2 ENDPOINTS - NEW ACHIEVEMENT SYSTEM WITH CATEGORIES & HIDDEN ACHIEVEMENTS
    // ============================================================================
    // These endpoints support:
    // - Category filtering and organization
    // - Hidden achievements (shows ? icon and ??? text until unlocked)
    // - Proper masking of locked hidden achievement details
    // - All achievements visible in user lists (with masking for hidden ones)

    // Get all achievements (v2)
    // Supports category filtering, shows all achievements including hidden ones
    register_rest_route('gread/v2', '/achievements', array(
        'methods' => 'GET',
        'callback' => 'gread_v2_get_all_achievements',
        'permission_callback' => '__return_true',
        'args' => array(
            'category' => array(
                'default' => '',
                'type' => 'string',
                'description' => 'Filter by category: authors, books_and_series, categories, contributions, career'
            )
        )
    ));

    // Get specific achievement details (v2)
    register_rest_route('gread/v2', '/achievements/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'gread_v2_get_achievement_by_id',
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

    // Get achievement by slug (v2)
    register_rest_route('gread/v2', '/achievements/slug/(?P<slug>[a-z0-9\-]+)', array(
        'methods' => 'GET',
        'callback' => 'gread_v2_get_achievement_by_slug',
        'permission_callback' => '__return_true'
    ));

    // Get all achievements for a specific user with progress (v2)
    // Shows ALL achievements including hidden ones (masked if locked)
    register_rest_route('gread/v2', '/user/(?P<id>\d+)/achievements', array(
        'methods' => 'GET',
        'callback' => 'gread_v2_get_user_achievements_with_progress',
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
                'enum' => array('all', 'unlocked', 'locked'),
                'description' => 'Filter achievements by unlock status'
            ),
            'category' => array(
                'default' => '',
                'type' => 'string',
                'description' => 'Filter by category'
            )
        )
    ));

    // Get current user's achievements (v2)
    register_rest_route('gread/v2', '/me/achievements', array(
        'methods' => 'GET',
        'callback' => 'gread_v2_get_current_user_achievements',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'filter' => array(
                'default' => 'all',
                'enum' => array('all', 'unlocked', 'locked')
            ),
            'category' => array(
                'default' => '',
                'type' => 'string'
            )
        )
    ));

    // Check and unlock achievements for current user (v2)
    register_rest_route('gread/v2', '/me/achievements/check', array(
        'methods' => 'POST',
        'callback' => 'gread_v2_check_current_user_achievements',
        'permission_callback' => 'gread_check_user_permission'
    ));

    // Get achievement statistics (v2)
    register_rest_route('gread/v2', '/achievements/stats', array(
        'methods' => 'GET',
        'callback' => 'gread_get_achievements_statistics', // Reuse v1 function (no changes needed)
        'permission_callback' => '__return_true'
    ));

    // Get achievement leaderboard (v2)
    register_rest_route('gread/v2', '/achievements/leaderboard', array(
        'methods' => 'GET',
        'callback' => 'gread_get_achievements_leaderboard', // Reuse v1 function (no changes needed)
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

    // Get user achievements grouped by category (v2)
    register_rest_route('gread/v2', '/user/(?P<id>\d+)/achievements/by-category', array(
        'methods' => 'GET',
        'callback' => 'gread_v2_get_user_achievements_by_category',
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

    // Get current user achievements grouped by category (v2)
    register_rest_route('gread/v2', '/me/achievements/by-category', array(
        'methods' => 'GET',
        'callback' => 'gread_v2_get_current_user_achievements_by_category',
        'permission_callback' => 'gread_check_user_permission'
    ));

}
add_action('rest_api_init', 'gread_register_achievements_routes');


// ============================================================================
// LEGACY V1 FUNCTIONS - REMOVE AFTER iOS APP UPDATES TO V2
// ============================================================================

/**
 * LEGACY V1: Get all achievements
 * REMOVE AFTER iOS APP MIGRATION
 */
function gread_get_all_achievements($request) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'hs_achievements';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return new WP_Error('table_not_found', 'Achievements table not found', array('status' => 500));
    }

    $show_hidden = $request->get_param('show_hidden');
    $category = $request->get_param('category');

    // Build WHERE clause
    $where_clauses = array();

    // Don't show hidden achievements to non-authenticated users
    if (!is_user_logged_in() && !$show_hidden) {
        $where_clauses[] = "is_hidden = 0";
    }

    // Filter by category if specified
    if (!empty($category)) {
        $where_clauses[] = $wpdb->prepare("category = %s", sanitize_text_field($category));
    }

    $where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

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
 * LEGACY V1: Get specific achievement by ID
 * REMOVE AFTER iOS APP MIGRATION
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
 * LEGACY V1: Get achievement by slug
 * REMOVE AFTER iOS APP MIGRATION
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
 * LEGACY V1: Get all achievements for a user with progress info
 * REMOVE AFTER iOS APP MIGRATION
 * NOTE: This filters OUT hidden achievements completely
 */
function gread_get_user_achievements_with_progress($request) {
    global $wpdb;

    $user_id = intval($request['id']);
    $filter = $request->get_param('filter') ?: 'all';
    $category = $request->get_param('category') ?: '';

    // Verify user exists
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found', array('status' => 404));
    }

    $achievements_table = $wpdb->prefix . 'hs_achievements';
    $user_achievements_table = $wpdb->prefix . 'hs_user_achievements';

    // Build WHERE clause
    $where_clauses = array('a.is_hidden = 0');

    if (!empty($category)) {
        $where_clauses[] = $wpdb->prepare("a.category = %s", sanitize_text_field($category));
    }

    $where = implode(' AND ', $where_clauses);

    // Build the query
    $query = "SELECT a.*, ua.date_unlocked, ua.id IS NOT NULL as is_unlocked
              FROM {$achievements_table} a
              LEFT JOIN {$user_achievements_table} ua ON a.id = ua.achievement_id AND ua.user_id = %d
              WHERE {$where}
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

        $result[] = gread_format_achievement_with_progress($achievement, $user_stats, $user_id);
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
 * LEGACY V1: Get current user's achievements
 * REMOVE AFTER iOS APP MIGRATION
 */
function gread_get_current_user_achievements($request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    // Use the same endpoint but for current user
    $new_request = new WP_REST_Request('GET', "/gread/v1/user/$user_id/achievements");
    $new_request->set_param('filter', $request->get_param('filter') ?: 'all');
    $new_request->set_param('category', $request->get_param('category') ?: '');

    return gread_get_user_achievements_with_progress($new_request);
}


/**
 * LEGACY V1: Check and unlock achievements for current user
 * REMOVE AFTER iOS APP MIGRATION
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
        'notes_created' => 'hs_notes_created_count',
        'citations_created' => 'hs_citations_created_count',
    );

    $user_stats = array();
    foreach ($metric_map as $metric => $meta_key) {
        $user_stats[$metric] = intval(get_user_meta($user_id, $meta_key, true));
    }

    return $user_stats;
}


/**
 * Calculate achievement rarity statistics
 * This should be run daily via cron
 */
function gread_calculate_achievement_rarity_stats() {
    global $wpdb;

    $achievements_table = $wpdb->prefix . 'hs_achievements';
    $user_achievements_table = $wpdb->prefix . 'hs_user_achievements';

    // Get total active users (users who have at least one achievement OR have read at least one book)
    $total_users = intval($wpdb->get_var(
        "SELECT COUNT(DISTINCT user_id)
         FROM {$user_achievements_table}"
    ));

    // Fallback to all users if no achievements have been unlocked yet
    if ($total_users === 0) {
        $total_users = intval($wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->users}"
        ));
    }

    // Calculate unlock count and percentage for each achievement
    $achievement_stats = $wpdb->get_results(
        "SELECT a.id,
                COUNT(DISTINCT ua.user_id) as unlock_count
         FROM {$achievements_table} a
         LEFT JOIN {$user_achievements_table} ua ON a.id = ua.achievement_id
         GROUP BY a.id"
    );

    $rarity_data = [];
    foreach ($achievement_stats as $stat) {
        $unlock_percentage = $total_users > 0 ? ($stat->unlock_count / $total_users) * 100 : 0;

        // Determine rarity tier
        $rarity_tier = gread_get_rarity_tier($unlock_percentage);

        $rarity_data[$stat->id] = [
            'unlock_count' => intval($stat->unlock_count),
            'unlock_percentage' => round($unlock_percentage, 2),
            'rarity_tier' => $rarity_tier
        ];
    }

    // Store in a transient that expires in 25 hours (daily + buffer)
    set_transient('gread_achievement_rarity_stats', [
        'total_users' => $total_users,
        'achievements' => $rarity_data,
        'last_updated' => current_time('mysql')
    ], 25 * HOUR_IN_SECONDS);

    return $rarity_data;
}


/**
 * Get rarity tier based on unlock percentage
 */
function gread_get_rarity_tier($percentage) {
    if ($percentage >= 75) {
        return 'common';
    } elseif ($percentage >= 40) {
        return 'uncommon';
    } elseif ($percentage >= 15) {
        return 'rare';
    } elseif ($percentage >= 5) {
        return 'epic';
    } else {
        return 'legendary';
    }
}


/**
 * Get achievement rarity data (from cache or calculate)
 */
function gread_get_achievement_rarity_stats() {
    $stats = get_transient('gread_achievement_rarity_stats');

    if ($stats === false) {
        // Transient expired or doesn't exist, recalculate
        $stats = gread_calculate_achievement_rarity_stats();
    }

    return $stats;
}


/**
 * Schedule daily rarity calculation
 */
function gread_schedule_rarity_calculation() {
    if (!wp_next_scheduled('gread_daily_rarity_calculation')) {
        wp_schedule_event(time(), 'daily', 'gread_daily_rarity_calculation');
    }
}
add_action('init', 'gread_schedule_rarity_calculation');

// Hook the calculation to the scheduled event
add_action('gread_daily_rarity_calculation', 'gread_calculate_achievement_rarity_stats');


/**
 * Format achievement data for API response
 */
function gread_format_achievement($achievement) {
    global $wpdb;

    // Get SVG URL if available
    $svg_url = null;
    if (!empty($achievement->icon_svg_path)) {
        $upload_dir = wp_upload_dir();
        $svg_url = $upload_dir['baseurl'] . $achievement->icon_svg_path;
    }

    // Get steps if this is a multi-step achievement
    $steps = [];
    $steps_table = $wpdb->prefix . 'hs_achievement_steps';
    $achievement_steps = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $steps_table WHERE achievement_id = %d ORDER BY step_order ASC",
        $achievement->id
    ));

    if (!empty($achievement_steps)) {
        foreach ($achievement_steps as $step) {
            // Handle target_gid as either numeric (GID) or string (tag slug)
            $target_gid_value = null;
            if ($step->target_gid !== null) {
                $target_gid_value = is_numeric($step->target_gid) ? intval($step->target_gid) : $step->target_gid;
            }

            $steps[] = array(
                'id' => intval($step->id),
                'order' => intval($step->step_order),
                'name' => $step->step_name,
                'description' => $step->step_description,
                'metric' => $step->metric,
                'target_value' => intval($step->target_value),
                'target_gid' => $target_gid_value,
                'requires_previous_step' => boolval($step->requires_previous_step)
            );
        }
    }

    // Get rarity data
    $rarity_stats = gread_get_achievement_rarity_stats();
    $rarity = null;
    if (isset($rarity_stats['achievements'][$achievement->id])) {
        $rarity = $rarity_stats['achievements'][$achievement->id];
    }

    return array(
        'id' => intval($achievement->id),
        'slug' => $achievement->slug,
        'name' => $achievement->name,
        'description' => $achievement->description,
        'icon' => array(
            'type' => $achievement->icon_type,
            'color' => $achievement->icon_color,
            'symbol' => function_exists('hs_get_icon_symbol') ? hs_get_icon_symbol($achievement->icon_type) : '⭐',
            'svg_url' => $svg_url
        ),
        'unlock_requirements' => array(
            'metric' => $achievement->unlock_metric,
            'value' => intval($achievement->unlock_value),
            'condition' => $achievement->unlock_condition
        ),
        'steps' => $steps,
        'is_multistep' => !empty($steps),
        'reward' => intval($achievement->points_reward),
        'is_hidden' => boolval($achievement->is_hidden),
        'category' => $achievement->category ?: null,
        'display_order' => intval($achievement->display_order),
        'rarity' => $rarity
    );
}


/**
 * Format achievement with user progress
 */
function gread_format_achievement_with_progress($achievement, $user_stats, $user_id = null) {
    global $wpdb;

    $current_value = isset($user_stats[$achievement->unlock_metric]) ? $user_stats[$achievement->unlock_metric] : 0;
    $progress_percentage = $achievement->unlock_value > 0 ? min(100, ($current_value / $achievement->unlock_value) * 100) : 0;

    // Check if achievement is unlocked
    $is_unlocked = boolval($achievement->is_unlocked);
    $is_hidden = boolval($achievement->is_hidden);

    // Determine if we should mask the achievement (hidden and not unlocked)
    $should_mask = $is_hidden && !$is_unlocked;

    // Get SVG URL if available
    $svg_url = null;
    if (!empty($achievement->icon_svg_path) && !$should_mask) {
        $upload_dir = wp_upload_dir();
        $svg_url = $upload_dir['baseurl'] . $achievement->icon_svg_path;
    }

    // Get steps and step progress if this is a multi-step achievement
    $steps = [];
    $steps_table = $wpdb->prefix . 'hs_achievement_steps';
    $progress_table = $wpdb->prefix . 'hs_user_step_progress';

    $achievement_steps = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, p.current_value, p.completed, p.date_completed
         FROM $steps_table s
         LEFT JOIN $progress_table p ON s.id = p.step_id AND p.user_id = %d
         WHERE s.achievement_id = %d
         ORDER BY s.step_order ASC",
        get_current_user_id(),
        $achievement->id
    ));

    if (!empty($achievement_steps)) {
        foreach ($achievement_steps as $step) {
            $step_current = intval($step->current_value ?? 0);
            $step_target = intval($step->target_value);
            $step_progress = $step_target > 0 ? min(100, ($step_current / $step_target) * 100) : 0;

            // Handle target_gid as either numeric (GID) or string (tag slug)
            $target_gid_value = null;
            if ($step->target_gid !== null) {
                $target_gid_value = is_numeric($step->target_gid) ? intval($step->target_gid) : $step->target_gid;
            }

            $steps[] = array(
                'id' => intval($step->id),
                'order' => intval($step->step_order),
                'name' => $step->step_name,
                'description' => $step->step_description,
                'metric' => $step->metric,
                'target_value' => $step_target,
                'target_gid' => $target_gid_value,
                'requires_previous_step' => boolval($step->requires_previous_step),
                'progress' => array(
                    'current' => $step_current,
                    'required' => $step_target,
                    'percentage' => round($step_progress, 2)
                ),
                'is_completed' => boolval($step->completed ?? false),
                'date_completed' => $step->date_completed ?? null
            );
        }
    }

    // Get rarity data
    $rarity_stats = gread_get_achievement_rarity_stats();
    $rarity = null;
    if (isset($rarity_stats['achievements'][$achievement->id])) {
        $rarity = $rarity_stats['achievements'][$achievement->id];
    }

    return array(
        'id' => intval($achievement->id),
        'slug' => $achievement->slug,
        'name' => $should_mask ? '???' : $achievement->name,
        'description' => $should_mask ? '???' : $achievement->description,
        'icon' => array(
            'type' => $should_mask ? 'question' : $achievement->icon_type,
            'color' => $should_mask ? '#999999' : $achievement->icon_color,
            'symbol' => $should_mask ? '?' : (function_exists('hs_get_icon_symbol') ? hs_get_icon_symbol($achievement->icon_type) : '⭐'),
            'svg_url' => $svg_url
        ),
        'unlock_requirements' => $should_mask ? null : array(
            'metric' => $achievement->unlock_metric,
            'value' => intval($achievement->unlock_value),
            'condition' => $achievement->unlock_condition
        ),
        'progress' => $should_mask ? null : array(
            'current' => intval($current_value),
            'required' => intval($achievement->unlock_value),
            'percentage' => round($progress_percentage, 2)
        ),
        'steps' => $should_mask ? [] : $steps,
        'is_multistep' => !empty($steps),
        'is_unlocked' => $is_unlocked,
        'date_unlocked' => $is_unlocked ? $achievement->date_unlocked : null,
        'reward' => $should_mask ? null : intval($achievement->points_reward),
        'is_hidden' => $is_hidden,
        'category' => $achievement->category ?: null,
        'display_order' => intval($achievement->display_order),
        'rarity' => $rarity
    );
}


// ============================================================================
// V2 API ENDPOINT FUNCTIONS - NEW ACHIEVEMENT SYSTEM
// ============================================================================

/**
 * V2: Get all achievements
 * Shows all achievements including hidden ones (no filtering)
 * Supports category filtering
 */
function gread_v2_get_all_achievements($request) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'hs_achievements';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return new WP_Error('table_not_found', 'Achievements table not found', array('status' => 500));
    }

    $category = $request->get_param('category');

    // Build WHERE clause (no hidden filtering - show all achievements)
    $where_clauses = array();

    // Filter by category if specified
    if (!empty($category)) {
        $where_clauses[] = $wpdb->prepare("category = %s", sanitize_text_field($category));
    }

    $where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

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
 * V2: Get specific achievement by ID
 */
function gread_v2_get_achievement_by_id($request) {
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

    return rest_ensure_response(gread_format_achievement($achievement));
}


/**
 * V2: Get achievement by slug
 */
function gread_v2_get_achievement_by_slug($request) {
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

    return rest_ensure_response(gread_format_achievement($achievement));
}


/**
 * V2: Get all achievements for a user with progress info
 * SHOWS ALL ACHIEVEMENTS INCLUDING HIDDEN ONES (masked if locked)
 */
function gread_v2_get_user_achievements_with_progress($request) {
    global $wpdb;

    $user_id = intval($request['id']);
    $filter = $request->get_param('filter') ?: 'all';
    $category = $request->get_param('category') ?: '';

    // Verify user exists
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found', array('status' => 404));
    }

    $achievements_table = $wpdb->prefix . 'hs_achievements';
    $user_achievements_table = $wpdb->prefix . 'hs_user_achievements';

    // Build WHERE clause - DO NOT filter out hidden achievements
    $where_clauses = array();

    if (!empty($category)) {
        $where_clauses[] = $wpdb->prepare("a.category = %s", sanitize_text_field($category));
    }

    $where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Build the query - includes ALL achievements (hidden ones will be masked in formatting)
    $query = "SELECT a.*, ua.date_unlocked, ua.id IS NOT NULL as is_unlocked
              FROM {$achievements_table} a
              LEFT JOIN {$user_achievements_table} ua ON a.id = ua.achievement_id AND ua.user_id = %d
              " . ($where ? $where : '') . "
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

        // Format achievement with masking for hidden ones
        $result[] = gread_format_achievement_with_progress($achievement, $user_stats, $user_id);
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
 * V2: Get current user's achievements
 */
function gread_v2_get_current_user_achievements($request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    // Use the v2 endpoint for current user
    $new_request = new WP_REST_Request('GET', "/gread/v2/user/$user_id/achievements");
    $new_request->set_param('filter', $request->get_param('filter') ?: 'all');
    $new_request->set_param('category', $request->get_param('category') ?: '');

    return gread_v2_get_user_achievements_with_progress($new_request);
}


/**
 * V2: Check and unlock achievements for current user
 */
function gread_v2_check_current_user_achievements($request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    // Call the existing function from achievements_manager.php
    if (function_exists('hs_check_user_achievements')) {
        hs_check_user_achievements($user_id);
    }

    // Return updated achievements using v2 endpoint
    $new_request = new WP_REST_Request('GET', "/gread/v2/user/$user_id/achievements");
    $new_request->set_param('filter', 'unlocked');

    return gread_v2_get_user_achievements_with_progress($new_request);
}


/**
 * V2: Get user achievements grouped by category
 * Returns achievements organized by category with counts
 */
function gread_v2_get_user_achievements_by_category($request) {
    global $wpdb;

    $user_id = intval($request['id']);

    // Verify user exists
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found', array('status' => 404));
    }

    $achievements_table = $wpdb->prefix . 'hs_achievements';
    $user_achievements_table = $wpdb->prefix . 'hs_user_achievements';

    // Get all achievements with unlock status
    $query = "SELECT a.*, ua.date_unlocked, ua.id IS NOT NULL as is_unlocked
              FROM {$achievements_table} a
              LEFT JOIN {$user_achievements_table} ua ON a.id = ua.achievement_id AND ua.user_id = %d
              ORDER BY a.category ASC, a.display_order ASC, a.name ASC";

    $achievements = $wpdb->get_results($wpdb->prepare($query, $user_id));

    if ($wpdb->last_error) {
        return new WP_Error('db_error', $wpdb->last_error, array('status' => 500));
    }

    // Get user stats
    $user_stats = gread_get_user_stats_for_achievements($user_id);

    // Group achievements by category
    $categories = [];
    $category_counts = [];

    foreach ($achievements as $achievement) {
        $category = $achievement->category ?: 'Uncategorized';

        if (!isset($categories[$category])) {
            $categories[$category] = [];
            $category_counts[$category] = ['total' => 0, 'unlocked' => 0, 'locked' => 0];
        }

        $formatted_achievement = gread_format_achievement_with_progress($achievement, $user_stats, $user_id);
        $categories[$category][] = $formatted_achievement;

        // Update counts
        $category_counts[$category]['total']++;
        if ($formatted_achievement['is_unlocked']) {
            $category_counts[$category]['unlocked']++;
        } else {
            $category_counts[$category]['locked']++;
        }
    }

    // Format the response
    $result = [];
    foreach ($categories as $category_name => $category_achievements) {
        $result[] = [
            'category' => $category_name,
            'total_count' => $category_counts[$category_name]['total'],
            'unlocked_count' => $category_counts[$category_name]['unlocked'],
            'locked_count' => $category_counts[$category_name]['locked'],
            'achievements' => $category_achievements
        ];
    }

    // Calculate overall totals
    $total_achievements = count($achievements);
    $total_unlocked = 0;
    foreach ($category_counts as $counts) {
        $total_unlocked += $counts['unlocked'];
    }

    return rest_ensure_response([
        'user_id' => $user_id,
        'total_achievements' => $total_achievements,
        'total_unlocked' => $total_unlocked,
        'total_locked' => $total_achievements - $total_unlocked,
        'categories' => $result
    ]);
}


/**
 * V2: Get current user achievements grouped by category
 */
function gread_v2_get_current_user_achievements_by_category($request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    // Use the by-category endpoint for current user
    $new_request = new WP_REST_Request('GET', "/gread/v2/user/$user_id/achievements/by-category");

    return gread_v2_get_user_achievements_by_category($new_request);
}
