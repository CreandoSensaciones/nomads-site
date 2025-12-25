FIELD TOGGLER MODULE
--------------------

### Überblick

Das Field Toggler Modul erweitert jedes konfigurierbare Drupal-Feld um eine optionale Umschaltfunktion (Toggle).
Der Toggler steuert die Sichtbarkeit des Feld-Widgets im Formular und die Anzeige der Feldwerte im Frontend.

### Optimierungen in dieser Version (Best Practice Tweak)

1.  **Volle Delta-Unterstützung (Multi-Value Fields):** Die Anzeigelogik in `hook_preprocess_field()` wurde erweitert, um den Ein/Aus-Zustand für *jedes* einzelne Element eines Multi-Value-Feldes (jedes Delta) abzurufen und zu respektieren.
2.  **Datenbank-Cleanup:** Die Funktion `hook_entity_delete()` wurde in der `field_toggler.module` implementiert. Dadurch werden alle zugehörigen Toggle-Zustände (`field_toggler_state`-Tabelle) automatisch bereinigt und gelöscht, sobald die übergeordnete Content Entity (z.B. ein Node) gelöscht wird.

### Hauptfunktionen

* **Toggler-Konfiguration pro Feld:** Label, Off Text und On Text werden als Third Party Settings gespeichert.
* **Formular:** Ein Toggle-Checkbox erscheint neben dem Feld-Widget. Bei OFF ist das Widget unsichtbar (Wert bleibt gespeichert).
* **Speicherung:** Eine eigene Datenbanktabelle (`field_toggler_state`) speichert den entity-spezifischen Status.
* **Anzeige (Formatter):** Fünf konfigurierbare Modi steuern, wann Feldwerte und/oder ON/OFF-Texte im Frontend angezeigt werden.

### Architektur

* Third Party Settings auf FieldConfig (Konfiguration).
* Dedicated Service (`FieldTogglerStorage`) für Datenbank-Zugriffe.
* `hook_preprocess_field()` für die Steuerung der Frontend-Anzeige.
* `hook_entity_delete()` für automatisiertes Database Cleanup.