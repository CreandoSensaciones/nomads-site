# Paragraph Relevance Virtual Fields

This module provides two virtual fields on Listing nodes:
- Core Focus Paragraphs (3) (`core_focus_paragraphs_3`)
- Relevant Paragraphs (2) (`relevant_paragraphs_2`)

Source of truth
Paragraph entities store relevance in `field_relevance2`:
- `3` = core focus
- `2` = relevant
Other values do not render in these virtual fields.

Eligibility and ordering
Paragraph bundles are selected and ordered per subsite:
`subsites.field_hostname` (matches request hostname) ->
`subsites.field_key_aspects` (ordered reference list) ->
`key_aspects.field_paragraph_bundle` (bundle machine name)

The stored reference order on `subsites.field_key_aspects` is the primary sort
order for rendering. Term weight is secondary and not relied on.

Caching
Render output varies by hostname and includes cache context `url.site`. Cacheable
dependencies include the Listing node, the active subsite term, referenced key
aspects terms, and rendered paragraphs.

Configuration
1) Create or edit a `subsites` term for each hostname.
2) Set `field_hostname` to the request host(s) (host only, no scheme).
3) Fill `field_key_aspects` with `key_aspects` terms in the desired order.
4) Ensure each `key_aspects` term has `field_paragraph_bundle` set.
5) Set paragraph `field_relevance2` values to `2` or `3`.

Fallbacks
- If no subsite term matches the hostname, the module tries `field_hostname =
  "default"`.
- If a paragraph view mode "2" or "3" does not exist for a bundle, rendering
  falls back to the "default" view mode.
