<?php
/**
 * Preview Product resolver.
 *
 * Registers a custom "previewProduct" GraphQL field that returns a single
 * product by its database ID, including drafts.
 *
 * Why this exists: WooGraphQL's default product loader (WC_CPT_Loader) only
 * resolves published products and throws when handed a draft. Instead of
 * fighting that loader, this file bypasses it entirely by building a dedicated
 * output type and reading the product data straight from WordPress/WooCommerce.
 *
 * Access is gated by headless_preview_is_valid_request(), so only authorized
 * preview requests can read unpublished data.
 *
 * @package HeadlessPreviewGraphQL
 */

// Prevent direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

add_action('graphql_register_types', function () {

    /* ---------------------------------------------------------------------
     * Image types
     * ------------------------------------------------------------------ */

    // A single image: source URL and alt text.
    register_graphql_object_type('PreviewImage', [
        'description' => 'An image for preview (URL and alt text).',
        'fields' => [
            'url' => ['type' => 'String'],
            'alt' => ['type' => 'String'],
        ],
    ]);

    // Connection wrapper so galleries mirror the standard { nodes { ... } } shape.
    register_graphql_object_type('PreviewImageConnection', [
        'description' => 'A gallery connection wrapping image nodes.',
        'fields' => [
            'nodes' => ['type' => ['list_of' => 'PreviewImage']],
        ],
    ]);

    /* ---------------------------------------------------------------------
     * Attribute types
     * ------------------------------------------------------------------ */

    // A single attribute: human-readable label plus its selected options.
    register_graphql_object_type('PreviewAttribute', [
        'description' => 'A single product attribute (label + options).',
        'fields' => [
            'label' => ['type' => 'String'],
            'options' => ['type' => ['list_of' => 'String']],
        ],
    ]);

    // Connection wrapper for attributes.
    register_graphql_object_type('PreviewAttributeConnection', [
        'description' => 'An attributes connection wrapping attribute nodes.',
        'fields' => [
            'nodes' => ['type' => ['list_of' => 'PreviewAttribute']],
        ],
    ]);

    /* ---------------------------------------------------------------------
     * Category types
     *
     * Mirrors the nested shape used on the real product page:
     * categories { nodes { name slug parent { node { name slug } } } }
     * ------------------------------------------------------------------ */

    // The inner parent node (name + slug of the parent category).
    register_graphql_object_type('PreviewCategoryParentNode', [
        'description' => 'The parent category node.',
        'fields' => [
            'name' => ['type' => 'String'],
            'slug' => ['type' => 'String'],
        ],
    ]);

    // Wrapper that holds the parent node (matches the "parent { node }" shape).
    register_graphql_object_type('PreviewCategoryParent', [
        'description' => 'Wrapper holding the parent category node.',
        'fields' => [
            'node' => ['type' => 'PreviewCategoryParentNode'],
        ],
    ]);

    // A single category, with an optional parent.
    register_graphql_object_type('PreviewCategory', [
        'description' => 'A single product category (with optional parent).',
        'fields' => [
            'name' => ['type' => 'String'],
            'slug' => ['type' => 'String'],
            'parent' => ['type' => 'PreviewCategoryParent'],
        ],
    ]);

    // Connection wrapper for categories.
    register_graphql_object_type('PreviewCategoryConnection', [
        'description' => 'A categories connection wrapping category nodes.',
        'fields' => [
            'nodes' => ['type' => ['list_of' => 'PreviewCategory']],
        ],
    ]);

    /* ---------------------------------------------------------------------
     * Root preview product type
     * ------------------------------------------------------------------ */

    // The dedicated output type for preview, assembled manually (not the
    // WooGraphQL Product model), so drafts can be returned safely.
    register_graphql_object_type('PreviewProduct', [
        'description' => 'A product returned for headless preview (may be a draft).',
        'fields' => [
            'id' => ['type' => 'Int'],
            'slug' => ['type' => 'String'],
            'name' => ['type' => 'String'],
            'sku' => ['type' => 'String'],
            'status' => ['type' => 'String'],
            'description' => ['type' => 'String'],
            'image' => ['type' => 'PreviewImage'],
            'galleryImages' => ['type' => 'PreviewImageConnection'],
            'attributes' => ['type' => 'PreviewAttributeConnection'],
            'productCategories' => ['type' => 'PreviewCategoryConnection'],
        ],
    ]);

    /* ---------------------------------------------------------------------
     * Root query field + resolver
     * ------------------------------------------------------------------ */

    register_graphql_field('RootQuery', 'previewProduct', [
        'type' => 'PreviewProduct',
        'description' => 'Fetch a single product by database ID, including drafts, for headless preview.',
        'args' => [
            'id' => ['type' => ['non_null' => 'Int']],
        ],
        'resolve' => function ($root, $args) {

            // Security gate: only valid preview requests may read drafts.
            if (!headless_preview_is_valid_request()) {
                return null;
            }

            // Sanitize the incoming ID (external input).
            $product_id = absint($args['id']);

            // Only resolve actual products.
            if (get_post_type($product_id) !== 'product') {
                return null;
            }

            $post = get_post($product_id);

            /* -----------------------------------------------------------
             * Featured image (URL + alt)
             * -------------------------------------------------------- */
            $image = null;
            $thumb_id = get_post_thumbnail_id($product_id);

            if ($thumb_id) {
                $image = [
                    'url' => wp_get_attachment_image_url($thumb_id, 'full'),
                    'alt' => get_post_meta($thumb_id, '_wp_attachment_image_alt', true),
                ];
            }

            /* -----------------------------------------------------------
             * Gallery images
             *
             * Gallery IDs are stored as a comma-separated string in the
             * "_product_image_gallery" post meta (e.g. "105,106,107").
             * -------------------------------------------------------- */
            $gallery = [];
            $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);

            if (!empty($gallery_ids)) {
                foreach (explode(',', $gallery_ids) as $gid) {
                    $gid = absint($gid);

                    // Skip anything that isn't a valid ID.
                    if (!$gid) {
                        continue;
                    }

                    $gallery[] = [
                        'url' => wp_get_attachment_image_url($gid, 'full'),
                        'alt' => get_post_meta($gid, '_wp_attachment_image_alt', true),
                    ];
                }
            }

            /* -----------------------------------------------------------
             * Attributes (label + options)
             *
             * Uses the WooCommerce product object, which (unlike the
             * WooGraphQL loader) resolves drafts fine. Handles both global
             * taxonomy attributes (pa_color, pa_size...) and custom ones.
             * -------------------------------------------------------- */
            $attributes = [];
            $wc_product = wc_get_product($product_id);

            if ($wc_product) {
                foreach ($wc_product->get_attributes() as $attribute) {

                    // Guard: get_attributes() may return non-attribute values.
                    if (!$attribute instanceof WC_Product_Attribute) {
                        continue;
                    }

                    if ($attribute->is_taxonomy()) {
                        // Global attribute: pull term names from the taxonomy.
                        $terms = wp_get_post_terms($product_id, $attribute->get_name());
                        $label = wc_attribute_label($attribute->get_name());
                        $options = is_wp_error($terms) ? [] : wp_list_pluck($terms, 'name');
                    } else {
                        // Custom attribute: options are stored on the attribute itself.
                        $label = $attribute->get_name();
                        $options = $attribute->get_options();
                    }

                    $attributes[] = [
                        'label' => $label,
                        'options' => $options,
                    ];
                }
            }

            /* -----------------------------------------------------------
             * Product categories (with optional parent)
             * -------------------------------------------------------- */
            $categories = [];
            $terms = get_the_terms($product_id, 'product_cat');

            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {

                    // Resolve the parent category, if any.
                    $parent = null;
                    if ($term->parent) {
                        $parent_term = get_term($term->parent, 'product_cat');
                        if ($parent_term && !is_wp_error($parent_term)) {
                            $parent = [
                                'node' => [
                                    'name' => $parent_term->name,
                                    'slug' => $parent_term->slug,
                                ],
                            ];
                        }
                    }

                    $categories[] = [
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'parent' => $parent,
                    ];
                }
            }

            /* -----------------------------------------------------------
             * Assemble the response.
             *
             * Connections are wrapped in { nodes => [...] } to match the
             * shape the frontend already uses for the real product page.
             * -------------------------------------------------------- */
            return [
                'id' => $post->ID,
                'slug' => $post->post_name ?: sanitize_title($post->post_title),
                'name' => $post->post_title,
                'sku' => get_post_meta($post->ID, '_sku', true),
                'status' => $post->post_status,
                'description' => $post->post_content,
                'image' => $image,
                'galleryImages' => ['nodes' => $gallery],
                'attributes' => ['nodes' => $attributes],
                'productCategories' => ['nodes' => $categories],
            ];
        },
    ]);
});