<?php

// Filter results for the search feature.
// Originally designed to prevent each ISBN of a book being listed in the results.


// Filter the main query to hide books that have been merged
add_action('pre_get_posts', 'hs_exclude_merged_books_from_search');


function hs_exclude_merged_books_from_search($query)
{
	// Make sure only public queries are affected
	if (is_admin() || !$query -> is_search() || $query -> is_main_query())
	{
		return;
	}

	// Verify that this is a book search
	$post_type = $query -> get('post_type');

	if ($post_type !== 'book' && (!is_array($post_type) || !in_array('book', $post_type)))
	{
		// If there is no post type specified, still filter the books
		if (empty($post_type))
		{

		}

		else
		{
			return;
		}
	}


	global $wpdb;


	// Retrieve the IDs of merged books
	$merged_book_ids = $wpdb -> get_col("
		SELECT post_id
		FROM {$wpdb -> prefix}hs_gid
		WHERE is_canonical = 0
	");


	if (!empty($merged_book_ids))
	{
		// Exclude merged books from the search
		$query -> set('post__not_in', array_merge(
			(array) $query -> get('post__not_in'),
			$merged_book_ids
		));
	}
}


// Filter book archives and taxonomy queries
add_action('pre_get_posts', 'hs_exclude_merged_books_from_archives');


function hs_exclude_merged_books_from_archives($query)
{
	// Only affect public archive queries
	if (is_admin() || !$query -> is_main_query())
	{
		return;
	}

	// Determine if this is book archive or taxonomy
	if (!is_post_type_archive('book') && !is_tax() && !is_category() && !is_tag())
	{
		return;
	}

	global $wpdb;

	// Retrieve the IDs of merged books
	$merged_book_ids = $wpdb -> get_col("
		SELECT post_id
		FROM {$wpdb -> prefix}hs_gid
		WHERE is_canonical = 0
	");

	if (!empty($merged_book_ids))
	{
		// Hide merged books
		$query -> set('post__not_in', array_merge(
			(array) $query -> get('post__not_in'),
			$merged_book_ids
		));
	}
}
