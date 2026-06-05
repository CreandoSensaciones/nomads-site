# Nomads Navigation

Provides domain-aware navigation helpers, Views integrations, and filter navigation blocks for Nomads listing discovery pages.

## Main Features

- Domain-specific navigation settings at `/admin/config/search/nomads-navigation`.
- Views filter plugin: `Has domain term`.
- Views field plugin: `Listing domains`.
- Frontpage landing-page teaser link rewriting for domain front pages.
- Query-preserving navbar URLs for `/`, `/list`, `/map`, and `/cal`.
- Domain menu URL rewriting so filter query strings survive domain switching.
- `Tag navigation` block for branch-based tag filters.
- `Geo navigation` block for hierarchical location filters.
- `Specific navigation` block for configurable child-term toggle pills.
- `Date period navigation` block for month-based date filters.

## Tag Navigation Block

Block admin label: `Tag navigation`

The block reads published terms from vocabulary `t`.

Behavior:

- Top-level published terms become filter branches.
- Each branch pill opens a dropdown of published direct children.
- Selecting a child term writes its ID into the `tags` query argument.
- Only one child from each top-level branch is kept in `tags`.
- Existing query arguments are preserved.
- When a branch has a selected child, the parent label appears above the pill.
- A branch-level `-clear-` option removes that branch value.
- A trailing `Clear` link removes all `tags` values.
- Opening one dropdown closes the other tag dropdowns.

Query format:

```text
?tags=123,456,789
```

The block only writes the query string. Whether Views treats multiple `tags` values as AND or OR depends on the View/filter handler that consumes the argument.

## Geo Navigation Block

Block admin label: `Geo navigation`

The block reads published terms from vocabulary `cit_countries_information`.

Behavior:

- The initial pill label is `Region / Country`.
- A selected term replaces the pill label.
- When a term is selected, `Region / Country` appears as the small label above the pill.
- The menu renders up to three taxonomy levels.
- Level 1 opens below the pill.
- Hovering a level-1 term opens its level-2 children horizontally to the right.
- Hovering a level-2 term opens its level-3 children vertically below it.
- Level 3 is the last rendered level.
- Clicking any rendered term writes one value to `geo`.
- Existing query arguments are preserved.
- A first-level `-clear-` option removes the selected `geo` value.

Query format:

```text
?geo=123
```

Current hard-coded geo visibility rules:

- Top-level branches `1278` and `1099` are not rendered.
- Children of term `1098` are not rendered.

## Specific Navigation Block

Block admin label: `Specific navigation`

Configure terms at `/admin/config/search/nomads-navigation` in `Specific navigation terms`.

Behavior:

- If no parent term is configured, the block renders nothing.
- A plain term ID such as `123` renders that term itself as a pill, even when the term is unpublished.
- A term ID suffixed with `>` such as `123>` renders a branch of that term's published direct children. The parent term does not need to be published.
- When more than one navigation group is rendered, each branch is wrapped in a container with classes such as `branch1 branch-123`, `branch2 branch-456`, and so on.
- When the only rendered navigation group is one branch, its child pills render directly without a branch wrapper.
- Pills never include a small internal parent label.
- When more than one navigation group is rendered, including another branch or a single configured term, each branch wrapper renders the parent term label once for the whole branch.
- Branch numbers count rendered branches only. The number of configured terms does not change how individual terms render.
- Clicking a pill toggles that term ID in the `t` query argument.
- Existing query arguments are preserved.
- Clicking an already selected pill removes that ID from `t`.

Query format:

```text
?t=123,456
```

## Date Period Navigation Block

Block admin label: `Date period navigation`

Behavior:

- Shows one pill labeled `Date period`.
- Dropdown contains the current month labeled `Actually`.
- Dropdown then lists the following 11 months by full month name, for example `June`, `July`, `August`.
- Dropdown ends with `-clear-`.
- Clicking a month writes one value to `month`.
- Clicking `-clear-` removes the selected `month` value.
- Existing query arguments are preserved.
- Navbar links and domain menu links preserve `month`.

Query format:

```text
?month=2026-6
```

## Navbar Query Preservation

The module adds page variables used by the custom `nomads` theme page template so the navbar links to:

- `/`
- `/list`
- `/map`
- `/cal`

keep the current query string, including filters such as `tags` and `geo`.

The `domains` menu is also rewritten by the theme preprocessing logic to keep the active discovery path/query when switching domains. It intentionally removes the `t` query argument used by Specific navigation before building domain-switch links.

## Views Integration

`hook_views_data_alter()` adds:

- `nomads_navigation_has_domain_term` filter on `node_field_data`
- `nomads_navigation_listing_domains` field on `node_field_data`

The domain term matching logic can inspect taxonomy term references directly on listing nodes and through selected owned paragraph bundles.

## Cache Notes

The navigation blocks vary by route, path, and query arguments. They carry taxonomy term cache tags so term label/published-state changes invalidate rendered output.

Clear Drupal caches after adding or changing block plugins, libraries, or theme template variables.
