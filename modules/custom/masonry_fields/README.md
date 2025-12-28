# Masonry Fields

Provides a field formatter for multi-value media reference fields that renders items in a Masonry layout without using Views.

## Features
- Formatter: "Masonry (media images)"
- Works with `entity_reference` fields targeting `media` that are multi-value.
- Adds Masonry settings (enable toggle + options from the Masonry API module).

## Dependencies
- Masonry API module (`masonry`)
- Media module (`media`)

## Notes
- Requires the Masonry JS library in `libraries/masonry/dist/masonry.pkgd.min.js`.
- ImagesLoaded is optional in `libraries/imagesloaded/imagesloaded.pkgd.min.js`.

## Usage
- Enable the module.
- In Manage Display, choose the formatter and enable Masonry.
