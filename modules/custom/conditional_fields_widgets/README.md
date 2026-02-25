# Conditional Fields Widgets

## Purpose
`conditional_fields_widgets` modifies the Conditional Fields rule-edit UI so dependee value widgets are normalized to option widgets (radios/checkboxes) with relaxed entry limits.

## What It Does
- Alters Conditional Fields edit forms:
  - `conditional_field_edit_form`
  - `conditional_field_edit_form_tab`
- Attempts to rebuild the dependee value element using the actual dependee field definition/widget metadata from form display configuration.
- Forces options-based widgets in the condition-value UI:
  - single-value -> radios
  - multi-value -> checkboxes
- Relaxes cardinality-related limits in the condition-value element tree (`#cardinality`, `#max_delta` -> `-1`).
- Supports dependee field types:
  - `boolean`
  - `entity_reference`
  - `list_integer`
  - `list_float`
  - `list_string`

## Files
- `conditional_fields_widgets.module`
  All form alters and helper functions for value-widget rebuilding/normalization.
- `conditional_fields_widgets.info.yml`
  Module metadata and dependencies.

## Runtime Behavior
1. On Conditional Fields rule edit screens, module intercepts the value field UI.
2. It resolves dependee metadata from form display third-party settings.
3. If possible, it rebuilds the value widget as options-buttons-based element.
4. It then normalizes the rendered element tree to radios/checkboxes and relaxes limits.

## Concerns
- Behavioral override risk: Forcing widget types can diverge from original dependee widget semantics and may confuse editors used to native widget behavior.
- Config coupling: Logic depends on exact form display third-party Conditional Fields config structure (`third_party_settings[conditional_fields][uuid]`), which may change across versions.
- Supported-type limits: Field types outside the hardcoded allowlist silently skip rebuilding, causing inconsistent admin UX.
- Cardinality handling: Unconditionally setting `#cardinality` / `#max_delta` to `-1` can conflict with expectations for strict single-value conditions.
- Maintainability: Deep recursive mutation of form elements increases fragility when upstream form element structures evolve.
- Testing: No automated tests for rule-edit form compatibility, widget normalization correctness, or version-to-version stability.

## Maintenance Notes
- Keep compatibility checks aligned with Conditional Fields updates.
- Add tests for:
  - dependee type coverage
  - single vs multiple value normalization
  - fallback behavior when config paths are missing
