<?php
// [book_directory]: Display a list of available books
function hs_book_directory_shortcode($atts)
{
    // Parse shortcode attributes with defaults
    $atts = shortcode_atts([
        'per_page' => 20,
        'orderby' => 'title',
        'order' => 'ASC',
    ], $atts);

    if (is_user_logged_in())
    {
        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb -> prefix . 'user_books';
        // Fixed: Use prepared statement to prevent SQL injection
        $user_book_ids = $wpdb -> get_col($wpdb->prepare("SELECT book_id FROM $table_name WHERE user_id = %d", $user_id));
	}

    // Get current page for pagination
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    $args =
    [
        'post_type' => 'book',
        'posts_per_page' => intval($atts['per_page']), // Fixed: Use pagination instead of loading all books
        'paged' => $paged,
        'orderby' => sanitize_text_field($atts['orderby']),
        'order' => sanitize_text_field($atts['order']),
    ];

    $books = new WP_Query($args);
    $output = '<div class="hs-container"><ul class="hs-book-list">';

    if ($books -> have_posts())
    {
        while ($books -> have_posts())
        {
            $books -> the_post();
            $book_id = get_the_ID();
            $author = get_post_meta($book_id, 'book_author', true);
            $pagecount = get_post_meta($book_id, 'nop', true);
            $isbn = get_post_meta($book_id, 'book_isbn', true);

            $output .= '<li>';
            $output .= '<h3>' . get_the_title() . '</h3>';
            
            if (!empty($author))
            {
                $output .= '<p><strong>Author:</strong> ' . esc_html($author) . '</p>';
            }

            if (!empty($pagecount))
            {
                $output .= '<p><strong>Pages:</strong> ' . esc_html($pagecount) . '</p>';
            }

            if (!empty($isbn))
            {
                $output .= '<p><strong>ISBN:</strong> ' . esc_html($isbn) . '</p>';
            }

			if (is_user_logged_in())
			{
				if (in_array($book_id, $user_book_ids))
				{
					$output .= '<button class="hs-button" disabled>Added</button>';
				}
				else
				{
					$output .= '<button class="hs-button hs-add-book" data-book-id="' . $book_id . '">Add to Library</button>';

				}
			}

            $output .= '</li>';
            }
        }

        else
        {
            $output .= '<li>No books are available. Check back later!</li>';
        }

        $output .= '</ul>';

        // Add pagination
        if ($books->max_num_pages > 1) {
            $output .= '<div class="hs-pagination">';
            $output .= paginate_links([
                'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                'format' => '?paged=%#%',
                'current' => max(1, $paged),
                'total' => $books->max_num_pages,
                'prev_text' => __('&laquo; Previous'),
                'next_text' => __('Next &raquo;'),
            ]);
            $output .= '</div>';
        }

        $output .= '</div>';
        wp_reset_postdata();
        return $output;
}
// Add the shortcode
add_shortcode('book_directory', 'hs_book_directory_shortcode');

