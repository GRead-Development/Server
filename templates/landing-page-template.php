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
            color: #333;
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
        }

        /* Override any theme containers */
        #content,
        .site-content,
        .content-area {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body <?php body_class('gread-landing-full-page'); ?>>

<?php
// Output the landing page content
echo do_shortcode('[gread_landing_page]');
?>

<?php wp_footer(); ?>
</body>
</html>
