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

$navbar[4]['active'] = True;
LBWeb::lbheader($template_title, $helplink, $helptemplate);
echo '<style>
h2 { color: #6dac20; border-bottom: 2px solid #e0e0e0; padding-bottom: 6px; font-size: 1.15em; }
table { border-collapse: collapse; }
table th { background: #f0f0f0; }
table, table td, table th { border: 1px solid #ddd !important; }
a { color: #6dac20; }
</style>';

require 'api-keys.php';
require 'config.php';

function mask($s, $keep = 4) {
  if ($s === '' || $s === NULL) return '<span style="color:#c00;font-weight:bold">LEER / NICHT GESETZT</span>';
  if (strlen($s) <= $keep) return str_repeat('*', strlen($s));
  return htmlspecialchars(substr($s, 0, $keep)).str_repeat('*', min(strlen($s) - $keep, 12));
}
function showval($s) {
  if ($s === '' || $s === NULL) return '<span style="color:#c00;font-weight:bold">LEER / NICHT GESETZT</span>';
  return htmlspecialchars($s);
}
function okwarn($cond, $ok, $warn) {
  if ($cond) return '<span style="color:#080">'.$ok.'</span>';
  return '<span style="color:#c00;font-weight:bold">'.$warn.'</span>';
}
?>

<h2>Gespeicherte Einstellungen (config.php)</h2>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse">
<tr><th align="left">Einstellung</th><th align="left">Wert</th></tr>
<tr><td>Auto Name ($zoename)</td><td><?=showval($zoename)?></td></tr>
<tr><td>Benutzer/E-Mail ($username)</td><td><?=showval($username)?></td></tr>
<tr><td>Passwort ($password)</td><td><?=mask($password, 2)?></td></tr>
<tr><td>VIN ($vin)</td><td><?=showval($vin)?></td></tr>
<tr><td>Land ($country)</td><td><?=showval($country)?></td></tr>
<tr><td>ZOE Phase ($zoeph)</td><td><?=showval($zoeph)?></td></tr>
<tr><td>DB speichern ($save_in_db)</td><td><?=showval($save_in_db)?></td></tr>
<tr><td>Mail bei Ladestand ($mail_bl)</td><td><?=showval($mail_bl)?></td></tr>
<tr><td>Ladeplan verbergen ($hide_cm)</td><td><?=showval($hide_cm)?></td></tr>
<tr><td>Karten-Anbieter ($map_provider)</td><td><?=showval($map_provider)?></td></tr>
<tr><td>Weather API Key</td><td><?=($weather_api_key == '' ? 'nicht gesetzt (optional)' : mask($weather_api_key))?></td></tr>
<tr><td>ABRP Token</td><td><?=($abrp_token == '' ? 'nicht gesetzt (optional)' : mask($abrp_token))?></td></tr>
<tr><td>Cron-Intervall normal ($cron_ncs)</td><td><?=showval($cron_ncs)?> min</td></tr>
<tr><td>Cron-Intervall laden ($cron_acs)</td><td><?=showval($cron_acs)?> min</td></tr>
</table>

<h2>API-Keys (api-keys.php)</h2>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse">
<tr><td>Gigya API Key</td><td><?=mask($gigya_api, 10)?></td></tr>
<tr><td>Kamereon API Key</td><td><?=mask($kamereon_api, 6)?></td></tr>
</table>

<h2>Zwischengespeicherte Daten (session)</h2>
<?php
$sessionfile = dirname(__FILE__).'/session';
if (!file_exists($sessionfile)) {
  echo '<p><span style="color:#c00;font-weight:bold">Keine session-Datei vorhanden.</span> Es wurde noch nie erfolgreich (oder überhaupt) ein Abruf durchgeführt, oder die Ersteinrichtung wurde gerade neu gespeichert.</p>';
} else {
  $session = explode('|', file_get_contents($sessionfile));
  $labels = array(
    0 => 'Datum Gigya-Token-Anforderung (md)',
    1 => 'Gigya JWT Token',
    2 => 'Renault Account-ID',
    3 => 'MD5 letzter Datenabruf',
    4 => 'Zeitstempel letzter Datenabruf',
    7 => 'Kilometerstand',
    8 => 'Datum Statusupdate',
    9 => 'Zeit Statusupdate',
    10 => 'Ladestatus',
    11 => 'Kabelstatus',
    12 => 'Batteriestand (%)',
    14 => 'Reichweite (km)',
    24 => 'Lademodus',
  );
  echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse">';
  echo '<tr><th align="left">Feld</th><th align="left">Wert</th></tr>';
  foreach ($labels as $i => $label) {
    $v = isset($session[$i]) ? $session[$i] : '';
    if ($i == 1) $v = ($v == '') ? '' : substr($v, 0, 20).'… ('.strlen($v).' Zeichen)';
    echo '<tr><td>'.$label.'</td><td>'.showval($v).'</td></tr>';
  }
  echo '</table>';
  echo '<p>Session-Datei zuletzt geändert: '.date('d.m.Y H:i:s', filemtime($sessionfile)).'</p>';
  echo '<p><a href="status.php?clearsession=1" onclick="return confirm(\'Session-Cache wirklich löschen? Beim nächsten Aufruf wird neu bei Renault angemeldet.\')">Session-Cache löschen (erzwingt Neuanmeldung)</a></p>';
}
if (isset($_GET['clearsession'])) {
  @unlink($sessionfile);
  echo '<p><b>Session-Cache gelöscht.</b> <a href="index.php">Jetzt Daten neu abrufen</a></p>';
}
?>

<h2>Schnell-Diagnose</h2>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse">
<tr><td>Zugangsdaten vollständig</td><td><?=okwarn($username != '' && $password != '' && $vin != '', 'OK', 'FEHLT - bitte unter Settings eintragen!')?></td></tr>
<tr><td>config.php beschreibbar</td><td><?=okwarn(is_writable(dirname(__FILE__).'/config.php'), 'OK', 'NICHT beschreibbar - Settings können nicht gespeichert werden')?></td></tr>
<tr><td>Plugin-Verzeichnis beschreibbar</td><td><?=okwarn(is_writable(dirname(__FILE__)), 'OK', 'NICHT beschreibbar - session/log können nicht geschrieben werden')?></td></tr>
<tr><td>PHP cURL verfügbar</td><td><?=okwarn(function_exists('curl_init'), 'OK', 'FEHLT - php-curl installieren')?></td></tr>
<tr><td>Logdatei</td><td><?=(file_exists(dirname(__FILE__).'/renault.log') ? 'vorhanden - <a href="log.php">anzeigen</a>' : 'noch nicht vorhanden (wird beim ersten Abruf über Home erzeugt)')?></td></tr>
</table>

<?php
LBWeb::lbfooter();
?>
