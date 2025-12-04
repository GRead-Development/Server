<?php
/**
 * Multi-Step Achievements with GID Tracking
 *
 * This file extends the achievement system to support:
 * - Multi-step achievements (e.g., read book X, then earn Y points, then review a book)
 * - GID-based book tracking (track specific books by their Global ID)
 * - Sequential step completion
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create tables for multi-step achievements
 */
function hs_multistep_achievements_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Achievement steps table
    $steps_table = $wpdb->prefix . 'hs_achievement_steps';
    $sql_steps = "CREATE TABLE IF NOT EXISTS $steps_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        achievement_id MEDIUMINT(9) NOT NULL,
        step_order INT NOT NULL,
        step_name VARCHAR(255) NOT NULL,
        step_description TEXT,
        metric VARCHAR(50) NOT NULL,
        target_value INT NOT NULL,
        target_gid VARCHAR(255) DEFAULT NULL,
        requires_previous_step TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY achievement_id (achievement_id),
        KEY step_order (step_order)
    ) $charset_collate;";

    // User step progress table
    $progress_table = $wpdb->prefix . 'hs_user_step_progress';
    $sql_progress = "CREATE TABLE IF NOT EXISTS $progress_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        achievement_id MEDIUMINT(9) NOT NULL,
        step_id BIGINT(20) UNSIGNED NOT NULL,
        current_value INT DEFAULT 0,
        completed TINYINT(1) DEFAULT 0,
        date_started DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_completed DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_step (user_id, step_id),
        KEY user_id (user_id),
        KEY achievement_id (achievement_id),
        KEY completed (completed)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_steps);
    dbDelta($sql_progress);
}

/**
 * Add a step to an achievement
 */
function hs_add_achievement_step($achievement_id, $step_data) {
    global $wpdb;
    $table = $wpdb->prefix . 'hs_achievement_steps';

    $defaults = array(
        'step_order' => 1,
        'step_name' => '',
        'step_description' => '',
        'metric' => '',
        'target_value' => 0,
        'target_gid' => null,
        'requires_previous_step' => 1
    );

    $step_data = wp_parse_args($step_data, $defaults);
    $step_data['achievement_id'] = $achievement_id;

    $result = $wpdb->insert($table, $step_data, array(
        '%d', // achievement_id
        '%d', // step_order
        '%s', // step_name
        '%s', // step_description
        '%s', // metric
        '%d', // target_value
        '%d', // target_gid
        '%d'  // requires_previous_step
    ));

    return $result ? $wpdb->insert_id : false;
}

/**
 * Get steps for an achievement
 */
function hs_get_achievement_steps($achievement_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'hs_achievement_steps';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE achievement_id = %d ORDER BY step_order ASC",
        $achievement_id
    ));
}

/**
 * Check user's progress on multi-step achievements
 */
function hs_check_multistep_achievements($user_id) {
    global $wpdb;

    $steps_table = $wpdb->prefix . 'hs_achievement_steps';
    $progress_table = $wpdb->prefix . 'hs_user_step_progress';
    $achievements_table = $wpdb->prefix . 'hs_achievements';
    $user_achievements_table = $wpdb->prefix . 'hs_user_achievements';

    // Get all achievements with steps
    $achievements_with_steps = $wpdb->get_results(
        "SELECT DISTINCT a.* FROM $achievements_table a
        INNER JOIN $steps_table s ON a.id = s.achievement_id
        WHERE a.id NOT IN (
            SELECT achievement_id FROM $user_achievements_table WHERE user_id = $user_id
        )"
    );

    if (empty($achievements_with_steps)) {
        return;
    }

    foreach ($achievements_with_steps as $achievement) {
        $steps = hs_get_achievement_steps($achievement->id);

        if (empty($steps)) {
            continue;
        }

        $all_steps_completed = true;

        foreach ($steps as $step) {
            // Get or create progress record
            $progress = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $progress_table WHERE user_id = %d AND step_id = %d",
                $user_id,
                $step->id
            ));

            if (!$progress) {
                // Create progress record
                $wpdb->insert($progress_table, array(
                    'user_id' => $user_id,
                    'achievement_id' => $achievement->id,
                    'step_id' => $step->id,
                    'current_value' => 0,
                    'completed' => 0
                ), array('%d', '%d', '%d', '%d', '%d'));

                $progress = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $progress_table WHERE user_id = %d AND step_id = %d",
                    $user_id,
                    $step->id
                ));
            }

            if ($progress->completed) {
                continue; // Step already completed
            }

            // Check if previous step is required and completed
            if ($step->requires_previous_step && $step->step_order > 1) {
                $previous_step = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $steps_table
                    WHERE achievement_id = %d AND step_order = %d",
                    $achievement->id,
                    $step->step_order - 1
                ));

                if ($previous_step) {
                    $previous_progress = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $progress_table
                        WHERE user_id = %d AND step_id = %d",
                        $user_id,
                        $previous_step->id
                    ));

                    if (!$previous_progress || !$previous_progress->completed) {
                        $all_steps_completed = false;
                        break; // Can't progress to this step yet
                    }
                }
            }

            // Check current step
            $current_value = hs_get_step_metric_value($user_id, $step);

            // Update progress
            $wpdb->update(
                $progress_table,
                array('current_value' => $current_value),
                array('user_id' => $user_id, 'step_id' => $step->id),
                array('%d'),
                array('%d', '%d')
            );

            // Check if step is completed
            if ($current_value >= $step->target_value) {
                $wpdb->update(
                    $progress_table,
                    array(
                        'completed' => 1,
                        'date_completed' => current_time('mysql')
                    ),
                    array('user_id' => $user_id, 'step_id' => $step->id),
                    array('%d', '%s'),
                    array('%d', '%d')
                );
            } else {
                $all_steps_completed = false;
            }
        }

        // If all steps are completed, unlock the achievement
        if ($all_steps_completed) {
            $wpdb->insert(
                $user_achievements_table,
                array(
                    'user_id' => $user_id,
                    'achievement_id' => $achievement->id,
                    'date_unlocked' => current_time('mysql')
                ),
                array('%d', '%d', '%s')
            );

            // Award points
            if ($achievement->points_reward > 0 && function_exists('award_points')) {
                award_points($user_id, $achievement->points_reward);
            }
        }
    }
}

/**
 * Get the current value for a step metric
 */
function hs_get_step_metric_value($user_id, $step) {
    global $wpdb;

    $metric = $step->metric;
    $target_gid = $step->target_gid;

    // Handle tag-based metrics
    if ($metric === 'read_books_with_tag' && $target_gid) {
        // For tag metrics, target_gid contains the tag slug
        $tag_slug = trim($target_gid);
        if (!empty($tag_slug)) {
            return hs_get_tag_read_count($user_id, $tag_slug);
        }
        return 0;
    }

    // Handle GID-specific metrics
    if ($target_gid && strpos($metric, '_gid') !== false) {
        $user_books_table = $wpdb->prefix . 'user_books';
        $gid_table = $wpdb->prefix . 'hs_gid';

        // Check if target_gid is numeric (actual GID) vs string (tag slug)
        if (!is_numeric($target_gid)) {
            return 0; // Invalid GID
        }

        $target_gid = (int)$target_gid;

        switch ($metric) {
            case 'read_book_gid':
                // Check if user has completed a book with this GID
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $user_books_table ub
                    INNER JOIN {$wpdb->posts} p ON ub.book_id = p.ID
                    INNER JOIN $gid_table g ON p.ID = g.post_id
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE ub.user_id = %d
                    AND g.gid = %d
                    AND pm.meta_key = 'nop'
                    AND ub.current_page >= CAST(pm.meta_value AS UNSIGNED)
                    AND CAST(pm.meta_value AS UNSIGNED) > 0",
                    $user_id,
                    $target_gid
                ));
                return (int)$count;

            case 'review_book_gid':
                // Check if user has reviewed a book with this GID
                $reviews_table = $wpdb->prefix . 'hs_book_reviews';
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $reviews_table r
                    INNER JOIN {$wpdb->posts} p ON r.book_id = p.ID
                    INNER JOIN $gid_table g ON p.ID = g.post_id
                    WHERE r.user_id = %d
                    AND g.gid = %d
                    AND r.rating IS NOT NULL",
                    $user_id,
                    $target_gid
                ));
                return (int)$count;

            case 'add_book_gid':
                // Check if user has added a book with this GID to library
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $user_books_table ub
                    INNER JOIN {$wpdb->posts} p ON ub.book_id = p.ID
                    INNER JOIN $gid_table g ON p.ID = g.post_id
                    WHERE ub.user_id = %d
                    AND g.gid = %d",
                    $user_id,
                    $target_gid
                ));
                return (int)$count;
        }
    }

    // Handle regular metrics (non-GID)
    $metric_map = array(
        'points' => 'user_points',
        'books_read' => 'hs_completed_books_count',
        'pages_read' => 'hs_total_pages_read',
        'books_added' => 'hs_books_added_count',
        'approved_reports' => 'hs_approved_reports_count',
        'notes_created' => 'hs_notes_created_count',
    );

    if (isset($metric_map[$metric])) {
        return (int)get_user_meta($user_id, $metric_map[$metric], true);
    }

    return 0;
}

/**
 * Get user's progress on an achievement
 */
function hs_get_user_achievement_progress($user_id, $achievement_id) {
    global $wpdb;
    $progress_table = $wpdb->prefix . 'hs_user_step_progress';
    $steps_table = $wpdb->prefix . 'hs_achievement_steps';

    $progress = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, s.step_name, s.step_description, s.step_order, s.target_value, s.metric
        FROM $progress_table p
        INNER JOIN $steps_table s ON p.step_id = s.id
        WHERE p.user_id = %d AND p.achievement_id = %d
        ORDER BY s.step_order ASC",
        $user_id,
        $achievement_id
    ));

    return $progress;
}

/**
 * Migrate achievement_steps table to change target_gid from INT to VARCHAR
 */
function hs_migrate_target_gid_to_varchar() {
    global $wpdb;
    $steps_table = $wpdb->prefix . 'hs_achievement_steps';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$steps_table'") != $steps_table) {
        return;
    }

    // Check current column type
    $column_info = $wpdb->get_row($wpdb->prepare(
        "SHOW COLUMNS FROM $steps_table LIKE %s",
        'target_gid'
    ));

    // If column exists and is INT, convert it to VARCHAR
    if ($column_info && strpos(strtolower($column_info->Type), 'int') !== false) {
        $wpdb->query("ALTER TABLE $steps_table MODIFY COLUMN target_gid VARCHAR(255) DEFAULT NULL");
    }
}
add_action('admin_init', 'hs_migrate_target_gid_to_varchar');

// Hook into stats updates
add_action('hs_stats_updated', 'hs_check_multistep_achievements', 10, 1);
add_action('hs_points_updated', 'hs_check_multistep_achievements', 10, 1);
