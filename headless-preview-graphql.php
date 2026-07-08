<?php
/**
 * Plugin Name:  headless-preview-graphql
 * Description:  Allows a headless frontend to preview draft content through WPGraphQL using a shared secret.
 * Version:      1.0.0
 * Author: Hossein Abedi
 *
 * @package HeadlessPreviewGraphQL
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/inc/secret-check.php';
require_once __DIR__ . '/inc/list-drafts.php';
require_once __DIR__ . '/inc/single-draft.php';
require_once __DIR__ . '/inc/preview-product.php';
require_once __DIR__ . '/inc/preview-redirect.php';
require_once __DIR__ . '/inc/preview-editor-button.php';