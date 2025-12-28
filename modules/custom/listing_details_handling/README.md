# Listing Details Handling

## Current Goal
On save of the Listing form (`node_listing_form`), redirect to the Details flow instead of the default `/node/{id}`.
On save of the Details form (`node_details_form`), redirect back to the Listing node.

## What Was Implemented
- **Submit handlers** are attached in `listing_details_handling.module` via:
  - `hook_form_node_listing_form_alter()`
  - `hook_form_node_details_form_alter()`
  - `listing_details_handling_attach_submit_handler()` inserts handlers right after `::submitForm` on:
    - `$form['actions']['submit']['#submit']`
    - `$form['actions']['publish']['#submit']` (if present)
- **Redirect forcing** helper `listing_details_handling_force_redirect()`:
  - `setIgnoreDestination(TRUE)`
  - remove `destination` query
  - set redirect URL

## Current Status
Redirects are enforced in a response subscriber to override final redirects when other handlers interfere.

## Files Touched
- `modules/custom/listing_details_handling/listing_details_handling.module`
- `modules/custom/listing_details_handling/src/EventSubscriber/ListingDetailsHandlingRedirectSubscriber.php`
