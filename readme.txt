=== Velo Glossary ===
Contributors: mixbusmarketing
Tags: glossary
Requires at least: 5.3.1
Tested up to: 7.0
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides configurable pop-up tooltip definitions of acronyms and terms defined in a glossary.

== Description ==

Velo Glossary is maintained by MixBus Marketing at https://mixbusmarketing.com. It registers a `glossary` custom post type for defining words, acronyms, or terms. Matching terms are highlighted in enabled frontend content, with each definition displayed in a popup tooltip.

Settings are available under **Settings > Velo Glossary**. By default, Velo Glossary keeps the original plugin behavior: enabled public content post types, archive/list views, and comments are processed. Individual pages, posts, and supported custom post types can be excluded from their editor sidebar.

Glossary entries can also be associated with specific posts, pages, or public custom post type entries. These associations are stored as repeatable `_velo_glossary_associated_post_id` post meta for normal `WP_Query` usage. Settings can optionally limit frontend matching to associated content, with a fallback option for unassociated terms.

Glossary entries support glossary-only tags through the `velo_glossary_tag` taxonomy. Entries can also be connected to related glossary terms with repeatable `_velo_glossary_related_term_id` post meta stored bidirectionally for normal `WP_Query` usage.

CSV tools are available under Settings > Velo Glossary. Administrators can download a sample template, export current glossary entries, upload a CSV for preview, and confirm an import that creates or updates terms by exact title.

== Installation ==

To use the plugin, install and activate it, then visit the Glossary section in your wp-admin dashboard to begin adding glossary terms. Use Settings > Velo Glossary to adjust where glossary terms are processed.

== Screenshots ==

1. An example showing a tooltip with a definition for the term "meta".

== Changelog ==

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
