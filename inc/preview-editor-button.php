<?php
/**
 * Loads the editor script that repoints Gutenberg's "Preview in new tab"
 * button to the headless frontend, and passes the frontend URL + secret to it.
 *
 * @package HeadlessPreviewGraphQL
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_enqueue_scripts', function ($hook) {
    // Need both the frontend URL and the secret.
    if (!defined('HEADLESS_PREVIEW_FRONTEND_URL') || !defined('HEADLESS_PREVIEW_SECRET')) {
        return;
    }

    // Only load on the post/product edit screens.
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }

    // Register + enqueue the script.
    wp_enqueue_script(
        'headless-preview-editor-button',
        plugin_dir_url(__DIR__) . 'assets/js/preview-editor-button.js',
        ['wp-data'],
        '1.0.0',
        true
    );

    // Pass PHP values to the script safely.
    wp_localize_script(
        'headless-preview-editor-button',
        'HeadlessPreviewData',
        [
            'frontendUrl' => HEADLESS_PREVIEW_FRONTEND_URL,
            'secret' => HEADLESS_PREVIEW_SECRET,
        ]
    );
});
