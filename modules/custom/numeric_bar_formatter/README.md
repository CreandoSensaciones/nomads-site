# Numeric Bar Formatter

## Purpose
`numeric_bar_formatter` provides field formatter output for integer and list-integer fields using visual progress styles (bar, color bar, circle, circular).

## What It Does
- Adds formatter plugin `numeric_bar_formatter` for:
  - `integer`
  - `list_integer`
- Provides formatter setting `numeric_bar_type` with four display modes:
  - `bar`
  - `colorbar`
  - `circle`
  - `circular`
- Registers Twig theme hooks/templates for each display mode.
- Attaches matching CSS library per selected display mode.
- For list-integer fields, computes stepped state data from allowed values.

## Files
- `src/Plugin/Field/FieldFormatter/NumericBarFieldFormatter.php`
  Main formatter settings and render logic.
- `numeric_bar_formatter.module`
  Theme hook registration for formatter templates.
- `numeric_bar_formatter.libraries.yml`
  Library definitions per style type.
- `templates/numeric-bar-format-*.html.twig`
  Output templates for each style mode.
- `css/progress.bar-*.css`
  Style-specific CSS assets.

## Runtime Behavior
1. Formatter is selected on integer/list-integer field display.
2. Module computes state percentage data for each item.
3. Corresponding template (`numeric_bar_format_<type>`) is rendered.
4. Matching CSS library (`progress-bar-<type>`) is attached.

## Concerns
- Data correctness: Integer percentage formula uses `value / (max - min)` and does not subtract `min`, causing incorrect results when field minimum is not zero.
- Frontend stability: `numeric-bar-format-circular.html.twig` injects inline script with fixed `id="graph"`; multiple formatter instances on the same page can conflict and render incorrectly.
- CSP/security posture: Inline `<script>` in Twig template may violate stricter Content Security Policy configurations.
- Maintainability: `numeric_bar_color` exists in default settings but has no settings-form control, creating dead/inconsistent configuration surface.
- UX rigidity: CSS includes fixed widths (e.g., `500px`, `220px`) that may not adapt well to responsive layouts.
- Semantics/accessibility: Visual bars/circles are rendered without ARIA semantics for assistive technologies.
- Testing: No automated tests for percentage computation, list-value mapping, or multi-instance frontend rendering.

## Maintenance Notes
- Add tests for:
  - integer min/max percentage calculations
  - list-integer mapping to stepped percentages
  - multi-instance circular rendering on one page
- Consider moving circular JS into a Drupal library behavior to avoid inline scripting and DOM ID collisions.
