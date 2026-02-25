# Special Category Select

## Purpose
`special_category_select` provides a custom taxonomy-term field widget with a two-column tree/selected UI, ordered selection support, and Conditional Fields integration.

## What It Does
- Adds field widget plugin `special_category_select` for `entity_reference` fields targeting taxonomy terms.
- Renders taxonomy terms as an expandable tree with optional:
  - `leaves_only` selection rule
  - sortable selected list
  - custom tree/selected column labels.
- Stores selected term IDs as ordered hidden inputs (`target_id`) and helper hidden fields (`_cf_values`, `_order`).
- Adds Conditional Fields handler `states_handler_special_category_select` using hidden `_cf_values` for dependency checks.
- Supports per-term tooltip display from taxonomy field `field_tooltip` when available.
- Attaches widget JS/CSS plus integration dependencies.

## Files
- `src/Plugin/Field/FieldWidget/SpecialCategorySelectWidget.php`
  Widget settings, tree/selected markup generation, input normalization, extraction/validation.
- `src/Plugin/conditional_fields/handler/SpecialCategorySelect.php`
  Conditional Fields states handler for widget values.
- `js/special_category_select.js`
  Client-side tree toggling, selection add/remove, hidden input syncing, optional drag sorting.
- `css/special_category_select.css`
  Widget layout, tree/selected styling, tooltip/drag/remove UI.
- `special_category_select.libraries.yml`
  Library definition.
- `special_category_select.module`
  Module bootstrap file (no runtime hooks).

## Runtime Behavior
1. Widget renders taxonomy tree and selected-list columns for configured vocabularies.
2. User selects/unselects terms in tree; selected list updates live.
3. JS writes hidden ordered values for form submit and Conditional Fields state matching.
4. On submit, widget normalizes user input to stable ordered `target_id` values.

## Concerns
- Dependency declarations: Runtime uses `term_reference_tree` and Conditional Fields plugin APIs, but `.info.yml` only declares `nomads_listing`; missing explicit dependencies can break plugin/library loading.
- Structural coupling: Tooltip support is hardcoded to taxonomy field `field_tooltip`; missing/renamed field removes that enhancement.
- Markup generation: Widget builds large HTML strings and returns `Markup::create`, increasing maintenance complexity vs render arrays/templates.
- Accessibility: Tree toggle controls are custom span buttons and hover-only tooltip patterns; keyboard/screen-reader behavior may be limited.
- Frontend coupling: JS relies on jQuery and specific class/data structures from generated markup and `term_reference_tree` styling.
- Validation model: Cardinality/max-selection limits are primarily enforced client-side in JS; server-side relies on field constraints but custom UX assumptions may diverge.
- Testing: No automated tests for ordering persistence, leaves-only logic, or Conditional Fields state behavior.

## Maintenance Notes
- Add explicit dependencies in `.info.yml` for modules/libraries used at runtime (e.g., `conditional_fields`, `term_reference_tree` if required).
- Add tests for:
  - ordered selection submit/extract roundtrip
  - cardinality max behavior
  - leaves-only selection rules
  - Conditional Fields state matching via `_cf_values`
