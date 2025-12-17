<?php
// Allows users to create notes

// Activation, hooked into the main file for HotSoup
function hs_book_notes_activate()
{
	global $wpdb;

	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix}hs_book_notes(
		id INT PRIMARY KEY AUTO_INCREMENT,
		user_id INT,
		book_id INT,
		note_text LONGTEXT,
		page_number INT,
		page_start INT,
		page_end INT,
		is_public TINYINT(1) DEFAULT 0,
		date_created DATETIME,
		date_updated DATETIME,
		INDEX (user_Id),
		INDEX (book_id),
		INDEX (user_id, book_id)
	)" );

	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix}hs_note_likes (
		id INT PRIMARY KEY AUTO_INCREMENT,
		user_id INT,
		note_id INT,
		date_liked DATETIME,
		UNIQUE KEY user_note (user_id, note_id),
		INDEX (note_id)
	)" );

	// Table to track book mentions in notes
	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix}hs_note_book_mentions (
		id INT PRIMARY KEY AUTO_INCREMENT,
		note_id INT NOT NULL,
		mentioned_book_id INT NOT NULL,
		UNIQUE KEY note_book (note_id, mentioned_book_id),
		INDEX (note_id),
		INDEX (mentioned_book_id)
	)" );
}


// Create a note for a given book
function hs_create_book_note($user_id, $book_id, $note_text, $page_number = null, $page_start = null, $page_end = null, $is_public = false)
{
	global $wpdb;

	// Authorization check
	if (!is_user_logged_in()) {
		return false;
	}

	if (get_current_user_id() != $user_id && !current_user_can('manage_options')) {
		return false;
	}

	$wpdb -> insert($wpdb -> prefix . 'hs_book_notes', array(
		'user_id' => intval($user_id),
		'book_id' => intval($book_id),
		'note_text' => sanitize_textarea_field($note_text),
		'page_number' => $page_number ? intval($page_number) : null,
		'page_start' => $page_start ? intval($page_start) : null,
		'page_end' => $page_end ? intval($page_end) : null,
		'is_public' => intval($is_public),
		'date_created' => current_time('mysql'),
		'date_updated' => current_time('mysql')
	));

	$note_id = $wpdb -> insert_id;

	// Extract and store book mentions
	if ($note_id) {
		hs_update_note_book_mentions($note_id, $note_text);
	}

	// Increment user's note count
	if ($note_id && function_exists('hs_increment_notes_created')) {
		hs_increment_notes_created($user_id);
	}

	// Award points for public notes (community contribution)
	if ($note_id && $is_public && function_exists('award_points')) {
		award_points($user_id, 3);
	}

	return $note_id;
}


// Retrieve all notes for a given book for a given user
function hs_get_user_book_notes($user_id, $book_id)
{
	global $wpdb;

	return $wpdb -> get_results($wpdb -> prepare(
		"SELECT * FROM {$wpdb -> prefix}hs_book_notes
		WHERE user_id = %d AND book_id = %d
		ORDER BY COALESCE(page_number, page_start) ASC, date_created DESC",
		intval($user_id),
		intval($book_id)
	));
}


// Retrieve all public notes for a given book
function hs_get_public_book_notes($book_id)
{
	global $wpdb;

	return $wpdb -> get_results($wpdb -> prepare(
		"SELECT n.*, u.display_name FROM {$wpdb -> prefix}hs_book_notes n
		JOIN {$wpdb -> users} u ON n.user_id = u.ID
		WHERE n.book_id = %d AND n.is_public = 1
		ORDER BY COALESCE(n.page_number, n.page_start) ASC",
		intval($book_id)
	));
}


// Retrieve a single note
function hs_get_book_note($note_id)
{
	global $wpdb;

	return $wpdb -> get_row($wpdb -> prepare(
		"SELECT n.*, u.display_name FROM {$wpdb -> prefix}hs_book_notes n
		JOIN {$wpdb -> users} u ON n.user_id = u.ID
		WHERE n.id = %d",
		intval($note_id)
	));
}


// Update a given note
function hs_update_book_note($note_id, $note_text, $page_number = null, $is_public = false)
{
	global $wpdb;

	$note = hs_get_book_note($note_id);

	if (!$note)
	{
		return false;
	}

	// Check permission
	if ($note -> user_id != get_current_user_id() && !current_user_can('manage_options'))
	{
		// TODO: Maybe a message
		return false;
	}

	// Track if visibility changed for point adjustments
	$old_is_public = (bool)$note->is_public;
	$new_is_public = (bool)$is_public;

	$result = $wpdb -> update(
		$wpdb -> prefix . 'hs_book_notes',
		array(
			'note_text' => sanitize_textarea_field($note_text),
			'page_number' => $page_number ? intval($page_number) : null,
			'is_public' => intval($is_public),
			'date_updated' => current_time('mysql')
		),
		array('id' => intval($note_id)),
		array('%s', '%d', '%d', '%s'),
		array('%d')
	);

	// Update book mentions
	if ($result !== false) {
		hs_update_note_book_mentions($note_id, $note_text);
	}

	// Handle point adjustments when visibility changes
	if ($result !== false) {
		if (!$old_is_public && $new_is_public && function_exists('award_points')) {
			// Note made public: award points
			award_points($note->user_id, 3);
		} elseif ($old_is_public && !$new_is_public && function_exists('hs_deduct_points')) {
			// Note made private: deduct points
			hs_deduct_points($note->user_id, 3);
		}
	}

	return $result;
}


// Delete a given note
function hs_delete_book_note($note_id)
{
	global $wpdb;

	$note = hs_get_book_note($note_id);

	if (!$note)
	{
		return false;
	}

	if ($note -> user_id != get_current_user_id() && !current_user_can('manage_options'))
	{
		return false;
	}

	// Track if note was public for point deduction
	$was_public = (bool)$note->is_public;

	// Delete book mentions first
	$wpdb -> delete($wpdb -> prefix . 'hs_note_book_mentions', array('note_id' => intval($note_id)), array('%d'));

	$result = $wpdb -> delete($wpdb -> prefix . 'hs_book_notes', array('id' => intval($note_id)));

	// Decrement user's note count
	if ($result && function_exists('hs_decrement_notes_created')) {
		hs_decrement_notes_created($note->user_id);
	}

	// Deduct points if the deleted note was public
	if ($result && $was_public && function_exists('hs_deduct_points')) {
		hs_deduct_points($note->user_id, 3);
	}

	return $result;
}


// Book note form
function hs_render_book_note_form($book_id)
{
	if (!is_user_logged_in())
	{
		echo '<p>You need to be logged in to add notes!</p>';
		return;
	}

	$user_id = get_current_user_id();
	$nonce = wp_create_nonce('hs_create_note_' . $book_id);

	ob_start(); ?>

	<form method="post" class="hs-book-note-form">
		<h3>Add a Note</h3>

		<textarea name="note_text" rows="5" required placeholder="Write your note"></textarea><br>

		<label>
			Page Number:
			<input type="number" name="page_number" min="1" />
		</label><br>
		// TODO Sanitize the text field
		<label>
			<input type="checkbox" name="is_public" value="1">
			Make this note public
			</label><br>

			<input type="hidden" name="book_id" value="<?php echo esc_attr($book_id); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
			<button type="submit" name="hs_submit_note">Save Note</button>
		</form>

		<?php
		return ob_get_clean();
}


/**
 * Extract book mentions from note text
 * Supports formats: @book:ID, [book:ID], #book:ID
 * Returns array of book IDs
 */
function hs_extract_book_mentions($note_text) {
	$book_ids = array();

	// Pattern to match @book:123, [book:123], or #book:123
	preg_match_all('/([@#\[]book[:\s]+(\d+)\]?)/', $note_text, $matches);

	if (!empty($matches[2])) {
		foreach ($matches[2] as $book_id) {
			$book_id = intval($book_id);
			// Verify the book exists
			if ($book_id > 0 && get_post_type($book_id) === 'book') {
				$book_ids[] = $book_id;
			}
		}
	}

	return array_unique($book_ids);
}


/**
 * Update book mentions for a note
 */
function hs_update_note_book_mentions($note_id, $note_text) {
	global $wpdb;

	$mentions_table = $wpdb->prefix . 'hs_note_book_mentions';

	// Extract mentioned book IDs
	$mentioned_books = hs_extract_book_mentions($note_text);

	// Delete existing mentions for this note
	$wpdb->delete($mentions_table, array('note_id' => intval($note_id)), array('%d'));

	// Insert new mentions
	foreach ($mentioned_books as $mentioned_book_id) {
		$wpdb->insert(
			$mentions_table,
			array(
				'note_id' => intval($note_id),
				'mentioned_book_id' => intval($mentioned_book_id)
			),
			array('%d', '%d')
		);
	}

	return count($mentioned_books);
}


/**
 * Get notes that mention a specific book
 * Returns public notes that mention the given book
 */
function hs_get_notes_mentioning_book($book_id, $include_private = false, $user_id = null) {
	global $wpdb;

	$notes_table = $wpdb->prefix . 'hs_book_notes';
	$mentions_table = $wpdb->prefix . 'hs_note_book_mentions';

	$where_clauses = array(
		$wpdb->prepare('m.mentioned_book_id = %d', intval($book_id))
	);

	// Filter by visibility
	if (!$include_private) {
		$where_clauses[] = 'n.is_public = 1';
	} elseif ($user_id) {
		$where_clauses[] = $wpdb->prepare(
			'(n.is_public = 1 OR n.user_id = %d)',
			intval($user_id)
		);
	}

	$where = implode(' AND ', $where_clauses);

	$query = $wpdb->prepare(
		"SELECT n.*, u.display_name
		 FROM {$mentions_table} m
		 INNER JOIN {$notes_table} n ON m.note_id = n.id
		 INNER JOIN {$wpdb->users} u ON n.user_id = u.ID
		 WHERE {$where}
		 ORDER BY n.date_created DESC"
	);

	return $wpdb->get_results($query);
}
