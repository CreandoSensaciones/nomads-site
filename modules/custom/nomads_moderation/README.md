# Nomads Moderation

## Purpose
`nomads_moderation` provides a custom taxonomy-reference moderation widget UI for listing-related tag fields, including grouped pill-style controls and force/block toggles.

## What It Does
- Provides field widget plugin `nomads_moderation` for `entity_reference` fields.
- Builds taxonomy branch groupings (top-level branch -> leaf terms) for the widget UI.
- Adds widget setting `enabled_branches` to limit visible taxonomy branches.
- In `hook_form_alter()`, ensures moderation fields are present and tagged for JS enhancement:
  - `field_tags`
  - `field_auto_tags`
  - `field_defaults_tags`
  - `field_force_tags`
  - `field_block_tags`
- Attaches JS/CSS library that replaces checkbox/radio display with moderation pills and control toggles.
- Synchronizes selected states between tags/default/auto/force/block inputs in the browser.

## Files
- `nomads_moderation.module`
  Form alter logic, dynamic widget injection for missing fields, library attachment.
- `src/Plugin/Field/FieldWidget/ListingsModerationWidget.php`
  Custom field widget plugin and taxonomy branch settings logic.
- `js/listings_moderation_widget.js`
  Client-side rendering/sync behavior for moderation pills and toggles.
- `css/listings_moderation_widget.css`
  Visual and visibility rules for moderation widget UI.
- `nomads_moderation.libraries.yml`
  Asset library definition.

## Runtime Behavior
1. Edit form is altered for configured moderation fields.
2. Fields are marked with data attributes and moderation library is attached.
3. JS scans form fields, builds grouped moderation controls, and hides raw checkbox/radio UI.
4. Toggle actions keep related fields synchronized (`tags`/`auto`/`defaults`/`force`/`block`).

## Concerns
- Structural coupling: Field behavior is hardcoded to specific machine names (`field_tags`, `field_auto_tags`, etc.), so field/bundle schema changes can silently break behavior.
- Data integrity: Core enforcement remains in frontend JS sync; no explicit server-side normalization is visible in this module for conflicting combinations (e.g., force + block states).
- Maintainability: Widget enhancement exists in two pathways (custom field widget plugin and module-level `hook_form_alter()` dynamic injection), increasing risk of divergence.
- Stability: JS fallback can reuse tree settings from another field when the current field lacks settings, which may create incorrect grouping in mixed forms.
- Accessibility/UX: CSS hides original controls (`display: none`) when active, so keyboard/screen-reader behavior depends entirely on custom buttons and ARIA quality.
- Performance: Form alter builds widget instances dynamically for missing fields and JS scans all relevant inputs per form attach; complex forms may incur overhead.
- Testing: No automated tests are included for widget settings, tree grouping, sync rules, or accessibility behavior.

## Maintenance Notes
- Keep taxonomy branch assumptions aligned with vocabulary structure and field configuration.
- Add tests for:
  - branch filtering (`enabled_branches`)
  - sync priority rules (block/force/default/auto)
  - fallback behavior when some moderation fields are absent
  - accessibility and keyboard interaction
