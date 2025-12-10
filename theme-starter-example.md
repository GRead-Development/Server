# GRead Theme Starter - Component Swapping Example

This file demonstrates how the modular component system works with practical examples.

## Example 1: Book Card Component

### Step 1: Create the Config File

**File: `/inc/config.php`**
```php
<?php
/**
 * GRead Theme Configuration
 * Change values here to swap components site-wide
 */
return [
    'books' => [
        'card_style' => 'book-card-default',  // SWAP THIS VALUE to change design
    ],
];
```

### Step 2: Create Component Loader

**File: `/inc/template-tags.php`**
```php
<?php
/**
 * Load a swappable template part
 *
 * @param string $section Section name (e.g., 'books', 'library')
 * @param string $key Config key to look up
 * @param array $args Variables to pass to template
 */
function gread_get_part($section, $key = 'default', $args = []) {
    static $config = null;

    // Load config once
    if ($config === null) {
        $config = include get_template_directory() . '/inc/config.php';
    }

    // Get the component name from config
    $component = $config[$section][$key] ?? 'default';

    // Build the template path
    $template_path = "parts/{$section}/{$component}.php";

    // Extract args so they're available as variables
    if (!empty($args)) {
        extract($args, EXTR_SKIP);
    }

    // Load the template
    locate_template($template_path, true, false);
}
```

### Step 3: Create Component Variants

**File: `/parts/books/book-card-default.php`**
```php
<?php
/**
 * Default Book Card - Simple vertical layout
 *
 * Available variables:
 * @var int $book_id The book post ID
 */

if (!isset($book_id)) {
    $book_id = get_the_ID();
}

$author = get_field('book_author', $book_id);
$pages = get_field('nop', $book_id);
?>

<div class="book-card book-card--default" data-book-id="<?php echo esc_attr($book_id); ?>">
    <div class="book-card__cover">
        <?php echo get_the_post_thumbnail($book_id, 'medium'); ?>
    </div>
    <div class="book-card__content">
        <h3 class="book-card__title">
            <a href="<?php echo get_permalink($book_id); ?>">
                <?php echo get_the_title($book_id); ?>
            </a>
        </h3>
        <?php if ($author): ?>
            <p class="book-card__author">by <?php echo esc_html($author); ?></p>
        <?php endif; ?>
        <?php if ($pages): ?>
            <p class="book-card__pages"><?php echo esc_html($pages); ?> pages</p>
        <?php endif; ?>
        <div class="book-card__actions">
            <button class="btn btn-primary add-to-library" data-book-id="<?php echo esc_attr($book_id); ?>">
                Add to Library
            </button>
        </div>
    </div>
</div>
```

**File: `/parts/books/book-card-compact.php`**
```php
<?php
/**
 * Compact Book Card - Horizontal layout for lists
 *
 * Available variables:
 * @var int $book_id The book post ID
 */

if (!isset($book_id)) {
    $book_id = get_the_ID();
}

$author = get_field('book_author', $book_id);
$pages = get_field('nop', $book_id);
?>

<div class="book-card book-card--compact" data-book-id="<?php echo esc_attr($book_id); ?>">
    <div class="book-card__row">
        <div class="book-card__cover-small">
            <?php echo get_the_post_thumbnail($book_id, 'thumbnail'); ?>
        </div>
        <div class="book-card__content-inline">
            <h4 class="book-card__title">
                <a href="<?php echo get_permalink($book_id); ?>">
                    <?php echo get_the_title($book_id); ?>
                </a>
            </h4>
            <?php if ($author): ?>
                <span class="book-card__author"><?php echo esc_html($author); ?></span>
            <?php endif; ?>
        </div>
        <div class="book-card__actions-inline">
            <button class="btn btn-sm add-to-library" data-book-id="<?php echo esc_attr($book_id); ?>">
                + Add
            </button>
        </div>
    </div>
</div>
```

**File: `/parts/books/book-card-detailed.php`**
```php
<?php
/**
 * Detailed Book Card - Large format with excerpt
 *
 * Available variables:
 * @var int $book_id The book post ID
 */

if (!isset($book_id)) {
    $book_id = get_the_ID();
}

$author = get_field('book_author', $book_id);
$pages = get_field('nop', $book_id);
$isbn = get_field('book_isbn', $book_id);
$year = get_field('publication_year', $book_id);
?>

<div class="book-card book-card--detailed" data-book-id="<?php echo esc_attr($book_id); ?>">
    <div class="book-card__inner">
        <div class="book-card__cover-large">
            <?php echo get_the_post_thumbnail($book_id, 'large'); ?>
        </div>
        <div class="book-card__details">
            <h2 class="book-card__title">
                <a href="<?php echo get_permalink($book_id); ?>">
                    <?php echo get_the_title($book_id); ?>
                </a>
            </h2>

            <?php if ($author): ?>
                <p class="book-card__author">
                    <strong>Author:</strong> <?php echo esc_html($author); ?>
                </p>
            <?php endif; ?>

            <div class="book-card__meta">
                <?php if ($year): ?>
                    <span class="meta-item">
                        <strong>Published:</strong> <?php echo esc_html($year); ?>
                    </span>
                <?php endif; ?>

                <?php if ($pages): ?>
                    <span class="meta-item">
                        <strong>Pages:</strong> <?php echo esc_html($pages); ?>
                    </span>
                <?php endif; ?>

                <?php if ($isbn): ?>
                    <span class="meta-item">
                        <strong>ISBN:</strong> <?php echo esc_html($isbn); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="book-card__excerpt">
                <?php echo get_the_excerpt($book_id); ?>
            </div>

            <div class="book-card__actions">
                <button class="btn btn-primary btn-lg add-to-library" data-book-id="<?php echo esc_attr($book_id); ?>">
                    Add to My Library
                </button>
                <a href="<?php echo get_permalink($book_id); ?>" class="btn btn-secondary btn-lg">
                    View Details
                </a>
            </div>
        </div>
    </div>
</div>
```

### Step 4: Use in Templates

**File: `archive-book.php`**
```php
<?php
/**
 * Book Archive Template
 */
get_header();
?>

<div class="book-archive">
    <header class="page-header">
        <h1>All Books</h1>
    </header>

    <div class="book-grid">
        <?php
        if (have_posts()) {
            while (have_posts()) {
                the_post();

                // 🔄 THIS IS WHERE THE MAGIC HAPPENS
                // Component loaded is controlled by config.php
                gread_get_part('books', 'card_style', [
                    'book_id' => get_the_ID()
                ]);
            }
        } else {
            echo '<p>No books found.</p>';
        }
        ?>
    </div>

    <?php
    // Pagination
    the_posts_pagination();
    ?>
</div>

<?php get_footer(); ?>
```

### Step 5: Swap Components

**To change from default to compact layout:**

1. Open `/inc/config.php`
2. Change this line:
```php
'card_style' => 'book-card-default',  // OLD
```
To:
```php
'card_style' => 'book-card-compact',  // NEW
```
3. Save and refresh - all book cards are now compact!

**To change to detailed layout:**
```php
'card_style' => 'book-card-detailed',
```

---

## Example 2: Library Sections

### Config
```php
'library' => [
    'sections' => [
        'currently_reading' => true,   // Show/hide by changing to false
        'want_to_read' => true,
        'finished' => true,
        'dnf' => true,
        'paused' => false,             // Hidden!
    ],
    'layout' => 'library-layout-grid', // Swap entire layout
],
```

### Template Parts

**File: `/parts/library/library-currently-reading.php`**
```php
<?php
/**
 * Currently Reading Section
 *
 * @var int $user_id User ID
 */

// Get currently reading books from HotSoup
global $wpdb;
$books = $wpdb->get_results($wpdb->prepare(
    "SELECT book_id, progress FROM {$wpdb->prefix}user_books
     WHERE user_id = %d AND status = 'currently_reading'
     ORDER BY last_read DESC",
    $user_id
));

if (empty($books)) {
    return;
}
?>

<section class="library-section library-section--currently-reading">
    <h2>Currently Reading</h2>
    <div class="library-books">
        <?php foreach ($books as $book): ?>
            <div class="library-book-item">
                <?php
                gread_get_part('books', 'card_style', [
                    'book_id' => $book->book_id
                ]);
                ?>
                <div class="library-book-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo esc_attr($book->progress); ?>%"></div>
                    </div>
                    <span class="progress-text"><?php echo esc_html($book->progress); ?>% complete</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
```

**File: `/parts/library/library-finished.php`**
```php
<?php
/**
 * Finished Books Section
 *
 * @var int $user_id User ID
 */

global $wpdb;
$books = $wpdb->get_results($wpdb->prepare(
    "SELECT book_id, finished_date FROM {$wpdb->prefix}user_books
     WHERE user_id = %d AND status = 'read'
     ORDER BY finished_date DESC
     LIMIT 12",
    $user_id
));

if (empty($books)) {
    return;
}
?>

<section class="library-section library-section--finished">
    <h2>Finished Books</h2>
    <div class="library-books library-books--finished">
        <?php foreach ($books as $book): ?>
            <div class="library-book-item">
                <?php
                gread_get_part('books', 'card_style', [
                    'book_id' => $book->book_id
                ]);
                ?>
                <div class="library-book-meta">
                    <small>Finished: <?php echo date('M j, Y', strtotime($book->finished_date)); ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
```

### Enhanced Loader for Sections

**Add to `/inc/template-tags.php`:**
```php
/**
 * Load all enabled sections for a feature
 *
 * @param string $section Section name
 * @param array $args Variables to pass to all sections
 */
function gread_get_sections($section, $args = []) {
    static $config = null;

    if ($config === null) {
        $config = include get_template_directory() . '/inc/config.php';
    }

    // Get enabled sections
    $sections = $config[$section]['sections'] ?? [];

    foreach ($sections as $section_key => $enabled) {
        if ($enabled) {
            // Convert key to filename (e.g., 'currently_reading' => 'library-currently-reading')
            $filename = $section . '-' . str_replace('_', '-', $section_key);
            $template_path = "parts/{$section}/{$filename}.php";

            if (!empty($args)) {
                extract($args, EXTR_SKIP);
            }

            locate_template($template_path, true, false);
        }
    }
}
```

### Use in Page Template

**File: `templates/page-library.php`**
```php
<?php
/**
 * Template Name: User Library
 */
get_header();

$user_id = get_current_user_id();

if (!$user_id) {
    echo '<p>Please log in to view your library.</p>';
    get_footer();
    exit;
}
?>

<div class="user-library">
    <header class="library-header">
        <h1>My Library</h1>
        <?php gread_get_part('library', 'stats_widget', ['user_id' => $user_id]); ?>
    </header>

    <div class="library-content">
        <?php
        // 🔄 LOAD ALL ENABLED SECTIONS FROM CONFIG
        gread_get_sections('library', ['user_id' => $user_id]);
        ?>
    </div>
</div>

<?php get_footer(); ?>
```

---

## Example 3: A/B Testing Components

### Advanced Config with Variants

```php
'books' => [
    // Define all available variants
    'card_variants' => [
        'default' => 'book-card-default',
        'compact' => 'book-card-compact',
        'detailed' => 'book-card-detailed',
        'experimental' => 'book-card-experimental',  // New design to test
    ],

    // Set active variant here
    'card_style' => 'default',  // Use key from card_variants

    // Context-specific overrides
    'archive_card' => 'compact',    // Different card for archives
    'search_card' => 'compact',     // Different card for search
    'featured_card' => 'detailed',  // Different card for featured
],
```

### Smart Loader with Context

```php
/**
 * Load component with context-aware variant selection
 *
 * @param string $section Section name
 * @param string $key Config key
 * @param array $args Variables to pass
 * @param string $context Optional context (archive, search, featured)
 */
function gread_get_part($section, $key = 'card_style', $args = [], $context = null) {
    static $config = null;

    if ($config === null) {
        $config = include get_template_directory() . '/inc/config.php';
    }

    // Check for context-specific override
    if ($context && isset($config[$section][$context . '_' . $key])) {
        $variant_key = $config[$section][$context . '_' . $key];
    } else {
        $variant_key = $config[$section][$key] ?? 'default';
    }

    // If variants are defined, look up the actual component name
    if (isset($config[$section][$key . '_variants'][$variant_key])) {
        $component = $config[$section][$key . '_variants'][$variant_key];
    } else {
        $component = $variant_key;
    }

    $template_path = "parts/{$section}/{$component}.php";

    if (!empty($args)) {
        extract($args, EXTR_SKIP);
    }

    locate_template($template_path, true, false);
}
```

### Usage with Context

```php
// In archive-book.php - use archive-specific card
gread_get_part('books', 'card_style', ['book_id' => get_the_ID()], 'archive');

// In search.php - use search-specific card
gread_get_part('books', 'card_style', ['book_id' => get_the_ID()], 'search');

// In featured section - use detailed card
gread_get_part('books', 'card_style', ['book_id' => get_the_ID()], 'featured');
```

---

## Quick Reference

### To Swap a Component:
1. Open `/inc/config.php`
2. Find the relevant section
3. Change the component name
4. Save and refresh

### To Add a New Component:
1. Create file in `/parts/{section}/{component-name}.php`
2. Add to config variants (optional)
3. Set as active in config
4. Test

### To Hide/Show Features:
1. Open `/inc/config.php`
2. Find the feature's section
3. Change `true` to `false` (or vice versa)
4. Save and refresh

### To Test A/B Variants:
1. Create both component files
2. Add both to config variants
3. Change active variant value
4. Compare results

---

## File Checklist

**To get started with this system, you need:**

- [ ] `/inc/config.php` - Configuration file
- [ ] `/inc/template-tags.php` - Helper functions
- [ ] `/parts/` directory - Create this!
- [ ] At least one component in `/parts/{section}/{name}.php`
- [ ] Include template-tags.php in functions.php:
```php
require_once get_template_directory() . '/inc/template-tags.php';
```

**That's it!** You now have a fully swappable component system.
