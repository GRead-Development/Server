<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_bloginfo('name'); ?> - Track Your Reading Journey</title>
    <?php wp_head(); ?>
    <style>
        /* Reset theme styles for clean landing page */
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333 !important;
            background-color: #fff !important;
        }

        /* Force all text elements to have explicit colors */
        .gread-landing-page,
        .gread-landing-page * {
            color: inherit;
        }

        .gread-landing-page h1,
        .gread-landing-page h2,
        .gread-landing-page h3,
        .gread-landing-page h4,
        .gread-landing-page h5,
        .gread-landing-page h6 {
            color: #1a202c !important;
        }

        .gread-landing-page p,
        .gread-landing-page span,
        .gread-landing-page div {
            color: #4a5568 !important;
        }

        .gread-landing-page a {
            color: #667eea !important;
            text-decoration: none;
        }

        /* Hero section overrides */
        .gread-hero h1,
        .gread-hero h2,
        .gread-hero p,
        .gread-hero span {
            color: #fff !important;
        }

        /* Stats section overrides */
        .gread-stats h1,
        .gread-stats h2,
        .gread-stats h3,
        .gread-stats p,
        .gread-stats span,
        .gread-stats div {
            color: #fff !important;
        }

        /* Feature cards */
        .gread-feature-card h3 {
            color: #1a202c !important;
        }

        .gread-feature-card p {
            color: #4a5568 !important;
        }

        /* Hide WordPress admin bar on landing page */
        #wpadminbar {
            display: none !important;
        }

        html {
            margin-top: 0 !important;
        }

        /* Ensure landing page takes full width */
        .gread-landing-page {
            width: 100%;
            margin: 0;
            padding: 0;
            background-color: #fff !important;
        }

        /* Override any theme containers */
        #content,
        .site-content,
        .content-area {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
            background-color: transparent !important;
        }

        /* Reset theme link styles */
        .gread-landing-page a:hover {
            color: #667eea !important;
        }

        .gread-btn-primary,
        .gread-btn-secondary {
            text-decoration: none !important;
        }
    </style>
</head>
<body <?php body_class('gread-landing-full-page'); ?>>

<?php
// Redirect logged-in users to activity feed
if (is_user_logged_in()) {
    wp_redirect(home_url('/activity'));
    exit;
}

// Output the landing page content for non-logged-in users
echo do_shortcode('[gread_landing_page]');
?>

<?php wp_footer(); ?>
</body>
</html>
