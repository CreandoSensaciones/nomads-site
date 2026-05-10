# Paragraph Relevance Virtual Fields

## Purpose
`paragraph_relevance_vfields` adds listing virtual display fields that render paragraphs by relevance, ordered via hostname-specific key-aspect taxonomy configuration.

## What It Does
- Registers listing node extra fields:
  - `key_aspects`
  - `core_focus_paragraphs_3`
  - `relevant_paragraphs_2`
- Renders paragraphs from bundles with listing `field_<bundle>_relevance = 3` in `core_focus_paragraphs_3`.
- Renders paragraphs from bundles with listing `field_<bundle>_relevance = 2` in `relevant_paragraphs_2`.
- Builds key-aspect grouped output (`3/2/1`) with configurable output style:
  - `icon`
  - `pill`
  - `label`
  - `none`
- Resolves ordering from taxonomy chain:
  - `subsites.field_hostname` -> `subsites.field_key_aspects` -> `key_aspects.field_paragraph_bundle`
- Applies hostname fallback to `field_hostname = default` when no exact match exists.
- Copies paragraph `field_relevance` into `field_relevance2` during listing presave and copies the first bundle paragraph's `field_relevance2` into `field_<bundle>_relevance`.

## Files
- `paragraph_relevance_vfields.module`
  Virtual field registration/rendering, display settings UI integration, key-aspect helpers, presave synchronization logic.
- `paragraph_relevance_vfields.install`
  Update hook to ensure `field_relevance2` field config exists on referenced paragraph bundles.
- `paragraph_relevance_vfields.libraries.yml`
  Key-aspects CSS library registration.
- `css/paragraph_relevance_vfields.css`
  Styling for key-aspect and virtual-field layouts.
- `config/schema/paragraph_relevance_vfields.schema.yml`
  Third-party display settings schema.

## Runtime Behavior
1. On listing display, module injects enabled virtual fields (`key_aspects`, relevance 3, relevance 2).
2. Paragraphs are collected from paragraph reference fields and filtered by the listing's `field_<bundle>_relevance` value.
3. Order is determined by hostname-matched `subsites` term and its ordered `field_key_aspects` references.
4. Paragraphs render in view mode `3`/`2` (fallback `default` when missing).
5. Presave keeps `field_relevance2` and per-bundle relevance summary fields synchronized.

## Concerns
- Debug/operations risk: `hook_entity_presave()` logs verbose `notice` messages per listing and paragraph (`PR DEBUG`), which can flood logs and degrade performance on active editorial sites.
- Structural coupling: Logic is tightly bound to hardcoded bundles/fields/vocabularies (`listing`, `subsites`, `key_aspects`, `field_paragraph_bundle`, `field_relevance`, `field_relevance2`).
- Access posture: Taxonomy lookups use `accessCheck(FALSE)` for hostname/key-aspect resolution, which broadens data visibility assumptions.
- Side-effect complexity: Presave mutates paragraph relevance and node-level summary fields, increasing risk of unintended data drift if field naming conventions change.
- Maintainability: Module-level settings UI hooks into display multistep internals, which may break with upstream admin UI changes.
- Frontend assumptions: Anchor links for key aspects target `#<paragraph_bundle>` IDs; collisions/duplicates can create ambiguous navigation targets.
- Testing: No automated tests for hostname mapping, fallback behavior, presave synchronization, or key-aspect display settings.

## Maintenance Notes
- Remove or gate presave debug logging before non-prototype environments.
- Add tests for:
  - hostname -> subsite resolution and default fallback
  - relevance filtering by listing `field_<bundle>_relevance`
  - key-aspect ordering and output mode settings
  - presave synchronization of paragraph/node relevance fields
