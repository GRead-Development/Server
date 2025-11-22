<?php

// REWRITE THIS

/**
 * Frontend Display Functions for Authors and Series
 *
 * Template tags and filters for displaying author and series information
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display clickable author names for a book
 *
 * @param int $book_id Book post ID
 * @param bool $echo Whether to echo or return
 * @param string $separator Separator between multiple authors
 * @return string|void
 */
function hs_the_book_authors($book_id = null, $echo = true, $separator = ', ') {
    if (!$book_id) {
        $book_id = get_the_ID();
    }

    $authors = hs_get_book_authors($book_id);

    if (empty($authors)) {
        // Fallback to old book_author field if no author IDs exist
        $author_string = get_field('book_author', $book_id);
        if ($echo) {
            echo esc_html($author_string);
        }
        return $author_string;
    }

    $author_links = array();
    foreach ($authors as $author) {
        $url = hs_get_author_url($author->id);
        $author_links[] = sprintf(
            '<a href="%s" class="hs-author-link" data-author-id="%d">%s</a>',
            esc_url($url),
            $author->id,
            esc_html($author->name)
        );
    }

    $output = implode($separator, $author_links);

    if ($echo) {
        echo $output;
    }

    return $output;
}

/**
 * Get URL for author page
 *
 * @param int $author_id
 * @return string
 */
function hs_get_author_url($author_id) {
    $author = hs_get_author($author_id);
    if (!$author) {
        return '';
    }

    // You can customize this to use a dedicated author page
    // For now, we'll use the current page with query param
    $author_page_id = get_option('hs_author_page_id');

    if ($author_page_id && get_post($author_page_id)) {
        return add_query_arg('author_id', $author_id, get_permalink($author_page_id));
    }

    // Fallback: use current page or home page
    return add_query_arg('author_id', $author_id, home_url('/'));
}

/**
 * Display series information for a book
 *
 * @param int $book_id Book post ID
 * @param bool $echo Whether to echo or return
 * @return string|void
 */
function hs_the_book_series($book_id = null, $echo = true) {
    if (!$book_id) {
        $book_id = get_the_ID();
    }

    $series_list = hs_get_book_series($book_id);

    if (empty($series_list)) {
        return '';
    }

    $output = '<div class="hs-book-series">';
    foreach ($series_list as $series) {
        $url = hs_get_series_url($series->id);
        $position_text = '';

        if ($series->position) {
            $position_text = ' <span class="hs-series-position">#' . $series->position . '</span>';
        }

        $output .= sprintf(
            '<div class="hs-series-item">Part of: <a href="%s" class="hs-series-link" data-series-id="%d">%s</a>%s</div>',
            esc_url($url),
            $series->id,
            esc_html($series->name),
            $position_text
        );
    }
    $output .= '</div>';

    if ($echo) {
        echo $output;
    }

    return $output;
}

/**
 * Get URL for series page
 *
 * @param int $series_id
 * @return string
 */
function hs_get_series_url($series_id) {
    $series = hs_get_series($series_id);
    if (!$series) {
        return '';
    }

    $series_page_id = get_option('hs_series_page_id');

    if ($series_page_id && get_post($series_page_id)) {
        return add_query_arg('series_id', $series_id, get_permalink($series_page_id));
    }

    return add_query_arg('series_id', $series_id, home_url('/'));
}

/**
 * Add author and series info to book content
 * This filter automatically adds author/series info to book posts
 */
add_filter('the_content', 'hs_add_author_series_to_content');

function hs_add_author_series_to_content($content) {
    if (!is_singular('book')) {
        return $content;
    }

    $book_id = get_the_ID();
    $author_html = '';
    $series_html = '';

    // Get authors
    $authors = hs_get_book_authors($book_id);
    if (!empty($authors)) {
        $author_html .= '<div class="hs-book-authors-display">';
        $author_html .= '<strong>Author' . (count($authors) > 1 ? 's' : '') . ':</strong> ';
        $author_html .= hs_the_book_authors($book_id, false);
        $author_html .= '</div>';
    }

    // Get series
    $series_list = hs_get_book_series($book_id);
    if (!empty($series_list)) {
        $series_html .= hs_the_book_series($book_id, false);
    }

    // Add to top of content
    $meta_html = '';
    if ($author_html || $series_html) {
        $meta_html .= '<div class="hs-book-meta">';
        $meta_html .= $author_html;
        $meta_html .= $series_html;
        $meta_html .= '</div>';
    }

    return $meta_html . $content;
}

/**
 * Add CSS for author and series display
 */
add_action('wp_head', 'hs_author_series_css');

function hs_author_series_css() {
    ?>
    <style>
        .hs-book-meta {
            padding: 20px;
            background: #f9f9f9;
            border-left: 4px solid #2271b1;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .hs-book-authors-display {
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .hs-author-link {
            color: #2271b1;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .hs-author-link:hover {
            color: #135e96;
            text-decoration: underline;
        }
        .hs-book-series {
            margin-top: 10px;
        }
        .hs-series-item {
            margin: 5px 0;
            font-style: italic;
        }
        .hs-series-link {
            color: #2271b1;
            text-decoration: none;
            font-weight: 500;
        }
        .hs-series-link:hover {
            color: #135e96;
            text-decoration: underline;
        }
        .hs-series-position {
            color: #666;
            font-weight: bold;
            font-style: normal;
        }
    </style>
    <?php
}

/**
 * Settings page for author/series pages
 */
add_action('admin_init', 'hs_author_series_settings_init');

function hs_author_series_settings_init() {
    register_setting('reading', 'hs_author_page_id');
    register_setting('reading', 'hs_series_page_id');

    add_settings_section(
        'hs_author_series_section',
        'Author & Series Pages',
        'hs_author_series_section_callback',
        'reading'
    );

    add_settings_field(
        'hs_author_page_id',
        'Author Page',
        'hs_author_page_callback',
        'reading',
        'hs_author_series_section'
    );

    add_settings_field(
        'hs_series_page_id',
        'Series Page',
        'hs_series_page_callback',
        'reading',
        'hs_author_series_section'
    );
}

function hs_author_series_section_callback() {
    echo '<p>Select pages that contain the [author_books] and [series_books] shortcodes for proper URL generation.</p>';
}

function hs_author_page_callback() {
    $page_id = get_option('hs_author_page_id');
    wp_dropdown_pages(array(
        'name' => 'hs_author_page_id',
        'selected' => $page_id,
        'show_option_none' => '— Select —',
    ));
    echo '<p class="description">The page containing the [author_books] shortcode</p>';
}

function hs_series_page_callback() {
    $page_id = get_option('hs_series_page_id');
    wp_dropdown_pages(array(
        'name' => 'hs_series_page_id',
        'selected' => $page_id,
        'show_option_none' => '— Select —',
    ));
    echo '<p class="description">The page containing series display shortcode</p>';
}
