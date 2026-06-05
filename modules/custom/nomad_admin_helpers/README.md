# Nomad Admin Helpers

## Purpose
`nomad_admin_helpers` applies small admin UX improvements for Drupal administration pages and Listing edit forms.

## What It Does
- Attaches a custom JS library only on route `system.modules_list`.
- Automatically collapses module group `<details>` sections in `#system-modules` on page load/attach.
- Regroups Listing edit-form administration controls into one `Admin` tab.
- Moves Drupal core form details such as status, revision information, authoring information, URL alias, redirects, domains, and publishing/display toggles into smaller Admin accordion sections.

## Files
- `nomad_admin_helpers.module`
  Adds conditional library attachment through `hook_page_attachments()`, custom Help page grouping, and Listing form after-build regrouping.
- `nomad_admin_helpers.libraries.yml`
  Declares the `admin_modules` JS library.
- `js/admin-modules-collapse.js`
  Drupal behavior that closes all module group details elements.

## Runtime Behavior
1. During page attachments, module checks current route name.
2. If route is `system.modules_list`, it attaches `nomad_admin_helpers/admin_modules`.
3. JS behavior runs once per matching element and sets each module-group `details` to closed.
4. During Listing node form builds, an after-build callback normalizes the `Admin` tab and creates Admin sections for publishing/display, domains, URL/author/date, revision, and redirects.
5. Core vertical-tab elements are reassigned from Drupal's `advanced` group into those Admin sections.

## Concerns
- Low-Medium (stability): Selector `#system-modules details` depends on current admin page markup; if Drupal core changes that structure, behavior will stop working silently.
- Low-Medium (stability): Listing form regrouping depends on known element machine names like `meta`, `revision_information`, `author`, `path`, and `url_redirects`.
- Low (UX): Forced collapsing on every attach removes user open-state preference and may conflict with future persisted UI states.
- Low (maintainability): Uses global `\Drupal::routeMatch()` in a hook, which is acceptable but less explicit/testable than service-based patterns.
- Low (testing): No automated tests are present to validate route-based attachment, behavior execution, or Listing form regrouping.

## Maintenance Notes
- If additional helpers are added, keep them narrowly scoped by route, form type, or bundle.
- If persistent expanded/collapsed state is desired, add client-side storage and opt-in behavior.
