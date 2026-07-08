<?php
/**
 * Redirects the WordPress admin "Preview" action to the headless frontend.
 *
 * When an admin previews a draft, WordPress normally shows its own theme.
 * This sends them to the Nuxt/Next frontend instead, passing the post ID,
 * type, and the shared preview secret so the frontend can fetch the draft.
 *
 * @package HeadlessPreviewGraphQL
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('preview_post_link', function ($link, $post) {

    // Need both the frontend URL and the secret defined in wp-config.
    if (!defined('HEADLESS_PREVIEW_FRONTEND_URL') || !defined('HEADLESS_PREVIEW_SECRET')) {
        return $link;
    }

    if (!$post) {
        return $link;
    }

    // Build the frontend preview URL with the data it needs.
    return add_query_arg(
        [
            'secret' => HEADLESS_PREVIEW_SECRET,
            'id' => $post->ID,
            'type' => $post->post_type,
        ],
        HEADLESS_PREVIEW_FRONTEND_URL . '/api/preview'
    );
}, 999, 2);
