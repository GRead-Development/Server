<?php
/**
 * Debug page to check Apple Sign-In configuration
 *
 * Place this file in your WordPress root and visit it to see your current settings.
 * Delete this file after debugging.
 */

// Load WordPress
require_once('./wp-load.php');

// Must be logged in as admin
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Apple Sign-In Debug</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; }
        h1 { color: #333; }
        .config-box { background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .config-item { margin: 10px 0; }
        .label { font-weight: bold; color: #555; }
        .value { font-family: monospace; background: #fff; padding: 5px 10px; border-radius: 4px; display: inline-block; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .instructions { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🍎 Apple Sign-In Configuration Debug</h1>

    <div class="config-box">
        <h2>Current WordPress Settings</h2>

        <div class="config-item">
            <span class="label">Apple Enabled:</span>
            <span class="value <?php echo get_option('hs_apple_enabled') ? 'success' : 'error'; ?>">
                <?php echo get_option('hs_apple_enabled') ? 'Yes ✓' : 'No ✗'; ?>
            </span>
        </div>

        <div class="config-item">
            <span class="label">Client ID (Service ID):</span>
            <span class="value">
                <?php echo esc_html(get_option('hs_apple_client_id') ?: '(not set)'); ?>
            </span>
        </div>

        <div class="config-item">
            <span class="label">Team ID:</span>
            <span class="value">
                <?php echo esc_html(get_option('hs_apple_team_id') ?: '(not set)'); ?>
            </span>
        </div>

        <div class="config-item">
            <span class="label">Key ID:</span>
            <span class="value">
                <?php echo esc_html(get_option('hs_apple_key_id') ?: '(not set)'); ?>
            </span>
        </div>

        <div class="config-item">
            <span class="label">Private Key:</span>
            <span class="value <?php echo get_option('hs_apple_private_key') ? 'success' : 'error'; ?>">
                <?php echo get_option('hs_apple_private_key') ? 'Set ✓' : 'Not set ✗'; ?>
            </span>
        </div>

        <div class="config-item">
            <span class="label">Redirect URI (WordPress Setting):</span>
            <span class="value">
                <?php
                $redirect_uri = get_option('hs_apple_redirect_uri', home_url('/'));
                echo esc_html($redirect_uri);
                ?>
            </span>
        </div>

        <div class="config-item">
            <span class="label">Home URL:</span>
            <span class="value"><?php echo esc_html(home_url('/')); ?></span>
        </div>

        <div class="config-item">
            <span class="label">Site URL:</span>
            <span class="value"><?php echo esc_html(site_url('/')); ?></span>
        </div>
    </div>

    <div class="instructions">
        <h3>📋 What to configure in Apple Developer Console:</h3>
        <ol>
            <li>Go to <a href="https://developer.apple.com/account/resources/identifiers/list/serviceId" target="_blank">Apple Developer - Service IDs</a></li>
            <li>Select your Service ID</li>
            <li>Click <strong>Configure</strong> next to "Sign in with Apple"</li>
            <li>Add these values:</li>
        </ol>

        <div style="margin: 20px; padding: 15px; background: white; border-radius: 4px;">
            <strong>Domains and Subdomains:</strong><br>
            <code style="background: #f5f5f5; padding: 5px 10px; display: inline-block; margin: 5px 0;">
                <?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?>
            </code>
            <br><br>
            <strong>Return URLs (add this EXACT URL):</strong><br>
            <code style="background: #f5f5f5; padding: 5px 10px; display: inline-block; margin: 5px 0;">
                <?php echo esc_html($redirect_uri); ?>
            </code>
        </div>

        <p><strong>⚠️ Important:</strong></p>
        <ul>
            <li>The Return URL must include <code>https://</code></li>
            <li>It must match EXACTLY (including trailing slash)</li>
            <li>After adding it, click <strong>Save</strong> in Apple Developer Console</li>
        </ul>
    </div>

    <div class="config-box">
        <h2>JavaScript Configuration (What's Sent to Apple)</h2>
        <p>This is what will be sent when the Apple Sign-In button is clicked:</p>

        <pre style="background: #fff; padding: 15px; border-radius: 4px; overflow-x: auto;">
AppleID.auth.init({
    clientId: "<?php echo esc_js(get_option('hs_apple_client_id')); ?>",
    scope: "name email",
    redirectURI: "<?php echo esc_js($redirect_uri); ?>",
    usePopup: true
});
        </pre>
    </div>

    <div class="instructions">
        <h3>🔧 How to Fix "Invalid web redirect url":</h3>
        <ol>
            <li><strong>In WordPress:</strong> Go to Settings → Social Auth → Apple Sign-In</li>
            <li>Set <strong>Redirect URI</strong> to: <code><?php echo esc_html(home_url('/')); ?></code></li>
            <li>Click <strong>Save Changes</strong></li>
            <li><strong>In Apple Developer Console:</strong> Add the same URL to Return URLs</li>
            <li>Wait 5-10 minutes for Apple's changes to propagate</li>
            <li>Clear your browser cache</li>
            <li>Try again</li>
        </ol>
    </div>

    <div style="margin-top: 40px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
        <strong>🗑️ Delete this file after debugging!</strong><br>
        This file shows sensitive configuration details and should not be left on your server.
    </div>

</body>
</html>
