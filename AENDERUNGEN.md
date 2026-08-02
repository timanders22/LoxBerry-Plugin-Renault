# Änderungen

## 1.6.0

Oberfläche auf den Loxberry-Plugin-Hausstandard umgebaut: statt sechs
Einzelseiten in der Navigationsleiste jetzt sechs Reiter auf einer Seite —
Einstellungen, MQTT, Einbindung in Loxone, Test, Ladehistorie, Logdateien.

### Achtung: einmalige Anpassung in Loxone nötig

Die Aufgaben sind jetzt sauber getrennt:

| Datei | Aufgabe |
|---|---|
| `webfrontend/htmlauth/index.php` | ausschließlich Bedienoberfläche |
| `webfrontend/htmlauth/abruf.php` | Datenabruf, vom Cron aufgerufen |
| `webfrontend/html/index.php` | Endpunkt für Loxone, ohne Anmeldung, mit Token |

Dadurch ändern sich die Adressen für die virtuellen Ausgänge:

    bisher: /admin/plugins/Renault_API/index.php?acnow
    neu:    /plugins/Renault_API/index.php?token=<TOKEN>&aktion=acnow

Das Token steht im Reiter *Einbindung in Loxone* und wird beim ersten Aufruf
der Oberfläche selbst erzeugt. Vorteil: der Miniserver braucht keine
LoxBerry-Zugangsdaten mehr im virtuellen Ausgang.

Der Cron ruft jetzt `abruf.php` statt `index.php` auf — das passiert bei der
Installation automatisch, es ist nichts zu tun.

### Behoben

- **Einstellungen wurden roh in PHP-Quelltext geschrieben.** Ein Apostroph im
  Passwort zerlegte `config.php`; darüber hinaus landete der eingegebene Wert
  unmaskiert als ausführbarer Code. Jetzt über `var_export()`, und die neue
  Datei wird vor dem Übernehmen auf Gültigkeit geprüft.
- **Jedes Speichern setzte alle übrigen Einstellungen zurück** — Aufzeichnung,
  Kartenanbieter, Cron-Intervalle und weitere fielen auf Vorgabewerte. Jetzt
  wird die vollständige Konfiguration geschrieben.
- **Die Aufzeichnung nach `database.csv` war unerreichbar**, weil der Schalter
  bei jedem Speichern auf `N` zurückgesetzt wurde. Sie ist jetzt im Reiter
  Einstellungen bedienbar.
- Eingaben werden geprüft: VIN auf 17 zulässige Zeichen, Fahrzeugname ohne
  `/`, `#` und `+` (Sonderzeichen in MQTT-Themen), Phase auf 1 oder 2.
- Ein leeres Passwortfeld behält das gespeicherte Passwort, statt es zu löschen.
- `config.php` wird mit Rechten 0600 geschrieben.
- `DatenWrite.php` entfernt — die Datei war über die URL erreichbar und hat den
  Schreibfehler enthalten.

Entfallen, weil ihr Inhalt jetzt in Reitern steht: `ersteinrichtung.php`,
`status.php`, `log.php`, `debug.php`, `anleitung.php`.
