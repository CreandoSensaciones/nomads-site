# Field Subtitle

## Purpose
`field_subtitle` adds an optional subtitle to field widgets on entity forms, configured per field via third-party field settings.

## What It Does
- Extends the field configuration edit form with `third_party_settings[field_subtitle][subtitle]`.
- Stores subtitle text on the field config as a third-party setting.
- Injects subtitle markup into field widget form elements at render time.
- Applies a CSS library for subtitle spacing and responsive layout behavior.
- Includes a special handling path for widget plugin ID `special_category_select`.

## Dependencies
- Drupal core field/form/render APIs.
- Uses `field` module config entity (`Drupal\field\Entity\FieldConfig`) to resolve settings.

## Files
- `field_subtitle.module`
  Main hooks and subtitle application logic.
- `field_subtitle.libraries.yml`
  Declares frontend stylesheet library.
- `css/field_subtitle.css`
  Styling for subtitle placement and responsive alignment.
- `config/schema/field_subtitle.schema.yml`
  Config schema for the stored subtitle third-party setting.

## Runtime Behavior
1. On field config edit (`field_config_edit_form`), a subtitle textfield is shown.
2. Empty subtitle values are normalized to `NULL` on validation.
3. During widget rendering, the module reads the field subtitle from context/field config.
4. Subtitle markup is injected as field prefix with escaped text.
5. CSS classes and library are attached to support consistent visual output.

## Output and Escaping
- Subtitle content is escaped with `Html::escape()` before being rendered.
- Markup wrappers are added through `Markup::create()` after escaping text fragments.

## Concerns
- Architecture: The module contains widget-specific branching for `special_category_select`, which couples a generic utility module to project-specific widget behavior.
- Stability: Subtitle injection depends on widget element structure (`value` child, `ui_title`, `#field_prefix`). Upstream widget/form API shape changes can break placement.
- Stability: The module appends classes/libraries in several paths and does not fully deduplicate class entries, which may produce duplicated class values in edge rebuild/alter flows.
- Performance: Fallback subtitle resolution uses `FieldConfig::loadByName()` during form build; repeated use across many widgets may add overhead.
- Maintainability: A stray docblock line (`Enables debug messages for a specific field.`) appears above `field_subtitle_apply_to_element()` and does not match behavior.
- Testing: No automated tests are included for hook execution order, AJAX rebuilds, or widget variants.

## Maintenance Notes
- If this module is intended to be reusable, move widget-specific logic into a separate integration module or plugin-based adapter.
- Add automated Kernel/Functional tests covering:
  - standard widgets and multivalue widgets
  - special category widget output
  - AJAX form rebuild behavior
  - empty vs populated subtitle normalization
