# Paragraph Relevance

## Purpose
`paragraph_relevance` controls listing-form paragraph relevance workflows and paragraph display enhancements, including relevance-driven tab visibility, paragraph auto-open behavior, and modal paragraph viewing.

## What It Does
- Targets listing node forms (`node_listing_form`, `node_listing_edit_form`) and attaches relevance UI behavior.
- Annotates relevance fields/groups with data attributes for frontend logic.
- Filters/synchronizes relevance behavior based on selected taxonomy terms in `field_type`.
- Prevents paragraph-field persistence when mapped relevance is `0` (submit + presave safeguards).
- Adds Paragraph type third-party setting:
  - `css_classes` for rendered paragraph wrappers.
- Adds paragraph-reference field third-party setting:
  - `open_default` to auto-open empty paragraph subforms.
- Provides modal route/controller for paragraph display:
  - `/paragraph/{paragraph}/modal` using `modal` view mode.
- Provides Paragraph Views filter:
  - `Current node paragraph reference` keeps only paragraph revisions currently referenced by node paragraph fields.
- Attaches paragraph layout CSS globally on paragraph entity view.
- Includes sticky action and leaflet-resize JS helpers for listing editor UX.

## Files
- `paragraph_relevance.module`
  Main hooks and workflow logic (form alters, submit/presave guards, preprocess, helper functions).
- `paragraph_relevance.routing.yml`
  Modal paragraph route.
- `src/Controller/ParagraphModalController.php`
  Controller rendering paragraph entities in `modal` view mode.
- `paragraph_relevance.libraries.yml`
  JS/CSS library definitions.
- `js/paragraph_relevance.js`
  Relevance UI interactions, menu ordering, field visibility, and auto-add behavior.
- `js/paragraph_relevance_sticky_actions.js`
  Sticky save-actions behavior for listing forms.
- `js/nomads-leaflet-fix.js`
  Leaflet resize workaround on tab interactions.
- `js/paragraph_relevance_core_focus_limit.js`
  Archived script (not attached).
- `css/paragraph_relevance.css`
  Relevance editor styling.
- `css/relevance_paragraph_base_layout.css`
  Paragraph base layout CSS.
- `config/schema/paragraph_relevance.schema.yml`
  Third-party settings schema for paragraph type and field config.
- `config/schema/paragraph_relevance.views.schema.yml`
  Views filter settings schema.
- `src/Plugin/views/filter/CurrentNodeParagraphReference.php`
  Views filter that removes stale paragraph revisions from Paragraph views.
- `config/install/core.entity_view_mode.paragraph.modal.yml`
  Installed paragraph view mode `modal`.

## Runtime Behavior
1. On listing forms, module adds relevance behavior library and tags relevant form elements.
2. JS reads selected `field_type` terms and shows/hides relevance controls and tab items.
3. On submit/presave, paragraph fields tied to non-allowed or zero relevance are reset to original/empty values.
4. Paragraph entities receive additional classes from paragraph-type settings during preprocess.
5. Paragraph modal links can render paragraph content through dedicated modal route/view mode.
6. Paragraph Views can add `Current node paragraph reference` to show only paragraph revisions referenced by current node paragraph field tables.

## Concerns
- Structural coupling: Logic is hardcoded to listing bundle/form IDs and field names (`field_type`, `field_<term>_relevance`, `field_<term>`), so schema/workflow changes can break behavior.
- Validation bypass risk: Listing forms are marked `novalidate`, shifting more responsibility to custom handlers and reducing native browser validation safeguards.
- Access/query posture: Taxonomy lookups use `accessCheck(FALSE)` in multiple queries; this can expose broader data assumptions if reused beyond trusted editorial flows.
- Side-effect complexity: Both submit and presave handlers mutate paragraph reference fields, increasing risk of unexpected data resets during edge-case edits.
- Frontend fragility: UI behavior depends on specific vertical-tabs DOM structure/text label (`relevance`) and can regress with admin theme/markup changes.
- Debug/ops hygiene: `nomads-leaflet-fix.js` includes production `console.log` statements and broad resize dispatches that can add noisy diagnostics/reflow overhead.
- Maintainability: Module is large (1000+ lines) with many global helpers; change impact is hard to isolate without automated tests.
- Testing: No automated tests for relevance gating, field-reset semantics, modal routing/access, or open-default validation paths.

## Maintenance Notes
- Prioritize tests for:
  - relevance allow-list derivation from selected taxonomy terms
  - submit/presave reset rules for `field_<term>` paragraphs
  - open-default auto-append/remove behavior
  - modal route access and rendering
- Consider extracting relevance mapping/config from hardcoded naming conventions into explicit admin configuration over time.
