<?php
/**
 * GRead Profile API
 *
 * Custom profile system API to replace BuddyPress profile API
 * Provides comprehensive user profile management through REST API
 *
 * @package GRead
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register profile-related REST API routes
 */
function gread_register_profile_routes() {
    // Get any user's profile
    register_rest_route('gread/v1', '/user/(?P<id>\d+)/profile', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_user_profile',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
        ),
    ));

    // Get current user's profile
    register_rest_route('gread/v1', '/me/profile', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_my_profile',
        'permission_callback' => 'gread_check_user_permission',
    ));

    // Update current user's profile
    register_rest_route('gread/v1', '/me/profile', array(
        'methods' => array('PUT', 'PATCH'),
        'callback' => 'gread_api_update_my_profile',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'display_name' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'bio' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'website' => array(
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ),
            'location' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));

    // Get XProfile fields
    register_rest_route('gread/v1', '/me/xprofile/fields', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_xprofile_fields',
        'permission_callback' => 'gread_check_user_permission',
    ));

    // Update XProfile fields
    register_rest_route('gread/v1', '/me/xprofile/fields', array(
        'methods' => array('PUT', 'PATCH'),
        'callback' => 'gread_api_update_xprofile_fields',
        'permission_callback' => 'gread_check_user_permission',
    ));

    // Get XProfile field groups
    register_rest_route('gread/v1', '/xprofile/groups', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_xprofile_groups',
        'permission_callback' => '__return_true',
    ));

    // Upload avatar
    register_rest_route('gread/v1', '/me/avatar', array(
        'methods' => 'POST',
        'callback' => 'gread_api_upload_avatar',
        'permission_callback' => 'gread_check_user_permission',
    ));

    // Delete avatar
    register_rest_route('gread/v1', '/me/avatar', array(
        'methods' => 'DELETE',
        'callback' => 'gread_api_delete_avatar',
        'permission_callback' => 'gread_check_user_permission',
    ));

    // Get user's followers
    register_rest_route('gread/v1', '/user/(?P<id>\d+)/followers', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_user_followers',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
            'page' => array(
                'default' => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'default' => 20,
                'sanitize_callback' => 'absint',
            ),
        ),
    ));

    // Get user's following
    register_rest_route('gread/v1', '/user/(?P<id>\d+)/following', array(
        'methods' => 'GET',
        'callback' => 'gread_api_get_user_following',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
            'page' => array(
                'default' => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'default' => 20,
                'sanitize_callback' => 'absint',
            ),
        ),
    ));

    // Follow a user
    register_rest_route('gread/v1', '/user/(?P<id>\d+)/follow', array(
        'methods' => 'POST',
        'callback' => 'gread_api_follow_user',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
        ),
    ));

    // Unfollow a user
    register_rest_route('gread/v1', '/user/(?P<id>\d+)/unfollow', array(
        'methods' => 'DELETE',
        'callback' => 'gread_api_unfollow_user',
        'permission_callback' => 'gread_check_user_permission',
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
}

/**
 * Get user profile by ID
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_get_user_profile($request) {
    $user_id = $request->get_param('id');
    $current_user_id = get_current_user_id();

    // Check if user exists
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error(
            'user_not_found',
            'User not found',
            array('status' => 404)
        );
    }

    $profile_data = gread_get_profile_data($user_id, $current_user_id);

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $profile_data,
    ), 200);
}

/**
 * Get current user's profile
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_get_my_profile($request) {
    $user_id = get_current_user_id();
    $profile_data = gread_get_profile_data($user_id, $user_id, true);

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $profile_data,
    ), 200);
}

/**
 * Update current user's profile
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_update_my_profile($request) {
    $user_id = get_current_user_id();
    $updated_fields = array();

    // Update display name
    if ($request->has_param('display_name')) {
        $display_name = $request->get_param('display_name');
        if (!empty($display_name)) {
            $result = wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $display_name,
            ));

            if (is_wp_error($result)) {
                return new WP_Error(
                    'update_failed',
                    'Failed to update display name: ' . $result->get_error_message(),
                    array('status' => 400)
                );
            }
            $updated_fields[] = 'display_name';
        }
    }

    // Update bio
    if ($request->has_param('bio')) {
        $bio = $request->get_param('bio');
        update_user_meta($user_id, 'description', $bio);
        $updated_fields[] = 'bio';
    }

    // Update website
    if ($request->has_param('website')) {
        $website = $request->get_param('website');
        if (empty($website) || filter_var($website, FILTER_VALIDATE_URL)) {
            wp_update_user(array(
                'ID' => $user_id,
                'user_url' => $website,
            ));
            $updated_fields[] = 'website';
        } else {
            return new WP_Error(
                'invalid_url',
                'Invalid website URL',
                array('status' => 400)
            );
        }
    }

    // Update location (custom field)
    if ($request->has_param('location')) {
        $location = $request->get_param('location');
        update_user_meta($user_id, 'gread_location', $location);
        $updated_fields[] = 'location';
    }

    // Get updated profile
    $profile_data = gread_get_profile_data($user_id, $user_id, true);

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Profile updated successfully',
        'updated_fields' => $updated_fields,
        'data' => $profile_data,
    ), 200);
}

/**
 * Get XProfile fields for current user
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_get_xprofile_fields($request) {
    if (!function_exists('bp_is_active') || !bp_is_active('xprofile')) {
        return new WP_Error(
            'xprofile_not_active',
            'BuddyPress XProfile is not active',
            array('status' => 501)
        );
    }

    $user_id = get_current_user_id();
    $xprofile_data = gread_get_xprofile_data($user_id);

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $xprofile_data,
    ), 200);
}

/**
 * Update XProfile fields for current user
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_update_xprofile_fields($request) {
    if (!function_exists('bp_is_active') || !bp_is_active('xprofile')) {
        return new WP_Error(
            'xprofile_not_active',
            'BuddyPress XProfile is not active',
            array('status' => 501)
        );
    }

    $user_id = get_current_user_id();
    $fields = $request->get_json_params();

    if (empty($fields) || !is_array($fields)) {
        return new WP_Error(
            'invalid_data',
            'Invalid field data provided',
            array('status' => 400)
        );
    }

    $updated_fields = array();
    $errors = array();

    foreach ($fields as $field_id => $value) {
        $field_id = absint($field_id);

        if ($field_id <= 0) {
            continue;
        }

        // Verify field exists
        $field = xprofile_get_field($field_id);
        if (!$field) {
            $errors[] = "Field ID {$field_id} does not exist";
            continue;
        }

        // Update field value
        $result = xprofile_set_field_data($field_id, $user_id, $value);

        if ($result) {
            $updated_fields[] = array(
                'field_id' => $field_id,
                'name' => $field->name,
                'value' => $value,
            );
        } else {
            $errors[] = "Failed to update field: {$field->name}";
        }
    }

    // Get updated XProfile data
    $xprofile_data = gread_get_xprofile_data($user_id);

    return new WP_REST_Response(array(
        'success' => empty($errors),
        'message' => empty($errors) ? 'XProfile fields updated successfully' : 'Some fields failed to update',
        'updated_fields' => $updated_fields,
        'errors' => $errors,
        'data' => $xprofile_data,
    ), empty($errors) ? 200 : 207);
}

/**
 * Get XProfile field groups
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_get_xprofile_groups($request) {
    if (!function_exists('bp_is_active') || !bp_is_active('xprofile')) {
        return new WP_Error(
            'xprofile_not_active',
            'BuddyPress XProfile is not active',
            array('status' => 501)
        );
    }

    $groups = bp_xprofile_get_groups(array(
        'fetch_fields' => true,
    ));

    $formatted_groups = array();

    if ($groups) {
        foreach ($groups as $group) {
            $fields = array();

            if (!empty($group->fields)) {
                foreach ($group->fields as $field) {
                    $field_data = array(
                        'id' => $field->id,
                        'name' => $field->name,
                        'description' => $field->description,
                        'type' => $field->type,
                        'is_required' => $field->is_required,
                        'can_delete' => $field->can_delete,
                        'order' => $field->field_order,
                    );

                    // Add options for certain field types
                    if (in_array($field->type, array('selectbox', 'multiselectbox', 'radio', 'checkbox'))) {
                        $field_data['options'] = $field->get_children();
                    }

                    $fields[] = $field_data;
                }
            }

            $formatted_groups[] = array(
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'can_delete' => $group->can_delete,
                'fields' => $fields,
            );
        }
    }

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $formatted_groups,
    ), 200);
}

/**
 * Upload avatar for current user
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_upload_avatar($request) {
    $user_id = get_current_user_id();

    // Check if file was uploaded
    $files = $request->get_file_params();
    if (empty($files['avatar'])) {
        return new WP_Error(
            'no_file',
            'No avatar file provided',
            array('status' => 400)
        );
    }

    $file = $files['avatar'];

    // Validate file type
    $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
    if (!in_array($file['type'], $allowed_types)) {
        return new WP_Error(
            'invalid_file_type',
            'Invalid file type. Allowed types: JPEG, PNG, GIF',
            array('status' => 400)
        );
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return new WP_Error(
            'file_too_large',
            'File size exceeds 5MB limit',
            array('status' => 400)
        );
    }

    // If BuddyPress is active, use its avatar functions
    if (function_exists('bp_is_active') && bp_is_active('xprofile')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Handle the upload using WordPress
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            return new WP_Error(
                'upload_failed',
                $upload['error'],
                array('status' => 500)
            );
        }

        // Use BuddyPress avatar upload
        if (function_exists('bp_core_avatar_handle_upload')) {
            $result = bp_core_avatar_handle_upload($file, 'bp_core_avatar_handle_crop');

            if (is_wp_error($result)) {
                return $result;
            }
        }

        $avatar_url = bp_core_fetch_avatar(array(
            'item_id' => $user_id,
            'type' => 'full',
            'html' => false,
        ));
    } else {
        // Fallback to WordPress media library
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $attachment_id = media_handle_upload('avatar', 0);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Store attachment ID in user meta
        update_user_meta($user_id, 'gread_avatar_attachment_id', $attachment_id);
        $avatar_url = wp_get_attachment_url($attachment_id);
    }

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Avatar uploaded successfully',
        'data' => array(
            'avatar_url' => $avatar_url,
        ),
    ), 200);
}

/**
 * Delete avatar for current user
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_delete_avatar($request) {
    $user_id = get_current_user_id();

    // If BuddyPress is active, delete BP avatar
    if (function_exists('bp_core_delete_existing_avatar')) {
        $result = bp_core_delete_existing_avatar(array(
            'item_id' => $user_id,
            'object' => 'user',
        ));
    }

    // Delete custom avatar if exists
    $attachment_id = get_user_meta($user_id, 'gread_avatar_attachment_id', true);
    if ($attachment_id) {
        wp_delete_attachment($attachment_id, true);
        delete_user_meta($user_id, 'gread_avatar_attachment_id');
    }

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Avatar deleted successfully',
    ), 200);
}

/**
 * Get user's followers
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_get_user_followers($request) {
    $user_id = $request->get_param('id');
    $page = $request->get_param('page');
    $per_page = $request->get_param('per_page');

    // Check if user exists
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error(
            'user_not_found',
            'User not found',
            array('status' => 404)
        );
    }

    $followers = gread_get_user_followers($user_id, $page, $per_page);

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $followers,
    ), 200);
}

/**
 * Get user's following
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_get_user_following($request) {
    $user_id = $request->get_param('id');
    $page = $request->get_param('page');
    $per_page = $request->get_param('per_page');

    // Check if user exists
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error(
            'user_not_found',
            'User not found',
            array('status' => 404)
        );
    }

    $following = gread_get_user_following($user_id, $page, $per_page);

    return new WP_REST_Response(array(
        'success' => true,
        'data' => $following,
    ), 200);
}

/**
 * Follow a user
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_follow_user($request) {
    $user_id = $request->get_param('id');
    $current_user_id = get_current_user_id();

    // Can't follow yourself
    if ($user_id == $current_user_id) {
        return new WP_Error(
            'cannot_follow_self',
            'You cannot follow yourself',
            array('status' => 400)
        );
    }

    // Check if user exists
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error(
            'user_not_found',
            'User not found',
            array('status' => 404)
        );
    }

    // Use BuddyPress friends component if available
    if (function_exists('bp_is_active') && bp_is_active('friends')) {
        if (function_exists('friends_add_friend')) {
            $result = friends_add_friend($current_user_id, $user_id);

            if (!$result) {
                return new WP_Error(
                    'follow_failed',
                    'Failed to follow user',
                    array('status' => 500)
                );
            }
        }
    } else {
        // Custom follow implementation
        $following = get_user_meta($current_user_id, 'gread_following', true);
        if (!is_array($following)) {
            $following = array();
        }

        if (!in_array($user_id, $following)) {
            $following[] = $user_id;
            update_user_meta($current_user_id, 'gread_following', $following);

            // Update follower's followers list
            $followers = get_user_meta($user_id, 'gread_followers', true);
            if (!is_array($followers)) {
                $followers = array();
            }
            if (!in_array($current_user_id, $followers)) {
                $followers[] = $current_user_id;
                update_user_meta($user_id, 'gread_followers', $followers);
            }
        }
    }

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Successfully followed user',
        'data' => array(
            'user_id' => $user_id,
            'is_following' => true,
        ),
    ), 200);
}

/**
 * Unfollow a user
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function gread_api_unfollow_user($request) {
    $user_id = $request->get_param('id');
    $current_user_id = get_current_user_id();

    // Check if user exists
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error(
            'user_not_found',
            'User not found',
            array('status' => 404)
        );
    }

    // Use BuddyPress friends component if available
    if (function_exists('bp_is_active') && bp_is_active('friends')) {
        if (function_exists('friends_remove_friend')) {
            $result = friends_remove_friend($current_user_id, $user_id);

            if (!$result) {
                return new WP_Error(
                    'unfollow_failed',
                    'Failed to unfollow user',
                    array('status' => 500)
                );
            }
        }
    } else {
        // Custom unfollow implementation
        $following = get_user_meta($current_user_id, 'gread_following', true);
        if (is_array($following)) {
            $following = array_diff($following, array($user_id));
            update_user_meta($current_user_id, 'gread_following', $following);

            // Update follower's followers list
            $followers = get_user_meta($user_id, 'gread_followers', true);
            if (is_array($followers)) {
                $followers = array_diff($followers, array($current_user_id));
                update_user_meta($user_id, 'gread_followers', $followers);
            }
        }
    }

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Successfully unfollowed user',
        'data' => array(
            'user_id' => $user_id,
            'is_following' => false,
        ),
    ), 200);
}

/**
 * Get comprehensive profile data for a user
 *
 * @param int $user_id User ID to get profile for
 * @param int $viewer_id ID of user viewing the profile
 * @param bool $include_private Whether to include private data
 * @return array Profile data
 */
function gread_get_profile_data($user_id, $viewer_id = 0, $include_private = false) {
    $user = get_userdata($user_id);

    if (!$user) {
        return array();
    }

    // Get avatar URL
    if (function_exists('bp_core_fetch_avatar')) {
        $avatar_url = bp_core_fetch_avatar(array(
            'item_id' => $user_id,
            'type' => 'full',
            'html' => false,
        ));
    } else {
        $custom_avatar_id = get_user_meta($user_id, 'gread_avatar_attachment_id', true);
        if ($custom_avatar_id) {
            $avatar_url = wp_get_attachment_url($custom_avatar_id);
        } else {
            $avatar_url = get_avatar_url($user_id, array('size' => 200));
        }
    }

    // Get profile URL
    if (function_exists('bp_core_get_user_domain')) {
        $profile_url = bp_core_get_user_domain($user_id);
    } else {
        $profile_url = get_author_posts_url($user_id);
    }

    // Basic profile data
    $profile = array(
        'id' => $user_id,
        'username' => $user->user_login,
        'display_name' => $user->display_name,
        'bio' => get_user_meta($user_id, 'description', true),
        'avatar_url' => $avatar_url,
        'profile_url' => $profile_url,
        'website' => $user->user_url,
        'location' => get_user_meta($user_id, 'gread_location', true),
        'registered_date' => $user->user_registered,
    );

    // Stats
    $profile['stats'] = array(
        'points' => (int) get_user_meta($user_id, 'user_points', true),
        'books_completed' => (int) get_user_meta($user_id, 'hs_completed_books_count', true),
        'pages_read' => (int) get_user_meta($user_id, 'hs_total_pages_read', true),
        'books_added' => (int) get_user_meta($user_id, 'hs_books_added_count', true),
        'approved_reports' => (int) get_user_meta($user_id, 'hs_approved_reports_count', true),
    );

    // Social stats
    $profile['social'] = array(
        'followers_count' => gread_get_followers_count($user_id),
        'following_count' => gread_get_following_count($user_id),
    );

    // Relationship status (if viewer is logged in)
    if ($viewer_id > 0 && $viewer_id != $user_id) {
        $profile['relationship'] = array(
            'is_following' => gread_is_following($viewer_id, $user_id),
            'is_follower' => gread_is_following($user_id, $viewer_id),
            'is_blocked' => gread_is_user_blocked($viewer_id, $user_id),
            'is_muted' => gread_is_user_muted($viewer_id, $user_id),
        );
    }

    // Include private data only for own profile
    if ($include_private && $user_id == $viewer_id) {
        $profile['email'] = $user->user_email;
    }

    return $profile;
}

/**
 * Get XProfile data for a user
 *
 * @param int $user_id User ID
 * @return array XProfile data
 */
function gread_get_xprofile_data($user_id) {
    if (!function_exists('bp_is_active') || !bp_is_active('xprofile')) {
        return array();
    }

    $groups = bp_xprofile_get_groups(array(
        'user_id' => $user_id,
        'fetch_fields' => true,
    ));

    $xprofile_data = array();

    if ($groups) {
        foreach ($groups as $group) {
            if (!empty($group->fields)) {
                foreach ($group->fields as $field) {
                    $field_value = xprofile_get_field_data($field->id, $user_id);

                    $xprofile_data[$field->id] = array(
                        'id' => $field->id,
                        'name' => $field->name,
                        'value' => $field_value,
                        'type' => $field->type,
                        'group' => $group->name,
                        'group_id' => $group->id,
                    );
                }
            }
        }
    }

    return $xprofile_data;
}

/**
 * Get user's followers
 *
 * @param int $user_id User ID
 * @param int $page Page number
 * @param int $per_page Items per page
 * @return array Followers data
 */
function gread_get_user_followers($user_id, $page = 1, $per_page = 20) {
    // Use BuddyPress friends if available
    if (function_exists('bp_is_active') && bp_is_active('friends')) {
        if (function_exists('friends_get_friend_user_ids')) {
            $friend_ids = friends_get_friend_user_ids($user_id);
            $total = count($friend_ids);

            // Paginate
            $offset = ($page - 1) * $per_page;
            $friend_ids = array_slice($friend_ids, $offset, $per_page);

            $followers = array();
            foreach ($friend_ids as $friend_id) {
                $followers[] = gread_get_user_summary($friend_id);
            }

            return array(
                'items' => $followers,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total / $per_page),
            );
        }
    }

    // Custom followers
    $follower_ids = get_user_meta($user_id, 'gread_followers', true);
    if (!is_array($follower_ids)) {
        $follower_ids = array();
    }

    $total = count($follower_ids);

    // Paginate
    $offset = ($page - 1) * $per_page;
    $follower_ids = array_slice($follower_ids, $offset, $per_page);

    $followers = array();
    foreach ($follower_ids as $follower_id) {
        $followers[] = gread_get_user_summary($follower_id);
    }

    return array(
        'items' => $followers,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page),
    );
}

/**
 * Get user's following
 *
 * @param int $user_id User ID
 * @param int $page Page number
 * @param int $per_page Items per page
 * @return array Following data
 */
function gread_get_user_following($user_id, $page = 1, $per_page = 20) {
    // Use BuddyPress friends if available
    if (function_exists('bp_is_active') && bp_is_active('friends')) {
        if (function_exists('friends_get_friend_user_ids')) {
            $friend_ids = friends_get_friend_user_ids($user_id);
            $total = count($friend_ids);

            // Paginate
            $offset = ($page - 1) * $per_page;
            $friend_ids = array_slice($friend_ids, $offset, $per_page);

            $following = array();
            foreach ($friend_ids as $friend_id) {
                $following[] = gread_get_user_summary($friend_id);
            }

            return array(
                'items' => $following,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total / $per_page),
            );
        }
    }

    // Custom following
    $following_ids = get_user_meta($user_id, 'gread_following', true);
    if (!is_array($following_ids)) {
        $following_ids = array();
    }

    $total = count($following_ids);

    // Paginate
    $offset = ($page - 1) * $per_page;
    $following_ids = array_slice($following_ids, $offset, $per_page);

    $following = array();
    foreach ($following_ids as $friend_id) {
        $following[] = gread_get_user_summary($friend_id);
    }

    return array(
        'items' => $following,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page),
    );
}

/**
 * Get user summary data
 *
 * @param int $user_id User ID
 * @return array User summary
 */
function gread_get_user_summary($user_id) {
    $user = get_userdata($user_id);

    if (!$user) {
        return null;
    }

    // Get avatar
    if (function_exists('bp_core_fetch_avatar')) {
        $avatar_url = bp_core_fetch_avatar(array(
            'item_id' => $user_id,
            'type' => 'thumb',
            'html' => false,
        ));
    } else {
        $avatar_url = get_avatar_url($user_id, array('size' => 96));
    }

    return array(
        'id' => $user_id,
        'username' => $user->user_login,
        'display_name' => $user->display_name,
        'avatar_url' => $avatar_url,
    );
}

/**
 * Get followers count for a user
 *
 * @param int $user_id User ID
 * @return int Followers count
 */
function gread_get_followers_count($user_id) {
    if (function_exists('bp_is_active') && bp_is_active('friends')) {
        if (function_exists('friends_get_total_friend_count')) {
            return (int) friends_get_total_friend_count($user_id);
        }
    }

    $followers = get_user_meta($user_id, 'gread_followers', true);
    return is_array($followers) ? count($followers) : 0;
}

/**
 * Get following count for a user
 *
 * @param int $user_id User ID
 * @return int Following count
 */
function gread_get_following_count($user_id) {
    if (function_exists('bp_is_active') && bp_is_active('friends')) {
        if (function_exists('friends_get_total_friend_count')) {
            return (int) friends_get_total_friend_count($user_id);
        }
    }

    $following = get_user_meta($user_id, 'gread_following', true);
    return is_array($following) ? count($following) : 0;
}

/**
 * Check if a user is following another user
 *
 * @param int $user_id User ID
 * @param int $target_user_id Target user ID
 * @return bool True if following
 */
function gread_is_following($user_id, $target_user_id) {
    if (function_exists('bp_is_active') && bp_is_active('friends')) {
        if (function_exists('friends_check_friendship')) {
            return friends_check_friendship($user_id, $target_user_id);
        }
    }

    $following = get_user_meta($user_id, 'gread_following', true);
    return is_array($following) && in_array($target_user_id, $following);
}

/**
 * Check if a user is blocked
 *
 * @param int $user_id User ID doing the blocking
 * @param int $blocked_user_id User ID being checked
 * @return bool True if blocked
 */
function gread_is_user_blocked($user_id, $blocked_user_id) {
    $blocked_users = get_user_meta($user_id, 'blocked_users', true);
    return is_array($blocked_users) && in_array($blocked_user_id, $blocked_users);
}

/**
 * Check if a user is muted
 *
 * @param int $user_id User ID doing the muting
 * @param int $muted_user_id User ID being checked
 * @return bool True if muted
 */
function gread_is_user_muted($user_id, $muted_user_id) {
    $muted_users = get_user_meta($user_id, 'muted_users', true);
    return is_array($muted_users) && in_array($muted_user_id, $muted_users);
}

// Register the profile routes
add_action('rest_api_init', 'gread_register_profile_routes');
