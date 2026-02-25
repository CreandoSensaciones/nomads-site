# Field Group Toggler

## Purpose
`field_group_toggler` adds a checkbox toggle UI to Field Group `details` wrappers so editors can explicitly enable/disable a section while still using Drupal Field Group formatting.

## What It Does
- Provides a Field Group formatter plugin with ID `toggle`.
- Adds class `field-group-toggle` to matching `details` groups.
- Attaches `field_group_toggler/field_group_toggler` library (JS + CSS).
- Replaces the default summary marker with a checkbox + label layout.
- Auto-checks and auto-opens the group when it detects meaningful input values.

## Dependencies
- `field_group:field_group`
- Drupal core libraries: `core/drupal`, `core/once`

## Files
- `src/Plugin/field_group/FieldGroupFormatter/FieldGroupToggler.php`
  Formatter plugin extending Field Group `Details`.
- `field_group_toggler.module`
  Alters formatter metadata label/category for plugin `toggle`.
- `js/field_group_toggle.js`
  Behavior that injects checkbox UI and syncs open/checked state.
- `css/field_group_toggle.css`
  Summary/marker styling for the toggle UI.
- `config/schema/field_group_toggler.schema.yml`
  Schema mapping for `field_group.field_group_formatter_plugin.toggle`.

## Runtime Behavior
1. On behavior attach, each `details.field-group-toggle` group is scanned.
2. The summary content is wrapped with:
   - checkbox: `.field-group-toggle__checkbox`
   - label container: `.field-group-toggle__label`
3. Input/select/textarea changes inside the group re-evaluate whether the group should be checked/open.
4. If a group has no meaningful values, it can be unchecked and collapsed.

## Value Detection Rules
- Ignores structural inputs such as drag/drop weight controls.
- Treats blank values and `_none` as empty for selects.
- Treats entity reference `target_id` value `0` as empty.
- Treats checked checkbox/radio as having value.
- Treats selected file inputs as having value.
- Includes custom handling for the Special Category widget selectors:
  - `.special-category-select__inputs [data-cf-values]`
  - `.special-category-select__inputs input[data-term-id]`
  - `.special-category-select__selected-item`

## Known Limitations
- The plugin ID is `toggle`; this assumes no conflicting formatter plugin with the same ID from other modules.
- JS contains project-specific selectors for Special Category UI, so markup changes there may require JS updates.
- No automated tests are included in this module currently.

## Concerns
- Architecture: Plugin ID `toggle` is generic and may collide with other formatter plugins in larger/shared Drupal installations.
- Stability: Special Category value detection relies on specific CSS selectors and data attributes, which can break if that widget markup changes.
- Stability: No automated test coverage exists for behavior attach/re-attach flows, so regressions in dynamic form/AJAX contexts may go unnoticed.
- Accessibility: The injected checkbox inside `summary` currently has no explicit label association (`for`/`id`) or ARIA mapping to the `details` open state.

## Maintenance Notes
- If this module is reused across projects, consider extracting project-specific selectors into configurable settings or a separate behavior.
- If plugin conflicts appear, rename the formatter plugin ID and update related Field Group display config.
