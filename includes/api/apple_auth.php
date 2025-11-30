<?php
/**
 * Apple Authentication REST API Endpoints
 *
 * Handles Apple Sign-In authentication for iOS app
 *
 * @package HotSoup
 * @since 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Apple authentication REST routes
 */
function gread_register_apple_auth_routes() {
    // Apple login endpoint for returning users
    register_rest_route('gread/v1', '/auth/apple/login', array(
        'methods' => 'POST',
        'callback' => 'gread_apple_login',
        'permission_callback' => '__return_true'
    ));

    // Apple registration endpoint for new users
    register_rest_route('gread/v1', '/auth/apple/register', array(
        'methods' => 'POST',
        'callback' => 'gread_apple_register',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'gread_register_apple_auth_routes');

/**
 * Handle Apple login for returning users
 *
 * Expected request body:
 * {
 *   "appleUserID": "001234.abc123def456.7890"
 * }
 */
function gread_apple_login($request) {
    $body = json_decode($request->get_body(), true);

    if (!$body || !isset($body['appleUserID'])) {
        return new WP_Error('invalid_request', 'Missing Apple User ID', array('status' => 400));
    }

    $apple_user_id = sanitize_text_field($body['appleUserID']);

    // Find user by Apple ID stored in user meta
    $users = get_users(array(
        'meta_key' => 'apple_user_id',
        'meta_value' => $apple_user_id,
        'number' => 1
    ));

    if (empty($users)) {
        return new WP_Error('user_not_found', 'No account found with this Apple ID. Please register first.', array('status' => 404));
    }

    $user = $users[0];

    // Log in the user
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);

    // Generate auth token for the app
    $token = gread_generate_auth_token($user->ID);

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Login successful',
        'user_id' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'token' => $token,
        'display_name' => $user->display_name
    ));
}

/**
 * Handle Apple registration for new users
 *
 * Expected request body:
 * {
 *   "appleUserID": "001234.abc123def456.7890",
 *   "email": "user@privaterelay.appleid.com",
 *   "fullName": "John Doe",
 *   "username": "johndoe"
 * }
 */
function gread_apple_register($request) {
    $body = json_decode($request->get_body(), true);

    // Validate required fields
    if (!$body || !isset($body['appleUserID']) || !isset($body['email']) || !isset($body['username'])) {
        return new WP_Error('invalid_request', 'Missing required fields', array('status' => 400));
    }

    $apple_user_id = sanitize_text_field($body['appleUserID']);
    $email = sanitize_email($body['email']);
    $username = sanitize_user($body['username']);
    $full_name = isset($body['fullName']) ? sanitize_text_field($body['fullName']) : '';

    // Validate email
    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'Invalid email address', array('status' => 400));
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

    // Check if email already exists
    if (email_exists($email)) {
        return new WP_Error('email_exists', 'An account with this email already exists', array('status' => 409));
    }

    // Check if Apple ID already registered
    $existing_users = get_users(array(
        'meta_key' => 'apple_user_id',
        'meta_value' => $apple_user_id,
        'number' => 1
    ));

    if (!empty($existing_users)) {
        return new WP_Error('apple_id_exists', 'This Apple ID is already registered', array('status' => 409));
    }

    // Parse full name
    $name_parts = gread_parse_full_name($full_name);

    // Create user with a strong random password
    $user_id = wp_create_user($username, wp_generate_password(32, true, true), $email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // Update user meta
    update_user_meta($user_id, 'first_name', $name_parts['first_name']);
    update_user_meta($user_id, 'last_name', $name_parts['last_name']);
    update_user_meta($user_id, 'apple_user_id', $apple_user_id);
    update_user_meta($user_id, 'auth_provider', 'apple');

    // Set display name
    $display_name = !empty($full_name) ? $full_name : $username;
    wp_update_user(array(
        'ID' => $user_id,
        'display_name' => $display_name
    ));

    // Log in the user
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    // Generate auth token for the app
    $token = gread_generate_auth_token($user_id);

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Registration successful',
        'user_id' => $user_id,
        'username' => $username,
        'email' => $email,
        'token' => $token,
        'display_name' => $display_name
    ));
}

/**
 * Parse full name into first and last name
 */
function gread_parse_full_name($full_name) {
    $parts = array(
        'first_name' => '',
        'last_name' => ''
    );

    if (empty($full_name)) {
        return $parts;
    }

    $name_array = explode(' ', trim($full_name), 2);
    $parts['first_name'] = $name_array[0];
    $parts['last_name'] = isset($name_array[1]) ? $name_array[1] : '';

    return $parts;
}

/**
 * Generate authentication token for the app
 */
function gread_generate_auth_token($user_id) {
    // Check if a token generation function already exists in the codebase
    if (function_exists('wp_generate_application_password')) {
        // Use WordPress application passwords (WP 5.6+)
        $token = wp_generate_password(32, false);
        update_user_meta($user_id, 'gread_auth_token', wp_hash_password($token));
        return $token;
    }

    // Fallback: Generate a simple token
    $token = bin2hex(random_bytes(32));
    update_user_meta($user_id, 'gread_auth_token', wp_hash_password($token));
    update_user_meta($user_id, 'gread_token_created', time());

    return $token;
}
