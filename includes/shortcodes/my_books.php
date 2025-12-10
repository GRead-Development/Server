<?php


/**
 * Shortcode: [my_books]
 * Displays the user's reading library with options for sorting and filtering.
 *
 * Sorting is done via shortcode attributes or GET parameters:
 * - sort_by: 'title' (default), 'author', or 'progress'
 * - sort_order: 'asc' (default) or 'desc'
 * - include_completed: 'yes' or 'no' (default)
 */
function hs_my_books_shortcode($atts)
{
    if (!is_user_logged_in()) {
        // Return message if the user is not logged in
        return '<p>Please log in to track your reading!</p>';
    }

    // 1. Parse Shortcode Attributes for Sorting and Filtering
    $atts = shortcode_atts(array(
        'sort_by' => 'title',
        'sort_order' => 'asc',
        'include_completed' => 'no',
    ), $atts, 'my_books');

    // Clean and validate attributes, preferring GET parameters if present for interactive sorting
    $sort_by = isset($_GET['sort_by']) ? sanitize_key($_GET['sort_by']) : $atts['sort_by'];
    $sort_order = isset($_GET['sort_order']) ? sanitize_key($_GET['sort_order']) : $atts['sort_order'];
    $include_completed = isset($_GET['include_completed']) ? ('yes' === sanitize_key($_GET['include_completed'])) : ('yes' === $atts['include_completed']);

    // Ensure valid values after checking GET/defaults
    $sort_by = in_array($sort_by, array('title', 'author', 'progress')) ? $sort_by : 'title';
    $sort_order = in_array(strtolower($sort_order), array('asc', 'desc')) ? strtolower($sort_order) : 'asc';


    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'user_books';

	// Retrieve user's reviews
	$reviews_table = $wpdb -> prefix . 'hs_book_reviews';
	$user_reviews_raw = $wpdb -> get_results($wpdb -> prepare("SELECT book_id, rating, review_text FROM {$reviews_table} WHERE user_id = %d", $user_id));
	$user_reviews = [];

	foreach ($user_reviews_raw as $review)
	{
		$user_reviews[$review -> book_id] = $review;
	}

	// Retrieve DNF data
	$dnf_table = $wpdb -> prefix . 'dnf_books';
	$dnf_data_raw = $wpdb -> get_results($wpdb -> prepare("SELECT book_id, reason, pages_read, date_dnf FROM {$dnf_table} WHERE user_id = %d", $user_id));
	$dnf_data = [];

	foreach ($dnf_data_raw as $dnf)
	{
		$dnf_data[$dnf -> book_id] = $dnf;
	}

    // Fetch all books for the current user
    $my_book_entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    if (empty($my_book_entries)) {
        return '<p>You have not added any books to your library. Browse the book database and add what books you are reading to your library. If you cannot find the book you are reading, submit it to the database and get some rewards!</p>';
    }

    // 2. Collect All Book Data into a single array
    $all_books_data = [];
    foreach ($my_book_entries as $book_entry) {
        $book = get_post($book_entry->book_id);
        if ($book) {
            $total_pages = (int)get_post_meta($book_entry->book_id, 'nop', true);
            $current_page = (int)$book_entry->current_page;
            $progress = ($total_pages > 0) ? round(($current_page / $total_pages) * 100) : 0;
            $is_completed = ($total_pages > 0 && $current_page >= $total_pages);

            // Assuming the author is the post author for simplicity; adjust meta key if needed
            $author = get_post_meta($book_entry->book_id, 'book_author', true);

		// Check if the user has reviewed the book
		$has_review = isset($user_reviews[$book_entry -> book_id]);
		$user_rating = $has_review ? $user_reviews[$book_entry -> book_id] -> rating : null;
		$user_review_text = $has_review ? $user_reviews[$book_entry -> book_id] -> review_text : '';

		// Get book status and DNF info
		$status = isset($book_entry -> status) ? $book_entry -> status : 'reading';
		$is_dnf = $status === 'dnf';
		$is_paused = $status === 'paused';
		$dnf_info = isset($dnf_data[$book_entry -> book_id]) ? $dnf_data[$book_entry -> book_id] : null;

            $all_books_data[] = [
                'entry' => $book_entry,
                'post' => $book,
                'total_pages' => $total_pages,
                'current_page' => $current_page,
                'progress' => $progress,
                'is_completed' => $is_completed,
                'author' => $author,
		'has_review' => $has_review,
		'user_rating' => $user_rating,
		'user_review_text' => $user_review_text,
		'status' => $status,
		'is_dnf' => $is_dnf,
		'is_paused' => $is_paused,
		'dnf_info' => $dnf_info
            ];
        }
    }

    // 3. Implement Sorting Logic (custom comparison function)
    usort($all_books_data, function($a, $b) use ($sort_by, $sort_order) {
        $a_val = '';
        $b_val = '';

        switch ($sort_by) {
            case 'author':
                $a_val = $a['author'];
                $b_val = $b['author'];
                break;
            case 'progress':
                $a_val = $a['progress'];
                $b_val = $b['progress'];
                break;
            case 'title':
            default:
                $a_val = $a['post']->post_title;
                $b_val = $b['post']->post_title;
        }

        // String comparison for title/author
        if ($sort_by === 'author' || $sort_by === 'title') {
            $result = strcasecmp($a_val, $b_val);
            return ($sort_order === 'asc') ? $result : -$result;
        }
        // Numeric comparison for progress
        else {
            if ($a_val == $b_val) return 0;
            if ($sort_order === 'asc') {
                return ($a_val < $b_val) ? -1 : 1;
            } else {
                return ($a_val > $b_val) ? -1 : 1;
            }
        }
    });

    // Initialize HTML containers
    $reading_books_html = '';
    $completed_books_html = '';
    $paused_books_html = '';
    $dnf_books_html = '';
    $completed_count = 0;
    $paused_count = 0;
    $dnf_count = 0;
    $main_list_html = ''; // For combined list when filtering is off

    // 4. Generate HTML based on the sorted list and current filter settings
    foreach ($all_books_data as $data) {
        $book_entry = $data['entry'];
        $book = $data['post'];
        $total_pages = $data['total_pages'];
        $current_page = $data['current_page'];
        $progress = $data['progress'];
        $is_completed = $data['is_completed'];
        $author = $data['author'];
	$has_review = $data['has_review'];
	$user_rating = $data['user_rating'];
	$user_review_text = $data['user_review_text'];
	$status = $data['status'];
	$is_dnf = $data['is_dnf'];
	$is_paused = $data['is_paused'];
	$dnf_info = $data['dnf_info'];

	error_log("Book ID {$book_entry -> book_id}: current_page={$current_page}, total_pages={$total_pages}, is_completed=" . ($is_completed ? 'YES' : 'NO'));

        $li_class = 'hs-my-book';
	if ($is_completed) {
		$li_class .= ' completed';
	}
	if ($is_paused) {
		$li_class .= ' hs-book-paused';
	}
	if ($is_dnf) {
		$li_class .= ' hs-book-dnf';
	}
        $bar_class = $is_completed ? 'hs-progress-bar golden' : 'hs-progress-bar';

	// Get ISBN for OpenLibrary API
	$isbn = get_post_meta($book_entry->book_id, 'book_isbn', true);
	if (empty($isbn)) {
		// Try to get from ISBN table
		global $wpdb;
		$isbn_table = $wpdb->prefix . 'hs_book_isbns';
		$isbn_row = $wpdb->get_row($wpdb->prepare("SELECT isbn FROM {$isbn_table} WHERE post_id = %d AND is_primary = 1 LIMIT 1", $book_entry->book_id));
		if ($isbn_row) {
			$isbn = $isbn_row->isbn;
		}
	}

	// Get existing cover URL if available
	$cover_url = get_the_post_thumbnail_url($book_entry->book_id, 'medium');
	$has_cover = !empty($cover_url);
	if (!$cover_url) {
		$cover_url = ''; // Will be replaced by JavaScript
	}

        // HTML for a single book item - NEW CARD STRUCTURE
        $book_html = '<div class="hs-book-card ' . esc_attr($li_class) . '" data-list-book-id="' . esc_attr($book_entry->book_id) . '" data-reviewed="' . ($has_review ? 'true' : 'false') . '" data-isbn="' . esc_attr($isbn) . '">';

        // Book cover (opens on hover)
        $cover_class = $has_cover ? 'hs-book-cover' : 'hs-book-cover no-cover';
        $book_html .= '<div class="' . esc_attr($cover_class) . '" style="background-image: url(' . esc_url($cover_url) . ');">';
        $book_html .= '<div class="hs-book-cover-overlay">';
        $book_html .= '<h3 class="hs-book-title">' . esc_html($book->post_title) . '</h3>';
        $book_html .= '<p class="hs-book-author">By: ' . esc_html($author) . '</p>';

	// Add status badge on cover
	if ($is_paused) {
		$book_html .= '<span class="hs-book-status-badge hs-badge-paused">Paused</span>';
	} elseif ($is_dnf) {
		$book_html .= '<span class="hs-book-status-badge hs-badge-dnf">DNF</span>';
	} elseif ($is_completed) {
		$book_html .= '<span class="hs-book-status-badge hs-badge-completed">Completed</span>';
	}

	$book_html .= '</div>'; // .hs-book-cover-overlay
	$book_html .= '</div>'; // .hs-book-cover

	// Book content (shown when cover opens)
	$book_html .= '<div class="hs-book-content">';
	$book_html .= '<h3><a href="' . esc_url(get_permalink($book->ID)) . '">' . esc_html($book->post_title) . '</a></h3>';
        $book_html .= '<p class="hs-book-author">By: ' . esc_html($author) . '</p>'; // Display Author
        $book_html .= '<div class="hs-progress-bar-container"><div class="' . esc_attr($bar_class) . '" style="width: ' . esc_attr($progress) . '%;"></div></div>';
        $book_html .= '<span>Progress: ' . esc_html($progress) . '% (' . esc_html($current_page) . ' / ' . esc_html($total_pages) . ' pages)</span>';

        $book_html .= '<form class="hs-progress-form">';
        $book_html .= '<input type="hidden" name="book_id" value="' . esc_attr($book_entry->book_id) . '">';
        $book_html .= '<label>Update current page number:</label>';
        $book_html .= '<input type="number" name="current_page" min="0" max="' . esc_attr($total_pages) . '" value="' . esc_attr($current_page) . '">';
        $book_html .= '<button type="submit" class="hs-button">Save Progress</button>';
        $book_html .= '<span class="hs-feedback"></span>';
        $book_html .= '</form>';

	// Show DNF reason if book is marked DNF
	if ($is_dnf && $dnf_info) {
		$book_html .= '<div class="hs-dnf-reason-display">DNF Reason: ' . esc_html($dnf_info->reason) . '</div>';
	}

        $book_html .= '<div class="hs-button-group">';
        $book_html .= '<button class="hs-button hs-remove-book" data-book-id="' . esc_attr($book_entry->book_id) . '">Remove</button>';
        $book_html .= '<button class="hs-button hs-notes-button" data-book-id="' . esc_attr($book_entry->book_id) . '" data-book-title="' . esc_attr($book->post_title) . '">View & Manage Notes</button>';

	// Add Pause/Resume/DNF buttons (not for completed or DNF books)
	if ($is_paused) {
		$book_html .= '<button class="hs-button hs-resume-book" data-book-id="' . esc_attr($book_entry->book_id) . '">Resume</button>';
	} elseif (!$is_completed && !$is_dnf) {
		$book_html .= '<button class="hs-button hs-pause-book" data-book-id="' . esc_attr($book_entry->book_id) . '">Pause</button>';
	}

	// Add DNF button (not for completed or already DNF books)
	if (!$is_completed && !$is_dnf) {
		$book_html .= '<button class="hs-button hs-dnf-book" data-book-id="' . esc_attr($book_entry->book_id) . '">Mark as DNF</button>';
	}

	$book_html .= '<span class="hs-book-action-feedback"></span>';
        $book_html .= '</div>';


	if ($is_completed) {
		error_log("Book ID {book_entry -> book_id}: ADDING REVIEW SECTION");
            $book_html .= '<div class="hs-review-section">';
            
            $review_button_text = $has_review ? 'Edit Review' : 'Review Book';
            $rating_display = $has_review && !is_null($user_rating) ? 'You rated this: <strong>' . esc_html($user_rating) . '/10</strong>' : '';

            $book_html .= '<span class="hs-user-rating-display">' . $rating_display . '</span>';
            $book_html .= '<button class="hs-button hs-toggle-review-form" data-book-id="' . esc_attr($book_entry->book_id) . '">' . $review_button_text . '</button>';

            // Hidden Review Form
            $book_html .= '<form class="hs-review-form" style="display:none;">';
            $book_html .= '<input type="hidden" name="book_id" value="' . esc_attr($book_entry->book_id) . '">';
            $book_html .= '<h4>Your Review</h4>';
            
            $book_html .= '<div class="hs-review-field">';
            $book_html .= '<label for="hs_rating_' . esc_attr($book_entry->book_id) . '">Rating (1.0 - 10.0):</label>';
            $book_html .= '<input type="number" id="hs_rating_' . esc_attr($book_entry->book_id) . '" name="hs_rating" min="1.0" max="10.0" step="0.1" value="' . esc_attr($user_rating) . '">';
            $book_html .= '</div>';
            
            $book_html .= '<div class="hs-review-field">';
            $book_html .= '<label for="hs_review_text_' . esc_attr($book_entry->book_id) . '">Review (optional, +20 points):</label>';
            $book_html .= '<textarea id="hs_review_text_' . esc_attr($book_entry->book_id) . '" name="hs_review_text" rows="4">' . esc_textarea($user_review_text) . '</textarea>';
            $book_html .= '</div>';

            $book_html .= '<button type="submit" class="hs-button">Submit Review</button>';
            $book_html .= '<span class="hs-review-feedback"></span>';
            $book_html .= '</form>'; // end .hs-review-form
            
            $book_html .= '</div>'; // end .hs-review-section
        }

	$book_html .= '</div>'; // end .hs-book-content
        $book_html .= '</div>'; // end .hs-book-card

        // Append to the correct section string based on the filter setting and status
        if ($include_completed) {
            $main_list_html .= $book_html;
        } else {
            if ($is_dnf) {
                $dnf_books_html .= $book_html;
                $dnf_count++;
            } elseif ($is_paused) {
                $paused_books_html .= $book_html;
                $paused_count++;
            } elseif ($is_completed) {
                $completed_books_html .= $book_html;
                $completed_count++;
            } else {
                $reading_books_html .= $book_html;
            }
        }

        // Count for summary even if in combined view
        if ($is_completed && $include_completed) {
            $completed_count++;
        }
        if ($is_paused && $include_completed) {
            $paused_count++;
        }
        if ($is_dnf && $include_completed) {
            $dnf_count++;
        }
    }


    // 5. Assemble the final output, starting with the sort/filter form

    // Determine the current URL to ensure form submission works correctly.
    $form_action = esc_url(remove_query_arg(array('sort_by', 'sort_order', 'include_completed')));

    $output = '<div class="hs-container">';

    // Form to control sorting and filtering
    $output .= '<form class="hs-sort-filter-form" action="' . $form_action . '" method="get">';

    // Hidden fields for existing GET parameters to maintain context if shortcode is on a complex page
    $current_url_params = $_GET;
    unset($current_url_params['sort_by'], $current_url_params['sort_order'], $current_url_params['include_completed']);
    foreach ($current_url_params as $key => $value) {
        $output .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
    }

    // Sort By Selector
    $output .= '<div class="hs-sort-group">';
    $output .= '<label for="hs_sort_by">Sort By:</label>';
    $output .= '<select name="sort_by" id="hs_sort_by" onchange="this.form.submit()">';
    $output .= '<option value="title"' . selected($sort_by, 'title', false) . '>Title</option>';
    $output .= '<option value="author"' . selected($sort_by, 'author', false) . '>Author</option>';
    $output .= '<option value="progress"' . selected($sort_by, 'progress', false) . '>Progress</option>';
    $output .= '</select>';
    $output .= '</div>';

    // Sort Order Selector
    $output .= '<div class="hs-sort-group">';
    $output .= '<label for="hs_sort_order">Order:</label>';
    $output .= '<select name="sort_order" id="hs_sort_order" onchange="this.form.submit()">';
    $output .= '<option value="asc"' . selected($sort_order, 'asc', false) . '>Ascending (A-Z, 0-100%)</option>';
    $output .= '<option value="desc"' . selected($sort_order, 'desc', false) . '>Descending (Z-A, 100%-0%)</option>';
    $output .= '</select>';
    $output .= '</div>';

    // Include Completed Checkbox
    $output .= '<div class="hs-filter-group">';
    $output .= '<label for="hs_include_completed">';
    // Use an input type="checkbox" but ensure the value is only submitted if checked
    $output .= '<input type="checkbox" name="include_completed" id="hs_include_completed" value="yes" ' . checked($include_completed, true, false) . ' onchange="this.form.submit()">';
    $output .= 'Include Completed in Main List';
    $output .= '</label>';
    $output .= '</div>';

    $output .= '</form>';


    // Display the book lists
    if ($include_completed) {
        // Combined List
        $output .= '<h2>My Full Library (Sorted by ' . esc_html(ucfirst($sort_by)) . ')</h2>';
        if (!empty($main_list_html)) {
            $output .= '<div class="hs-book-grid hs-combined-list" id="hs-combined-books-list">' . $main_list_html . '</div>';
        } else {
            $output .= '<p>Your library is empty.</p>';
        }
    } else {
        // Separated Lists (Currently Reading / Completed)

        // "Currently Reading" Section
        $output .= '<div style = "padding: 15px"><h2 style = "margin:0px"> Currently Reading</h2></div>';
        if (!empty($reading_books_html)) {
            $output .= '<div class="hs-book-grid" id="hs-reading-books-list">' . $reading_books_html . '</div>';
        } else {
            $output .= '<p>You are not currently reading any books.</p>';
        }

        // "Paused Books" Section
        if ($paused_count > 0) {
            $output .= '<details class="hs-completed-section">';
            $output .= '<summary><h3>Paused Books (' . $paused_count . ')</h3></summary>';
            $output .= '<div class="hs-book-grid" id="hs-paused-books-list">' . $paused_books_html . '</div>';
            $output .= '</details>';
        }

        // "DNF Books" Section
        if ($dnf_count > 0) {
            $output .= '<details class="hs-completed-section">';
            $output .= '<summary><h3>DNF Books (' . $dnf_count . ')</h3></summary>';
            $output .= '<div class="hs-book-grid" id="hs-dnf-books-list">' . $dnf_books_html . '</div>';
            $output .= '</details>';
        }

        // "Completed Books" Section
        if ($completed_count > 0) {
            $output .= '<details class="hs-completed-section">';
            $output .= '<summary><h3>Completed Books (' . $completed_count . ')</h3></summary>';
            $output .= '<div class="hs-book-grid" id="hs-completed-books-list">' . $completed_books_html . '</div>';
            $output .= '</details>';
        }
    }

    // Add DNF Modal HTML
    $output .= '
    <div id="hs-dnf-modal" style="display:none;">
        <div class="hs-modal-content">
            <span id="hs-close-dnf-modal">&times;</span>
            <h3>Mark Book as DNF (Did Not Finish)</h3>
            <p>Please tell us why you stopped reading this book. Your feedback helps us improve recommendations.</p>
            <form id="hs-dnf-form">
                <input type="hidden" id="hs-dnf-book-id" value="">
                <textarea id="hs-dnf-reason-textarea" placeholder="e.g., The pacing was too slow, not what I expected, etc." required></textarea>
                <button type="submit" class="hs-button" id="hs-submit-dnf-button">Mark as DNF</button>
                <span id="hs-dnf-feedback"></span>
            </form>
        </div>
    </div>';

    $output .= '</div>';
    return $output;
}
// Add the shortcode
add_shortcode('my_books', 'hs_my_books_shortcode');
