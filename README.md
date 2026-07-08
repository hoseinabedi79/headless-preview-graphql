# headless-preview-graphql

A reusable WordPress plugin that lets a headless frontend preview draft content through WPGraphQL using a shared secret. It works for posts, pages, and any standard post type, and includes a dedicated resolver to preview draft WooCommerce products (bypassing WooGraphQL's publish-only loader).

## How it works

Draft content is private, so WPGraphQL won't return it to unauthenticated requests. This plugin allows a draft to be returned **only** when the request carries a valid preview secret in the `X-Preview-Secret` header. The secret protects the endpoint so only your own frontend can request unpublished content.

For standard post types (post, page, etc.), it opens draft visibility on both list and single queries. For WooCommerce products, it registers a separate `previewProduct` field that reads product data directly, because WooGraphQL's default loader only resolves published products.

## Requirements

- WordPress with [WPGraphQL](https://www.wpgraphql.com/)
- [WPGraphQL for WooCommerce (WooGraphQL)](https://github.com/wp-graphql/wp-graphql-woocommerce) — only if you need product preview

## Setup

Add the following two constants to your `wp-config.php` (never commit these):

```php
define( 'HEADLESS_PREVIEW_SECRET', 'your-long-random-secret-here' );
define( 'HEADLESS_PREVIEW_FRONTEND_URL', 'https://your-frontend-url.com' );
```

- `HEADLESS_PREVIEW_SECRET` — a long random string used to authorize preview requests.
- `HEADLESS_PREVIEW_FRONTEND_URL` — the base URL of your headless frontend (e.g. `http://localhost:3000` during development).

Then activate the plugin from the WordPress admin.

## Usage

### Frontend requests

Send the secret in the request header when querying draft content:

### Previewing a draft product

```graphql
query PreviewDraftProduct($id: Int!) {
  previewProduct(id: $id) {
    id
    slug
    name
    status
    description
    image { url alt }
    galleryImages { nodes { url alt } }
    attributes { nodes { label options } }
    productCategories {
      nodes {
        name
        slug
        parent { node { name slug } }
      }
    }
  }
}
```

### Previewing a draft post or page

Use the standard WPGraphQL query with the secret header:

```graphql
query PreviewPage($id: ID!) {
  page(id: $id, idType: DATABASE_ID) {
    databaseId
    title
    status
  }
}
```

### Preview button

When set up, the WordPress admin "Preview" button redirects to your frontend at `/api/preview` with the post ID, type, and secret as query parameters. Your frontend is responsible for handling that route.

## Security notes

- Keep the secret only in `wp-config.php`. Never hardcode it in plugin files or commit it.
- Use HTTPS in production so the secret and draft data are never sent in plaintext.
- The secret protects the endpoint but is not per-user authentication; anyone with the secret can read drafts.
