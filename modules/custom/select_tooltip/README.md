# Select Tooltip

## Purpose
`select_tooltip` enhances select/radio/checkbox labels by parsing inline tooltip markers from option text and adds uncheck behavior for Pretty-styled radio widgets.

## What It Does
- Scans form options/labels for the marker pattern `Label -- Tooltip text`.
- For `<select>` options:
  - splits text at `--`
  - stores tooltip text in `title` and `data-tooltip`
  - updates the select element `title` with the currently selected option tooltip.
- For checkbox/radio labels:
  - parses `--` markers from label text
  - moves tooltip text to label `title`/`data-tooltip`
  - adds `select-tooltip__label` class for tooltip indicator styling.
- For `.pretty-element` radio widgets:
  - removes `_none` radio option elements from the rendered UI
  - allows unchecking an already-selected radio by clicking it again.
- Attaches behavior only when form has tooltip markers or pretty widget markers.

## Files
- `select_tooltip.module`
  Conditional library attachment and recursive form/option detection.
- `js/select_tooltip.js`
  Tooltip parsing/application for select options and label text.
- `js/pretty_radio_toggle.js`
  Pretty-radio `_none` removal and toggle-to-uncheck behavior.
- `css/select_tooltip.css`
  Tooltip indicator styling.
- `select_tooltip.libraries.yml`
  Library definition.

## Runtime Behavior
1. Form alter checks whether tooltip markers or pretty elements exist.
2. If yes, module attaches JS/CSS library.
3. JS transforms `--` markers into tooltip attributes and clean labels/options.
4. Pretty radio behavior removes `_none` option and enables click-to-uncheck.

## Concerns
- Data/text coupling: Tooltip extraction depends on `--` inside labels; legitimate content containing `--` may be unintentionally split.
- UX/accessibility: Tooltip content is exposed mainly through `title`, which is inconsistent across touch devices and assistive technology.
- Behavioral override risk: Enabling uncheck for radios changes native radio semantics and may conflict with validation/business rules expecting one value.
- Integration coupling: Pretty-radio logic depends on `.pretty-element` markup and `_none` option value conventions.
- Scope breadth: Behavior runs on all `<select>` elements once attached, which can affect unrelated widgets that use `--` in visible text.
- Testing: No automated tests for marker parsing edge cases, optgroup behavior, or radio-uncheck interactions.

## Maintenance Notes
- If needed, limit parsing to specific field names/selectors instead of all select/label elements.
- Add tests for:
  - option/label parsing with edge-case text
  - `_none` option handling
  - radio uncheck behavior with keyboard and mouse interaction
