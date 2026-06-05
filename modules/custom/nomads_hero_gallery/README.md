# Nomads Hero Gallery

## Purpose
`nomads_hero_gallery` provides a media-reference field formatter for rendering image-based hero galleries with position-specific responsive image styles.

## What It Does
- Adds formatter plugin `nomads_hero_gallery_media` for `entity_reference` fields targeting media.
- Renders referenced media image source fields with configurable responsive image styles for:
  - first image
  - second image
  - remaining images
- Supports optional image count limiting via `max_images`.
- Renders a unified hero-gallery structure:
  - one Swiper container for the main image area
  - secondary images as desktop/tablet tiles outside Swiper
  - the same Swiper becomes a full slideshow below `768px`
- Attaches `nomads_hero_gallery/hero_gallery` CSS library for gallery layout styling.

## Files
- `src/Plugin/Field/FieldFormatter/HeroGalleryMediaFormatter.php`
  Formatter plugin and render/build logic.
- `css/hero-gallery.css`
  Hero-gallery responsive tile and Swiper layout styles.
- `js/nomads-hero-gallery.swiper.js`
  Initializes the unified Swiper behavior.
- `nomads_hero_gallery.libraries.yml`
  Library declaration.

## Runtime Behavior
1. Collects referenced media entities from `field_images`.
2. Resolves each media source image field and applies responsive image style by position.
3. Splits images into one main image and remaining secondary images.
4. Outputs one Swiper container and a separate desktop/tablet tile container.
5. Keeps overflow images as hidden desktop GLightbox-only links when image limiting is enabled.
6. Adds responsive image style, fallback image style, and media cache dependencies to render output.

## Concerns
- Stability: Source field resolution assumes media type source field exists and is type `image`; non-image media references are silently skipped.
- Performance: Formatter renders per-item elements and media checks each request; large reference sets may increase render-time overhead.
- Testing: No automated tests are present for style selection by position, max-image branch behavior, or cache dependency propagation.

## Maintenance Notes
- Add formatter-level tests for:
  - mixed media references (image and non-image)
  - style assignment across first/second/rest positions
  - `max_images` limiting and unified Swiper output
- If additional fixed layouts are needed, consider explicit setting options instead of implicit numeric branching.
