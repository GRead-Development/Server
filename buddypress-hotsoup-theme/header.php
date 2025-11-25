<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
    <header class="site-header">
        <div class="site-container">
            <div class="site-branding">
                <?php if (has_custom_logo()) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <h1 class="site-title">
                        <a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                            <?php bloginfo('name'); ?>
                        </a>
                    </h1>
                    <?php
                    $description = get_bloginfo('description', 'display');
                    if ($description || is_customize_preview()) :
                    ?>
                        <p class="site-description"><?php echo $description; ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <nav class="main-navigation" role="navigation" aria-label="<?php esc_attr_e('Primary Navigation', 'bp-hotsoup-theme'); ?>">
                <?php bp_hotsoup_primary_nav(); ?>
            </nav>

            <div class="header-actions">
                <?php if (is_user_logged_in()) : ?>
                    <?php bp_hotsoup_user_profile_link(); ?>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="logout-link">
                        <?php _e('Logout', 'bp-hotsoup-theme'); ?>
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="login-link">
                        <?php _e('Login', 'bp-hotsoup-theme'); ?>
                    </a>
                    <?php if (get_option('users_can_register')) : ?>
                        <a href="<?php echo esc_url(wp_registration_url()); ?>" class="register-link button">
                            <?php _e('Register', 'bp-hotsoup-theme'); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main id="main" class="site-main">
        <div class="site-container">
