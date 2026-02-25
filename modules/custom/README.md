# Custom Modules To-Do (Prototype Phase)

This file summarizes open actions after the full custom-module review pass.

## Priority 0 - Stabilize Now

- Add server-side validation for rules currently enforced only in JS:
  - `selection_tweaks` grouped selection limits
  - `price_range_widget` min/max currency consistency
  - `special_category_select` max/ordering assumptions if business-critical
- Audit and complete `.info.yml` dependency declarations where runtime APIs are used but not declared.
- Decide and implement SVG trust/sanitization boundary for icon-rendering modules:
  - `nomads_icon_field_label`
- Remove remaining prototype-level noise/risk:
  - done: `PR DEBUG` logs removed from `paragraph_relevance_vfields`
  - done: `console.log` removed from `nomads-leaflet-fix.js` files

## Priority 1 - Structural Hardening During Prototyping

- Replace hardcoded field/bundle/vocabulary assumptions with configurable mapping where possible.
- Reduce broad form/DOM coupling in admin JS-driven modules (prefer scoped selectors and explicit field targeting).
- Add explicit fallback/error handling for modules that silently no-op when expected config/markup is missing.

## Priority 2 - Test Baseline (Minimum Safety Net)

- Add smoke/functional coverage for modules with data mutation or rendering logic:
  - `paragraph_relevance`
  - `paragraph_relevance_vfields`
  - `nomads_prices_virtual_fields`
  - `nomads_hosting_period_virtual_field`
  - `special_category_select`
  - `selection_tweaks`
- Add regression checks for route/access and modal flows:
  - `nomads_listing`
  - `nomads_listing_wizard`

## Module-Specific Open Items

### `nomads_slideshow`
- Module is currently scaffold/incomplete:
  - formatter classes are empty
  - vendored Splide assets are placeholders
- Keep marked as not activated until implementation is complete.

### `paragraph_relevance`
- Large, high-coupling module; prioritize tests around submit/presave field reset behavior.
- Review `novalidate` usage on listing forms and ensure intended validation still runs.

### `paragraph_relevance_vfields`
- Review `accessCheck(FALSE)` taxonomy queries and confirm acceptable access model.
- Keep presave sync behavior under tests to prevent data drift.

### `nomads_tiles`
- Review recursive field suppression scope to avoid accidental removal of unrelated render elements.
- Clean up dangling/misleading comments in formatter class when code work is allowed.

### `numeric_bar_formatter`
- Fix integer percentage formula for non-zero field minimum.
- Replace inline JS/fixed DOM IDs in circular template with Drupal behavior/library approach.

### `special_category_select`
- Add explicit dependencies for Conditional Fields and term tree integration if required by runtime usage.
- Improve accessibility for tree toggle and tooltip interactions.

### `conditional_fields_widgets`
- Confirm UI override strategy still matches current Conditional Fields internals after module/core updates.

## Packaging/Metadata Follow-Up

- Continue normalizing package usage to only:
  - `Nomads Forms`
  - `Nomads Display`
  - `Nomads Features`
  - `Nomads Site General`
- Place display-oriented virtual-field modules under `Nomads Display`.
- Keep module descriptions in `.info.yml` aligned with actual behavior and activation status.

## Cleanup Queue

- Remove stale zip snapshots from `app/modules/custom` when no longer needed.
- Keep README + `.info.yml` synchronized whenever module behavior changes.

## Main To-Do's For Later

- Replace hardcoded field/bundle/vocabulary assumptions with admin-configurable mappings.
- Expand automated tests from smoke coverage to full regression coverage for critical custom modules.
- Refactor large hook-heavy modules into smaller services/plugins for easier maintenance.
- Improve accessibility across custom widgets (tooltips, tree toggles, custom radio/selection UIs).
- Reduce DOM-selector fragility in admin JS by scoping to stable wrappers and explicit data attributes.
- Consolidate repeated helper logic across modules (taxonomy lookup, value extraction, widget syncing).
- Remove deprecated/archive files and dead scaffolding modules/assets once replacements are complete.
