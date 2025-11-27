<?php
/**
 * HotSoup! Performance Monitoring Tool
 *
 * Tracks database queries, execution time, and identifies performance bottlenecks.
 * Use this to measure the impact of optimization changes.
 *
 * @package HotSoup
 */

if (!defined('ABSPATH')) {
    exit;
}

class HS_Performance_Monitor {
    private static $instance = null;
    private $request_start = 0;
    private $enabled = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->enabled = get_option('hs_performance_monitor_enabled', false);

        if ($this->enabled) {
            // Set request start time immediately
            $this->request_start = microtime(true);
            $this->init_monitoring();
        }
    }

    private function init_monitoring() {
        // Log request end
        add_action('shutdown', array($this, 'log_request_metrics'), 999);

        // Clean old logs daily
        if (!wp_next_scheduled('hs_cleanup_performance_logs')) {
            wp_schedule_event(time(), 'daily', 'hs_cleanup_performance_logs');
        }
        add_action('hs_cleanup_performance_logs', array($this, 'cleanup_old_logs'));
    }

    public function log_request_metrics() {
        global $wpdb;

        // Skip if this is an admin-ajax call from our own dashboard
        if (defined('DOING_AJAX') && isset($_REQUEST['action']) &&
            strpos($_REQUEST['action'], 'hs_performance') !== false) {
            return;
        }

        // Calculate total time - add safety check
        $total_time = microtime(true) - $this->request_start;

        // If request_start wasn't set or time is invalid, skip logging
        if ($this->request_start <= 0 || $total_time < 0 || $total_time > 60) {
            error_log('HS Performance Monitor: Invalid time calculation, skipping log');
            return;
        }

        // Get query count and time from WordPress
        $query_count = 0;
        $total_query_time = 0;
        $n_plus_one_patterns = array();
        $slow_queries = array();

        if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
            $query_count = count($wpdb->queries);

            // Calculate total query time and find slow queries
            foreach ($wpdb->queries as $query_data) {
                $total_query_time += $query_data[1];

                // Track slow queries (>100ms)
                if ($query_data[1] > 0.1) {
                    $slow_queries[] = array(
                        'query' => $query_data[0],
                        'time' => round($query_data[1], 4),
                        'backtrace' => $query_data[2] ?? ''
                    );
                }
            }

            // Detect N+1 patterns
            $n_plus_one_patterns = $this->detect_n_plus_one($wpdb->queries);
        } else {
            // Even without SAVEQUERIES, count queries
            $query_count = $wpdb->num_queries;
        }

        // Determine request type
        $request_type = $this->get_request_type();

        // Get endpoint/page info
        $endpoint = $this->get_endpoint_info();

        // Store metrics
        $metrics = array(
            'timestamp' => current_time('mysql'),
            'request_type' => $request_type,
            'endpoint' => $endpoint,
            'query_count' => $query_count,
            'total_time' => round($total_time, 4),
            'query_time' => round($total_query_time, 4),
            'php_time' => round($total_time - $total_query_time, 4),
            'memory_usage' => memory_get_peak_usage(true),
            'n_plus_one_count' => count($n_plus_one_patterns),
            'slow_queries' => $slow_queries,
            'user_id' => get_current_user_id(),
            'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
        );

        // Store in database
        $saved = $this->save_metrics($metrics);

        // If in debug mode, output to console
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            $this->output_debug_info($metrics, $n_plus_one_patterns);
        }

        // Log to error log if saving failed
        if (!$saved && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HS Performance Monitor: Failed to save metrics');
        }
    }

    private function get_request_type() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'REST_API';
        } elseif (defined('DOING_AJAX') && DOING_AJAX) {
            return 'AJAX';
        } elseif (is_admin()) {
            return 'ADMIN';
        } else {
            return 'FRONTEND';
        }
    }

    private function get_endpoint_info() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown';
        } elseif (defined('DOING_AJAX') && DOING_AJAX) {
            return isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : 'unknown');
        } else {
            global $wp;
            return isset($wp->request) ? home_url($wp->request) : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown');
        }
    }

    private function detect_n_plus_one($queries) {
        if (empty($queries)) {
            return array();
        }

        $patterns = array();
        $query_patterns = array();

        // Group similar queries
        foreach ($queries as $query_data) {
            $query = $query_data[0];

            // Normalize query by removing specific IDs and values
            $normalized = preg_replace('/\d+/', 'N', $query);
            $normalized = preg_replace('/\'[^\']*\'/', "'X'", $normalized);
            $normalized = preg_replace('/\"[^\"]*\"/', '"X"', $normalized);

            if (!isset($query_patterns[$normalized])) {
                $query_patterns[$normalized] = array(
                    'count' => 0,
                    'example' => $query,
                    'total_time' => 0,
                    'backtrace' => isset($query_data[2]) ? $query_data[2] : ''
                );
            }

            $query_patterns[$normalized]['count']++;
            $query_patterns[$normalized]['total_time'] += $query_data[1];
        }

        // Find patterns with high repetition (potential N+1)
        foreach ($query_patterns as $pattern => $data) {
            if ($data['count'] > 5) {
                $patterns[] = array(
                    'pattern' => $pattern,
                    'count' => $data['count'],
                    'total_time' => round($data['total_time'], 4),
                    'example' => $data['example'],
                    'backtrace' => $data['backtrace']
                );
            }
        }

        // Sort by count (highest first)
        usort($patterns, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $patterns;
    }

    private function save_metrics($metrics) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'hs_performance_logs';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            // Try to create it
            hs_performance_monitor_create_table();

            // Check again
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

            if (!$table_exists) {
                error_log('HS Performance Monitor: Table does not exist and could not be created');
                return false;
            }
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'timestamp' => $metrics['timestamp'],
                'request_type' => $metrics['request_type'],
                'endpoint' => substr($metrics['endpoint'], 0, 255),
                'query_count' => $metrics['query_count'],
                'total_time' => $metrics['total_time'],
                'query_time' => $metrics['query_time'],
                'php_time' => $metrics['php_time'],
                'memory_usage' => $metrics['memory_usage'],
                'n_plus_one_count' => $metrics['n_plus_one_count'],
                'slow_queries' => maybe_serialize($metrics['slow_queries']),
                'user_id' => $metrics['user_id'],
                'url' => substr($metrics['url'], 0, 500)
            ),
            array('%s', '%s', '%s', '%d', '%f', '%f', '%f', '%d', '%d', '%s', '%d', '%s')
        );

        if ($result === false) {
            error_log('HS Performance Monitor: Insert failed - ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    private function output_debug_info($metrics, $n_plus_one_patterns) {
        echo "\n<!-- \n";
        echo "=== HotSoup Performance Monitor ===\n";
        echo "Request Type: {$metrics['request_type']}\n";
        echo "Endpoint: {$metrics['endpoint']}\n";
        echo "Total Queries: {$metrics['query_count']}\n";
        echo "Total Time: {$metrics['total_time']}s\n";
        echo "Query Time: {$metrics['query_time']}s\n";
        echo "PHP Time: {$metrics['php_time']}s\n";
        echo "Memory: " . size_format($metrics['memory_usage']) . "\n";
        echo "N+1 Patterns Detected: {$metrics['n_plus_one_count']}\n";

        if (!empty($n_plus_one_patterns)) {
            echo "\n--- N+1 Query Patterns ---\n";
            foreach ($n_plus_one_patterns as $pattern) {
                echo "Count: {$pattern['count']} | Time: {$pattern['total_time']}s\n";
                echo "Example: " . substr($pattern['example'], 0, 100) . "...\n";
                echo "---\n";
            }
        }

        if (!empty($metrics['slow_queries'])) {
            echo "\n--- Slow Queries (>100ms) ---\n";
            foreach ($metrics['slow_queries'] as $slow_query) {
                echo "Time: {$slow_query['time']}s\n";
                echo "Query: " . substr($slow_query['query'], 0, 100) . "...\n";
                echo "---\n";
            }
        }

        echo "-->\n";
    }

    public function cleanup_old_logs() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'hs_performance_logs';
        $days_to_keep = get_option('hs_performance_logs_retention', 7);

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_to_keep
        ));
    }

    /**
     * Get performance statistics
     */
    public static function get_stats($timeframe = '1 hour') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'hs_performance_logs';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            return null;
        }

        // Convert timeframe to SQL
        $interval_map = array(
            '1 hour' => 'INTERVAL 1 HOUR',
            '24 hours' => 'INTERVAL 24 HOUR',
            '7 days' => 'INTERVAL 7 DAY',
            '30 days' => 'INTERVAL 30 DAY'
        );

        $interval = isset($interval_map[$timeframe]) ? $interval_map[$timeframe] : 'INTERVAL 1 HOUR';

        $stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total_requests,
                AVG(query_count) as avg_queries,
                MAX(query_count) as max_queries,
                AVG(total_time) as avg_time,
                MAX(total_time) as max_time,
                AVG(memory_usage) as avg_memory,
                SUM(n_plus_one_count) as total_n_plus_one
            FROM $table_name
            WHERE timestamp > DATE_SUB(NOW(), $interval)
        ");

        if (!$stats || $stats->total_requests == 0) {
            return null;
        }

        // Get queries per minute
        $qpm = $wpdb->get_var("
            SELECT AVG(queries_per_minute)
            FROM (
                SELECT
                    DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i') as minute,
                    SUM(query_count) as queries_per_minute
                FROM $table_name
                WHERE timestamp > DATE_SUB(NOW(), $interval)
                GROUP BY minute
            ) as minute_stats
        ");

        // Get by request type
        $by_type = $wpdb->get_results("
            SELECT
                request_type,
                COUNT(*) as request_count,
                AVG(query_count) as avg_queries,
                AVG(total_time) as avg_time
            FROM $table_name
            WHERE timestamp > DATE_SUB(NOW(), $interval)
            GROUP BY request_type
        ");

        // Get slowest endpoints
        $slowest = $wpdb->get_results("
            SELECT
                endpoint,
                AVG(query_count) as avg_queries,
                AVG(total_time) as avg_time,
                COUNT(*) as hit_count
            FROM $table_name
            WHERE timestamp > DATE_SUB(NOW(), $interval)
            GROUP BY endpoint
            ORDER BY avg_time DESC
            LIMIT 10
        ");

        return array(
            'summary' => $stats,
            'queries_per_minute' => round($qpm ? $qpm : 0, 2),
            'by_request_type' => $by_type ? $by_type : array(),
            'slowest_endpoints' => $slowest ? $slowest : array()
        );
    }

    /**
     * Get total log count for diagnostics
     */
    public static function get_log_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hs_performance_logs';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
}

/**
 * Create performance logs table
 */
function hs_performance_monitor_create_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'hs_performance_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        timestamp datetime NOT NULL,
        request_type varchar(20) NOT NULL,
        endpoint varchar(255) NOT NULL,
        query_count int(11) NOT NULL,
        total_time float NOT NULL,
        query_time float NOT NULL,
        php_time float NOT NULL,
        memory_usage bigint(20) NOT NULL,
        n_plus_one_count int(11) NOT NULL DEFAULT 0,
        slow_queries longtext,
        user_id bigint(20) NOT NULL DEFAULT 0,
        url varchar(500),
        PRIMARY KEY  (id),
        KEY timestamp (timestamp),
        KEY request_type (request_type),
        KEY endpoint (endpoint),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Verify table was created
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    if ($table_exists) {
        error_log('HS Performance Monitor: Table created successfully');
    } else {
        error_log('HS Performance Monitor: Failed to create table');
    }
}

/**
 * Enable performance monitoring
 */
function hs_performance_monitor_enable() {
    // Create table if it doesn't exist
    hs_performance_monitor_create_table();

    update_option('hs_performance_monitor_enabled', true);

    // Show notice about SAVEQUERIES
    if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
        set_transient('hs_performance_savequeries_notice', true, 300);
    }

    // Initialize the monitor
    HS_Performance_Monitor::get_instance();
}

/**
 * Disable performance monitoring
 */
function hs_performance_monitor_disable() {
    update_option('hs_performance_monitor_enabled', false);
    wp_clear_scheduled_hook('hs_cleanup_performance_logs');
}

/**
 * Initialize on plugin load if enabled
 */
add_action('plugins_loaded', function() {
    if (get_option('hs_performance_monitor_enabled', false)) {
        HS_Performance_Monitor::get_instance();
    }
}, 1);

/**
 * Admin notice about SAVEQUERIES
 */
add_action('admin_notices', function() {
    if (get_transient('hs_performance_savequeries_notice')) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>Performance Monitor:</strong> For detailed query tracking, add this to your wp-config.php file:</p>
            <p><code>define('SAVEQUERIES', true);</code></p>
            <p>Without it, the monitor will only count queries but won't show N+1 patterns or slow queries.</p>
        </div>
        <?php
        delete_transient('hs_performance_savequeries_notice');
    }
});
