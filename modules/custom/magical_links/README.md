# Magical Links

## Purpose
`magical_links` provides a custom link field widget and formatter that map links to taxonomy-driven icon definitions, with grouped icon UI in forms and grouped icon output in rendered content.

## What It Does
- Adds link widget plugin `magical_links` (extends core `LinkWidget`).
- Adds link formatter plugin `magical_links` (extends `FormatterBase`).
- Builds icon/prefix definitions from taxonomy terms (vocabulary `links`) via a service.
- Applies JS/CSS to transform field editing UI into icon-assisted link entry.
- Groups rendered links by taxonomy parent term and displays icon + label per link.

## Dependencies
- Core/Contrib: `drupal:link`, `drupal:field`
- Runtime service dependencies:
  - `entity_type.manager`
  - `file_url_generator`
  - `cache.default`
  - `language_manager`

## Files
- `src/Plugin/Field/FieldWidget/MagicalLinksWidget.php`
  Custom widget; renders icon chooser markup and applies widget library.
- `src/Plugin/Field/FieldFormatter/MagicalLinksFormatter.php`
  Custom formatter; groups/matches links and renders iconized link output.
- `src/Service/MagicalLinksDefinitionRepository.php`
  Taxonomy/media/file-backed icon definition repository with cache metadata.
- `js/magical_links_widget.js`
  Client behavior for icon selection, prefill, row add/remove handling, and AJAX rebind.
- `css/magical_links_widget.css`
  Editor-side layout/styling for icon sidebar and link rows.
- `css/magical_links_formatter.css`
  Frontend display styling for grouped links.
- `magical_links.services.yml`
  Service definitions.
- `magical_links.module`
  Module hooks file.

## Runtime Behavior
1. Widget build calls repository definitions and renders icon groups above the URI input.
2. JS behavior rewires the field UI into a sidebar icon selector + transformed link rows.
3. Clicking an icon prefills URL prefix and title, or triggers Add-more flow if current row is dirty.
4. Formatter resolves definitions again, matches each link by URL prefix/title, groups by taxonomy parent, and renders grouped link output.
5. Cache metadata from definitions is applied to widget/formatter render arrays.

## Data Model Expectations
- Vocabulary: `links`
- Term fields:
  - icon field (default `field_icons`) supporting file/media image sources
  - prefix field (default `field_prefill`)
- Optional legacy fallback fields for prefix resolution:
  `field_link`, `field_url`, `field_link_prefix`, `field_prefix`

## Concerns
- Medium (stability): widget JS depends on internal AJAX structures (`Drupal.ajax.instances` and `eventResponse`) and heavy DOM mutation logic; core/contrib markup or AJAX changes can break behavior.
- Medium (scope side effects): widget CSS contains broad selectors like `[data-drupal-selector$="-uri--description"]` and `[id$="-uri--description"]`, which can hide descriptions outside this widget context.
- Medium (maintainability): both widget/formatter fetch services via global `\Drupal::service()` and formatter uses `\Drupal::token()` directly, reducing testability and DI clarity.
- Low-Medium (behavior coupling): formatter CSS targets `.field--name-field-links` directly, coupling presentation to one field machine name.
- Low (testing): no automated tests were found for taxonomy definition resolution, widget JS interactions, formatter matching/grouping, or cache invalidation behavior.

## Maintenance Notes
- Narrow CSS selectors so style/hiding logic is scoped under `.magical-links-widget`.
- Add tests for:
  - definition repository caching + tag/context invalidation
  - formatter matching by prefix/title and group ordering
  - widget AJAX add/remove interactions and icon prefill behavior
