<?php
require_once "loxberry_web.php";

$L = LBSystem::readlanguage("language.ini");
$template_title = "Renault-Api";
$helplink = "http://www.loxwiki.eu:80/x/2wzL";
$helptemplate = "help.html";

$navbar[1]['Name'] = "Home";
$navbar[1]['URL'] = 'index.php';
$navbar[2]['Name'] = "Load History";
$navbar[2]['URL'] = 'history.php';
$navbar[3]['Name'] = "Settings";
$navbar[3]['URL'] = 'ersteinrichtung.php';
$navbar[4]['Name'] = "Konfiguration";
$navbar[4]['URL'] = 'status.php';
$navbar[5]['Name'] = "Log";
$navbar[5]['URL'] = 'log.php';
$navbar[6]['Name'] = "Anleitung";
$navbar[6]['URL'] = 'anleitung.php';

$navbar[6]['active'] = True;
LBWeb::lbheader($template_title, $helplink, $helptemplate);

require 'config.php';
$rza = htmlspecialchars($zoename, ENT_QUOTES, 'UTF-8');
$host = htmlspecialchars($_SERVER['HTTP_HOST'] ?? '<loxberry-ip>', ENT_QUOTES, 'UTF-8');
?>
<style>
.rna-wrap { max-width: 940px; margin: 0 auto; font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; color: #333; }
.rna-wrap h2 { color: #6dac20; margin: 24px 0 10px; font-size: 1.15em; border-bottom: 2px solid #e0e0e0; padding-bottom: 6px; }
.rna-step { margin: 10px 0; padding: 10px 14px; background: #fafafa; border-left: 4px solid #6dac20; border-radius: 0 8px 8px 0; }
.rna-tbl { border-collapse: collapse; margin: 8px 0; }
.rna-tbl th, .rna-tbl td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; font-size: 0.9em; }
.rna-tbl th { background: #f0f0f0; }
.rna-mono { font-family: ui-monospace, monospace; background: #f5f5f5; padding: 2px 6px; border-radius: 4px; }
.rna-info { background: #e3f2fd; border: 1px solid #90caf9; font-size: 0.9em; border-radius: 8px; padding: 10px 14px; margin: 12px 0; }
</style>
<div class="rna-wrap">

<h2>Einbindung in Loxone &mdash; Schritt f&uuml;r Schritt</h2>
<p>Das Plugin holt regelm&auml;&szlig;ig (per Cron alle 3 Minuten) die Daten Ihres Renault
(Batterie, Reichweite, Ladestatus, Position &hellip;) und ver&ouml;ffentlicht sie per <b>MQTT</b>
&uuml;ber das LoxBerry MQTT Gateway. Von dort holt sie sich der Miniserver automatisch.
Kommandos (Vorklimatisierung, Laden starten) sendet der Miniserver &uuml;ber einen Virtuellen Ausgang.</p>

<div class="rna-step"><b>Schritt 1: Plugin einrichten</b><br><br>
Im Reiter <b>Settings</b> die Zugangsdaten des My-Renault-Kontos eintragen: E-Mail, Passwort,
<b>VIN</b> (Fahrgestellnummer, steht in der My-Renault-App), Land und die Fahrzeug-Generation
(<b>PH1</b> = Zoe bis ca. 2019, <b>PH2</b> = ab 2019 sowie Twingo Electric).<br><br>
Danach im Reiter <b>Konfiguration</b> pr&uuml;fen, ob alles gr&uuml;n ist, und einmal <b>Home</b> aufrufen &mdash;
wenn dort Kilometerstand und Batteriestand erscheinen, funktioniert die Verbindung zu Renault.
Falls nicht: Reiter <b>Log</b> ansehen, dort steht der genaue Fehler.</div>

<div class="rna-step"><b>Schritt 2: MQTT Gateway pr&uuml;fen</b><br><br>
LoxBerry-Men&uuml; &rarr; <b>MQTT Gateway</b> &ouml;ffnen. Nach dem ersten erfolgreichen Abruf erscheinen
unter <i>Incoming Overview</i> die Themen (Topics) des Plugins, z.&nbsp;B.:<br>
<span class="rna-mono">Renault/<?= $rza ?>/BattSOC</span> &nbsp;
<span class="rna-mono">Renault/<?= $rza ?>/Range</span> &nbsp;
<span class="rna-mono">Renault/<?= $rza ?>/CargingStatus</span><br><br>
Damit der Miniserver die Werte bekommt: im MQTT Gateway unter <i>Subscriptions</i> das Thema
<span class="rna-mono">Renault/#</span> abonnieren und bei den gew&uuml;nschten Topics das H&auml;kchen
<i>&bdquo;An Miniserver senden&ldquo;</i> setzen. Das Gateway legt die Virtuellen Eing&auml;nge am
Miniserver <b>automatisch</b> an &mdash; nichts von Hand anlegen!</div>

<div class="rna-step"><b>Schritt 3: Die wichtigsten Werte und ihre Bedeutung</b><br><br>
<table class="rna-tbl">
<tr><th>MQTT-Topic</th><th>Bedeutung</th><th>Beispielwert</th></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/BattSOC</span></td><td>Batteriestand in %</td><td>78</td></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/Range</span></td><td>Reichweite in km</td><td>212</td></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/CargingStatus</span></td><td>L&auml;dt gerade? 1 = ja, 0 = nein</td><td>1</td></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/CableStatus</span></td><td>Kabel eingesteckt? 1 = ja, 0 = nein</td><td>1</td></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/ChargingTime</span></td><td>Restladezeit in Minuten</td><td>95</td></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/ChargingEffekt</span></td><td>Ladeleistung in kW</td><td>11</td></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/Mileage</span></td><td>Kilometerstand</td><td>23415</td></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/ChargeMode</span></td><td>Lademodus (always_charging = sofort laden, schedule_mode = Ladeplan)</td><td>always_charging</td></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/HvAcStatusBin</span></td><td>Klimatisierung aktiv? 1/0 (nur PH2)</td><td>0</td></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/GPS-Latitude</span> / <span class="rna-mono">GPS-Longitude</span></td><td>Fahrzeugposition (nur PH2)</td><td>49.87&hellip;</td></tr>
<tr><td><span class="rna-mono">Renault/<?= $rza ?>/LastDataRetrieval</span></td><td>Uhrzeit des letzten Abrufs (HHMM)</td><td>1830</td></tr>
</table>
<i>Hinweis:</i> &bdquo;CargingStatus&ldquo; (ohne h) ist kein Tippfehler dieser Anleitung, sondern
der historisch gewachsene Topic-Name des Plugins &mdash; er bleibt aus Kompatibilit&auml;tsgr&uuml;nden so.</div>

<div class="rna-step"><b>Schritt 4: Kommandos senden (Virtueller Ausgang)</b><br><br>
In <b>Loxone Config</b>: Miniserver anklicken &rarr; <i>Virtuelle Ausg&auml;nge</i> &rarr; neuen
<i>Virtuellen Ausgang</i> anlegen:
<table class="rna-tbl">
<tr><th>Eigenschaft</th><th>Wert</th></tr>
<tr><td>Adresse</td><td><span class="rna-mono">http://admin:IHR-LOXBERRY-PASSWORT@<?= $host ?></span></td></tr>
</table>
Darunter je Kommando einen <i>Virtuellen Ausgang Befehl</i> anlegen (Feld &bdquo;Befehl bei EIN&ldquo;):
<table class="rna-tbl">
<tr><th>Funktion</th><th>Befehl bei EIN</th></tr>
<tr><td>Vorklimatisierung starten (21&nbsp;&deg;C)</td><td><span class="rna-mono">/admin/plugins/Renault_API/index.php?acnow</span></td></tr>
<tr><td>Sofort laden starten</td><td><span class="rna-mono">/admin/plugins/Renault_API/index.php?chargenow</span></td></tr>
<tr><td>Ladeplan aktivieren</td><td><span class="rna-mono">/admin/plugins/Renault_API/index.php?cmon</span></td></tr>
<tr><td>Ladeplan deaktivieren (sofort laden erlauben)</td><td><span class="rna-mono">/admin/plugins/Renault_API/index.php?cmoff</span></td></tr>
</table>
&bdquo;admin&ldquo; und das Passwort sind die Zugangsdaten der LoxBerry-Weboberfl&auml;che
(die Plugin-Seiten sind passwortgesch&uuml;tzt). In der Visualisierung verbindet man die Befehle
z.&nbsp;B. mit einem Taster-Baustein (&bdquo;Vorklimatisieren&ldquo;).</div>

<div class="rna-step"><b>Schritt 5: Beispiel f&uuml;r die Programmierseite</b><br><br>
<b>Anzeige in der App</b> (Status-Baustein): Eingang v1 mit dem MQTT-Eingang
<span class="rna-mono">BattSOC</span>, v2 mit <span class="rna-mono">Range</span> verbinden.
Statustext: <span class="rna-mono">Auto: &lt;v1.0&gt; % geladen, Reichweite &lt;v2.0&gt; km</span> &mdash;
H&auml;kchen &bdquo;Visualisierung&ldquo; setzen, fertig ist die App-Kachel.<br><br>
<b>Benachrichtigung &bdquo;Auto vollgeladen&ldquo;:</b> Schwellwertschalter an
<span class="rna-mono">BattSOC</span> (Ein-Schwelle 79,5 / Aus-Schwelle 79) UND
<span class="rna-mono">CargingStatus</span> = 1 &rarr; Benachrichtigungs-Baustein
(&bdquo;Auto ist bei 80&nbsp;% &mdash; Ladung beenden?&ldquo;).<br><br>
<b>PV-&Uuml;berschussladen:</b> Bei PV-&Uuml;berschuss den Befehl <i>Ladeplan deaktivieren</i>
(<span class="rna-mono">?cmoff</span>) senden &rarr; das Auto l&auml;dt sofort; bei Wolken wieder
<span class="rna-mono">?cmon</span> &rarr; das Auto wartet auf den Ladeplan.</div>

<div class="rna-step"><b>Stolperfallen aus der Praxis</b><br><br>
&bull; Renault erlaubt <b>max. 1 Datenabruf pro Minute</b> &mdash; das Plugin h&auml;lt das automatisch ein
(Meldung &bdquo;Abruf &uuml;bersprungen&ldquo; im Log ist also normal).<br>
&bull; Nach dem Speichern der Settings wird der Anmelde-Cache geleert und beim n&auml;chsten Aufruf neu
angemeldet &mdash; der erste Abruf kann daher einen Moment dauern.<br>
&bull; Erscheint im Log &bdquo;There is no data for this vin and uid&ldquo;, obwohl die VIN stimmt:
Datenfreigabe im Auto aktivieren (My-Renault-App bzw. Fahrzeugmen&uuml; &rarr; Datenschutz) und pr&uuml;fen,
ob die App selbst Live-Daten zeigt.<br>
&bull; Die MQTT-Topics enthalten den <b>Auto-Namen aus den Settings</b> (aktuell:
&bdquo;<?= $rza ?>&ldquo;). Wird er ge&auml;ndert, &auml;ndern sich alle Topics &mdash; dann die
Subscriptions im MQTT Gateway anpassen.</div>

<div class="rna-info"><b>Kurz-Checkliste:</b> Settings ausf&uuml;llen &rarr; Home einmal aufrufen &rarr;
Log pr&uuml;fen (&bdquo;Batterie-Status OK&ldquo;) &rarr; MQTT Gateway: <span class="rna-mono">Renault/#</span>
abonnieren &rarr; Werte in Loxone verwenden &rarr; optional Virtuellen Ausgang f&uuml;r Klima/Laden anlegen.</div>

</div>
<?php
LBWeb::lbfooter();
?>
