<?php
/**
 * Include drafts in GraphQL list/connection queries during a valid preview request.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Allow draft/pending/private content in list queries during a valid preview request.
 *
 * @param WP_Query $query The query being prepared.
 * @return void
 */
function headless_preview_include_drafts($query)
{

    if (!headless_preview_is_valid_request()) {
        return;
    }

    $query->set(
        'post_status',
        array('publish', 'draft', 'pending', 'future', 'private')
    );
}

add_action('pre_get_posts', 'headless_preview_include_drafts');
