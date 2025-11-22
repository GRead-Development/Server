<?php

// Author and series management system



if (!defined('ABSPATH'))
{
	exit;
}


function hs_authors_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_authors';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		canonical_name VARCHAR(255) NOT NULL,
		slug VARCHAR(255) NOT NULL,
		bio TEXT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		created_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug),
		INDEX canonical_name_index (canonical_name),
		INDEX name_index (name)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}


// Author alias table
function hs_author_aliases_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_author_aliases';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id BIGINT(2) UNSIGNED NOT NULL AUTO_INCREMENT,
		author_id BIGINT(20) UNSIGNED NOT NULL,
		alias_name VARCHAR(255) NOT NULL,
		alias_slug VARCHAR(255) NOT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		created_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (id),
		UNIQUE KEY alias_slug (alias_slug),
		INDEX author_id_index (author_id),
		INDEX alias_name_index (alias_name)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}


// Book-author relationship (many-to-many)
function hs_book_authors_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_book_authors';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		book_id BIGINT(20) UNSIGNED NOT NULL,
		author_id BIGINT(20) UNSIGNED NOT NULL,
		author_order TINYINT(3) UNSIGNED DEFAULT 1,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY book_author_unique (book_id, author_id),
		INDEX book_id_index (book_id),
		INDEX author_id_index (author_id),
		INDEX author_order_index (author_order)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}


// Series metadata table
function hs_series_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_series';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		slug VARCHAR(255) NOT NULL,
		description TEXT NULL,
		total_books INT DEFAULT 0,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		created_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug),
		INDEX name_index (name)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

// Book-Series relationship table (many-to-many)
function hs_book_series_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_book_series';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		book_id BIGINT(20) UNSIGNED NOT NULL,
		series_id BIGINT(20) UNSIGNED NOT NULL,
		position DECIMAL(5,2) NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY book_series_unique (book_id, series_id),
		INDEX book_id_index (book_id),
		INDEX series_id_index (series_id),
		INDEX position_index (position)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

// Track authors merges
function hs_author_merges_create_table()
{
	global $wpdb;
	$table_name = $wpdb -> prefix . 'hs_author_merges';
	$charset_collate = $wpdb -> get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		from_author_id BIGINT(20) UNSIGNED NOT NULL,
		to_author_id BIGINT(20) UNSIGNED NOT NULL,
		merged_by BIGINT(20) UNSIGNED NOT NULL,
		merge_reason TEXT NULL,
		merged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		INDEX from_author_index (from_author_id),
		INDEX to_author_index (to_author_id),
		INDEX merged_at_index (merged_at)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}


// Create an author record
function hs_create_author($name, $args = array())
{
	global $wpdb;

	$name = trim($name);
	if (empty($name))
	{
		return false;
	}

	// Set default values
	$canonical_name = isset($args['canonical_name']) ? $args['canonical_name'] : $name;
	$bio = isset($args['bio']) ? $args['bio'] : null;
	$created_by = isset($args['created_by']) ? $args['created_by'] : get_current_user_id();

	// Create a unique slug for the new author
	$slug = sanitize_title($name);
	$original_slug = $slug;
	$counter = 1;

	// Make sure it is a unique slug
	while ($wpdb -> get_var($wpdb -> prepare(
		"SELECT id FROM {$wpdb -> prefix}hs_authors WHERE slug = %s",
		$slug
	)))

	{
		$slug = $original_slug . '-' . $counter;
		$counter++;
	}

	$result = $wpdb -> insert(
		$wpdb -> prefix . 'hs_authors',
		array(
		'name' => $name,
		'canonical_name' => $canonical_name,
		'slug' => $slug,
		'bio' => $bio,
		'created_by' => $created_by
	),
		array('%s', '%s', '%s', '%s', '%d')
	);

	if ($result === false)
	{
		return false;
	}

	return $wpdb -> insert_id;
}


// Get author by ID
function hs_get_author($author_id)
{
	global $wpdb;

	return $wpdb -> get_row($wpdb -> prepare(
		"SELECT * FROM {$wpdb -> prefix}hs_authors WHERE id = %d",
		$author_id
	));
}


// Get author by name or slug
function hs_get_author_by_name($name_or_slug)
{
	global $wpdb;

	// Try to find an exact match by name
	$author = $wpdb -> get_row($wpdb -> prepare(
		"SELECT * FROM {$wpdb -> prefix}hs_authors WHERE name = %s OR canonical_name = %s",
		$name_or_slug, $name_or_slug
	));


	// Try to match the slug
	if (!$author)
	{
		$author = $wpdb -> get_row($wpdb -> prepare(
		"SELECT * FROM {$wpdb -> prefix}hs_authors WHERE slug = %s",
		sanitize_title($name_or_slug)
		));
	}

	// Match alias
	if (!$author)
	{
		$alias = $wpdb -> get_row($wpdb -> prepare(
		"SELECT * FROM {$wpdb -> prefix}hs_author_aliases WHERE alias_name = %s OR alias_slug = %s",
		$name_or_slug, sanitize_title($name_or_slug)
		));

		if ($alias)
		{
			$author = hs_get_author($alias -> author_id);
		}
	}

	return $author;
}


// Add alias to an author
function hs_add_author_alias($author_id, $alias_name)
{
	global $wpdb;
	$alias_name = trim($alias_name);
	if (empty($alias_name))
	{
		return false;
	}

	// Check if the author actually exists
	if (!hs_get_author($author_id))
	{
		return false;
	}

	$alias_slug = sanitize_title($alias_name);
	$original_slug = $alias_slug;
	$counter = 1;

	// Make sure that the alias is unique (again, I guess)
	while ($wpdb -> get_var($wpdb -> prepare(
		"SELECT id FROM {$wpdb -> prefix}hs_author_aliases WHERE alias_slug = %s",
		$alias_slug
	)))
	{
		$alias_slug = $original_slug . '-' . $counter;
		$counter++;
	}

	$result = $wpdb -> insert(
		$wpdb -> prefix . 'hs_author_aliases',
		array(
			'author_id' => $author_id,
			'alias_name' => $alias_name,
			'alias_slug' => $alias_slug,
			'created_by' => get_current_user_id()
		),

		array('%d', '%s', '%s', '%d')
	);

	if ($result === false)
	{
		return false;
	}

	return $wpdb -> insert_id;
}


// REWRITE

/**
 * Get all aliases for an author
 *
 * @param int $author_id
 * @return array Array of alias objects
 */
function hs_get_author_aliases($author_id) {
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_author_aliases WHERE author_id = %d ORDER BY alias_name",
        $author_id
    ));
}

/**
 * Delete an author alias
 *
 * @param int $alias_id
 * @return bool True on success, false on failure
 */
function hs_delete_author_alias($alias_id) {
    global $wpdb;

    return $wpdb->delete(
        $wpdb->prefix . 'hs_author_aliases',
        array('id' => $alias_id),
        array('%d')
    ) !== false;
}

/**
 * Merge two authors together
 * All books, aliases from $from_author_id will be moved to $to_author_id
 *
 * @param int $from_author_id Author to merge from (will be deleted)
 * @param int $to_author_id Author to merge into (will be kept)
 * @param string $reason Optional reason for merge
 * @return bool True on success, false on failure
 */
function hs_merge_authors($from_author_id, $to_author_id, $reason = '') {
    global $wpdb;

    // Validate both authors exist
    $from_author = hs_get_author($from_author_id);
    $to_author = hs_get_author($to_author_id);

    if (!$from_author || !$to_author) {
        return false;
    }

    // Can't merge an author with itself
    if ($from_author_id === $to_author_id) {
        return false;
    }

    // Start transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Move all book relationships to the target author
        // Use INSERT IGNORE to avoid duplicates (same book with both authors)
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}hs_book_authors
             SET author_id = %d
             WHERE author_id = %d
             AND book_id NOT IN (
                 SELECT book_id FROM (
                     SELECT book_id FROM {$wpdb->prefix}hs_book_authors WHERE author_id = %d
                 ) AS existing_books
             )",
            $to_author_id, $from_author_id, $to_author_id
        ));

        // Delete any remaining duplicate relationships
        $wpdb->delete(
            $wpdb->prefix . 'hs_book_authors',
            array('author_id' => $from_author_id),
            array('%d')
        );

        // Move all aliases to the target author
        $wpdb->update(
            $wpdb->prefix . 'hs_author_aliases',
            array('author_id' => $to_author_id),
            array('author_id' => $from_author_id),
            array('%d'),
            array('%d')
        );

        // Add the old author's name as an alias to the new author
        hs_add_author_alias($to_author_id, $from_author->name);

        // Record the merge in history
        $wpdb->insert(
            $wpdb->prefix . 'hs_author_merges',
            array(
                'from_author_id' => $from_author_id,
                'to_author_id' => $to_author_id,
                'merged_by' => get_current_user_id(),
                'merge_reason' => $reason
            ),
            array('%d', '%d', '%d', '%s')
        );

        // Delete the old author record
        $wpdb->delete(
            $wpdb->prefix . 'hs_authors',
            array('id' => $from_author_id),
            array('%d')
        );

        $wpdb->query('COMMIT');
        return true;

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return false;
    }
}

/**
 * Delete an author (only if they have no books)
 *
 * @param int $author_id
 * @return bool True on success, false on failure
 */
function hs_delete_author($author_id) {
    global $wpdb;

    // Check if author has any books
    $book_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_authors WHERE author_id = %d",
        $author_id
    ));

    if ($book_count > 0) {
        return false; // Can't delete author with books
    }

    // Delete all aliases first
    $wpdb->delete(
        $wpdb->prefix . 'hs_author_aliases',
        array('author_id' => $author_id),
        array('%d')
    );

    // Delete the author
    return $wpdb->delete(
        $wpdb->prefix . 'hs_authors',
        array('id' => $author_id),
        array('%d')
    ) !== false;
}

/**
 * Link a book to an author
 *
 * @param int $book_id
 * @param int $author_id
 * @param int $order Author order for multi-author books (1 = first author, 2 = second, etc.)
 * @return int|false Relationship ID on success, false on failure
 */
function hs_link_book_author($book_id, $author_id, $order = 1) {
    global $wpdb;

    // Check if relationship already exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}hs_book_authors WHERE book_id = %d AND author_id = %d",
        $book_id, $author_id
    ));

    if ($existing) {
        // Update the order if it already exists
        $wpdb->update(
            $wpdb->prefix . 'hs_book_authors',
            array('author_order' => $order),
            array('id' => $existing),
            array('%d'),
            array('%d')
        );
        return $existing;
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'hs_book_authors',
        array(
            'book_id' => $book_id,
            'author_id' => $author_id,
            'author_order' => $order
        ),
        array('%d', '%d', '%d')
    );

    if ($result === false) {
        return false;
    }

    return $wpdb->insert_id;
}

/**
 * Unlink a book from an author
 *
 * @param int $book_id
 * @param int $author_id
 * @return bool True on success, false on failure
 */
function hs_unlink_book_author($book_id, $author_id) {
    global $wpdb;

    return $wpdb->delete(
        $wpdb->prefix . 'hs_book_authors',
        array('book_id' => $book_id, 'author_id' => $author_id),
        array('%d', '%d')
    ) !== false;
}

/**
 * Get all authors for a book
 *
 * @param int $book_id
 * @return array Array of author objects with order information
 */
function hs_get_book_authors($book_id) {
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, ba.author_order
         FROM {$wpdb->prefix}hs_authors a
         INNER JOIN {$wpdb->prefix}hs_book_authors ba ON a.id = ba.author_id
         WHERE ba.book_id = %d
         ORDER BY ba.author_order, a.name",
        $book_id
    ));
}

/**
 * Get all books by an author
 *
 * @param int $author_id
 * @param array $args Optional query arguments (limit, offset, orderby, order)
 * @return array Array of book post objects
 */
function hs_get_author_books($author_id, $args = array()) {
    global $wpdb;

    $defaults = array(
        'limit' => -1,
        'offset' => 0,
        'orderby' => 'title',
        'order' => 'ASC'
    );

    $args = wp_parse_args($args, $defaults);

    // Get book IDs for this author
    $book_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT book_id FROM {$wpdb->prefix}hs_book_authors WHERE author_id = %d",
        $author_id
    ));

    if (empty($book_ids)) {
        return array();
    }

    // Use WordPress get_posts to retrieve the books
    $query_args = array(
        'post_type' => 'book',
        'post__in' => $book_ids,
        'posts_per_page' => $args['limit'],
        'offset' => $args['offset'],
        'orderby' => $args['orderby'],
        'order' => $args['order']
    );

    return get_posts($query_args);
}

/**
 * Get book count for an author
 *
 * @param int $author_id
 * @return int Number of books
 */
function hs_get_author_book_count($author_id) {
    global $wpdb;

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_authors WHERE author_id = %d",
        $author_id
    ));
}

// ===========================
// SERIES MANAGEMENT FUNCTIONS
// ===========================

/**
 * Create a new series
 *
 * @param string $name Series name
 * @param array $args Optional arguments (description, created_by)
 * @return int|false Series ID on success, false on failure
 */
function hs_create_series($name, $args = array()) {
    global $wpdb;

    $name = trim($name);
    if (empty($name)) {
        return false;
    }

    $description = isset($args['description']) ? $args['description'] : null;
    $created_by = isset($args['created_by']) ? $args['created_by'] : get_current_user_id();

    // Generate unique slug
    $slug = sanitize_title($name);
    $original_slug = $slug;
    $counter = 1;

    while ($wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}hs_series WHERE slug = %s",
        $slug
    ))) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'hs_series',
        array(
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'created_by' => $created_by
        ),
        array('%s', '%s', '%s', '%d')
    );

    if ($result === false) {
        return false;
    }

    return $wpdb->insert_id;
}

/**
 * Get series by ID
 *
 * @param int $series_id
 * @return object|null Series object or null if not found
 */
function hs_get_series($series_id) {
    global $wpdb;

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_series WHERE id = %d",
        $series_id
    ));
}

/**
 * Get series by name or slug
 *
 * @param string $name_or_slug
 * @return object|null Series object or null if not found
 */
function hs_get_series_by_name($name_or_slug) {
    global $wpdb;

    // Try name match first
    $series = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_series WHERE name = %s",
        $name_or_slug
    ));

    // Try slug match
    if (!$series) {
        $series = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hs_series WHERE slug = %s",
            sanitize_title($name_or_slug)
        ));
    }

    return $series;
}

/**
 * Link a book to a series
 *
 * @param int $book_id
 * @param int $series_id
 * @param float $position Book position in series (e.g., 1, 2, 2.5 for novellas)
 * @return int|false Relationship ID on success, false on failure
 */
function hs_link_book_series($book_id, $series_id, $position = null) {
    global $wpdb;

    // Check if relationship already exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}hs_book_series WHERE book_id = %d AND series_id = %d",
        $book_id, $series_id
    ));

    if ($existing) {
        // Update the position if it already exists
        $wpdb->update(
            $wpdb->prefix . 'hs_book_series',
            array('position' => $position),
            array('id' => $existing),
            array('%f'),
            array('%d')
        );

        // Update series book count
        hs_update_series_book_count($series_id);

        return $existing;
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'hs_book_series',
        array(
            'book_id' => $book_id,
            'series_id' => $series_id,
            'position' => $position
        ),
        array('%d', '%d', '%f')
    );

    if ($result === false) {
        return false;
    }

    // Update series book count
    hs_update_series_book_count($series_id);

    return $wpdb->insert_id;
}

/**
 * Unlink a book from a series
 *
 * @param int $book_id
 * @param int $series_id
 * @return bool True on success, false on failure
 */
function hs_unlink_book_series($book_id, $series_id) {
    global $wpdb;

    $result = $wpdb->delete(
        $wpdb->prefix . 'hs_book_series',
        array('book_id' => $book_id, 'series_id' => $series_id),
        array('%d', '%d')
    );

    if ($result !== false) {
        hs_update_series_book_count($series_id);
    }

    return $result !== false;
}

/**
 * Get all books in a series
 *
 * @param int $series_id
 * @return array Array of book objects with position information
 */
function hs_get_series_books($series_id) {
    global $wpdb;

    $book_data = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, bs.position
         FROM {$wpdb->prefix}posts p
         INNER JOIN {$wpdb->prefix}hs_book_series bs ON p.ID = bs.book_id
         WHERE bs.series_id = %d AND p.post_type = 'book' AND p.post_status = 'publish'
         ORDER BY bs.position ASC, p.post_title ASC",
        $series_id
    ));

    return $book_data;
}

/**
 * Get series for a book
 *
 * @param int $book_id
 * @return array Array of series objects with position information
 */
function hs_get_book_series($book_id) {
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, bs.position
         FROM {$wpdb->prefix}hs_series s
         INNER JOIN {$wpdb->prefix}hs_book_series bs ON s.id = bs.series_id
         WHERE bs.book_id = %d
         ORDER BY s.name",
        $book_id
    ));
}

/**
 * Update the book count for a series
 *
 * @param int $series_id
 */
function hs_update_series_book_count($series_id) {
    global $wpdb;

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_series WHERE series_id = %d",
        $series_id
    ));

    $wpdb->update(
        $wpdb->prefix . 'hs_series',
        array('total_books' => $count),
        array('id' => $series_id),
        array('%d'),
        array('%d')
    );
}

/**
 * Delete a series (only if it has no books)
 *
 * @param int $series_id
 * @return bool True on success, false on failure
 */
function hs_delete_series($series_id) {
    global $wpdb;

    // Check if series has any books
    $book_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_series WHERE series_id = %d",
        $series_id
    ));

    if ($book_count > 0) {
        return false; // Can't delete series with books
    }

    return $wpdb->delete(
        $wpdb->prefix . 'hs_series',
        array('id' => $series_id),
        array('%d')
    ) !== false;
}

// ===========================
// MIGRATION & UTILITY FUNCTIONS
// ===========================

/**
 * Process a book's author field and create/link author records
 * This function is called when indexing books to ensure authors are properly tracked
 *
 * @param int $book_id
 * @param string $author_string Author string from book_author field (may contain multiple authors)
 * @return array Array of author IDs that were linked
 */
function hs_process_book_authors($book_id, $author_string) {
    global $wpdb;

    if (empty($author_string)) {
        return array();
    }

    // Check if this book already has author relationships
    // If so, don't recalculate (preserve manual corrections)
    $existing_authors = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_authors WHERE book_id = %d",
        $book_id
    ));

    if ($existing_authors > 0) {
        // Return existing author IDs
        return $wpdb->get_col($wpdb->prepare(
            "SELECT author_id FROM {$wpdb->prefix}hs_book_authors WHERE book_id = %d",
            $book_id
        ));
    }

    // Parse multiple authors (comma or semicolon separated)
    $author_names = preg_split('/[,;]/', $author_string);
    $author_ids = array();
    $order = 1;

    foreach ($author_names as $author_name) {
        $author_name = trim($author_name);
        if (empty($author_name)) {
            continue;
        }

        // Try to find existing author (by name or alias)
        $author = hs_get_author_by_name($author_name);

        if (!$author) {
            // Create new author
            $author_id = hs_create_author($author_name);
        } else {
            $author_id = $author->id;
        }

        if ($author_id) {
            // Link book to author
            hs_link_book_author($book_id, $author_id, $order);
            $author_ids[] = $author_id;
            $order++;
        }
    }

    return $author_ids;
}

/**
 * Migrate all existing books to the author ID system
 * This is a one-time migration function
 *
 * @param int $batch_size Number of books to process per batch
 * @param int $offset Starting offset
 * @return array Status information (processed, total, etc.)
 */
function hs_migrate_book_authors($batch_size = 50, $offset = 0) {
    global $wpdb;

    // Get total count of books
    $total_books = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'book'");

    // Get batch of books
    $books = $wpdb->get_results($wpdb->prepare(
        "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'book' ORDER BY ID LIMIT %d OFFSET %d",
        $batch_size, $offset
    ));

    $processed = 0;
    $created_authors = 0;
    $created_links = 0;

    foreach ($books as $book) {
        $author_string = get_field('book_author', $book->ID);

        if (!empty($author_string)) {
            $before_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_authors");
            $author_ids = hs_process_book_authors($book->ID, $author_string);
            $after_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_authors");

            $created_authors += ($after_count - $before_count);
            $created_links += count($author_ids);
        }

        $processed++;
    }

    return array(
        'processed' => $processed,
        'total' => $total_books,
        'offset' => $offset + $processed,
        'created_authors' => $created_authors,
        'created_links' => $created_links,
        'remaining' => max(0, $total_books - ($offset + $processed)),
        'complete' => ($offset + $processed) >= $total_books
    );
}

/**
 * Search for authors by name
 *
 * @param string $search_term
 * @param int $limit
 * @return array Array of author objects
 */
function hs_search_authors($search_term, $limit = 20) {
    global $wpdb;

    $search_term = '%' . $wpdb->esc_like($search_term) . '%';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT a.*
         FROM {$wpdb->prefix}hs_authors a
         LEFT JOIN {$wpdb->prefix}hs_author_aliases aa ON a.id = aa.author_id
         WHERE a.name LIKE %s
            OR a.canonical_name LIKE %s
            OR aa.alias_name LIKE %s
         ORDER BY a.name
         LIMIT %d",
        $search_term, $search_term, $search_term, $limit
    ));
}

/**
 * Search for series by name
 *
 * @param string $search_term
 * @param int $limit
 * @return array Array of series objects
 */
function hs_search_series($search_term, $limit = 20) {
    global $wpdb;

    $search_term = '%' . $wpdb->esc_like($search_term) . '%';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_series
         WHERE name LIKE %s
         ORDER BY name
         LIMIT %d",
        $search_term, $limit
    ));
}
