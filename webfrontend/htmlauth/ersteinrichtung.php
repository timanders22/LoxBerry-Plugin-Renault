
<?php
require_once "loxberry_web.php";
  
// This will read your language files to the array $L
$L = LBSystem::readlanguage("language.ini");
$template_title = "Renault-Api";
$helplink = "http://www.loxwiki.eu:80/x/2wzL";
$helptemplate = "help.html";
 $navbar[1]['Name'] = "Home";
$navbar[1]['URL'] = 'index.php';
 
$navbar[2]['Name'] = "Ladehistorie";
$navbar[2]['URL'] = 'history.php';
 
$navbar[3]['Name'] = "Einstellungen";
$navbar[3]['URL'] = 'ersteinrichtung.php';

$navbar[4]['Name'] = "gesp. Konfiguration";
$navbar[4]['URL'] = 'status.php';

$navbar[5]['Name'] = "Log";
$navbar[5]['URL'] = 'log.php';
$navbar[6]['Name'] = "Anleitung";
$navbar[6]['URL'] = 'anleitung.php';


// Activate the second element
$navbar[3]['active'] = True;
LBWeb::lbheader($template_title, $helplink, $helptemplate);
 
// This is the main area for your plugin
?>
<p><?=$L['SOMEAREA.WELCOMEMESSAGE']?></p>
<p><?=$L['SOMEAREA.HOWTOMESSAGE']?></p>
 




<form action="DatenWrite.php" method="post">
<fieldset>


  
<table align=center>
<font color='#6dac20'><span style="font-size:10pt">

<center>
<table>
<table align=center>
<tr><td> <width="400"><p><font color='#6dac20'><span style="font-size:18pt">Willkommen zur Ersteinrichtung!<br></td></tr>

<tr><td align=center><p><font color='#6dac20'><span style="font-size:12pt">Auto Name ( z.B.: Renault-Traffic)<br></td>
<tr><td align=center><input type="Text" name="zoename"></p></td></tr>
<tr><td align=center><p><font color='#6dac20'><span style="font-size:12pt">Renault Type (PH1 Baujahr bis ca 2019 PH2 ab 2019)<br></td>
<tr><td align=center>
<select name="zoeph">
   <option>1</option>
   <option>2</option>
   </select>
<br></td>


<tr><td align=center><p><font color='#6dac20'><span style="font-size:12pt">User/E-Mail<br></td>
<tr><td align=center><input type="Text" name="username"></p></td></tr>
<tr><td align=center><p><font color='#6dac20'><span style="font-size:12pt">Passwort<br></td>
<tr><td align=center><input type="Text" name="password"></p></td></tr>
<tr><td align=center><p><font color='#6dac20'><span style="font-size:12pt">VIN Nummer (Fahrgestellnummer)<br></td>
<tr><td align=center><input type="Text" name="vin"></p></td></tr>
<tr><td align=center><p><font color='#6dac20'><span style="font-size:12pt">Land<br></td>



<tr><td align=center>
<select name="country">
   <option>AT</option>
   <option>DE</option>
   <option>IT</option>
   <option>GB</option>
   <option>SE</option>
</select>
<br></td>

<table align=center>
<tr><td width="400"> <input type="submit" name="" value="Daten senden"></td></tr>
</table>
</center>
</form>






<?php 
// Finally print the footer 
LBWeb::lbfooter();
?>