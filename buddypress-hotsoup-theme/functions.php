<?php
/**
 * BuddyPress HotSoup Theme Functions
 *
 * @package BP_HotSoup_Theme
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme Setup
 */
function bp_hotsoup_theme_setup() {
    // Add default posts and comments RSS feed links to head
    add_theme_support('automatic-feed-links');

    // Let WordPress manage the document title
    add_theme_support('title-tag');

    // Enable support for Post Thumbnails on posts and pages
    add_theme_support('post-thumbnails');
    set_post_thumbnail_size(1200, 630, true);

    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'bp-hotsoup-theme'),
        'footer'  => __('Footer Menu', 'bp-hotsoup-theme'),
    ));

    // Switch default core markup to output valid HTML5
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'script',
        'style',
    ));

    // Add support for responsive embeds
    add_theme_support('responsive-embeds');

    // Add support for editor styles
    add_theme_support('editor-styles');

    // Add support for wide and full aligned images
    add_theme_support('align-wide');
}
add_action('after_setup_theme', 'bp_hotsoup_theme_setup');

/**
 * Register Widget Areas
 */
function bp_hotsoup_widgets_init() {
    register_sidebar(array(
        'name'          => __('Sidebar', 'bp-hotsoup-theme'),
        'id'            => 'sidebar-1',
        'description'   => __('Add widgets here to appear in your sidebar.', 'bp-hotsoup-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));

    register_sidebar(array(
        'name'          => __('Footer', 'bp-hotsoup-theme'),
        'id'            => 'footer-1',
        'description'   => __('Add widgets here to appear in your footer.', 'bp-hotsoup-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
}
add_action('widgets_init', 'bp_hotsoup_widgets_init');

/**
 * Enqueue Scripts and Styles
 */
function bp_hotsoup_scripts() {
    // Theme stylesheet
    wp_enqueue_style('bp-hotsoup-style', get_stylesheet_uri(), array(), '1.0.0');

    // Theme JavaScript
    wp_enqueue_script('bp-hotsoup-script', get_template_directory_uri() . '/js/main.js', array('jquery'), '1.0.0', true);

    // Comments reply script
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'bp_hotsoup_scripts');

/**
 * BuddyPress Support
 */
function bp_hotsoup_buddypress_support() {
    add_theme_support('buddypress');
}
add_action('after_setup_theme', 'bp_hotsoup_buddypress_support');

/**
 * Custom Excerpt Length
 */
function bp_hotsoup_excerpt_length($length) {
    return 30;
}
add_filter('excerpt_length', 'bp_hotsoup_excerpt_length');

/**
 * Custom Excerpt More
 */
function bp_hotsoup_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'bp_hotsoup_excerpt_more');

/**
 * Add body classes for better styling control
 */
function bp_hotsoup_body_classes($classes) {
    // Add class if sidebar is active
    if (is_active_sidebar('sidebar-1')) {
        $classes[] = 'has-sidebar';
    }

    // Add class for BuddyPress pages
    if (function_exists('is_buddypress') && is_buddypress()) {
        $classes[] = 'buddypress-page';
    }

    // Add class for HotSoup pages
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'my_books') || has_shortcode($post->post_content, 'book_directory'))) {
        $classes[] = 'hotsoup-page';
    }

    return $classes;
}
add_filter('body_class', 'bp_hotsoup_body_classes');

/**
 * Custom Navigation Walker for Better Mobile Support
 */
class BP_HotSoup_Walker_Nav_Menu extends Walker_Nav_Menu {
    function start_lvl(&$output, $depth = 0, $args = array()) {
        $indent = str_repeat("\t", $depth);
        $output .= "\n$indent<ul class=\"sub-menu\">\n";
    }
}

/**
 * Get the primary navigation menu
 */
function bp_hotsoup_primary_nav() {
    wp_nav_menu(array(
        'theme_location' => 'primary',
        'menu_class'     => 'primary-menu',
        'container'      => false,
        'fallback_cb'    => 'bp_hotsoup_fallback_menu',
        'walker'         => new BP_HotSoup_Walker_Nav_Menu(),
    ));
}

/**
 * Fallback menu if no menu is set
 */
function bp_hotsoup_fallback_menu() {
    echo '<ul class="primary-menu">';
    echo '<li><a href="' . esc_url(home_url('/')) . '">' . __('Home', 'bp-hotsoup-theme') . '</a></li>';

    if (function_exists('bp_is_active')) {
        echo '<li><a href="' . esc_url(bp_get_members_directory_permalink()) . '">' . __('Members', 'bp-hotsoup-theme') . '</a></li>';
        echo '<li><a href="' . esc_url(bp_get_activity_directory_permalink()) . '">' . __('Activity', 'bp-hotsoup-theme') . '</a></li>';

        if (bp_is_active('groups')) {
            echo '<li><a href="' . esc_url(bp_get_groups_directory_permalink()) . '">' . __('Groups', 'bp-hotsoup-theme') . '</a></li>';
        }
    }

    echo '</ul>';
}

/**
 * HotSoup Integration Enhancements
 */
function bp_hotsoup_hotsoup_enhancements() {
    // Only load on relevant pages
    global $post;
    if (!is_a($post, 'WP_Post')) {
        return;
    }

    $load_hotsoup = (
        has_shortcode($post->post_content, 'my_books') ||
        has_shortcode($post->post_content, 'book_directory') ||
        has_shortcode($post->post_content, 'hs_book_search') ||
        is_singular('book') ||
        (function_exists('bp_is_user') && bp_is_user())
    );

    if ($load_hotsoup) {
        // Additional HotSoup styling
        wp_enqueue_style(
            'bp-hotsoup-hotsoup-integration',
            get_template_directory_uri() . '/css/hotsoup-integration.css',
            array('bp-hotsoup-style'),
            '1.0.0'
        );
    }
}
add_action('wp_enqueue_scripts', 'bp_hotsoup_hotsoup_enhancements');

/**
 * Customize BuddyPress activity avatar size
 */
function bp_hotsoup_activity_avatar_size() {
    return 60;
}
add_filter('bp_activity_avatar_size', 'bp_hotsoup_activity_avatar_size');

/**
 * Add search form to header
 */
function bp_hotsoup_header_search() {
    ?>
    <div class="header-search">
        <form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
            <input type="search" class="search-field" placeholder="<?php echo esc_attr_x('Search...', 'placeholder', 'bp-hotsoup-theme'); ?>" value="<?php echo get_search_query(); ?>" name="s" />
            <button type="submit" class="search-submit">
                <span class="screen-reader-text"><?php echo _x('Search', 'submit button', 'bp-hotsoup-theme'); ?></span>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16zM19 19l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>
    </div>
    <?php
}

/**
 * Pagination
 */
function bp_hotsoup_pagination() {
    global $wp_query;

    if ($wp_query->max_num_pages <= 1) {
        return;
    }

    $paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
    $max   = intval($wp_query->max_num_pages);

    if ($paged >= 1) {
        $links = array();
    }

    if ($paged >= 3) {
        $links[] = 1;
        $links[] = 2;
    }

    if ($paged + 1 <= $max) {
        $links[] = $paged + 1;
    }

    if ($paged >= 2) {
        $links[] = $paged - 1;
    }

    echo '<div class="pagination"><ul>';

    if (get_previous_posts_link()) {
        printf('<li>%s</li>' . "\n", get_previous_posts_link('&laquo; Previous'));
    }

    if (!in_array(1, $links)) {
        $class = 1 == $paged ? ' class="active"' : '';
        printf('<li%s><a href="%s">%s</a></li>' . "\n", $class, esc_url(get_pagenum_link(1)), '1');

        if (!in_array(2, $links)) {
            echo '<li>...</li>';
        }
    }

    sort($links);
    foreach ((array) $links as $link) {
        $class = $paged == $link ? ' class="active"' : '';
        printf('<li%s><a href="%s">%s</a></li>' . "\n", $class, esc_url(get_pagenum_link($link)), $link);
    }

    if (!in_array($max, $links)) {
        if (!in_array($max - 1, $links)) {
            echo '<li>...</li>' . "\n";
        }

        $class = $paged == $max ? ' class="active"' : '';
        printf('<li%s><a href="%s">%s</a></li>' . "\n", $class, esc_url(get_pagenum_link($max)), $max);
    }

    if (get_next_posts_link()) {
        printf('<li>%s</li>' . "\n", get_next_posts_link('Next &raquo;'));
    }

    echo '</ul></div>';
}

/**
 * Customize comment form
 */
function bp_hotsoup_comment_form_defaults($defaults) {
    $defaults['class_submit'] = 'button';
    $defaults['title_reply_before'] = '<h3 id="reply-title" class="comment-reply-title">';
    $defaults['title_reply_after'] = '</h3>';
    return $defaults;
}
add_filter('comment_form_defaults', 'bp_hotsoup_comment_form_defaults');

/**
 * Display user profile link if logged in
 */
function bp_hotsoup_user_profile_link() {
    if (!is_user_logged_in()) {
        return;
    }

    $current_user = wp_get_current_user();

    if (function_exists('bp_core_get_user_domain')) {
        $profile_url = bp_core_get_user_domain($current_user->ID);
    } else {
        $profile_url = get_edit_user_link($current_user->ID);
    }

    printf(
        '<a href="%s" class="user-profile-link">%s</a>',
        esc_url($profile_url),
        esc_html($current_user->display_name)
    );
}

/**
 * Security: Remove WordPress version from header
 */
remove_action('wp_head', 'wp_generator');

/**
 * Optimize performance: Remove emoji scripts if not needed
 */
function bp_hotsoup_disable_emojis() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
}
add_action('init', 'bp_hotsoup_disable_emojis');
