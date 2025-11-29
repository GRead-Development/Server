<?php
/**
 * Book Reviews System
 * Handles book reviews and ratings
 */

if (!defined('ABSPATH')) {
    exit;
}

// Create reviews table
function hs_reviews_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        book_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        rating TINYINT(1) UNSIGNED NOT NULL,
        review_text TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY book_user_unique (book_id, user_id),
        INDEX book_id_index (book_id),
        INDEX user_id_index (user_id),
        INDEX rating_index (rating),
        INDEX created_at_index (created_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Create a review
function hs_create_review($book_id, $user_id, $rating, $review_text = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';

    // Validate rating (1-5)
    if ($rating < 1 || $rating > 5) {
        return false;
    }

    // Check if review already exists
    $existing_review = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table_name WHERE book_id = %d AND user_id = %d",
        $book_id, $user_id
    ));

    if ($existing_review) {
        // Update existing review
        $result = $wpdb->update(
            $table_name,
            array(
                'rating' => $rating,
                'review_text' => $review_text,
                'updated_at' => current_time('mysql')
            ),
            array(
                'book_id' => $book_id,
                'user_id' => $user_id
            ),
            array('%d', '%s', '%s'),
            array('%d', '%d')
        );

        return $existing_review->id;
    } else {
        // Insert new review
        $result = $wpdb->insert(
            $table_name,
            array(
                'book_id' => $book_id,
                'user_id' => $user_id,
                'rating' => $rating,
                'review_text' => $review_text
            ),
            array('%d', '%d', '%d', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }
}

// Get review by ID
function hs_get_review($review_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $review_id
    ));
}

// Get user's review for a book
function hs_get_user_review($book_id, $user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE book_id = %d AND user_id = %d",
        $book_id, $user_id
    ));
}

// Get all reviews for a book
function hs_get_book_reviews($book_id, $limit = 50, $offset = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';

    $reviews = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, u.display_name, u.user_login
         FROM $table_name r
         LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
         WHERE r.book_id = %d
         ORDER BY r.created_at DESC
         LIMIT %d OFFSET %d",
        $book_id, $limit, $offset
    ));

    return $reviews;
}

// Get all reviews by a user
function hs_get_user_reviews($user_id, $limit = 50, $offset = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';

    $reviews = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, p.post_title as book_title
         FROM $table_name r
         LEFT JOIN {$wpdb->posts} p ON r.book_id = p.ID
         WHERE r.user_id = %d
         ORDER BY r.created_at DESC
         LIMIT %d OFFSET %d",
        $user_id, $limit, $offset
    ));

    return $reviews;
}

// Update a review
function hs_update_review($review_id, $rating, $review_text) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';

    // Validate rating (1-5)
    if ($rating < 1 || $rating > 5) {
        return false;
    }

    $result = $wpdb->update(
        $table_name,
        array(
            'rating' => $rating,
            'review_text' => $review_text,
            'updated_at' => current_time('mysql')
        ),
        array('id' => $review_id),
        array('%d', '%s', '%s'),
        array('%d')
    );

    return $result !== false;
}

// Delete a review
function hs_delete_review($review_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';

    $result = $wpdb->delete(
        $table_name,
        array('id' => $review_id),
        array('%d')
    );

    return $result !== false;
}

// Get average rating for a book
function hs_get_book_average_rating($book_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';

    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT AVG(rating) as average_rating, COUNT(*) as review_count
         FROM $table_name
         WHERE book_id = %d",
        $book_id
    ));

    return array(
        'average_rating' => $result->average_rating ? round($result->average_rating, 2) : 0,
        'review_count' => (int)$result->review_count
    );
}

// Get rating distribution for a book
function hs_get_book_rating_distribution($book_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_reviews';

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT rating, COUNT(*) as count
         FROM $table_name
         WHERE book_id = %d
         GROUP BY rating
         ORDER BY rating DESC",
        $book_id
    ));

    // Create distribution array with all ratings 1-5
    $distribution = array(
        '5' => 0,
        '4' => 0,
        '3' => 0,
        '2' => 0,
        '1' => 0
    );

    foreach ($results as $result) {
        $distribution[(string)$result->rating] = (int)$result->count;
    }

    return $distribution;
}
