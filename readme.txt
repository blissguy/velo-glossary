=== Velo Glossary ===
Contributors: mixbusmarketing
Tags: glossary
Requires at least: 6.9
Requires PHP: 8.0
Tested up to: 7.0
Stable tag: 1.12.0-beta.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides configurable pop-up tooltip definitions of acronyms and terms defined in a glossary.

== Description ==

Velo Glossary is maintained by MixBus Marketing at https://mixbusmarketing.com. It registers a `glossary` custom post type for defining words, acronyms, or terms. Matching terms are highlighted in enabled frontend content, with each definition displayed in a popup tooltip.

Settings are available under **Glossary > Settings**. By default, Velo Glossary keeps the original plugin behavior: enabled public content post types, archive/list views, and comments are processed. Individual pages, posts, and supported custom post types can be excluded from their editor sidebar. Term matching can also be excluded inside specific HTML tags (headings h1-h6 by default) and inside elements carrying configured class names.

Frontend URL settings control whether glossary entries have public single pages and whether glossary tag archives resolve publicly. These URLs are disabled by default while keeping glossary entries and glossary tags available for admin screens, REST, builders, and custom queries.

Glossary entries can also be associated with specific posts, pages, or public custom post type entries. These associations are stored as repeatable `_velo_glossary_associated_post_id` post meta for normal `WP_Query` usage. Settings can optionally limit frontend matching to associated content, with a fallback option for unassociated terms.

Glossary entries support glossary-only tags through the `velo_glossary_tag` taxonomy. Entries can also be connected to related glossary terms with repeatable `_velo_glossary_related_term_id` post meta stored bidirectionally for normal `WP_Query` usage.

CSV tools are available under Glossary > Settings. Administrators can download a sample template, export current glossary entries, upload a CSV for preview, and confirm an import that creates or updates terms by exact title.

Velo Glossary requires WordPress 6.9 or newer and PHP 8.0 or newer. It registers Abilities API tools for agent-friendly glossary term listing, retrieval, creation, updating, and trashing. CSV import/export remains admin UI only.

== Installation ==

To use the plugin, install and activate it, then visit the Glossary section in your wp-admin dashboard to begin adding glossary terms. Use Glossary > Settings to adjust where glossary terms are processed.

== Screenshots ==

1. An example showing a tooltip with a definition for the term "meta".

== Changelog ==

= 1.12.0-beta.1 =
* New: a "Highlight in lessons" switch on each term. A term switched off stays in the glossary but isn't marked in lesson text, on the site or in the app.

= 1.11.0 =
* Terms that arrive after the page loads now get a hovercard. The script ran once and never looked again, so a term in a Q&A answer, a notification or anything else rendered by script was underlined with nothing behind it. Added nodes are watched, and window.veloGlossary.initHovercards( element ) wires a subtree on demand for scripts that would rather not wait.
* Only one definition shows at a time again, including cards opened in quick succession.
* Dropped the jQuery and hoverintent dependencies. Cards open on mouseenter with no delay, so hoverintent only ever reopened a card that was already up.

= 1.10.3 =
* Fixed hovercards that never appeared, or appeared far from the word. Tippy puts an interactive card inside the term's own parent element unless told otherwise, so a card opened from a collapsed menu or a narrow inline wrapper inherited that element's clipping and width. On one course page ten of twenty cards were clipped to nothing and one was squeezed into a sliver rendered hundreds of pixels above its term. Cards now attach to the page body.
* Terms sharing a line with a later ">" are linked again. The matching pattern only ever sees text, never markup, but still carried a guard against matching inside a tag. "Wide -> Telephoto" linked neither term.
* Names of three characters or fewer, such as ISO and RAW, now only match their own casing. Lowercase "iso" used to match, fail the case check and link nothing, which also blocked nothing else on the line.
* Stopped linking terms inside button, nav, aside, header and footer. Menu labels and other chrome repeat on every page, and a term spent there is a term the reading cannot use.
* Hovercard markup left behind by opening the builder on an already rendered page is now unwrapped rather than treated as a linked term, so the word links normally instead of showing an empty card.

= 1.10.2 =
* Fixed terms being linked inside script, style and textarea. Since 1.10.0 the Bricks path scans a whole rendered element in one string, so inline scripts and styles inside it were scanned too. Linking a term in a JSON script breaks it for whatever reads it, and linking one inside a CSS rule breaks the declaration.
* Because a term is linked only once per page, terms spent inside that markup never reached the visible text. They do now.

= 1.10.1 =
* Fixed excluded class names having no effect in Bricks. A wrapper's class only excludes the text inside it when both are read together, and 1.10.0 read each text element on its own, so a class on a surrounding element never applied. Tab labels and before/after labels kept getting linked whatever was excluded.
* Glossary terms are now matched across a whole outermost Bricks element rather than element by element, which is how excluded tags and excluded class names already behaved in regular post content.
* Stopped linking terms in Bricks header and footer templates, so navigation and other per-page chrome is left alone.
* Replaced the velo_glossary_bricks_elements filter with velo_glossary_bricks_process_element, which decides per element rather than by element name.

= 1.10.0 =
* Added glossary term linking inside Bricks text elements. Bricks renders its own markup and only its Post Content element runs the_content, so terms written directly into a text element were never linked and no hovercard appeared. Scoped to the text and text-basic elements, filterable through velo_glossary_bricks_elements.
* Linked each term once per page rather than once per element, so a term repeated across several Bricks elements is only highlighted on its first occurrence.
* Skipped term linking inside the Bricks builder. The builder saves the markup it renders, and the frontend script removes the definition from the page on load, so a saved copy produced an empty hovercard.
* Moved the settings page from Settings > Velo Glossary to Glossary > Settings, alongside the glossary entries it configures. Saved settings are unchanged.

= 1.9.4 =
* Restored the classic editor for glossary entries so the Alternate Names, Associated Content, and Related Terms metaboxes render below the title as before 1.9.0, while keeping entries available through REST for builders.
* Excluded glossary entries from frontend search results when entry single pages are disabled, preventing search results that link to 404 pages.

= 1.9.3 =
* Re-enabled the block editor for glossary entries and marked the Alternate Names, Associated Content, and Related Terms metaboxes as block-editor compatible.

= 1.9.2 =
* Restored the classic glossary editor screen so Alternate Names, Associated Content, and Related Terms metaboxes remain visible while keeping glossary entries available through REST.

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
