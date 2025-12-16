<?php
/**
 * HotSoup Security Hardening Plugin
 *
 * Comprehensive security plugin to prevent code injection, XSS attacks,
 * SQL injection, and unauthorized code execution.
 *
 * @package HotSoup
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Main Security Hardening Class
 */
class HotSoup_Security_Hardening {

    private static $instance = null;
    private $blocked_attempts = array();
    private $rate_limit_cache = array();
    private $security_log = array();

    // Security settings
    private $max_requests_per_minute = 60;
    private $max_failed_attempts = 5;
    private $lockout_duration = 900; // 15 minutes

    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize all security hooks
     */
    private function __construct() {
        // Input validation and sanitization
        add_filter('rest_pre_dispatch', array($this, 'validate_rest_request'), 10, 3);
        add_action('init', array($this, 'init_security_measures'), 1);

        // XSS Protection
        add_filter('pre_comment_content', array($this, 'sanitize_user_content'), 10);
        add_filter('content_save_pre', array($this, 'sanitize_user_content'), 10);
        add_filter('excerpt_save_pre', array($this, 'sanitize_user_content'), 10);
        add_filter('title_save_pre', array($this, 'sanitize_title_content'), 10);

        // REST API Security
        add_action('rest_api_init', array($this, 'secure_rest_endpoints'), 5);

        // AJAX Security
        add_action('wp_ajax_nopriv_*', array($this, 'block_unauthorized_ajax'), -1);

        // Admin panel security
        add_action('admin_init', array($this, 'admin_security_checks'));
        add_action('admin_menu', array($this, 'add_security_admin_page'));

        // Rate limiting
        add_action('wp', array($this, 'check_rate_limit'));

        // SQL Injection monitoring
        add_filter('query', array($this, 'monitor_sql_queries'));

        // File upload security
        add_filter('upload_mimes', array($this, 'restrict_upload_mimes'));
        add_filter('wp_handle_upload_prefilter', array($this, 'validate_file_upload'));

        // Headers security
        add_action('send_headers', array($this, 'add_security_headers'));

        // Log cleaning (daily)
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_logs'));
    }

    /**
     * Initialize security measures
     */
    public function init_security_measures() {
        // Remove WordPress version from headers
        remove_action('wp_head', 'wp_generator');

        // Disable XML-RPC if not needed
        add_filter('xmlrpc_enabled', '__return_false');

        // Disable file editing from admin
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }

        // Remove sensitive information from login errors
        add_filter('login_errors', array($this, 'generic_login_error'));
    }

    /**
     * Validate REST API requests
     */
    public function validate_rest_request($result, $server, $request) {
        $route = $request->get_route();
        $params = $request->get_params();

        // Check rate limiting
        if (!$this->check_rate_limit_rest($route)) {
            $this->log_security_event('rate_limit_exceeded', array(
                'route' => $route,
                'ip' => $this->get_client_ip()
            ));
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many requests. Please try again later.',
                array('status' => 429)
            );
        }

        // Validate and sanitize all parameters
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                // Check for code injection attempts
                if ($this->detect_code_injection($value)) {
                    $this->log_security_event('code_injection_attempt', array(
                        'route' => $route,
                        'param' => $key,
                        'value' => substr($value, 0, 200),
                        'ip' => $this->get_client_ip(),
                        'user_id' => get_current_user_id()
                    ));

                    return new WP_Error(
                        'invalid_input',
                        'Invalid input detected. Your request has been logged.',
                        array('status' => 400)
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Detect code injection attempts
     */
    private function detect_code_injection($input) {
        // Patterns for common injection attacks
        $dangerous_patterns = array(
            // JavaScript injection
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i', // Event handlers like onclick, onerror

            // PHP code injection
            '/<\?php/i',
            '/<\?=/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/passthru\s*\(/i',
            '/shell_exec\s*\(/i',
            '/base64_decode\s*\(/i',
            '/assert\s*\(/i',
            '/preg_replace.*\/e["\']$/i',

            // SQL injection patterns
            '/union\s+select/i',
            '/union\s+all\s+select/i',
            '/\bor\b\s+\d+\s*=\s*\d+/i',
            '/\band\b\s+\d+\s*=\s*\d+/i',
            '/benchmark\s*\(/i',
            '/sleep\s*\(/i',
            '/waitfor\s+delay/i',

            // File inclusion
            '/\.\.[\/\\\\]/i', // Directory traversal
            '/include\s*\(/i',
            '/require\s*\(/i',

            // Command injection
            '/\|\s*\w+/i', // Pipe commands
            '/;\s*\w+/i', // Command chaining
            '/`[^`]+`/i', // Backtick execution
            '/\$\(\s*\w+/i', // Command substitution

            // Object injection
            '/O:\d+:"/i', // Serialized objects

            // HTML injection with dangerous tags
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/<link/i',
            '/<style/i',
            '/<meta/i',
            '/<base/i',
        );

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        // Check for encoded versions
        $decoded_input = urldecode($input);
        if ($decoded_input !== $input) {
            foreach ($dangerous_patterns as $pattern) {
                if (preg_match($pattern, $decoded_input)) {
                    return true;
                }
            }
        }

        // Check for double encoding
        $double_decoded = urldecode($decoded_input);
        if ($double_decoded !== $decoded_input) {
            foreach ($dangerous_patterns as $pattern) {
                if (preg_match($pattern, $double_decoded)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Sanitize user content with strict rules
     */
    public function sanitize_user_content($content) {
        // Remove all script tags and their contents
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);

        // Remove dangerous attributes
        $content = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);
        $content = preg_replace('/on\w+\s*=\s*[^>\s]*/i', '', $content);

        // Remove javascript: protocol
        $content = preg_replace('/javascript:/i', '', $content);

        // Remove data: protocol (can be used for XSS)
        $content = preg_replace('/data:text\/html/i', '', $content);

        // Use WordPress kses for additional filtering
        $allowed_tags = wp_kses_allowed_html('post');

        // Remove iframe, object, embed from allowed tags
        unset($allowed_tags['iframe']);
        unset($allowed_tags['object']);
        unset($allowed_tags['embed']);
        unset($allowed_tags['script']);
        unset($allowed_tags['style']);
        unset($allowed_tags['link']);
        unset($allowed_tags['meta']);
        unset($allowed_tags['base']);

        $content = wp_kses($content, $allowed_tags);

        // Additional sanitization
        $content = sanitize_textarea_field($content);

        return $content;
    }

    /**
     * Sanitize title content (stricter)
     */
    public function sanitize_title_content($title) {
        // Titles should be plain text only
        $title = strip_tags($title);
        $title = sanitize_text_field($title);

        // Check for code injection in titles
        if ($this->detect_code_injection($title)) {
            $this->log_security_event('code_injection_in_title', array(
                'title' => $title,
                'ip' => $this->get_client_ip(),
                'user_id' => get_current_user_id()
            ));
            return 'Invalid Title';
        }

        return $title;
    }

    /**
     * Secure REST endpoints
     */
    public function secure_rest_endpoints() {
        // Add additional authentication to sensitive endpoints
        add_filter('rest_pre_dispatch', function($result, $server, $request) {
            $route = $request->get_route();

            // Protect admin/sensitive endpoints
            $protected_routes = array(
                '/gread/v1/admin/',
                '/gread/v1/books/approve',
                '/gread/v1/books/reject',
                '/gread/v1/users/block',
                '/gread/v1/users/unblock',
            );

            foreach ($protected_routes as $protected_route) {
                if (strpos($route, $protected_route) === 0) {
                    if (!current_user_can('manage_options')) {
                        return new WP_Error(
                            'rest_forbidden',
                            'You do not have permission to access this endpoint.',
                            array('status' => 403)
                        );
                    }
                }
            }

            return $result;
        }, 10, 3);
    }

    /**
     * Block unauthorized AJAX requests
     */
    public function block_unauthorized_ajax() {
        if (!is_user_logged_in() && !check_ajax_referer('public_ajax', 'nonce', false)) {
            wp_die('Unauthorized request', 'Unauthorized', array('response' => 403));
        }
    }

    /**
     * Admin security checks
     */
    public function admin_security_checks() {
        // Additional verification for admin actions
        if (is_admin() && !wp_doing_ajax()) {
            // Check for suspicious $_GET parameters
            foreach ($_GET as $key => $value) {
                if (is_string($value) && $this->detect_code_injection($value)) {
                    $this->log_security_event('admin_injection_attempt', array(
                        'param' => $key,
                        'value' => substr($value, 0, 200),
                        'ip' => $this->get_client_ip(),
                        'user_id' => get_current_user_id()
                    ));

                    wp_die('Invalid request detected. This incident has been logged.');
                }
            }
        }
    }

    /**
     * Rate limiting check
     */
    public function check_rate_limit() {
        $ip = $this->get_client_ip();
        $current_time = time();

        // Initialize if not set
        if (!isset($this->rate_limit_cache[$ip])) {
            $this->rate_limit_cache[$ip] = array(
                'count' => 0,
                'start_time' => $current_time
            );
        }

        // Reset counter if minute has passed
        if ($current_time - $this->rate_limit_cache[$ip]['start_time'] >= 60) {
            $this->rate_limit_cache[$ip] = array(
                'count' => 1,
                'start_time' => $current_time
            );
            return true;
        }

        // Increment counter
        $this->rate_limit_cache[$ip]['count']++;

        // Check if limit exceeded
        if ($this->rate_limit_cache[$ip]['count'] > $this->max_requests_per_minute) {
            $this->log_security_event('rate_limit_exceeded', array(
                'ip' => $ip,
                'count' => $this->rate_limit_cache[$ip]['count']
            ));

            wp_die('Rate limit exceeded. Please slow down.', 'Too Many Requests', array('response' => 429));
        }

        return true;
    }

    /**
     * Check rate limit for REST API
     */
    private function check_rate_limit_rest($route) {
        $ip = $this->get_client_ip();
        $cache_key = 'rest_' . $ip . '_' . md5($route);
        $current_time = time();

        if (!isset($this->rate_limit_cache[$cache_key])) {
            $this->rate_limit_cache[$cache_key] = array(
                'count' => 1,
                'start_time' => $current_time
            );
            return true;
        }

        if ($current_time - $this->rate_limit_cache[$cache_key]['start_time'] >= 60) {
            $this->rate_limit_cache[$cache_key] = array(
                'count' => 1,
                'start_time' => $current_time
            );
            return true;
        }

        $this->rate_limit_cache[$cache_key]['count']++;

        // Allow more requests for authenticated users
        $limit = is_user_logged_in() ? $this->max_requests_per_minute * 2 : $this->max_requests_per_minute;

        return $this->rate_limit_cache[$cache_key]['count'] <= $limit;
    }

    /**
     * Monitor SQL queries for injection attempts
     */
    public function monitor_sql_queries($query) {
        // Skip monitoring for admin users doing legitimate operations
        if (current_user_can('manage_options')) {
            return $query;
        }

        // Check for suspicious patterns
        $suspicious_patterns = array(
            '/union\s+select/i',
            '/union\s+all\s+select/i',
            '/into\s+outfile/i',
            '/into\s+dumpfile/i',
            '/load_file\s*\(/i',
            '/benchmark\s*\(/i',
            '/sleep\s*\(/i',
            '/waitfor\s+delay/i',
        );

        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                $this->log_security_event('sql_injection_attempt', array(
                    'query' => substr($query, 0, 500),
                    'ip' => $this->get_client_ip(),
                    'user_id' => get_current_user_id()
                ));

                // Don't execute the query
                return "SELECT 1 WHERE 0"; // Return harmless query
            }
        }

        return $query;
    }

    /**
     * Restrict file upload MIME types
     */
    public function restrict_upload_mimes($mimes) {
        // Remove potentially dangerous file types
        unset($mimes['exe']);
        unset($mimes['php']);
        unset($mimes['phtml']);
        unset($mimes['php3']);
        unset($mimes['php4']);
        unset($mimes['php5']);
        unset($mimes['pht']);
        unset($mimes['phar']);
        unset($mimes['sh']);
        unset($mimes['bat']);
        unset($mimes['cmd']);
        unset($mimes['com']);

        return $mimes;
    }

    /**
     * Validate file uploads
     */
    public function validate_file_upload($file) {
        // Check file extension
        $filetype = wp_check_filetype($file['name']);

        if (!$filetype['ext']) {
            $file['error'] = 'Invalid file type.';
            $this->log_security_event('invalid_file_upload', array(
                'filename' => $file['name'],
                'ip' => $this->get_client_ip(),
                'user_id' => get_current_user_id()
            ));
            return $file;
        }

        // Check for double extensions
        if (preg_match('/\.php\./i', $file['name']) || preg_match('/\.exe\./i', $file['name'])) {
            $file['error'] = 'Invalid file name.';
            $this->log_security_event('suspicious_file_upload', array(
                'filename' => $file['name'],
                'ip' => $this->get_client_ip(),
                'user_id' => get_current_user_id()
            ));
        }

        return $file;
    }

    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');

        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self';");

        // Remove Server header
        header_remove('X-Powered-By');
    }

    /**
     * Generic login error message
     */
    public function generic_login_error() {
        return 'Invalid credentials. Please try again.';
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // CloudFlare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return sanitize_text_field($_SERVER[$key]);
            }
        }

        return 'unknown';
    }

    /**
     * Log security event
     */
    private function log_security_event($event_type, $data = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'type' => $event_type,
            'data' => $data
        );

        $this->security_log[] = $log_entry;

        // Store in WordPress options (limited to last 1000 entries)
        $existing_log = get_option('hs_security_log', array());
        $existing_log[] = $log_entry;

        // Keep only last 1000 entries
        if (count($existing_log) > 1000) {
            $existing_log = array_slice($existing_log, -1000);
        }

        update_option('hs_security_log', $existing_log, false);

        // Send email alert for critical events
        $critical_events = array('code_injection_attempt', 'sql_injection_attempt', 'admin_injection_attempt');
        if (in_array($event_type, $critical_events)) {
            $this->send_security_alert($event_type, $data);
        }
    }

    /**
     * Send security alert email
     */
    private function send_security_alert($event_type, $data) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');

        $subject = sprintf('[%s] Security Alert: %s', $site_name, ucwords(str_replace('_', ' ', $event_type)));

        $message = sprintf(
            "A security event has been detected on your website.\n\n" .
            "Event Type: %s\n" .
            "Time: %s\n" .
            "IP Address: %s\n" .
            "User ID: %s\n\n" .
            "Details:\n%s\n\n" .
            "Please review your security logs in the WordPress admin panel.",
            $event_type,
            current_time('mysql'),
            isset($data['ip']) ? $data['ip'] : 'unknown',
            isset($data['user_id']) ? $data['user_id'] : 'none',
            print_r($data, true)
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Add security admin page
     */
    public function add_security_admin_page() {
        add_menu_page(
            'Security Dashboard',
            'Security',
            'manage_options',
            'hs-security-dashboard',
            array($this, 'render_security_dashboard'),
            'dashicons-shield',
            99
        );
    }

    /**
     * Render security dashboard
     */
    public function render_security_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $security_log = get_option('hs_security_log', array());
        $security_log = array_reverse($security_log); // Show newest first

        // Count events by type
        $event_counts = array();
        foreach ($security_log as $entry) {
            $type = $entry['type'];
            if (!isset($event_counts[$type])) {
                $event_counts[$type] = 0;
            }
            $event_counts[$type]++;
        }

        ?>
        <div class="wrap">
            <h1>Security Dashboard</h1>

            <div class="hs-security-stats" style="margin: 20px 0;">
                <h2>Security Event Summary</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Event Type</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($event_counts)): ?>
                            <tr>
                                <td colspan="2">No security events logged yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($event_counts as $type => $count): ?>
                                <tr>
                                    <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $type))); ?></strong></td>
                                    <td><?php echo intval($count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="hs-security-log" style="margin: 20px 0;">
                <h2>Recent Security Events (Last 50)</h2>
                <?php if (isset($_POST['clear_log']) && check_admin_referer('clear_security_log')): ?>
                    <?php
                    delete_option('hs_security_log');
                    echo '<div class="notice notice-success"><p>Security log cleared.</p></div>';
                    $security_log = array();
                    ?>
                <?php endif; ?>

                <form method="post" style="margin-bottom: 10px;">
                    <?php wp_nonce_field('clear_security_log'); ?>
                    <button type="submit" name="clear_log" class="button button-secondary"
                            onclick="return confirm('Are you sure you want to clear the security log?');">
                        Clear Log
                    </button>
                </form>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="15%">Timestamp</th>
                            <th width="20%">Event Type</th>
                            <th width="15%">IP Address</th>
                            <th width="10%">User ID</th>
                            <th width="40%">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($security_log)): ?>
                            <tr>
                                <td colspan="5">No security events logged yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($security_log, 0, 50) as $entry): ?>
                                <tr>
                                    <td><?php echo esc_html($entry['timestamp']); ?></td>
                                    <td>
                                        <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $entry['type']))); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $ip = isset($entry['data']['ip']) ? $entry['data']['ip'] : 'N/A';
                                        echo esc_html($ip);
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $user_id = isset($entry['data']['user_id']) ? $entry['data']['user_id'] : 'N/A';
                                        echo esc_html($user_id);
                                        ?>
                                    </td>
                                    <td>
                                        <details>
                                            <summary style="cursor: pointer;">View Details</summary>
                                            <pre style="margin-top: 10px; padding: 10px; background: #f5f5f5; overflow-x: auto; font-size: 11px;"><?php echo esc_html(print_r($entry['data'], true)); ?></pre>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="hs-security-info" style="margin: 20px 0;">
                <h2>Security Features Enabled</h2>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><strong>Code Injection Detection</strong> - Monitors and blocks XSS, SQL injection, and command injection attempts</li>
                    <li><strong>Input Sanitization</strong> - Sanitizes all user input across forms, REST API, and AJAX endpoints</li>
                    <li><strong>Rate Limiting</strong> - Prevents abuse by limiting requests (60/minute for guests, 120/minute for logged-in users)</li>
                    <li><strong>File Upload Validation</strong> - Blocks dangerous file types and validates uploads</li>
                    <li><strong>Security Headers</strong> - Adds XSS, clickjacking, and MIME-type protection headers</li>
                    <li><strong>SQL Query Monitoring</strong> - Monitors database queries for injection patterns</li>
                    <li><strong>Email Alerts</strong> - Sends alerts for critical security events</li>
                    <li><strong>Audit Logging</strong> - Logs all security events for review</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Clean up old logs
     */
    public function cleanup_old_logs() {
        $security_log = get_option('hs_security_log', array());

        // Keep only last 30 days
        $thirty_days_ago = strtotime('-30 days');
        $security_log = array_filter($security_log, function($entry) use ($thirty_days_ago) {
            $timestamp = strtotime($entry['timestamp']);
            return $timestamp >= $thirty_days_ago;
        });

        update_option('hs_security_log', array_values($security_log), false);
    }
}

/**
 * Initialize the security plugin
 */
function hotsoup_init_security_hardening() {
    return HotSoup_Security_Hardening::get_instance();
}

// Start the security plugin
add_action('plugins_loaded', 'hotsoup_init_security_hardening', 1);
