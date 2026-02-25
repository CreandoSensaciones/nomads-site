# Nomads Form Widgets

## Purpose
`nomads_form_widgets` centralizes custom widget plugins and form-level UI behavior tweaks used by Nomads entity editing flows.

## What It Does
- Provides custom field widget plugin `nomads_buttons` (extends options buttons widget).
- Applies button-style behavior for checkbox/radio options (`buttons` library).
- Applies layout/style tweaks for:
  - double field widgets
  - geolocation map widget inputs
  - daterange widgets (inline layout)
- Adds sliderwidget behavior tweaks:
  - bubble positioning and overlap handling
  - default value range syncing in field config forms
- Adds list-integer handling so allowed value key `0` can behave as a valid required selection.

## Files
- `src/Plugin/Field/FieldWidget/ButtonsWidget.php`
  Custom widget plugin for boolean/list option fields.
- `nomads_form_widgets.module`
  Hook implementations and recursive form/widget detection helpers.
- `js/buttons.js`
  Removes `_none` option and allows unchecking selected radios in button UI.
- `js/sliderwidget_bubble_tweaks.js`
  Repositions slider bubbles and handles range-empty state display.
- `js/sliderwidget_default_value_range.js`
  Syncs range slider defaults to hidden from/to inputs on field config form.
- `css/*.css`
  Widget-specific visual/layout adjustments.

## Runtime Behavior
1. On form alter, module scans form structure and conditionally attaches relevant libraries.
2. On field widget alter, module adds daterange inline classes and custom validation logic for list-integer `0`.
3. JS behaviors run per matching widget to enhance interaction and keep hidden/source inputs synchronized.

## Concerns
- Stability: Multiple recursive full-form scans (`has_double_field`, `has_geolocation_widget`, `has_sliderwidget`, `mark_daterange_inline`) can add overhead on large forms.
- Stability: Sliderwidget behaviors rely on jQuery UI slider internals and specific class/DOM structures; upstream module changes may break behavior.
- Data integrity: Several fixes are client-side (JS sync/mapping). If JS fails or is bypassed, resulting form values may diverge from expected mapped values.
- UX/accessibility: `buttons.js` removes `_none` options and implements radio uncheck behavior, which deviates from standard radio semantics and can confuse keyboard/screen-reader expectations.
- Testing: No automated tests are present for hook-driven form alterations, slider synchronization, or custom radio behavior.

## Maintenance Notes
- Add Kernel/Functional tests for:
  - list-integer zero required validation
  - daterange class attachment
  - slider default-value sync
- Consider consolidating repeated recursive scans into one traversal to reduce form build overhead.
