<?php

// Allows users to register/login with Apple/Google


if (!defined('ABSPATH'))
{
	exit;
}


// Redirect from the Wordpress login page to the custom login page
function hs_redirect_to_custom_login()
{
	$custom_login_enabled = get_option('hs_custom_login_enabled', true);
	$custom_login_page = get_option('hs_custom_login_page', home_url('/login'));

	if (!$custom_login_enabled)
	{
		return;
	}

	// If the user is already on the custom login page, chill
	if (is_page() && get_permalink() === $custom_login_page)
	{
		return;
	}


	// Only redirect on wp-login.php
	global $pagenow;
	if ($pagenow === 'wp-login.php' && !isset($_GET['action']))
	{
		wp_redirect($custom_login_page);
		exit;
	}
}
add_action('init', 'hs_redirect_to_custom_login');


// Redirect from the Wordpress registration page to the custom registration page
function hs_redirect_to_custom_registration()
{
	$custom_registration_enabled = get_option('hs_custom_registration_enabled', true);
	$custom_registration_page = get_option('hs_custom_registration_page', home_url('/register'));

	if (!$custom_registration_enabled)
	{
		return;
	}

	global $pagenow;
	if ($pagenow === 'wp-login.php' && isset($_GET['action']) && $_GET['action'] === 'register')
	{
		wp_redirect($custom_registration_page);
		exit;
	}
}
add_action('init', 'hs_redirect_to_custom_registration');


// Add social login buttons to the Wordpress login page
function hs_add_social_auth_to_login_page()
{
	$add_to_wp_login = get_option('hs_add_social_to_wp_login', false);

	if (!$add_to_wp_login)
	{
		return;
	}


	$apple_enabled = get_option('hs_apple_enabled', false);
	$google_enabled = get_option('hs_google_enabled', false);

	if (!$apple_enabled && !$google_enabled)
	{
		return;
	}

	?>

	<style>
		.hs-wp-login-social
		{
			margin-bottom: 20px;
		}

		.hs-wp-login-social .hs-social-btn
		{
			width: 100%;
			margin-bottom: 10px;
		}

		.hs-wp-login-divider
		{
			text-align: center;
			margin: 20px 0;
			color: #72777c;
		}

	</style>

	<div class="hs-login-social">
	  <?php if ($apple_enabled): ?>
        <button class="hs-social-btn hs-apple-signin-btn" data-mode="signin" style="width: 100%; margin-bottom: 10px;">
            Sign in with Apple
        </button>
        <?php endif; ?>

        <?php if ($google_enabled): ?>
        <div id="hs-google-signin-button"></div>
        <button class="hs-social-btn hs-google-signin-btn" data-mode="signin" style="display: none;">
            Sign in with Google
        </button>
        <?php endif; ?>

        <div class="hs-wp-login-divider">or</div>
	</div>

	<input type="hidden" class="hs-auth-mode" value="signin">
	<div class="hs-auth-loading"></div>
	<div class="hs-auth-error"></div>
	<div class="hs-auth-success"></div>
	<?php
}
add_action('login_form', 'hs_add_social_auth_to_login_page');


function hs_enqueue_login_scripts() {
    $add_to_wp_login = get_option('hs_add_social_to_wp_login', false);

    if (!$add_to_wp_login) {
        return;
    }

    // Enqueue CSS
    wp_enqueue_style('hs-social-auth', plugins_url('css/social-auth.css', dirname(__FILE__, 2)), array(), '0.37');

    // Enqueue JavaScript
    wp_enqueue_script('hs-social-auth', plugins_url('js/social-auth.js', dirname(__FILE__, 2)), array('jquery'), '0.37', true);

    // Localize script
    wp_localize_script('hs-social-auth', 'hsAuthConfig', array(
        'apiUrl' => rest_url('gread/v1'),
        'appleEnabled' => get_option('hs_apple_enabled', false),
        'googleEnabled' => get_option('hs_google_enabled', false),
        'appleClientId' => get_option('hs_apple_client_id', ''),
        'googleClientId' => get_option('hs_google_client_id', ''),
        'redirectUri' => home_url('/'),
        'redirectAfterLogin' => home_url('/'),
        'tosUrl' => get_option('hs_tos_url', 'https://gread.fun/tos'),
    ));
}
add_action('login_enqueue_scripts', 'hs_enqueue_login_scripts');


// REWRITE

function hs_custom_login_logo() {
    $custom_logo = get_option('hs_login_logo_url', '');

    if (empty($custom_logo)) {
        return;
    }
    ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url('<?php echo esc_url($custom_logo); ?>');
            height: 80px;
            width: 320px;
            background-size: contain;
            background-repeat: no-repeat;
            padding-bottom: 30px;
        }
    </style>
    <?php
}
add_action('login_enqueue_scripts', 'hs_custom_login_logo');

/**
 * Customize login logo URL
 */
function hs_custom_login_logo_url() {
    return home_url();
}
add_filter('login_headerurl', 'hs_custom_login_logo_url');

/**
 * Customize login logo title
 */
function hs_custom_login_logo_title() {
    return get_bloginfo('name');
}
add_filter('login_headertext', 'hs_custom_login_logo_title');

/**
 * Remove WordPress registration link if custom registration is enabled
 */
function hs_remove_register_link($links) {
    $custom_registration_enabled = get_option('hs_custom_registration_enabled', true);

    if ($custom_registration_enabled) {
        $custom_registration_page = get_option('hs_custom_registration_page', home_url('/register'));
        return '<a href="' . esc_url($custom_registration_page) . '">Register</a>';
    }

    return $links;
}
add_filter('register', 'hs_remove_register_link');

/**
 * Redirect after login based on user role
 */
function hs_login_redirect($redirect_to, $request, $user) {
    // If no user, use default redirect
    if (!isset($user->roles) || !is_array($user->roles)) {
        return $redirect_to;
    }

    // Redirect admins to dashboard
    if (in_array('administrator', $user->roles)) {
        return admin_url();
    }

    // Redirect subscribers to home or profile
    if (in_array('subscriber', $user->roles)) {
        $custom_redirect = get_option('hs_login_redirect_url', home_url('/'));
        return $custom_redirect;
    }

    return $redirect_to;
}
add_filter('login_redirect', 'hs_login_redirect', 10, 3);

/**
 * Redirect after logout
 */
function hs_logout_redirect() {
    $logout_redirect = get_option('hs_logout_redirect_url', home_url('/'));
    wp_redirect($logout_redirect);
    exit;
}
add_action('wp_logout', 'hs_logout_redirect');

/**
 * Hide WordPress admin bar for subscribers
 */
function hs_hide_admin_bar() {
    if (!current_user_can('administrator')) {
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'hs_hide_admin_bar');

/**
 * Prevent subscribers from accessing WordPress admin
 */
function hs_prevent_admin_access() {
    if (is_admin() && !current_user_can('administrator') && !wp_doing_ajax()) {
        wp_redirect(home_url());
        exit;
    }
}
add_action('admin_init', 'hs_prevent_admin_access');

/**
 * Add settings to Social Auth settings page
 */
function hs_register_login_integration_settings() {
    // Custom login page settings
    register_setting('hs_social_auth', 'hs_custom_login_enabled', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ));

    register_setting('hs_social_auth', 'hs_custom_login_page', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => home_url('/login'),
    ));

    // Custom registration page settings
    register_setting('hs_social_auth', 'hs_custom_registration_enabled', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ));

    register_setting('hs_social_auth', 'hs_custom_registration_page', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => home_url('/register'),
    ));

    // Add social auth to default wp-login.php
    register_setting('hs_social_auth', 'hs_add_social_to_wp_login', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => false,
    ));

    // Login/logout redirect URLs
    register_setting('hs_social_auth', 'hs_login_redirect_url', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => home_url('/'),
    ));

    register_setting('hs_social_auth', 'hs_logout_redirect_url', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => home_url('/'),
    ));

    // Login logo
    register_setting('hs_social_auth', 'hs_login_logo_url', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => '',
    ));
}
add_action('admin_init', 'hs_register_login_integration_settings');

/**
 * BuddyPress registration override
 */
function hs_buddypress_registration_override() {
    // If BuddyPress is active and custom registration is enabled
    if (function_exists('bp_is_register_page') && bp_is_register_page()) {
        $custom_registration_enabled = get_option('hs_custom_registration_enabled', true);
        $custom_registration_page = get_option('hs_custom_registration_page', home_url('/register'));

        if ($custom_registration_enabled && get_permalink() !== $custom_registration_page) {
            wp_redirect($custom_registration_page);
            exit;
        }
    }
}
add_action('template_redirect', 'hs_buddypress_registration_override');

/**
 * BuddyPress login override
 */
function hs_buddypress_login_override() {
    // If BuddyPress is active and custom login is enabled
    if (function_exists('bp_is_activation_page')) {
        $custom_login_enabled = get_option('hs_custom_login_enabled', true);
        $custom_login_page = get_option('hs_custom_login_page', home_url('/login'));

        // Don't override activation pages
        if (bp_is_activation_page()) {
            return;
        }
    }
}
add_action('template_redirect', 'hs_buddypress_login_override');
