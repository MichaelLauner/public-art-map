=== Public Art Map ===
Contributors: michaellauner
Tags: maps, public art, custom post type
Requires at least: 6.4
Tested up to: 6.9.4
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a custom post type and frontend templates for mapping public art locations.

== Description ==

Public Art Map adds the content types, metadata, and frontend templates needed to present a public art map on a WordPress site.

Features include:

* a `map_location` custom post type for public art entries
* artwork type and collection taxonomies for filtering and styling
* a fullscreen public art map page template
* optional Mapbox-powered geocoding for address-to-coordinate lookup
* an optional REST export feed for sharing map data with another site

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/public-art-map/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to `Settings > Public Art Map`.
4. Enter a Mapbox public access token.
5. Choose the page that should use the fullscreen map template.
6. Add and publish `Map Locations` content.

== Frequently Asked Questions ==

= Is Mapbox required? =

Yes. The interactive map and address geocoding features depend on a Mapbox public access token.

= What does the export feed do? =

When enabled, the plugin exposes a JSON feed at `wp-json/public-art-map/v1/export` containing published map locations, related taxonomy terms, and optional image URLs.

= What happens if I leave the export token blank? =

If the export feed is enabled and no token is configured, the feed is publicly accessible.

== Third-Party Services ==

This plugin integrates with Mapbox to render maps and geocode addresses.

It connects to:

* `https://api.mapbox.com/` for map tile/style requests in the browser
* `https://api.mapbox.com/geocoding/v5/mapbox.places/` for converting saved addresses into coordinates

When geocoding is used, address fields entered in WordPress are sent to Mapbox. When the frontend map is viewed, the visitor's browser connects to Mapbox to load the interactive map experience.

Mapbox terms of service: https://www.mapbox.com/legal/tos
Mapbox privacy policy: https://www.mapbox.com/legal/privacy

== Notes ==

The optional export feed is disabled by default. If you enable it, use an export token unless you intentionally want the feed to be public.

== Changelog ==

= 0.1.1 =
* Added an optional REST export feed for sharing map locations, taxonomies, and image URLs with another site.
* Hardened post and taxonomy save flows with nonce and capability checks.
* Bundled Mapbox GL assets locally instead of loading them from a public CDN.
* Added plugin metadata, translation support, and expanded repository documentation.
