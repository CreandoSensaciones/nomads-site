# Nomads Listing Form Progress

## Current Goal
On save of the Listing form (`node_listing_form`), redirect to the Details flow instead of the default `/node/{id}`.
On save of the Details form (`node_details_form`), redirect back to the Listing node.

## What Was Implemented
- **Submit handlers** are attached in `nomads_listing_form_progress.module` via:
  - `hook_form_node_listing_form_alter()`
  - `hook_form_node_details_form_alter()`
  - `nomads_listing_form_progress_attach_submit_handler()` attaches to:
    - `$form['#submit']`
    - `$form['actions']['submit']['#submit']`
    - `$form['actions']['publish']['#submit']` (if present)
- **Redirect forcing** helper `nomads_listing_form_progress_force_redirect()`:
  - `setIgnoreDestination(TRUE)`
  - remove `destination` query
  - set redirect URL
  - set a `RedirectResponse`
- **Response subscriber** added to override redirects after entity save:
  - `modules/custom/nomads_listing_form_progress/src/EventSubscriber/ListingFormProgressRedirectSubscriber.php`
  - Listens on `KernelEvents::RESPONSE` with **priority -1000**
  - Detects the form by `form_id` in POST data
  - Extracts `/node/{id}` from the default redirect and overrides it
    - Listing form -> either edit the matched Details node or open `node/add/details?listing_ref={id}`
    - Details form -> redirect to Listing canonical
- **Service definition**:
  - `modules/custom/nomads_listing_form_progress/nomads_listing_form_progress.services.yml`
  - Service uses only `@entity_type.manager`

## Known Issue
After saving a new Listing, the redirect still goes to `/node/{id}` instead of the Details flow.

A container error occurred when the subscriber constructor signature changed. The fix is already in the services file, but it requires a cache rebuild.

## Where To Resume Next Time
1. **Rebuild caches** to apply the updated service wiring:
   - `drush cr`
2. **Re-test Listing save** and confirm whether it still lands on `/node/{id}`.
3. If it still redirects to the Listing node:
   - Add temporary logging inside `ListingFormProgressRedirectSubscriber::onResponse()` to confirm it fires and sees `form_id` and the redirect URL.
   - If it fires but does not override, raise the priority further (e.g. `-10000`) or move to a later event (`KernelEvents::TERMINATE`) to force the redirect.
   - If it does not fire, check if the response is not a `RedirectResponse` (e.g., a `TrustedRedirectResponse`) and handle that type too.
4. If needed, add a very late response override using `$event->setResponse()` even when another module sets a response object.

## Files Touched
- `modules/custom/nomads_listing_form_progress/nomads_listing_form_progress.module`
- `modules/custom/nomads_listing_form_progress/nomads_listing_form_progress.services.yml`
- `modules/custom/nomads_listing_form_progress/src/EventSubscriber/ListingFormProgressRedirectSubscriber.php`
