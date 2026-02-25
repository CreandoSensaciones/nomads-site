# Nomads Prices Virtual Fields

## Purpose
`nomads_prices_virtual_fields` provides reusable display-only virtual fields (extra fields) that combine multiple Commerce price fields into a single rendered price-range output.

## What It Does
- Registers paragraph extra field `nomads_prices_virtual_fields_price_range` on bundle `hosting`.
- Reads price inputs from:
  - `field_min_price`
  - `field_max_price`
  - `field_min_price_month`
  - `field_max_price_month`
- Builds grouped output for:
  - per-day prices
  - per-month prices
- Supports two output modes per group:
  - `range` (`min - max`)
  - `starting_at` (`min` only)
- Renders with Twig template `nomads-price-range.html.twig`.
- Attaches module CSS library `nomads_prices_virtual_fields/price-range`.

## Enable the Price Range extra field

1. Enable the module.
2. Go to **Structure** -> **Paragraph types** -> **hosting** -> **Manage display**.
3. In the **Disabled** section, enable **Price Range** (machine name: `nomads_prices_virtual_fields_price_range`).
4. Place it in the desired region and save.

The output is generated only when at least one minimum price exists (day or month).

## Files

- `nomads_prices_virtual_fields.module`
  Registers extra field, injects render array during entity view, registers theme hook.
- `src/PriceVirtualFieldBuilder.php`
  Core build logic, translation-aware entity handling, price extraction/formatting.
- `templates/nomads-price-range.html.twig`
  Markup for day/month range output.
- `css/nomads_prices_virtual_fields.css`
  Basic visual styling.
- `nomads_prices_virtual_fields.services.yml`
  Service definition for the builder.
- `nomads_prices_virtual_fields.libraries.yml`
  Library registration.

## Runtime Behavior
1. During paragraph render, module checks for entity type `paragraph` and bundle `hosting`.
2. It verifies the extra field is enabled in the active view mode.
3. Builder collects price values, formats currency, and returns themed render array.
4. Output appears only when data is sufficient for at least one group (day or month).

## Concerns
- Structural coupling: Bundle and field names are hardcoded (`hosting`, `field_min_price`, etc.), so schema changes require code updates.
- Data semantics: Module allows mixed states (e.g., min without max) but ignores max-only states; editors may see no output without clear validation feedback.
- Currency consistency: No explicit guard for mixed currencies between min/max fields; formatted output assumes coherent currency data.
- Scope rigidity: `hook_entity_extra_field_info()` only registers for paragraph bundle `hosting`; reuse on other bundles/types needs additional implementation.
- Maintainability: Module-level hooks use static service access (`\Drupal::service(...)`), which is acceptable in hooks but less testable.
- Testing: No automated tests for view-mode integration, translation behavior, or price mode branching.

## Maintenance Notes
- If reusing on other bundles, add explicit configuration mapping instead of duplicating hardcoded field names.
- Add tests for:
  - extra-field visibility by view mode
  - day/month `range` vs `starting_at` behavior
  - translation + cache metadata behavior
