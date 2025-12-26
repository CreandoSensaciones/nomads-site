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
