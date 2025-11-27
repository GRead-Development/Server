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
    private $queries = array();
    private $start_time = 0;
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
            $this->init_monitoring();
        }
    }

    private function init_monitoring() {
        // Track request start time
        add_action('init', array($this, 'track_request_start'), 1);

        // Track queries
        add_filter('query', array($this, 'track_query'));

        // Log request end
        add_action('shutdown', array($this, 'log_request_metrics'), 999);

        // Clean old logs daily
        if (!wp_next_scheduled('hs_cleanup_performance_logs')) {
            wp_schedule_event(time(), 'daily', 'hs_cleanup_performance_logs');
        }
        add_action('hs_cleanup_performance_logs', array($this, 'cleanup_old_logs'));
    }

    public function track_request_start() {
        $this->request_start = microtime(true);
        $this->queries = array();
    }

    public function track_query($query) {
        global $wpdb;

        $start_time = microtime(true);

        // Store query info
        $this->queries[] = array(
            'query' => $query,
            'time' => 0, // Will be updated after execution
            'backtrace' => $this->get_clean_backtrace(),
            'timestamp' => $start_time
        );

        return $query;
    }

    private function get_clean_backtrace() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $relevant_trace = array();

        foreach ($backtrace as $trace) {
            // Skip WordPress core files
            if (isset($trace['file']) &&
                (strpos($trace['file'], 'wp-includes') !== false ||
                 strpos($trace['file'], 'wp-admin') !== false)) {
                continue;
            }

            if (isset($trace['file'])) {
                $relevant_trace[] = array(
                    'file' => str_replace(WP_PLUGIN_DIR, '', $trace['file']),
                    'line' => isset($trace['line']) ? $trace['line'] : 0,
                    'function' => isset($trace['function']) ? $trace['function'] : ''
                );
            }
        }

        return array_slice($relevant_trace, 0, 3);
    }

    public function log_request_metrics() {
        global $wpdb;

        if (empty($this->queries)) {
            return;
        }

        $total_time = microtime(true) - $this->request_start;
        $query_count = count($this->queries);

        // Get actual query times from WordPress
        if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
            $total_query_time = 0;
            foreach ($wpdb->queries as $query_data) {
                $total_query_time += $query_data[1];
            }
        } else {
            $total_query_time = 0;
        }

        // Detect N+1 patterns
        $n_plus_one_patterns = $this->detect_n_plus_one();

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
            'slow_queries' => $this->get_slow_queries(),
            'user_id' => get_current_user_id(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
        );

        // Store in database
        $this->save_metrics($metrics);

        // If in debug mode, output to console
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            $this->output_debug_info($metrics, $n_plus_one_patterns);
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
            return $_SERVER['REQUEST_URI'] ?? 'unknown';
        } elseif (defined('DOING_AJAX') && DOING_AJAX) {
            return $_POST['action'] ?? $_GET['action'] ?? 'unknown';
        } else {
            global $wp;
            return home_url($wp->request);
        }
    }

    private function detect_n_plus_one() {
        global $wpdb;

        if (!defined('SAVEQUERIES') || !SAVEQUERIES || empty($wpdb->queries)) {
            return array();
        }

        $patterns = array();
        $query_patterns = array();

        // Group similar queries
        foreach ($wpdb->queries as $query_data) {
            $query = $query_data[0];

            // Normalize query by removing specific IDs
            $normalized = preg_replace('/\d+/', 'N', $query);
            $normalized = preg_replace('/\'[^\']*\'/', "'X'", $normalized);

            if (!isset($query_patterns[$normalized])) {
                $query_patterns[$normalized] = array(
                    'count' => 0,
                    'example' => $query,
                    'total_time' => 0,
                    'backtrace' => $query_data[2] ?? ''
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

    private function get_slow_queries() {
        global $wpdb;

        if (!defined('SAVEQUERIES') || !SAVEQUERIES || empty($wpdb->queries)) {
            return array();
        }

        $slow_queries = array();
        $threshold = 0.1; // 100ms

        foreach ($wpdb->queries as $query_data) {
            if ($query_data[1] > $threshold) {
                $slow_queries[] = array(
                    'query' => $query_data[0],
                    'time' => round($query_data[1], 4),
                    'backtrace' => $query_data[2] ?? ''
                );
            }
        }

        // Sort by time (slowest first)
        usort($slow_queries, function($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        return array_slice($slow_queries, 0, 10);
    }

    private function save_metrics($metrics) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'hs_performance_logs';

        $wpdb->insert(
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
    }

    private function output_debug_info($metrics, $n_plus_one_patterns) {
        echo "<!-- \n";
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

        // Convert timeframe to SQL
        $interval_map = array(
            '1 hour' => 'INTERVAL 1 HOUR',
            '24 hours' => 'INTERVAL 24 HOUR',
            '7 days' => 'INTERVAL 7 DAY',
            '30 days' => 'INTERVAL 30 DAY'
        );

        $interval = $interval_map[$timeframe] ?? 'INTERVAL 1 HOUR';

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
            'queries_per_minute' => round($qpm, 2),
            'by_request_type' => $by_type,
            'slowest_endpoints' => $slowest
        );
    }
}

/**
 * Create performance logs table
 */
function hs_performance_monitor_create_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'hs_performance_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
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
}

/**
 * Enable performance monitoring
 */
function hs_performance_monitor_enable() {
    // Create table if it doesn't exist
    hs_performance_monitor_create_table();

    // Enable SAVEQUERIES for detailed tracking
    if (!defined('SAVEQUERIES')) {
        define('SAVEQUERIES', true);
    }

    update_option('hs_performance_monitor_enabled', true);

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
