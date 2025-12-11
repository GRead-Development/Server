<?php
/**
 * Endpoint Status Monitor Admin Page
 *
 * Provides a comprehensive dashboard for testing and monitoring
 * all REST API endpoints in the HotSoup plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Endpoint Status Monitor to admin menu
 */
function hs_endpoint_monitor_add_admin_page() {
    add_submenu_page(
        'hotsoup-admin',
        'Endpoint Status Monitor',
        'Endpoint Monitor',
        'manage_options',
        'hs-endpoint-monitor',
        'hs_endpoint_monitor_page_html'
    );
}
add_action('admin_menu', 'hs_endpoint_monitor_add_admin_page');

/**
 * Enqueue scripts and styles for the endpoint monitor page
 */
function hs_endpoint_monitor_enqueue_scripts($hook) {
    if ($hook !== 'hotsoup_page_hs-endpoint-monitor') {
        return;
    }

    wp_enqueue_style(
        'hs-endpoint-monitor-css',
        plugin_dir_url(dirname(dirname(__FILE__))) . 'css/admin/endpoint-status-monitor.css',
        array(),
        '1.0.0'
    );

    wp_enqueue_script(
        'hs-endpoint-monitor-js',
        plugin_dir_url(dirname(dirname(__FILE__))) . 'js/admin/endpoint-status-monitor.js',
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script('hs-endpoint-monitor-js', 'hsEndpointMonitor', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hs_endpoint_monitor_nonce'),
        'restUrl' => rest_url(),
        'restNonce' => wp_create_nonce('wp_rest')
    ));
}
add_action('admin_enqueue_scripts', 'hs_endpoint_monitor_enqueue_scripts');

/**
 * Display the endpoint monitor page HTML
 */
function hs_endpoint_monitor_page_html() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap hs-endpoint-monitor">
        <h1>
            <span class="dashicons dashicons-admin-site-alt3"></span>
            Endpoint Status Monitor
        </h1>

        <div class="hs-monitor-description">
            <p>This tool tests all REST API endpoints to verify they are functioning correctly. Click "Test All Endpoints" to begin monitoring.</p>
        </div>

        <div class="hs-monitor-controls">
            <button id="test-all-endpoints" class="button button-primary button-large">
                <span class="dashicons dashicons-update"></span>
                Test All Endpoints
            </button>
            <button id="test-failed-only" class="button button-secondary" style="display:none;">
                <span class="dashicons dashicons-warning"></span>
                Retest Failed Endpoints
            </button>
            <button id="export-results" class="button button-secondary" style="display:none;">
                <span class="dashicons dashicons-download"></span>
                Export Results
            </button>
            <button id="stop-testing" class="button button-secondary" style="display:none;">
                <span class="dashicons dashicons-no"></span>
                Stop Testing
            </button>
        </div>

        <div class="hs-monitor-progress" style="display:none;">
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: 0%"></div>
            </div>
            <div class="progress-text">
                <span class="current-progress">0</span> / <span class="total-endpoints">0</span> endpoints tested
            </div>
        </div>

        <div class="hs-monitor-summary" style="display:none;">
            <div class="summary-card success-card">
                <div class="summary-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="summary-content">
                    <div class="summary-number" id="success-count">0</div>
                    <div class="summary-label">Successful</div>
                </div>
            </div>

            <div class="summary-card error-card">
                <div class="summary-icon">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <div class="summary-content">
                    <div class="summary-number" id="error-count">0</div>
                    <div class="summary-label">Failed</div>
                </div>
            </div>

            <div class="summary-card auth-card">
                <div class="summary-icon">
                    <span class="dashicons dashicons-lock"></span>
                </div>
                <div class="summary-content">
                    <div class="summary-number" id="auth-count">0</div>
                    <div class="summary-label">Auth Required</div>
                </div>
            </div>

            <div class="summary-card total-card">
                <div class="summary-icon">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                </div>
                <div class="summary-content">
                    <div class="summary-number" id="total-count">0</div>
                    <div class="summary-label">Total Endpoints</div>
                </div>
            </div>
        </div>

        <div class="hs-monitor-filters" style="display:none;">
            <label>
                <input type="checkbox" id="filter-success" checked>
                Show Successful (<span class="filter-count-success">0</span>)
            </label>
            <label>
                <input type="checkbox" id="filter-error" checked>
                Show Failed (<span class="filter-count-error">0</span>)
            </label>
            <label>
                <input type="checkbox" id="filter-auth" checked>
                Show Auth Required (<span class="filter-count-auth">0</span>)
            </label>
            <label>
                <input type="checkbox" id="filter-untested" checked>
                Show Untested (<span class="filter-count-untested">0</span>)
            </label>
        </div>

        <div class="hs-monitor-results">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-status" width="80">Status</th>
                        <th class="column-method" width="80">Method</th>
                        <th class="column-endpoint">Endpoint</th>
                        <th class="column-response-time" width="100">Time</th>
                        <th class="column-details" width="150">Details</th>
                    </tr>
                </thead>
                <tbody id="endpoint-results">
                    <tr class="no-results">
                        <td colspan="5" class="text-center">Click "Test All Endpoints" to begin monitoring</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="endpoint-detail-modal" class="hs-modal" style="display:none;">
            <div class="hs-modal-content">
                <span class="hs-modal-close">&times;</span>
                <h2>Endpoint Details</h2>
                <div id="endpoint-detail-content"></div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * AJAX handler to get all endpoints
 */
function hs_get_all_endpoints() {
    check_ajax_referer('hs_endpoint_monitor_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'), 403);
        return;
    }

    global $wp_rest_server;

    if (empty($wp_rest_server)) {
        $wp_rest_server = rest_get_server();
    }

    $namespaces = $wp_rest_server->get_namespaces();
    $all_endpoints = array();

    foreach ($namespaces as $namespace) {
        if (!in_array($namespace, array('gread/v1', 'gread/v2'))) {
            continue;
        }

        $routes = $wp_rest_server->get_routes($namespace);

        foreach ($routes as $route => $route_data) {
            foreach ($route_data as $handler) {
                if (isset($handler['methods'])) {
                    $methods = array_keys($handler['methods']);

                    foreach ($methods as $method) {
                        $endpoint_info = array(
                            'namespace' => $namespace,
                            'route' => $route,
                            'method' => $method,
                            'full_url' => rest_url($route),
                            'requires_auth' => !isset($handler['permission_callback']) ||
                                             ($handler['permission_callback'] !== '__return_true'),
                            'args' => isset($handler['args']) ? $handler['args'] : array()
                        );

                        $all_endpoints[] = $endpoint_info;
                    }
                }
            }
        }
    }

    usort($all_endpoints, function($a, $b) {
        $route_compare = strcmp($a['route'], $b['route']);
        if ($route_compare !== 0) {
            return $route_compare;
        }
        return strcmp($a['method'], $b['method']);
    });

    wp_send_json_success(array(
        'endpoints' => $all_endpoints,
        'total' => count($all_endpoints)
    ));
}
add_action('wp_ajax_hs_get_all_endpoints', 'hs_get_all_endpoints');
