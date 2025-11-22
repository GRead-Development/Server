<?php
// Provides HotSoup with a way to track books across different printings.
// The goal is to merge multiple printings of a book into a single post.


// Activate the GID system
function hs_gid_activate()
{
	global $wpdb;

	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix}hs_gid
	(
		id INT PRIMARY KEY AUTO_INCREMENT,
		post_id INT UNIQUE,
		gid INT,
		merged_by INT,
		merge_reason TEXT,
		date_merged DATETIME,
		is_canonical TINYINT(1) DEFAULT 0,
		INDEX (gid),
		INDEX (post_id)
	)");

	// Duplicate reports
	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix}hs_duplicate_reports (
		id INT PRIMARY KEY AUTO_INCREMENT,
		reporter_id INT,
		primary_book_id INT,
		reason TEXT,
		status ENUM('pending', 'merged', 'rejected') DEFAULT 'pending',
		date_reported DATETIME,
		reviewed_by INT,
		INDEX (status),
		INDEX (primary_book_id)
		)");

	// ISBN tracking table for having multiple ISBNs per book
	$wpdb -> query( "CREATE TABLE IF NOT EXISTS {$wpdb -> prefix}hs_book_isbns (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		gid INT NOT NULL,
		post_id BIGINT(20) UNSIGNED NOT NULL,
		isbn VARCHAR(13) NOT NULL,
		is_primary TINYINT(1) DEFAULT 0,
		edition VARCHAR(255) DEFAULT '',
		publication_year INT DEFAULT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY isbn (isbn),
		KEY gid (gid),
		KEY post_id (post_id),
		KEY is_primary (is_primary)
	)");

	// User ISBN preference table
	$wpdb -> query("CREATE TABLE IF NOT EXISTS {$wpdb -> prefix}hs_user_book_isbns (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT(20) UNSIGNED NOT NULL,
		book_id BIGINT(20) UNSIGNED NOT NULL,
		isbn VARCHAR(13) NOT NULL,
		selected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY user_book (user_id, book_id),
		KEY user_id (user_id),
		KEY book_id (book_id)
	)");

}


// Get or create GID for book post
function hs_get_or_create_gid($post_id)
{
	global $wpdb;
	$table = $wpdb -> prefix . 'hs_gid';

	// Check if the post has a GID
	$existing = $wpdb -> get_row($wpdb -> prepare(
		"SELECT gid FROM $table WHERE post_id = %d",
		intval($post_id)
	));

	if ($existing)
	{
		return intval($existing -> gid);
	}


	// Create GID by using the post_id from the first book
	$wpdb -> insert($table, array(
		'post_id' => intval($post_id),
		'gid' => intval($post_id),
		'is_canonical' => 1,
		'date_merged' => current_time('mysql')
	));

	return intval($post_id);
}


// Get the GID for a post (doesn't the other function do this?)
function hs_get_gid($post_id)
{
	global $wpdb;
	$table = $wpdb -> prefix . 'hs_gid';

	$result = $wpdb -> get_var($wpdb -> prepare(
		"SELECT gid FROM $table WHERE post_id = %d",
		intval($post_id)
	));

	return $result ? intval($result) : null;
}


// Retrieve post IDs by GID
function hs_get_posts_by_gid($gid)
{
	global $wpdb;
	$table = $wpdb -> prefix . 'hs_gid';

	return $wpdb -> get_col($wpdb -> prepare(
		"SELECT post_id FROM $table WHERE gid = %d",
		intval($gid)
	));
}

// Get canonical post for GID
function hs_get_canonical_post($gid)
{
	global $wpdb;
	$table = $wpdb -> prefix . 'hs_gid';

	$result = $wpdb -> get_var($wpdb -> prepare(
		"SELECT post_id FROM $table WHERE gid = %d AND is_canonical = 1 LIMIT 1",
		intval($gid)
	));

	return $result ? intval($result) : null;
}

function hs_migrate_isbns_to_table()
{
	global $wpdb;

	// Get all published books
	$args = array(
		'post_type' => 'book',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'fields' => 'ids'
	);

	$book_ids = get_posts($args);
	$migrated = 0;
	$skipped = 0;

	foreach ($book_ids as $post_id) {
		// Get the ISBN from ACF field
		$isbn = get_field('book_isbn', $post_id);

		if (empty($isbn)) {
			$skipped++;
			continue;
		}

		// Check if this ISBN already exists in the table
		$exists = $wpdb -> get_var($wpdb -> prepare(
			"SELECT id FROM {$wpdb -> prefix}hs_book_isbns WHERE isbn = %s",
			sanitize_text_field($isbn)
		));

		if ($exists) {
			$skipped++;
			continue;
		}

		// Get or create GID for this book
		$gid = hs_get_or_create_gid($post_id);

		// Get publication year if available
		$year = get_field('publication_year', $post_id);

		// Insert the ISBN as primary
		$result = $wpdb -> insert(
			$wpdb -> prefix . 'hs_book_isbns',
			array(
				'gid' => intval($gid),
				'post_id' => intval($post_id),
				'isbn' => sanitize_text_field($isbn),
				'edition' => '',
				'publication_year' => $year ? intval($year) : null,
				'is_primary' => 1,
				'created_at' => current_time('mysql')
			),
			array('%d', '%d', '%s', '%s', '%d', '%d', '%s')
		);

		if ($result !== false) {
			$migrated++;
		}
	}

	return array(
		'total' => count($book_ids),
		'migrated' => $migrated,
		'skipped' => $skipped
	);
}


// Admin page for migration
function hs_isbn_migration_page()
{
	add_submenu_page(
		'tools.php',
		'ISBN Migration',
		'ISBN Migration',
		'manage_options',
		'hs-isbn-migration',
		'hs_isbn_migration_page_html'
	);
}
add_action('admin_menu', 'hs_isbn_migration_page');


// Render migration page
function hs_isbn_migration_page_html()
{
	if (isset($_GET['hs_migration_status'])) {
		if ($_GET['hs_migration_status'] === 'success') {
			$migrated = isset($_GET['migrated']) ? intval($_GET['migrated']) : 0;
			$skipped = isset($_GET['skipped']) ? intval($_GET['skipped']) : 0;
			echo '<div class="notice notice-success is-dismissible"><p><strong>Success:</strong> Migrated ' . $migrated . ' ISBNs. Skipped ' . $skipped . ' (already migrated or empty).</p></div>';
		}
	}
	?>
	<div class="wrap">
		<h1>ISBN Migration to New System</h1>
		<p>This tool migrates existing ISBNs from the ACF <code>book_isbn</code> field to the new <code>hs_book_isbns</code> table.</p>
		<p>This enables support for <strong>multiple ISBNs per book</strong> and integrates with the GID system.</p>

		<h2>What will this do?</h2>
		<ul>
			<li>Create GID entries for all existing books</li>
			<li>Migrate ISBNs from ACF fields to the new table</li>
			<li>Mark migrated ISBNs as "primary"</li>
			<li>Skip books that already have ISBNs in the new table</li>
			<li>Skip books with empty ISBN fields</li>
		</ul>

		<form method="post" action="">
			<?php wp_nonce_field('hs_migrate_isbns_action', 'hs_migrate_isbns_nonce'); ?>
			<p>
				<button type="submit" name="hs_run_migration" class="button button-primary">Migrate ISBNs</button>
			</p>
		</form>
	</div>
	<?php
}


// Handle migration form submission
function hs_handle_isbn_migration()
{
	if (isset($_POST['hs_run_migration']) && isset($_POST['hs_migrate_isbns_nonce']) && wp_verify_nonce($_POST['hs_migrate_isbns_nonce'], 'hs_migrate_isbns_action') && current_user_can('manage_options')) {

		$result = hs_migrate_isbns_to_table();

		wp_safe_redirect(admin_url('tools.php?page=hs-isbn-migration&hs_migration_status=success&migrated=' . $result['migrated'] . '&skipped=' . $result['skipped']));
		exit;
	}
}
add_action('admin_init', 'hs_handle_isbn_migration');
