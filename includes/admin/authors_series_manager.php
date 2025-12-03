<?php

// Rewrite

// The admin interface for author and series management


if (!defined('ABSPATH'))
{
	exit;
}


// Add the menu
add_action('admin_menu', 'hs_authors_series_admin_menu');

function hs_authors_series_admin_menu()
{
	add_submenu_page(
		'hotsoup-admin',
		'Authors and Series Manager',
		'Authors/Series',
		'manage_options',
		'hs-authors-series',
		'hs_authors_series_admin_page'
	);
}


function hs_authors_series_admin_page() {
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        hs_authors_series_handle_post();
    }

    // Get current tab
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'authors';

    ?>
    <div class="wrap">
        <h1>Authors & Series Manager</h1>

        <h2 class="nav-tab-wrapper">
            <a href="?post_type=book&page=hs-authors-series&tab=authors" class="nav-tab <?php echo $tab === 'authors' ? 'nav-tab-active' : ''; ?>">Authors</a>
            <a href="?post_type=book&page=hs-authors-series&tab=series" class="nav-tab <?php echo $tab === 'series' ? 'nav-tab-active' : ''; ?>">Series</a>
            <a href="?post_type=book&page=hs-authors-series&tab=migrate" class="nav-tab <?php echo $tab === 'migrate' ? 'nav-tab-active' : ''; ?>">Migration</a>
        </h2>

        <div class="tab-content">
            <?php
            switch ($tab) {
                case 'series':
                    hs_authors_series_tab_series();
                    break;
                case 'migrate':
                    hs_authors_series_tab_migrate();
                    break;
                default:
                    hs_authors_series_tab_authors();
                    break;
            }
            ?>
        </div>
    </div>

    <style>
        .hs-author-item, .hs-series-item {
            padding: 15px;
            margin: 10px 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .hs-author-item h3, .hs-series-item h3 {
            margin-top: 0;
        }
        .hs-author-meta, .hs-series-meta {
            color: #666;
            font-size: 0.9em;
            margin: 5px 0;
        }
        .hs-alias-list {
            margin: 10px 0;
            padding-left: 20px;
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
        .hs-migration-progress {
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 20px 0;
        }
        .hs-progress-bar {
            width: 100%;
            height: 30px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        .hs-progress-fill {
            height: 100%;
            background: #2271b1;
            transition: width 0.3s ease;
        }
    </style>
    <?php
}

function hs_authors_series_tab_authors() {
    global $wpdb;

    // Get search query
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    // Get authors
    if (!empty($search)) {
        $authors = hs_search_authors($search, 100);
    } else {
        $authors = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}hs_authors ORDER BY name LIMIT 100"
        );
    }

    // Get total author count
    $total_authors = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_authors");
    $total_aliases = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_author_aliases");

    ?>
    <div class="hs-stats-box">
        <strong>Statistics:</strong>
        <?php echo number_format($total_authors); ?> authors,
        <?php echo number_format($total_aliases); ?> aliases
    </div>

    <div class="hs-search-box">
        <form method="get" action="">
            <input type="hidden" name="post_type" value="book">
            <input type="hidden" name="page" value="hs-authors-series">
            <input type="hidden" name="tab" value="authors">
            <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Search authors..." style="width: 300px;">
            <button type="submit" class="button">Search</button>
            <?php if (!empty($search)): ?>
                <a href="?post_type=book&page=hs-authors-series&tab=authors" class="button">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($authors)): ?>
        <p>No authors found. <?php if (empty($search)) echo 'Run the migration to import existing authors.'; ?></p>
    <?php else: ?>
        <?php foreach ($authors as $author): ?>
            <?php
            $book_count = hs_get_author_book_count($author->id);
            $aliases = hs_get_author_aliases($author->id);
            ?>
            <div class="hs-author-item">
                <h3><?php echo esc_html($author->name); ?></h3>

                <div class="hs-author-meta">
                    <strong>ID:</strong> <?php echo $author->id; ?> |
                    <strong>Canonical Name:</strong> <?php echo esc_html($author->canonical_name); ?> |
                    <strong>Books:</strong> <?php echo $book_count; ?>
                </div>

                <?php if (!empty($aliases)): ?>
                    <div class="hs-author-meta">
                        <strong>Aliases:</strong>
                        <ul class="hs-alias-list">
                            <?php foreach ($aliases as $alias): ?>
                                <li>
                                    <?php echo esc_html($alias->alias_name); ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_alias">
                                        <input type="hidden" name="alias_id" value="<?php echo $alias->id; ?>">
                                        <button type="submit" class="button button-small" onclick="return confirm('Delete this alias?');">Delete</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="hs-button-group">
                    <button type="button" class="button" onclick="toggleAddAlias(<?php echo $author->id; ?>)">Add Alias</button>
                    <button type="button" class="button" onclick="toggleMerge(<?php echo $author->id; ?>)">Merge Author</button>
                    <a href="?post_type=book&page=hs-authors-series&tab=authors&view_books=<?php echo $author->id; ?>" class="button">View Books (<?php echo $book_count; ?>)</a>
                    <?php if ($book_count === 0): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="delete_author">
                            <input type="hidden" name="author_id" value="<?php echo $author->id; ?>">
                            <button type="submit" class="button button-link-delete" onclick="return confirm('Delete this author?');">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Add Alias Form -->
                <div id="add-alias-<?php echo $author->id; ?>" style="display: none;" class="hs-merge-form">
                    <h4>Add Alias for <?php echo esc_html($author->name); ?></h4>
                    <form method="post">
                        <input type="hidden" name="action" value="add_alias">
                        <input type="hidden" name="author_id" value="<?php echo $author->id; ?>">
                        <p>
                            <label>Alias Name:</label><br>
                            <input type="text" name="alias_name" required style="width: 300px;">
                        </p>
                        <button type="submit" class="button button-primary">Add Alias</button>
                        <button type="button" class="button" onclick="toggleAddAlias(<?php echo $author->id; ?>)">Cancel</button>
                    </form>
                </div>

                <!-- Merge Form -->
                <div id="merge-<?php echo $author->id; ?>" style="display: none;" class="hs-merge-form">
                    <h4>Merge <?php echo esc_html($author->name); ?> into another author</h4>
                    <form method="post">
                        <input type="hidden" name="action" value="merge_authors">
                        <input type="hidden" name="from_author_id" value="<?php echo $author->id; ?>">
                        <p>
                            <label>Target Author ID:</label><br>
                            <input type="number" name="to_author_id" required style="width: 200px;">
                            <small>Enter the ID of the author to merge into (this author will be deleted)</small>
                        </p>
                        <p>
                            <label>Reason (optional):</label><br>
                            <input type="text" name="merge_reason" style="width: 400px;" placeholder="e.g., R.L. Stine and Robert Lawrence Stine are the same person">
                        </p>
                        <button type="submit" class="button button-primary" onclick="return confirm('This will merge all books and delete this author. Continue?');">Merge Authors</button>
                        <button type="button" class="button" onclick="toggleMerge(<?php echo $author->id; ?>)">Cancel</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        function toggleAddAlias(authorId) {
            var form = document.getElementById('add-alias-' + authorId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function toggleMerge(authorId) {
            var form = document.getElementById('merge-' + authorId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
    <?php

    // Show books if viewing an author
    if (isset($_GET['view_books'])) {
        $author_id = intval($_GET['view_books']);
        $author = hs_get_author($author_id);
        if ($author) {
            $books = hs_get_author_books($author_id);
            ?>
            <div class="hs-stats-box">
                <h2>Books by <?php echo esc_html($author->name); ?></h2>
                <?php if (empty($books)): ?>
                    <p>No books found for this author.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($books as $book): ?>
                            <li>
                                <a href="<?php echo get_edit_post_link($book->ID); ?>" target="_blank">
                                    <?php echo esc_html($book->post_title); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php
        }
    }
}

function hs_authors_series_tab_series() {
    global $wpdb;

    // Get search query
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    // Get series
    if (!empty($search)) {
        $series_list = hs_search_series($search, 100);
    } else {
        $series_list = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}hs_series ORDER BY name LIMIT 100"
        );
    }

    $total_series = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_series");

    ?>
    <div class="hs-stats-box">
        <strong>Statistics:</strong> <?php echo number_format($total_series); ?> series
    </div>

    <div class="hs-search-box">
        <form method="get" action="">
            <input type="hidden" name="post_type" value="book">
            <input type="hidden" name="page" value="hs-authors-series">
            <input type="hidden" name="tab" value="series">
            <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Search series..." style="width: 300px;">
            <button type="submit" class="button">Search</button>
            <?php if (!empty($search)): ?>
                <a href="?post_type=book&page=hs-authors-series&tab=series" class="button">Clear</a>
            <?php endif; ?>
        </form>

        <form method="post" style="margin-top: 15px;">
            <input type="hidden" name="action" value="create_series">
            <input type="text" name="series_name" placeholder="New series name..." style="width: 300px;" required>
            <button type="submit" class="button button-primary">Create Series</button>
        </form>
    </div>

    <?php if (empty($series_list)): ?>
        <p>No series found.</p>
    <?php else: ?>
        <?php foreach ($series_list as $series): ?>
            <?php $books = hs_get_series_books($series->id); ?>
            <div class="hs-series-item">
                <h3><?php echo esc_html($series->name); ?></h3>

                <div class="hs-series-meta">
                    <strong>ID:</strong> <?php echo $series->id; ?> |
                    <strong>Books:</strong> <?php echo $series->total_books; ?>
                </div>

                <?php if (!empty($series->description)): ?>
                    <p><?php echo esc_html($series->description); ?></p>
                <?php endif; ?>

                <?php if (!empty($books)): ?>
                    <div class="hs-author-meta">
                        <strong>Books in series:</strong>
                        <ul class="hs-alias-list">
                            <?php foreach ($books as $book): ?>
                                <li>
                                    <?php if ($book->position): ?>
                                        <strong>#<?php echo $book->position; ?></strong>
                                    <?php endif; ?>
                                    <a href="<?php echo get_edit_post_link($book->ID); ?>" target="_blank">
                                        <?php echo esc_html($book->post_title); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="hs-button-group">
                    <?php if ($series->total_books === 0): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="delete_series">
                            <input type="hidden" name="series_id" value="<?php echo $series->id; ?>">
                            <button type="submit" class="button button-link-delete" onclick="return confirm('Delete this series?');">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
}

// Migration tab
function hs_authors_series_tab_migrate() {
    global $wpdb;

    $total_books = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'book'");
    $books_with_authors = $wpdb->get_var("SELECT COUNT(DISTINCT book_id) FROM {$wpdb->prefix}hs_book_authors");
    $remaining = $total_books - $books_with_authors;

    ?>
    <div class="hs-stats-box">
        <h2>Author Migration Status</h2>
        <p><strong>Total Books:</strong> <?php echo number_format($total_books); ?></p>
        <p><strong>Books with Author IDs:</strong> <?php echo number_format($books_with_authors); ?></p>
        <p><strong>Books Remaining:</strong> <?php echo number_format($remaining); ?></p>
    </div>

    <div class="hs-migration-progress">
        <h3>Migrate Existing Books to Author ID System</h3>
        <p>This will process all books that don't have author relationships yet and create author records based on the book_author field.</p>
        <p><strong>Important:</strong> This will NOT overwrite existing author relationships, so it's safe to run multiple times.</p>

        <div id="migration-progress" style="display: none;">
            <div class="hs-progress-bar">
                <div id="progress-fill" class="hs-progress-fill" style="width: 0%;"></div>
            </div>
            <p id="progress-text">Processing...</p>
        </div>

        <button type="button" id="start-migration" class="button button-primary button-hero" onclick="startMigration()">
            Start Migration
        </button>
    </div>

    <script>
        let migrationOffset = 0;
        let migrationTotal = <?php echo $total_books; ?>;
        let migrationRunning = false;

        function startMigration() {
            if (migrationRunning) return;

            if (!confirm('This will process all books and create author records. Continue?')) {
                return;
            }

            migrationRunning = true;
            migrationOffset = 0;
            document.getElementById('start-migration').disabled = true;
            document.getElementById('migration-progress').style.display = 'block';

            processBatch();
        }

        function processBatch() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'hs_migrate_authors',
                    offset: migrationOffset,
                    batch_size: 50
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    migrationOffset = data.data.offset;
                    let percent = (migrationOffset / migrationTotal) * 100;
                    document.getElementById('progress-fill').style.width = percent + '%';
                    document.getElementById('progress-text').innerHTML =
                        'Processed: ' + data.data.processed + ' | ' +
                        'Created Authors: ' + data.data.created_authors + ' | ' +
                        'Created Links: ' + data.data.created_links + ' | ' +
                        'Remaining: ' + data.data.remaining;

                    if (!data.data.complete) {
                        setTimeout(processBatch, 100);
                    } else {
                        migrationRunning = false;
                        document.getElementById('progress-text').innerHTML += '<br><strong>Migration Complete!</strong>';
                        document.getElementById('start-migration').disabled = false;
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    alert('Migration failed: ' + data.data);
                    migrationRunning = false;
                    document.getElementById('start-migration').disabled = false;
                }
            })
            .catch(error => {
                alert('Migration error: ' + error);
                migrationRunning = false;
                document.getElementById('start-migration').disabled = false;
            });
        }
    </script>
    <?php
}

function hs_authors_series_handle_post() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'add_alias':
            $author_id = intval($_POST['author_id']);
            $alias_name = sanitize_text_field($_POST['alias_name']);
            if (hs_add_author_alias($author_id, $alias_name)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>Alias added successfully!</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Failed to add alias.</p></div>';
                });
            }
            break;

        case 'delete_alias':
            $alias_id = intval($_POST['alias_id']);
            if (hs_delete_author_alias($alias_id)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>Alias deleted successfully!</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Failed to delete alias.</p></div>';
                });
            }
            break;

        case 'merge_authors':
            $from_author_id = intval($_POST['from_author_id']);
            $to_author_id = intval($_POST['to_author_id']);
            $merge_reason = sanitize_text_field($_POST['merge_reason']);
            if (hs_merge_authors($from_author_id, $to_author_id, $merge_reason)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>Authors merged successfully!</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Failed to merge authors.</p></div>';
                });
            }
            break;

        case 'delete_author':
            $author_id = intval($_POST['author_id']);
            if (hs_delete_author($author_id)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>Author deleted successfully!</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Failed to delete author. Make sure they have no books.</p></div>';
                });
            }
            break;

        case 'create_series':
            $series_name = sanitize_text_field($_POST['series_name']);
            if (hs_create_series($series_name)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>Series created successfully!</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Failed to create series.</p></div>';
                });
            }
            break;

        case 'delete_series':
            $series_id = intval($_POST['series_id']);
            if (hs_delete_series($series_id)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>Series deleted successfully!</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Failed to delete series. Make sure it has no books.</p></div>';
                });
            }
            break;
    }
}

// AJAX handler for migration
add_action('wp_ajax_hs_migrate_authors', 'hs_migrate_authors_ajax');

function hs_migrate_authors_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;

    $result = hs_migrate_book_authors($batch_size, $offset);

    wp_send_json_success($result);
}
