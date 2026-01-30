# Nomads Tiles

Provides a field formatter for Media image reference fields that renders a grid of tiles.

## Usage
- Enable the module.
- On **Manage display** for the content type, select the formatter **Nomads Tiles (media images)** for a media reference image field.
- Create Field Group wrappers whose machine names start with `group_tile` (for example, `group_tile_1`).
- Place the image field inside a Field Group. The formatter will locate that group and then use sibling `group_tile*` groups as data tiles.
- Fields inside `group_tile*` groups will be suppressed from normal rendering and instead shown as data tiles in the tile grid.

## Formatter settings
- `pattern_1d`, `pattern_2d`, `pattern_3d`: control the starting sequence of Data (D) and Image (I) tiles.
- `min_total_tiles`: minimum tiles to output; empty tiles are added if needed.
- `max_total_tiles`: maximum tiles to output; 0/empty means no limit.

## Notes
- Image tiles render as `<div>` elements with `background-image` set using the `tile` image style (falls back to the original file URL if the style is missing).
- Data tiles are rendered from Field Group `group_tile*` groups in the order defined by their weight.
