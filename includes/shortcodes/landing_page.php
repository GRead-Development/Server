<?php

/**
 * Landing Page Shortcode
 *
 * Creates a beautiful landing page with:
 * - Hero section with login/register CTAs
 * - Features showcase
 * - Recently added books
 * - Call to action
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode: [gread_landing_page]
 * Displays the full landing page experience
 */
function hs_gread_landing_page_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_books' => 'yes',
        'book_limit' => 12,
    ), $atts, 'gread_landing_page');

    $show_books = $atts['show_books'] === 'yes';
    $book_limit = absint($atts['book_limit']);

    $is_logged_in = is_user_logged_in();
    $login_url = wp_login_url();
    $register_url = wp_registration_url();

    ob_start();
    ?>
    <div class="gread-landing-page">
        <!-- Hero Section -->
        <section class="gread-hero">
            <div class="gread-hero-content">
                <h1 class="gread-hero-title">Track Your Reading Journey</h1>
                <p class="gread-hero-subtitle">Discover, track, and share your love of books with a community of passionate readers</p>

                <?php if (!$is_logged_in): ?>
                    <div class="gread-hero-cta">
                        <a href="<?php echo esc_url($register_url); ?>" class="gread-btn gread-btn-primary">Get Started Free</a>
                        <a href="<?php echo esc_url($login_url); ?>" class="gread-btn gread-btn-secondary">Sign In</a>
                    </div>
                <?php else: ?>
                    <div class="gread-hero-cta">
                        <a href="<?php echo esc_url(bp_loggedin_user_domain() . 'my-books/'); ?>" class="gread-btn gread-btn-primary">Go to My Library</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Features Section -->
        <section class="gread-features">
            <div class="gread-features-container">
                <h2 class="gread-section-title">Why Readers Love GRead</h2>

                <div class="gread-features-grid">
                    <div class="gread-feature-card">
                        <div class="gread-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                        </div>
                        <h3 class="gread-feature-title">Track Your Progress</h3>
                        <p class="gread-feature-description">Keep track of every page you read with our intuitive progress tracking system. Update your reading progress and see your reading stats grow.</p>
                    </div>

                    <div class="gread-feature-card">
                        <div class="gread-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                        </div>
                        <h3 class="gread-feature-title">Rate & Review</h3>
                        <p class="gread-feature-description">Share your thoughts on books you've read. Write reviews, rate books, and help others discover their next favorite read.</p>
                    </div>

                    <div class="gread-feature-card">
                        <div class="gread-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <h3 class="gread-feature-title">Join the Community</h3>
                        <p class="gread-feature-description">Connect with fellow readers, follow friends, and discover what books others are enjoying. Share your reading journey together.</p>
                    </div>

                    <div class="gread-feature-card">
                        <div class="gread-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="7"></circle>
                                <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                            </svg>
                        </div>
                        <h3 class="gread-feature-title">Earn Achievements</h3>
                        <p class="gread-feature-description">Unlock achievements as you read, review, and contribute. Earn points, unlock themes, and climb the leaderboards.</p>
                    </div>

                    <div class="gread-feature-card">
                        <div class="gread-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                            </svg>
                        </div>
                        <h3 class="gread-feature-title">Massive Book Database</h3>
                        <p class="gread-feature-description">Browse thousands of books with detailed information. Can't find a book? Submit it to our database and earn rewards!</p>
                    </div>

                    <div class="gread-feature-card">
                        <div class="gread-feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"></path>
                                <polygon points="18 2 22 6 12 16 8 16 8 12 18 2"></polygon>
                            </svg>
                        </div>
                        <h3 class="gread-feature-title">Take Notes</h3>
                        <p class="gread-feature-description">Capture your thoughts, quotes, and insights as you read. Keep all your book notes organized in one place.</p>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($show_books): ?>
            <!-- Recently Added Books Section -->
            <section class="gread-books-preview">
                <div class="gread-books-container">
                    <h2 class="gread-section-title">Recently Added Books</h2>
                    <p class="gread-section-subtitle">Discover the latest additions to our growing library</p>

                    <?php echo do_shortcode('[recently_added_books limit="' . $book_limit . '"]'); ?>

                    <?php if (!$is_logged_in): ?>
                        <div class="gread-books-cta">
                            <a href="<?php echo esc_url($register_url); ?>" class="gread-btn gread-btn-primary">Join GRead to Start Tracking</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Statistics Section -->
        <section class="gread-stats">
            <div class="gread-stats-container">
                <h2 class="gread-section-title">GRead by the Numbers</h2>
                <div class="gread-stats-grid">
                    <?php
                    global $wpdb;

                    // Count total books
                    $book_count = wp_count_posts('book');
                    $total_books = $book_count->publish;

                    // Count total users
                    $total_users = count_users();
                    $user_count = $total_users['total_users'];

                    // Count total reviews
                    $reviews_table = $wpdb->prefix . 'hs_book_reviews';
                    $review_count = $wpdb->get_var("SELECT COUNT(*) FROM {$reviews_table}");

                    // Count total pages read
                    $total_pages = get_option('hs_total_pages_read_global', 0);
                    ?>

                    <div class="gread-stat-card">
                        <div class="gread-stat-number"><?php echo number_format($total_books); ?></div>
                        <div class="gread-stat-label">Books in Database</div>
                    </div>

                    <div class="gread-stat-card">
                        <div class="gread-stat-number"><?php echo number_format($user_count); ?></div>
                        <div class="gread-stat-label">Active Readers</div>
                    </div>

                    <div class="gread-stat-card">
                        <div class="gread-stat-number"><?php echo number_format($review_count); ?></div>
                        <div class="gread-stat-label">Book Reviews</div>
                    </div>

                    <?php if ($total_pages > 0): ?>
                        <div class="gread-stat-card">
                            <div class="gread-stat-number"><?php echo number_format($total_pages); ?></div>
                            <div class="gread-stat-label">Pages Read</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Final CTA Section -->
        <?php if (!$is_logged_in): ?>
            <section class="gread-final-cta">
                <div class="gread-final-cta-content">
                    <h2 class="gread-cta-title">Ready to Start Your Reading Journey?</h2>
                    <p class="gread-cta-subtitle">Join thousands of readers tracking their books on GRead</p>
                    <div class="gread-hero-cta">
                        <a href="<?php echo esc_url($register_url); ?>" class="gread-btn gread-btn-primary gread-btn-large">Create Your Free Account</a>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gread_landing_page', 'hs_gread_landing_page_shortcode');

/**
 * Enqueue landing page assets
 */
function hs_landing_page_enqueue_assets() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gread_landing_page')) {
        wp_enqueue_style('hs-style');
    }
}
add_action('wp_enqueue_scripts', 'hs_landing_page_enqueue_assets');
