# Nomads Prices Virtual Fields

Provides reusable virtual fields (extra fields) for combined price display on nodes.

## Enable the Price Range extra field

1. Enable the module.
2. Go to **Structure** -> **Content types** -> *Your content type* -> **Manage display**.
3. In the **Disabled** section, enable **Price Range** (machine name: `nomads_prices_virtual_fields_price_range`).
4. Place it in the desired region and save.

The output is generated only when valid min prices exist for the configured fields.

## Add additional virtual fields

1. Add a new method to the builder service (`buildMyNewField()`), reusing the helpers.
2. Register the extra field in `nomads_prices_virtual_fields_entity_extra_field_info()` with a new machine name and label.
3. Inject the render array in `nomads_prices_virtual_fields_entity_view()` when the extra field is enabled.
4. Create a new Twig template and register it in `nomads_prices_virtual_fields_theme()`.

Clear caches after adding new virtual fields so the extra field shows up in **Manage display**.
