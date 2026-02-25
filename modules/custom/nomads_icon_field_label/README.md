# Nomads Icon Field Label

## Purpose
`nomads_icon_field_label` adds a custom field label display mode (`icon`) that replaces text labels with SVG icons loaded per entity-type/bundle/field combination.

## What It Does
- Adds `Icon` as a label-display option on manage-display forms.
- Detects fields using `label_display = icon` during entity render.
- Prepends icon markup before the first field item and hides the normal label text.
- Loads SVGs from `public://data-icons/{entity_type}-{bundle}-{field_name}.svg`.
- Falls back to a built-in placeholder circle icon when no SVG file is found.
- Attaches module CSS for icon sizing/layout.

## Files
- `nomads_icon_field_label.module`
  Form alter hooks, field preprocessing, icon lookup/markup helpers.
- `src/TrustedCallbacks.php`
  Trusted pre-render callback used to inject icon wrapper in field render arrays.
- `css/nomads_icon_field_label.css`
  Icon size/display styling.
- `nomads_icon_field_label.libraries.yml`
  Library declaration.

## Runtime Behavior
1. Manage display form adds `Icon` label option to each field row.
2. During entity view/preprocess, fields configured with icon label mode are targeted.
3. Module resolves SVG by deterministic file naming convention in public files.
4. First rendered item is wrapped with icon + original content container.

## Concerns
- Security: Raw SVG file contents are injected into markup (`file_get_contents` + `Markup::create`) without sanitization, so untrusted SVG uploads can introduce XSS risk.
- Stability: Icon resolution depends on strict filename convention in `public://data-icons`; missing/renamed files silently fall back to placeholder.
- Rendering consistency: Only the first field item is wrapped with icon; multi-value fields may produce uneven visual structure.
- Maintainability: Logic is implemented in both preprocess and pre-render pathways, increasing risk of divergence/duplicate behavior.
- Performance: Per-request filesystem reads for SVG files can add overhead without persistent cache invalidation strategy.
- Testing: No automated tests are present for manage-display integration, icon fallback, or render behavior across view modes.

## Maintenance Notes
- Restrict who can upload/modify SVG files in `public://data-icons`.
- If icons are trusted but large in number, consider render-cache/file metadata caching strategies.
- Add tests covering:
  - icon label option availability
  - placeholder fallback behavior
  - multi-value field rendering with icon mode
