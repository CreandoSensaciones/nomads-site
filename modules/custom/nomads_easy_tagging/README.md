# Easy Tagging

Easy Tagging provides a custom taxonomy term reference widget that renders taxonomy terms as card sets, driven by machine-name settings on each term.

## Assign the widget
1. Go to Manage form display for the content type that owns the unified tagging field.
2. Find the unified taxonomy reference field.
3. Select the widget labeled "Easy Tagging" and save.

## Term fields used
- field_settings (list_string; machine-name settings used by this widget)
- field_children_limit (integer)
- field_no_combine (entity reference, cross vocabulary allowed)
- field_ui_explainer (text)
- field_icons (entity reference to Media bundle term_icon)

Icon selection prefers a media variant with value "onboarding", then "default", then the first available media item.

## `field_settings` machine names for Easy Tagging
- `category_label`
If any top-level term in a hierarchical vocabulary has this setting, all top-level terms are rendered as section labels (without card selection for the top-level item itself), each with its children as cards.

- `shows_initially`
Only used together with `category_label`. If a top-level label does not have `shows_initially`, that label section is initially hidden (`display: none`) and can be shown by custom JS logic.

- `branch_replace`
When clicking a card with children, replace the current card set with that term's direct children. Back button restores previous set.

- `branch_open`
When clicking a card with children, open a new card set section labeled with the clicked term, using that term's direct children as cards.

- `branch_ignore` (default)
Children are ignored for navigation; the clicked term behaves as a normal selectable card.

- `limit_1_child` / `limit_1_children`
Only one direct child under that term can be selected.

- `limit_2_child` / `limit_2_children`
Only two direct children under that term can be selected.

- `limit_3_child` / `limit_3_children`
Only three direct children under that term can be selected.

Notes:
- The site currently already uses `limit_1_child`, `limit_2_child`, `limit_3_child`. The widget also accepts plural variants for compatibility.
- If no top-level term has `category_label`, hierarchical vocabularies render top-level terms as selectable cards.

## Constraints semantics
- field_no_combine is symmetric. If a selected term blocks another term, the other term is disabled in the UI and its descendants are disabled too.
- Soft block only prevents new selections. Existing selections are not removed.
- Children limits are primarily resolved from `field_settings` (`limit_1/2/3_*`). `field_children_limit` remains as fallback.
- Cross field context includes current selections from field_type when present on the node edit form.
- The module works even if the term fields are not installed yet. Constraints and UI explainers appear only when the fields exist.

## Caching notes
- Children and descendants are cached per language and tagged with taxonomy term cache tags.
- Constraint responses are not cached to reflect live form state.
