# Nomads Hero Gallery

## Purpose
`nomads_hero_gallery` provides a media-reference field formatter for rendering image-based hero galleries with position-specific image styles.

## What It Does
- Adds formatter plugin `nomads_hero_gallery_media` for `entity_reference` fields targeting media.
- Renders referenced media image source fields with configurable styles for:
  - first image
  - second image
  - remaining images
- Supports optional image count limiting via `max_images`.
- Applies dedicated container/grid markup when `max_images` is set to `7`.
- Attaches `nomads_hero_gallery/hero_gallery` CSS library for gallery layout styling.

## Files
- `src/Plugin/Field/FieldFormatter/HeroGalleryMediaFormatter.php`
  Formatter plugin and render/build logic.
- `css/hero-gallery.css`
  Hero-gallery layout styles (notably the 7-image variant).
- `nomads_hero_gallery.libraries.yml`
  Library declaration.

## Runtime Behavior
1. Collects referenced media entities from field items.
2. Resolves each media source image field and applies style by position.
3. Adds image style/media cache dependencies to render output.
4. If `max_images === 7`, outputs lead+grid structural markup and attaches gallery CSS.
5. Otherwise returns per-delta image formatter elements with gallery CSS attached.

## Concerns
- Stability: Specialized layout branch is hardcoded to `max_images === 7`; other limits do not get equivalent structured markup and may produce inconsistent presentation.
- Stability: Source field resolution assumes media type source field exists and is type `image`; non-image media references are silently skipped.
- Maintainability: Uses `function_exists('image_style_options')` fallback in settings form; behavior depends on procedural helper availability.
- Performance: Formatter renders per-item elements and media checks each request; large reference sets may increase render-time overhead.
- Testing: No automated tests are present for style selection by position, max-image branch behavior, or cache dependency propagation.

## Maintenance Notes
- Add formatter-level tests for:
  - mixed media references (image and non-image)
  - style assignment across first/second/rest positions
  - `max_images` limiting and 7-image layout output
- If additional fixed layouts are needed, consider explicit setting options instead of implicit numeric branching.
