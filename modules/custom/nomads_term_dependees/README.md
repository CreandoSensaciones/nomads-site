# Nomads Term Dependees

Adds opt-in dependee behavior to taxonomy term field widgets.

## How to enable
1. Go to `Manage form display` for your entity/form mode.
2. Open settings for a taxonomy term reference field widget.
3. Enable `Term dependees`.
4. Save display.

## Behavior
- Controller terms are the user-selected terms inside widgets where `Term dependees` is enabled.
- If a controller term has values in `field_dependee`, those dependee terms are auto-selected.
- Dependee terms are selected in the same widget when possible, otherwise in another compatible taxonomy term field on the same form.
- Auto-selected dependees receive a visual marker class/attribute.
- When controllers are unselected and no controller requires a dependee anymore, that dependee is auto-unselected.

## Easy Tagging interoperability
- `nomads_easy_tagging` category-label sections expose markup markers:
  - `data-nomads-category-label="1"`
  - `data-nomads-term-tid="<tid>"`
  - `data-nomads-shows-initially="0|1"`
- This module toggles visibility for sections with `category_label` and no `shows_initially` based on active controller->dependee relations.
- Visibility marker used by this module:
  - `data-nomads-dependee-visible="1|0"`
  - class `is-dependee-forced-visible`
- If multiple controllers map to the same dependee term, that section remains visible as long as at least one controller is still selected.

## Data requirements
- Taxonomy term field machine name: `field_dependee` (term reference).
