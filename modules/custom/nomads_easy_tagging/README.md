# Easy Tagging

Easy Tagging provides a custom taxonomy term reference widget that renders a unified vocabulary as stacked sections with grid cards, including a two level Category section and soft blocking based on term constraints.

## Assign the widget
1. Go to Manage form display for the content type that owns the unified tagging field.
2. Find the unified taxonomy reference field.
3. Select the widget labeled "Easy Tagging" and save.

## Term fields used
- field_children_limit (integer)
- field_no_combine (entity reference, cross vocabulary allowed)
- field_ui_explainer (text)
- field_icons (entity reference to Media bundle term_icon)

Icon selection prefers a media variant with value "onboarding", then "default", then the first available media item.

## Constraints semantics
- field_no_combine is symmetric. If a selected term blocks another term, the other term is disabled in the UI and its descendants are disabled too.
- Soft block only prevents new selections. Existing selections are not removed.
- field_children_limit is applied per parent term and limits how many direct children can be selected.
- Cross field context includes current selections from field_type when present on the node edit form.
- The module works even if the term fields are not installed yet. Constraints and UI explainers appear only when the fields exist.

## Caching notes
- Children and descendants are cached per language and tagged with taxonomy term cache tags.
- Constraint responses are not cached to reflect live form state.
