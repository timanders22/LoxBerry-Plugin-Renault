# LoxBerry-Plugin: Renault API

Verbindet Renault-Elektrofahrzeuge (Zoe PH1/PH2, Twingo Electric u. a.) mit dem
Loxone Miniserver – über den LoxBerry. Batteriestand, Reichweite, Ladestatus,
Kilometerstand, Position u. v. m. werden alle 3 Minuten abgerufen und per
**MQTT** (LoxBerry MQTT Gateway) bereitgestellt. Vorklimatisierung und
Ladesteuerung lassen sich aus Loxone heraus auslösen.

Basiert auf [ZoePHP](https://github.com/db-EV/ZoePHP) von db-EV.

## Funktionen

- Abruf von Batterie-/Lade-/Fahrzeugdaten über die My-Renault-API (Gigya/Kamereon)
- MQTT-Publish über das LoxBerry MQTT Gateway (Topics `Renault/<Autoname>/...`)
- Kommandos: Vorklimatisierung, Sofortladen, Ladeplan ein/aus (`?acnow`, `?chargenow`, `?cmon`, `?cmoff`)
- Ladehistorie (CSV) mit Diagramm-Seite
- Reiter **gesp. Konfiguration** (gespeicherte Einstellungen + Schnell-Diagnose),
  **Log** (jeder API-Schritt wird protokolliert) und **Anleitung**
  (Schritt-für-Schritt-Einbindung in Loxone für Einsteiger)

## Version 1.4.1

- Tippfehler korrigiert: MQTT-Topic heißt jetzt `ChargingStatus` (vorher
  `CargingStatus`, ohne h). **Achtung:** in der Loxone-Konfiguration muss das
  Topic entsprechend angepasst werden.
- Menü-Reiter umbenannt: **Einstellungen** (vorher „Settings"),
  **gesp. Konfiguration** (vorher „Konfiguration") und **Ladehistorie**
  (vorher „Load History")

## Version 1.4

- Konfiguration, Ladehistorie und Log **überstehen Plugin-Updates**
  (Sicherung/Wiederherstellung über pre-/postupgrade; zusätzlich dauerhafte
  Konfigurationssicherung im LoxBerry-Config-Verzeichnis)
- Neuer Reiter **Anleitung**: Einbindung in Loxone Schritt für Schritt
  (MQTT Gateway, Topics-Tabelle, Virtuelle Ausgänge, Beispiele, Stolperfallen)
- Einheitliches LoxBerry-Grün auf allen Plugin-Seiten

### Ältere Änderungen

- 1.3: Automatische Wahl des richtigen Renault-Accounts (MYRENAULT statt
  SFDC/SALES), Diagnose der im Account verknüpften VINs bei 404-Fehlern
- 1.2: Reiter Konfiguration + Log, Logging aller API-Schritte, Bugfix:
  fehlgeschlagener Login wird nicht mehr bis Mitternacht gecacht
- 1.1: Aktuelle API-Keys (ZoePHP 2026), nicht-fatale Behandlung entfallener
  API-Endpunkte (hvac-status, batteryTemperature)

## Installation

ZIP über die LoxBerry-Pluginverwaltung installieren, dann unter
**Settings** die My-Renault-Zugangsdaten, VIN und Fahrzeuggeneration eintragen.
Alles Weitere erklärt der Reiter **Anleitung** im Plugin.

## Hinweise

- Max. 1 API-Abruf pro Minute (wird automatisch eingehalten)
- Bei „There is no data for this vin and uid": Datenfreigabe im Fahrzeug
  aktivieren und prüfen, ob die My-Renault-App Live-Daten zeigt
- Die Zugangsdaten bleiben lokal auf dem LoxBerry (config.php, passwortgeschützter Bereich)
