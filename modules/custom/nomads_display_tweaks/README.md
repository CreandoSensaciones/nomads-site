# Nomads Display Tweaks

Display-layer glue for Nomads entity, field, formatter, media, and block visibility output.

## Features

- Provides a `title_vfield` display-only extra field for listing and landing page nodes.
- Applies CSS classes from `field_css` to node, paragraph, and entity render wrappers.
- Applies Field Formatter Class values, including token replacements, to rendered field wrappers and selected widget wrappers.
- Adds formatter support for linking media image output to the parent entity that references the media item.
- Provides `Tweaked label` and `Tweaked pills` formatters for list (text) fields that parse inline label markers for tooltips and small text.
- Wraps field prefixes, suffixes, numeric units, physical units, and range formatter affixes in `prefix` and `suffix` spans.
- Formats `field_subdomain` output into `domain-first` and `domain-rest` spans.
- Adds `/node/{node}/modal`, which renders a node in `modal` view mode with Drupal dialog Ajax attached.
- Adds extra Nomads block visibility rules for hiding blocks on full hero and tutorial node pages.
- Controls whether Listing full-page renders use `full` or `simple` view mode.

## Listing Simple And Advanced Display

The module adds a boolean field to the Listing content type:

- Machine name: `field_advanced_listing`
- Label: `Advanced listing`
- Type: boolean
- Default value: unchecked / `0`

Runtime behavior for Listing nodes:

- `field_advanced_listing = 1`: keep the requested `full` render in `full` view mode.
- `field_advanced_listing = 0`, empty, or missing: switch a requested `full` render to `simple` view mode.
- Other requested view modes, such as `teaser` and `modal`, are not changed.

The module does not create the `full` node view mode. Drupal core already provides it.

The module creates the `simple` node view mode only if `node.simple` does not exist yet. The `simple` display still needs to be configured in Drupal UI or config management for the desired Listing output.

## Bulk Actions

The module provides two configured node actions for Views bulk forms:

- `Mark selected listings as advanced`
- `Mark selected listings as simple`

These actions are node actions so they can appear in the standard content bulk form, but they only apply to Listing nodes that have `field_advanced_listing`.

If a View bulk form is configured with `Only selected actions`, edit the bulk form field settings and add these actions under `Selected actions`.

## Install And Updates

For new installs, config in `config/install` creates:

- `field.storage.node.field_advanced_listing`
- `field.field.node.listing.field_advanced_listing`
- `system.action.nomads_display_tweaks_mark_listing_advanced_action`
- `system.action.nomads_display_tweaks_mark_listing_simple_action`

For existing installs, run:

```bash
drush updb
drush cr
```

Update hooks create the field, create `node.simple` only if missing, add the field to the Listing default form display when available, and create the configured bulk actions.

The module also repairs missing bulk action config during cache rebuild.

## Notes

Most behavior is implemented through entity view, entity display build alter, entity view alter, field preprocess, node preprocess, paragraph preprocess, formatter settings, widget alter, and rebuild hooks.

Because this module touches several render phases, review output and cache behavior when changing hook order, display configuration, or formatter output.
