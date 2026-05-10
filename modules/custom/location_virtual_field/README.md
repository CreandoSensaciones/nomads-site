# Location virtual field

Compatibility module for the listing location virtual field.

The active implementation has been moved into
`nomads_listing_virtual_fields`. This module keeps the old service, theme hook,
template, display settings, and extra-field code available as a fallback, but
each hook exits early when `nomads_listing_virtual_fields` is enabled.

## Purpose

The module exposes one display-only extra field for listing nodes:

- Entity type: `node`
- Bundle: `listing`
- Extra field machine name: `location_vfield`
- Label: `Location vfield`

The field does not store data. It renders a synthesized location/date summary
from location paragraphs referenced by the listing.

## Activation

1. Enable the module.
2. Go to **Structure** -> **Content types** -> **Listing** -> **Manage display**.
3. Move **Location vfield** from disabled fields into the desired display
   region.
4. Save the display.

The virtual field renders only when the display component exists and its region
is not `hidden`.

## Compatibility behavior

`location_virtual_field.module` checks for `nomads_listing_virtual_fields` at
the start of every hook implementation:

- `hook_entity_extra_field_info()`
- `hook_entity_view()`
- `hook_theme()`
- display-form alter hooks

When `nomads_listing_virtual_fields` is enabled, this module returns without
registering or rendering anything. In that case, the consolidated module owns
the `location_vfield` extra field and uses the same theme hook name
(`location_virtual_field`) and template concept.

## Render Mechanism

Rendering is driven by `hook_entity_view()`:

1. Drupal starts rendering a `node` entity.
2. The hook ignores all entities except listing nodes.
3. The hook loads the actual view display for the current view mode with
   `entity_display.repository->getViewDisplay('node', 'listing', $view_mode)`.
4. The hook reads the `location_vfield` display component.
5. Rendering stops when the component is missing or the component region is
   `hidden`.
6. The hook calls the `location_virtual_field.builder` service.
7. The builder returns a render array or `NULL`.
8. The hook copies the configured component `weight` and `region` onto the
   render array.
9. The render array is added to the entity build as
   `$build['location_vfield']`.

The builder service is `Drupal\location_virtual_field\LocationExtraFieldBuilder`.
It is registered in `location_virtual_field.services.yml`.

## Source Data

The builder reads source data from the listing field `field_location_date`.

Only referenced paragraphs that satisfy both conditions are used:

- The referenced entity implements `ParagraphInterface`.
- The paragraph bundle is `location`.

Each paragraph is translated into the current listing render language when that
translation exists. If the paragraph has no matching translation, the original
paragraph entity is used.

For each location paragraph, the builder reads:

- `field_country`: taxonomy term references used for country, region, place,
  free-tagging, breadcrumb, and link output.
- `field_date`: date range used for month or month-range output.

The current implementation contains helper methods for `field_settlement`,
`field_surroundings`, and `field_title`, but normalized output currently derives
the rendered location from `field_country` terms and the rendered date from
`field_date`.

## Display Settings

The module adds a **Location virtual field** settings details element to the
listing Manage display form.

The active consolidated module saves these settings as third-party settings on
the entity view display under the `nomads_listing_virtual_fields` provider. If
the compatibility module is running without the consolidated module, the same
settings are saved under the `location_virtual_field` provider.

- `link_terms`: boolean, default `TRUE`. When enabled, eligible taxonomy terms
  are linked to their term pages.
- `output_mode`: string, default `detailed`. The Manage display form exposes
  this as radio buttons:
  - `detailed`: preserves the previous output exactly.
  - `combined`: uses the condensed country/date aggregation described in
    [Combined Output Mode](#combined-output-mode).
  - `switch`: uses `detailed` until the number of location/date bundles exceeds
    `max_bundles`; when there are more location/date bundles than
    `max_bundles`, it uses `combined`.
- `max_bundles`: integer, default `6`, minimum `1`. Limits the number of
  location bundles rendered by the multi-bundles variant and acts as the
  threshold for `switch` mode.

The schema for these settings lives in
`config/schema/location_virtual_field.schema.yml`.

The Manage display form values are copied onto the `EntityViewDisplay` through
the module's `#entity_builders` callback. This runs during Drupal's
`buildEntity()` step, before the display config entity is saved, so the
third-party settings persist with the rest of the display configuration.

## Builder Flow

`LocationExtraFieldBuilder::build()` performs the render preparation in this
order:

1. Create cache metadata and add the listing and view display as dependencies.
2. Read `link_terms`, `output_mode`, and `max_bundles` from display third-party
   settings.
3. Collect translated `location` paragraphs from `field_location_date`.
4. Normalize each paragraph into a bundle array.
5. Resolve the effective output mode:
   - `detailed`: use detailed output.
   - `combined`: use combined output.
   - `switch`: use detailed output when `count($bundles) <= max_bundles`, and
     combined output when `count($bundles) > max_bundles`.
6. Build the mode-specific render array. Detailed mode then selects one
   detailed variant from the number of bundles and whether any bundle has a
   complete date range.
7. Build wrapper CSS classes.
8. Return a themed render array using `#theme = location_virtual_field`.

If any required data is unavailable, the builder returns `NULL`; the field is
then omitted from the entity render array.

## Normalized Bundle Shape

Each location paragraph is normalized into a bundle with these keys:

- `country`: plain country or region label.
- `country_markup`: linked or unlinked safe markup for the country or region.
- `country_term`: selected country or region taxonomy term object, when
  available.
- `country_terms`: country taxonomy term objects represented by the bundle.
  Region-country mode stores the explicit countries here; normal mode stores
  the selected or inferred country.
- `country_lineage`: root-first taxonomy lineage for `country_term`.
- `breadcrumb`: plain breadcrumb label chain.
- `breadcrumb_markup`: linked or unlinked safe markup for the breadcrumb chain.
- `tail`: plain trailing place labels.
- `tail_markup`: linked or unlinked safe markup for trailing place labels.
- `places`: plain list of place/country labels used for class state and
  separators.
- `region_country_mode`: boolean for explicit region plus country rendering.
- `date_from`: parsed `DateTimeImmutable` start date or `NULL`.
- `date_to`: parsed `DateTimeImmutable` end date or `NULL`.
- `has_date`: boolean, true only when both parsed dates exist and the end date
  is not earlier than the start date.
- `freetagging`: currently normalized to an empty string.

## Taxonomy Rules

All taxonomy logic starts from terms referenced by `field_country`.

Duplicate term IDs are ignored after their first occurrence. The remaining terms
are classified through their `field_type` values:

- Region-like terms: `region`, `continent`, `continental_subregion`,
  `subregion`.
- Country terms: `country`.
- Place terms: `place`.
- Free-tagging terms: `free`.
- Breadcrumb-visible region terms: any region-like term that also has
  `breadcrumb`.

Term ancestry is read through the taxonomy `parent` field from the selected term
up to the root. Ancestors tagged as breadcrumb-visible region terms can appear
in the breadcrumb. Country ancestors can be inferred when no explicit country
term was selected.

Country selection order:

1. Use the explicit country term with the shallowest lineage depth, then lowest
   selection index.
2. Otherwise use an inferred country ancestor with the deepest lineage, then
   lowest term ID.
3. Otherwise use the first selected term.

When an explicit region-like term is selected, the output switches to
region-country mode:

- The first explicit region-like term becomes the main country/region value.
- Explicit country terms become the tail.
- The full label renders as `Region - Country1, Country2`.

Outside region-country mode, the full label renders from breadcrumb and tail:

- `Breadcrumb > Place` when there is one tail item.
- `Breadcrumb - Place1, Place2` when there are multiple tail items.
- `Breadcrumb` when there is no tail.
- `Tail` when there is no breadcrumb.
- `Country` as the final fallback.

## Term Markup and Linking

Each term is rendered as:

```html
<span class="country"><a href="/taxonomy/term/123">Term label</a></span>
```

or, when not linked:

```html
<span class="country">Term label</span>
```

The semantic class is selected in this order:

1. `free`
2. `place`
3. `country`
4. `region`
5. `free` fallback

Terms are linked only when `link_terms` is enabled and the term is one of:

- region-like
- country
- place

Terms tagged `free` are never linked.

## Combined Output Mode

The `output_mode` display setting controls whether the builder uses the
existing detailed aggregation or the new combined aggregation.

`detailed` is the default and keeps the previous behavior exactly, including
all variants described in [Rendering Variants](#rendering-variants).

`combined` renders one condensed location/date line:

- Just location, no valid date range: render only the country term. If multiple
  location bundles exist without dates, render the deepest shared geo parent
  that contains the represented countries; if no shared parent can be resolved,
  fall back to the unique country labels.
- One location/date bundle: render `country term / date`. The date is formatted
  by the existing short month logic: month names up to five letters are written
  fully, longer month names are reduced to three letters.
- Several location/date bundles: render `best fitting georegion / entire date
  range`. The combined container begins with
  `<span class="count">5 locations</span>`, with the actual bundle count.

The best fitting georegion is resolved from the country terms represented by
the location bundles:

1. Build the root-first taxonomy lineage for each represented country.
2. Find the common lineage prefix shared by all represented countries.
3. Use the deepest term in that shared prefix.
4. Render that term with the same term markup and linking rules as detailed
   mode.

For several dated bundles, the date range is calculated from all valid dated
bundles:

1. Find the earliest `field_date.value`.
2. Find the latest `field_date.end_value`.
3. Render the first month of the earliest date through the first month of the
   latest date using the same short month logic: month names up to five letters
   are written fully, longer month names are reduced to three letters.

This multi-bundle combined date span intentionally uses the whole earliest to
latest month span instead of the detailed per-bundle limit controlled by
`max_bundles`.

`switch` chooses the effective output mode at render time. The threshold uses
the normalized location/date bundle count, not the number of rendered items
after detailed-mode filtering:

- When the listing has `max_bundles` or fewer location/date bundles, it renders
  with `detailed`.
- When the listing has more than `max_bundles` location/date bundles, it
  renders with `combined`.

## Date Rules

Dates are read from `field_date` as `value` and `end_value`.

A date range is valid only when:

- Both values parse successfully.
- The end date is not earlier than the start date.

Rendered date labels are month based:

- Same calendar month: render one month.
- Two-month ranges: ignore tiny edge months of 3 days or fewer unless that
  would hide a meaningful 7-day-or-longer part of the other month.
- Ranges spanning 3 or more calendar months: trim start or end months that
  contain 3 covered days or fewer, then render the first and last meaningful
  months.

Year suffixes are added when the rendered month is in the past or more than
11 months in the future. Detailed `single-range` output uses full years
(`2026`). Detailed `multi-bundles` output and all combined date output use
short years (`26`). Month names longer than five letters are abbreviated to
three letters in detailed `multi-bundles` and combined output; month names up
to five letters stay full.

## Rendering Variants

The builder chooses exactly one variant:

- `single-location`: one bundle and no valid date.
- `single-range`: one bundle with a valid date.
- `multi-countries`: multiple bundles and no valid dates.
- `multi-bundles`: multiple bundles and at least one valid date.

### `single-location`

Renders the full location markup in a single span.

### `single-range`

Renders a container with:

- `<span class="location">...</span>`
- `<span class="sep"> : </span>`
- `<span class="date">...</span>`

The date uses full month/year formatting.

### `multi-countries`

Collects unique non-empty country labels from all bundles and renders them as a
comma-separated list. The first markup encountered for each country label is
used.

### `multi-bundles`

Sorts bundles by start date, with undated bundles last. If the total bundle
count exceeds `max_bundles`, past bundles are filtered out by requiring
`date_to > now`; the remaining bundles are sorted again. The result is sliced to
`max_bundles`.

Each rendered bundle uses:

```html
<span class="bundle bundle1">
  <span class="location">Country markup</span>
  <span class="date">Date label</span>
</span>
```

The location part for this variant is the bundle `country_markup`, not the full
breadcrumb/tail label.

## Theme Hook and Template

The builder returns this top-level render array:

```php
[
  '#theme' => 'location_virtual_field',
  '#label' => 'Location vfield',
  '#label_display' => $component['label'] ?? 'hidden',
  '#wrapper_classes' => $classes,
  '#content' => $content,
]
```

`hook_theme()` maps `location_virtual_field` to
`templates/location-virtual-field.html.twig`.

The template receives:

- `label`
- `label_display`
- `wrapper_classes`
- `content`

The template renders:

```twig
<div class="{{ classes|join(' ') }}">
  {% if label and label_display != 'hidden' %}
    <div class="field__label">{{ label }}</div>
  {% endif %}
  {{ content }}
</div>
```

## Wrapper Classes

Every rendered field receives:

- `location-date`
- `taxonomy-breadcrumb`
- `location-date--single` or `location-date--multi`
- `location-date--{variant}`
- `location-date--count-{render_count}`

Combined mode uses these variants:

- `combined-location`
- `combined-single-range`
- `combined-multi-range`

Additional classes:

- `location-date--filtered`: multi-bundles output was filtered because the
  source bundle count exceeded `max_bundles`.
- `location-date--has-breadcrumb`: at least one rendered bundle has a breadcrumb
  value.
- `location-date--has-places`: at least one rendered bundle has a non-empty
  `places` array.
- `location-date--has-freetagging`: at least one rendered bundle has a
  non-empty `freetagging` value.

## Cacheability

The render array carries cache metadata for:

- The listing node being rendered.
- The entity view display.
- Each translated location paragraph used.
- Each referenced `field_country` taxonomy term used.
- Country lineage terms used to resolve or render the combined best fitting
  georegion.

The cache metadata is applied directly to the returned render array before it is
inserted into `$build['location_vfield']`.

## Failure Cases

The field does not render when:

- `nomads_listing_virtual_fields` is enabled and therefore owns the
  implementation.
- The entity is not a listing node.
- The display component `location_vfield` is disabled or missing.
- The listing lacks `field_location_date`.
- `field_location_date` has no referenced `location` paragraphs.
- Normalization produces no renderable bundle data.
- The selected variant cannot produce content.

## Concerns

- The module is a compatibility wrapper; active rendering should normally be
  maintained in `nomads_listing_virtual_fields`.
- Logic is tied to listing nodes, location paragraphs, and specific field names.
- There are no automated tests in this module for variant selection, taxonomy
  lineage behavior, date formatting, display settings, or cache metadata.
