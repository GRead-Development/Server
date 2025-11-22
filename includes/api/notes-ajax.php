<?php
/**
 * Notes AJAX Handlers
 *
 * Handles all AJAX requests for notes operations:
 * - Get user notes
 * - Get public notes
 * - Get single note
 * - Create note
 * - Update note
 * - Delete note
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get user's notes for a book
 */
function hs_ajax_get_user_notes() {
    check_ajax_referer('hs_notes_action', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('User not authenticated');
    }

    $book_id = intval($_POST['book_id']);
    $user_id = get_current_user_id();

    if (!$book_id) {
        wp_send_json_error('Invalid book ID');
    }

    $notes = hs_get_user_book_notes($user_id, $book_id);

    wp_send_json_success($notes);
}
add_action('wp_ajax_hs_get_user_notes', 'hs_ajax_get_user_notes');

/**
 * Get public notes for a book
 */
function hs_ajax_get_public_notes() {
    check_ajax_referer('hs_notes_action', 'nonce');

    $book_id = intval($_POST['book_id']);

    if (!$book_id) {
        wp_send_json_error('Invalid book ID');
    }

    $notes = hs_get_public_book_notes($book_id);

    wp_send_json_success($notes);
}
add_action('wp_ajax_hs_get_public_notes', 'hs_ajax_get_public_notes');

/**
 * Get a single note
 */
function hs_ajax_get_note() {
    check_ajax_referer('hs_notes_action', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('User not authenticated');
    }

    $note_id = intval($_POST['note_id']);
    $user_id = get_current_user_id();

    if (!$note_id) {
        wp_send_json_error('Invalid note ID');
    }

    $note = hs_get_book_note($note_id);

    if (!$note) {
        wp_send_json_error('Note not found');
    }

    // Check if user owns this note
    if ($note->user_id != $user_id && !current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    wp_send_json_success($note);
}
add_action('wp_ajax_hs_get_note', 'hs_ajax_get_note');

/**
 * Create a new note
 */
function hs_ajax_create_note() {
    check_ajax_referer('hs_notes_action', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('User not authenticated');
    }

    $user_id = get_current_user_id();
    $book_id = intval($_POST['book_id']);
    $note_text = isset($_POST['note_text']) ? sanitize_textarea_field($_POST['note_text']) : '';
    $page_number = isset($_POST['page_number']) && $_POST['page_number'] ? intval($_POST['page_number']) : null;
    $is_public = isset($_POST['is_public']) && $_POST['is_public'] ? 1 : 0;

    if (!$book_id || !$note_text) {
        wp_send_json_error('Book ID and note text are required');
    }

    // Check if book exists
    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        wp_send_json_error('Book not found');
    }

    $note_id = hs_create_book_note($user_id, $book_id, $note_text, $page_number, null, null, $is_public);

    if ($note_id) {
        wp_send_json_success(array('note_id' => $note_id));
    } else {
    	global $wpdb;
		
		error_log('Could not create note. WPDB Error: ' . $wpdb -> last_error);
		error_log('Note data - User: ' . $user_id  . ', Book: ' . $book_id . ', Text: ' . $note_text . ', Page: ' . $page_number . ', Public: ' . $is_public);
		wp_send_json_error('Could not create note.');
	}
}
add_action('wp_ajax_hs_create_note', 'hs_ajax_create_note');

/**
 * Update an existing note
 */
function hs_ajax_update_note() {
    check_ajax_referer('hs_notes_action', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('User not authenticated');
    }

    $note_id = intval($_POST['note_id']);
    $note_text = isset($_POST['note_text']) ? sanitize_textarea_field($_POST['note_text']) : '';
    $page_number = isset($_POST['page_number']) && $_POST['page_number'] ? intval($_POST['page_number']) : null;
    $is_public = isset($_POST['is_public']) && $_POST['is_public'] ? 1 : 0;

    if (!$note_id || !$note_text) {
        wp_send_json_error('Note ID and note text are required');
    }

    $result = hs_update_book_note($note_id, $note_text, $page_number, $is_public);

    if ($result !== false) {
        wp_send_json_success(array('note_id' => $note_id));
    } else {
        wp_send_json_error('Failed to update note');
    }
}
add_action('wp_ajax_hs_update_note', 'hs_ajax_update_note');

/**
 * Delete a note
 */
function hs_ajax_delete_note() {
    check_ajax_referer('hs_notes_action', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('User not authenticated');
    }

    $note_id = intval($_POST['note_id']);

    if (!$note_id) {
        wp_send_json_error('Invalid note ID');
    }

    $result = hs_delete_book_note($note_id);

    if ($result !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete note');
    }
}
add_action('wp_ajax_hs_delete_note', 'hs_ajax_delete_note');
