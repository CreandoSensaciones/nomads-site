# Nomads Taxonomy Icons

## Purpose
`nomads_taxonomy_icons` provides a field formatter for taxonomy term reference fields that renders term labels with optional icons, pill styling, and grouped category output.

## What It Does
- Adds formatter plugin `nomads_taxonomy_icons` for taxonomy term reference fields.
- Supports icon rendering from term field `field_icons` (image field or media reference).
- Supports optional image style selection for icons.
- Supports optional links on:
  - label text (`link_label`)
  - icon (`link_icon`)
- Supports optional "pills after first item" mode where only first item shows icon and following items render as pills.
- Supports optional grouping mode where top-level parent terms become category headings.
- Attaches CSS library `nomads_taxonomy_icons/taxonomy-icons`.

## Files
- `src/Plugin/Field/FieldFormatter/TaxonomyIconsFormatter.php`
  Main formatter plugin logic: settings form/summary, term icon resolution, grouping, render output.
- `css/nomads_taxonomy_icons.css`
  Formatter CSS for icon sizing, pill display, and grouped layout.
- `nomads_taxonomy_icons.libraries.yml`
  Library definition.
- `nomads_taxonomy_icons.info.yml`
  Module metadata.

## Runtime Behavior
1. Formatter applies only to `entity_reference` fields targeting `taxonomy_term`.
2. Each referenced term is rendered with label and optional icon.
3. If grouping mode is enabled, terms are grouped by resolved top-level parent term.
4. If icon is present, it is rendered from `field_icons` using optional image style.
5. Formatter CSS library is attached for display styling.

## Concerns
- Structural coupling: Icon source is hardcoded to term field `field_icons`; taxonomy schema changes require code updates.
- Dependency risk: Formatter imports `Drupal\media\MediaInterface` and uses media/image APIs, but `.info.yml` does not declare explicit module dependencies (`media`, `image`, `taxonomy`).
- Performance: Image derivative creation (`createDerivative`) can occur during rendering, causing filesystem writes/work on request path.
- Taxonomy edge cases: Grouping logic follows a single parent path (`reset($parents)`); multi-parent terms can group inconsistently.
- Maintainability: Mixed behavior options (grouping + pills + link flags) increase branching complexity and regression risk.
- Accessibility/UX: Icon-only clickable area can exist separately from label link, creating split interaction targets.
- Testing: No automated tests for grouping behavior, icon source resolution, or fallback cases.

## Maintenance Notes
- Add explicit module dependencies in `.info.yml` to match runtime APIs.
- Add tests for:
  - field applicability and formatter settings
  - grouping with nested and multi-parent terms
  - image vs media icon rendering paths
  - missing/empty `field_icons` fallback behavior
