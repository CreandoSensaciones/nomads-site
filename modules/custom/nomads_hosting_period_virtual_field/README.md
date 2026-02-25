# Nomads hosting period virtual field

Provides a single extra field for Paragraph bundle `hosting` that combines the
minimum hosting and typical hosting period fields into one output.

## What It Does
- Registers an extra display field on paragraph bundle `hosting`:
  `nomads_hosting_period_virtual_field`.
- Builds output from:
  - `field_minimum_hosting`
  - `field_typical_hosting_period`
- Resolves translated paragraph values for the current render language.
- Renders output via theme hook `nomads_hosting_period_extra_field` and Twig template.
- Only renders when both source values are present.

## Enable the extra field

1. Enable the module.
2. Go to **Structure** -> **Paragraphs types** -> **hosting** -> **Manage display**.
3. In the **Disabled** section, enable **Nomads hosting period virtual field**
   (machine name: `nomads_hosting_period_virtual_field`).
4. Place it in the desired region and save.

The extra field renders only when both source fields have values.

## Format setting

This module uses the default output format and the standard label display
controls from the view display.

## Concerns
- Integrity: The virtual field is display-only and does not add server-side validation for source field consistency.
- Stability: Logic is hardcoded to paragraph bundle `hosting` and field names `field_minimum_hosting` / `field_typical_hosting_period`; schema renames will break output.
- Maintainability: `hook_entity_view()` fetches display/builder via global `\Drupal::service(...)`, which is acceptable in hooks but harder to test.
- Maintainability: `nomads_hosting_period_virtual_field.module` ends with a dangling `hook_form_FORM_ID_alter` docblock without implementation, which may confuse maintainers.
- Testing: No automated tests are included for render output, translation behavior, or cache metadata.
