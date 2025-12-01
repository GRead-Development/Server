<?php

// Randomly select a book from the user's library



if (!defined('ABSPATH'))
{
	exit;
}


function hs_get_random_unread_book($user_id)
{
	global $wpdb;


	// Retrieve a list of books that a user has in their library and has not completed
	$books = $wpdb -> get_results($wpdb -> prepare(
		"SELECT um.meta_value as book_id, p.post_title, p.ID,
			pm_author.meta_value as book_author,
			pm_page.meta_value as total_pages
		FROM {$wpdb -> usermeta} um
		LEFT JOIN {$wpdb -> posts} p ON um.meta_value = p.ID
		LEFT JOIN {$wpdb -> postmeta} pm_author ON p.ID = pm_author.post_id AND pm_author.meta_key = 'book_author'
		LEFT JOIN {$wpdb -> postmeta} pm_pages ON p.ID = pm_pages.post_id AND pm_pages.meta_key = 'nop'
		LEFT JOIN {$wpdb -> usermeta} um2 on um2.user_id = %d AND um2.meta_key = CONCAT('book_', um.meta_value, '_completed')
		WHERE um.user_id = %d
		AND um.meta_key LIKE 'book_%%_in_library'
		AND um.meta_value IS NOT NULL
		AND (um2.meta_value IS NULL OR um2.meta_value != '1')
		AND p.post_type = 'book'
		AND p.post_status = 'publish'",
		$user_id,
		$user_id
	));


	if (empty($books))
	{
		return false;
	}


	// Select a random book
	$random_book = $books[array_rand($books)];

	// Retrieve current page
	$current_page = get_user_meta($user_id, "book_{$random_book -> ID}_current_page", true) ?: 0;

	$book_data = array(
		'id' => (int)$random_book -> ID,
		'title' => $random_book -> post_title,
		'author' => $random_book -> book_author,
		'total_pages' => (int)$random_book -> total_pages,
		'current_page' => (int)$current_page,
		'progress_percentage' => $random_book -> total_pages > 0 ? round(($current_page / $random_book -> total_pages) * 100, 2) : 0,
	);


	return $book_data;
}
