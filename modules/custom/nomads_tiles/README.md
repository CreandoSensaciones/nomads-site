# Nomads Tiles

## Purpose
`nomads_tiles` provides a media-image field formatter and a companion Field Group formatter to build mixed tile layouts that combine image tiles with structured "data tiles".

## What It Does
- Adds field formatter plugin `nomads_tiles_media` for media-image reference fields.
- Adds Field Group formatter plugin `nomads_data_tile` for view-mode groups intended as data-tile sources.
- Collects configured data-tile groups and renders their child fields into tile containers.
- Builds image tiles from referenced media images (using image style `tile` if available).
- Generates output sequence using configurable `D/I` patterns:
  - `pattern_1d`
  - `pattern_2d`
  - `pattern_3d`
- Supports `min_total_tiles` and `max_total_tiles` constraints with empty-tile fill.
- Suppresses normally-rendered child fields from tile groups via `hook_entity_view_alter()` pre-render cleanup.
- Attaches CSS library `nomads_tiles/tiles`.

## Files
- `src/Plugin/Field/FieldFormatter/NomadsTilesMediaFormatter.php`
  Main formatter logic: group discovery, field rendering, tile sequencing, image URL resolution.
- `src/Plugin/field_group/FieldGroupFormatter/NomadsDataTile.php`
  Field Group formatter plugin for data-tile groups.
- `nomads_tiles.module`
  Entity view alter + pre-render suppression of data-tile child fields.
- `config/schema/nomads_tiles.schema.yml`
  Formatter and field-group setting schema.
- `css/nomads_tiles.css`
  Base tile layout styles.
- `nomads_tiles.libraries.yml`
  Library definition.

## Runtime Behavior
1. On entity view, module identifies `nomads_data_tile` field groups and marks their child fields for suppression.
2. Formatter gathers data tiles from group children and image tiles from media references.
3. Tile list is sequenced by configured `D/I` pattern and min/max tile limits.
4. Render output is wrapped in `.nomads-tiles` and displayed as mixed data/image tiles.

## Concerns
- Structural coupling: Behavior depends on Field Group internals and specific formatter IDs (`nomads_data_tile`); field-group config/model changes can break tile extraction.
- Side effects: Recursive pre-render suppression in `nomads_tiles.module` can remove fields broadly when names overlap in nested render arrays.
- Maintainability: Formatter class ends with a dangling docblock comment referencing a non-existent hook implementation, which is misleading.
- Performance: Data tile rendering can invoke extra field hooks (`buildExtraFieldComponents`) and render multiple child formatters per request.
- Stability: `isApplicable()` uses static `\Drupal::entityTypeManager()` instead of injected services, reducing testability and increasing static coupling.
- Data/path assumptions: Image style name `tile` is hardcoded; missing style changes output behavior silently (fallback to original URL).
- Dependencies: Runtime uses media/image and Field Group APIs, but only `field_group` dependency is declared in `.info.yml`.
- Testing: No automated tests for suppression logic, pattern sequencing, group extraction, or extra-field rendering.

## Maintenance Notes
- Add explicit dependencies for runtime APIs (`media`, `image` if required by deployment policy).
- Add tests for:
  - group detection and child suppression
  - `D/I` pattern sequencing edge cases
  - max/min tile behavior with sparse content
  - media-image fallback when style `tile` is missing
