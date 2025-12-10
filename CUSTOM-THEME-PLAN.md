# Custom Theme Development Plan for GRead
**Last Updated:** 2025-12-10

## Executive Summary

This document outlines a modular, component-based custom theme architecture that:
- ✅ Supports all HotSoup plugin features
- ✅ Full BuddyPress integration
- ✅ Allows page-by-page replacement
- ✅ Easy code snippet swapping via template parts
- ✅ Maintains backward compatibility during migration

---

## 1. THEME REQUIREMENTS CHECKLIST

### 1.1 WordPress Core Support
- [ ] Standard WordPress template hierarchy
- [ ] Custom post type support (book)
- [ ] Featured images/thumbnails
- [ ] Custom menus
- [ ] Widget areas
- [ ] Gutenberg block editor support
- [ ] Theme customizer integration
- [ ] RTL language support
- [ ] Translation-ready

### 1.2 HotSoup Plugin Integration

#### Template Requirements
- [ ] **Book Archive Template** (`archive-book.php`)
  - Book grid/list display
  - Search integration
  - Filter controls
  - Pagination

- [ ] **Single Book Template** (`single-book.php`)
  - Book cover display
  - Book metadata (author, ISBN, pages, year)
  - Add to library button
  - Progress tracking UI
  - Review display
  - User notes section
  - Citations/quotes
  - Chapter list
  - Character information
  - Series information

- [ ] **User Library Template** (page template)
  - Currently reading section
  - Want to read section
  - Finished books section
  - DNF (Did Not Finish) section
  - Paused books section
  - Progress bars
  - Quick actions (update progress, remove)

- [ ] **Book Search Template** (page template)
  - Search form
  - Autocomplete support
  - Search results display
  - Author filtering
  - ISBN search

- [ ] **Book Submission Template** (page template)
  - Book submission form
  - Cover upload
  - ISBN lookup
  - Author selection/creation
  - Series assignment

#### Shortcode Support Areas
- [ ] `[my_books]` - Display areas
- [ ] `[book_directory]` - Display areas
- [ ] `[book_list]` - DataTables integration
- [ ] `[hs_book_search]` - Search widget areas
- [ ] `[submit_book]` - Submission page areas
- [ ] `[author_books]` - Author page areas
- [ ] `[author_series]` - Series page areas
- [ ] `[total_books]` - Stats widget areas
- [ ] `[note_form]` - Notes areas
- [ ] `[notes_modal]` - Modal areas

#### CSS Integration Points
- [ ] HotSoup main styles (hs-style.css)
- [ ] Achievement styles (hs-achievements.css)
- [ ] Rewards styles (hs-rewards.css)
- [ ] Search styles (hs-search.css)
- [ ] Theme selector styles (hs-themes.css)
- [ ] Notes modal styles (notes-modal.css)
- [ ] Book submission modal styles
- [ ] Chapter submission styles
- [ ] Social auth styles
- [ ] Book mentions styles

#### JavaScript Integration Points
- [ ] Main JS (hs-main.js)
- [ ] Theme selector (theme-selector.js)
- [ ] Notes modal (notes-modal.js)
- [ ] Book submission modal
- [ ] Social auth
- [ ] Reading sessions
- [ ] Book mentions
- [ ] Search functionality
- [ ] Chapter submissions
- [ ] Contributions modals

### 1.3 BuddyPress Integration

#### Core Components
- [ ] **Member Profiles**
  - Custom profile header design
  - Reading stats display
  - Achievement badges
  - Recent activity
  - Books currently reading

- [ ] **Activity Stream**
  - Reading progress updates
  - Book additions
  - Reviews posted
  - Achievements unlocked
  - User contributions

- [ ] **Settings Pages**
  - Theme selector integration (`/settings/themes/`)
  - Custom settings navigation
  - Privacy settings
  - Notification preferences

- [ ] **Messages System**
  - Book submission notifications
  - Admin messages
  - Moderation notifications

- [ ] **Groups (if enabled)**
  - Book club groups
  - Group libraries
  - Group discussions

#### BuddyPress Templates Needed
- [ ] `members/single/member-header.php`
- [ ] `members/single/home.php`
- [ ] `members/single/settings/` templates
- [ ] `activity/` templates
- [ ] `members/members-loop.php`
- [ ] `groups/` templates (if using groups)

### 1.4 Additional Features

#### User Experience
- [ ] Responsive design (mobile-first)
- [ ] Dark mode support (via user themes)
- [ ] Fast loading (optimized assets)
- [ ] Accessibility (WCAG 2.1 AA)
- [ ] Print styles

#### Navigation
- [ ] Primary navigation menu
- [ ] Footer navigation
- [ ] User account menu
- [ ] Mobile hamburger menu
- [ ] Breadcrumbs

#### Content Areas
- [ ] Homepage template
- [ ] About page
- [ ] FAQ page
- [ ] Contact page
- [ ] Privacy policy
- [ ] Terms of service
- [ ] 404 error page
- [ ] Search results page

#### Widgets/Sidebars
- [ ] Main sidebar
- [ ] Footer widget areas (4 columns)
- [ ] Homepage widgets
- [ ] Book archive sidebar
- [ ] Profile sidebar

---

## 2. MODULAR ARCHITECTURE DESIGN

### 2.1 Directory Structure

```
gread-custom-theme/
│
├── style.css                    # Theme metadata + base styles
├── functions.php                # Theme setup and configuration
├── index.php                    # Fallback template
├── header.php                   # Site header
├── footer.php                   # Site footer
├── sidebar.php                  # Main sidebar
│
├── /templates/                  # 🔄 PAGE TEMPLATES (swap entire pages)
│   ├── page-library.php
│   ├── page-search.php
│   ├── page-submit-book.php
│   ├── page-author.php
│   ├── page-full-width.php
│   └── page-no-sidebar.php
│
├── /parts/                      # 🔄 TEMPLATE PARTS (swap components)
│   │
│   ├── /headers/                # Header variations
│   │   ├── header-default.php
│   │   ├── header-minimal.php
│   │   └── header-transparent.php
│   │
│   ├── /footers/                # Footer variations
│   │   ├── footer-default.php
│   │   ├── footer-minimal.php
│   │   └── footer-widgets.php
│   │
│   ├── /navigation/             # Navigation components
│   │   ├── nav-primary.php
│   │   ├── nav-mobile.php
│   │   └── nav-user-account.php
│   │
│   ├── /books/                  # Book-related components
│   │   ├── book-card.php        # Single book card
│   │   ├── book-grid.php        # Book grid layout
│   │   ├── book-list.php        # Book list layout
│   │   ├── book-meta.php        # Book metadata display
│   │   ├── book-cover.php       # Book cover image
│   │   ├── book-actions.php     # Add/remove buttons
│   │   ├── book-progress.php    # Progress bar
│   │   └── book-review-form.php # Review submission
│   │
│   ├── /library/                # User library components
│   │   ├── library-currently-reading.php
│   │   ├── library-want-to-read.php
│   │   ├── library-finished.php
│   │   ├── library-dnf.php
│   │   ├── library-paused.php
│   │   └── library-stats.php
│   │
│   ├── /buddypress/             # BuddyPress components
│   │   ├── member-header.php
│   │   ├── member-stats.php
│   │   ├── activity-item.php
│   │   ├── achievement-badge.php
│   │   └── theme-selector.php
│   │
│   ├── /search/                 # Search components
│   │   ├── search-form.php
│   │   ├── search-results.php
│   │   ├── search-filters.php
│   │   └── search-autocomplete.php
│   │
│   ├── /forms/                  # Form components
│   │   ├── form-book-submit.php
│   │   ├── form-chapter-submit.php
│   │   ├── form-notes.php
│   │   └── form-review.php
│   │
│   ├── /achievements/           # Gamification components
│   │   ├── achievement-card.php
│   │   ├── achievement-progress.php
│   │   ├── unlockables-grid.php
│   │   └── points-display.php
│   │
│   └── /common/                 # Reusable elements
│       ├── breadcrumbs.php
│       ├── pagination.php
│       ├── loading-spinner.php
│       ├── modal-container.php
│       └── alert-message.php
│
├── /single/                     # Single post/CPT templates
│   ├── single.php               # Blog post
│   └── single-book.php          # Book CPT
│
├── /archive/                    # Archive templates
│   ├── archive.php              # Blog archive
│   ├── archive-book.php         # Book archive
│   └── author.php               # Author archive
│
├── /buddypress/                 # BuddyPress template overrides
│   ├── members/
│   ├── activity/
│   ├── settings/
│   └── groups/
│
├── /inc/                        # Theme functionality
│   ├── setup.php                # Theme setup
│   ├── template-tags.php        # Custom template functions
│   ├── hooks.php                # Action/filter hooks
│   ├── customizer.php           # Theme customizer
│   ├── widgets.php              # Widget registration
│   ├── enqueue.php              # CSS/JS enqueuing
│   └── config.php               # 🔄 COMPONENT CONFIGURATION
│
├── /assets/
│   ├── /css/
│   │   ├── main.css             # Custom theme styles
│   │   ├── components.css       # Component-specific styles
│   │   └── utilities.css        # Utility classes
│   ├── /js/
│   │   ├── main.js              # Theme JavaScript
│   │   └── components.js        # Component interactions
│   └── /images/
│
└── /languages/                  # Translation files
```

### 2.2 Component Swapping System

#### Configuration File: `/inc/config.php`

```php
<?php
/**
 * Theme Component Configuration
 *
 * This file controls which template parts are loaded throughout the theme.
 * Change the values to swap different components in and out.
 */

return [
    // Header configuration
    'header' => [
        'type' => 'default', // Options: 'default', 'minimal', 'transparent'
        'show_search' => true,
        'show_user_menu' => true,
    ],

    // Footer configuration
    'footer' => [
        'type' => 'widgets', // Options: 'default', 'minimal', 'widgets'
        'columns' => 4,
    ],

    // Navigation configuration
    'navigation' => [
        'primary' => 'nav-primary', // Template part name
        'mobile' => 'nav-mobile',
        'user_account' => 'nav-user-account',
    ],

    // Book display configuration
    'books' => [
        'archive_layout' => 'grid', // Options: 'grid', 'list'
        'card_style' => 'book-card', // Template part name
        'show_progress' => true,
        'show_actions' => true,
    ],

    // Library configuration
    'library' => [
        'sections' => [
            'currently_reading' => true,
            'want_to_read' => true,
            'finished' => true,
            'dnf' => true,
            'paused' => true,
        ],
        'show_stats' => true,
    ],

    // BuddyPress configuration
    'buddypress' => [
        'member_header' => 'member-header', // Template part name
        'activity_style' => 'activity-item',
        'show_achievements' => true,
    ],

    // Search configuration
    'search' => [
        'form_style' => 'search-form',
        'results_style' => 'search-results',
        'enable_autocomplete' => true,
        'enable_filters' => true,
    ],
];
```

#### Template Part Loader: `/inc/template-tags.php`

```php
<?php
/**
 * Load a configurable template part
 */
function gread_get_part($section, $key = null, $args = []) {
    $config = include get_template_directory() . '/inc/config.php';

    if ($key) {
        $part_name = $config[$section][$key] ?? 'default';
    } else {
        $part_name = $config[$section]['type'] ?? 'default';
    }

    $template_path = "parts/{$section}/{$part_name}.php";

    if ($args) {
        extract($args);
    }

    locate_template($template_path, true, false);
}

/**
 * Load multiple template parts for a section
 */
function gread_get_section($section, $args = []) {
    $config = include get_template_directory() . '/inc/config.php';

    if (isset($config[$section]['sections'])) {
        foreach ($config[$section]['sections'] as $key => $enabled) {
            if ($enabled) {
                gread_get_part($section, null, array_merge($args, ['section_key' => $key]));
            }
        }
    }
}
```

### 2.3 Usage Examples

#### In `header.php`:
```php
<?php gread_get_part('headers'); ?>
```

#### In `archive-book.php`:
```php
<?php
while (have_posts()) {
    the_post();
    gread_get_part('books', 'card_style', ['book_id' => get_the_ID()]);
}
?>
```

#### In page template:
```php
<?php
// Load library sections based on config
gread_get_section('library', ['user_id' => get_current_user_id()]);
?>
```

---

## 3. PHASED MIGRATION STRATEGY

### Phase 1: Foundation (Week 1)
**Goal:** Basic theme structure with one working page

1. Create theme structure
2. Set up `functions.php` with basic setup
3. Create `header.php` and `footer.php` (using config system)
4. Create homepage template
5. Enqueue HotSoup plugin CSS/JS
6. Test: Homepage displays correctly with navigation

**Deliverable:** Working homepage you can visit

---

### Phase 2: Book Display (Week 2)
**Goal:** Replace book archive and single book pages

1. Create `archive-book.php`
2. Create `single-book.php`
3. Build book template parts:
   - `parts/books/book-card.php`
   - `parts/books/book-meta.php`
   - `parts/books/book-cover.php`
4. Test: Browse books and view single book pages

**Deliverable:** Complete book browsing experience

---

### Phase 3: User Library (Week 3)
**Goal:** Replace user library functionality

1. Create `templates/page-library.php`
2. Build library template parts:
   - `parts/library/library-currently-reading.php`
   - `parts/library/library-finished.php`
   - `parts/library/library-stats.php`
3. Integrate HotSoup library data
4. Test: User can view their library

**Deliverable:** Full library page

---

### Phase 4: BuddyPress Integration (Week 4)
**Goal:** Member profiles and activity

1. Create `/buddypress/` directory
2. Override member templates:
   - `members/single/member-header.php`
   - `members/single/home.php`
3. Build BuddyPress parts:
   - `parts/buddypress/member-header.php`
   - `parts/buddypress/activity-item.php`
4. Test: User profiles display correctly

**Deliverable:** Working member profiles

---

### Phase 5: Search & Forms (Week 5)
**Goal:** Search and submission functionality

1. Create `templates/page-search.php`
2. Create `templates/page-submit-book.php`
3. Build search parts:
   - `parts/search/search-form.php`
   - `parts/search/search-results.php`
4. Build form parts:
   - `parts/forms/form-book-submit.php`
5. Test: Search works, submissions work

**Deliverable:** Search and submission pages

---

### Phase 6: Achievements & Themes (Week 6)
**Goal:** Gamification system

1. Create achievement display templates
2. Build achievement parts:
   - `parts/achievements/achievement-card.php`
   - `parts/achievements/unlockables-grid.php`
3. Integrate theme selector
4. Test: User themes apply correctly

**Deliverable:** Achievement and theme system

---

### Phase 7: Polish & Optimization (Week 7)
**Goal:** Refinement and performance

1. Mobile responsiveness testing
2. Accessibility audit
3. Performance optimization
4. Cross-browser testing
5. Documentation

**Deliverable:** Production-ready theme

---

## 4. SWAPPING CODE SNIPPETS

### 4.1 How to Swap a Component

**Example: Change book card design**

1. Open `/inc/config.php`
2. Find the books section:
```php
'books' => [
    'card_style' => 'book-card', // Change this value
]
```
3. Create new template part: `/parts/books/book-card-v2.php`
4. Update config:
```php
'card_style' => 'book-card-v2',
```
5. Refresh page - new card style appears!

### 4.2 How to Swap an Entire Page

**Example: Replace library page**

1. Create new template: `/templates/page-library-v2.php`
2. In WordPress admin:
   - Go to Pages → Library
   - Change "Template" dropdown to "Library V2"
   - Save
3. Page now uses new template!

### 4.3 How to A/B Test Components

**Add variant system to config:**

```php
'books' => [
    'card_variants' => [
        'default' => 'book-card',
        'compact' => 'book-card-compact',
        'detailed' => 'book-card-detailed',
    ],
    'active_variant' => 'default', // Swap this to test
]
```

---

## 5. DEVELOPMENT WORKFLOW

### 5.1 Setting Up Development

```bash
# 1. Clone Dogaroni theme as starting point
cd wp-content/themes/
git clone https://github.com/GRead-Development/Dogaroni.git gread-custom

# 2. Remove Dogaroni branding, keep structure
cd gread-custom/

# 3. Update style.css metadata
# Theme Name: GRead Custom
# Description: Custom modular theme for GRead
# Version: 1.0.0

# 4. Activate in WordPress admin
```

### 5.2 Creating a New Component

```bash
# 1. Create template part file
touch parts/books/book-card-new.php

# 2. Add HTML/PHP to file
# 3. Update config.php to reference it
# 4. Test on frontend
# 5. Commit when working
```

### 5.3 Testing Changes

```bash
# Quick test checklist:
- [ ] Component displays without PHP errors
- [ ] Data loads correctly from HotSoup plugin
- [ ] Responsive on mobile
- [ ] Works when logged out
- [ ] No JavaScript console errors
```

---

## 6. INTEGRATION POINTS

### 6.1 HotSoup Plugin Hooks

The theme should use these filters/actions:

```php
// Modify book card display
add_filter('hs_book_card_html', 'gread_custom_book_card', 10, 2);

// Modify library sections
add_filter('hs_library_sections', 'gread_custom_library_sections');

// Add custom CSS classes
add_filter('hs_book_classes', 'gread_custom_book_classes', 10, 2);

// Customize achievement display
add_filter('hs_achievement_html', 'gread_custom_achievement');
```

### 6.2 BuddyPress Hooks

```php
// Customize member header
add_action('bp_before_member_header_meta', 'gread_member_stats');

// Add reading stats to activity
add_filter('bp_activity_custom_content', 'gread_activity_content', 10, 2);

// Customize profile navigation
add_action('bp_setup_nav', 'gread_custom_nav_items', 100);
```

### 6.3 Enqueuing Assets

```php
// /inc/enqueue.php
function gread_enqueue_assets() {
    // Theme styles
    wp_enqueue_style('gread-main', get_template_directory_uri() . '/assets/css/main.css');

    // HotSoup plugin compatibility
    // (Plugin already enqueues its own CSS/JS, theme just needs to style around it)

    // Theme JavaScript
    wp_enqueue_script('gread-main', get_template_directory_uri() . '/assets/js/main.js', ['jquery'], '1.0', true);

    // Pass config to JavaScript
    wp_localize_script('gread-main', 'greadConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'userId' => get_current_user_id(),
    ]);
}
add_action('wp_enqueue_scripts', 'gread_enqueue_assets');
```

---

## 7. BEST PRACTICES

### 7.1 Coding Standards
- Follow WordPress coding standards
- Use meaningful function/variable names with `gread_` prefix
- Comment complex logic
- Keep template parts under 100 lines
- Use `wp_kses_post()` for output sanitization

### 7.2 Performance
- Lazy load images (especially book covers)
- Minimize database queries
- Cache template parts when possible
- Combine/minify CSS/JS for production
- Use CDN for static assets

### 7.3 Accessibility
- Proper heading hierarchy (h1-h6)
- Alt text on all images
- ARIA labels on interactive elements
- Keyboard navigation support
- Color contrast ratios (4.5:1 minimum)

### 7.4 Documentation
- Document each template part's purpose
- List required variables/arguments
- Note dependencies (plugins, BuddyPress features)
- Keep this file updated with changes

---

## 8. QUICK START GUIDE

### To replace a single page TODAY:

1. **Pick ONE page** (e.g., book archive)
2. **Copy from Dogaroni**: `archive-book.php` → your theme
3. **Create ONE template part**: `parts/books/book-card.php`
4. **Update archive template** to use:
```php
<?php gread_get_part('books', 'card_style', ['book_id' => get_the_ID()]); ?>
```
5. **Create basic config**: `/inc/config.php` with:
```php
return ['books' => ['card_style' => 'book-card']];
```
6. **Add loader function**: Copy `gread_get_part()` to `functions.php`
7. **Test**: Visit `/books/` page

**That's it!** You now have a swappable component system for book cards.

Repeat this pattern for each page/component you want to replace.

---

## 9. TROUBLESHOOTING

**Q: Template part not loading?**
- Check file path matches config exactly
- Verify file exists in `/parts/` directory
- Look for PHP syntax errors

**Q: HotSoup features not working?**
- Ensure plugin is active
- Check if shortcode needs to be used instead
- Verify AJAX endpoints still accessible

**Q: BuddyPress templates not overriding?**
- Directory must be `/buddypress/` not `/bp/`
- Match BuddyPress file structure exactly
- Clear template cache

**Q: Config changes not applying?**
- Try clearing object cache
- Check for typos in config keys
- Verify config is being included correctly

---

## 10. RESOURCES

### Documentation
- [WordPress Template Hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/)
- [BuddyPress Theme Development](https://codex.buddypress.org/themes/)
- [WordPress Theme Handbook](https://developer.wordpress.org/themes/)

### Tools
- [Theme Check Plugin](https://wordpress.org/plugins/theme-check/)
- [Query Monitor](https://wordpress.org/plugins/query-monitor/)
- [Debug Bar](https://wordpress.org/plugins/debug-bar/)

### Your Files
- HotSoup Plugin: `/home/user/Server/`
- Dogaroni Theme: `https://github.com/GRead-Development/Dogaroni`
- This Plan: `/home/user/Server/CUSTOM-THEME-PLAN.md`

---

## NEXT STEPS

1. ✅ Review this document
2. ⬜ Decide which page to replace FIRST
3. ⬜ Set up basic theme structure
4. ⬜ Create config system
5. ⬜ Build first template part
6. ⬜ Test swapping components
7. ⬜ Expand to additional pages

**Ready to start? Pick ONE page and follow Section 8 (Quick Start Guide) above!**
