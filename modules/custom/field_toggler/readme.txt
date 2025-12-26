FIELD TOGGLER MODULE
--------------------

### Overview

The Field Toggler module adds an optional toggle to configurable Drupal fields.
The toggle controls field widget visibility in the form and the display of field values on the frontend.

### Improvements in this version (Best Practice Tweak)

1.  **Full delta support (multi-value fields):** The display logic in `hook_preprocess_field()` was extended to load and respect the on/off state for each item (delta) of a multi-value field.
2.  **Database cleanup:** The `hook_entity_delete()` implementation in `field_toggler.module` removes related toggle state records (`field_toggler_state` table) when the parent content entity (e.g. a node) is deleted.

### Core features

* **Per-field toggle configuration:** Label, Off text, and On text are stored as third-party settings.
* **Form:** A toggle checkbox appears next to the field widget. When OFF, the widget is hidden (value remains stored).
* **Storage:** A dedicated database table (`field_toggler_state`) stores entity-specific state.
* **Display (formatter):** Five configurable modes determine when field values and/or ON/OFF texts are shown on the frontend.

### Architecture

* Third-party settings on FieldConfig (configuration).
* Dedicated service (`FieldTogglerStorage`) for database access.
* `hook_preprocess_field()` for frontend display control.
* `hook_entity_delete()` for automated database cleanup.
