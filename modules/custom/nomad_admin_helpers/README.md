# Nomad Admin Helpers

## Purpose
`nomad_admin_helpers` applies small admin UX improvements, currently focused on the Extend (`/admin/modules`) page.

## What It Does
- Attaches a custom JS library only on route `system.modules_list`.
- Automatically collapses module group `<details>` sections in `#system-modules` on page load/attach.

## Files
- `nomad_admin_helpers.module`
  Adds conditional library attachment through `hook_page_attachments()`.
- `nomad_admin_helpers.libraries.yml`
  Declares the `admin_modules` JS library.
- `js/admin-modules-collapse.js`
  Drupal behavior that closes all module group details elements.

## Runtime Behavior
1. During page attachments, module checks current route name.
2. If route is `system.modules_list`, it attaches `nomad_admin_helpers/admin_modules`.
3. JS behavior runs once per matching element and sets each module-group `details` to closed.

## Concerns
- Low-Medium (stability): Selector `#system-modules details` depends on current admin page markup; if Drupal core changes that structure, behavior will stop working silently.
- Low (UX): Forced collapsing on every attach removes user open-state preference and may conflict with future persisted UI states.
- Low (maintainability): Uses global `\Drupal::routeMatch()` in a hook, which is acceptable but less explicit/testable than service-based patterns.
- Low (testing): No automated tests are present to validate route-based attachment or behavior execution.

## Maintenance Notes
- If additional helpers are added, consider separate libraries/behaviors per page to keep scope clear.
- If persistent expanded/collapsed state is desired, add client-side storage and opt-in behavior.
