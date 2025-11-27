<?php
/**
 * Performance Dashboard Admin Page
 *
 * Displays performance metrics and monitoring controls
 *
 * @package HotSoup
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu page
 */
function hs_performance_dashboard_menu() {
    add_submenu_page(
        'edit.php?post_type=book',
        'Performance Monitor',
        'Performance',
        'manage_options',
        'hs-performance-dashboard',
        'hs_performance_dashboard_page'
    );
}
add_action('admin_menu', 'hs_performance_dashboard_menu');

/**
 * Handle form submissions
 */
function hs_performance_dashboard_handle_actions() {
    if (!isset($_POST['hs_performance_action']) || !current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('hs_performance_action');

    $action = sanitize_text_field($_POST['hs_performance_action']);

    switch ($action) {
        case 'enable':
            hs_performance_monitor_enable();
            add_settings_error('hs_performance', 'monitor_enabled', 'Performance monitoring enabled!', 'success');
            break;

        case 'disable':
            hs_performance_monitor_disable();
            add_settings_error('hs_performance', 'monitor_disabled', 'Performance monitoring disabled.', 'success');
            break;

        case 'clear_logs':
            global $wpdb;
            $table_name = $wpdb->prefix . 'hs_performance_logs';
            $wpdb->query("TRUNCATE TABLE $table_name");
            add_settings_error('hs_performance', 'logs_cleared', 'Performance logs cleared!', 'success');
            break;

        case 'update_retention':
            $days = intval($_POST['retention_days']);
            if ($days > 0 && $days <= 90) {
                update_option('hs_performance_logs_retention', $days);
                add_settings_error('hs_performance', 'retention_updated', 'Log retention updated!', 'success');
            }
            break;
    }
}
add_action('admin_init', 'hs_performance_dashboard_handle_actions');

/**
 * Dashboard page content
 */
function hs_performance_dashboard_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    global $wpdb;

    $is_enabled = get_option('hs_performance_monitor_enabled', false);
    $retention_days = get_option('hs_performance_logs_retention', 7);
    $timeframe = isset($_GET['timeframe']) ? sanitize_text_field($_GET['timeframe']) : '1 hour';

    // Get diagnostics
    $log_count = HS_Performance_Monitor::get_log_count();
    $table_name = $wpdb->prefix . 'hs_performance_logs';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    $savequeries_enabled = defined('SAVEQUERIES') && SAVEQUERIES;

    // Get stats
    $stats = null;
    if ($is_enabled && $table_exists) {
        $stats = HS_Performance_Monitor::get_stats($timeframe);
    }

    ?>
    <div class="wrap">
        <h1>🚀 HotSoup! Performance Monitor</h1>

        <?php settings_errors('hs_performance'); ?>

        <!-- Diagnostics Panel -->
        <div class="card" style="max-width: 100%; margin-top: 20px; background: #f0f6fc; border-left: 4px solid #2271b1;">
            <h3>📊 System Status</h3>
            <table class="form-table">
                <tr>
                    <th>Monitoring:</th>
                    <td>
                        <?php if ($is_enabled): ?>
                            <span style="color: #46b450; font-weight: bold;">✓ Enabled</span>
                        <?php else: ?>
                            <span style="color: #dc3232; font-weight: bold;">✗ Disabled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Database Table:</th>
                    <td>
                        <?php if ($table_exists): ?>
                            <span style="color: #46b450; font-weight: bold;">✓ Exists</span>
                        <?php else: ?>
                            <span style="color: #dc3232; font-weight: bold;">✗ Not Created</span>
                            <p class="description">The table will be created when you enable monitoring.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>SAVEQUERIES:</th>
                    <td>
                        <?php if ($savequeries_enabled): ?>
                            <span style="color: #46b450; font-weight: bold;">✓ Enabled</span> - Full query tracking available
                        <?php else: ?>
                            <span style="color: #f0ad4e; font-weight: bold;">⚠ Disabled</span> - Only query counting available
                            <p class="description">
                                Add <code>define('SAVEQUERIES', true);</code> to your wp-config.php file<br>
                                (before the "That's all" comment) to enable N+1 detection and slow query tracking.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Logs Collected:</th>
                    <td>
                        <strong><?php echo number_format($log_count); ?></strong> requests tracked
                        <?php if ($log_count == 0 && $is_enabled): ?>
                            <p class="description">
                                No logs yet. Make some requests to your site (visit pages, use the API) and refresh this page.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>Monitoring Status</h2>

            <?php if ($is_enabled): ?>
                <p style="color: #46b450; font-weight: bold;">✓ Performance monitoring is ACTIVE</p>
                <p>Database queries and performance metrics are being tracked.</p>

                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('hs_performance_action'); ?>
                    <input type="hidden" name="hs_performance_action" value="disable">
                    <button type="submit" class="button">Disable Monitoring</button>
                </form>
            <?php else: ?>
                <p style="color: #dc3232; font-weight: bold;">✗ Performance monitoring is INACTIVE</p>
                <p>Enable monitoring to start tracking performance metrics.</p>

                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0;">
                    <strong>⚠️ Important:</strong> Performance monitoring adds a small overhead to each request.
                    Enable it when you need to measure performance, and disable it when done.
                </div>

                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('hs_performance_action'); ?>
                    <input type="hidden" name="hs_performance_action" value="enable">
                    <button type="submit" class="button button-primary">Enable Monitoring</button>
                </form>
            <?php endif; ?>

            <hr>

            <h3>Settings</h3>
            <form method="post">
                <?php wp_nonce_field('hs_performance_action'); ?>
                <input type="hidden" name="hs_performance_action" value="update_retention">

                <table class="form-table">
                    <tr>
                        <th scope="row">Log Retention</th>
                        <td>
                            <input type="number" name="retention_days" value="<?php echo esc_attr($retention_days); ?>" min="1" max="90" style="width: 100px;">
                            days
                            <p class="description">Logs older than this will be automatically deleted.</p>
                        </td>
                    </tr>
                </table>

                <button type="submit" class="button">Update Settings</button>
            </form>

            <hr>

            <form method="post" onsubmit="return confirm('Are you sure you want to clear all performance logs?');">
                <?php wp_nonce_field('hs_performance_action'); ?>
                <input type="hidden" name="hs_performance_action" value="clear_logs">
                <button type="submit" class="button button-secondary">Clear All Logs</button>
            </form>
        </div>

        <?php if ($stats && $is_enabled): ?>
            <div style="margin-top: 20px;">
                <h2>Performance Statistics</h2>

                <div style="margin-bottom: 20px;">
                    <strong>Timeframe:</strong>
                    <a href="?post_type=book&page=hs-performance-dashboard&timeframe=1 hour" class="button <?php echo $timeframe === '1 hour' ? 'button-primary' : ''; ?>">Last Hour</a>
                    <a href="?post_type=book&page=hs-performance-dashboard&timeframe=24 hours" class="button <?php echo $timeframe === '24 hours' ? 'button-primary' : ''; ?>">Last 24 Hours</a>
                    <a href="?post_type=book&page=hs-performance-dashboard&timeframe=7 days" class="button <?php echo $timeframe === '7 days' ? 'button-primary' : ''; ?>">Last 7 Days</a>
                    <a href="?post_type=book&page=hs-performance-dashboard&timeframe=30 days" class="button <?php echo $timeframe === '30 days' ? 'button-primary' : ''; ?>">Last 30 Days</a>
                </div>

                <?php if ($stats['summary']->total_requests > 0): ?>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div class="card">
                            <h3>📊 Total Requests</h3>
                            <div style="font-size: 2em; font-weight: bold; color: #2271b1;">
                                <?php echo number_format($stats['summary']->total_requests); ?>
                            </div>
                        </div>

                        <div class="card">
                            <h3>🔍 Queries/Minute</h3>
                            <div style="font-size: 2em; font-weight: bold; color: #2271b1;">
                                <?php echo number_format($stats['queries_per_minute'], 2); ?>
                            </div>
                        </div>

                        <div class="card">
                            <h3>📈 Avg Queries/Request</h3>
                            <div style="font-size: 2em; font-weight: bold; color: #2271b1;">
                                <?php echo number_format($stats['summary']->avg_queries, 1); ?>
                            </div>
                            <div style="font-size: 0.9em; color: #666;">
                                Max: <?php echo number_format($stats['summary']->max_queries); ?>
                            </div>
                        </div>

                        <div class="card">
                            <h3>⏱️ Avg Response Time</h3>
                            <div style="font-size: 2em; font-weight: bold; color: <?php echo $stats['summary']->avg_time > 1 ? '#dc3232' : '#46b450'; ?>">
                                <?php echo number_format($stats['summary']->avg_time * 1000, 0); ?>ms
                            </div>
                            <div style="font-size: 0.9em; color: #666;">
                                Max: <?php echo number_format($stats['summary']->max_time * 1000, 0); ?>ms
                            </div>
                        </div>

                        <div class="card">
                            <h3>💾 Avg Memory</h3>
                            <div style="font-size: 2em; font-weight: bold; color: #2271b1;">
                                <?php echo size_format($stats['summary']->avg_memory, 0); ?>
                            </div>
                        </div>

                        <div class="card">
                            <h3>⚠️ N+1 Patterns</h3>
                            <div style="font-size: 2em; font-weight: bold; color: <?php echo $stats['summary']->total_n_plus_one > 0 ? '#dc3232' : '#46b450'; ?>">
                                <?php echo number_format($stats['summary']->total_n_plus_one); ?>
                            </div>
                            <div style="font-size: 0.9em; color: #666;">
                                <?php if ($stats['summary']->total_n_plus_one > 0): ?>
                                    <strong>Needs optimization!</strong>
                                <?php else: ?>
                                    No issues detected
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="margin-top: 20px;">
                        <h3>Performance by Request Type</h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Requests</th>
                                    <th>Avg Queries</th>
                                    <th>Avg Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['by_request_type'] as $type): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($type->request_type); ?></strong></td>
                                        <td><?php echo number_format($type->request_count); ?></td>
                                        <td><?php echo number_format($type->avg_queries, 1); ?></td>
                                        <td style="color: <?php echo $type->avg_time > 1 ? '#dc3232' : '#000'; ?>">
                                            <?php echo number_format($type->avg_time * 1000, 0); ?>ms
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card" style="margin-top: 20px;">
                        <h3>🐌 Slowest Endpoints</h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Endpoint</th>
                                    <th>Hits</th>
                                    <th>Avg Queries</th>
                                    <th>Avg Time</th>
                                    <th>Impact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['slowest_endpoints'] as $endpoint): ?>
                                    <?php
                                    $impact_score = $endpoint->hit_count * $endpoint->avg_time;
                                    $impact_color = $impact_score > 10 ? '#dc3232' : ($impact_score > 5 ? '#f0ad4e' : '#46b450');
                                    ?>
                                    <tr>
                                        <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis;">
                                            <code><?php echo esc_html($endpoint->endpoint); ?></code>
                                        </td>
                                        <td><?php echo number_format($endpoint->hit_count); ?></td>
                                        <td><?php echo number_format($endpoint->avg_queries, 1); ?></td>
                                        <td style="color: <?php echo $endpoint->avg_time > 1 ? '#dc3232' : '#000'; ?>">
                                            <?php echo number_format($endpoint->avg_time * 1000, 0); ?>ms
                                        </td>
                                        <td>
                                            <span style="color: <?php echo $impact_color; ?>; font-weight: bold;">
                                                <?php
                                                if ($impact_score > 10) {
                                                    echo '🔴 High';
                                                } elseif ($impact_score > 5) {
                                                    echo '🟡 Medium';
                                                } else {
                                                    echo '🟢 Low';
                                                }
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top: 10px; font-size: 0.9em; color: #666;">
                            <strong>Impact</strong> = Hits × Avg Time. Focus optimization efforts on high-impact endpoints.
                        </p>
                    </div>

                    <div class="card" style="margin-top: 20px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                        <h3>💡 Performance Tips</h3>
                        <ul style="line-height: 1.8;">
                            <li><strong>Target:</strong> &lt;50 queries per request for frontend pages</li>
                            <li><strong>Target:</strong> &lt;500ms response time for API endpoints</li>
                            <li><strong>N+1 Patterns:</strong> Should be zero - these cause exponential query growth</li>
                            <li><strong>Focus:</strong> Optimize high-impact endpoints first (frequent + slow)</li>
                            <li><strong>Check:</strong> Enable WP_DEBUG to see query details in HTML comments</li>
                        </ul>
                    </div>

                <?php else: ?>
                    <div class="card">
                        <p>No performance data available for the selected timeframe.</p>
                        <p>Make some requests to your site to start collecting metrics.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($is_enabled): ?>
            <div class="card" style="margin-top: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <h3>⏳ Waiting for Data...</h3>
                <p><strong>Monitoring is active!</strong> Data collection has started.</p>

                <?php if ($log_count == 0): ?>
                    <p>No requests have been logged yet. To start seeing data:</p>
                    <ol>
                        <li>Visit your site's homepage</li>
                        <li>Browse a few pages</li>
                        <li>Use the search feature</li>
                        <li>View a book page</li>
                        <li>Come back here and refresh this page</li>
                    </ol>
                    <p><em>Tip: Open your site in a new tab and interact with it, then refresh this page.</em></p>
                <?php else: ?>
                    <p>We have <?php echo $log_count; ?> requests logged, but none in the selected timeframe (<?php echo esc_html($timeframe); ?>).</p>
                    <p>Try selecting a longer timeframe above, or make some new requests to your site.</p>
                <?php endif; ?>
            </div>
        <?php elseif (!$is_enabled): ?>
            <div class="card" style="margin-top: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <h3>🚀 Get Started</h3>
                <p>Performance monitoring is currently disabled.</p>
                <p><strong>To start tracking performance:</strong></p>
                <ol>
                    <li>Click "Enable Monitoring" above</li>
                    <li>Use your site normally for a few minutes</li>
                    <li>Return here to view performance metrics</li>
                </ol>
                <p><em>See the "How to Use This Tool" section below for more details.</em></p>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-top: 20px;">
            <h3>📖 How to Use This Tool</h3>
            <ol style="line-height: 1.8;">
                <li><strong>Enable monitoring</strong> before making optimizations</li>
                <li><strong>Baseline measurement:</strong> Use your site normally for a few minutes to establish current performance</li>
                <li><strong>Note the metrics:</strong> Record queries/minute, avg queries/request, and response times</li>
                <li><strong>Make one optimization</strong> at a time</li>
                <li><strong>Test and measure:</strong> Use the site the same way and compare metrics</li>
                <li><strong>Disable monitoring</strong> when you're done testing</li>
            </ol>

            <h3>🔍 Understanding the Metrics</h3>
            <ul style="line-height: 1.8;">
                <li><strong>Queries/Minute:</strong> Total database queries per minute across all requests. Lower is better.</li>
                <li><strong>Avg Queries/Request:</strong> Average number of database queries per page/API request. Frontend pages should be &lt;50.</li>
                <li><strong>Avg Response Time:</strong> How long requests take to complete. API endpoints should be &lt;500ms.</li>
                <li><strong>N+1 Patterns:</strong> Critical issue where a query is repeated many times. This should always be zero!</li>
                <li><strong>Impact Score:</strong> Hits × Time. Shows which endpoints are hurting performance the most.</li>
            </ul>

            <h3>🎯 Optimization Checklist</h3>
            <p>Use the following checklist for each N+1 pattern or slow endpoint:</p>
            <ul style="line-height: 1.8;">
                <li>☐ Identify the code causing the issue (use backtrace in slow queries)</li>
                <li>☐ Replace loops with batch queries (use IN() clauses)</li>
                <li>☐ Add caching where appropriate</li>
                <li>☐ Test the change</li>
                <li>☐ Measure the improvement</li>
                <li>☐ Document the change</li>
            </ul>
        </div>
    </div>

    <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }

        .card h2, .card h3 {
            margin-top: 0;
        }
    </style>
    <?php
}

/**
 * Enqueue admin scripts
 */
function hs_performance_dashboard_scripts($hook) {
    if ('book_page_hs-performance-dashboard' !== $hook) {
        return;
    }

    // Add auto-refresh script for real-time monitoring
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Auto-refresh every 30 seconds when monitoring is active
        <?php if (get_option('hs_performance_monitor_enabled', false)): ?>
        setTimeout(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>
    });
    </script>
    <?php
}
add_action('admin_enqueue_scripts', 'hs_performance_dashboard_scripts');
