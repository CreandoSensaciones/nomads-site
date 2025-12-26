<img alt="Drupal Logo" src="https://www.drupal.org/files/Wordmark_blue_RGB.png" height="60px">

Drupal is an open source content management platform supporting a variety of
websites ranging from personal weblogs to large community-driven websites. For
more information, visit the Drupal website, [Drupal.org][Drupal.org], and join
the [Drupal community][Drupal community].

## Contributing

Drupal is developed on [Drupal.org][Drupal.org], the home of the international
Drupal community since 2001!

[Drupal.org][Drupal.org] hosts Drupal's [GitLab repository][GitLab repository],
its [issue queue][issue queue], and its [documentation][documentation]. Before
you start working on code, be sure to search the [issue queue][issue queue] and
create an issue if your aren't able to find an existing issue.

Every issue on Drupal.org automatically creates a new community-accessible fork
that you can contribute to. Learn more about the code contribution process on
the [Issue forks & merge requests page][issue forks].

## Usage

For a brief introduction, see [USAGE.txt](/core/USAGE.txt). You can also find
guides, API references, and more by visiting Drupal's [documentation
page][documentation].

## Project Notes

Conditional Fields Widgets helper (custom module):
Tried to override the Conditional Fields "value from widget" UI so it always
uses radios/checkboxes and ignores widget-specific limits (e.g. Special Category
Select). Implemented:
- New custom module `conditional_fields_widgets` (package: Admin helpers).
- Form alters for `conditional_field_edit_form` and `_tab` to replace the
  widget with `options_buttons`, then force checkboxes and unlimited selection.
- Additional recursion to relax `#cardinality`/`#max_delta`.

Status: still not working in the Conditional Fields UI. The widget remains the
special category select and the selection limit persists. Revisit later.

Conditional Fields not reacting to Special Category Select (and term_reference_tree):
Goal: have Conditional Fields "value" conditions work when the dependee field
uses the Special Category Select widget (entity_reference). It currently works
with native checkboxes widget but not with Special Category Select or
term_reference_tree. Observed symptoms: target field stays visible on initial
load and no conditional behavior triggers when selections change.

Tried fixes:
- Added `_cf_values` hidden input (newline-separated term IDs) for the widget,
  kept in sync via JS and dispatching `change` events.
- Preserved `_cf_values` input across sync to avoid losing bound listeners.
- Added `#name` and explicit `name` attribute on the widget element for
  selector building.
- Added a custom Conditional Fields handler
  `states_handler_special_category_select` that builds selectors pointing to
  `_cf_values` and maps values for all value-sets.
- Added selector fallback to derive name prefix from `#field_name`/`#parents`.

Status: still not working; conditional behavior does not trigger for
Special Category Select (and term_reference_tree). Needs deeper investigation
of Conditional Fields state/selector mapping for custom widgets.

You can quickly extend Drupal's core feature set by installing any of its
[thousands of free and open source modules][modules]. With Drupal and its
module ecosystem, you can often build most or all of what your project needs
before writing a single line of code.

## Changelog

Drupal keeps detailed [change records][changelog]. You can search Drupal's
changes for a record of every notable breaking change and new feature since
2011.

## Security

For a list of security announcements, see the [Security advisories
page][Security advisories] (available as [an RSS feed][security RSS]). This
page also describes how to subscribe to these announcements via email.

For information about the Drupal security process, or to find out how to report
a potential security issue to the Drupal security team, see the [Security team
page][security team].

## Need a helping hand?

Visit the [Support page][support] or browse [over a thousand Drupal
providers][service providers] offering design, strategy, development, and
hosting services.

## Legal matters

Know your rights when using Drupal by reading Drupal core's
[license](/core/LICENSE.txt).

Learn about the [Drupal trademark and logo policy here][trademark].

[Drupal.org]: https://www.drupal.org
[Drupal community]: https://www.drupal.org/community
[GitLab repository]: https://git.drupalcode.org/project/drupal
[issue queue]: https://www.drupal.org/project/issues/drupal
[issue forks]: https://www.drupal.org/drupalorg/docs/gitlab-integration/issue-forks-merge-requests
[documentation]: https://www.drupal.org/documentation
[changelog]: https://www.drupal.org/list-changes/drupal
[modules]: https://www.drupal.org/project/project_module
[security advisories]: https://www.drupal.org/security
[security RSS]: https://www.drupal.org/security/rss.xml
[security team]: https://www.drupal.org/drupal-security-team
[service providers]: https://www.drupal.org/drupal-services
[support]: https://www.drupal.org/support
[trademark]: https://www.drupal.com/trademark
