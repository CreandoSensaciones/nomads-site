# Hidden Formatter

## Purpose
`hidden_formatter` provides a field formatter (`nomads_hidden`) that renders no output for a field in view mode, while keeping the field enabled and stored.

## What It Does
- Registers formatter plugin ID `nomads_hidden`.
- Returns an empty render array from `viewElements()`.
- Alters formatter definition at runtime so the formatter is available for all field types.

## Files
- `src/Plugin/Field/FieldFormatter/HiddenFormatter.php`
  Field formatter plugin implementation.
- `hidden_formatter.module`
  `hook_field_formatter_info_alter()` to expand supported field types.
- `hidden_formatter.info.yml`
  Module metadata.

## Runtime Behavior
1. Drupal discovers formatter plugin `nomads_hidden`.
2. During formatter info alter, the module reads all field type plugin definitions.
3. The formatter’s `field_types` list is replaced with all discovered field type IDs.
4. When selected in display mode, the formatter outputs nothing for that field.

## Concerns
- Architecture: The plugin annotation declares only `string`, then `hook_field_formatter_info_alter()` rewrites support to all field types at runtime. This split definition can be confusing for maintainers and static analysis.
- Stability: Applying a single formatter to every field type may include types where downstream expectations differ (e.g., custom formatters with side effects/cache behavior), so broad compatibility should be validated in site-specific usage.
- Maintainability: `hidden_formatter.module` uses `\Drupal::service(...)` directly instead of dependency injection patterns; acceptable in hooks but harder to test.
- Security expectations: This formatter hides field output in rendered displays only. It does not remove data from storage, APIs, exports, or custom code paths reading field values.
- Testing: No automated tests are present for plugin discovery, formatter availability across field types, or render/cache behavior.

## Maintenance Notes
- If broad field-type support is intended permanently, consider defining compatibility in one place (annotation vs alter hook) for clarity.
- Add Kernel tests that assert:
  - formatter discovery
  - formatter availability for representative core/custom field types
  - empty output in rendered entity builds
