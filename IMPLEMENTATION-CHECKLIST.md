# GRead Custom Theme - Implementation Checklist

**Quick-start guide to build your modular theme TODAY**

---

## ⚡ Quick Start (30 minutes)

Follow these steps to get a working modular theme replacement:

### Step 1: Set Up Theme Structure (5 min)

```bash
# Navigate to themes directory
cd wp-content/themes/

# Clone Dogaroni as starting point
git clone https://github.com/GRead-Development/Dogaroni.git gread-custom
cd gread-custom/

# Clean up
rm -rf .git  # Remove Dogaroni's git history
```

**Edit `style.css` header:**
```css
/*
Theme Name: GRead Custom
Theme URI: https://gread.com
Author: GRead Team
Author URI: https://gread.com
Description: Modular custom theme for GRead community
Version: 1.0.0
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: gread-custom
*/
```

### Step 2: Create Directory Structure (2 min)

```bash
# Create new directories
mkdir -p parts/books
mkdir -p parts/library
mkdir -p parts/headers
mkdir -p parts/footers
mkdir -p parts/navigation
mkdir -p parts/buddypress
mkdir -p parts/search
mkdir -p parts/forms
mkdir -p parts/achievements
mkdir -p parts/common
mkdir -p inc
mkdir -p templates
```

### Step 3: Create Config System (5 min)

**Create `/inc/config.php`:**
```php
<?php
/**
 * GRead Theme Configuration
 * Swap components by changing values here
 */
return [
    // Book display
    'books' => [
        'card_style' => 'book-card',
        'archive_layout' => 'grid',
    ],

    // Library sections
    'library' => [
        'sections' => [
            'currently_reading' => true,
            'want_to_read' => true,
            'finished' => true,
            'dnf' => true,
            'paused' => true,
        ],
    ],

    // Header/Footer
    'header' => [
        'type' => 'default',
    ],
    'footer' => [
        'type' => 'default',
    ],
];
```

**Create `/inc/template-tags.php`:**
```php
<?php
/**
 * Template loading functions
 */

/**
 * Load a swappable template part
 */
function gread_get_part($section, $key = 'default', $args = []) {
    static $config = null;

    if ($config === null) {
        $config_file = get_template_directory() . '/inc/config.php';
        if (file_exists($config_file)) {
            $config = include $config_file;
        } else {
            $config = [];
        }
    }

    // Get component name from config
    if (is_array($config) && isset($config[$section][$key])) {
        $component = $config[$section][$key];
    } else {
        $component = $key;
    }

    // Build template path
    $template_path = "parts/{$section}/{$component}.php";

    // Extract args
    if (!empty($args)) {
        extract($args, EXTR_SKIP);
    }

    // Load template
    $located = locate_template($template_path, false, false);

    if ($located) {
        load_template($located, false);
    } else {
        // Fallback for debugging
        if (WP_DEBUG) {
            echo "<!-- Template not found: {$template_path} -->";
        }
    }
}

/**
 * Load multiple section components
 */
function gread_get_sections($section, $args = []) {
    static $config = null;

    if ($config === null) {
        $config_file = get_template_directory() . '/inc/config.php';
        if (file_exists($config_file)) {
            $config = include $config_file;
        } else {
            $config = [];
        }
    }

    if (!isset($config[$section]['sections'])) {
        return;
    }

    foreach ($config[$section]['sections'] as $section_key => $enabled) {
        if ($enabled) {
            $filename = $section . '-' . str_replace('_', '-', $section_key);
            $template_path = "parts/{$section}/{$filename}.php";

            if (!empty($args)) {
                extract($args, EXTR_SKIP);
            }

            locate_template($template_path, true, false);
        }
    }
}

/**
 * Get config value
 */
function gread_get_config($section, $key = null) {
    static $config = null;

    if ($config === null) {
        $config_file = get_template_directory() . '/inc/config.php';
        if (file_exists($config_file)) {
            $config = include $config_file;
        } else {
            $config = [];
        }
    }

    if ($key === null) {
        return $config[$section] ?? [];
    }

    return $config[$section][$key] ?? null;
}
```

**Add to `functions.php` (at the bottom):**
```php
/**
 * Load custom theme functions
 */
require_once get_template_directory() . '/inc/template-tags.php';
```

### Step 4: Create Your First Component (10 min)

**Create `/parts/books/book-card.php`:**
```php
<?php
/**
 * Book Card Component
 *
 * @var int $book_id
 */

if (!isset($book_id)) {
    $book_id = get_the_ID();
}

$author = get_field('book_author', $book_id);
$pages = get_field('nop', $book_id);
?>

<article class="book-card" data-book-id="<?php echo esc_attr($book_id); ?>">
    <?php if (has_post_thumbnail($book_id)): ?>
        <div class="book-card__cover">
            <a href="<?php echo get_permalink($book_id); ?>">
                <?php echo get_the_post_thumbnail($book_id, 'medium'); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="book-card__content">
        <h3 class="book-card__title">
            <a href="<?php echo get_permalink($book_id); ?>">
                <?php echo get_the_title($book_id); ?>
            </a>
        </h3>

        <?php if ($author): ?>
            <p class="book-card__author">
                by <?php echo esc_html($author); ?>
            </p>
        <?php endif; ?>

        <?php if ($pages): ?>
            <p class="book-card__pages">
                <?php echo number_format($pages); ?> pages
            </p>
        <?php endif; ?>

        <div class="book-card__actions">
            <button class="btn btn-primary add-to-library"
                    data-book-id="<?php echo esc_attr($book_id); ?>"
                    data-nonce="<?php echo wp_create_nonce('add_to_library'); ?>">
                Add to Library
            </button>
        </div>
    </div>
</article>
```

### Step 5: Create Book Archive Template (5 min)

**Create `archive-book.php` (or edit existing):**
```php
<?php
/**
 * Book Archive Template
 */
get_header();
?>

<div class="container">
    <div class="book-archive">

        <header class="archive-header">
            <h1 class="archive-title">
                <?php post_type_archive_title(); ?>
            </h1>
        </header>

        <?php if (have_posts()): ?>

            <div class="book-grid">
                <?php while (have_posts()): the_post(); ?>

                    <?php
                    // 🔄 THIS LOADS THE SWAPPABLE COMPONENT
                    gread_get_part('books', 'card_style', [
                        'book_id' => get_the_ID()
                    ]);
                    ?>

                <?php endwhile; ?>
            </div>

            <?php
            // Pagination
            the_posts_pagination([
                'mid_size' => 2,
                'prev_text' => __('← Previous', 'gread-custom'),
                'next_text' => __('Next →', 'gread-custom'),
            ]);
            ?>

        <?php else: ?>

            <p><?php _e('No books found.', 'gread-custom'); ?></p>

        <?php endif; ?>

    </div>
</div>

<?php get_footer(); ?>
```

### Step 6: Add Basic Styling (3 min)

**Create `/assets/css/components.css`:**
```css
/* Book Card Styles */
.book-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 2rem;
    margin: 2rem 0;
}

.book-card {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.book-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.book-card__cover {
    aspect-ratio: 2/3;
    overflow: hidden;
    background: #f5f5f5;
}

.book-card__cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.book-card__content {
    padding: 1rem;
}

.book-card__title {
    margin: 0 0 0.5rem;
    font-size: 1.1rem;
    line-height: 1.3;
}

.book-card__title a {
    color: #333;
    text-decoration: none;
}

.book-card__title a:hover {
    color: #0073aa;
}

.book-card__author {
    margin: 0 0 0.25rem;
    color: #666;
    font-size: 0.9rem;
}

.book-card__pages {
    margin: 0 0 1rem;
    color: #999;
    font-size: 0.85rem;
}

.book-card__actions {
    margin-top: 1rem;
}

.btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9rem;
    text-align: center;
    transition: background-color 0.2s;
}

.btn-primary {
    background: #0073aa;
    color: #fff;
}

.btn-primary:hover {
    background: #005a87;
}
```

**Enqueue in `functions.php`:**
```php
function gread_enqueue_custom_styles() {
    wp_enqueue_style(
        'gread-components',
        get_template_directory_uri() . '/assets/css/components.css',
        [],
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'gread_enqueue_custom_styles');
```

### Step 7: Activate Theme

1. Go to WordPress Admin → Appearance → Themes
2. Find "GRead Custom"
3. Click "Activate"
4. Visit `/books/` to see your new book archive!

---

## ✅ Testing Checklist

- [ ] Theme activates without errors
- [ ] Book archive page displays (`/books/`)
- [ ] Book cards show cover, title, author
- [ ] Clicking book title goes to single book page
- [ ] "Add to Library" button appears (functionality requires HotSoup plugin)
- [ ] No PHP errors in debug log
- [ ] Page is responsive on mobile

---

## 🔄 Test Component Swapping

### Create Alternative Book Card

**Create `/parts/books/book-card-minimal.php`:**
```php
<?php
/**
 * Minimal Book Card - Just cover and title
 *
 * @var int $book_id
 */

if (!isset($book_id)) {
    $book_id = get_the_ID();
}
?>

<article class="book-card book-card--minimal">
    <a href="<?php echo get_permalink($book_id); ?>" class="book-card__link">
        <?php if (has_post_thumbnail($book_id)): ?>
            <div class="book-card__cover">
                <?php echo get_the_post_thumbnail($book_id, 'medium'); ?>
            </div>
        <?php endif; ?>
        <h4 class="book-card__title-minimal">
            <?php echo get_the_title($book_id); ?>
        </h4>
    </a>
</article>
```

### Swap It

1. Open `/inc/config.php`
2. Change:
```php
'card_style' => 'book-card',  // OLD
```
To:
```php
'card_style' => 'book-card-minimal',  // NEW
```
3. Refresh `/books/` page
4. Cards are now minimal! 🎉

---

## 📋 Full Implementation Checklist

### Phase 1: Foundation ✅
- [x] Clone Dogaroni theme
- [x] Update theme metadata
- [x] Create directory structure
- [x] Create config system
- [x] Create template loader functions
- [x] Create first component
- [x] Create book archive template
- [x] Add basic styling
- [x] Activate theme
- [x] Test component swapping

### Phase 2: Single Book Page
- [ ] Create `single-book.php`
- [ ] Create `/parts/books/book-header.php`
- [ ] Create `/parts/books/book-meta.php`
- [ ] Create `/parts/books/book-actions.php`
- [ ] Create `/parts/books/book-reviews.php`
- [ ] Add to config
- [ ] Style components
- [ ] Test

### Phase 3: User Library
- [ ] Create `templates/page-library.php`
- [ ] Create `/parts/library/library-currently-reading.php`
- [ ] Create `/parts/library/library-want-to-read.php`
- [ ] Create `/parts/library/library-finished.php`
- [ ] Create `/parts/library/library-dnf.php`
- [ ] Create `/parts/library/library-stats.php`
- [ ] Add section config
- [ ] Style components
- [ ] Test with real data

### Phase 4: Search
- [ ] Create `templates/page-search.php`
- [ ] Create `/parts/search/search-form.php`
- [ ] Create `/parts/search/search-results.php`
- [ ] Create `/parts/search/search-filters.php`
- [ ] Add to config
- [ ] Integrate HotSoup search
- [ ] Style components
- [ ] Test

### Phase 5: BuddyPress
- [ ] Create `/buddypress/members/single/member-header.php`
- [ ] Create `/parts/buddypress/member-stats.php`
- [ ] Create `/parts/buddypress/activity-item.php`
- [ ] Create `/parts/buddypress/achievement-badge.php`
- [ ] Add to config
- [ ] Style components
- [ ] Test user profiles

### Phase 6: Forms
- [ ] Create `templates/page-submit-book.php`
- [ ] Create `/parts/forms/form-book-submit.php`
- [ ] Create `/parts/forms/form-review.php`
- [ ] Create `/parts/forms/form-notes.php`
- [ ] Add to config
- [ ] Style forms
- [ ] Test submissions

### Phase 7: Achievements
- [ ] Create achievement display page
- [ ] Create `/parts/achievements/achievement-card.php`
- [ ] Create `/parts/achievements/achievement-progress.php`
- [ ] Create `/parts/achievements/unlockables-grid.php`
- [ ] Add to config
- [ ] Style components
- [ ] Test unlocking

### Phase 8: Polish
- [ ] Mobile responsive testing
- [ ] Cross-browser testing (Chrome, Firefox, Safari, Edge)
- [ ] Accessibility audit
- [ ] Performance optimization
- [ ] Image lazy loading
- [ ] CSS/JS minification
- [ ] Documentation
- [ ] User testing

---

## 🎨 Customization Examples

### Example 1: Hide DNF Section

**In `/inc/config.php`:**
```php
'library' => [
    'sections' => [
        'currently_reading' => true,
        'want_to_read' => true,
        'finished' => true,
        'dnf' => false,  // ← Changed to false
        'paused' => true,
    ],
],
```

### Example 2: Change Book Layout

**In `/inc/config.php`:**
```php
'books' => [
    'card_style' => 'book-card-list',  // Use list view
    'archive_layout' => 'list',        // List instead of grid
],
```

### Example 3: Use Different Header

**In `/inc/config.php`:**
```php
'header' => [
    'type' => 'minimal',  // Use minimal header
],
```

**Then create `/parts/headers/minimal.php`**

---

## 🐛 Troubleshooting

### Component Not Loading?

**Check:**
1. File exists in correct location: `/parts/{section}/{component}.php`
2. Filename matches config exactly (case-sensitive!)
3. Config file returns an array
4. `template-tags.php` is included in `functions.php`

**Debug:**
```php
// Add to functions.php temporarily
add_action('wp_footer', function() {
    if (WP_DEBUG && current_user_can('administrator')) {
        $config = include get_template_directory() . '/inc/config.php';
        echo '<pre style="background:#000;color:#0f0;padding:1rem;">';
        print_r($config);
        echo '</pre>';
    }
});
```

### Styles Not Applying?

1. Clear browser cache (Ctrl+Shift+R)
2. Check if CSS file is enqueued in `functions.php`
3. Verify file path is correct
4. Check for CSS syntax errors

### HotSoup Features Not Working?

1. Ensure HotSoup plugin is active
2. Check if shortcodes need to be used
3. Verify AJAX endpoints are accessible
4. Check browser console for JavaScript errors

---

## 📚 Resources

### Your Files
- **Main Plan:** `/home/user/Server/CUSTOM-THEME-PLAN.md`
- **Examples:** `/home/user/Server/theme-starter-example.md`
- **This Checklist:** `/home/user/Server/IMPLEMENTATION-CHECKLIST.md`
- **HotSoup Plugin:** `/home/user/Server/`
- **Dogaroni Theme:** `https://github.com/GRead-Development/Dogaroni`

### WordPress Documentation
- [Template Hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/)
- [Template Tags](https://developer.wordpress.org/themes/basics/template-tags/)
- [Theme Handbook](https://developer.wordpress.org/themes/)

### BuddyPress
- [Theme Development](https://codex.buddypress.org/themes/)
- [Template Overrides](https://codex.buddypress.org/developer/theme-development/theme-compatibility/)

---

## 🚀 Next Steps

**You should now:**

1. ✅ Have read this checklist
2. ⬜ Follow "Quick Start" steps above
3. ⬜ Test your first component swap
4. ⬜ Pick next page to replace
5. ⬜ Repeat the pattern!

**Remember:** Replace ONE page at a time. Get it working, then move to the next.

Good luck! 🎉
