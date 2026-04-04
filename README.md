# public-art-map

A WordPress plugin for adding an interactive map for communities.

## GitHub updates

This plugin now includes GitHub-based update checks using `plugin-update-checker`.

To publish an update that appears in WordPress:

1. Bump the version in `public-art-map.php` and `readme.txt`.
2. Create a Git tag or GitHub release for that version.
3. Prefer attaching a release ZIP asset named like `public-art-map.zip` or `public-art-map-0.1.2.zip`.
4. Have the client click the normal update button in WordPress.

## Data export feed

This plugin can also expose a JSON feed of map locations for reuse on another site.

1. In WordPress admin, go to `Settings > Public Art Map`.
2. Enable the export feed.
3. Optionally set an export token.
4. Fetch `wp-json/public-art-map/v1/export` from the source site.

The feed includes:

- published map locations
- Public Art Map meta fields
- artwork type and collection term references
- featured image and gallery image URLs
- artwork type color/icon metadata
