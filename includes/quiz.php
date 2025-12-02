<?php

// GRead's quiz to help members find new books to read


if (!defined('ABSPATH'))
{
	exit;
}


// Create a table for the questions
function hs_quiz_questions_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_quiz_questions';
	$charset_collate = $wpdb -> get_charset_collate();


	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		question_key VARCHAR(100) NOT NULL,
		question_text TEXT NOT NULL,
		question_subtitle TEXT NULL,
		question_type VARCHAR(50) NOT NULL,
		options LONGTEXT NULL,
		metadata_field VARCHAR(100) NULL,
		weight INT DEFAULT 10,
		is_required BOOLEAN DEFAULT TRUE,
		is_active BOOLEAN DEFAULT TRUE,
		display_order INT DEFAULT 0,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY question_key_unique (question_key),
		INDEX is_active_index (is_active),
		INDEX display_order_index (display_order)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);


	// Insert the default questions
	hs_quiz_insert_default_questions();
}


// Create a table for quiz sessions
function hs_quiz_sessions_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_quiz_sessions';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT(20) UNSIGNED NULL,
		session_token VARCHAR(255) NOT NULL,
		quiz_type VARCHAR(50) DEFAULT 'reading_recommendations',
		responses LONGTEXT NULL,
		recommended_books LONGTEXT NULL,
		date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
		ip_address VARCHAR(45) NULL,
		PRIMARY KEY (id),
		INDEX user_id_index (user_id),
		INDEX session_token_index (session_token),
		INDEX date_created_index (date_created)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}


// Insert default quiz questions
function hs_quiz_insert_default_questions()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_quiz_questions';

	// Make sure that they are not already in the table
	$existing = $wpdb -> get_var("SELECT COUNT(*) FROM $table_name");

	if ($existing > 0)
	{
		return;
	}

	require_once plugin_dir_path(__FILE__) . 'quiz_recommendation.php';
	$options = hs_get_quiz_options();

	$default_questions = array(
	array(
		'question_key' => 'fiction',
		'question_text' => 'Do you like fiction, or non-fiction?',
		'question_text' => 'Select one of the options.',
		'question_type' => 'single_select',
		'options' => json_encode($options['genres']),
		'metadata_field' => 'genres',
		'weight' => 30,
		'display_order' => 1
	));

	foreach ($default_options as $question)
	{
		$wpdb -> insert($table_name, $question);
	}
}


// Create book metadata table
function hs_book_metadata_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_book_metadata';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		book_id BIGINT(20) UNSIGNED NOT NULL,
		genres LONGTEXT NULL,
		themes LONGTEXT NULL,
		mood VARCHAR(100) NULL,
		reading_level VARCHAR(50) NULL,
		pacing VARCHAR(50) NULL,
		time_period VARCHAR(100) NULL,
		length_category VARCHAR(50) NULL,
		content_warnings LONGTEXT NULL,
		target_audiences VARCHAR(100) NULL,
		date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
		date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		updated_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (id),
		UNIQUE KEY book_id_unique (book_id),
		INDEX mood_index (mood),
		INDEX reading_level_index (reading_level),
		INDEX pacing_index (pacing),
		INDEX length_category_index (length_category)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}


// Retrieve all active quiz questions
function hs_get_quiz_questions()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_quiz_questions';
	$questions = $wpdb -> get_results(
		"SELECT * FROM $table_name WHERE is_active = 1 ORDER BY display_order ASC",
		ARRAY_A
	);

	// Decode JSON options
	foreach ($questions as &$question)
	{
		if (!empty($question['options']))
		{
			$question['options'] = json_decode($question['options'], true);
		}
	}

	return $questions;
}
