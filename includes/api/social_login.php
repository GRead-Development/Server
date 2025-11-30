<?php
/**
 * Social Authentication REST API Endpoints
 *
 * Handles Google OAuth authentication for sign-in and registration
 * Note: Apple authentication is handled in apple_auth.php
 *
 * @package HotSoup
 * @since 0.37
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register social authentication REST routes
 */
function gread_register_social_auth_routes() {
    // Sign-in endpoint
    register_rest_route('gread/v1', '/auth/signin', array(
        'methods' => 'POST',
        'callback' => 'gread_social_auth_signin',
        'permission_callback' => '__return_true'
    ));

    // Registration endpoint
    register_rest_route('gread/v1', '/auth/register', array(
        'methods' => 'POST',
        'callback' => 'gread_social_auth_register',
        'permission_callback' => '__return_true'
    ));

    // Google OAuth callback (for redirect flow if needed)
    register_rest_route('gread/v1', '/auth/google/callback', array(
        'methods' => 'GET',
        'callback' => 'gread_google_auth_callback',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'gread_register_social_auth_routes');

/**
 * Sign in with Google
 */
function gread_social_auth_signin($request) {
    $body = json_decode($request->get_body(), true);

    if (!$body || !isset($body['provider'])) {
        return new WP_Error('invalid_request', 'Invalid request data', array('status' => 400));
    }

    $provider = sanitize_text_field($body['provider']);

    // Verify provider (only Google is supported in this endpoint)
    if ($provider !== 'google') {
        return new WP_Error('invalid_provider', 'Invalid authentication provider. Use /auth/apple/login for Apple authentication.', array('status' => 400));
    }

    // Check if provider is enabled
    $provider_enabled = get_option('hs_' . $provider . '_enabled', false);
    if (!$provider_enabled) {
        return new WP_Error('provider_disabled', ucfirst($provider) . ' authentication is not enabled', array('status' => 403));
    }

    // Handle Google authentication
    return gread_handle_google_signin($body);
}

/**
 * Register with Google
 */
function gread_social_auth_register($request) {
    $body = json_decode($request->get_body(), true);

    if (!$body || !isset($body['provider']) || !isset($body['username'])) {
        return new WP_Error('invalid_request', 'Invalid request data', array('status' => 400));
    }

    $provider = sanitize_text_field($body['provider']);
    $username = sanitize_user($body['username']);

    // Verify provider (only Google is supported in this endpoint)
    if ($provider !== 'google') {
        return new WP_Error('invalid_provider', 'Invalid authentication provider. Use /auth/apple/register for Apple authentication.', array('status' => 400));
    }

    // Check if provider is enabled
    $provider_enabled = get_option('hs_' . $provider . '_enabled', false);
    if (!$provider_enabled) {
        return new WP_Error('provider_disabled', ucfirst($provider) . ' authentication is not enabled', array('status' => 403));
    }

    // Validate username
    if (strlen($username) < 3 || strlen($username) > 20) {
        return new WP_Error('invalid_username', 'Username must be between 3 and 20 characters', array('status' => 400));
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return new WP_Error('invalid_username', 'Username can only contain letters, numbers, and underscores', array('status' => 400));
    }

    // Check if username already exists
    if (username_exists($username)) {
        return new WP_Error('username_exists', 'Username already exists', array('status' => 409));
    }

    // Handle Google registration
    return gread_handle_google_register($body, $username);
}

/**
 * Handle Google Sign-In
 */
function gread_handle_google_signin($data) {
    if (!isset($data['credential'])) {
        return new WP_Error('invalid_data', 'Missing Google authentication data', array('status' => 400));
    }

    // Verify Google credential
    $user_info = gread_verify_google_token($data['credential']);

    if (!$user_info || !isset($user_info['email'])) {
        return new WP_Error('verification_failed', 'Failed to verify Google credential', array('status' => 401));
    }

    $email = sanitize_email($user_info['email']);

    // Find user by email
    $user = get_user_by('email', $email);

    if (!$user) {
        return new WP_Error('user_not_found', 'No account found with this Google account. Please register first.', array('status' => 404));
    }

    // Log in the user
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Sign in successful',
        'user_id' => $user->ID,
        'username' => $user->user_login,
        'redirect_url' => get_option('hs_login_redirect_url', home_url('/'))
    ));
}

/**
 * Handle Google Registration
 */
function gread_handle_google_register($data, $username) {
    if (!isset($data['credential'])) {
        return new WP_Error('invalid_data', 'Missing Google authentication data', array('status' => 400));
    }

    // Verify Google credential
    $user_info = gread_verify_google_token($data['credential']);

    if (!$user_info || !isset($user_info['email'])) {
        return new WP_Error('verification_failed', 'Failed to verify Google credential', array('status' => 401));
    }

    $email = sanitize_email($user_info['email']);

    // Check if email already exists
    if (email_exists($email)) {
        return new WP_Error('email_exists', 'An account with this email already exists', array('status' => 409));
    }

    // Get name from user data
    $first_name = sanitize_text_field($user_info['given_name'] ?? '');
    $last_name = sanitize_text_field($user_info['family_name'] ?? '');

    // Create user
    $user_id = wp_create_user($username, wp_generate_password(32, true, true), $email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // Update user meta
    update_user_meta($user_id, 'first_name', $first_name);
    update_user_meta($user_id, 'last_name', $last_name);
    update_user_meta($user_id, 'auth_provider', 'google');
    update_user_meta($user_id, 'google_id', $user_info['sub']);

    // Log in the user
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Registration successful',
        'user_id' => $user_id,
        'username' => $username,
        'redirect_url' => get_option('hs_login_redirect_url', home_url('/'))
    ));
}

/**
 * Verify Google credential token
 */
function gread_verify_google_token($credential) {
    $client_id = get_option('hs_google_client_id', '');

    // Verify with Google's token verification endpoint
    $response = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!$data || isset($data['error'])) {
        return false;
    }

    // Verify audience (client ID)
    if (!isset($data['aud']) || $data['aud'] !== $client_id) {
        return false;
    }

    // Verify issuer
    if (!isset($data['iss']) || !in_array($data['iss'], array('accounts.google.com', 'https://accounts.google.com'))) {
        return false;
    }

    return $data;
}

/**
 * Google OAuth callback (for redirect flow if needed)
 */
function gread_google_auth_callback($request) {
    // This endpoint is for the redirect flow if needed in the future
    return rest_ensure_response(array(
        'success' => false,
        'message' => 'This endpoint is not yet implemented. Please use the popup flow.'
    ));
}
