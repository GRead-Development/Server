<?php
/**
 * Chapter Submissions Frontend Display
 * Displays chapter information and submission button on book pages
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add chapters display and submit button to book pages
 */
function hs_display_chapters_on_book_page($content) {
    if (!is_singular('book') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    global $post;
    $book_id = $post->ID;
    $user_id = get_current_user_id();

    // Get approved chapters
    $approved_chapters = hs_get_approved_chapters($book_id);

    // Check if user has pending submission
    $has_pending_submission = false;
    if ($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hs_chapter_submissions';
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE book_id = %d AND user_id = %d AND status = 'pending'",
            $book_id,
            $user_id
        ));
        $has_pending_submission = ($pending > 0);
    }

    $chapters_html = '';

    // Display approved chapters
    if ($approved_chapters && !empty($approved_chapters)) {
        $chapters_html .= '<div class="hs-book-chapters">';
        $chapters_html .= '<h2 onclick="hsToggleChapters()">';
        $chapters_html .= 'Chapters (' . count($approved_chapters) . ')';
        $chapters_html .= ' <span class="hs-chapters-toggle">▼</span>';
        $chapters_html .= '</h2>';
        $chapters_html .= '<div class="hs-chapters-content">';
        $chapters_html .= '<ul class="hs-chapters-list">';

        foreach ($approved_chapters as $chapter) {
            $chapters_html .= '<li class="hs-chapter-item">';
            $chapters_html .= '<span class="hs-chapter-number">Chapter ' . intval($chapter['number']) . '</span>';
            $chapters_html .= '<span class="hs-chapter-title">' . esc_html($chapter['title']) . '</span>';
            $chapters_html .= '</li>';
        }

        $chapters_html .= '</ul>';
        $chapters_html .= '</div>';
        $chapters_html .= '</div>';
    } else {
        // No chapters yet - show empty state
        $chapters_html .= '<div class="hs-book-chapters">';
        $chapters_html .= '<h2>Chapters</h2>';
        $chapters_html .= '<p class="hs-chapters-empty">No chapter information available yet. Be the first to contribute!</p>';
        $chapters_html .= '</div>';
    }

    // Add submit button for logged-in users
    if ($user_id) {
        if ($has_pending_submission) {
            $chapters_html .= '<div class="hs-chapters-pending-notice">';
            $chapters_html .= '<strong>Your chapter submission is pending review.</strong> Thank you for your contribution!';
            $chapters_html .= '</div>';
        } else {
            $chapters_html .= '<button class="hs-submit-chapters-btn" data-book-id="' . $book_id . '">';
            $chapters_html .= 'Submit Chapter Information';
            $chapters_html .= '</button>';
        }
    } else {
        $chapters_html .= '<p><a href="' . wp_login_url(get_permalink()) . '">Log in</a> to submit chapter information.</p>';
    }

    // Add JavaScript for collapsible chapters
    $chapters_html .= '
    <script>
    function hsToggleChapters() {
        const content = document.querySelector(".hs-chapters-content");
        const toggle = document.querySelector(".hs-chapters-toggle");

        if (content && toggle) {
            content.classList.toggle("collapsed");
            toggle.classList.toggle("collapsed");
        }
    }
    </script>';

    return $content . $chapters_html;
}
add_filter('the_content', 'hs_display_chapters_on_book_page', 20);

/**
 * Enqueue chapter submission assets on book pages
 */
function hs_enqueue_chapter_submission_assets() {
    if (is_singular('book')) {
        $plugin_url = plugin_dir_url(dirname(__FILE__));

        // Enqueue CSS
        wp_enqueue_style(
            'hs-chapter-submissions',
            $plugin_url . 'css/chapter-submissions.css',
            [],
            '1.0.0'
        );

        // Enqueue JavaScript (depends on jQuery and wp-api for REST nonce)
        wp_enqueue_script(
            'hs-chapter-submissions',
            $plugin_url . 'js/chapter-submission-modal.js',
            ['jquery', 'wp-api'],
            '1.0.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'hs_enqueue_chapter_submission_assets');
