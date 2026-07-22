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

$navbar[5]['active'] = True;
LBWeb::lbheader($template_title, $helplink, $helptemplate);
echo '<style>
h2 { color: #6dac20; border-bottom: 2px solid #e0e0e0; padding-bottom: 6px; font-size: 1.15em; }
table { border-collapse: collapse; }
table th { background: #f0f0f0; }
table, table td, table th { border: 1px solid #ddd !important; }
a { color: #6dac20; }
</style>';

$logfile = dirname(__FILE__).'/renault.log';

if (isset($_GET['clearlog'])) {
  @unlink($logfile);
  @unlink($logfile.'.1');
  echo '<p><b>Log gelöscht.</b></p>';
}
?>

<h2>Log</h2>
<p>
<a href="log.php">Aktualisieren</a> |
<a href="log.php?clearlog=1" onclick="return confirm('Log wirklich löschen?')">Log löschen</a> |
<a href="index.php">Datenabruf jetzt ausführen</a> (schreibt neue Logeinträge)
</p>

<?php
if (!file_exists($logfile)) {
  echo '<p>Noch keine Logdatei vorhanden. Bitte einmal <a href="index.php">Home</a> aufrufen, dann werden hier die einzelnen Schritte des Datenabrufs protokolliert.</p>';
} else {
  // Show the last 300 lines, newest first
  $lines = file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $lines = array_slice($lines, -300);
  $lines = array_reverse($lines);
  echo '<p>Logdatei: renault.log ('.round(filesize($logfile) / 1024, 1).' kB) &ndash; neueste Einträge zuerst, max. 300 Zeilen</p>';
  echo '<pre style="background:#f4f4f4;border:1px solid #ccc;padding:10px;max-height:600px;overflow:auto;font-size:12px">';
  foreach ($lines as $line) {
    $h = htmlspecialchars($line);
    if (strpos($line, '[ERROR]') !== FALSE) echo '<span style="color:#c00;font-weight:bold">'.$h.'</span>'."\n";
    elseif (strpos($line, '[WARN]') !== FALSE) echo '<span style="color:#b60">'.$h.'</span>'."\n";
    else echo $h."\n";
  }
  echo '</pre>';
}

LBWeb::lbfooter();
?>
