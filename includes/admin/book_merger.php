<?php
/**
 * Admin interface for book merging and ISBN management
 * Similar to the authors/series manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'hs_book_merge_admin_menu');

function hs_book_merge_admin_menu()
{
    add_submenu_page(
        'edit.php?post_type=book',
        'Book Merge & ISBN Manager',
        'Book Merge/ISBN',
        'manage_options',
        'hs-book-merge',
        'hs_book_merge_admin_page'
    );
}

function hs_book_merge_admin_page()
{
    // Get current tab
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'recent';

    ?>
    <div class="wrap">
        <h1>Book Merge & ISBN Manager</h1>

        <h2 class="nav-tab-wrapper">
            <a href="?post_type=book&page=hs-book-merge&tab=recent" class="nav-tab <?php echo $tab === 'recent' ? 'nav-tab-active' : ''; ?>">Recent Books</a>
            <a href="?post_type=book&page=hs-book-merge&tab=search" class="nav-tab <?php echo $tab === 'search' ? 'nav-tab-active' : ''; ?>">Search</a>
            <a href="?post_type=book&page=hs-book-merge&tab=isbn" class="nav-tab <?php echo $tab === 'isbn' ? 'nav-tab-active' : ''; ?>">ISBN Management</a>
            <a href="?post_type=book&page=hs-book-merge&tab=duplicate-reports" class="nav-tab <?php echo $tab === 'duplicate-reports' ? 'nav-tab-active' : ''; ?>">Duplicate Reports</a>
        </h2>

        <div class="tab-content">
            <?php
            switch ($tab) {
                case 'search':
                    hs_book_merge_tab_search();
                    break;
                case 'isbn':
                    hs_book_merge_tab_isbn();
                    break;
                case 'duplicate-reports':
                    hs_book_merge_tab_duplicate_reports();
                    break;
                default:
                    hs_book_merge_tab_recent();
                    break;
            }
            ?>
        </div>
    </div>

    <style>
        .hs-book-item {
            padding: 15px;
            margin: 10px 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: flex;
            gap: 15px;
        }
        .hs-book-item.canonical {
            border-left: 4px solid #2271b1;
        }
        .hs-book-item.selected {
            background: #f0f6fc;
            border-color: #2271b1;
        }
        .hs-book-checkbox {
            flex-shrink: 0;
            padding-top: 5px;
        }
        .hs-book-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .hs-book-content {
            flex: 1;
        }
        .hs-book-item h3 {
            margin-top: 0;
        }
        .hs-book-meta {
            color: #666;
            font-size: 0.9em;
            margin: 5px 0;
        }
        .hs-isbn-list {
            margin: 10px 0;
            padding-left: 20px;
        }
        .hs-isbn-list li {
            padding: 5px 0;
        }
        .hs-isbn-primary {
            font-weight: bold;
            color: #2271b1;
        }
        .hs-merge-form {
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .hs-button-group {
            margin-top: 10px;
        }
        .hs-button-group button {
            margin-right: 5px;
        }
        .hs-search-box {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .hs-stats-box {
            padding: 15px;
            background: #fff;
            border-left: 4px solid #2271b1;
            margin: 20px 0;
        }
        .hs-bulk-merge-box {
            padding: 20px;
            background: #fff;
            border: 2px solid #2271b1;
            border-radius: 4px;
            margin: 20px 0;
        }
        .hs-bulk-merge-box h3 {
            margin-top: 0;
            color: #2271b1;
        }
        .hs-bulk-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .hs-bulk-status {
            padding: 10px 15px;
            background: #f0f6fc;
            border-radius: 4px;
            font-weight: bold;
        }
        .hs-notice {
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .hs-notice.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .hs-notice.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .hs-book-thumbnail {
            max-width: 80px;
            height: auto;
            margin-right: 15px;
            float: left;
        }
        .hs-book-details {
            overflow: hidden;
        }
        .hs-merge-controls {
            clear: both;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            margin-top: 10px;
        }
    </style>

    <script>
        function toggleMergeForm(bookId) {
            var form = document.getElementById('merge-form-' + bookId);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function toggleISBNForm(bookId) {
            var form = document.getElementById('isbn-form-' + bookId);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function confirmMerge(fromTitle, toTitle) {
            return confirm('Are you sure you want to merge "' + fromTitle + '" into "' + toTitle + '"?\n\nThis will:\n- Move all ISBNs to the target book\n- Update the merged book\'s metadata to match the target\n- Mark the source book as merged\n\nThis action cannot be undone.');
        }

        // Bulk merge functionality
        function updateBulkStatus() {
            var checkboxes = document.querySelectorAll('.hs-book-select:checked');
            var count = checkboxes.length;
            var status = document.getElementById('bulk-status');
            var mergeBtn = document.getElementById('bulk-merge-btn');

            if (status) {
                status.textContent = count + ' book' + (count !== 1 ? 's' : '') + ' selected';
            }

            if (mergeBtn) {
                mergeBtn.disabled = count === 0;
            }

            // Highlight selected items
            document.querySelectorAll('.hs-book-item').forEach(function(item) {
                var checkbox = item.querySelector('.hs-book-select');
                if (checkbox && checkbox.checked) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        }

        function toggleSelectAll(checkbox) {
            var checkboxes = document.querySelectorAll('.hs-book-select');
            checkboxes.forEach(function(cb) {
                cb.checked = checkbox.checked;
            });
            updateBulkStatus();
        }

        function confirmBulkMerge() {
            var checkboxes = document.querySelectorAll('.hs-book-select:checked');
            var targetId = document.getElementById('bulk-target-id').value;

            if (!targetId) {
                alert('Please enter a target book ID');
                return false;
            }

            if (checkboxes.length === 0) {
                alert('Please select at least one book to merge');
                return false;
            }

            var bookIds = [];
            checkboxes.forEach(function(cb) {
                bookIds.push(cb.value);
            });

            return confirm('Are you sure you want to merge ' + checkboxes.length + ' book(s) into book ID ' + targetId + '?\n\nThis will:\n- Merge all selected books into the target book\n- Move all ISBNs to the target book\n- Mark the source books as merged\n\nThis action cannot be undone.');
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add change listeners to all book checkboxes
            document.querySelectorAll('.hs-book-select').forEach(function(checkbox) {
                checkbox.addEventListener('change', updateBulkStatus);
            });

            // Initial status update
            updateBulkStatus();
        });
    </script>
    <?php
}

function hs_book_merge_tab_recent()
{
    global $wpdb;

    // Get pagination
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    // Display notices
    if (isset($_GET['merged']) && $_GET['merged'] === 'success') {
        $count = isset($_GET['count']) ? intval($_GET['count']) : 1;
        $message = $count > 1
            ? '<strong>Success!</strong> ' . $count . ' books have been merged successfully.'
            : '<strong>Success!</strong> Books have been merged successfully.';
        echo '<div class="hs-notice success">' . $message . '</div>';
    } elseif (isset($_GET['error'])) {
        echo '<div class="hs-notice error"><strong>Error:</strong> ' . esc_html(urldecode($_GET['error'])) . '</div>';
    }

    // Get statistics
    $total_books = wp_count_posts('book')->publish;
    $total_isbns = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_isbns");

    ?>
    <div class="hs-stats-box">
        <strong>Statistics:</strong>
        <?php echo number_format($total_books); ?> books,
        <?php echo number_format($total_isbns); ?> ISBNs tracked
    </div>

    <h3>Recently Added Books (<?php echo number_format($total_books); ?> total)</h3>
    <p>Showing the most recently added books to help identify duplicates. Use the search tab to find specific books.</p>

    <!-- Bulk Merge Controls -->
    <div class="hs-bulk-merge-box">
        <h3>Bulk Merge Books</h3>
        <p>Select multiple books below and merge them all into a single target book at once.</p>

        <form method="post" id="bulk-merge-form" onsubmit="return confirmBulkMerge();">
            <?php wp_nonce_field('hs_bulk_merge_books', 'bulk_merge_nonce'); ?>
            <input type="hidden" name="action" value="bulk_merge_books">
            <input type="hidden" name="current_tab" value="recent">

            <div class="hs-bulk-controls">
                <div class="hs-bulk-status" id="bulk-status">0 books selected</div>

                <label>
                    <input type="checkbox" onchange="toggleSelectAll(this)"> Select/Deselect All
                </label>

                <label style="display: flex; align-items: center; gap: 5px;">
                    <strong>Target Book ID:</strong>
                    <input type="number" name="target_book_id" id="bulk-target-id" required style="width: 120px;" placeholder="Enter ID">
                </label>

                <label>
                    <input type="checkbox" name="sync_metadata" value="1" checked>
                    Sync metadata from target
                </label>

                <button type="submit" class="button button-primary" id="bulk-merge-btn" disabled>
                    Merge Selected Books
                </button>
            </div>
        </form>
    </div>

    <?php
    // Get recent books
    $args = array(
        'post_type' => 'book',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'offset' => $offset,
        'orderby' => 'date',
        'order' => 'DESC'
    );

    $query = new WP_Query($args);
    $books = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            // Skip merged books (not canonical)
            $gid_entry = $wpdb->get_row($wpdb->prepare(
                "SELECT is_canonical FROM {$wpdb->prefix}hs_gid WHERE post_id = %d",
                $post_id
            ));

            // If book has a GID entry and is not canonical (is_canonical = 0), skip it
            if ($gid_entry && $gid_entry->is_canonical == 0) {
                continue;
            }

            $books[] = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'author' => get_field('book_author', $post_id),
                'isbn' => get_field('book_isbn', $post_id),
                'page_count' => get_field('nop', $post_id),
                'date' => get_the_date('Y-m-d H:i', $post_id),
                'gid' => hs_get_gid($post_id),
                'is_canonical' => hs_is_canonical_book($post_id)
            );
        }
        wp_reset_postdata();
    }

    // Render books using same layout as search
    if (!empty($books)):
        foreach ($books as $book):
            $isbns = hs_get_book_isbns($book['id']);
            $merge_history = hs_get_book_merge_history($book['id']);
            $thumbnail = get_the_post_thumbnail($book['id'], 'thumbnail', array('class' => 'hs-book-thumbnail'));
            ?>
            <div class="hs-book-item <?php echo $book['is_canonical'] ? 'canonical' : ''; ?>">
                <div class="hs-book-checkbox">
                    <input type="checkbox" class="hs-book-select" name="book_ids[]" value="<?php echo $book['id']; ?>" form="bulk-merge-form">
                </div>

                <div class="hs-book-content">
                    <?php if ($thumbnail): ?>
                        <?php echo $thumbnail; ?>
                    <?php endif; ?>

                    <div class="hs-book-details">
                    <h3>
                        <?php echo esc_html($book['title']); ?>
                        <?php if ($book['is_canonical']): ?>
                            <span style="background: #2271b1; color: white; padding: 2px 8px; font-size: 0.8em; border-radius: 3px;">CANONICAL</span>
                        <?php endif; ?>
                    </h3>

                    <div class="hs-book-meta">
                        <strong>ID:</strong> <?php echo $book['id']; ?> |
                        <strong>Author:</strong> <?php echo esc_html($book['author'] ?: 'Unknown'); ?> |
                        <strong>Pages:</strong> <?php echo $book['page_count'] ?: 'N/A'; ?> |
                        <strong>Added:</strong> <?php echo $book['date']; ?> |
                        <strong>GID:</strong> <?php echo $book['gid'] ?: 'None'; ?>
                    </div>

                    <?php if (!empty($isbns)): ?>
                        <div class="hs-book-meta">
                            <strong>ISBNs (<?php echo count($isbns); ?>):</strong>
                            <ul class="hs-isbn-list">
                                <?php foreach ($isbns as $isbn): ?>
                                    <li class="<?php echo $isbn->is_primary ? 'hs-isbn-primary' : ''; ?>">
                                        <?php echo esc_html($isbn->isbn); ?>
                                        <?php if ($isbn->is_primary): ?>
                                            <span style="color: #2271b1;">(Primary)</span>
                                        <?php endif; ?>
                                        <?php if ($isbn->edition): ?>
                                            - <?php echo esc_html($isbn->edition); ?>
                                        <?php endif; ?>
                                        <?php if ($isbn->publication_year): ?>
                                            (<?php echo $isbn->publication_year; ?>)
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="hs-book-meta">
                            <strong>ISBNs:</strong> None in database
                            <?php if ($book['isbn']): ?>
                                (ACF field has: <?php echo esc_html($book['isbn']); ?>)
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($merge_history)): ?>
                        <div class="hs-book-meta">
                            <strong>Merge History:</strong> This book has <?php echo count($merge_history); ?> merged book(s)
                        </div>
                    <?php endif; ?>
                </div>

                <div class="hs-merge-controls">
                    <div class="hs-button-group">
                        <button type="button" class="button" onclick="toggleMergeForm(<?php echo $book['id']; ?>)">Merge This Book</button>
                        <button type="button" class="button" onclick="toggleISBNForm(<?php echo $book['id']; ?>)">Add ISBN</button>
                        <a href="<?php echo get_edit_post_link($book['id']); ?>" class="button">Edit Book</a>
                        <a href="<?php echo get_permalink($book['id']); ?>" class="button" target="_blank">View</a>
                    </div>

                    <!-- Merge Form -->
                    <div id="merge-form-<?php echo $book['id']; ?>" class="hs-merge-form" style="display: none;">
                        <h4>Merge "<?php echo esc_html($book['title']); ?>" into another book</h4>
                        <form method="post" onsubmit="return confirmMerge('<?php echo esc_js($book['title']); ?>', document.getElementById('target-book-<?php echo $book['id']; ?>').value);">
                            <?php wp_nonce_field('hs_merge_books', 'merge_nonce'); ?>
                            <input type="hidden" name="action" value="merge_books">
                            <input type="hidden" name="from_book_id" value="<?php echo $book['id']; ?>">
                            <input type="hidden" name="current_tab" value="recent">

                            <p>
                                <label><strong>Target Book ID:</strong></label><br>
                                <input type="number" name="to_book_id" id="target-book-<?php echo $book['id']; ?>" required style="width: 150px;">
                                <span class="description">Enter the ID of the book to merge INTO (this book will remain, the current book will be merged)</span>
                            </p>

                            <p>
                                <label>
                                    <input type="checkbox" name="sync_metadata" value="1" checked>
                                    <strong>Sync metadata</strong> - Update this book's title, author, and page count to match the target book
                                </label>
                            </p>

                            <p>
                                <label><strong>Reason (optional):</strong></label><br>
                                <textarea name="reason" style="width: 100%; max-width: 500px;" rows="3" placeholder="Why are these books being merged?"></textarea>
                            </p>

                            <p>
                                <button type="submit" class="button button-primary">Merge Books</button>
                                <button type="button" class="button" onclick="toggleMergeForm(<?php echo $book['id']; ?>)">Cancel</button>
                            </p>
                        </form>
                    </div>

                    <!-- Add ISBN Form -->
                    <div id="isbn-form-<?php echo $book['id']; ?>" class="hs-merge-form" style="display: none;">
                        <h4>Add ISBN to "<?php echo esc_html($book['title']); ?>"</h4>
                        <form method="post">
                            <?php wp_nonce_field('hs_add_isbn', 'isbn_nonce'); ?>
                            <input type="hidden" name="action" value="add_isbn">
                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">

                            <p>
                                <label><strong>ISBN:</strong></label><br>
                                <input type="text" name="isbn" required style="width: 200px;" placeholder="978-0-123456-78-9">
                            </p>

                            <p>
                                <label><strong>Edition (optional):</strong></label><br>
                                <input type="text" name="edition" style="width: 200px;" placeholder="First Edition, Paperback, etc.">
                            </p>

                            <p>
                                <label><strong>Publication Year (optional):</strong></label><br>
                                <input type="number" name="year" style="width: 100px;" placeholder="2024">
                            </p>

                            <p>
                                <label>
                                    <input type="checkbox" name="is_primary" value="1">
                                    Set as primary ISBN
                                </label>
                            </p>

                            <p>
                                <button type="submit" class="button button-primary">Add ISBN</button>
                                <button type="button" class="button" onclick="toggleISBNForm(<?php echo $book['id']; ?>)">Cancel</button>
                            </p>
                        </form>
                    </div>
                </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php
        $total_pages = ceil($total_books / $per_page);
        if ($total_pages > 1):
        ?>
            <div style="margin: 20px 0; text-align: center;">
                <?php
                $base_url = admin_url('edit.php?post_type=book&page=hs-book-merge&tab=recent');

                if ($page > 1):
                    echo '<a href="' . $base_url . '&paged=' . ($page - 1) . '" class="button">Previous</a> ';
                endif;

                echo '<span style="margin: 0 10px;">Page ' . $page . ' of ' . $total_pages . '</span>';

                if ($page < $total_pages):
                    echo ' <a href="' . $base_url . '&paged=' . ($page + 1) . '" class="button">Next</a>';
                endif;
                ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <p>No books found.</p>
    <?php endif;
}

function hs_book_merge_tab_search()
{
    global $wpdb;

    // Get search query
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    // Display notices
    if (isset($_GET['merged']) && $_GET['merged'] === 'success') {
        $count = isset($_GET['count']) ? intval($_GET['count']) : 1;
        $message = $count > 1
            ? '<strong>Success!</strong> ' . $count . ' books have been merged successfully.'
            : '<strong>Success!</strong> Books have been merged successfully.';
        echo '<div class="hs-notice success">' . $message . '</div>';
    } elseif (isset($_GET['error'])) {
        echo '<div class="hs-notice error"><strong>Error:</strong> ' . esc_html(urldecode($_GET['error'])) . '</div>';
    }

    // Get statistics
    $total_books = wp_count_posts('book')->publish;
    $total_isbns = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_isbns");
    $total_gids = $wpdb->get_var("SELECT COUNT(DISTINCT gid) FROM {$wpdb->prefix}hs_gid WHERE post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'book')");

    ?>
    <div class="hs-stats-box">
        <strong>Statistics:</strong>
        <?php echo number_format($total_books); ?> books,
        <?php echo number_format($total_isbns); ?> ISBNs,
        <?php echo number_format($total_gids); ?> book groups (GIDs)
    </div>

    <div class="hs-search-box">
        <h3>Search Books</h3>
        <form method="get" action="">
            <input type="hidden" name="post_type" value="book">
            <input type="hidden" name="page" value="hs-book-merge">
            <input type="hidden" name="tab" value="search">
            <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Search by title, author, or ISBN..." style="width: 400px;">
            <button type="submit" class="button button-primary">Search</button>
            <?php if (!empty($search)): ?>
                <a href="?post_type=book&page=hs-book-merge&tab=search" class="button">Clear</a>
            <?php endif; ?>
        </form>
        <p class="description">Search for books to view their ISBNs, merge duplicates, or manage book data.</p>
    </div>

    <?php if (!empty($search)): ?>
        <?php
        $books = hs_search_books($search, 50);
        ?>
        <?php if (empty($books)): ?>
            <p>No books found matching "<?php echo esc_html($search); ?>".</p>
        <?php else: ?>
            <!-- Bulk Merge Controls -->
            <div class="hs-bulk-merge-box">
                <h3>Bulk Merge Books</h3>
                <p>Select multiple books below and merge them all into a single target book at once.</p>

                <form method="post" id="bulk-merge-form-search" onsubmit="return confirmBulkMerge();">
                    <?php wp_nonce_field('hs_bulk_merge_books', 'bulk_merge_nonce'); ?>
                    <input type="hidden" name="action" value="bulk_merge_books">
                    <input type="hidden" name="current_tab" value="search">

                    <div class="hs-bulk-controls">
                        <div class="hs-bulk-status" id="bulk-status">0 books selected</div>

                        <label>
                            <input type="checkbox" onchange="toggleSelectAll(this)"> Select/Deselect All
                        </label>

                        <label style="display: flex; align-items: center; gap: 5px;">
                            <strong>Target Book ID:</strong>
                            <input type="number" name="target_book_id" id="bulk-target-id" required style="width: 120px;" placeholder="Enter ID">
                        </label>

                        <label>
                            <input type="checkbox" name="sync_metadata" value="1" checked>
                            Sync metadata from target
                        </label>

                        <button type="submit" class="button button-primary" id="bulk-merge-btn" disabled>
                            Merge Selected Books
                        </button>
                    </div>
                </form>
            </div>

            <h3>Search Results (<?php echo count($books); ?> books found)</h3>
            <?php foreach ($books as $book): ?>
                <?php
                $isbns = hs_get_book_isbns($book['id']);
                $merge_history = hs_get_book_merge_history($book['id']);
                $thumbnail = get_the_post_thumbnail($book['id'], 'thumbnail', array('class' => 'hs-book-thumbnail'));
                ?>
                <div class="hs-book-item <?php echo $book['is_canonical'] ? 'canonical' : ''; ?>">
                    <div class="hs-book-checkbox">
                        <input type="checkbox" class="hs-book-select" name="book_ids[]" value="<?php echo $book['id']; ?>" form="bulk-merge-form-search">
                    </div>

                    <div class="hs-book-content">
                        <?php if ($thumbnail): ?>
                            <?php echo $thumbnail; ?>
                        <?php endif; ?>

                        <div class="hs-book-details">
                            <h3>
                            <?php echo esc_html($book['title']); ?>
                            <?php if ($book['is_canonical']): ?>
                                <span style="background: #2271b1; color: white; padding: 2px 8px; font-size: 0.8em; border-radius: 3px;">CANONICAL</span>
                            <?php endif; ?>
                        </h3>

                        <div class="hs-book-meta">
                            <strong>ID:</strong> <?php echo $book['id']; ?> |
                            <strong>Author:</strong> <?php echo esc_html($book['author'] ?: 'Unknown'); ?> |
                            <strong>Pages:</strong> <?php echo $book['page_count'] ?: 'N/A'; ?> |
                            <strong>GID:</strong> <?php echo $book['gid'] ?: 'None'; ?>
                        </div>

                        <?php if (!empty($isbns)): ?>
                            <div class="hs-book-meta">
                                <strong>ISBNs (<?php echo count($isbns); ?>):</strong>
                                <ul class="hs-isbn-list">
                                    <?php foreach ($isbns as $isbn): ?>
                                        <li class="<?php echo $isbn->is_primary ? 'hs-isbn-primary' : ''; ?>">
                                            <?php echo esc_html($isbn->isbn); ?>
                                            <?php if ($isbn->is_primary): ?>
                                                <span style="color: #2271b1;">(Primary)</span>
                                            <?php endif; ?>
                                            <?php if ($isbn->edition): ?>
                                                - <?php echo esc_html($isbn->edition); ?>
                                            <?php endif; ?>
                                            <?php if ($isbn->publication_year): ?>
                                                (<?php echo $isbn->publication_year; ?>)
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="hs-book-meta">
                                <strong>ISBNs:</strong> None found
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($merge_history)): ?>
                            <div class="hs-book-meta">
                                <strong>Merge History:</strong> This book has <?php echo count($merge_history); ?> merged book(s)
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="hs-merge-controls">
                        <div class="hs-button-group">
                            <button type="button" class="button" onclick="toggleMergeForm(<?php echo $book['id']; ?>)">Merge This Book</button>
                            <button type="button" class="button" onclick="toggleISBNForm(<?php echo $book['id']; ?>)">Add ISBN</button>
                            <a href="<?php echo get_edit_post_link($book['id']); ?>" class="button">Edit Book</a>
                            <a href="<?php echo get_permalink($book['id']); ?>" class="button" target="_blank">View</a>
                        </div>

                        <!-- Merge Form -->
                        <div id="merge-form-<?php echo $book['id']; ?>" class="hs-merge-form" style="display: none;">
                            <h4>Merge "<?php echo esc_html($book['title']); ?>" into another book</h4>
                            <form method="post" onsubmit="return confirmMerge('<?php echo esc_js($book['title']); ?>', document.getElementById('target-book-<?php echo $book['id']; ?>').value);">
                                <?php wp_nonce_field('hs_merge_books', 'merge_nonce'); ?>
                                <input type="hidden" name="action" value="merge_books">
                                <input type="hidden" name="from_book_id" value="<?php echo $book['id']; ?>">
                                <input type="hidden" name="current_tab" value="search">

                                <p>
                                    <label><strong>Target Book ID:</strong></label><br>
                                    <input type="number" name="to_book_id" id="target-book-<?php echo $book['id']; ?>" required style="width: 150px;">
                                    <span class="description">Enter the ID of the book to merge INTO (this book will remain, the current book will be merged)</span>
                                </p>

                                <p>
                                    <label>
                                        <input type="checkbox" name="sync_metadata" value="1" checked>
                                        <strong>Sync metadata</strong> - Update this book's title, author, and page count to match the target book
                                    </label>
                                </p>

                                <p>
                                    <label><strong>Reason (optional):</strong></label><br>
                                    <textarea name="reason" style="width: 100%; max-width: 500px;" rows="3" placeholder="Why are these books being merged?"></textarea>
                                </p>

                                <p>
                                    <button type="submit" class="button button-primary">Merge Books</button>
                                    <button type="button" class="button" onclick="toggleMergeForm(<?php echo $book['id']; ?>)">Cancel</button>
                                </p>
                            </form>
                        </div>

                        <!-- Add ISBN Form -->
                        <div id="isbn-form-<?php echo $book['id']; ?>" class="hs-merge-form" style="display: none;">
                            <h4>Add ISBN to "<?php echo esc_html($book['title']); ?>"</h4>
                            <form method="post">
                                <?php wp_nonce_field('hs_add_isbn', 'isbn_nonce'); ?>
                                <input type="hidden" name="action" value="add_isbn">
                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">

                                <p>
                                    <label><strong>ISBN:</strong></label><br>
                                    <input type="text" name="isbn" required style="width: 200px;" placeholder="978-0-123456-78-9">
                                </p>

                                <p>
                                    <label><strong>Edition (optional):</strong></label><br>
                                    <input type="text" name="edition" style="width: 200px;" placeholder="First Edition, Paperback, etc.">
                                </p>

                                <p>
                                    <label><strong>Publication Year (optional):</strong></label><br>
                                    <input type="number" name="year" style="width: 100px;" placeholder="2024">
                                </p>

                                <p>
                                    <label>
                                        <input type="checkbox" name="is_primary" value="1">
                                        Set as primary ISBN
                                    </label>
                                </p>

                                <p>
                                    <button type="submit" class="button button-primary">Add ISBN</button>
                                    <button type="button" class="button" onclick="toggleISBNForm(<?php echo $book['id']; ?>)">Cancel</button>
                                </p>
                            </form>
                        </div>
                    </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <p>Use the search box above to find books by title, author, or ISBN. You can then merge duplicate books or manage their ISBNs.</p>
    <?php endif; ?>
    <?php
}

function hs_book_merge_tab_isbn()
{
    global $wpdb;

    // Get book ID if specified
    $book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;

    ?>
    <div class="hs-search-box">
        <h3>ISBN Management</h3>
        <form method="get" action="">
            <input type="hidden" name="post_type" value="book">
            <input type="hidden" name="page" value="hs-book-merge">
            <input type="hidden" name="tab" value="isbn">
            <label><strong>Book ID:</strong></label>
            <input type="number" name="book_id" value="<?php echo $book_id; ?>" placeholder="Enter book ID..." style="width: 150px;">
            <button type="submit" class="button button-primary">Load Book</button>
        </form>
    </div>

    <?php if ($book_id): ?>
        <?php
        $book = get_post($book_id);
        if ($book && $book->post_type === 'book'):
            $isbns = hs_get_book_isbns($book_id);
            $gid = hs_get_gid($book_id);
        ?>
            <div class="hs-book-item">
                <h3><?php echo esc_html($book->post_title); ?></h3>
                <div class="hs-book-meta">
                    <strong>ID:</strong> <?php echo $book_id; ?> |
                    <strong>GID:</strong> <?php echo $gid ?: 'None'; ?> |
                    <strong>Author:</strong> <?php echo esc_html(get_field('book_author', $book_id) ?: 'Unknown'); ?>
                </div>

                <h4>ISBNs for this book:</h4>
                <?php if (!empty($isbns)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>ISBN</th>
                                <th>Edition</th>
                                <th>Year</th>
                                <th>Primary</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($isbns as $isbn): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($isbn->isbn); ?></strong></td>
                                    <td><?php echo esc_html($isbn->edition ?: '-'); ?></td>
                                    <td><?php echo $isbn->publication_year ?: '-'; ?></td>
                                    <td><?php echo $isbn->is_primary ? '<span style="color: #2271b1;">✓ Primary</span>' : '-'; ?></td>
                                    <td>
                                        <?php if (!$isbn->is_primary): ?>
                                            <form method="post" style="display: inline;">
                                                <?php wp_nonce_field('hs_set_primary_isbn', 'primary_nonce'); ?>
                                                <input type="hidden" name="action" value="set_primary_isbn">
                                                <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                                                <input type="hidden" name="isbn" value="<?php echo esc_attr($isbn->isbn); ?>">
                                                <button type="submit" class="button button-small">Set as Primary</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this ISBN?');">
                                            <?php wp_nonce_field('hs_remove_isbn', 'remove_nonce'); ?>
                                            <input type="hidden" name="action" value="remove_isbn">
                                            <input type="hidden" name="isbn" value="<?php echo esc_attr($isbn->isbn); ?>">
                                            <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                                            <button type="submit" class="button button-small">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No ISBNs found for this book.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>Book not found.</p>
        <?php endif; ?>
    <?php endif; ?>
    <?php
}

function hs_book_merge_tab_duplicate_reports()
{
    global $wpdb;

    $reports = $wpdb->get_results(
        "SELECT dr.*, p.post_title, u.display_name as reporter_name
        FROM {$wpdb->prefix}hs_duplicate_reports dr
        LEFT JOIN {$wpdb->posts} p ON dr.primary_book_id = p.ID
        LEFT JOIN {$wpdb->users} u ON dr.reporter_id = u.ID
        WHERE dr.status = 'pending'
        ORDER BY dr.date_reported DESC
        LIMIT 100"
    );

    ?>
    <h3>Pending Duplicate Reports</h3>
    <?php if (empty($reports)): ?>
        <p>No pending duplicate reports.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Reported By</th>
                    <th>Date</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($report->post_title); ?></strong><br>
                            <span class="description">ID: <?php echo $report->primary_book_id; ?></span>
                        </td>
                        <td><?php echo esc_html($report->reporter_name); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($report->date_reported)); ?></td>
                        <td><?php echo esc_html($report->reason); ?></td>
                        <td>
                            <a href="?post_type=book&page=hs-book-merge&tab=search&search=<?php echo $report->primary_book_id; ?>" class="button button-small">View Book</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php
}

// Handle form submissions on admin_init (before any output)
add_action('admin_init', 'hs_book_merge_check_post');

function hs_book_merge_check_post()
{
    // Only run on our admin page
    if (!isset($_GET['page']) || $_GET['page'] !== 'hs-book-merge') {
        return;
    }

    // Only if POST request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        hs_book_merge_handle_post();
    }
}

function hs_book_merge_handle_post()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'merge_books':
            if (!isset($_POST['merge_nonce']) || !wp_verify_nonce($_POST['merge_nonce'], 'hs_merge_books')) {
                wp_die('Invalid nonce');
            }

            $from_book_id = intval($_POST['from_book_id']);
            $to_book_id = intval($_POST['to_book_id']);
            $sync_metadata = isset($_POST['sync_metadata']) && $_POST['sync_metadata'] === '1';
            $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';

            $result = hs_merge_books($from_book_id, $to_book_id, $sync_metadata, $reason);

            // Determine which tab to redirect to
            $redirect_tab = isset($_POST['current_tab']) ? sanitize_text_field($_POST['current_tab']) : 'recent';

            if (is_wp_error($result)) {
                wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=' . $redirect_tab . '&error=' . urlencode($result->get_error_message())));
            } else {
                wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=' . $redirect_tab . '&merged=success'));
            }
            exit;

        case 'add_isbn':
            if (!isset($_POST['isbn_nonce']) || !wp_verify_nonce($_POST['isbn_nonce'], 'hs_add_isbn')) {
                wp_die('Invalid nonce');
            }

            $book_id = intval($_POST['book_id']);
            $isbn = sanitize_text_field($_POST['isbn']);
            $edition = sanitize_text_field($_POST['edition']);
            $year = !empty($_POST['year']) ? intval($_POST['year']) : null;
            $is_primary = isset($_POST['is_primary']) && $_POST['is_primary'] === '1';

            $result = hs_add_isbn_to_book($book_id, $isbn, $edition, $year, $is_primary);

            if (is_wp_error($result)) {
                wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=search&error=' . urlencode($result->get_error_message())));
            } else {
                wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=search&search=' . $book_id));
            }
            exit;

        case 'set_primary_isbn':
            if (!isset($_POST['primary_nonce']) || !wp_verify_nonce($_POST['primary_nonce'], 'hs_set_primary_isbn')) {
                wp_die('Invalid nonce');
            }

            $book_id = intval($_POST['book_id']);
            $isbn = sanitize_text_field($_POST['isbn']);

            hs_set_primary_isbn($book_id, $isbn);

            wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=isbn&book_id=' . $book_id));
            exit;

        case 'remove_isbn':
            if (!isset($_POST['remove_nonce']) || !wp_verify_nonce($_POST['remove_nonce'], 'hs_remove_isbn')) {
                wp_die('Invalid nonce');
            }

            $isbn = sanitize_text_field($_POST['isbn']);
            $book_id = intval($_POST['book_id']);

            hs_remove_isbn($isbn);

            wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=isbn&book_id=' . $book_id));
            exit;

        case 'bulk_merge_books':
            if (!isset($_POST['bulk_merge_nonce']) || !wp_verify_nonce($_POST['bulk_merge_nonce'], 'hs_bulk_merge_books')) {
                wp_die('Invalid nonce');
            }

            $target_book_id = intval($_POST['target_book_id']);
            $book_ids = isset($_POST['book_ids']) ? array_map('intval', $_POST['book_ids']) : array();
            $sync_metadata = isset($_POST['sync_metadata']) && $_POST['sync_metadata'] === '1';
            $redirect_tab = isset($_POST['current_tab']) ? sanitize_text_field($_POST['current_tab']) : 'recent';

            // Validate we have books to merge
            if (empty($book_ids)) {
                wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=' . $redirect_tab . '&error=' . urlencode('No books selected')));
                exit;
            }

            if (!$target_book_id) {
                wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=' . $redirect_tab . '&error=' . urlencode('No target book specified')));
                exit;
            }

            // Remove target book from the list if it was selected
            $book_ids = array_diff($book_ids, array($target_book_id));

            if (empty($book_ids)) {
                wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=' . $redirect_tab . '&error=' . urlencode('No books to merge (target cannot merge with itself)')));
                exit;
            }

            // Merge each book into the target
            $errors = array();
            $success_count = 0;

            foreach ($book_ids as $from_book_id) {
                $result = hs_merge_books($from_book_id, $target_book_id, $sync_metadata, 'Bulk merge operation');

                if (is_wp_error($result)) {
                    $errors[] = 'Book ' . $from_book_id . ': ' . $result->get_error_message();
                } else {
                    $success_count++;
                }
            }

            // Redirect with results
            if (!empty($errors)) {
                $error_msg = 'Merged ' . $success_count . ' book(s). Errors: ' . implode('; ', $errors);
                wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=' . $redirect_tab . '&error=' . urlencode($error_msg)));
            } else {
                wp_redirect(admin_url('edit.php?post_type=book&page=hs-book-merge&tab=' . $redirect_tab . '&merged=success&count=' . $success_count));
            }
            exit;
    }
}
