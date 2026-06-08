# Velo Glossary

Velo Glossary is a [MixBus Marketing](https://mixbusmarketing.com)-maintained fork of WordPress.org Glossary. It keeps the existing `glossary` post type and hovercard markup while updating tooltip dependencies and adding settings for where glossary terms should be processed.

## Loading Rules

Go to **Settings > Velo Glossary** to choose which public post types should use glossary hovercards. Archive/list views and comments are enabled by default to preserve the original plugin behavior.

Each supported content editor also gets a **Velo Glossary** side metabox. Check **Disable Velo Glossary on this content** to exclude one page, post, or custom post from glossary processing and hovercard asset loading.

## Term Associations

Glossary entries include an **Associated Content** metabox. Use it to connect a term to related posts, pages, or public custom post type entries. Associations are stored as repeatable `_velo_glossary_associated_post_id` post meta on the glossary entry, so builders and custom code can query them with a normal `WP_Query` meta query.

The **Term Associations** settings are display rules only. By default, associations are saved but glossary matching remains global. Enable **Only show glossary terms on content they are associated with** to restrict matches to the current content item. Enable the fallback setting if terms with no associations should continue to appear everywhere while restriction mode is active.

## Tags and Related Terms

Glossary entries support a dedicated `velo_glossary_tag` taxonomy named **Glossary Tags**. This keeps glossary organization separate from normal WordPress post tags while still supporting standard taxonomy queries.

Glossary entries can also be connected to other glossary entries through the **Related Terms** metabox. Related terms are stored as repeatable `_velo_glossary_related_term_id` post meta rows and mirrored both ways, so a relationship between term A and term B can be queried from either term.

## Dependencies

The plugin ships browser-ready assets from:

- `tippy.js` 6.3.7
- `@popperjs/core` 2.11.8

## Releases

GitHub releases are created automatically when a new version is pushed to `main`. Before pushing a release commit, make sure the `Version` header in `velo-glossary.php` and the `package.json` version match.

If release `vX.Y.Z` does not already exist, the workflow creates the tag, builds a WordPress-installable `velo-glossary-X.Y.Z.zip` file with the `velo-glossary/` plugin folder inside, and attaches it to the GitHub release.
