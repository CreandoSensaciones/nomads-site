## Contrib Change: conditional_fields

File changed:
- `modules/contrib/conditional_fields/src/ConditionalFieldsFormHelper.php`

Reason:
The node add/edit form was throwing a TypeError:
`ConditionalFieldsFormHelper::elementAddProperty()` received `null` instead of an
array for the dependent element. This happens when a configured dependency
points to a field that is not present in the current form build. To prevent the
fatal error, a guard was added to skip adding validation when the dependent
element is missing.

Status:
This is a local patch to a contrib module. Consider replacing it with a custom
module hook/alter or upstreaming if appropriate.

## Custom Module: paragraph_relevance

Change:
`modules/custom/paragraph_relevance/js/paragraph_relevance.js`

Reason:
The listing edit form can show relevance tabs without any paragraph items when
no paragraphs exist yet. A JS workaround triggers the "Add" action when the
user clicks the vertical tab menu item, so the paragraph subform opens
synchronously with the tab. This avoids a confusing empty pane.

Status:
Workaround only. Replace with a proper server-side default item or a more
robust AJAX integration when available.

Issue: toggle groups and default values
Change:
`modules/custom/paragraph_relevance/js/field_group_toggle.js`
`modules/custom/paragraph_relevance/js/paragraph_relevance.js`
`modules/custom/paragraph_relevance/paragraph_relevance.module`

Reason:
Default values (notably sliderwidget numeric fields with default "1") cause
the parent field_group toggle to auto-check on new listing forms. Hidden fields
inside a closed toggle group can also still be saved because their defaults are
submitted. We attempted to prevent this by (a) baselining initial input values
and only auto-checking when values change from the baseline, (b) disabling
inputs in hidden toggle groups, and (c) tracking hidden terms/inputs and clearing
them on submit. The sliderwidget still triggered auto-checking and the hidden
defaults were still saved, so these approaches were removed.

Status:
Open issue. Current workaround is to remove default values from affected fields.
