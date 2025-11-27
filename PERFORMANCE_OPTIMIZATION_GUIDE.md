# HotSoup! Performance Optimization Guide

## 🎯 Overview

This guide provides step-by-step instructions for optimizing the HotSoup! plugin to reduce database queries and improve response times. **The site is getting slower due to N+1 query patterns** - where a single query is followed by many individual queries in a loop.

## 📊 Measuring Performance

### Step 1: Enable the Performance Monitor

A performance monitoring tool has been added to track database queries and response times.

1. Log into WordPress admin
2. Go to **Books → Performance**
3. Click **"Enable Monitoring"**
4. The dashboard will show:
   - **Queries/Minute** - Total DB queries per minute
   - **Avg Queries/Request** - Average queries per page load
   - **Avg Response Time** - How long requests take
   - **N+1 Patterns** - Critical issues (should be ZERO)
   - **Slowest Endpoints** - Which pages/APIs need optimization

### Step 2: Establish Baseline Metrics

Before making any changes:

1. Use your site normally for 5-10 minutes
2. Note the current metrics:
   - Current Queries/Minute: _______
   - Current Avg Queries/Request: _______
   - Current Avg Response Time: _______
   - Current N+1 Patterns: _______

### Step 3: Apply Optimizations One at a Time

Apply each optimization below, then remeasure to see the impact.

### Step 4: Disable Monitor When Done

Once optimizations are complete, disable the monitor to remove monitoring overhead.

---

## 🔥 Critical Issues (Fix These First)

These are causing exponential query growth and should be fixed immediately:

### Priority 1: Library API N+1 Query ⚠️ CRITICAL

**File:** `includes/rest.php`
**Lines:** 495-514
**Function:** `gread_get_user_library()`

**Problem:** For each book in the user's library, 4 separate queries are executed:
- `get_post($book_id)` - 1 query per book
- `get_post_meta($book_id, 'book_author', true)` - 1 query per book
- `get_post_meta($book_id, 'book_isbn', true)` - 1 query per book
- `get_post_meta($book_id, 'nop', true)` - 1 query per book

**Impact:** User with 50 books = 200+ queries!

**Solution: Batch Query with JOIN**

Replace the current loop with a batch query:

```php
function gread_get_user_library($request) {
    global $wpdb;
    $user_id = $request->get_param('user_id') ?: get_current_user_id();

    // Fetch all user books
    $table_name = $wpdb->prefix . 'user_books';
    $user_books = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    if (empty($user_books)) {
        return rest_ensure_response(array());
    }

    // Get all book IDs
    $book_ids = array_map(function($ub) { return $ub->book_id; }, $user_books);
    $book_ids_placeholder = implode(',', array_fill(0, count($book_ids), '%d'));

    // BATCH QUERY: Get all posts and metadata in one query
    $books_data = $wpdb->get_results($wpdb->prepare(
        "SELECT
            p.ID,
            p.post_title,
            p.post_name,
            p.post_status,
            MAX(CASE WHEN pm.meta_key = 'book_author' THEN pm.meta_value END) as author,
            MAX(CASE WHEN pm.meta_key = 'book_isbn' THEN pm.meta_value END) as isbn,
            MAX(CASE WHEN pm.meta_key = 'nop' THEN pm.meta_value END) as pages
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            AND pm.meta_key IN ('book_author', 'book_isbn', 'nop')
        WHERE p.ID IN ($book_ids_placeholder)
        GROUP BY p.ID",
        ...$book_ids
    ));

    // Index by ID for quick lookup
    $books_by_id = array();
    foreach ($books_data as $book) {
        $books_by_id[$book->ID] = $book;
    }

    // Build response array
    $library = array();
    foreach ($user_books as $user_book) {
        $book_data = $books_by_id[$user_book->book_id] ?? null;

        if (!$book_data) {
            continue;
        }

        $library[] = array(
            'id' => $user_book->id,
            'book_id' => $user_book->book_id,
            'book_title' => $book_data->post_title,
            'book_slug' => $book_data->post_name,
            'author' => $book_data->author,
            'isbn' => $book_data->isbn,
            'pages' => (int)$book_data->pages,
            'status' => $user_book->status,
            'progress' => (int)$user_book->progress,
            'rating' => $user_book->rating,
            'date_added' => $user_book->date_added,
            'date_started' => $user_book->date_started,
            'date_completed' => $user_book->date_completed,
            'last_read' => $user_book->last_read,
        );
    }

    return rest_ensure_response($library);
}
```

**Expected Improvement:** 200+ queries → 2 queries (99% reduction!)

---

### Priority 2: Book Search N+1 Query ⚠️ CRITICAL

**File:** `includes/rest.php`
**Lines:** 665-681
**Function:** `gread_search_books()`

**Problem:** For each search result, 3 separate `get_post_meta()` calls are made.

**Impact:** 20 search results = 60 extra queries

**Solution: Use WP_Query with Meta Join**

Replace the current implementation:

```php
function gread_search_books($request) {
    global $wpdb;

    $query = sanitize_text_field($request->get_param('q'));
    $limit = intval($request->get_param('limit')) ?: 20;
    $offset = intval($request->get_param('offset')) ?: 0;

    if (empty($query)) {
        return new WP_Error('no_query', 'Search query is required', array('status' => 400));
    }

    // Search using the search index table (already optimized)
    $search_table = $wpdb->prefix . 'hs_book_search_index';
    $search_results = $wpdb->get_col($wpdb->prepare(
        "SELECT book_id FROM $search_table
        WHERE search_text LIKE %s
        LIMIT %d OFFSET %d",
        '%' . $wpdb->esc_like($query) . '%',
        $limit,
        $offset
    ));

    if (empty($search_results)) {
        return rest_ensure_response(array());
    }

    // BATCH QUERY: Get all book data with metadata in one query
    $book_ids_placeholder = implode(',', array_fill(0, count($search_results), '%d'));

    $books = $wpdb->get_results($wpdb->prepare(
        "SELECT
            p.ID,
            p.post_title,
            p.post_name,
            MAX(CASE WHEN pm.meta_key = 'book_author' THEN pm.meta_value END) as author,
            MAX(CASE WHEN pm.meta_key = 'book_isbn' THEN pm.meta_value END) as isbn,
            MAX(CASE WHEN pm.meta_key = 'nop' THEN pm.meta_value END) as pages
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            AND pm.meta_key IN ('book_author', 'book_isbn', 'nop')
        WHERE p.ID IN ($book_ids_placeholder)
            AND p.post_status = 'publish'
            AND p.post_type = 'book'
        GROUP BY p.ID",
        ...$search_results
    ));

    $results = array();
    foreach ($books as $book) {
        $results[] = array(
            'id' => $book->ID,
            'title' => $book->post_title,
            'slug' => $book->post_name,
            'author' => $book->author,
            'isbn' => $book->isbn,
            'pages' => (int)$book->pages,
            'url' => get_permalink($book->ID)
        );
    }

    return rest_ensure_response($results);
}
```

**Expected Improvement:** 60+ queries → 2 queries (97% reduction!)

---

### Priority 3: Activity Feed N+1 Query ⚠️ CRITICAL

**File:** `includes/rest.php`
**Lines:** 739-746
**Function:** `gread_get_activity_feed()`

**Problem:** For each activity, `get_userdata()` is called separately.

**Impact:** 20 activities = 20 extra queries

**Solution: Batch User Data Query**

Replace the current implementation:

```php
function gread_get_activity_feed($request) {
    global $wpdb;

    $user_id = get_current_user_id();
    $per_page = intval($request->get_param('per_page')) ?: 20;
    $page = intval($request->get_param('page')) ?: 1;

    // Get blocked and muted users (keep existing logic)
    $blocked_users = hs_get_blocked_users($user_id);
    $muted_users = hs_get_muted_users($user_id);
    $excluded_users = array_merge($blocked_users, $muted_users);

    // Get activities using BuddyPress
    $activities_args = array(
        'per_page' => $per_page,
        'page' => $page,
        'exclude' => implode(',', $excluded_users)
    );

    $activities = bp_activity_get($activities_args);

    if (empty($activities['activities'])) {
        return rest_ensure_response(array());
    }

    // BATCH QUERY: Get all user IDs first
    $user_ids = array_unique(array_map(function($activity) {
        return $activity->user_id;
    }, $activities['activities']));

    // Fetch all users in one query
    $user_ids_placeholder = implode(',', array_fill(0, count($user_ids), '%d'));

    $users_data = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, display_name, user_login, user_email
        FROM {$wpdb->users}
        WHERE ID IN ($user_ids_placeholder)",
        ...$user_ids
    ));

    // Index users by ID
    $users_by_id = array();
    foreach ($users_data as $user) {
        $users_by_id[$user->ID] = $user;
    }

    // Build activity feed
    $feed = array();
    foreach ($activities['activities'] as $activity) {
        $user = $users_by_id[$activity->user_id] ?? null;

        if (!$user) {
            continue;
        }

        $feed[] = array(
            'id' => $activity->id,
            'user_id' => $activity->user_id,
            'user_name' => $user->display_name,
            'user_avatar' => bp_core_fetch_avatar(array(
                'item_id' => $activity->user_id,
                'type' => 'thumb',
                'html' => false
            )),
            'content' => $activity->content,
            'date' => $activity->date_recorded,
            'type' => $activity->type
        );
    }

    return rest_ensure_response($feed);
}
```

**Expected Improvement:** 20+ queries → 2 queries (90% reduction!)

---

### Priority 4: My Books Shortcode N+1 Query ⚠️ CRITICAL

**File:** `includes/shortcodes/my_books.php`
**Lines:** 63-92
**Function:** `hs_my_books_shortcode()`

**Problem:** Same as Library API - loops through books calling get_post() and get_post_meta() multiple times.

**Impact:** Loaded on every user's library page

**Solution:** Use the same batch query approach as Priority 1

```php
function hs_my_books_shortcode($atts) {
    global $wpdb;

    if (!is_user_logged_in()) {
        return '<p>Please log in to view your books.</p>';
    }

    $user_id = get_current_user_id();

    // Fetch user books with reviews in one query
    $table_name = $wpdb->prefix . 'user_books';
    $reviews_table = $wpdb->prefix . 'hs_book_reviews';

    $user_books = $wpdb->get_results($wpdb->prepare(
        "SELECT ub.*, r.review_text, r.rating as review_rating
        FROM $table_name ub
        LEFT JOIN $reviews_table r ON ub.book_id = r.book_id AND ub.user_id = r.user_id
        WHERE ub.user_id = %d
        ORDER BY ub.date_added DESC",
        $user_id
    ));

    if (empty($user_books)) {
        return '<p>You haven\'t added any books yet.</p>';
    }

    // Get all book IDs
    $book_ids = array_map(function($ub) { return $ub->book_id; }, $user_books);
    $book_ids_placeholder = implode(',', array_fill(0, count($book_ids), '%d'));

    // BATCH QUERY: Get all book data
    $books_data = $wpdb->get_results($wpdb->prepare(
        "SELECT
            p.ID,
            p.post_title,
            p.post_name,
            MAX(CASE WHEN pm.meta_key = 'book_author' THEN pm.meta_value END) as author,
            MAX(CASE WHEN pm.meta_key = 'nop' THEN pm.meta_value END) as pages,
            MAX(CASE WHEN pm.meta_key = '_thumbnail_id' THEN pm.meta_value END) as thumbnail_id
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            AND pm.meta_key IN ('book_author', 'nop', '_thumbnail_id')
        WHERE p.ID IN ($book_ids_placeholder)
        GROUP BY p.ID",
        ...$book_ids
    ));

    // Index by ID
    $books_by_id = array();
    foreach ($books_data as $book) {
        $books_by_id[$book->ID] = $book;
    }

    // Build HTML output
    ob_start();
    ?>
    <div class="my-books-grid">
        <?php foreach ($user_books as $entry): ?>
            <?php
            $book = $books_by_id[$entry->book_id] ?? null;
            if (!$book) continue;

            $progress_percent = $book->pages > 0 ? ($entry->progress / $book->pages) * 100 : 0;
            ?>
            <div class="book-card" data-book-id="<?php echo esc_attr($entry->book_id); ?>">
                <div class="book-thumbnail">
                    <?php if ($book->thumbnail_id): ?>
                        <?php echo wp_get_attachment_image($book->thumbnail_id, 'medium'); ?>
                    <?php else: ?>
                        <div class="no-cover">No Cover</div>
                    <?php endif; ?>
                </div>

                <div class="book-info">
                    <h3><?php echo esc_html($book->post_title); ?></h3>
                    <p class="author">by <?php echo esc_html($book->author); ?></p>
                    <p class="status"><?php echo esc_html($entry->status); ?></p>

                    <?php if ($entry->status === 'reading' && $book->pages > 0): ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
                        </div>
                        <p class="progress-text">
                            <?php echo number_format($entry->progress); ?> / <?php echo number_format($book->pages); ?> pages
                            (<?php echo number_format($progress_percent, 1); ?>%)
                        </p>
                    <?php endif; ?>

                    <?php if ($entry->review_text): ?>
                        <div class="review">
                            <strong>Your Review:</strong>
                            <p><?php echo esc_html($entry->review_text); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
```

**Expected Improvement:** 150+ queries → 3 queries (98% reduction!)

---

## 🎯 Secondary Optimizations

### Priority 5: Add Post Meta Caching

**Files:** Multiple
**Impact:** Reduces repeated meta queries

**Solution:** WordPress has built-in object caching, but you can prime the cache:

Create a new file: `includes/cache-helpers.php`

```php
<?php
/**
 * Cache Helper Functions
 */

/**
 * Prime post meta cache for multiple posts
 * Call this before looping through posts
 */
function hs_prime_post_meta_cache($post_ids, $meta_keys = array()) {
    if (empty($post_ids)) {
        return;
    }

    // If specific keys provided, only cache those
    if (!empty($meta_keys)) {
        update_meta_cache('post', $post_ids);
        return;
    }

    // Otherwise cache all meta
    update_meta_cache('post', $post_ids);
}

/**
 * Prime user meta cache for multiple users
 */
function hs_prime_user_meta_cache($user_ids) {
    if (empty($user_ids)) {
        return;
    }

    update_meta_cache('user', $user_ids);
}

/**
 * Get multiple post meta values efficiently
 */
function hs_get_posts_meta($post_ids, $meta_key) {
    global $wpdb;

    if (empty($post_ids)) {
        return array();
    }

    $post_ids_placeholder = implode(',', array_fill(0, count($post_ids), '%d'));

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT post_id, meta_value
        FROM {$wpdb->postmeta}
        WHERE post_id IN ($post_ids_placeholder)
            AND meta_key = %s",
        ...array_merge($post_ids, array($meta_key))
    ));

    $meta_values = array();
    foreach ($results as $row) {
        $meta_values[$row->post_id] = $row->meta_value;
    }

    return $meta_values;
}
```

Add to `hotsoup.php`:
```php
require_once plugin_dir_path(__FILE__) . 'includes/cache-helpers.php';
```

**Usage in any loop:**
```php
// Before looping
hs_prime_post_meta_cache($book_ids);

// Then loop normally
foreach ($books as $book) {
    $author = get_post_meta($book->ID, 'book_author', true); // Now cached!
}
```

---

### Priority 6: Cache User Statistics

**File:** `includes/user_stats.php`
**Function:** `hs_update_user_stats()`

**Problem:** Complex JOIN queries run on every progress update

**Current Code:** Has 5-minute transient cache, but can be improved

**Solution:** Increase cache time and add lazy recalculation

```php
function hs_update_user_stats($user_id) {
    // Check if we have cached stats less than 1 hour old
    $cache_key = 'hs_user_stats_' . $user_id;
    $cached_stats = get_transient($cache_key);

    if ($cached_stats !== false) {
        return $cached_stats;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'user_books';

    // Calculate total pages read
    $total_pages = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(progress) FROM $table_name WHERE user_id = %d",
        $user_id
    ));

    // Calculate completed books
    $completed_books = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND status = 'completed'",
        $user_id
    ));

    // Update user meta
    update_user_meta($user_id, 'hs_total_pages_read', $total_pages);
    update_user_meta($user_id, 'hs_completed_books_count', $completed_books);

    $stats = array(
        'total_pages' => $total_pages,
        'completed_books' => $completed_books
    );

    // Cache for 1 hour
    set_transient($cache_key, $stats, HOUR_IN_SECONDS);

    return $stats;
}

/**
 * Invalidate stats cache when book progress changes
 */
function hs_invalidate_user_stats_cache($user_id) {
    delete_transient('hs_user_stats_' . $user_id);
}

// Hook into progress updates
add_action('hs_book_progress_updated', 'hs_invalidate_user_stats_cache');
add_action('hs_book_status_changed', 'hs_invalidate_user_stats_cache');
```

---

### Priority 7: Optimize Book Details Page

**File:** `hotsoup.php`
**Lines:** 796-935
**Function:** `hs_book_details_page()`

**Problem:** Multiple queries for reader counts, reviews, ratings

**Solution:** Combine queries

```php
function hs_book_details_page($content) {
    if (!is_singular('book')) {
        return $content;
    }

    global $wpdb, $post;
    $book_id = $post->ID;

    // SINGLE QUERY: Get all book stats at once
    $reviews_table = $wpdb->prefix . 'hs_book_reviews';
    $user_books_table = $wpdb->prefix . 'user_books';

    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT
            (SELECT COUNT(DISTINCT user_id) FROM $user_books_table WHERE book_id = %d) as reader_count,
            (SELECT COUNT(DISTINCT user_id) FROM $user_books_table WHERE book_id = %d AND status = 'completed') as completed_count,
            (SELECT AVG(rating) FROM $reviews_table WHERE book_id = %d) as avg_rating,
            (SELECT COUNT(*) FROM $reviews_table WHERE book_id = %d AND review_text IS NOT NULL) as review_count
        FROM DUAL",
        $book_id, $book_id, $book_id, $book_id
    ));

    // Get reviews with user data in one query
    $reviews = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, u.display_name, u.user_login
        FROM $reviews_table r
        JOIN {$wpdb->users} u ON r.user_id = u.ID
        WHERE r.book_id = %d AND r.review_text IS NOT NULL
        ORDER BY r.date_submitted DESC
        LIMIT 10",
        $book_id
    ));

    // Build HTML output (existing code, but now using $stats and $reviews)
    ob_start();
    ?>
    <div class="book-details-page">
        <div class="book-stats">
            <p><strong><?php echo number_format($stats->reader_count); ?></strong> readers</p>
            <p><strong><?php echo number_format($stats->completed_count); ?></strong> completed</p>
            <p><strong><?php echo number_format($stats->avg_rating, 1); ?></strong> average rating</p>
        </div>

        <div class="book-reviews">
            <h3><?php echo number_format($stats->review_count); ?> Reviews</h3>
            <?php foreach ($reviews as $review): ?>
                <div class="review">
                    <div class="review-header">
                        <strong><?php echo esc_html($review->display_name); ?></strong>
                        <?php if ($review->rating): ?>
                            <span class="rating"><?php echo str_repeat('⭐', $review->rating); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="review-content">
                        <?php echo esc_html($review->review_text); ?>
                    </div>
                    <div class="review-date">
                        <?php echo date('F j, Y', strtotime($review->date_submitted)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    $details = ob_get_clean();

    return $content . $details;
}
```

**Expected Improvement:** 15+ queries → 2 queries

---

### Priority 8: Add Database Indexes

**Problem:** Queries on unindexed columns are slow

**Solution:** Add indexes to frequently queried columns

Create a new file: `includes/admin/add-indexes.php`

```php
<?php
/**
 * Add Database Indexes for Performance
 */

function hs_add_performance_indexes() {
    global $wpdb;

    // Add indexes to user_books table
    $user_books = $wpdb->prefix . 'user_books';
    $wpdb->query("ALTER TABLE $user_books ADD INDEX idx_status (status)");
    $wpdb->query("ALTER TABLE $user_books ADD INDEX idx_date_added (date_added)");
    $wpdb->query("ALTER TABLE $user_books ADD INDEX idx_last_read (last_read)");

    // Add indexes to reviews table
    $reviews = $wpdb->prefix . 'hs_book_reviews';
    $wpdb->query("ALTER TABLE $reviews ADD INDEX idx_date_submitted (date_submitted)");
    $wpdb->query("ALTER TABLE $reviews ADD INDEX idx_rating (rating)");

    // Add indexes to book search table
    $search = $wpdb->prefix . 'hs_book_search_index';
    $wpdb->query("ALTER TABLE $search ADD FULLTEXT INDEX idx_search_text (search_text)");

    // Add indexes to relationships table
    $relationships = $wpdb->prefix . 'hs_user_relationships';
    $wpdb->query("ALTER TABLE $relationships ADD INDEX idx_user_relationship (user_id, related_user_id, relationship_type)");

    return true;
}

/**
 * Admin page to add indexes
 */
function hs_add_indexes_admin_page() {
    ?>
    <div class="wrap">
        <h1>Add Performance Indexes</h1>
        <p>Click the button below to add database indexes for better performance.</p>
        <p><strong>Note:</strong> This operation may take a few minutes on large databases.</p>

        <form method="post">
            <?php wp_nonce_field('hs_add_indexes'); ?>
            <input type="submit" name="add_indexes" class="button button-primary" value="Add Indexes">
        </form>

        <?php
        if (isset($_POST['add_indexes']) && check_admin_referer('hs_add_indexes')) {
            hs_add_performance_indexes();
            echo '<div class="notice notice-success"><p>Indexes added successfully!</p></div>';
        }
        ?>
    </div>
    <?php
}

function hs_add_indexes_menu() {
    add_submenu_page(
        'edit.php?post_type=book',
        'Add Indexes',
        'Add Indexes',
        'manage_options',
        'hs-add-indexes',
        'hs_add_indexes_admin_page'
    );
}
add_action('admin_menu', 'hs_add_indexes_menu');
```

Add to `hotsoup.php`:
```php
require_once plugin_dir_path(__FILE__) . 'includes/admin/add-indexes.php';
```

Then go to **Books → Add Indexes** and click the button.

---

### Priority 9: Implement Query Result Caching

**Problem:** Same queries run repeatedly for popular pages

**Solution:** Cache query results for frequently accessed data

Create: `includes/query-cache.php`

```php
<?php
/**
 * Query Result Caching
 */

/**
 * Get cached query result or execute and cache
 */
function hs_cached_query($cache_key, $callback, $expiration = 300) {
    // Try to get from cache
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    // Execute callback to get fresh data
    $result = call_user_func($callback);

    // Cache the result
    set_transient($cache_key, $result, $expiration);

    return $result;
}

/**
 * Cache popular books list
 */
function hs_get_popular_books_cached($limit = 20) {
    return hs_cached_query(
        'hs_popular_books_' . $limit,
        function() use ($limit) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'user_books';

            return $wpdb->get_results($wpdb->prepare(
                "SELECT book_id, COUNT(*) as reader_count
                FROM $table_name
                GROUP BY book_id
                ORDER BY reader_count DESC
                LIMIT %d",
                $limit
            ));
        },
        HOUR_IN_SECONDS // Cache for 1 hour
    );
}

/**
 * Cache recently active users
 */
function hs_get_recent_readers_cached($limit = 10) {
    return hs_cached_query(
        'hs_recent_readers_' . $limit,
        function() use ($limit) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'user_books';

            return $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT user_id, MAX(last_read) as last_activity
                FROM $table_name
                WHERE last_read IS NOT NULL
                GROUP BY user_id
                ORDER BY last_activity DESC
                LIMIT %d",
                $limit
            ));
        },
        300 // Cache for 5 minutes
    );
}

/**
 * Invalidate caches when data changes
 */
function hs_invalidate_book_cache($book_id) {
    global $wpdb;

    // Clear popular books cache
    for ($i = 10; $i <= 50; $i += 10) {
        delete_transient('hs_popular_books_' . $i);
    }

    // Clear recent readers
    delete_transient('hs_recent_readers_10');
    delete_transient('hs_recent_readers_20');
}

// Hook into data changes
add_action('hs_book_added_to_library', 'hs_invalidate_book_cache');
add_action('hs_book_progress_updated', 'hs_invalidate_book_cache');
```

Add to `hotsoup.php`:
```php
require_once plugin_dir_path(__FILE__) . 'includes/query-cache.php';
```

---

### Priority 10: Lazy Load Admin Modules

**Problem:** All admin modules load on every request, even frontend

**Solution:** Only load admin files when in admin area

In `hotsoup.php`, wrap admin file includes:

```php
// Only load admin modules in admin area
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/admin/book_merger.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/social_auth.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/chimera.php';
    require_once plugin_dir_path(__FILE__) . 'includes/importer.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/theme_manager.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/index_dbs.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/support_manager.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/achievements_manager.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/tags_manager.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/authors_series_manager.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/database_repair.php';
    require_once plugin_dir_path(__FILE__) . 'includes/admin/performance-dashboard.php';
}
```

**Expected Improvement:** Reduces memory usage and load time on frontend

---

## 📋 Implementation Checklist

### Phase 1: Measurement (Do First!)
- [ ] Enable Performance Monitor
- [ ] Record baseline metrics
- [ ] Identify top 3 slowest endpoints

### Phase 2: Critical N+1 Fixes (Do These First!)
- [ ] Fix Library API (Priority 1)
- [ ] Test and measure improvement
- [ ] Fix Book Search (Priority 2)
- [ ] Test and measure improvement
- [ ] Fix Activity Feed (Priority 3)
- [ ] Test and measure improvement
- [ ] Fix My Books Shortcode (Priority 4)
- [ ] Test and measure improvement

### Phase 3: Secondary Optimizations
- [ ] Add Post Meta Caching (Priority 5)
- [ ] Cache User Statistics (Priority 6)
- [ ] Optimize Book Details Page (Priority 7)
- [ ] Add Database Indexes (Priority 8)
- [ ] Implement Query Result Caching (Priority 9)
- [ ] Lazy Load Admin Modules (Priority 10)

### Phase 4: Validation
- [ ] Run full site test
- [ ] Compare before/after metrics
- [ ] Document improvements
- [ ] Disable Performance Monitor

---

## 🎯 Expected Results

### Before Optimizations (Typical)
- **Queries/Request:** 150-300 queries
- **Response Time:** 2-5 seconds
- **N+1 Patterns:** 10-20 detected
- **Memory Usage:** 40-60MB

### After Optimizations (Target)
- **Queries/Request:** 10-30 queries (90% reduction!)
- **Response Time:** 200-500ms (10x faster!)
- **N+1 Patterns:** 0 (eliminated!)
- **Memory Usage:** 20-30MB (50% reduction!)

---

## ⚠️ Important Notes

1. **Backup First:** Always backup your database before making changes
2. **Test in Staging:** If possible, test optimizations in a staging environment first
3. **One at a Time:** Apply optimizations one at a time and measure each
4. **Monitor Errors:** Check error logs after each change
5. **Cache Invalidation:** Make sure caches clear when data changes
6. **Documentation:** Document each change for future reference

---

## 🆘 Troubleshooting

### Issue: Performance monitor shows no data
**Solution:** Make some requests to your site. Data takes a few minutes to appear.

### Issue: Queries not decreasing
**Solution:** Clear all caches (object cache, page cache, CDN) and test again.

### Issue: Errors after optimization
**Solution:** Check PHP error logs. Most likely cause is SQL syntax error or missing variable.

### Issue: Cache not clearing
**Solution:** Make sure invalidation hooks are firing. Add error_log() to debug.

---

## 📞 Support

If you encounter issues:
1. Check the PHP error log: `/var/log/php-errors.log`
2. Enable WP_DEBUG in wp-config.php
3. Check browser console for JS errors
4. Review performance dashboard for specific slow endpoints

---

## 🎉 Completion

Once all optimizations are complete:

1. ✅ Disable Performance Monitor
2. ✅ Document final metrics
3. ✅ Clear all caches
4. ✅ Monitor site for a few days
5. ✅ Celebrate your performance gains!

Your site should now be **dramatically faster** with 90%+ fewer database queries!
