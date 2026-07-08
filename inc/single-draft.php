<?php
/**
 * Allow single-object GraphQL queries to return draft/private content during a valid preview request.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Treat single posts/products as visible during a valid preview request.
 *
 * WPGraphQL loads single objects through a loader that bypasses pre_get_posts,
 * so we adjust the per-object visibility check here.
 *
 * @param bool $is_private Whether WPGraphQL considers the object private.
 * @param string $model_name The model name.
 * @param mixed $data The object.
 * @return bool
 */
function headless_preview_allow_single($is_private, $model_name, $data)
{

    if (!headless_preview_is_valid_request()) {
        return $is_private;
    }

    // Models we allow previewing (post, product, etc.).
    $allowed_models = ['PostObject', 'ProductObject'];

    if (!in_array($model_name, $allowed_models, true)) {
        return $is_private;
    }

    return false;
}

add_filter('graphql_data_is_private', 'headless_preview_allow_single', 10, 3);