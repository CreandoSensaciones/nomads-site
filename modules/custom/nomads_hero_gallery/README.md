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
- Renders a responsive hero-gallery structure:
  - desktop grid at `1200px` and wider with 1 lead image and 6 secondary images
  - tablet grid from `768px` to `1199px` with 1 lead image and 4 visible secondary images
  - mobile Swiper slideshow below `768px`
- Attaches `nomads_hero_gallery/hero_gallery` CSS library for gallery layout styling.

## Files
- `src/Plugin/Field/FieldFormatter/HeroGalleryMediaFormatter.php`
  Formatter plugin and render/build logic.
- `css/hero-gallery.css`
  Hero-gallery responsive grid and mobile slideshow layout styles.
- `js/nomads-hero-gallery.swiper.js`
  Initializes and destroys the mobile-only Swiper behavior.
- `nomads_hero_gallery.libraries.yml`
  Library declaration.

## Runtime Behavior
1. Collects referenced media entities from field items.
2. Resolves each media source image field and applies responsive image style by position.
3. Outputs a desktop/tablet grid container and a mobile Swiper container.
4. Keeps overflow images as hidden GLightbox-only links.
5. Adds responsive image style, fallback image style, and media cache dependencies to render output.

## Concerns
- Stability: Source field resolution assumes media type source field exists and is type `image`; non-image media references are silently skipped.
- Performance: Formatter renders per-item elements and media checks each request; large reference sets may increase render-time overhead.
- Testing: No automated tests are present for style selection by position, max-image branch behavior, or cache dependency propagation.

## Maintenance Notes
- Add formatter-level tests for:
  - mixed media references (image and non-image)
  - style assignment across first/second/rest positions
  - `max_images` limiting and 7-image layout output
- If additional fixed layouts are needed, consider explicit setting options instead of implicit numeric branching.
