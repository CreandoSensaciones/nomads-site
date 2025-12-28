# Nomad Taxonomy Specials

Enforces incompatibility rules for the `type` vocabulary on taxonomy term widgets, including the Special Category Select widget.

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
