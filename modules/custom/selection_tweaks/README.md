# Selection Tweaks

## Purpose
`selection_tweaks` applies UI-level constraints to grouped multi-select/checkbox options and normalizes option labels by removing trailing priority markers.

## What It Does
- Attaches frontend behavior on node forms.
- Parses grouping/limit tokens from option values using format like:
  - `a2`
  - `a2-b1`
- Enforces selection limits per group in the browser:
  - for `<select multiple>`
  - for checkbox groups
- Reads Drupal field cardinality from options widgets and disables remaining
  unselected choices when the field's allowed number of values is reached.
- Disables options/checkboxes that would exceed active group limits.
- Removes trailing suffix markers from option labels (e.g. `Label *2`, `Label_*3`) for display clarity.

## Files
- `selection_tweaks.module`
  Node-form library attachment, field cardinality metadata, and recursive label normalization helpers.
- `js/selection_tweaks.js`
  Group parsing and client-side limit enforcement logic.
- `css/selection_tweaks.css`
  Disabled-state styling for Pretty Checkbox Radio elements.
- `selection_tweaks.libraries.yml`
  Library registration.

## Runtime Behavior
1. On node forms, module attaches `selection_tweaks` JS library.
2. PHP adds finite Drupal field cardinality as `data-selection-tweaks-cardinality` on options widgets.
3. PHP normalizes option labels by stripping priority suffixes.
4. JS computes currently selected group counts and total selected count, then disables conflicting choices.
5. Limits are recalculated on each relevant selection change.

## Concerns
- Client-side enforcement only: Encoded group limits are applied in JS without corresponding server-side validation in this module, so bypassed JS can submit invalid combinations. Drupal field cardinality is still enforced server-side by Field API.
- Encoding coupling: Logic depends on value encoding pattern (`letter + number` segments split by `-`), so option value format changes break enforcement silently.
- Scope breadth: Library is attached to all node forms, including forms/fields that may not use grouped value encoding.
- UX ambiguity: Disabled options reflect current selections but do not explain which group rule blocked them.
- Data/label coupling: Label suffix stripping mutates visible labels globally for select/radio/checkbox elements and may remove meaningful text when `*<number>` is intentional content.
- Testing: No automated tests for parser edge cases, mixed-group interactions, or integration with widget variants.

## Maintenance Notes
- Add server-side validation if grouped limits must be strictly enforced.
- Add tests for:
  - token parsing (`a2-b1` and malformed variants)
  - dynamic enable/disable transitions
  - suffix-stripping behavior for legitimate label text
