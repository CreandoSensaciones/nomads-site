FIELD GROUP TOGGLER MODULE
--------------------------

### Überblick

Das Field Group Toggler Modul erweitert jede Field Group im Drupal-System um eine optionale Umschaltfunktion (ON/OFF). Der Toggler steuert die Sichtbarkeit der gesamten Gruppe in Formularen und im Frontend, ohne dass Feldwerte verloren gehen.

### Optimierungen in dieser Version (Best Practice Tweak)

1.  **Datenbank-Cleanup:** Die Funktion `hook_entity_delete()` wurde in der `field_group_toggler.module` implementiert. Dadurch werden alle zugehörigen Toggle-Zustände (`field_group_toggler_state`-Tabelle) automatisch bereinigt und gelöscht, sobald die übergeordnete Content Entity (z.B. ein Node) gelöscht wird. Dies gewährleistet die Sauberkeit der Datenbank.

### Hauptfunktionen

* **Konfiguration pro Field Group:** Toggler Label, Off Text und On Text werden über Third Party Settings auf der Display-Konfiguration gespeichert.
* **Formular:** Ein Toggle erscheint oberhalb jeder Field Group. Die Sichtbarkeit der Gruppenfelder wird via Drupal `#states` geregelt.
* **Frontend (Entity View):** Fünf konfigurierbare Modi steuern, wann die Gruppenfelder und/oder ON/OFF-Texte angezeigt werden.
* **Unterstützung:** Unterstützt alle Field Groups, die vom Modul `field_group` bereitgestellt werden.

### Architektur

* Third Party Settings auf EntityFormDisplay und EntityViewDisplay (Konfiguration).
* Dedizierte Hooks für Field Group Integration: `hook_field_group_form_process` und `hook_field_group_pre_render`.
* Dedicated Service (`FieldGroupTogglerStorage`) für Datenbank-Zugriffe.
* `hook_entity_delete()` für automatisiertes Database Cleanup.