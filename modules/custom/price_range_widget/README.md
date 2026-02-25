# Price Range Widget

## Purpose
`price_range_widget` improves listing form UX for Commerce min/max price fields by arranging them as a single range row and synchronizing currency selectors.

## What It Does
- Detects forms containing both `field_min_price` and `field_max_price`.
- Attaches `price_range_widget` JS/CSS library when price range fields are present.
- In the editor UI:
  - places min and max price widgets in one row
  - shows one primary currency selector
  - hides duplicate currency selectors
  - keeps hidden currency selectors synchronized with the primary selector
- Supports two DOM patterns:
  - grouped wrapper ending in `group-price-range`
  - direct min/max field wrapper pairing

## Files
- `price_range_widget.module`
  Form alter + recursive field detection to conditionally attach library.
- `js/price_range_widget.js`
  DOM restructuring and currency synchronization behavior.
- `css/price_range_widget.css`
  Layout and visibility styles for the range row/currency elements.
- `price_range_widget.libraries.yml`
  Library definition.

## Runtime Behavior
1. Form alter checks whether min/max price fields exist.
2. If present, JS/CSS library is attached.
3. JS finds target wrappers and creates/uses `.price-range-widget__row`.
4. Primary currency field remains visible; additional currency fields are hidden and synced on change.

## Concerns
- Structural coupling: Behavior relies on specific `data-drupal-selector` suffixes (`field-min-price-wrapper`, `field-max-price-wrapper`, `group-price-range`), so form markup changes can break the widget silently.
- Data integrity: Currency synchronization is client-side JS; if JS fails or is bypassed, min/max currency values can diverge.
- Scope control: Module attaches to any form containing min/max field names without checking bundle/form IDs, which may affect unintended forms.
- Accessibility/UX: Hidden duplicate currency fields (`display: none`) can make debugging/editor understanding harder when values diverge.
- Maintainability: DOM move operations (`appendChild`/`insertBefore`) assume stable wrapper hierarchy and can conflict with other form-alter widgets.
- Testing: No automated tests for attachment conditions, grouped/non-grouped layout paths, or currency sync behavior.

## Maintenance Notes
- Add functional tests for:
  - library attachment conditions
  - grouped and non-grouped form markup variants
  - currency synchronization on change and initial load
- Consider adding server-side validation to enforce equal currencies for min/max fields.
