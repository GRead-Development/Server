<?php


function gread_api_submit_inaccuracy_report($request) {
    global $wpdb;

    $book_id = intval($request['book_id']);
    $report_text = sanitize_textarea_field($request['report_text']);
    $user_id = get_current_user_id();

    // Verify book exists
    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return new WP_Error('invalid_book', 'Book not found', array('status' => 404));
    }

    // Insert report
    $result = $wpdb->insert(
        $wpdb->prefix . 'hs_book_reports',
        array(
            'book_id' => $book_id,
            'user_id' => $user_id,
            'report_text' => $report_text,
            'status' => 'pending',
            'date_submitted' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s', '%s')
    );

    if ($result === false) {
        return new WP_Error('db_error', 'Failed to submit report', array('status' => 500));
    }

    return new WP_REST_Response(array(
        'success' => true,
        'report_id' => $wpdb->insert_id,
        'message' => 'Report submitted successfully'
    ), 201);
}

function gread_api_get_user_reports($request) {
    global $wpdb;

    $user_id = get_current_user_id();
    $status = sanitize_text_field($request['status']);

    $where = "r.user_id = %d";
    $params = array($user_id);

    if ($status !== 'all') {
        $where .= " AND r.status = %s";
        $params[] = $status;
    }

    $reports = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, p.post_title as book_title
        FROM {$wpdb->prefix}hs_book_reports r
        LEFT JOIN {$wpdb->posts} p ON r.book_id = p.ID
        WHERE {$where}
        ORDER BY r.date_submitted DESC",
        $params
    ));

    return new WP_REST_Response(array(
        'success' => true,
        'reports' => $reports,
        'count' => count($reports)
    ), 200);
}

