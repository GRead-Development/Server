<?php
/**
 * Social Authentication Settings
 *
 * Admin settings page for configuring Apple and Google OAuth
 *
 * @package HotSoup
 * @since 0.37
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Social Auth settings page to admin menu
 */
function hs_add_social_auth_settings_page() {
    add_submenu_page(
        'options-general.php',
        'Social Authentication Settings',
        'Social Auth',
        'manage_options',
        'hs-social-auth',
        'hs_render_social_auth_settings_page'
    );
}
add_action('admin_menu', 'hs_add_social_auth_settings_page');

/**
 * Register settings
 */
function hs_register_social_auth_settings() {
    // Apple Settings
    register_setting('hs_social_auth', 'hs_apple_client_id', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ));

    register_setting('hs_social_auth', 'hs_apple_team_id', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ));

    register_setting('hs_social_auth', 'hs_apple_key_id', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ));

    register_setting('hs_social_auth', 'hs_apple_private_key', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_textarea_field',
        'default' => '',
    ));

    register_setting('hs_social_auth', 'hs_apple_enabled', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => false,
    ));

    // Google Settings
    register_setting('hs_social_auth', 'hs_google_client_id', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ));

    register_setting('hs_social_auth', 'hs_google_client_secret', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ));

    register_setting('hs_social_auth', 'hs_google_enabled', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => false,
    ));

    // General Settings
    register_setting('hs_social_auth', 'hs_require_tos_acceptance', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ));

    register_setting('hs_social_auth', 'hs_tos_url', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => 'https://gread.fun/tos',
    ));
}
add_action('admin_init', 'hs_register_social_auth_settings');

/**
 * Render the settings page
 */
function hs_render_social_auth_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle settings update
    if (isset($_GET['settings-updated'])) {
        add_settings_error('hs_social_auth_messages', 'hs_social_auth_message', 'Settings saved successfully', 'updated');
    }

    settings_errors('hs_social_auth_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <h2 class="nav-tab-wrapper">
            <a href="#apple-settings" class="nav-tab nav-tab-active">Apple Sign-In</a>
            <a href="#google-settings" class="nav-tab">Google Sign-In</a>
            <a href="#general-settings" class="nav-tab">General Settings</a>
            <a href="#login-integration" class="nav-tab">Login Integration</a>
            <a href="#setup-guide" class="nav-tab">Setup Guide</a>
        </h2>

        <form method="post" action="options.php">
            <?php settings_fields('hs_social_auth'); ?>

            <!-- Apple Settings -->
            <div id="apple-settings" class="tab-content">
                <h2>Apple Sign-In Configuration</h2>
                <p>Configure Sign in with Apple for your site. You'll need to create an App ID and Service ID in your Apple Developer account.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="hs_apple_enabled">Enable Apple Sign-In</label>
                        </th>
                        <td>
                            <input type="checkbox" id="hs_apple_enabled" name="hs_apple_enabled" value="1" <?php checked(get_option('hs_apple_enabled'), 1); ?>>
                            <p class="description">Enable or disable Sign in with Apple</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_apple_client_id">Client ID (Service ID)</label>
                        </th>
                        <td>
                            <input type="text" id="hs_apple_client_id" name="hs_apple_client_id" value="<?php echo esc_attr(get_option('hs_apple_client_id')); ?>" class="regular-text">
                            <p class="description">Your Apple Service ID (e.g., com.yourcompany.serviceid)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_apple_team_id">Team ID</label>
                        </th>
                        <td>
                            <input type="text" id="hs_apple_team_id" name="hs_apple_team_id" value="<?php echo esc_attr(get_option('hs_apple_team_id')); ?>" class="regular-text">
                            <p class="description">Your Apple Developer Team ID (10 characters)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_apple_key_id">Key ID</label>
                        </th>
                        <td>
                            <input type="text" id="hs_apple_key_id" name="hs_apple_key_id" value="<?php echo esc_attr(get_option('hs_apple_key_id')); ?>" class="regular-text">
                            <p class="description">Your Apple Key ID (10 characters)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_apple_private_key">Private Key</label>
                        </th>
                        <td>
                            <textarea id="hs_apple_private_key" name="hs_apple_private_key" rows="8" class="large-text code"><?php echo esc_textarea(get_option('hs_apple_private_key')); ?></textarea>
                            <p class="description">Your Apple private key (.p8 file contents). Keep this secure!</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Google Settings -->
            <div id="google-settings" class="tab-content" style="display: none;">
                <h2>Google Sign-In Configuration</h2>
                <p>Configure Google Sign-In for your site. You'll need to create OAuth 2.0 credentials in the Google Cloud Console.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="hs_google_enabled">Enable Google Sign-In</label>
                        </th>
                        <td>
                            <input type="checkbox" id="hs_google_enabled" name="hs_google_enabled" value="1" <?php checked(get_option('hs_google_enabled'), 1); ?>>
                            <p class="description">Enable or disable Google Sign-In</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_google_client_id">Client ID</label>
                        </th>
                        <td>
                            <input type="text" id="hs_google_client_id" name="hs_google_client_id" value="<?php echo esc_attr(get_option('hs_google_client_id')); ?>" class="large-text">
                            <p class="description">Your Google OAuth 2.0 Client ID</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_google_client_secret">Client Secret</label>
                        </th>
                        <td>
                            <input type="text" id="hs_google_client_secret" name="hs_google_client_secret" value="<?php echo esc_attr(get_option('hs_google_client_secret')); ?>" class="regular-text">
                            <p class="description">Your Google OAuth 2.0 Client Secret. Keep this secure!</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Authorized Redirect URI</th>
                        <td>
                            <code><?php echo esc_url(home_url('/wp-json/gread/v1/auth/google/callback')); ?></code>
                            <p class="description">Add this URL to your Google OAuth authorized redirect URIs</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- General Settings -->
            <div id="general-settings" class="tab-content" style="display: none;">
                <h2>General Registration Settings</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="hs_require_tos_acceptance">Require ToS Acceptance</label>
                        </th>
                        <td>
                            <input type="checkbox" id="hs_require_tos_acceptance" name="hs_require_tos_acceptance" value="1" <?php checked(get_option('hs_require_tos_acceptance', true), 1); ?>>
                            <p class="description">Require users to accept Terms of Service during registration</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_tos_url">Terms of Service URL</label>
                        </th>
                        <td>
                            <input type="url" id="hs_tos_url" name="hs_tos_url" value="<?php echo esc_attr(get_option('hs_tos_url', 'https://gread.fun/tos')); ?>" class="regular-text">
                            <p class="description">URL to your Terms of Service page</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Username Requirements</th>
                        <td>
                            <ul>
                                <li>3-20 characters</li>
                                <li>Only letters, numbers, and underscores</li>
                                <li>Cannot be changed after registration</li>
                            </ul>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Login Integration -->
            <div id="login-integration" class="tab-content" style="display: none;">
                <h2>WordPress Login Integration</h2>
                <p>Control how HotSoup integrates with WordPress login and registration pages.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="hs_custom_login_enabled">Replace WordPress Login</label>
                        </th>
                        <td>
                            <input type="checkbox" id="hs_custom_login_enabled" name="hs_custom_login_enabled" value="1" <?php checked(get_option('hs_custom_login_enabled', true), 1); ?>>
                            <p class="description">Redirect wp-login.php to your custom login page with social auth</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_custom_login_page">Custom Login Page URL</label>
                        </th>
                        <td>
                            <input type="url" id="hs_custom_login_page" name="hs_custom_login_page" value="<?php echo esc_attr(get_option('hs_custom_login_page', home_url('/login'))); ?>" class="regular-text">
                            <p class="description">URL of your page with [hs_signin_form] shortcode</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_custom_registration_enabled">Replace WordPress Registration</label>
                        </th>
                        <td>
                            <input type="checkbox" id="hs_custom_registration_enabled" name="hs_custom_registration_enabled" value="1" <?php checked(get_option('hs_custom_registration_enabled', true), 1); ?>>
                            <p class="description">Redirect wp-login.php?action=register to your custom registration page</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_custom_registration_page">Custom Registration Page URL</label>
                        </th>
                        <td>
                            <input type="url" id="hs_custom_registration_page" name="hs_custom_registration_page" value="<?php echo esc_attr(get_option('hs_custom_registration_page', home_url('/register'))); ?>" class="regular-text">
                            <p class="description">URL of your page with [hs_registration_form] shortcode</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_add_social_to_wp_login">Add Social Auth to wp-login.php</label>
                        </th>
                        <td>
                            <input type="checkbox" id="hs_add_social_to_wp_login" name="hs_add_social_to_wp_login" value="1" <?php checked(get_option('hs_add_social_to_wp_login'), 1); ?>>
                            <p class="description">Add social auth buttons to default WordPress login page (alternative to full redirect)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_login_redirect_url">After Login Redirect</label>
                        </th>
                        <td>
                            <input type="url" id="hs_login_redirect_url" name="hs_login_redirect_url" value="<?php echo esc_attr(get_option('hs_login_redirect_url', home_url('/'))); ?>" class="regular-text">
                            <p class="description">Where to redirect users after successful login (subscribers only, admins go to dashboard)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_logout_redirect_url">After Logout Redirect</label>
                        </th>
                        <td>
                            <input type="url" id="hs_logout_redirect_url" name="hs_logout_redirect_url" value="<?php echo esc_attr(get_option('hs_logout_redirect_url', home_url('/'))); ?>" class="regular-text">
                            <p class="description">Where to redirect users after logout</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hs_login_logo_url">Custom Login Logo URL</label>
                        </th>
                        <td>
                            <input type="url" id="hs_login_logo_url" name="hs_login_logo_url" value="<?php echo esc_attr(get_option('hs_login_logo_url')); ?>" class="regular-text">
                            <p class="description">URL to your logo image (optional, replaces WordPress logo on login page)</p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin-top: 30px;">Quick Setup Instructions</h3>
                <ol>
                    <li>Create a page called "Login" and add the shortcode: <code>[hs_signin_form]</code></li>
                    <li>Create a page called "Register" and add the shortcode: <code>[hs_registration_form]</code></li>
                    <li>Note the URLs of these pages (e.g., <code><?php echo home_url('/login'); ?></code> and <code><?php echo home_url('/register'); ?></code>)</li>
                    <li>Enter these URLs above and enable the redirects</li>
                    <li>Save settings</li>
                    <li>Try visiting <code><?php echo admin_url('wp-login.php'); ?></code> - you should be redirected to your custom page!</li>
                </ol>
            </div>

            <!-- Setup Guide -->
            <div id="setup-guide" class="tab-content" style="display: none;">
                <h2>Setup Guide</h2>

                <div class="card">
                    <h3>Setting up Apple Sign-In</h3>
                    <ol>
                        <li>Go to <a href="https://developer.apple.com" target="_blank">Apple Developer Portal</a></li>
                        <li>Create an App ID for your application</li>
                        <li>Enable "Sign in with Apple" capability</li>
                        <li>Create a Services ID</li>
                        <li>Configure the Services ID with your domain and return URLs:
                            <ul>
                                <li>Domain: <code><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></code></li>
                                <li>Return URL: <code><?php echo esc_url(home_url()); ?></code></li>
                            </ul>
                        </li>
                        <li>Create a Private Key and download the .p8 file</li>
                        <li>Enter your Team ID, Key ID, Client ID (Services ID), and Private Key above</li>
                    </ol>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h3>Setting up Google Sign-In</h3>
                    <ol>
                        <li>Go to <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
                        <li>Create a new project or select an existing one</li>
                        <li>Enable the Google+ API</li>
                        <li>Go to "Credentials" and create OAuth 2.0 Client ID</li>
                        <li>Choose "Web application" as the application type</li>
                        <li>Add authorized JavaScript origins:
                            <ul>
                                <li><code><?php echo esc_url(home_url()); ?></code></li>
                            </ul>
                        </li>
                        <li>Add authorized redirect URIs:
                            <ul>
                                <li><code><?php echo esc_url(home_url('/wp-json/gread/v1/auth/google/callback')); ?></code></li>
                            </ul>
                        </li>
                        <li>Copy your Client ID and Client Secret and enter them above</li>
                    </ol>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h3>Testing Your Configuration</h3>
                    <p>After configuring the settings above:</p>
                    <ol>
                        <li>Save your settings</li>
                        <li>Visit your registration page</li>
                        <li>You should see "Sign in with Apple" and "Sign in with Google" buttons</li>
                        <li>Try registering with each method</li>
                        <li>Verify that users are created with the correct metadata</li>
                    </ol>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h3>Security Notes</h3>
                    <ul>
                        <li><strong>Keep your private keys and client secrets secure!</strong> Never share them or commit them to version control.</li>
                        <li>Use HTTPS for your site when Sign in with Apple or Google is enabled</li>
                        <li>Regularly review and rotate your API credentials</li>
                        <li>Monitor for suspicious account creation activity</li>
                    </ul>
                </div>
            </div>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>

    <style>
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }
        .tab-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-top: none;
        }
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h3 {
            margin-top: 0;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $($(this).attr('href')).show();
            });
        });
    </script>
    <?php
}
