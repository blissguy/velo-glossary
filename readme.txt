=== Velo Glossary ===
Contributors: mixbusmarketing
Tags: glossary
Requires at least: 6.9
Requires PHP: 8.0
Tested up to: 7.0
Stable tag: 1.9.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides configurable pop-up tooltip definitions of acronyms and terms defined in a glossary.

== Description ==

Velo Glossary is maintained by MixBus Marketing at https://mixbusmarketing.com. It registers a `glossary` custom post type for defining words, acronyms, or terms. Matching terms are highlighted in enabled frontend content, with each definition displayed in a popup tooltip.

Settings are available under **Settings > Velo Glossary**. By default, Velo Glossary keeps the original plugin behavior: enabled public content post types, archive/list views, and comments are processed. Individual pages, posts, and supported custom post types can be excluded from their editor sidebar. Term matching can also be excluded inside specific HTML tags (headings h1-h6 by default) and inside elements carrying configured class names.

Frontend URL settings control whether glossary entries have public single pages and whether glossary tag archives resolve publicly. These URLs are disabled by default while keeping glossary entries and glossary tags available for admin screens, REST, builders, and custom queries.

Glossary entries can also be associated with specific posts, pages, or public custom post type entries. These associations are stored as repeatable `_velo_glossary_associated_post_id` post meta for normal `WP_Query` usage. Settings can optionally limit frontend matching to associated content, with a fallback option for unassociated terms.

Glossary entries support glossary-only tags through the `velo_glossary_tag` taxonomy. Entries can also be connected to related glossary terms with repeatable `_velo_glossary_related_term_id` post meta stored bidirectionally for normal `WP_Query` usage.

CSV tools are available under Settings > Velo Glossary. Administrators can download a sample template, export current glossary entries, upload a CSV for preview, and confirm an import that creates or updates terms by exact title.

Velo Glossary requires WordPress 6.9 or newer and PHP 8.0 or newer. It registers Abilities API tools for agent-friendly glossary term listing, retrieval, creation, updating, and trashing. CSV import/export remains admin UI only.

== Installation ==

To use the plugin, install and activate it, then visit the Glossary section in your wp-admin dashboard to begin adding glossary terms. Use Settings > Velo Glossary to adjust where glossary terms are processed.

== Screenshots ==

1. An example showing a tooltip with a definition for the term "meta".

== Changelog ==

= 1.9.1 =
* Improved GitHub release notes so the WordPress plugin details modal has clean changelog formatting even when it falls back to release metadata.

= 1.9.0 =
* Added Frontend URLs settings for enabling glossary entry single pages and glossary tag archives only when needed.
* Disabled glossary entry single pages and glossary tag archives by default while keeping entries and tags queryable for builders and custom code.
* Registered glossary post types and taxonomies earlier so builder taxonomy selectors can discover Glossary Tags.

= 1.8.0 =
* Added Loading Rules settings to exclude HTML tags and class names from glossary term matching. Headings (h1-h6) are excluded by default.
* Fixed term matching not resuming after an already-linked glossary term when content filters run over previously processed content.

= 1.7.1 =
* Changed the glossary post type singular label to "Glossary Entry" so it is clearly identifiable in Bricks query loop and other builder post type dropdowns.

= 1.7.0 =
* Raised minimum support to WordPress 6.9 and PHP 8.0.
* Added WordPress Abilities API tools for listing, reading, saving, and trashing glossary terms.
* Fixed CSV downloads on modern PHP by explicitly passing the fputcsv escape parameter.
* Exported raw stored glossary titles and definitions instead of display-filtered values.

= 1.6.0 =
* Added CSV sample download, full export, preview import, and confirmed import tools under Settings > Velo Glossary.
* Imports create or update glossary terms by exact title and report ignored columns plus invalid relationship IDs.
* CSV relationship fields support numeric associated content IDs and bidirectional related term IDs.

= 1.5.3 =
* Fixed hovercard skip detection when rendered glossary wrappers include multiple classes.

= 1.5.2 =
* Hardened glossary relationship meta so generic REST meta updates cannot bypass per-target validation.

= 1.5.1 =
* Added GitHub release update checks for non-WordPress.org installs.

= 1.5.0 =
* Added glossary-only tags for organizing glossary entries.
* Added bidirectional related glossary terms for builder-friendly queries.

= 1.4.1 =
* Scoped tooltip styling to the Velo Glossary Tippy theme and BEM classes.

= 1.4.0 =
* Added queryable associated content relationships for glossary entries.
* Added settings to limit visible terms to associated content and optionally keep unassociated terms global.
* Added an admin search selector for associating glossary terms with posts, pages, and public custom post types.

= 1.3.0 =
* Rebranded the maintained fork as Velo Glossary by MixBus Marketing.
* Added Settings > Velo Glossary loading rules for post types, archive/list views, and comments.
* Added a per-content opt-out metabox.
* Renamed internal classes, asset handles, cache keys, package metadata, and text domain to Velo Glossary naming.

= 1.2.1-fork.1 =
* Forked the plugin locally.
* Updated tooltip dependencies to Tippy.js 6.3.7 and @popperjs/core 2.11.8.
* Updated hovercard styles and initialization for the current Tippy.js API.

= 1.2 =
* Bump Tested up to.
* Don't create glossary hovercards for phrases within `<option>` tags.
* Don't create glossary hovercards wtihin oEmbed responses.
* PHP Notice fixes.
* Fixes to ensure that the matched text isn't past the current element.
* PHP 8 fatal fix, `array_merge() does not accept unknown named parameters`.

= 1.1 =
* Added hoverIntent to avoid accidental showing of the glossary items when scrolling the page.

= 1.0 =
* Switch to a different tooltip library to avoid some glitchy behaviour.
* Eliminate some unnecessary highlighting, and generally improve the accuracy of term matching.
* Fix some visual glitches.
* Cooperate better with O2 auto-linking and tagging.

= 0.1 =
* Initial public release.
