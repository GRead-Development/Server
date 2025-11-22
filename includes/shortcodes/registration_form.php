<?php
/**
 * Registration Form Shortcode
 *
 * Displays registration form with social authentication options
 *
 * @package HotSoup
 * @since 0.37
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registration Form Shortcode
 *
 * Usage: [hs_registration_form]
 */
function hs_registration_form_shortcode($atts) {
    // Don't show form if user is already logged in
    if (is_user_logged_in()) {
        return '<div class="hs-auth-container"><p>You are already logged in. <a href="' . esc_url(home_url('/')) . '">Go to homepage</a></p></div>';
    }

    $atts = shortcode_atts(array(
        'redirect' => home_url('/'),
        'show_email' => 'true',
    ), $atts);

    $apple_enabled = get_option('hs_apple_enabled', false);
    $google_enabled = get_option('hs_google_enabled', false);
    $show_email = filter_var($atts['show_email'], FILTER_VALIDATE_BOOLEAN);
    $tos_url = get_option('hs_tos_url', 'https://gread.fun/tos');

    ob_start();
    ?>
    <div class="hs-auth-container">
        <h2>Create Your Account</h2>

        <!-- Messages -->
        <div class="hs-auth-loading"></div>
        <div class="hs-auth-error"></div>
        <div class="hs-auth-success"></div>

        <!-- Hidden mode field -->
        <input type="hidden" class="hs-auth-mode" value="register">

        <!-- Social Authentication Buttons -->
        <?php if ($apple_enabled || $google_enabled): ?>
        <div class="hs-social-buttons">
            <?php if ($apple_enabled): ?>
            <button class="hs-social-btn hs-apple-signin-btn" data-mode="register">
                Sign up with Apple
            </button>
            <?php endif; ?>

            <?php if ($google_enabled): ?>
            <!-- Google's official button will be rendered here by JavaScript -->
            <div id="hs-google-signin-button"></div>
            <!-- Fallback button -->
            <button class="hs-social-btn hs-google-signin-btn" data-mode="register" style="display: none;">
                Sign up with Google
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Divider -->
        <?php if (($apple_enabled || $google_enabled) && $show_email): ?>
        <div class="hs-auth-divider">or</div>
        <?php endif; ?>

        <!-- Email Registration Form -->
        <?php if ($show_email): ?>
        <form class="hs-email-registration-form active">
            <div class="hs-form-group">
                <label for="hs-email">Email Address</label>
                <input type="email" id="hs-email" name="email" required autocomplete="email">
            </div>

            <div class="hs-form-group">
                <label for="hs-password">Password</label>
                <input type="password" id="hs-password" name="password" required autocomplete="new-password" minlength="8">
                <small style="color: #666; font-size: 13px;">Minimum 8 characters</small>
            </div>

            <div class="hs-form-group">
                <label for="hs-username">Username</label>
                <input type="text" id="hs-username" name="username" required autocomplete="username" minlength="3" maxlength="20" pattern="[a-zA-Z0-9_]+">
                <span class="hs-username-warning">
                    <strong>Important:</strong> Your username CANNOT be changed after registration. Choose carefully!
                </span>
                <div class="hs-username-feedback"></div>
            </div>

            <div class="hs-tos-group">
                <div class="hs-tos-checkbox">
                    <input type="checkbox" id="hs-accept-tos" name="accept_tos" required>
                    <label for="hs-accept-tos">
                        I accept the <a href="<?php echo esc_url($tos_url); ?>" target="_blank">terms of service</a>
                    </label>
                </div>
            </div>

            <button type="submit" class="hs-submit-btn">Create Account</button>
        </form>
        <?php endif; ?>

        <!-- Social Registration Form (shown after social button click) -->
        <form class="hs-registration-form">
            <a href="#" class="hs-back-btn" onclick="location.reload(); return false;">Back</a>

            <input type="hidden" class="hs-auth-provider" name="provider">
            <input type="hidden" class="hs-auth-token" name="token">
            <input type="hidden" class="hs-auth-userdata" name="userdata">

            <div class="hs-form-group">
                <label for="hs-username">Choose a Username</label>
                <input type="text" id="hs-username" name="username" required autocomplete="username" minlength="3" maxlength="20" pattern="[a-zA-Z0-9_]+">
                <span class="hs-username-warning">
                    <strong>Important:</strong> Your username CANNOT be changed after registration. Choose carefully!
                </span>
                <div class="hs-username-feedback"></div>
            </div>

            <div class="hs-tos-group">
                <div class="hs-tos-checkbox">
                    <input type="checkbox" id="hs-accept-tos" name="accept_tos" required>
                    <label for="hs-accept-tos">
                        I accept the <a href="<?php echo esc_url($tos_url); ?>" target="_blank">terms of service</a>
                    </label>
                </div>
            </div>

            <button type="submit" class="hs-submit-btn">Complete Registration</button>
        </form>

        <!-- Toggle to Sign In -->
        <div class="hs-auth-toggle">
            Already have an account? <a href="<?php echo esc_url(wp_login_url($atts['redirect'])); ?>">Sign in</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('hs_registration_form', 'hs_registration_form_shortcode');

/**
 * Sign-In Form Shortcode
 *
 * Usage: [hs_signin_form]
 */
function hs_signin_form_shortcode($atts) {
    // Don't show form if user is already logged in
    if (is_user_logged_in()) {
        return '<div class="hs-auth-container"><p>You are already logged in. <a href="' . esc_url(home_url('/')) . '">Go to homepage</a></p></div>';
    }

    $atts = shortcode_atts(array(
        'redirect' => home_url('/'),
    ), $atts);

    $apple_enabled = get_option('hs_apple_enabled', false);
    $google_enabled = get_option('hs_google_enabled', false);

    ob_start();
    ?>
    <div class="hs-auth-container">
        <h2>Sign In</h2>

        <!-- Messages -->
        <div class="hs-auth-loading"></div>
        <div class="hs-auth-error"></div>
        <div class="hs-auth-success"></div>

        <!-- Hidden mode field -->
        <input type="hidden" class="hs-auth-mode" value="signin">

        <!-- Social Authentication Buttons -->
        <?php if ($apple_enabled || $google_enabled): ?>
        <div class="hs-social-buttons">
            <?php if ($apple_enabled): ?>
            <button class="hs-social-btn hs-apple-signin-btn" data-mode="signin">
                Sign in with Apple
            </button>
            <?php endif; ?>

            <?php if ($google_enabled): ?>
            <!-- Google's official button will be rendered here by JavaScript -->
            <div id="hs-google-signin-button"></div>
            <!-- Fallback button -->
            <button class="hs-social-btn hs-google-signin-btn" data-mode="signin" style="display: none;">
                Sign in with Google
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Divider -->
        <?php if ($apple_enabled || $google_enabled): ?>
        <div class="hs-auth-divider">or</div>
        <?php endif; ?>

        <!-- Standard WordPress Login -->
        <div class="hs-auth-toggle">
            <a href="<?php echo esc_url(wp_login_url($atts['redirect'])); ?>">Sign in with email and password</a>
        </div>

        <!-- Toggle to Registration -->
        <div class="hs-auth-toggle" style="margin-top: 30px;">
            Don't have an account? <a href="<?php echo esc_url(home_url('/register')); ?>">Create one</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('hs_signin_form', 'hs_signin_form_shortcode');

/**
 * Enqueue scripts and styles for authentication forms
 */
function hs_enqueue_auth_scripts() {
    // Only enqueue on pages with the shortcodes or registration pages
    if (is_user_logged_in()) {
        return;
    }

    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'hs_registration_form') || has_shortcode($post->post_content, 'hs_signin_form'))) {
        // Enqueue CSS
        wp_enqueue_style('hs-social-auth', plugins_url('css/social-auth.css', dirname(__FILE__, 2)), array(), '0.37');

        // Enqueue JavaScript
        wp_enqueue_script('hs-social-auth', plugins_url('js/social-auth.js', dirname(__FILE__, 2)), array('jquery'), '0.37', true);

        // Localize script with config
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
}
add_action('wp_enqueue_scripts', 'hs_enqueue_auth_scripts');
