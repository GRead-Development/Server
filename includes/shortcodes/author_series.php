<?php

// REWRITE 
/**
 * Author Books Shortcode
 *
 * Displays all books by a specific author or allows browsing all authors
 *
 * Usage:
 * [author_books author_id="123"] - Show books by specific author
 * [author_books author_slug="rl-stine"] - Show books by author slug
 * [author_books] - Show author directory
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('author_books', 'hs_author_books_shortcode');
add_shortcode('author_directory', 'hs_author_directory_shortcode');

/**
 * Display books by a specific author
 */
function hs_author_books_shortcode($atts) {
    $atts = shortcode_atts(array(
        'author_id' => '',
        'author_slug' => '',
        'limit' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ), $atts);

    // Get author by ID or slug
    if (!empty($atts['author_id'])) {
        $author = hs_get_author(intval($atts['author_id']));
    } elseif (!empty($atts['author_slug'])) {
        $author = hs_get_author_by_name($atts['author_slug']);
    } elseif (isset($_GET['author_id'])) {
        $author = hs_get_author(intval($_GET['author_id']));
    } elseif (isset($_GET['author_slug'])) {
        $author = hs_get_author_by_name(sanitize_text_field($_GET['author_slug']));
    } else {
        return '<p>No author specified.</p>';
    }

    if (!$author) {
        return '<p>Author not found.</p>';
    }

    // Get author's books
    $books = hs_get_author_books($author->id, array(
        'limit' => intval($atts['limit']),
        'orderby' => $atts['orderby'],
        'order' => $atts['order']
    ));

    $book_count = hs_get_author_book_count($author->id);
    $aliases = hs_get_author_aliases($author->id);

    ob_start();
    ?>
    <div class="hs-author-books">
        <div class="hs-author-header">
            <h2><?php echo esc_html($author->name); ?></h2>

            <?php if (!empty($aliases)): ?>
                <p class="hs-author-aliases">
                    <strong>Also known as:</strong>
                    <?php
                    $alias_names = array_map(function($alias) {
                        return esc_html($alias->alias_name);
                    }, $aliases);
                    echo implode(', ', $alias_names);
                    ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($author->bio)): ?>
                <div class="hs-author-bio">
                    <?php echo wpautop(esc_html($author->bio)); ?>
                </div>
            <?php endif; ?>

            <p class="hs-author-stats">
                <strong><?php echo number_format($book_count); ?></strong> book<?php echo $book_count !== 1 ? 's' : ''; ?>
                in the library
            </p>
        </div>

        <?php if (empty($books)): ?>
            <p>No books found for this author.</p>
        <?php else: ?>
            <div class="hs-books-grid">
                <?php foreach ($books as $book): ?>
                    <?php
                    $isbn = get_field('book_isbn', $book->ID);
                    $cover_url = hs_get_book_cover_url($book->ID, $isbn);
                    ?>
                    <div class="hs-book-item">
                        <a href="<?php echo get_permalink($book->ID); ?>" class="hs-book-link">
                            <?php if ($cover_url): ?>
                                <img src="<?php echo esc_url($cover_url); ?>"
                                     alt="<?php echo esc_attr($book->post_title); ?>"
                                     class="hs-book-cover">
                            <?php else: ?>
                                <div class="hs-book-cover-placeholder">
                                    <?php echo esc_html(substr($book->post_title, 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="hs-book-title">
                                <?php echo esc_html($book->post_title); ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .hs-author-books {
            margin: 20px 0;
        }
        .hs-author-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .hs-author-header h2 {
            margin-top: 0;
        }
        .hs-author-aliases {
            color: #666;
            font-style: italic;
        }
        .hs-author-bio {
            margin: 15px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #2271b1;
        }
        .hs-author-stats {
            color: #666;
            font-size: 1.1em;
        }
        .hs-books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .hs-book-item {
            text-align: center;
        }
        .hs-book-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .hs-book-cover {
            width: 100%;
            height: 225px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .hs-book-cover-placeholder {
            width: 100%;
            height: 225px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4em;
            font-weight: bold;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .hs-book-link:hover .hs-book-cover,
        .hs-book-link:hover .hs-book-cover-placeholder {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .hs-book-title {
            margin-top: 10px;
            font-weight: 500;
            line-height: 1.3;
        }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Display author directory with search
 */
function hs_author_directory_shortcode($atts) {
    global $wpdb;

    $atts = shortcode_atts(array(
        'limit' => 50,
        'orderby' => 'name',
        'order' => 'ASC'
    ), $atts);

    // Get search query
    $search = isset($_GET['author_search']) ? sanitize_text_field($_GET['author_search']) : '';

    // Get authors
    if (!empty($search)) {
        $authors = hs_search_authors($search, intval($atts['limit']));
    } else {
        $authors = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hs_authors ORDER BY {$atts['orderby']} {$atts['order']} LIMIT %d",
            intval($atts['limit'])
        ));
    }

    ob_start();
    ?>
    <div class="hs-author-directory">
        <div class="hs-author-search">
            <form method="get" action="">
                <input type="search"
                       name="author_search"
                       value="<?php echo esc_attr($search); ?>"
                       placeholder="Search authors..."
                       class="hs-search-input">
                <button type="submit" class="hs-search-button">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="<?php echo esc_url(remove_query_arg('author_search')); ?>" class="hs-clear-button">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($authors)): ?>
            <p>No authors found.</p>
        <?php else: ?>
            <div class="hs-authors-list">
                <?php foreach ($authors as $author): ?>
                    <?php $book_count = hs_get_author_book_count($author->id); ?>
                    <div class="hs-author-item">
                        <h3>
                            <a href="<?php echo esc_url(add_query_arg('author_id', $author->id)); ?>">
                                <?php echo esc_html($author->name); ?>
                            </a>
                        </h3>
                        <p class="hs-author-book-count">
                            <?php echo number_format($book_count); ?> book<?php echo $book_count !== 1 ? 's' : ''; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .hs-author-directory {
            margin: 20px 0;
        }
        .hs-author-search {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .hs-search-input {
            padding: 10px 15px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 300px;
            max-width: 100%;
        }
        .hs-search-button,
        .hs-clear-button {
            padding: 10px 20px;
            font-size: 16px;
            margin-left: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .hs-search-button {
            background: #2271b1;
            color: white;
        }
        .hs-search-button:hover {
            background: #135e96;
        }
        .hs-clear-button {
            background: #ddd;
            color: #333;
        }
        .hs-clear-button:hover {
            background: #ccc;
        }
        .hs-authors-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .hs-author-item {
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: box-shadow 0.2s;
        }
        .hs-author-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .hs-author-item h3 {
            margin-top: 0;
        }
        .hs-author-item h3 a {
            text-decoration: none;
            color: #2271b1;
        }
        .hs-author-item h3 a:hover {
            color: #135e96;
            text-decoration: underline;
        }
        .hs-author-book-count {
            color: #666;
            margin: 0;
        }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Helper function to get book cover URL
 * (You may need to adjust this based on your existing cover image implementation)
 */
function hs_get_book_cover_url($book_id, $isbn = null) {
    // Try featured image first
    $thumbnail_id = get_post_thumbnail_id($book_id);
    if ($thumbnail_id) {
        $image = wp_get_attachment_image_src($thumbnail_id, 'medium');
        if ($image) {
            return $image[0];
        }
    }

    // Try ISBN-based cover (OpenLibrary)
    if (!empty($isbn)) {
        return "https://covers.openlibrary.org/b/isbn/{$isbn}-M.jpg";
    }

    return null;
}
