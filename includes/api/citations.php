<?php
/**
 * GRead Citations REST API Endpoints
 * Provides REST endpoints for generating book citations in various formats
 * Available at: gread.fun/wp-json/gread/v2/books/{book_id}/cite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register citation REST API routes
 */
function gread_register_citation_routes() {

    // Generate citation for a book (v2)
    register_rest_route('gread/v2', '/books/(?P<book_id>\d+)/cite', array(
        'methods' => 'POST',
        'callback' => 'gread_generate_book_citation',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'book_id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
            'format' => array(
                'required' => false,
                'default' => 'mla',
                'enum' => array('mla', 'apa', 'chicago'),
                'description' => 'Citation format: mla, apa, or chicago'
            ),
            'page_range' => array(
                'required' => false,
                'type' => 'string',
                'description' => 'Page range (e.g., "45-67" or "23")'
            ),
            'access_date' => array(
                'required' => false,
                'type' => 'string',
                'description' => 'Access date for online sources (YYYY-MM-DD)'
            ),
            'medium' => array(
                'required' => false,
                'default' => 'print',
                'enum' => array('print', 'web', 'ebook', 'audiobook'),
                'description' => 'Medium of the book'
            )
        )
    ));

    // Get citation history for current user (v2)
    register_rest_route('gread/v2', '/me/citations', array(
        'methods' => 'GET',
        'callback' => 'gread_get_user_citation_history',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'limit' => array(
                'default' => 20,
                'type' => 'integer'
            ),
            'offset' => array(
                'default' => 0,
                'type' => 'integer'
            )
        )
    ));

    // Get citation count for current user (v2)
    register_rest_route('gread/v2', '/me/citations/count', array(
        'methods' => 'GET',
        'callback' => 'gread_get_user_citation_count',
        'permission_callback' => 'gread_check_user_permission'
    ));

}
add_action('rest_api_init', 'gread_register_citation_routes');


/**
 * Generate citation for a book
 */
function gread_generate_book_citation($request) {
    global $wpdb;

    $user_id = get_current_user_id();
    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    $book_id = intval($request['book_id']);
    $format = $request->get_param('format') ?: 'mla';
    $page_range = $request->get_param('page_range');
    $access_date = $request->get_param('access_date');
    $medium = $request->get_param('medium') ?: 'print';

    // Get book details
    $book = get_post($book_id);
    if (!$book || $book->post_type !== 'book') {
        return new WP_Error('book_not_found', 'Book not found', array('status' => 404));
    }

    // Get book metadata
    $book_data = gread_get_book_metadata($book_id);

    // Generate citation based on format
    $citation = '';
    switch ($format) {
        case 'mla':
            $citation = gread_generate_mla_citation($book_data, $page_range, $access_date, $medium);
            break;
        case 'apa':
            $citation = gread_generate_apa_citation($book_data, $page_range, $access_date, $medium);
            break;
        case 'chicago':
            $citation = gread_generate_chicago_citation($book_data, $page_range, $access_date, $medium);
            break;
        default:
            return new WP_Error('invalid_format', 'Invalid citation format', array('status' => 400));
    }

    // Store citation in history
    $citation_id = gread_store_citation_history($user_id, $book_id, $format, $citation, array(
        'page_range' => $page_range,
        'access_date' => $access_date,
        'medium' => $medium
    ));

    // Increment user citation count
    $current_count = (int) get_user_meta($user_id, 'hs_citations_created_count', true);
    update_user_meta($user_id, 'hs_citations_created_count', $current_count + 1);

    // Trigger achievement check
    do_action('hs_stats_updated', $user_id);

    return rest_ensure_response(array(
        'citation_id' => $citation_id,
        'citation' => $citation,
        'format' => $format,
        'book' => array(
            'id' => $book_id,
            'title' => $book_data['title'],
            'author' => $book_data['author']
        ),
        'created_at' => current_time('mysql'),
        'total_citations' => $current_count + 1
    ));
}


/**
 * Get book metadata for citation generation
 */
function gread_get_book_metadata($book_id) {
    $book = get_post($book_id);

    // Get author(s)
    $authors = array();
    $author_terms = wp_get_post_terms($book_id, 'author');
    foreach ($author_terms as $author) {
        $authors[] = $author->name;
    }

    // Get other metadata
    $publisher = get_post_meta($book_id, 'publisher', true);
    $publication_year = get_post_meta($book_id, 'publication_year', true);
    $edition = get_post_meta($book_id, 'edition', true);
    $isbn = get_post_meta($book_id, 'isbn', true);
    $url = get_post_meta($book_id, 'url', true);

    return array(
        'title' => $book->post_title,
        'author' => !empty($authors) ? $authors[0] : 'Unknown Author',
        'all_authors' => $authors,
        'publisher' => $publisher ?: 'Unknown Publisher',
        'publication_year' => $publication_year ?: date('Y'),
        'edition' => $edition,
        'isbn' => $isbn,
        'url' => $url
    );
}


/**
 * Generate MLA format citation
 * Format: Author Last, First. Title. Publisher, Year.
 * With pages: Author Last, First. Title. Publisher, Year, pp. 45-67.
 */
function gread_generate_mla_citation($book_data, $page_range = null, $access_date = null, $medium = 'print') {
    $citation = '';

    // Author (Last, First format)
    $author = $book_data['author'];
    $author_parts = explode(' ', $author);
    if (count($author_parts) > 1) {
        $last_name = array_pop($author_parts);
        $first_names = implode(' ', $author_parts);
        $citation .= $last_name . ', ' . $first_names . '. ';
    } else {
        $citation .= $author . '. ';
    }

    // Title (italicized in display - we'll use markdown-style *)
    $citation .= '*' . $book_data['title'] . '*. ';

    // Edition (if specified)
    if (!empty($book_data['edition'])) {
        $citation .= $book_data['edition'] . ' ed., ';
    }

    // Publisher
    $citation .= $book_data['publisher'] . ', ';

    // Publication year
    $citation .= $book_data['publication_year'];

    // Page range
    if (!empty($page_range)) {
        if (strpos($page_range, '-') !== false) {
            $citation .= ', pp. ' . $page_range;
        } else {
            $citation .= ', p. ' . $page_range;
        }
    }

    // Medium (if web or ebook)
    if ($medium === 'web' || $medium === 'ebook') {
        $citation .= '. ' . ucfirst($medium);
    }

    // Access date (for web sources)
    if ($medium === 'web' && !empty($access_date)) {
        $formatted_date = date('j M. Y', strtotime($access_date));
        $citation .= '. Accessed ' . $formatted_date;
    }

    $citation .= '.';

    return $citation;
}


/**
 * Generate APA format citation
 * Format: Author, A. A. (Year). Title. Publisher.
 * NOTE: APA implementation coming in phase 2
 */
function gread_generate_apa_citation($book_data, $page_range = null, $access_date = null, $medium = 'print') {
    $citation = '';

    // Author (Last, F. M. format)
    $author = $book_data['author'];
    $author_parts = explode(' ', $author);
    if (count($author_parts) > 1) {
        $last_name = array_pop($author_parts);
        $initials = '';
        foreach ($author_parts as $name) {
            $initials .= strtoupper(substr($name, 0, 1)) . '. ';
        }
        $citation .= $last_name . ', ' . trim($initials) . ' ';
    } else {
        $citation .= $author . ' ';
    }

    // Year
    $citation .= '(' . $book_data['publication_year'] . '). ';

    // Title (italicized)
    $citation .= '*' . $book_data['title'] . '*';

    // Edition
    if (!empty($book_data['edition'])) {
        $citation .= ' (' . $book_data['edition'] . ' ed.)';
    }

    $citation .= '. ';

    // Publisher
    $citation .= $book_data['publisher'] . '.';

    return $citation;
}


/**
 * Generate Chicago format citation
 * Format: Author Last, First. Title. City: Publisher, Year.
 * NOTE: Chicago implementation coming in phase 2
 */
function gread_generate_chicago_citation($book_data, $page_range = null, $access_date = null, $medium = 'print') {
    $citation = '';

    // Author (Last, First format)
    $author = $book_data['author'];
    $author_parts = explode(' ', $author);
    if (count($author_parts) > 1) {
        $last_name = array_pop($author_parts);
        $first_names = implode(' ', $author_parts);
        $citation .= $last_name . ', ' . $first_names . '. ';
    } else {
        $citation .= $author . '. ';
    }

    // Title (italicized)
    $citation .= '*' . $book_data['title'] . '*. ';

    // Publisher and year (Chicago typically includes city, but we'll skip if not available)
    $citation .= $book_data['publisher'] . ', ' . $book_data['publication_year'] . '.';

    return $citation;
}


/**
 * Store citation in user history
 */
function gread_store_citation_history($user_id, $book_id, $format, $citation, $metadata) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'hs_citations';

    // Create table if it doesn't exist
    gread_create_citations_table();

    $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'book_id' => $book_id,
            'format' => $format,
            'citation' => $citation,
            'metadata' => json_encode($metadata),
            'created_at' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s', '%s', '%s')
    );

    return $wpdb->insert_id;
}


/**
 * Create citations table
 */
function gread_create_citations_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'hs_citations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        book_id bigint(20) NOT NULL,
        format varchar(20) NOT NULL,
        citation text NOT NULL,
        metadata text,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY book_id (book_id),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}


/**
 * Get user citation history
 */
function gread_get_user_citation_history($request) {
    global $wpdb;

    $user_id = get_current_user_id();
    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    $limit = intval($request->get_param('limit')) ?: 20;
    $offset = intval($request->get_param('offset')) ?: 0;

    $table_name = $wpdb->prefix . 'hs_citations';

    // Create table if it doesn't exist
    gread_create_citations_table();

    $citations = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, p.post_title as book_title
         FROM $table_name c
         LEFT JOIN {$wpdb->posts} p ON c.book_id = p.ID
         WHERE c.user_id = %d
         ORDER BY c.created_at DESC
         LIMIT %d OFFSET %d",
        $user_id,
        $limit,
        $offset
    ));

    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    $result = array();
    foreach ($citations as $citation) {
        $metadata = json_decode($citation->metadata, true);
        $result[] = array(
            'id' => intval($citation->id),
            'book_id' => intval($citation->book_id),
            'book_title' => $citation->book_title,
            'format' => $citation->format,
            'citation' => $citation->citation,
            'page_range' => $metadata['page_range'] ?? null,
            'access_date' => $metadata['access_date'] ?? null,
            'medium' => $metadata['medium'] ?? 'print',
            'created_at' => $citation->created_at
        );
    }

    return rest_ensure_response(array(
        'citations' => $result,
        'total' => intval($total),
        'limit' => $limit,
        'offset' => $offset
    ));
}


/**
 * Get user citation count
 */
function gread_get_user_citation_count($request) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return new WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
    }

    $count = (int) get_user_meta($user_id, 'hs_citations_created_count', true);

    return rest_ensure_response(array(
        'count' => $count
    ));
}


/**
 * Permission callback - check if user is logged in
 */
function gread_check_user_permission() {
    return is_user_logged_in();
}
