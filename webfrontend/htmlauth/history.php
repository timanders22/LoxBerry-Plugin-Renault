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
$navbar[2]['active'] = True;

 
LBWeb::lbheader($template_title, $helplink, $helptemplate);





session_cache_limiter('nocache');
require 'api-keys.php';
require 'config.php';

//MQTT Start-----------------------------------------------
require_once "loxberry_io.php";
require_once "phpMQTT/phpMQTT.php";
// Get the MQTT Gateway connection details from LoxBerry
$creds = mqtt_connectiondetails();
// MQTT requires a unique client id
$client_id = uniqid(gethostname()."_client");
//MQTT ENDE-----------------------------------------------

if (file_exists('lng/'.$country.'.php')) require 'lng/'.$country.'.php';
else require 'lng/EN.php';
header('Content-Type: text/html; charset=utf-8');
// ZoePHP 20260520: one Europe-wide Gigya key ($gigya_api from api-keys.php),
// country-specific key selection removed.

$date_today = date_create('now');
$date_today = date_format($date_today, 'md');
$update_ok = FALSE;

//Request cached login
$session = file_get_contents('session');
$session = explode('|', $session);

//Retrieve new Gigya token if the session file is outdated
if ($session[0] !== $date_today) {
  //Login Gigya
  $update_ok = TRUE;
  $postData = array(
    'ApiKey' => $gigya_api,
    'loginId' => $username,
    'password' => $password,
    'include' => 'data',
    'sessionExpiration' => 60
  );
  $ch = curl_init('https://accounts.eu1.gigya.com/accounts.login');
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));  
  $responseData = json_decode($response, TRUE);
  $oauth_token = $responseData['sessionInfo']['cookieValue'];

  //Request Gigya JWT token
  $postData = array(
    'login_token' => $oauth_token,
    'ApiKey' => $gigya_api,
    'fields' => 'data.personId,data.gigyaDataCenter',
    'expiration' => 87000
  );
  $ch = curl_init('https://accounts.eu1.gigya.com/accounts.getJWT');
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $session[1] = $responseData['id_token'];
  $session[0] = $date_today;
}

//Request charging history
$postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
);
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charges?country='.$country.'&start='.date("Ymd", strtotime("-1 months")).'&end='.date("Ymd"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);

$response = curl_exec($ch);

if ($response === FALSE) die(curl_error($ch));
$responseData = json_decode($response, TRUE);
$data = array();
if (isset($responseData['data']['attributes']['charges'])) $data = $responseData['data']['attributes']['charges'];
// ZoePHP 20260520: sort charges by start date (newest first) instead of array_reverse
usort($data, function($a, $b) {
    return strcmp($b['chargeStartDate'], $a['chargeStartDate']);
});


// Be careful about the required namespace on inctancing new objects:
$mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'],  $creds['brokerport'], $client_id);
    if( $mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass'] ) ) {

// ZoePHP 20260520: obsolete requests to the retired Renault app-config AWS bucket removed.

//Output
echo '<HTML>'."\n".'<HEAD>'."\n".'<LINK REL="stylesheet" HREF="stylesheet.css">'."\n".'<META NAME="viewport" CONTENT="width=device-width, initial-scale=1.0">'."\n".'<TITLE>'.$zoename.'</TITLE>'."\n".'</HEAD>'."\n".'<BODY>'."\n".'<DIV ID="container">'."\n".'<MAIN>'."\n".'<ARTICLE>'."\n".'<TABLE>'."\n".'<TR ALIGN="left"><TH>'.$zoename.'</TH></TR>'."\n".'<TR><TD COLSPAN="2"><HR></TD></TR>'."\n";
for ($i = 0; $i < count($data); $i++) {
  if (!empty($data[$i]['chargeStartDate']) && !empty($data[$i]['chargeEndDate']) && !empty($data[$i]['chargeEnergyRecovered'])) {
    $s = date_create_from_format(DATE_ISO8601, $data[$i]['chargeStartDate'], timezone_open('UTC'));
    $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
    $sts = $s;
    $sd = date_format($s, 'd.m.Y');
    $st = date_format($s, 'H:i');
    $s = date_create_from_format(DATE_ISO8601, $data[$i]['chargeEndDate'], timezone_open('UTC'));
    $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
    $ets = $s;
    $ed = date_format($s, 'd.m.Y');
    $et = date_format($s, 'H:i');
    $cd = date_diff($sts, $ets);
    $cdm = date_interval_format($cd, '%a') * 24 * 60;
    $cdm += date_interval_format($cd, '%h') * 60;
    $cdm += date_interval_format($cd, '%i');
    echo '<TR><TD>'.$lng['Start'].':</TD><TD>'.$sd.' '.$st.'</TD></TR>'."\n";
    if ($zoeph == 1) {
      echo '<TR><TD>'.$lng['Charging'].':</TD><TD>'.$data[$i]['chargeStartBatteryLevel'].' % '.$lng['to'].' '.$data[$i]['chargeEndBatteryLevel'].' % '.$lng['in'].' '.$cdm.' '.$lng['minutes'].'</TD></TR>'."\n";
      $s = $data[$i]['chargeStartInstantaneousPower']/1000;
      echo '<TR><TD>'.$lng['Power'].':</TD><TD>'.$data[$i]['chargePower'].' ('.$s.' kW)</TD></TR>'."\n";
	
	$mqtt->publish("Renault/$zoename/chargeStartBatteryLevel(Prozent)", @$data[0]['chargeStartBatteryLevel'], 0, 1);
	$mqtt->publish("Renault/$zoename/chargeEndBatteryLevel(Prozent)", @$data[0]['chargeEndBatteryLevel'], 0, 1);
	$mqtt->publish("Renault/$zoename/chargeDuration(min)", $cdm, 0, 1);
	$mqtt->publish("Renault/$zoename/chargePowerAverage(kW)", round(@$data[0]['chargeEnergyRecovered'] * 60 / ($cdm+0.0000001), 2), 0, 1);

    } else {
      echo '<TR><TD>'.$lng['Charging'].':</TD><TD>'.round($data[$i]['chargeEnergyRecovered'], 2).' kWh '.$lng['in'].' '.$cdm.' '.$lng['minutes'].'</TD></TR>'."\n";
      //echo '<TR><TD>'.$lng['AverageChargingPower'].':</TD><TD>'.round($data[$i]['chargeEnergyRecovered'] * 60 / ($cdm+0.0000001), 2).' kW</TD></TR>'."\n";

	$mqtt->publish("Renault/$zoename/chargePowerAverage(kW)", round(@$data[0]['chargeEnergyRecovered'] * 60 / ($cdm+0.0000001), 2), 0, 1);
	$mqtt->publish("Renault/$zoename/chargeEnergyRecovered(kWh)", @$data[0]['chargeEnergyRecovered'], 0, 1);
	$mqtt->publish("Renault/$zoename/chargeDuration(min)", $cdm, 0, 1);
    }
    echo '<TR><TD>'.$lng['Status'].':</TD><TD>'.$data[$i]['chargeEndStatus'].' '.$lng['at'].' '.$ed.' '.$et.'</TD></TR>'."\n".'<TR><TD COLSPAN="2"><HR></TD></TR>'."\n";
	$mqtt->publish("Renault/$zoename/chargeEndStatus", @$data[0]['chargeEndStatus'], 0, 1);
  }
}
echo '<TR><TD COLSPAN="2"><A HREF="./">'.$lng['Back'].'</A></TD></TR>'."\n".'</TABLE>'."\n".'</ARTICLE>'."\n";
echo '</MAIN>'."\n".'</DIV>'."\n".'</BODY>'."\n".'</HTML>';
curl_close($ch);

        //$mqtt->publish("Renault/$zoename/BattSOC", $session[12], 0, 1);
	$mqtt->publish("Renault/$zoename/chargeStartInstantaneousPower", @$data[0]['chargeStartInstantaneousPower'], 0, 1);
	//$mqtt->publish("Renault/$zoename/chargeStartDate", $data[0]['chargeStartDate'], 0, 1);
	//$mqtt->publish("Renault/$zoename/chargeStartTime", $st, 0, 1);
	//$mqtt->publish("Renault/$zoename/chargeEndDate", $data[0]['chargeEndDate'], 0, 1);
	//$mqtt->publish("Renault/$zoename/chargeEndTime", $et, 0, 1);
	
	

 //print_r($data);





        $mqtt->close();
    } else {
        echo "MQTT connection failed";
    }


//Cache new Gigya token
if ($update_ok === TRUE) {
  $session = implode('|', $session);
  file_put_contents('session', $session);
}
LBWeb::lbfooter();
?>
