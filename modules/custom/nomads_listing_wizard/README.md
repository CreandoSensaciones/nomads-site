# Nomads Listing Wizard

## Purpose
`nomads_listing_wizard` provides a dedicated `/listing/wizard` entry point that opens listing node creation in an onboarding-style multi-step form, including modal/AJAX behavior.

## What It Does
- Adds route `/listing/wizard` to create `listing` nodes.
- Registers node form operation `onboarding` by mapping it to core `NodeForm`.
- Builds a new unsaved listing node and renders it with operation `onboarding`.
- Supports direct page render and AJAX modal render (`_wrapper_format=drupal_ajax`).
- Converts submit actions to AJAX refresh inside the wizard wrapper.
- Prevents redirects on intermediate steps and only redirects on successful final step.
- Adjusts step format settings defaults through `hook_entity_node_form_listing_steps_alter()`.

## Files
- `nomads_listing_wizard.module`
  Form/step alter hooks, AJAX callback, intermediate submit handler, last-step detection helper.
- `nomads_listing_wizard.routing.yml`
  Wizard route definition and access requirement.
- `src/Controller/ListingWizardController.php`
  Controller that builds onboarding form and optional modal response.

## Runtime Behavior
1. User accesses `/listing/wizard` (with create access for `node:listing`).
2. Controller creates a new listing node and builds form operation `onboarding`.
3. Module hooks attach AJAX submit behavior and keep interaction inside modal wrapper.
4. Intermediate steps rebuild without redirect; final successful submit closes modal and redirects to canonical node page.

## Concerns
- Structural coupling: Workflow depends on specific form-state internals for step detection (`entity_form_steps`, hidden fields, fallback keys). Upstream/contrib changes can break wizard flow.
- Integration risk: `hook_entity_type_alter()` sets the `onboarding` form class on the `node` entity type globally, so other modules using `onboarding` may collide.
- Dependency clarity: Step-specific hooks imply dependency on step-form behavior/module, but `.info.yml` does not declare an explicit dependency for that integration.
- Stability: `nomads_listing_wizard_is_last_step()` returns `TRUE` when state cannot be detected, which can cause premature redirect attempts.
- Maintainability: Mixed use of static service access (`\Drupal::routeMatch()`, `\Drupal::request()`) in runtime logic reduces testability and consistency.
- UX consistency: Button relabel logic only checks label text `'Save'`, so translated/custom-labeled buttons may not be converted to `Next`.
- Testing: No automated tests for modal flow, multi-step state transitions, final redirect, or access control.

## Maintenance Notes
- Keep wizard behavior aligned with any updates to entity-step module internals.
- Add functional tests for:
  - route access
  - intermediate step rebuild/no redirect
  - final step redirect
  - modal AJAX open/close behavior
