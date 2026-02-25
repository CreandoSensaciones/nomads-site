# Nomads Slideshow

## Purpose
`nomads_slideshow` is intended to provide Splide-based field formatters for image/media-image slideshow rendering in teaser and listing contexts.

## What It Does
- Declares frontend library `nomads_slideshow/splide` with:
  - Splide vendor JS/CSS paths
  - module init behavior `js/nomads_slideshow.init.js`
  - module styling `css/nomads_slideshow.css`
- Initializes Splide on `.nomads-splide` elements when at least two slides exist.
- Reads per-instance options from `data-nomads-splide` JSON.

## Files
- `nomads_slideshow.libraries.yml`
  Library registration for Splide assets and init behavior.
- `js/nomads_slideshow.init.js`
  Drupal behavior that mounts Splide per slideshow container.
- `css/nomads_slideshow.css`
  Slideshow sizing and ratio helper styles.
- `src/Plugin/Field/FieldFormatter/NomadsSplideFormatterBase.php`
  Intended formatter base class (currently empty file).
- `src/Plugin/Field/FieldFormatter/NomadsSplideImageFormatter.php`
  Intended image formatter plugin (currently empty file).
- `src/Plugin/Field/FieldFormatter/NomadsSplideMediaImageFormatter.php`
  Intended media-image formatter plugin (currently empty file).
- `vendor/splide/js/splide.min.js`
  Splide vendor JS placeholder.
- `vendor/splide/css/splide.min.css`
  Splide vendor CSS placeholder.

## Runtime Behavior

1. Field formatter output (from formatter plugins) should render `.nomads-splide` markup with slide items and JSON options.
2. Attached library loads Splide vendor assets plus init script.
3. JS behavior mounts Splide only when there are 2+ slides.

## Concerns
- Critical functionality gap: All formatter PHP files in `src/Plugin/Field/FieldFormatter/` are currently zero-byte files, so formatter plugins are effectively missing.
- Critical asset gap: `vendor/splide/js/splide.min.js` and `vendor/splide/css/splide.min.css` are placeholders, not real Splide assets; slideshow runtime cannot function as intended.
- Documentation mismatch: Current usage instructions describe configurable formatters that are not actually implemented in code.
- Stability: JS silently falls back to empty options when `data-nomads-splide` JSON parsing fails, making misconfiguration hard to detect.
- Dependency governance: Vendored third-party assets in-module require manual updates and vulnerability tracking.
- Testing: No automated tests for formatter discovery/rendering or frontend slideshow initialization.

## Maintenance Notes
- Before production use, implement the formatter plugin classes and provide real Splide assets.
- Add smoke tests that verify:
  - formatter plugins are discoverable in Manage display
  - rendered markup includes required `.nomads-splide` structure
  - Splide JS mounts successfully on multi-slide output
