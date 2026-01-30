# Nomads Easy Listing

## Status (session handoff)
- Modal flow intercepts `/node/add/listing` and opens `/nomads-easy-listing/start` in an AJAX modal.
- Step 1 creates a listing node and saves the title.
- Step 2 is a placeholder with a Next button.
- Step 3 renders a 3x3 grid of taxonomy terms (icon, label, easy listing text) and should save a branch term into `field_types`, then advance to Step 4 placeholder.
- Step 3 header: "Which category fits best for your initiative?"

## Current issue
- Step 3 still shows "No terms available" even though terms exist in vocabulary `type` (label: Category options).
- Cache was cleared; still empty.

## Investigated
- Confirmed vocabulary machine name `type` in config.
- Added fallbacks in the loader to avoid strict `loadTree()` constraints and access checks.

### Loader changes (EasyListingStartForm)
- Root terms: load via `loadTree('type', 0, 1, TRUE)`; fallback to `entityQuery` with `accessCheck(FALSE)` for `parent=0`; final fallback loads all terms in the vocab and treats empty parent as roots.
- Branch terms: load via `loadTree($root->bundle(), $root_tid)`; fallback BFS over descendants using access-free queries; last resort returns the root term.

## Files changed in module
- `modules/custom/nomads_easy_listing/src/Form/EasyListingStartForm.php`

## Next steps
1) Clear cache again and re-test Step 3.
2) If still empty, collect DB evidence:
   - `select t.tid, t.vid, t.name, t.default_langcode from taxonomy_term_field_data t where t.vid='type' order by t.tid limit 20;`
   - `select p.entity_id, p.parent_target_id from taxonomy_term__parent p where p.entity_id in (select t.tid from taxonomy_term_field_data t where t.vid='type') limit 20;`
3) If DB access is required, share MySQL socket path or TCP creds so a dump/query can be run.

## Notes
- Admin user, no language handling.
- Drush SQL and sql-dump currently fail with "Can't create TCP/IP socket (1)".
