# Velo Glossary

Velo Glossary is a MixBus Marketing-maintained fork of WordPress.org Glossary. It keeps the existing `glossary` post type and hovercard markup while updating tooltip dependencies and adding settings for where glossary terms should be processed.

## Loading Rules

Go to **Settings > Velo Glossary** to choose which public post types should use glossary hovercards. Archive/list views and comments are enabled by default to preserve the original plugin behavior.

Each supported content editor also gets a **Velo Glossary** side metabox. Check **Disable Velo Glossary on this content** to exclude one page, post, or custom post from glossary processing and hovercard asset loading.

## Term Associations

Glossary entries include an **Associated Content** metabox. Use it to connect a term to related posts, pages, or public custom post type entries. Associations are stored as repeatable `_velo_glossary_associated_post_id` post meta on the glossary entry, so builders and custom code can query them with a normal `WP_Query` meta query.

The **Term Associations** settings are display rules only. By default, associations are saved but glossary matching remains global. Enable **Only show glossary terms on content they are associated with** to restrict matches to the current content item. Enable the fallback setting if terms with no associations should continue to appear everywhere while restriction mode is active.

## Dependencies

The plugin ships browser-ready assets from:

- `tippy.js` 6.3.7
- `@popperjs/core` 2.11.8
