# Nomads Fitted Pills

## Purpose
`nomads_fitted_pills` provides field formatters that render selected values as pill UI and can optionally pack pills into fitted rows using a character-budget heuristic.

## What It Does
- Adds formatter `nomads_fitted_pills_list` for `list_string` fields.
- Adds formatter `nomads_fitted_pills_term_ref` for taxonomy term reference fields.
- Supports optional tooltip display and inline/non-inline rendering modes.
- Supports row fitting controls (`max_chars_per_row`, `pill_overhead`, `trigger_gap`, `min_improvement`).
- Supports max-row limiting, auto-big-pills behavior, and radical fitting with short-item pool logic.
- Attaches `nomads_fitted_pills/fitted-pills` CSS library for output styling.

## Files
- `src/Plugin/Field/FieldFormatter/FittedPillsFormatterBase.php`
  Shared settings and render-array build logic for fitted pills formatters.
- `src/Plugin/Field/FieldFormatter/FittedPillsListFormatter.php`
  Formatter implementation for list string fields.
- `src/Plugin/Field/FieldFormatter/FittedPillsTermRefFormatter.php`
  Formatter implementation for taxonomy term references.
- `src/Packer/FittedPillsPacker.php`
  Packing algorithm service used to build row layout.
- `tests/src/Unit/FittedPillsPackerTest.php`
  Unit tests for core packing behaviors.
- `css/nomads_fitted_pills.css`
  Display styles and tooltip CSS.

## Runtime Behavior
1. Formatter collects items and normalizes labels (including optional priority suffix stripping).
2. Packer computes rows by character budget, with optional best-fit improvement and radical fitting strategy.
3. Optional max-row trimming removes pills from the tail until rows fit.
4. Render array outputs rows/pills with attached CSS library.

## Concerns
- Stability: row fitting is based on character length (`strlen`) rather than rendered width, so visual fit may vary by font, language, and viewport.
- Internationalization: `strlen` counts bytes, which can misestimate multi-byte labels; `mb_strlen` would be safer for multilingual data.
- Maintainability: list formatter includes complex priority/max-row reintroduction logic that is not covered by dedicated tests beyond packer-level tests.
- UX/accessibility: tooltip display relies on `title`-based hover/focus CSS, which is inconsistent across devices and limited for touch users.
- Scope coupling: CSS includes generic class names (`big-pills`) that may collide if reused elsewhere.

## Maintenance Notes
- Add formatter-level tests (Kernel/Functional) for max-row trimming, priority suffix behavior, and tooltip rendering.
- If strict visual fitting is required, consider client-side measurement or CSS layout-driven fitting instead of char-count heuristics.
