<?php

/**
 * Book Shelf Shortcodes
 *
 * Provides horizontal scrollable book shelves for:
 * - Recently Added to GRead
 * - Being Read (books with recent progress updates)
 * - Top Books (best ratings, prioritizing books with more reviews)
 * - Most Read (books read by most users)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to render a single book card for shelf display
 * Simplified version without interactive elements
 */
function hs_render_shelf_book_card($book_id) {
    $book = get_post($book_id);
    if (!$book) {
        return '';
    }

    $author = get_post_meta($book_id, 'book_author', true);
    $total_pages = (int)get_post_meta($book_id, 'nop', true);
    $cover_url = get_the_post_thumbnail_url($book_id, 'medium');
    $isbn = get_post_meta($book_id, 'book_isbn', true);

    $has_cover = !empty($cover_url);
    if (!$cover_url) {
        $cover_url = '';
    }

    $cover_class = $has_cover ? 'hs-book-cover' : 'hs-book-cover no-cover';

    $html = '<div class="hs-shelf-book-card" data-book-id="' . esc_attr($book_id) . '" data-isbn="' . esc_attr($isbn) . '">';
    $html .= '<a href="' . esc_url(get_permalink($book_id)) . '" class="hs-shelf-book-link">';
    $html .= '<div class="' . esc_attr($cover_class) . '" style="background-image: url(' . esc_url($cover_url) . ');">';
    $html .= '<div class="hs-book-cover-overlay">';
    $html .= '<h3 class="hs-book-title">' . esc_html($book->post_title) . '</h3>';
    $html .= '<p class="hs-book-author">By: ' . esc_html($author) . '</p>';
    $html .= '</div>'; // .hs-book-cover-overlay
    $html .= '</div>'; // .hs-book-cover
    $html .= '</a>';
    $html .= '</div>'; // .hs-shelf-book-card

    return $html;
}

/**
 * Shortcode: [recently_added_books limit="20"]
 * Displays recently added books to GRead in a horizontal scrollable shelf
 */
function hs_recently_added_books_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 20,
    ), $atts, 'recently_added_books');

    $limit = absint($atts['limit']);
    if ($limit <= 0 || $limit > 100) {
        $limit = 20; // Enforce reasonable limit
    }

    // Query recently published books
    $query_args = array(
        'post_type' => 'book',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true, // Performance optimization
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
    );

    $books_query = new WP_Query($query_args);

    if (!$books_query->have_posts()) {
        return '<p>No books found.</p>';
    }

    ob_start();
    ?>
    <div class="hs-book-shelf-container">
        <h2 class="hs-shelf-title">Recently Added to GRead</h2>
        <div class="hs-book-shelf">
            <?php
            while ($books_query->have_posts()) {
                $books_query->the_post();
                echo hs_render_shelf_book_card(get_the_ID());
            }
            wp_reset_postdata();
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('recently_added_books', 'hs_recently_added_books_shortcode');

/**
 * Shortcode: [being_read_books limit="20" days="7"]
 * Displays books that users have recently updated progress in
 */
function hs_being_read_books_shortcode($atts) {
    global $wpdb;

    $atts = shortcode_atts(array(
        'limit' => 20,
        'days' => 7, // Look at progress updates in last N days
    ), $atts, 'being_read_books');

    $limit = absint($atts['limit']);
    if ($limit <= 0 || $limit > 100) {
        $limit = 20;
    }

    $days = absint($atts['days']);
    if ($days <= 0 || $days > 365) {
        $days = 7;
    }

    $user_books_table = $wpdb->prefix . 'user_books';

    // Get books with recent progress updates
    // Group by book_id and count unique users, order by most recent update
    $sql = $wpdb->prepare(
        "SELECT book_id, MAX(date_updated) as recent_update, COUNT(DISTINCT user_id) as reader_count
        FROM {$user_books_table}
        WHERE date_updated >= DATE_SUB(NOW(), INTERVAL %d DAY)
        AND status = 'reading'
        GROUP BY book_id
        ORDER BY recent_update DESC
        LIMIT %d",
        $days,
        $limit
    );

    $results = $wpdb->get_results($sql);

    if (empty($results)) {
        return '<p>No books are currently being read.</p>';
    }

    ob_start();
    ?>
    <div class="hs-book-shelf-container">
        <h2 class="hs-shelf-title">Being Read</h2>
        <div class="hs-book-shelf">
            <?php
            foreach ($results as $row) {
                echo hs_render_shelf_book_card($row->book_id);
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('being_read_books', 'hs_being_read_books_shortcode');

/**
 * Shortcode: [top_rated_books limit="20" min_reviews="3"]
 * Displays top-rated books, prioritizing books with more reviews
 */
function hs_top_rated_books_shortcode($atts) {
    global $wpdb;

    $atts = shortcode_atts(array(
        'limit' => 20,
        'min_reviews' => 3, // Minimum number of reviews to be considered
    ), $atts, 'top_rated_books');

    $limit = absint($atts['limit']);
    if ($limit <= 0 || $limit > 100) {
        $limit = 20;
    }

    $min_reviews = absint($atts['min_reviews']);
    if ($min_reviews < 1) {
        $min_reviews = 1;
    }

    $reviews_table = $wpdb->prefix . 'hs_book_reviews';

    // Get books with best average ratings, weighted by review count
    // Using a simple weighted score: (avg_rating * review_count)
    // This prioritizes books with more reviews
    $sql = $wpdb->prepare(
        "SELECT book_id,
                AVG(rating) as avg_rating,
                COUNT(*) as review_count,
                (AVG(rating) * LOG(COUNT(*) + 1)) as weighted_score
        FROM {$reviews_table}
        WHERE rating IS NOT NULL
        GROUP BY book_id
        HAVING review_count >= %d
        ORDER BY weighted_score DESC, review_count DESC
        LIMIT %d",
        $min_reviews,
        $limit
    );

    $results = $wpdb->get_results($sql);

    if (empty($results)) {
        return '<p>No top-rated books found yet.</p>';
    }

    ob_start();
    ?>
    <div class="hs-book-shelf-container">
        <h2 class="hs-shelf-title">Top Books</h2>
        <div class="hs-book-shelf">
            <?php
            foreach ($results as $row) {
                echo hs_render_shelf_book_card($row->book_id);
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('top_rated_books', 'hs_top_rated_books_shortcode');

/**
 * Shortcode: [most_read_books limit="20"]
 * Displays books that have been read by the most users
 */
function hs_most_read_books_shortcode($atts) {
    global $wpdb;

    $atts = shortcode_atts(array(
        'limit' => 20,
    ), $atts, 'most_read_books');

    $limit = absint($atts['limit']);
    if ($limit <= 0 || $limit > 100) {
        $limit = 20;
    }

    $user_books_table = $wpdb->prefix . 'user_books';

    // Get books read by most users (count distinct users per book)
    $sql = $wpdb->prepare(
        "SELECT book_id, COUNT(DISTINCT user_id) as reader_count
        FROM {$user_books_table}
        GROUP BY book_id
        ORDER BY reader_count DESC
        LIMIT %d",
        $limit
    );

    $results = $wpdb->get_results($sql);

    if (empty($results)) {
        return '<p>No books have been read yet.</p>';
    }

    ob_start();
    ?>
    <div class="hs-book-shelf-container">
        <h2 class="hs-shelf-title">Most Read</h2>
        <div class="hs-book-shelf">
            <?php
            foreach ($results as $row) {
                echo hs_render_shelf_book_card($row->book_id);
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('most_read_books', 'hs_most_read_books_shortcode');

/**
 * Enqueue CSS for book shelves
 */
function hs_book_shelves_enqueue_assets() {
    global $post;
    if (is_a($post, 'WP_Post') && (
        has_shortcode($post->post_content, 'recently_added_books') ||
        has_shortcode($post->post_content, 'being_read_books') ||
        has_shortcode($post->post_content, 'top_rated_books') ||
        has_shortcode($post->post_content, 'most_read_books')
    )) {
        wp_enqueue_style('hs-style');
    }
}
add_action('wp_enqueue_scripts', 'hs_book_shelves_enqueue_assets');
