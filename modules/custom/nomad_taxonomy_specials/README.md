# Nomad Taxonomy Specials

Enforces incompatibility rules for the `type` vocabulary on taxonomy term widgets, including the Special Category Select widget.

## What It Does
- Detects entity reference widgets targeting taxonomy vocabulary `type`.
- Builds a term-to-top-level map and branch groups from the taxonomy tree.
- Applies client-side disabling rules to prevent incompatible selections.
- Works with standard inputs and Special Category Select DOM patterns.
- Shows a temporary "Incompatible choice" message when disabled items are clicked.

## Rules
- If term 14 or any child is selected: all other branches are disabled.
- If term 25 or any child is selected: all other branches are disabled.
- If any other term is selected: branches 14 and 25 (and their children) are disabled.

## Notes
- This module disables incompatible choices; it does not remove existing selections.
- Shows "Incompatible choice" below the selected list when a disabled term is clicked.

## Files
- JS behavior: `js/nomad_taxonomy_specials.js`
- Attachments: `nomad_taxonomy_specials.module`

## Concerns
- Stability: Rules are hardcoded to top-level IDs `14` and `25` in module settings; term migrations/rebuilds can change IDs and silently break intended behavior.
- Stability: JS depends on specific Special Category Select selectors/classes (`.special-category-select__...`), so widget markup changes can break enforcement.
- Integrity: Restrictions are enforced client-side only; users or integrations bypassing JS can still submit incompatible combinations unless server-side validation exists elsewhere.
- Scope: Behavior selector includes `.special-category-select` broadly, which may apply logic to unintended widgets if similar markup appears on other forms.
- Testing: No automated tests are documented for rule computation, selector coverage, or edge cases (nested parents, removed terms, stale selections).
