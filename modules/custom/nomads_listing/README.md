# Nomads Listing

## Purpose
`nomads_listing` provides listing-specific UI pieces: custom node local tasks/routes for split edit forms and frontend/admin asset libraries (layout CSS and a Leaflet resize helper script).

## What It Does
- Adds node local task tabs for listing content:
  - `Edit texts` -> `/node/{node}/edit-texts`
  - `Manage images` -> `/node/{node}/manage-images`
- Restricts those routes to:
  - node bundle `listing`
  - users with node update access on that entity.
- Declares CSS libraries for listing layout/admin form overrides.
- Declares JS library `nomads_leaflet_fix` that dispatches window resize events after tab clicks/AJAX attach to help Leaflet map reflow.
- Ships a Twig template `nomads-listing-pills.html.twig` intended for pill-style list rendering.

## Files
- `nomads_listing.module`
  Hook implementations (`hook_form_alter`, entity type alter stub).
- `src/Access/ListingTextFormAccess.php`
  Route access callback for listing edit tabs.
- `nomads_listing.routing.yml`
  Route definitions for listing text/images edit forms.
- `nomads_listing.links.task.yml`
  Local task tab definitions.
- `nomads_listing.libraries.yml`
  Library declarations (`base`, `listing_base_layout`, `nomads_leaflet_fix`).
- `css/temporary.css`
  Admin layout override CSS.
- `css/listing-base-layout.css`
  Listing page structural grid layout CSS.
- `css/nomads_listing.base.css`
  Small base utility style.
- `js/nomads-leaflet-fix.js`
  Leaflet resize workaround behavior.
- `templates/nomads-listing-pills.html.twig`
  Pill markup template.

## Runtime Behavior
1. Visiting a listing node shows extra local tasks for text/image edit forms.
2. Access callback checks listing bundle and node update access.
3. If `nomads_leaflet_fix` library is attached in render/theme, it listens to tab interactions and fires delayed resize events.

## Concerns
- Performance/operations: `hook_form_alter()` currently logs every altered form ID (`notice`), which can create noisy logs and unnecessary overhead on busy sites.
- Maintainability: `hook_entity_type_alter()` is a no-op stub; keeping empty hooks increases cognitive load unless a clear near-term purpose exists.
- Frontend stability: `nomads_leaflet_fix.js` uses broad selectors (`.tabs a`) and global resize dispatches, which can trigger extra reflows and side effects outside intended map contexts.
- Debug hygiene: Browser `console.log` calls remain in production JS and can pollute logs during support/debug sessions.
- Wiring clarity: `nomads-listing-pills.html.twig` appears unregistered in this module (no `hook_theme()`), so it may be dead code or implicitly used elsewhere.
- Documentation drift: Prior README mentioned only `css/nomads_listing.base.css`, while active `base` library points at `css/temporary.css`; this mismatch can confuse maintainers.
- Testing: No automated tests are present for route access, local task visibility, or JS behavior.

## Maintenance Notes
- Keep `nomads_leaflet_fix` attached only where maps exist; avoid global attachment.
- If template usage is required, register it explicitly via `hook_theme()` and add render tests.
- Replace high-volume debug logging with targeted conditional logging.
