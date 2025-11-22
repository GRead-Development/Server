<?php

/**
 * Book Auditor
 * Administrative tool for marking books as audited/verified
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Create the book audit table on plugin activation
 */
function hs_book_auditor_create_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'hs_book_audit';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
		id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
		book_id BIGINT(20) UNSIGNED NOT NULL,
		audited TINYINT(1) NOT NULL DEFAULT 1,
		date_audited DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY book_id_unique (book_id),
		INDEX (audited)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

/**
 * Add the book auditor page to the admin menu
 */
function hs_book_auditor_add_admin_page() {
	add_menu_page(
		'Chimera',
		'Chimera',
		'manage_options',
		'hs-book-auditor',
		'hs_book_auditor_admin_page_html',
		'dashicons-yes-alt',
		28
	);
}
add_action('admin_menu', 'hs_book_auditor_add_admin_page');

/**
 * Render the admin page
 */
function hs_book_auditor_admin_page_html() {
	// Security check
	if (!current_user_can('manage_options')) {
		wp_die('You are not an admin! Buzz off!');
	}

	global $wpdb;
	$audit_table = $wpdb->prefix . 'hs_book_audit';

	// Ensure the table exists
	hs_book_auditor_create_table();

	// Enqueue scripts and styles
	wp_enqueue_script('jquery');

	// Get the base plugin URL from this file's location
	$base_url = plugin_dir_url(__FILE__) . '../../';

	wp_enqueue_script(
		'hs-book-auditor-admin',
		$base_url . 'js/admin/chimera.js',
		['jquery'],
		'1.0.0',
		true
	);
	wp_enqueue_style(
		'hs-book-auditor-admin',
		$base_url . 'css/admin/chimera.css',
		[],
		'1.0.0'
	);

	// Localize script for AJAX and Google Books API
	// Try to get API key from constant (wp-config.php) first, then from option
	$google_books_api_key = '';
	if (defined('HS_GOOGLE_BOOKS_API_KEY')) {
		$google_books_api_key = HS_GOOGLE_BOOKS_API_KEY;
	} else {
		$google_books_api_key = get_option('hs_google_books_api_key', '');
	}

	wp_localize_script(
		'hs-book-auditor-admin',
		'hsBookAuditor',
		[
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('hs_book_auditor_nonce'),
			'googleBooksApiKey' => $google_books_api_key,
		]
	);

	// Inline script to verify everything is loaded
	wp_add_inline_script('hs-book-auditor-admin', 'console.log("Book Auditor script loaded");');
	wp_add_inline_script('jquery', 'console.log("jQuery available");');

	// Query: Get all unaudited books
	// A book is unaudited if it doesn't have an entry in the audit table
	// OR if the audit entry has audited = 0
	$unaudited_books = $wpdb->get_results("
		SELECT DISTINCT p.ID, p.post_title
		FROM {$wpdb->posts} p
		LEFT JOIN {$audit_table} a ON p.ID = a.book_id AND a.audited = 1
		WHERE p.post_type = 'book'
		AND p.post_status = 'publish'
		AND a.book_id IS NULL
		ORDER BY p.post_date DESC
		LIMIT 500
	");

	// Now get the metadata for these books
	if (!empty($unaudited_books)) {
		foreach ($unaudited_books as $book) {
			$book->book_author = get_post_meta($book->ID, 'book_author', true);
			$book->book_isbn = get_post_meta($book->ID, 'book_isbn', true);
			$book->book_pages = get_post_meta($book->ID, 'nop', true);
		}
	}

	// Get total counts
	$total_books = intval($wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'book' AND post_status = 'publish'"));
	$audited_books = intval($wpdb->get_var("SELECT COUNT(DISTINCT book_id) FROM {$audit_table} WHERE audited = 1"));
	$unaudited_count = max(0, $total_books - $audited_books);

	?>

	<div class="wrap">
		<h1>Book Auditor</h1>
		<p>Mark books as audited/verified. Books shown below have not yet been audited.</p>

		<!-- Statistics Section -->
		<div class="hs-book-auditor-stats">
			<div class="stat-box">
				<div class="stat-number"><?php echo intval($total_books); ?></div>
				<div class="stat-label">Total Books</div>
			</div>
			<div class="stat-box">
				<div class="stat-number"><?php echo intval($audited_books); ?></div>
				<div class="stat-label">Audited Books</div>
			</div>
			<div class="stat-box">
				<div class="stat-number"><?php echo intval($unaudited_count); ?></div>
				<div class="stat-label">Unaudited Books</div>
			</div>
		</div>

		<!-- Unaudited Books Table -->
		<h2>Unaudited Books</h2>

		<!-- Bulk Actions Toolbar -->
		<div id="hs-bulk-actions-toolbar" style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; display: none;">
			<label for="hs-bulk-action-select" style="margin-right: 15px;">Bulk Action:</label>
			<select id="hs-bulk-action-select" style="padding: 5px 10px; margin-right: 10px;">
				<option value="">— Select Action —</option>
				<option value="fill_titles">Fill Missing Titles</option>
				<option value="fill_authors">Fill Missing Authors</option>
				<option value="fill_isbns">Fill Missing ISBNs</option>
				<option value="fill_pages">Fill Missing Page Counts</option>
			</select>
			<button id="hs-bulk-action-btn" class="button button-primary">Apply</button>
			<button id="hs-bulk-clear-selection-btn" class="button" style="margin-left: 10px;">Clear Selection</button>
			<span id="hs-bulk-selection-count" style="margin-left: 15px; font-style: italic;"></span>
			<div id="hs-bulk-progress" style="margin-top: 10px; display: none;">
				<progress id="hs-bulk-progress-bar" value="0" max="100" style="width: 300px; vertical-align: middle;"></progress>
				<span id="hs-bulk-progress-text" style="margin-left: 10px;"></span>
			</div>
		</div>

		<?php if (empty($unaudited_books)): ?>
			<div class="notice notice-success">
				<p>All books have been audited! Great job!</p>
			</div>
		<?php else: ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 50px;"><input type="checkbox" id="hs-select-all-books"></th>
					<th>Book Title</th>
						<th>Author</th>
						<th>ISBN</th>
						<th>Pages</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($unaudited_books as $book): ?>
						<tr class="hs-book-row" data-book-id="<?php echo intval($book->ID); ?>">
							<td style="text-align: center;"><input type="checkbox" class="hs-book-select-checkbox" value="<?php echo intval($book->ID); ?>"></td>
							<td class="hs-editable-field" data-field="post_title" data-book-id="<?php echo intval($book->ID); ?>">
								<span class="hs-field-display">
									<strong><?php echo esc_html($book->post_title); ?></strong>
									<a href="<?php echo esc_url(get_permalink($book->ID)); ?>" target="_blank" class="hs-view-link" style="margin-left: 10px; font-weight: normal; font-size: 12px;">[view]</a>
								</span>
								<input type="text" class="hs-field-input" value="<?php echo esc_attr($book->post_title); ?>" style="display: none;">
								<div class="hs-field-actions" style="display: none;">
									<button class="button button-small hs-save-field">Save</button>
									<button class="button button-small hs-cancel-field">Cancel</button>
									<button class="button button-small hs-api-search-field">🔍 API</button>
								</div>
							</td>
							<td class="hs-editable-field" data-field="book_author" data-book-id="<?php echo intval($book->ID); ?>">
								<span class="hs-field-display"><?php echo esc_html($book->book_author ?: 'Unknown'); ?></span>
								<input type="text" class="hs-field-input" value="<?php echo esc_attr($book->book_author ?: ''); ?>" style="display: none;">
								<div class="hs-field-actions" style="display: none;">
									<button class="button button-small hs-save-field">Save</button>
									<button class="button button-small hs-cancel-field">Cancel</button>
									<button class="button button-small hs-api-search-field">🔍 API</button>
								</div>
							</td>
							<td class="hs-editable-field" data-field="book_isbn" data-book-id="<?php echo intval($book->ID); ?>">
								<span class="hs-field-display"><?php echo esc_html($book->book_isbn ?: '—'); ?></span>
								<input type="text" class="hs-field-input" value="<?php echo esc_attr($book->book_isbn ?: ''); ?>" style="display: none;">
								<div class="hs-field-actions" style="display: none;">
									<button class="button button-small hs-save-field">Save</button>
									<button class="button button-small hs-cancel-field">Cancel</button>
									<button class="button button-small hs-api-search-field">🔍 API</button>
								</div>
							</td>
							<td class="hs-editable-field" data-field="nop" data-book-id="<?php echo intval($book->ID); ?>">
								<span class="hs-field-display"><?php echo esc_html($book->book_pages ?: '—'); ?></span>
								<input type="number" class="hs-field-input" value="<?php echo esc_attr($book->book_pages ?: ''); ?>" style="display: none;">
								<div class="hs-field-actions" style="display: none;">
									<button class="button button-small hs-save-field">Save</button>
									<button class="button button-small hs-cancel-field">Cancel</button>
									<button class="button button-small hs-api-search-field">🔍 API</button>
								</div>
							</td>
							<td>
								<button class="button button-primary hs-audit-book-btn" data-book-id="<?php echo intval($book->ID); ?>">
									✓ Audited
								</button>
								<button class="button button-link-delete hs-delete-book-btn" data-book-id="<?php echo intval($book->ID); ?>" style="margin-left: 10px; color: #dc3545;">
									🗑 Delete
								</button>
								<span class="hs-audit-spinner spinner" style="float: none; visibility: hidden;"></span>
								<span class="hs-audit-feedback" style="margin-left: 10px; font-style: italic;"></span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- Delete Confirmation Modal -->
	<div id="hs-delete-confirm-modal" class="hs-api-search-modal" style="display: none;">
		<div class="hs-modal-content" style="max-width: 400px;">
			<div class="hs-modal-header">
				<h2>Confirm Delete</h2>
				<button class="hs-modal-close hs-delete-modal-close">✕</button>
			</div>
			<div class="hs-modal-body">
				<p style="margin: 20px 0;">Are you sure you want to delete this book? This action cannot be undone.</p>
				<div style="text-align: right;">
					<button id="hs-delete-confirm-yes" class="button button-primary" style="background-color: #dc3545; border-color: #dc3545;">Yes, Delete</button>
					<button id="hs-delete-confirm-no" class="button" style="margin-left: 10px;">Cancel</button>
				</div>
			</div>
		</div>
	</div>

	<!-- API Search Modal -->
	<div id="hs-api-search-modal" class="hs-api-search-modal" style="display: none;">
		<div class="hs-modal-content">
			<div class="hs-modal-header">
				<h2 id="hs-api-modal-title">Searching...</h2>
				<button class="hs-modal-close">✕</button>
			</div>
			<div class="hs-modal-body">
				<div id="hs-api-search-spinner" class="spinner" style="display: none; float: none; margin: 20px auto;"></div>
				<div id="hs-api-search-message" class="hs-api-search-message" style="margin-top: 15px; display: none;"></div>
			</div>
		</div>
	</div>

	<style>
		.hs-book-auditor-stats {
			display: flex;
			gap: 20px;
			margin: 20px 0;
		}

		.stat-box {
			background: #fff;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			padding: 20px;
			text-align: center;
			flex: 1;
			max-width: 200px;
		}

		.stat-number {
			font-size: 32px;
			font-weight: bold;
			color: #0073aa;
		}

		.stat-label {
			color: #666;
			font-size: 14px;
			margin-top: 5px;
		}

		.hs-book-row {
			transition: background-color 0.3s ease;
		}

		.hs-book-row.hs-audited {
			background-color: #d4edda !important;
		}

		.hs-audit-spinner {
			display: inline-block;
		}

		.hs-audit-feedback {
			color: #28a745;
		}

		.hs-audit-feedback.error {
			color: #dc3545;
		}
	</style>

	<?php
}

/**
 * AJAX handler to mark a book as audited
 */
function hs_ajax_mark_book_audited() {
	check_ajax_referer('hs_book_auditor_nonce', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;

	if (!$book_id) {
		wp_send_json_error(['message' => 'Invalid book ID']);
	}

	// Verify the book exists and is published
	$book = get_post($book_id);
	if (!$book || $book->post_type !== 'book' || $book->post_status !== 'publish') {
		wp_send_json_error(['message' => 'Invalid book']);
	}

	global $wpdb;
	$audit_table = $wpdb->prefix . 'hs_book_audit';

	// Insert or update the audit record
	$result = $wpdb->replace(
		$audit_table,
		[
			'book_id' => $book_id,
			'audited' => 1,
			'date_audited' => current_time('mysql'),
		],
		['%d', '%d', '%s']
	);

	if ($result !== false) {
		wp_send_json_success([
			'message' => 'Book marked as audited successfully',
			'book_id' => $book_id,
		]);
	} else {
		wp_send_json_error(['message' => 'Failed to mark book as audited']);
	}
}
add_action('wp_ajax_hs_mark_book_audited', 'hs_ajax_mark_book_audited');

/**
 * AJAX handler to save book field changes
 */
function hs_ajax_save_book_field() {
	check_ajax_referer('hs_book_auditor_nonce', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
	$field = isset($_POST['field']) ? sanitize_key($_POST['field']) : '';
	$value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';

	if (!$book_id || !$field) {
		wp_send_json_error(['message' => 'Invalid request']);
	}

	// Verify the book exists and is published
	$book = get_post($book_id);
	if (!$book || $book->post_type !== 'book' || $book->post_status !== 'publish') {
		wp_send_json_error(['message' => 'Invalid book']);
	}

	// Whitelist allowed fields
	$allowed_fields = ['book_author', 'book_isbn', 'nop', 'post_title'];
	if (!in_array($field, $allowed_fields)) {
		wp_send_json_error(['message' => 'Invalid field']);
	}

	// Handle numeric field (nop = number of pages)
	if ($field === 'nop') {
		$value = intval($value);
	}

	// Update the post title or post metadata
	if ($field === 'post_title') {
		// Update the post title itself
		$update_result = wp_update_post([
			'ID' => $book_id,
			'post_title' => $value,
		]);
		if ($update_result === 0 || is_wp_error($update_result)) {
			wp_send_json_error(['message' => 'Failed to update title']);
		}
	} else {
		// Update the post metadata
		update_post_meta($book_id, $field, $value);
	}

	wp_send_json_success([
		'message' => 'Field updated successfully',
		'book_id' => $book_id,
		'field' => $field,
		'value' => $value,
	]);
}
add_action('wp_ajax_hs_save_book_field', 'hs_ajax_save_book_field');

/**
 * AJAX handler to delete a book
 */
function hs_ajax_delete_book() {
	check_ajax_referer('hs_book_auditor_nonce', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;

	if (!$book_id) {
		wp_send_json_error(['message' => 'Invalid book ID']);
	}

	// Verify the book exists and is published
	$book = get_post($book_id);
	if (!$book || $book->post_type !== 'book' || $book->post_status !== 'publish') {
		wp_send_json_error(['message' => 'Invalid book']);
	}

	// Move the book to trash (soft delete) instead of permanently deleting
	$delete_result = wp_trash_post($book_id);

	if ($delete_result !== false) {
		// Remove the audit record for this book
		global $wpdb;
		$audit_table = $wpdb->prefix . 'hs_book_audit';
		$wpdb->delete(
			$audit_table,
			['book_id' => $book_id],
			['%d']
		);

		wp_send_json_success([
			'message' => 'Book deleted successfully',
			'book_id' => $book_id,
		]);
	} else {
		wp_send_json_error(['message' => 'Failed to delete book']);
	}
}
add_action('wp_ajax_hs_delete_book', 'hs_ajax_delete_book');
