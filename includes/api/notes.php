<?php


function gread_api_get_book_notes($request) {
    global $wpdb;

    $book_id = intval($request['book_id']);
    $type = sanitize_text_field($request['type']);
    $current_user_id = get_current_user_id();

    // Verify book exists
    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return new WP_Error('invalid_book', 'Book not found', array('status' => 404));
    }

    $notes = array();

    if ($type === 'public') {
        $notes = hs_get_public_book_notes($book_id);
    } elseif ($type === 'user') {
        if (!$current_user_id) {
            return new WP_Error('unauthorized', 'Authentication required', array('status' => 401));
        }
        $notes = hs_get_user_book_notes($current_user_id, $book_id);
    } elseif ($type === 'all') {
        if ($current_user_id) {
            $public_notes = hs_get_public_book_notes($book_id);
            $user_notes = hs_get_user_book_notes($current_user_id, $book_id);
            $notes = array_merge($user_notes, $public_notes);

            usort($notes, function($a, $b) {
                $page_a = $a->page_start ?? $a->page_number ?? 0;
                $page_b = $b->page_start ?? $b->page_number ?? 0;
                return $page_a - $page_b;
            });
        } else {
            $notes = hs_get_public_book_notes($book_id);
        }
    }

    return new WP_REST_Response(array(
        'success' => true,
        'notes' => $notes,
        'count' => count($notes)
    ), 200);
}

function gread_api_get_note($request) {
    global $wpdb;

    $note_id = intval($request['note_id']);
    $current_user_id = get_current_user_id();

    $note = hs_get_book_note($note_id);

    if (!$note) {
        return new WP_Error('not_found', 'Note not found', array('status' => 404));
    }

    if (!$note->is_public && (!$current_user_id || $note->user_id != $current_user_id)) {
        return new WP_Error('forbidden', 'You do not have permission to view this note', array('status' => 403));
    }

    return new WP_REST_Response(array(
        'success' => true,
        'note' => $note
    ), 200);
}

function gread_api_create_note($request) {
    $book_id = intval($request['book_id']);
    $note_text = sanitize_textarea_field($request['note_text']);
    $page_number = isset($request['page_number']) ? intval($request['page_number']) : null;
    $is_public = filter_var($request['is_public'], FILTER_VALIDATE_BOOLEAN);
    $user_id = get_current_user_id();

    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return new WP_Error('invalid_book', 'Book not found', array('status' => 404));
    }

    if (empty(trim($note_text))) {
        return new WP_Error('invalid_note', 'Note text cannot be empty', array('status' => 400));
    }

    $note_id = hs_create_book_note($user_id, $book_id, $note_text, $page_number, null, null, $is_public);

    if (!$note_id) {
        return new WP_Error('db_error', 'Failed to create note', array('status' => 500));
    }

    $note = hs_get_book_note($note_id);

    return new WP_REST_Response(array(
        'success' => true,
        'note' => $note,
        'message' => 'Note created successfully'
    ), 201);
}

function gread_api_update_note($request) {
    global $wpdb;

    $note_id = intval($request['note_id']);
    $user_id = get_current_user_id();

    $note = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_book_notes WHERE id = %d",
        $note_id
    ));

    if (!$note) {
        return new WP_Error('not_found', 'Note not found', array('status' => 404));
    }

    if ($note->user_id != $user_id) {
        return new WP_Error('forbidden', 'You do not have permission to edit this note', array('status' => 403));
    }

    $update_data = array('date_updated' => current_time('mysql'));
    $update_format = array('%s');

    if (isset($request['note_text'])) {
        $update_data['note_text'] = sanitize_textarea_field($request['note_text']);
        $update_format[] = '%s';
    }

    if (isset($request['page_number'])) {
        $update_data['page_number'] = $request['page_number'] ? intval($request['page_number']) : null;
        $update_format[] = '%d';
    }

    if (isset($request['is_public'])) {
        $update_data['is_public'] = filter_var($request['is_public'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $update_format[] = '%d';
    }

    $result = $wpdb->update(
        $wpdb->prefix . 'hs_book_notes',
        $update_data,
        array('id' => $note_id),
        $update_format,
        array('%d')
    );

    if ($result === false) {
        return new WP_Error('db_error', 'Failed to update note', array('status' => 500));
    }

    $updated_note = hs_get_book_note($note_id);

    return new WP_REST_Response(array(
        'success' => true,
        'note' => $updated_note,
        'message' => 'Note updated successfully'
    ), 200);
}

function gread_api_delete_note($request) {
    global $wpdb;

    $note_id = intval($request['note_id']);
    $user_id = get_current_user_id();

    $note = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_book_notes WHERE id = %d",
        $note_id
    ));

    if (!$note) {
        return new WP_Error('not_found', 'Note not found', array('status' => 404));
    }

    if ($note->user_id != $user_id) {
        return new WP_Error('forbidden', 'You do not have permission to delete this note', array('status' => 403));
    }

    $result = hs_delete_book_note($note_id);

    if (!$result) {
        return new WP_Error('db_error', 'Failed to delete note', array('status' => 500));
    }

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Note deleted successfully'
    ), 200);
}

function gread_api_like_note($request) {
    global $wpdb;

    $note_id = intval($request['note_id']);
    $user_id = get_current_user_id();

    $note = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hs_book_notes WHERE id = %d",
        $note_id
    ));

    if (!$note) {
        return new WP_Error('not_found', 'Note not found', array('status' => 404));
    }

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hs_note_likes
        WHERE user_id = %d AND note_id = %d",
        $user_id, $note_id
    ));

    if ($existing > 0) {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Note already liked'
        ), 200);
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'hs_note_likes',
        array(
            'user_id' => $user_id,
            'note_id' => $note_id,
            'date_liked' => current_time('mysql')
        ),
        array('%d', '%d', '%s')
    );

    if ($result === false) {
        return new WP_Error('db_error', 'Failed to like note', array('status' => 500));
    }

    $like_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hs_note_likes WHERE note_id = %d",
        $note_id
    ));

    return new WP_REST_Response(array(
        'success' => true,
        'like_count' => intval($like_count),
        'message' => 'Note liked successfully'
    ), 200);
}

function gread_api_unlike_note($request) {
    global $wpdb;

    $note_id = intval($request['note_id']);
    $user_id = get_current_user_id();

    $result = $wpdb->delete(
        $wpdb->prefix . 'hs_note_likes',
        array(
            'user_id' => $user_id,
            'note_id' => $note_id
        ),
        array('%d', '%d')
    );

    $like_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hs_note_likes WHERE note_id = %d",
        $note_id
    ));

    return new WP_REST_Response(array(
        'success' => true,
        'like_count' => intval($like_count),
        'message' => 'Note unliked successfully'
    ), 200);
}

