<?php
/**
 * Social Authentication API Handler
 *
 * Handles registration and sign-in with Apple and Google
 *
 * @package HotSoup
 * @since 0.37
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register social authentication REST API endpoints
 */
function hs_register_social_auth_endpoints() {
    // Apple Sign-In endpoints
    register_rest_route('gread/v1', '/auth/apple/signin', array(
        'methods' => 'POST',
        'callback' => 'hs_apple_signin',
        'permission_callback' => '__return_true',
        'args' => array(
            'id_token' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Apple ID token from Sign In with Apple',
            ),
            'user_data' => array(
                'required' => false,
                'type' => 'object',
                'description' => 'User data from Apple (only provided on first sign-in)',
            ),
        ),
    ));

    register_rest_route('gread/v1', '/auth/apple/register', array(
        'methods' => 'POST',
        'callback' => 'hs_apple_register',
        'permission_callback' => '__return_true',
        'args' => array(
            'id_token' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Apple ID token from Sign In with Apple',
            ),
            'user_data' => array(
                'required' => false,
                'type' => 'object',
                'description' => 'User data from Apple',
            ),
            'username' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Desired username (cannot be changed later)',
            ),
            'accept_tos' => array(
                'required' => true,
                'type' => 'boolean',
                'description' => 'Must accept terms of service',
            ),
        ),
    ));

    // Google Sign-In endpoints
    register_rest_route('gread/v1', '/auth/google/signin', array(
        'methods' => 'POST',
        'callback' => 'hs_google_signin',
        'permission_callback' => '__return_true',
        'args' => array(
            'id_token' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Google ID token',
            ),
        ),
    ));

    register_rest_route('gread/v1', '/auth/google/register', array(
        'methods' => 'POST',
        'callback' => 'hs_google_register',
        'permission_callback' => '__return_true',
        'args' => array(
            'id_token' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Google ID token',
            ),
            'username' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Desired username (cannot be changed later)',
            ),
            'accept_tos' => array(
                'required' => true,
                'type' => 'boolean',
                'description' => 'Must accept terms of service',
            ),
        ),
    ));

    // Email registration endpoint
    register_rest_route('gread/v1', '/auth/email/register', array(
        'methods' => 'POST',
        'callback' => 'hs_email_register',
        'permission_callback' => '__return_true',
        'args' => array(
            'email' => array(
                'required' => true,
                'type' => 'string',
                'format' => 'email',
                'description' => 'Email address',
            ),
            'password' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Password',
            ),
            'username' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Desired username (cannot be changed later)',
            ),
            'accept_tos' => array(
                'required' => true,
                'type' => 'boolean',
                'description' => 'Must accept terms of service',
            ),
        ),
    ));

    // Check username availability endpoint
    register_rest_route('gread/v1', '/auth/check-username', array(
        'methods' => 'GET',
        'callback' => 'hs_check_username_availability',
        'permission_callback' => '__return_true',
        'args' => array(
            'username' => array(
                'required' => true,
                'type' => 'string',
            ),
        ),
    ));
}
add_action('rest_api_init', 'hs_register_social_auth_endpoints');

/**
 * Apple Sign-In Handler
 */
function hs_apple_signin($request) {
    $id_token = $request->get_param('id_token');

    // Verify Apple ID token
    $apple_user_data = hs_verify_apple_token($id_token);

    if (is_wp_error($apple_user_data)) {
        return new WP_Error('invalid_token', 'Invalid Apple ID token', array('status' => 401));
    }

    $apple_user_id = $apple_user_data['sub'];

    // Check if user exists with this Apple ID
    $users = get_users(array(
        'meta_key' => 'apple_user_id',
        'meta_value' => $apple_user_id,
        'number' => 1,
    ));

    if (!empty($users)) {
        $user = $users[0];

        // Log the user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        return array(
            'success' => true,
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'message' => 'Successfully signed in with Apple',
        );
    } else {
        // User doesn't exist - needs to register
        return new WP_Error('user_not_found', 'No account found. Please register first.', array('status' => 404));
    }
}

/**
 * Apple Registration Handler
 */
function hs_apple_register($request) {
    $id_token = $request->get_param('id_token');
    $username = sanitize_user($request->get_param('username'));
    $accept_tos = $request->get_param('accept_tos');
    $user_data = $request->get_param('user_data');

    // Validate ToS acceptance
    if (!$accept_tos) {
        return new WP_Error('tos_required', 'You must accept the Terms of Service to register', array('status' => 400));
    }

    // Validate username
    $username_validation = hs_validate_username($username);
    if (is_wp_error($username_validation)) {
        return $username_validation;
    }

    // Verify Apple ID token
    $apple_user_data = hs_verify_apple_token($id_token);

    if (is_wp_error($apple_user_data)) {
        return new WP_Error('invalid_token', 'Invalid Apple ID token', array('status' => 401));
    }

    $apple_user_id = $apple_user_data['sub'];
    $email = isset($apple_user_data['email']) ? $apple_user_data['email'] : '';

    // Check if Apple ID already registered
    $existing_users = get_users(array(
        'meta_key' => 'apple_user_id',
        'meta_value' => $apple_user_id,
        'number' => 1,
    ));

    if (!empty($existing_users)) {
        return new WP_Error('already_registered', 'This Apple account is already registered', array('status' => 409));
    }

    // Get name from user_data if available
    $first_name = '';
    $last_name = '';
    if ($user_data && isset($user_data['name'])) {
        $first_name = isset($user_data['name']['firstName']) ? sanitize_text_field($user_data['name']['firstName']) : '';
        $last_name = isset($user_data['name']['lastName']) ? sanitize_text_field($user_data['name']['lastName']) : '';
    }

    // Create WordPress user
    $user_id = hs_create_social_user($username, $email, $apple_user_id, 'apple', $first_name, $last_name);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // Log the user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

    do_action('hs_user_registered_apple', $user_id);

    $user = get_userdata($user_id);

    return array(
        'success' => true,
        'user_id' => $user_id,
        'username' => $user->user_login,
        'display_name' => $user->display_name,
        'email' => $user->user_email,
        'message' => 'Successfully registered with Apple',
    );
}

/**
 * Google Sign-In Handler
 */
function hs_google_signin($request) {
    $id_token = $request->get_param('id_token');

    // Verify Google ID token
    $google_user_data = hs_verify_google_token($id_token);

    if (is_wp_error($google_user_data)) {
        return new WP_Error('invalid_token', 'Invalid Google ID token', array('status' => 401));
    }

    $google_user_id = $google_user_data['sub'];

    // Check if user exists with this Google ID
    $users = get_users(array(
        'meta_key' => 'google_user_id',
        'meta_value' => $google_user_id,
        'number' => 1,
    ));

    if (!empty($users)) {
        $user = $users[0];

        // Log the user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        return array(
            'success' => true,
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'message' => 'Successfully signed in with Google',
        );
    } else {
        // User doesn't exist - needs to register
        return new WP_Error('user_not_found', 'No account found. Please register first.', array('status' => 404));
    }
}

/**
 * Google Registration Handler
 */
function hs_google_register($request) {
    $id_token = $request->get_param('id_token');
    $username = sanitize_user($request->get_param('username'));
    $accept_tos = $request->get_param('accept_tos');

    // Validate ToS acceptance
    if (!$accept_tos) {
        return new WP_Error('tos_required', 'You must accept the Terms of Service to register', array('status' => 400));
    }

    // Validate username
    $username_validation = hs_validate_username($username);
    if (is_wp_error($username_validation)) {
        return $username_validation;
    }

    // Verify Google ID token
    $google_user_data = hs_verify_google_token($id_token);

    if (is_wp_error($google_user_data)) {
        return new WP_Error('invalid_token', 'Invalid Google ID token', array('status' => 401));
    }

    $google_user_id = $google_user_data['sub'];
    $email = isset($google_user_data['email']) ? $google_user_data['email'] : '';
    $first_name = isset($google_user_data['given_name']) ? $google_user_data['given_name'] : '';
    $last_name = isset($google_user_data['family_name']) ? $google_user_data['family_name'] : '';

    // Check if Google ID already registered
    $existing_users = get_users(array(
        'meta_key' => 'google_user_id',
        'meta_value' => $google_user_id,
        'number' => 1,
    ));

    if (!empty($existing_users)) {
        return new WP_Error('already_registered', 'This Google account is already registered', array('status' => 409));
    }

    // Create WordPress user
    $user_id = hs_create_social_user($username, $email, $google_user_id, 'google', $first_name, $last_name);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // Log the user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

    do_action('hs_user_registered_google', $user_id);

    $user = get_userdata($user_id);

    return array(
        'success' => true,
        'user_id' => $user_id,
        'username' => $user->user_login,
        'display_name' => $user->display_name,
        'email' => $user->user_email,
        'message' => 'Successfully registered with Google',
    );
}

/**
 * Email Registration Handler
 */
function hs_email_register($request) {
    $email = sanitize_email($request->get_param('email'));
    $password = $request->get_param('password');
    $username = sanitize_user($request->get_param('username'));
    $accept_tos = $request->get_param('accept_tos');

    // Validate ToS acceptance
    if (!$accept_tos) {
        return new WP_Error('tos_required', 'You must accept the Terms of Service to register', array('status' => 400));
    }

    // Validate email
    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'Invalid email address', array('status' => 400));
    }

    // Check if email already exists
    if (email_exists($email)) {
        return new WP_Error('email_exists', 'An account with this email already exists', array('status' => 409));
    }

    // Validate username
    $username_validation = hs_validate_username($username);
    if (is_wp_error($username_validation)) {
        return $username_validation;
    }

    // Validate password
    if (strlen($password) < 8) {
        return new WP_Error('weak_password', 'Password must be at least 8 characters long', array('status' => 400));
    }

    // Create WordPress user
    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // Set user role
    $user = new WP_User($user_id);
    $user->set_role('subscriber');

    // Update user meta
    update_user_meta($user_id, 'tos_accepted', true);
    update_user_meta($user_id, 'tos_accepted_date', current_time('mysql'));
    update_user_meta($user_id, 'registration_method', 'email');

    // Initialize user stats
    update_user_meta($user_id, 'user_points', 0);
    update_user_meta($user_id, 'hs_completed_books_count', 0);
    update_user_meta($user_id, 'hs_total_pages_read', 0);
    update_user_meta($user_id, 'hs_books_added_count', 0);

    // Log the user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

    do_action('hs_user_registered_email', $user_id);

    $user = get_userdata($user_id);

    return array(
        'success' => true,
        'user_id' => $user_id,
        'username' => $user->user_login,
        'display_name' => $user->display_name,
        'email' => $user->user_email,
        'message' => 'Successfully registered with email',
    );
}

/**
 * Check username availability
 */
function hs_check_username_availability($request) {
    $username = sanitize_user($request->get_param('username'));

    $validation = hs_validate_username($username);

    if (is_wp_error($validation)) {
        return array(
            'available' => false,
            'message' => $validation->get_error_message(),
        );
    }

    return array(
        'available' => true,
        'message' => 'Username is available',
    );
}

/**
 * Validate username
 */
function hs_validate_username($username) {
    // Check if username is empty
    if (empty($username)) {
        return new WP_Error('empty_username', 'Username is required', array('status' => 400));
    }

    // Check username length (3-20 characters)
    if (strlen($username) < 3) {
        return new WP_Error('username_too_short', 'Username must be at least 3 characters', array('status' => 400));
    }

    if (strlen($username) > 20) {
        return new WP_Error('username_too_long', 'Username must be 20 characters or less', array('status' => 400));
    }

    // Check if username contains only valid characters
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return new WP_Error('invalid_username', 'Username can only contain letters, numbers, and underscores', array('status' => 400));
    }

    // Check if username already exists
    if (username_exists($username)) {
        return new WP_Error('username_exists', 'This username is already taken', array('status' => 409));
    }

    // Check against WordPress reserved usernames
    $invalid_usernames = array('admin', 'administrator', 'root', 'system', 'hotsoup', 'gread');
    if (in_array(strtolower($username), $invalid_usernames)) {
        return new WP_Error('reserved_username', 'This username is reserved and cannot be used', array('status' => 400));
    }

    return true;
}

/**
 * Create user from social authentication
 */
function hs_create_social_user($username, $email, $social_id, $provider, $first_name = '', $last_name = '') {
    // Generate a random password for social auth users
    $password = wp_generate_password(20, true, true);

    // If no email provided, generate a placeholder
    if (empty($email)) {
        $email = $username . '@' . $provider . '.hotsoup.local';
    }

    // Create the user
    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // Set user role
    $user = new WP_User($user_id);
    $user->set_role('subscriber');

    // Update user meta
    update_user_meta($user_id, $provider . '_user_id', $social_id);
    update_user_meta($user_id, 'tos_accepted', true);
    update_user_meta($user_id, 'tos_accepted_date', current_time('mysql'));
    update_user_meta($user_id, 'registration_method', $provider);

    // Set display name
    if (!empty($first_name) && !empty($last_name)) {
        $display_name = $first_name . ' ' . $last_name;
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name,
        ));
    } else {
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $username,
        ));
    }

    // Initialize user stats
    update_user_meta($user_id, 'user_points', 0);
    update_user_meta($user_id, 'hs_completed_books_count', 0);
    update_user_meta($user_id, 'hs_total_pages_read', 0);
    update_user_meta($user_id, 'hs_books_added_count', 0);

    return $user_id;
}

/**
 * Prevent username changes
 * Users should not be able to change their username after registration
 */
function hs_prevent_username_change($errors, $update, $user) {
    if ($update) {
        $old_userdata = get_userdata($user->ID);
        if ($old_userdata && $old_userdata->user_login !== $user->user_login) {
            $errors->add('username_change_not_allowed', 'Username cannot be changed after registration');
        }
    }
}
add_action('user_profile_update_errors', 'hs_prevent_username_change', 10, 3);

/**
 * Include social provider utilities
 */
require_once plugin_dir_path(__FILE__) . 'social-providers.php';
