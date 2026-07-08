<?php
/**
 * Preview secret validation.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check whether the current request is a valid GraphQL preview request.
 *
 * The frontend sends the secret in an HTTP header: X-Preview-Secret
 *
 * @return bool True if this is an authorized GraphQL preview request.
 */
function headless_preview_is_valid_request()
{
//    error_log( 'PREVIEW HEADER: ' . ( $_SERVER['HTTP_X_PREVIEW_SECRET'] ?? 'NOT RECEIVED' ) );
//    error_log( 'ALL HEADERS: ' . print_r( $_SERVER, true ) );
    // Only act on GraphQL requests; ignore normal WordPress queries.
    if (!function_exists('is_graphql_request') || !is_graphql_request()) {
        return false;
    }

    // The secret must be defined in wp-config.php.
    if (!defined('HEADLESS_PREVIEW_SECRET') || empty(HEADLESS_PREVIEW_SECRET)) {
        return false;
    }

    // Read the secret sent by the frontend.
    $sent_secret = isset($_SERVER['HTTP_X_PREVIEW_SECRET'])
        ? trim(wp_unslash($_SERVER['HTTP_X_PREVIEW_SECRET']))
        : '';

    if ($sent_secret === '') {
        return false;
    }

    // Compare safely (timing-attack resistant).
    return hash_equals(HEADLESS_PREVIEW_SECRET, $sent_secret);
}
