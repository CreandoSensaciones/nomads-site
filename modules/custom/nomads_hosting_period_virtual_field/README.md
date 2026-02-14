# Nomads hosting period virtual field

Provides a single extra field for Paragraph bundle `hosting` that combines the
minimum hosting and typical hosting period fields into one output.

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
