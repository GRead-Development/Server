<?php
/**
 * API Endpoints Panel
 *
 * Admin panel for viewing, managing, and exporting all API endpoints
 *
 * @package HotSoup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the API Endpoints admin menu
 */
function hotsoup_api_endpoints_add_admin_page() {
    add_menu_page(
        'API Endpoints',                           // Page title
        'API Endpoints',                           // Menu title
        'manage_options',                          // Capability required
        'hotsoup-api-endpoints',                   // Menu slug
        'hotsoup_api_endpoints_page_html',         // Callback function
        'dashicons-rest-api',                      // Icon
        85                                         // Position
    );
}
add_action('admin_menu', 'hotsoup_api_endpoints_add_admin_page');

/**
 * Render the API Endpoints admin page
 */
function hotsoup_api_endpoints_page_html() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Enqueue scripts and styles
    wp_enqueue_script(
        'hotsoup-api-endpoints-js',
        plugins_url('../../js/admin/api-endpoints.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    wp_enqueue_style(
        'hotsoup-api-endpoints-css',
        plugins_url('../../css/admin/api-endpoints.css', __FILE__),
        array(),
        '1.0.0'
    );

    // Localize script with data
    wp_localize_script('hotsoup-api-endpoints-js', 'hotSoupApiEndpoints', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hotsoup_api_endpoints_nonce'),
        'restUrl' => rest_url('gread/v1/admin/endpoints'),
        'restNonce' => wp_create_nonce('wp_rest')
    ));

    // Get all endpoints data
    $endpoints_data = hotsoup_get_all_endpoints_data();

    ?>
    <div class="wrap hotsoup-api-endpoints-wrap">
        <h1>
            <span class="dashicons dashicons-rest-api"></span>
            API Endpoints Reference
        </h1>

        <div class="hotsoup-api-endpoints-header">
            <div class="hotsoup-api-stats">
                <div class="stat-box">
                    <span class="stat-number"><?php echo count($endpoints_data); ?></span>
                    <span class="stat-label">Total Endpoints</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo hotsoup_count_endpoints_by_method($endpoints_data, 'GET'); ?></span>
                    <span class="stat-label">GET</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo hotsoup_count_endpoints_by_method($endpoints_data, 'POST'); ?></span>
                    <span class="stat-label">POST</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo hotsoup_count_endpoints_by_method($endpoints_data, 'PUT'); ?></span>
                    <span class="stat-label">PUT</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo hotsoup_count_endpoints_by_method($endpoints_data, 'DELETE'); ?></span>
                    <span class="stat-label">DELETE</span>
                </div>
            </div>

            <div class="hotsoup-api-actions">
                <button id="refresh-endpoints" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    Refresh
                </button>
                <button id="export-json" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    Export JSON
                </button>
                <button id="export-csv" class="button button-secondary">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    Export CSV
                </button>
                <button id="export-markdown" class="button button-primary">
                    <span class="dashicons dashicons-media-document"></span>
                    Export Markdown
                </button>
            </div>
        </div>

        <div class="hotsoup-api-filters">
            <input type="text" id="search-endpoints" placeholder="Search endpoints..." class="regular-text">
            <select id="filter-method">
                <option value="">All Methods</option>
                <option value="GET">GET</option>
                <option value="POST">POST</option>
                <option value="PUT">PUT</option>
                <option value="DELETE">DELETE</option>
                <option value="PATCH">PATCH</option>
            </select>
            <select id="filter-namespace">
                <option value="">All Namespaces</option>
                <option value="gread/v1">gread/v1</option>
                <option value="wp/v2">wp/v2</option>
            </select>
        </div>

        <div id="loading-indicator" class="hotsoup-loading" style="display: none;">
            <span class="spinner is-active"></span>
            <span>Loading endpoints...</span>
        </div>

        <div id="endpoints-container" class="hotsoup-endpoints-container">
            <?php hotsoup_render_endpoints_table($endpoints_data); ?>
        </div>
    </div>
    <?php
}

/**
 * Get all API endpoints data
 *
 * @return array Array of endpoint data
 */
function hotsoup_get_all_endpoints_data() {
    $rest_server = rest_get_server();
    $all_routes = $rest_server->get_routes();
    $endpoints = array();

    foreach ($all_routes as $route => $route_data) {
        // Filter to only show gread/v1 namespace by default, but include all for completeness
        if (strpos($route, '/gread/v1') !== 0 && strpos($route, '/wp/v2') !== 0) {
            continue;
        }

        foreach ($route_data as $endpoint_data) {
            $methods = isset($endpoint_data['methods']) ? array_keys($endpoint_data['methods']) : array('GET');

            foreach ($methods as $method) {
                $endpoint = array(
                    'route' => $route,
                    'method' => $method,
                    'namespace' => hotsoup_extract_namespace($route),
                    'callback' => hotsoup_get_callback_info($endpoint_data),
                    'permission_callback' => hotsoup_get_permission_callback_info($endpoint_data),
                    'args' => isset($endpoint_data['args']) ? $endpoint_data['args'] : array(),
                    'description' => hotsoup_generate_endpoint_description($route, $method),
                    'example_request' => hotsoup_generate_example_request($route, $method, $endpoint_data),
                    'example_response' => hotsoup_generate_example_response($route, $method)
                );

                $endpoints[] = $endpoint;
            }
        }
    }

    // Sort by route
    usort($endpoints, function($a, $b) {
        return strcmp($a['route'], $b['route']);
    });

    return $endpoints;
}

/**
 * Extract namespace from route
 *
 * @param string $route The route
 * @return string The namespace
 */
function hotsoup_extract_namespace($route) {
    preg_match('#^/([^/]+/[^/]+)#', $route, $matches);
    return isset($matches[1]) ? $matches[1] : '';
}

/**
 * Get callback information
 *
 * @param array $endpoint_data Endpoint data
 * @return string Callback info
 */
function hotsoup_get_callback_info($endpoint_data) {
    if (isset($endpoint_data['callback'])) {
        if (is_array($endpoint_data['callback'])) {
            if (is_object($endpoint_data['callback'][0])) {
                return get_class($endpoint_data['callback'][0]) . '::' . $endpoint_data['callback'][1];
            }
            return implode('::', $endpoint_data['callback']);
        }
        return $endpoint_data['callback'];
    }
    return 'N/A';
}

/**
 * Get permission callback information
 *
 * @param array $endpoint_data Endpoint data
 * @return string Permission callback info
 */
function hotsoup_get_permission_callback_info($endpoint_data) {
    if (isset($endpoint_data['permission_callback'])) {
        if (is_string($endpoint_data['permission_callback'])) {
            return $endpoint_data['permission_callback'];
        } else if ($endpoint_data['permission_callback'] === '__return_true') {
            return 'Public (no auth required)';
        } else if (is_array($endpoint_data['permission_callback'])) {
            return 'Custom callback';
        }
        return 'Custom callback';
    }
    return 'Not specified';
}

/**
 * Generate endpoint description based on route and method
 *
 * @param string $route The route
 * @param string $method The HTTP method
 * @return string Description
 */
function hotsoup_generate_endpoint_description($route, $method) {
    // Generate descriptions based on common patterns
    $descriptions = array(
        '/gread/v1/library' => array(
            'GET' => 'Get the current user\'s library of books',
        ),
        '/gread/v1/library/add' => array(
            'POST' => 'Add a book to the user\'s library',
        ),
        '/gread/v1/library/progress' => array(
            'POST' => 'Update reading progress for a book',
        ),
        '/gread/v1/library/remove' => array(
            'DELETE' => 'Remove a book from the user\'s library',
        ),
        '/gread/v1/books/search' => array(
            'GET' => 'Search for books in the database',
        ),
        '/gread/v1/authors' => array(
            'GET' => 'Get a paginated list of all authors',
        ),
    );

    // Check for exact match
    if (isset($descriptions[$route][$method])) {
        return $descriptions[$route][$method];
    }

    // Pattern matching for dynamic routes
    if (preg_match('#/gread/v1/authors/(\d+)$#', $route)) {
        if ($method === 'GET') return 'Get details for a specific author';
    }
    if (preg_match('#/gread/v1/authors/(\d+)/books$#', $route)) {
        if ($method === 'GET') return 'Get all books by a specific author';
    }
    if (preg_match('#/gread/v1/authors/(\d+)/page$#', $route)) {
        if ($method === 'GET') return 'Get complete author page data including books and bio';
    }
    if (preg_match('#/gread/v1/user/(\d+)/stats$#', $route)) {
        if ($method === 'GET') return 'Get statistics for a specific user';
    }

    // Default description
    return ucfirst(strtolower($method)) . ' endpoint for ' . $route;
}

/**
 * Generate example request
 *
 * @param string $route The route
 * @param string $method The HTTP method
 * @param array $endpoint_data Endpoint data
 * @return string Example request
 */
function hotsoup_generate_example_request($route, $method, $endpoint_data) {
    $base_url = get_rest_url(null, $route);
    $args = isset($endpoint_data['args']) ? $endpoint_data['args'] : array();

    $example = $method . ' ' . $base_url;

    // Add example parameters for specific endpoints
    if ($method === 'POST' && !empty($args)) {
        $example_params = array();
        foreach ($args as $arg_name => $arg_data) {
            if (isset($arg_data['required']) && $arg_data['required']) {
                $example_params[$arg_name] = hotsoup_get_example_value($arg_name, $arg_data);
            }
        }
        if (!empty($example_params)) {
            $example .= "\n\nBody: " . json_encode($example_params, JSON_PRETTY_PRINT);
        }
    }

    return $example;
}

/**
 * Get example value for parameter
 *
 * @param string $param_name Parameter name
 * @param array $param_data Parameter data
 * @return mixed Example value
 */
function hotsoup_get_example_value($param_name, $param_data) {
    if (strpos($param_name, 'id') !== false) {
        return 123;
    }
    if (isset($param_data['type'])) {
        switch ($param_data['type']) {
            case 'integer':
                return 1;
            case 'boolean':
                return true;
            case 'array':
                return array();
            default:
                return 'example_value';
        }
    }
    return 'example_value';
}

/**
 * Generate example response
 *
 * @param string $route The route
 * @param string $method The HTTP method
 * @return string Example response
 */
function hotsoup_generate_example_response($route, $method) {
    // For now, return a generic success response
    // This could be enhanced to provide route-specific examples
    if ($method === 'DELETE') {
        return json_encode(array('success' => true, 'message' => 'Resource deleted'), JSON_PRETTY_PRINT);
    }

    return json_encode(array('success' => true, 'data' => array()), JSON_PRETTY_PRINT);
}

/**
 * Render endpoints table
 *
 * @param array $endpoints Array of endpoint data
 */
function hotsoup_render_endpoints_table($endpoints) {
    if (empty($endpoints)) {
        echo '<p>No endpoints found.</p>';
        return;
    }

    ?>
    <table class="wp-list-table widefat fixed striped hotsoup-endpoints-table">
        <thead>
            <tr>
                <th class="column-method">Method</th>
                <th class="column-route">Route</th>
                <th class="column-description">Description</th>
                <th class="column-auth">Auth</th>
                <th class="column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($endpoints as $index => $endpoint): ?>
                <tr class="endpoint-row" data-method="<?php echo esc_attr($endpoint['method']); ?>" data-namespace="<?php echo esc_attr($endpoint['namespace']); ?>">
                    <td class="column-method">
                        <span class="method-badge method-<?php echo strtolower($endpoint['method']); ?>">
                            <?php echo esc_html($endpoint['method']); ?>
                        </span>
                    </td>
                    <td class="column-route">
                        <code><?php echo esc_html($endpoint['route']); ?></code>
                    </td>
                    <td class="column-description">
                        <?php echo esc_html($endpoint['description']); ?>
                    </td>
                    <td class="column-auth">
                        <?php
                        $auth_required = $endpoint['permission_callback'] !== 'Public (no auth required)';
                        if ($auth_required) {
                            echo '<span class="auth-required">Required</span>';
                        } else {
                            echo '<span class="auth-public">Public</span>';
                        }
                        ?>
                    </td>
                    <td class="column-actions">
                        <button class="button button-small view-details" data-index="<?php echo $index; ?>">
                            Details
                        </button>
                    </td>
                </tr>
                <tr class="endpoint-details" id="details-<?php echo $index; ?>" style="display: none;">
                    <td colspan="5">
                        <div class="endpoint-details-content">
                            <div class="details-section">
                                <h3>Endpoint Information</h3>
                                <table class="details-table">
                                    <tr>
                                        <th>Namespace:</th>
                                        <td><code><?php echo esc_html($endpoint['namespace']); ?></code></td>
                                    </tr>
                                    <tr>
                                        <th>Callback:</th>
                                        <td><code><?php echo esc_html($endpoint['callback']); ?></code></td>
                                    </tr>
                                    <tr>
                                        <th>Permission Callback:</th>
                                        <td><code><?php echo esc_html($endpoint['permission_callback']); ?></code></td>
                                    </tr>
                                </table>
                            </div>

                            <?php if (!empty($endpoint['args'])): ?>
                            <div class="details-section">
                                <h3>Parameters</h3>
                                <table class="details-table params-table">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Type</th>
                                            <th>Required</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($endpoint['args'] as $arg_name => $arg_data): ?>
                                            <tr>
                                                <td><code><?php echo esc_html($arg_name); ?></code></td>
                                                <td><?php echo isset($arg_data['type']) ? esc_html($arg_data['type']) : 'mixed'; ?></td>
                                                <td><?php echo isset($arg_data['required']) && $arg_data['required'] ? 'Yes' : 'No'; ?></td>
                                                <td><?php echo isset($arg_data['description']) ? esc_html($arg_data['description']) : 'N/A'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                            <div class="details-section">
                                <h3>Example Request</h3>
                                <pre><code><?php echo esc_html($endpoint['example_request']); ?></code></pre>
                            </div>

                            <div class="details-section">
                                <h3>Example Response</h3>
                                <pre><code><?php echo esc_html($endpoint['example_response']); ?></code></pre>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Count endpoints by HTTP method
 *
 * @param array $endpoints Array of endpoint data
 * @param string $method HTTP method to count
 * @return int Count of endpoints
 */
function hotsoup_count_endpoints_by_method($endpoints, $method) {
    $count = 0;
    foreach ($endpoints as $endpoint) {
        if ($endpoint['method'] === $method) {
            $count++;
        }
    }
    return $count;
}

/**
 * AJAX handler to get fresh endpoint data
 */
function hotsoup_ajax_get_endpoints() {
    check_ajax_referer('hotsoup_api_endpoints_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }

    $endpoints_data = hotsoup_get_all_endpoints_data();

    ob_start();
    hotsoup_render_endpoints_table($endpoints_data);
    $html = ob_get_clean();

    wp_send_json_success(array(
        'html' => $html,
        'stats' => array(
            'total' => count($endpoints_data),
            'get' => hotsoup_count_endpoints_by_method($endpoints_data, 'GET'),
            'post' => hotsoup_count_endpoints_by_method($endpoints_data, 'POST'),
            'put' => hotsoup_count_endpoints_by_method($endpoints_data, 'PUT'),
            'delete' => hotsoup_count_endpoints_by_method($endpoints_data, 'DELETE'),
        )
    ));
}
add_action('wp_ajax_hotsoup_get_endpoints', 'hotsoup_ajax_get_endpoints');

/**
 * REST API endpoint to get all endpoints data (for external access)
 */
function hotsoup_register_api_endpoints_endpoint() {
    register_rest_route('gread/v1', '/admin/endpoints', array(
        'methods' => 'GET',
        'callback' => 'hotsoup_rest_get_all_endpoints',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
}
add_action('rest_api_init', 'hotsoup_register_api_endpoints_endpoint');

/**
 * REST API callback to get all endpoints
 *
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response Response object
 */
function hotsoup_rest_get_all_endpoints($request) {
    $endpoints_data = hotsoup_get_all_endpoints_data();

    return new WP_REST_Response(array(
        'success' => true,
        'count' => count($endpoints_data),
        'endpoints' => $endpoints_data
    ), 200);
}
