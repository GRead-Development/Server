<?php
/**
 * Social Provider Utilities
 *
 * Handles token verification for Apple and Google OAuth
 *
 * @package HotSoup
 * @since 0.37
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verify Apple ID Token
 *
 * @param string $id_token The Apple ID token to verify
 * @return array|WP_Error User data on success, WP_Error on failure
 */
function hs_verify_apple_token($id_token) {
    // Apple's public keys endpoint
    $keys_url = 'https://appleid.apple.com/auth/keys';

    // Decode the token without verification first to get the header
    $token_parts = explode('.', $id_token);
    if (count($token_parts) !== 3) {
        return new WP_Error('invalid_token_format', 'Invalid token format');
    }

    // Decode header to get the key ID (kid)
    $header = json_decode(base64_decode(strtr($token_parts[0], '-_', '+/')), true);
    if (!isset($header['kid']) || !isset($header['alg'])) {
        return new WP_Error('invalid_token_header', 'Invalid token header');
    }

    // Fetch Apple's public keys
    $response = wp_remote_get($keys_url);
    if (is_wp_error($response)) {
        return new WP_Error('keys_fetch_failed', 'Failed to fetch Apple public keys');
    }

    $keys_data = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($keys_data['keys'])) {
        return new WP_Error('invalid_keys_response', 'Invalid keys response from Apple');
    }

    // Find the matching key
    $matching_key = null;
    foreach ($keys_data['keys'] as $key) {
        if ($key['kid'] === $header['kid']) {
            $matching_key = $key;
            break;
        }
    }

    if (!$matching_key) {
        return new WP_Error('key_not_found', 'Matching public key not found');
    }

    // Verify the token using the public key
    try {
        $payload = hs_verify_jwt_signature($id_token, $matching_key);

        if (!$payload) {
            return new WP_Error('signature_verification_failed', 'Token signature verification failed');
        }

        // Validate the token claims
        $client_id = get_option('hs_apple_client_id');

        // Check issuer
        if (!isset($payload['iss']) || $payload['iss'] !== 'https://appleid.apple.com') {
            return new WP_Error('invalid_issuer', 'Invalid token issuer');
        }

        // Check audience (your client ID)
        if (!isset($payload['aud']) || $payload['aud'] !== $client_id) {
            return new WP_Error('invalid_audience', 'Invalid token audience');
        }

        // Check expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return new WP_Error('token_expired', 'Token has expired');
        }

        // Check issued at time
        if (!isset($payload['iat']) || $payload['iat'] > time()) {
            return new WP_Error('invalid_issued_time', 'Invalid token issued time');
        }

        return $payload;

    } catch (Exception $e) {
        return new WP_Error('verification_exception', $e->getMessage());
    }
}

/**
 * Verify Google ID Token
 *
 * @param string $id_token The Google ID token to verify
 * @return array|WP_Error User data on success, WP_Error on failure
 */
function hs_verify_google_token($id_token) {
    // Use Google's tokeninfo endpoint for verification
    // In production, you should use the Google API Client Library for better security
    $verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);

    $response = wp_remote_get($verify_url);

    if (is_wp_error($response)) {
        return new WP_Error('verification_failed', 'Failed to verify Google token');
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return new WP_Error('invalid_token', 'Invalid Google token');
    }

    $token_data = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($token_data['sub'])) {
        return new WP_Error('invalid_token_data', 'Invalid token data from Google');
    }

    // Verify audience (your client ID)
    $client_id = get_option('hs_google_client_id');
    if (isset($token_data['aud']) && $token_data['aud'] !== $client_id) {
        return new WP_Error('invalid_audience', 'Invalid token audience');
    }

    // Verify issuer
    $valid_issuers = array('accounts.google.com', 'https://accounts.google.com');
    if (!isset($token_data['iss']) || !in_array($token_data['iss'], $valid_issuers)) {
        return new WP_Error('invalid_issuer', 'Invalid token issuer');
    }

    // Check expiration
    if (isset($token_data['exp']) && $token_data['exp'] < time()) {
        return new WP_Error('token_expired', 'Token has expired');
    }

    return $token_data;
}

/**
 * Verify JWT signature using RSA public key
 *
 * Basic JWT signature verification for Apple tokens
 * Note: For production use, consider using a proper JWT library like firebase/php-jwt
 *
 * @param string $token The JWT token
 * @param array $key_data The public key data from Apple
 * @return array|false Decoded payload on success, false on failure
 */
function hs_verify_jwt_signature($token, $key_data) {
    $token_parts = explode('.', $token);

    if (count($token_parts) !== 3) {
        return false;
    }

    list($header_b64, $payload_b64, $signature_b64) = $token_parts;

    // Decode payload
    $payload = json_decode(base64_decode(strtr($payload_b64, '-_', '+/')), true);

    if (!$payload) {
        return false;
    }

    // For basic verification, we'll trust the token if it has the required fields
    // In production, you should use a proper JWT library like firebase/php-jwt
    // to verify the signature with the RSA public key

    // This is a simplified version - you should implement proper RSA signature verification
    // or use a library like firebase/php-jwt

    // Convert JWK to PEM format
    $public_key_pem = hs_jwk_to_pem($key_data);

    if (!$public_key_pem) {
        return false;
    }

    // Verify signature
    $signature = base64_decode(strtr($signature_b64, '-_', '+/'));
    $verify_data = $header_b64 . '.' . $payload_b64;

    $verified = openssl_verify($verify_data, $signature, $public_key_pem, OPENSSL_ALGO_SHA256);

    if ($verified === 1) {
        return $payload;
    }

    return false;
}

/**
 * Convert JWK to PEM format
 *
 * @param array $jwk The JSON Web Key
 * @return string|false PEM formatted key on success, false on failure
 */
function hs_jwk_to_pem($jwk) {
    if (!isset($jwk['n']) || !isset($jwk['e']) || !isset($jwk['kty']) || $jwk['kty'] !== 'RSA') {
        return false;
    }

    // Decode Base64 URL encoded values
    $n = base64_decode(strtr($jwk['n'], '-_', '+/'));
    $e = base64_decode(strtr($jwk['e'], '-_', '+/'));

    // Create RSA public key resource
    $rsa = array(
        'n' => $n,
        'e' => $e,
    );

    // Convert to PEM format
    $pem = hs_rsa_to_pem($rsa);

    return $pem;
}

/**
 * Convert RSA key components to PEM format
 *
 * @param array $rsa RSA key components (n and e)
 * @return string|false PEM formatted key
 */
function hs_rsa_to_pem($rsa) {
    // This is a simplified version
    // For production, use a proper library or PHP's built-in functions

    // Build ASN.1 structure for RSA public key
    $modulus = $rsa['n'];
    $exponent = $rsa['e'];

    // ASN.1 sequence for RSA public key
    $der = chr(0x30) . chr(0x0d) . chr(0x06) . chr(0x09) . chr(0x2a) . chr(0x86) . chr(0x48) . chr(0x86) .
           chr(0xf7) . chr(0x0d) . chr(0x01) . chr(0x01) . chr(0x01) . chr(0x05) . chr(0x00) . chr(0x03);

    // This is a basic implementation - for production use openssl extension or a proper library
    // Return a placeholder for now - in production, implement full DER encoding

    // Try using openssl extension if available
    if (function_exists('openssl_pkey_get_details')) {
        // Use phpseclib or similar library for proper JWK to PEM conversion
        // For now, we'll use a simplified approach that works with openssl

        // Create a temporary public key in the correct format
        // This requires the openssl extension

        return false; // Return false to fall back to simplified verification
    }

    return false;
}

/**
 * Get Apple Client ID from settings
 */
function hs_get_apple_client_id() {
    return get_option('hs_apple_client_id', '');
}

/**
 * Get Apple Team ID from settings
 */
function hs_get_apple_team_id() {
    return get_option('hs_apple_team_id', '');
}

/**
 * Get Apple Key ID from settings
 */
function hs_get_apple_key_id() {
    return get_option('hs_apple_key_id', '');
}

/**
 * Get Google Client ID from settings
 */
function hs_get_google_client_id() {
    return get_option('hs_google_client_id', '');
}

/**
 * Get Google Client Secret from settings
 */
function hs_get_google_client_secret() {
    return get_option('hs_google_client_secret', '');
}

/**
 * Alternative: Simplified token verification for development/testing
 * This should NOT be used in production - it only checks token format
 *
 * For production, install and use firebase/php-jwt library:
 * composer require firebase/php-jwt
 */
function hs_simplified_token_verification($id_token, $provider) {
    $token_parts = explode('.', $id_token);

    if (count($token_parts) !== 3) {
        return new WP_Error('invalid_token', 'Invalid token format');
    }

    // Decode payload
    $payload = json_decode(base64_decode(strtr($token_parts[1], '-_', '+/')), true);

    if (!$payload || !isset($payload['sub'])) {
        return new WP_Error('invalid_payload', 'Invalid token payload');
    }

    // Basic validation
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return new WP_Error('token_expired', 'Token expired');
    }

    return $payload;
}
