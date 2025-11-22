<?php

// A collection of functions for tracking user and site statistics

if (!defined('ABSPATH'))
{
	exit;
}


// Tracks the actual time of each activity

function hs_create_activity_tracking_table()
{
	global $wpdb;
	$charset_collate = $wpdb -> get_charset_collate();
	$table = $wpdb -> prefix . 'hs_library_activity';

	$sql = "CREATE TABLE IF NOT EXISTS $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		book_id bigint(20) unsigned NOT NULL,
		activity_type
	enum('added', 'started', 'completed', 'removed', 'progress_update') NOT NULL,
		activity_data text DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY book_id (book_id),
		KEY activity_type (activity_type),
		KEY created_at (created_at)) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	update_option('hs_activity_tracking_db_version', '1.0');
}
// Create the table on admin init
add_action('admin_init', function()
{
	if (get_option('hs_activity_tracking_db_version') !== '1.0')
	{
		hs_create_activity_tracking_table();
	}
});


// Track library activities
function hs_track_library_activity($user_id, $book_id, $activity_type, $activity_data = null)
{
	global $wpdb;
	$table = $wpdb -> prefix . 'hs_library_activity';

	$data_json = $activity_data ? json_encode($activity_data) : null;

	$result = $wpdb -> insert(
		$table,
		array(
			'user_id' => $user_id,
			'book_id' => $book_id,
			'activity_type' => $activity_type,
			'activity_data' => $data_json,
		),

		array('%d', '%d', '%s', '%s')
	);

	if ($result === false)
	{
		return false;
	}

	$activity_id = $wpdb -> insert_id;

	// Trigger action for various features to hook into
	do_action('hs_library_activity_tracked', $user_id, $book_id, $activity_type, $activity_data);

	return $activity_id;
}


// Get library activity for a user
function hs_get_library_activity_for_user($user_id, $args = array())
{
	global $wpdb;
	$table = $wpdb -> prefix . 'hs_library_activity';

	$defaults = array(
		'book_id' => null,
		'activity_type' => null,
		'limit' => 50,
		'offset' => 0,
	);


	$args = wp_parse_args($args, $defaults);
	$where = array('user_id = %d');
	$where_values = array($user_id);

	if ($args['book_id'])
	{
		$where[] = 'book_id = %d';
		$where_values[] = $args['book_id'];
	}

	if ($args['activity_type'])
	{
		$where[] = 'activity_type = %s';
		$where_values[] = $args['activity_type'];
	}

	$where_clause = implode(' AND ', $where);
	$where_values[] = $args['limit'];
	$where_values[] = $args['offset'];

	$query = "SELECT la.*, p.post_title as book_title
		FROM $table la
		LEFT JOIN {$wpdb -> posts} p ON la.book_id = p.ID
		WHERE $where_clause
		ORDER BY la.created_at DESC
		LIMIT %d OFFSET %d";

	$activities = $wpdb -> get_results($wpdb -> prepare($query, $where_values));


	// Parse JSON
	foreach ($activites as $activity)
	{
		if ($activity -> activity_data)
		{
			$activity -> activity_data = json_decode($activity -> activity_data);
		}
	}

	return $activities;
}


/* Site-wide statistics */

// How many books have been added to users' libraries, collectively.
function hs_get_total_books_in_libraries()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'user_books';
	$count = $wpdb -> get_var("SELECT COUNT(*) FROM $table_name");

	return (int) $count;
}


// Sum of page counts across all the books in the DB (this function may need to be done away with, honestly).
function hs_get_total_pages_available()
{
	global $wpdb;

	// Sum all 'nop' counts
	$total_pages = $wpdb -> get_var($wpdb -> prepare("
		SELECT SUM(CAST(pm.meta_value AS UNSIGNED))
		FROM {$wpdb -> postmeta} pm
		INNER JOIN {$wpdb -> posts} p ON pm.post_id = p.ID
		WHERE pm.meta_key = %s
		AND p.post_type = %s
		AND p.post_status = %s
		AND pm.meta_value REGEXP '^[0-9]+$'", 'nop', 'book', 'publish'));

	return (int) $total_pages;
}


// Count how many points have been earned between all the users on GRead
function hs_get_total_points_earned()
{
	global $wpdb;

	$total_points = $wpdb -> get_var($wpdb -> prepare("
		SELECT SUM(CAST(meta_value AS UNSIGNED))
		FROM {$wpdb -> usermeta}
		WHERE meta_key = %s", 'user_points'));

	return (int) $total_points;
}


// Count how many pages have been read between all the users on GRead
function hs_get_total_pages_read()
{
	global $wpdb;

	$total_pages = $wpdb -> get_var($wpdb -> prepare("
		SELECT SUM(CAST(meta_value AS UNSIGNED))
		FROM {$wpdb -> usermeta}
		WHERE meta_key = %s", 'hs_total_pages_read'));

	return (int) $total_pages;
}


// Count how many books have been completed between all the users on GRead
function hs_get_total_books_completed()
{
	global $wpdb;

	$total_completed = $wpdb -> get_var($wpdb -> prepare("
		SELECT SUM(CAST(meta_value AS UNSIGNED))
		FROM {$wpdb -> usermeta}
		WHERE meta_key = %s", 'hs_completed_books_count'));

	return (int) $total_completed;
}


// Count how many users have registered for GRead
function hs_get_total_users_registered()
{
	$user_count = count_users();
	return (int) $user_count['total_users'];
}


// Arrange all the sitewide statistics into a single array
function hs_get_site_statistics()
{
	return array(
		'books_in_libraries' => hs_get_total_books_in_libraries(),
		'pages_available' => hs_get_total_pages_available(),
		'total_points' => hs_get_total_points_earned(),
		'total_pages_read' => hs_get_total_pages_read(),
		'books_completed' => hs_get_total_books_completed(),
		'users_registered' => hs_get_total_users_registered(),
	);
}
