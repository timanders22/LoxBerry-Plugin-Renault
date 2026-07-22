<?php
require_once "loxberry_web.php";

// This will read your language files to the array $L
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


// Activate the second element
$navbar[1]['active'] = True;
LBWeb::lbheader($template_title, $helplink, $helptemplate);



require_once "loxberry_io.php";
require_once "phpMQTT/phpMQTT.php";
 
// Get the MQTT Gateway connection details from LoxBerry
$creds = mqtt_connectiondetails();
 
// MQTT requires a unique client id
$client_id = uniqid(gethostname()."_client");

session_cache_limiter('nocache');
require 'api-keys.php';
require 'config.php';
require 'logger.php';

//Check credentials before doing anything
if (empty($username) || empty($password) || empty($vin)) {
  renault_log('ERROR', 'Zugangsdaten unvollständig (username/password/vin leer) - bitte Settings ausfüllen.');
}
if (file_exists('lng/'.$country.'.php')) require 'lng/'.$country.'.php';
else require 'lng/EN.php';
// ZoePHP 20260520: one Europe-wide Gigya key ($gigya_api from api-keys.php),
// country-specific key selection removed.

//Evaluate parameters
if (isset($_GET['cron']) || (isset($argv[1]) && $argv[1] == 'cron')) {
  header('Content-Type: text/plain; charset=utf-8');
  $cmd_cron = TRUE;
} else {
  header('Content-Type: text/html; charset=utf-8');
  $cmd_cron = FALSE;
}
if (isset($_GET['acnow']) || (isset($argv[1]) && $argv[1] == 'acnow')) $cmd_acnow = TRUE;
else $cmd_acnow = FALSE;
if (isset($_GET['chargenow']) || (isset($argv[1]) && $argv[1] == 'chargenow')) $cmd_chargenow = TRUE;
else $cmd_chargenow = FALSE;
if (isset($_GET['cmon']) || (isset($argv[1]) && $argv[1] == 'cmon')) $cmd_cmon = TRUE;
else {
  $cmd_cmon = FALSE;
  if (isset($_GET['cmoff']) || (isset($argv[1]) && $argv[1] == 'cmoff')) $cmd_cmoff = TRUE;
  else $cmd_cmoff = FALSE;
}

$date_today = date_create('now');
$date_today = date_format($date_today, 'md');
$timestamp_now = date_create('now');
$timestamp_now = date_format($timestamp_now, 'YmdHi');

/**Retrieve cached data
 * Session array:
 * 0: Date Gigya JWT Token request (md)
 * 1: Gigya JWT Token
 * 2: Renault account id
 * 3: MD5 hash of the last data retrieval
 * 4: Timestamp of the last data retrieval (YmdHi)
 * 5: Action done when reaching battery level (Y/N)
 * 6: Car is charging (Y/N)
 * 7: Mileage
 * 8: Date status update
 * 9: Time status update
 * 10: Charging status
 * 11: Cable status
 * 12: Battery level
 * 13: Battery temperature (Ph1) / battery capacity (Ph2)
 * 14: Range in km
 * 15: Charging time
 * 16: Charging effect
 * 17: Outside temperature (Ph1) / GPS-Latitude (Ph2)
 * 18: GPS-Longitude (Ph2)
 * 19: GPS date (Ph2, d.m.Y)
 * 20: GPS time (Ph2, H:i)
 * 21: Setting battery level for mail function
 * 22: Outside temperature (Ph2, openweathermap API)
 * 23: Weather condition (Ph2, openweathermap API)
 * 24: Chargemode
 */
$session = file_get_contents('session');
if ($session !== FALSE) $session = explode('|', $session);
else $session = array('0000', '', '', '', '202001010000', 'N', 'N', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '80','','','');

//Retrieve setting battery level for mail function
if (isset($_POST['bl']) && is_numeric($_POST['bl']) && $_POST['bl'] >= 1 && $_POST['bl'] <= 99) {
  if ($_POST['bl'] > $session[21]) $session[5] = 'N';
  $session[21] = $_POST['bl'];
}

//Checking cron time interval
if ($cmd_cron == TRUE) {
  $s = date_create_from_format('YmdHi', $session[4]);
  if ($session[10] == 1 || $session[6] == 'Y') date_add($s, date_interval_create_from_date_string($cron_acs.' minutes'));
  else date_add($s, date_interval_create_from_date_string($cron_ncs.' minutes'));
  $s = date_format($s, 'YmdHi');
  if ($timestamp_now < $s) exit('INTERVAL NOT REACHED');
}

//Max one API request per minute
$s = date_create_from_format('YmdHi', $session[4]);
date_add($s, date_interval_create_from_date_string('1 minutes'));
$s = date_format($s, 'YmdHi');
if ($timestamp_now < $s) $update_ok = FALSE;
else $update_ok = TRUE;

//Retrieve new Gigya token if the date has changed since last request
if (empty($session[1]) || $session[0] !== $date_today) {
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
  renault_log_api('Gigya Login (accounts.login)', $ch, $response);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  if (isset($responseData['errorCode']) && $responseData['errorCode'] != 0) {
    renault_log('ERROR', 'Gigya Login fehlgeschlagen: errorCode='.$responseData['errorCode'].' ('.@$responseData['errorDetails'].') - Benutzername/Passwort prüfen!');
  }
  $personId = @$responseData['data']['personId'];
  $oauth_token = @$responseData['sessionInfo']['cookieValue'];
  if (empty($personId)) renault_log('ERROR', 'Gigya Login: keine personId erhalten - Login bei Renault nicht erfolgreich.');
  if (empty($oauth_token)) renault_log('ERROR', 'Gigya Login: kein Session-Token (cookieValue) erhalten.');

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
  renault_log_api('Gigya JWT (accounts.getJWT)', $ch, $response);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  if (empty($responseData['id_token'])) {
    // Do NOT cache a failed login until tomorrow - retry on next call
    renault_log('ERROR', 'Gigya JWT: kein id_token erhalten. Antwort: '.substr(preg_replace('/\s+/', ' ', $response), 0, 300));
    $session[1] = '';
    $session[0] = '0000';
  } else {
    renault_log('INFO', 'Gigya JWT Token erfolgreich erhalten.');
    $session[1] = $responseData['id_token'];
    $session[0] = $date_today;
  }
}

//Request Renault account id if not cached
if (empty($session[2])) {
  //Request Kamereon account id
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1],
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/persons/'.$personId.'?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  renault_log_api('Kamereon Account-ID (persons)', $ch, $response);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $accounts = @$responseData['accounts'];
  if (is_array($accounts)) {
    foreach ($accounts as $acc) renault_log('INFO', 'Renault-Account gefunden: '.@$acc['accountId'].' (Typ: '.@$acc['accountType'].', Status: '.@$acc['accountStatus'].')');
    // Prefer the MYRENAULT account - some users have a second (e.g. SFDC) account
    // that knows nothing about the car and answers 404 "no data for this vin and uid".
    foreach ($accounts as $acc) {
      if (@$acc['accountType'] == 'MYRENAULT') { $session[2] = $acc['accountId']; break; }
    }
    if (empty($session[2])) $session[2] = @$accounts[0]['accountId'];
  }
  if (empty($session[2])) renault_log('ERROR', 'Keine Account-ID erhalten. Antwort: '.substr(preg_replace('/\s+/', ' ', $response), 0, 300));
  else renault_log('INFO', 'Verwende Account-ID: '.$session[2]);
}

//Evaluate parameter "acnow" for preconditioning
if ($cmd_acnow === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  $jsonData = '{"data":{"type":"HvacStart","attributes":{"action":"start","targetTemperature":"21"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/hvac-start?country='.$country);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
}

//Evaluate parameter "chargenow" for instant charging
if ($cmd_chargenow === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  $jsonData = '{"data":{"type":"ChargingStart","attributes":{"action":"start"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charging-start?country='.$country);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
}

//Evaluate parameters "cmon" respectively "cmoff" for setting the chargemode
if ($cmd_cmon === TRUE || $cmd_cmoff === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  if ($cmd_cmon === TRUE) $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"schedule_mode"}}}';
  else $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"always_charging"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charge-mode?country='.$country);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
}

//Request battery and charging status from Renault
if ($update_ok === TRUE) {
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v2/cars/'.$vin.'/battery-status?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  renault_log_api('Batterie-Status (battery-status)', $ch, $response);

  if ($response === FALSE) die(curl_error($ch));
  $md5 = md5($response);
  $responseData = json_decode($response, TRUE);
  if (!isset($responseData['data'])) {
    renault_log('ERROR', 'Batterie-Status: keine Daten im Response. Antwort: '.substr(preg_replace('/\s+/', ' ', $response), 0, 400));
    //Diagnosis on 404 "no data for this vin and uid": list the VINs known to this account
    if (strpos($response, 'notFound') !== FALSE) {
      $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/vehicles?country='.$country);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
      $r2 = curl_exec($ch);
      if ($r2 !== FALSE) {
        $rd2 = json_decode($r2, TRUE);
        if (isset($rd2['vehicleLinks'])) {
          $vins = array();
          foreach ($rd2['vehicleLinks'] as $vl) if (!empty($vl['vin'])) $vins[] = $vl['vin'];
          if (empty($vins)) renault_log('ERROR', 'Diagnose: In Account '.$session[2].' ist KEIN Fahrzeug verknüpft. Fahrzeug in der My-Renault-App diesem Konto hinzufügen, oder es wird der falsche Account verwendet (Session-Cache unter Konfiguration löschen!).');
          else {
            renault_log('WARN', 'Diagnose: Im Account verknüpfte VIN(s): '.implode(', ', $vins).' - konfigurierte VIN: '.$vin);
            if (!in_array($vin, $vins)) renault_log('ERROR', 'Diagnose: Die konfigurierte VIN stimmt mit KEINER VIN im Account überein! Bitte VIN in den Settings korrigieren (Tippfehler?).');
          }
        } else renault_log('WARN', 'Diagnose: Fahrzeugliste nicht lesbar: '.substr(preg_replace('/\s+/', ' ', $r2), 0, 300));
      }
    }
  }
  if (isset($responseData['data'])) {
    $update_sucess = TRUE;
    $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['timestamp'], timezone_open('UTC'));
    $utc_timestamp = date_timestamp_get($s);
    $weather_api_dt = date_format($s, 'U');
    $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
    $session[8] = date_format($s, 'd.m.Y');
    $session[9] = date_format($s, 'H:i');
    $session[10] = $responseData['data']['attributes']['chargingStatus'];
    $session[11] = $responseData['data']['attributes']['plugStatus'];
    $session[12] = $responseData['data']['attributes']['batteryLevel'];
    // ZoePHP 20260520: batteryTemperature/batteryAvailableEnergy are no longer
    // provided by the Renault API for most cars - read them non-fatally.
    if (($zoeph == 1)) $session[13] = @$responseData['data']['attributes']['batteryTemperature'];
    else $session[13] = @$responseData['data']['attributes']['batteryAvailableEnergy'];
    $session[14] = $responseData['data']['attributes']['batteryAutonomy'];
    $session[15] = @$responseData['data']['attributes']['chargingRemainingTime'];
    $s = @$responseData['data']['attributes']['chargingInstantaneousPower'];
    if ($zoeph == 1) $session[16] = $s/1000;
    else $session[16] = $s;
    renault_log('INFO', 'Batterie-Status OK: '.$session[12].' %, Reichweite '.$session[14].' km, Ladestatus '.$session[10]);
  } else $update_sucess = FALSE;
} else {
  $update_sucess = FALSE;
  renault_log('INFO', 'Abruf übersprungen: letzter Abruf liegt weniger als 1 Minute zurück (max. 1 API-Request/Minute).');
}



//Request more data from Renault if changed data since last request are expected
if (isset($md5) && $md5 != $session[3] && $update_sucess === TRUE) {
  //Request mileage
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/cockpit?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $s = $responseData['data']['attributes']['totalMileage'];
  if (empty($s)) $update_sucess = FALSE;
  else $session[7] = $s;

  //Request chargemode
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$session[1]
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charge-mode?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $s = @$responseData['data']['attributes']['chargeMode'];
  if (empty($s)) $session[24] = 'n/a';
  else $session[24] = $s;

  // ZoePHP 20260520: hvac-status request for the outside temperature (Ph1)
  // was removed - the Renault API no longer provides this endpoint reliably.

  //Request GPS position (only Ph2)
  if ($zoeph == 2) {
    $postData = array(
      'apikey: '.$kamereon_api,
      'x-gigya-id_token: '.$session[1]
    );
    $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/location?country='.$country);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
    $response = curl_exec($ch);
    if ($response === FALSE) die(curl_error($ch));
    $responseData = json_decode($response, TRUE);
    $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['lastUpdateTime'], timezone_open('UTC'));
    if (empty($s)) $zoeph = 3;
    else {
      $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
      $session[17] = $responseData['data']['attributes']['gpsLatitude'];
      $session[18] = $responseData['data']['attributes']['gpsLongitude'];
      $session[19] = date_format($s, 'd.m.Y');
      $session[20] = date_format($s, 'H:i');
    }
  }
  
  //Request weather data from openweathermap (only Ph2)
  if ($zoeph == 2 && $weather_api_key != '') {
    $ch = curl_init('https://api.openweathermap.org/data/2.5/onecall/timemachine?lat='.$session[17].'&lon='.$session[18].'&dt='.$weather_api_dt.'&units=metric&lang='.$country.'&appid='.$weather_api_key);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $response = curl_exec($ch);
    if ($response === FALSE) die(curl_error($ch));
    $responseData = json_decode($response, TRUE);	
    $session[22] = $responseData['current']['temp'];
    $session[23] = $responseData['current']['weather']['0']['description'];
  }

  //Send mail, execute command or activate schedule mode if configured
  if ($mail_bl === 'Y' || $cmon_bl === 'Y' || !empty($exec_bl)) {
    if ($session[12] >= $session[21] && $session[10] == 1 && $session[5] != 'Y') {
      if ($session[15] != '') $s = $session[15];
      else $s = $lng['some'];
      $sendmessage = $lng['Specified battery level reached.']."\n".$lng['Battery level'].': '.$session[12].' %'."\n".$lng['Remaining charging time'].': '.$s.' '.$lng['minutes']."\n".$lng['Range'].': '.$session[14].' km'."\n".$lng['Status update'].': '.$session[8].' '.$session[9];
      if ($mail_bl === 'Y') mail($username, $zoename, $sendmessage);
      if ($cmon_bl === 'Y') {
        $postData = array(
          'Content-type: application/vnd.api+json',
          'apikey: '.$kamereon_api,
          'x-gigya-id_token: '.$session[1]
        );
        $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"schedule_mode"}}}';
        $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charge-mode?country='.$country);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        $response = curl_exec($ch);
        if ($response === FALSE) die(curl_error($ch));
      }
      if (!empty($exec_bl)) shell_exec($exec_bl.' "'.$sendmessage.'"');
      $session[5] = 'Y';
    } elseif ($session[5] == 'Y' && $session[10] != 1) $session[5] = 'N';
  }
  if ($mail_csf === 'Y' || !empty($exec_csf)) {
    $sendmessage = $lng['Charging finished.']."\n".$lng['Battery level'].': '.$session[12].' %'."\n".$lng['Range'].': '.$session[14].' km'."\n".$lng['Status update'].': '.$session[8].' '.$session[9];
    if ($session[6] == 'Y' && $session[10] != 1) {
      if ($mail_csf === 'Y') mail($username, $zoename, $sendmessage);
      if (!empty($exec_csf)) shell_exec($exec_bl.' "'.$sendmessage.'"');
    }
    if ($session[10] == 1) $session[6] = 'Y';
    else $session[6] = 'N';
  }

  //Save data in database if configured
  if ($update_sucess === TRUE && $save_in_db === 'Y') {
    if (!file_exists('database.csv')) {
      if ($zoeph == 1) file_put_contents('database.csv', 'Date;Time;Mileage;Outside temperature;Battery temperature;Battery level;Range;Cable status;Charging status;Charging speed;Remaining charging time;Charging schedule'."\n");
      else file_put_contents('database.csv', 'Date;Time;Mileage;Battery level;Battery capacity;Range;Cable status;Charging status;Charging speed;Remaining charging time;GPS Latitude;GPS Longitude;GPS date;GPS time;Outside temperature;Weather condition;Charging schedule'."\n");
    }
    if ($zoeph == 1) file_put_contents('database.csv', $session[8].';'.$session[9].';'.$session[7].';'.$session[17].';'.$session[13].';'.$session[12].';'.$session[14].';'.$session[11].';'.$session[10].';'.$session[16].';'.$session[15].';'.$session[24]."\n", FILE_APPEND);
    elseif ($zoeph == 2) file_put_contents('database.csv', $session[8].';'.$session[9].';'.$session[7].';'.$session[12].';'.$session[13].';'.$session[14].';'.$session[11].';'.$session[10].';'.$session[16].';'.$session[15].';'.$session[17].';'.$session[18].';'.$session[19].';'.$session[20].';'.$session[22].';'.$session[23].';'.$session[24]."\n", FILE_APPEND);
    else file_put_contents('database.csv', $session[8].';'.$session[9].';'.$session[7].';'.$session[12].';'.$session[13].';'.$session[14].';'.$session[11].';'.$session[10].';'.$session[16].';'.$session[15].';;;;;;;'.$session[24]."\n", FILE_APPEND);
  }

  //Send data to ABRP if configured
  if (!empty($abrp_token) && !empty($abrp_model)) {
    if ($session[10] == 1) $abrp_is_charging = 1;
    else $abrp_is_charging = 0;
    $jsonData = urlencode('{"car_model":"'.$abrp_model.'","utc":'.$utc_timestamp.',"soc":'.$session[12].',"odometer":'.$session[7].',"is_charging":'.$abrp_is_charging.'}');
    $ch = curl_init('https://api.iternio.com/1/tlm/send?api_key=fd99255b-91a0-45cd-9df5-d6baa8e50ef8&token='.$abrp_token.'&tlm='.$jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $response = curl_exec($ch);
    if ($response === FALSE) die(curl_error($ch));
  }
}
if (isset($ch)) unset($ch);



//Request HVAC Status (LoxBerry addition, for MQTT topic HvAcStatus)
//ZoePHP 20260520 removed hvac-status upstream because the endpoint no longer
//works for many cars - therefore this request is now non-fatal.
$hvac = 'n/a';
$postData = array(
  'apikey: '.$kamereon_api,
  'x-gigya-id_token: '.$session[1]
);
$ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$session[2].'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/hvac-status?country='.$country);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
$response = curl_exec($ch);
if ($response !== FALSE) {
  $responseData = json_decode($response, TRUE);
  if (isset($responseData['data']['attributes']['hvacStatus'])) $hvac = $responseData['data']['attributes']['hvacStatus'];
}








//Output
if ($cmd_cron === TRUE) {
  if ($cmd_acnow === TRUE) echo 'AC NOW'."\n";
  if ($cmd_chargenow === TRUE) echo 'CHARGE NOW'."\n";
  if ($cmd_cmon === TRUE) echo 'CM ON'."\n";
  elseif ($cmd_cmoff === TRUE) echo 'CM OFF'."\n";
  if ($update_sucess === TRUE) echo 'OK';
  else echo 'NO DATA';
} else {
  $requesturi = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
  echo '<HTML>'."\n".'<HEAD>'."\n".'<LINK REL="manifest" HREF="zoephp.webmanifest">'."\n".'<LINK REL="stylesheet" HREF="stylesheet.css">'."\n".'<META NAME="viewport" CONTENT="width=device-width, initial-scale=1.0">'."\n".'<TITLE>'.$zoename.'</TITLE>'."\n".'</HEAD>'."\n".'<BODY>'."\n".'<DIV ID="container">'."\n".'<MAIN>'."\n";
  if ($mail_bl === 'Y') echo '<FORM ACTION="'.$requesturi.'" METHOD="post" AUTOCOMPLETE="off">'."\n";
  echo '<ARTICLE>'."\n".'<TABLE>'."\n".'<TR ALIGN="left"><TH>'.$zoename.'</TH><TD><SMALL><A HREF="'.$requesturi.'">'.$lng['Update'].'</A></SMALL></TD></TR>'."\n";
  if ($cmd_acnow === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Preconditioning requested.'].'</TD><TD>'."\n";
  if ($cmd_chargenow === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Instant charging requested.'].'</TD><TD>'."\n";
  if ($cmd_cmon === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Activation of the charging schedule requested.'].'</TD><TD>'."\n";
  elseif ($cmd_cmoff === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['Deactivation of the charging schedule requested.'].'</TD><TD>'."\n";
  if ($update_sucess === FALSE && $update_ok === TRUE) echo '<TR><TD COLSPAN="2">'.$lng['No new data'].'</TD><TD>'."\n";
  echo '<TR><TD>'.$lng['Mileage'].':</TD><TD>'.$session[7].' km</TD></TR>'."\n".'<TR><TD>'.$lng['Connected'].':</TD><TD>';
  if ($session[11] == 0) echo $lng['No'];
  else echo $lng['Yes'];



  echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Charging'].':</TD><TD>';
  if ($session[10] == 1){
    if ($session[15] != ''){
      $s = date_create_from_format('d.m.YH:i', $session[8].$session[9]);
      date_add($s, date_interval_create_from_date_string($session[15].' minutes'));
      $s = date_format($s, 'H:i');
    } else $s = $lng['Soon'];
    echo $lng['Yes'].'</TD></TR>'."\n".'<TR><TD>'.$lng['Ready'].':</TD><TD>'.$s;
    if ($zoeph == 1) echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Effect'].':</TD><TD>'.$session[16].' kW';
  } else echo $lng['No'];
  if ($hide_cm !== 'Y') {
      echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Charging schedule'].':</TD><TD>';
      if (substr($session[24], 0, 6) === 'always' || $session[24] === 'n/a') echo $lng['Inactive'];
      else echo $lng['Active'];
  }
  echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Battery level'].':</TD><TD>'.$session[12].' %</TD></TR>'."\n";

echo '</TD></TR>'."\n".'<TR><TD>'.$lng['hvac'].':</TD><TD>'.$hvac.' </TD></TR>'."\n";


  if ($mail_bl === 'Y' || $cmon_bl === 'Y' || !empty($exec_bl)) echo '<TR><TD>'.$lng['Action at battery level'].':</TD><TD><INPUT TYPE="number" NAME="bl" VALUE="'.$session[21].'" MIN="1" MAX="99"><INPUT TYPE="submit" VALUE="%"></TD></TR>'."\n";
  if ($zoeph == 2) echo '<TR><TD>'.$lng['Battery capacity'].':</TD><TD>'.$session[13].' kWh</TD></TR>'."\n";
  echo '<TR><TD>'.$lng['Range'].':</TD><TD>'.$session[14].' km</TD></TR>'."\n";
  if ($zoeph == 1) echo '<TR><TD>'.$lng['Battery temperature'].':</TD><TD>'.$session[13].' &deg;C</TD></TR>'."\n".'<TR><TD>'.$lng['Outside temperature'].':</TD><TD>'.$session[17].' &deg;C</TD></TR>'."\n";
  elseif ($zoeph == 2 && $weather_api_key != '') echo '<TR><TD>'.$lng['Outside temperature'].':</TD><TD>'.$session[22].' &deg;C ('.htmlentities($session[23]).')</TD></TR>'."\n";
  echo '<TR><TD>'.$lng['Status update'].':</TD><TD>'.$session[8].' '.$session[9].'</TD></TR>'."\n";
  if ($zoeph == 2) {
    echo '<TR><TD>'.$lng['Car position'].':</TD><TD>';
    if ($map_provider == 'osm') echo '<A HREF="https://www.openstreetmap.org/?mlat='.$session[17].'&mlon='.$session[18].'&zoom=17" TARGET="_blank">OpenStreetMap</A>';
    else echo '<A HREF="https://www.google.com/maps/place/'.$session[17].','.$session[18].'" TARGET="_blank">Google Maps</A>';
    echo '</TD></TR>'."\n".'<TR><TD>'.$lng['Position update'].':</TD><TD>'.$session[19].' '.$session[20].'</TD></TR>'."\n";
  }
  echo '<TR><TD COLSPAN="2"><A HREF="'.$requesturi.'?acnow">'.$lng['Start preconditioning'].'</A></TD></TR>'."\n";
  if ($hide_cm !== 'Y') echo '<TR><TD COLSPAN="2">'.$lng['Charging schedule'].': <A HREF="'.$requesturi.'?cmon">'.$lng['on'].'</A> | <A HREF="'.$requesturi.'?cmoff">'.$lng['off'].'</A></TD></TR>'."\n".'<TR><TD COLSPAN="2"><A HREF="'.$requesturi.'?chargenow">'.$lng['Start charging'].'</A></TD></TR>'."\n";
 // echo '<TR><TD COLSPAN="2"><A HREF="history.php">'.$lng['Charging history'].'</A></TD></TR>'."\n";
  echo '</TABLE>'."\n".'</ARTICLE>'."\n";
  if ($mail_bl === 'Y') echo '</FORM>'."\n";
  echo '</MAIN>'."\n".'</DIV>'."\n".'</BODY>'."\n".'</HTML>';
}

 $km = $lng['Mileage'];








// Be careful about the required namespace on inctancing new objects:
$mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'],  $creds['brokerport'], $client_id);
    if( $mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass'] ) ) {
        $mqtt->publish("Renault/$zoename/BattSOC", $session[12], 0, 1);
	
	//$mqtt->publish("Renault/$zoename/GigjaToken", $session[1], 0, 1);
	$lastData = str_split($session[4],4);
	$mqtt->publish("Renault/$zoename/LastDataRetrieval", $lastData[2], 0, 1);


	$mqtt->publish("Renault/$zoename/CargingStatus", $session[10], 0, 1);
	$mqtt->publish("Renault/$zoename/CableStatus", $session[11], 0, 1);
	$mqtt->publish("Renault/$zoename/Range", $session[14], 0, 1);
	$mqtt->publish("Renault/$zoename/ChargingTime", $session[15], 0, 1);
	$mqtt->publish("Renault/$zoename/ChargingEffekt", $session[16], 0, 1);
	$mqtt->publish("Renault/$zoename/GPSTime", $session[20], 0, 1);
	$mqtt->publish("Renault/$zoename/ChargeMode", $session[24], 0, 1);
	//$mqtt->publish("Renault/$zoename/AccountID", $session[2], 0, 1);
	//$mqtt->publish("Renault/$zoename/MD5", $session[3], 0, 1);
	//$mqtt->publish("Renault/$zoename/DateStatusUpdate", $session[8], 0, 1);
	//$mqtt->publish("Renault/$zoename/TimeStatusUpdate", $session[9], 0, 1);
	$mqtt->publish("Renault/$zoename/Mileage", $session[7], 0, 1);
	$mqtt->publish("Renault/$zoename/Name", $zoename, 0, 1);
	//$mqtt->publish("Renault/$zoename/ClimatefromPHP", $cmd_acnow, 0, 1);
	//$mqtt->publish("Renault/$zoename/ChargefromPHP", $cmd_chargenow, 0, 1);
if ($zoeph == 1) {
	$mqtt->publish("Renault/$zoename/OutTemp", $session[17], 0, 1);
	$mqtt->publish("Renault/$zoename/GPS-Longitude", "N/A", 0, 1);
	$mqtt->publish("Renault/$zoename/GPS-Latitude", "N/A", 0, 1);
	$mqtt->publish("Renault/$zoename/RenaultPHMode", "1", 0, 1);
	$mqtt->publish("Renault/$zoename/BatTemp", $session[13], 0, 1);
}
if ($zoeph == 2) {
	$mqtt->publish("Renault/$zoename/GPS-Latitude", $session[17], 0, 1);
	$latitude = str_split($session[17], 5);
	$mqtt->publish("Renault/$zoename/GPS-Latitude_1", $latitude[0], 0, 1);
	$mqtt->publish("Renault/$zoename/GPS-Latitude_2", $latitude[1], 0, 1);
	$mqtt->publish("Renault/$zoename/GPS-Latitude_3", $latitude[2], 0, 1);

	$mqtt->publish("Renault/$zoename/GPS-Longitude", $session[18], 0, 1);
	$longitude = str_split($session[18],5);
	$mqtt->publish("Renault/$zoename/GPS-Longitude_1", $longitude[0], 0, 1);
	$mqtt->publish("Renault/$zoename/GPS-Longitude_2", $longitude[1], 0, 1);
	$mqtt->publish("Renault/$zoename/GPS-Longitude_3", $longitude[2], 0, 1);

	$mqtt->publish("Renault/$zoename/OutTemp", "N/A", 0, 1);
	$mqtt->publish("Renault/$zoename/RenaultPHMode", "2", 0, 1);
	$mqtt->publish("Renault/$zoename/EnergieOnBoard", $session[13], 0, 1);
	$mqtt->publish("Renault/$zoename/BatTemp", "N/A", 0, 1);
	$mqtt->publish("Renault/$zoename/HvAcStatus", $hvac, 0, 1);
	};


	if ($hvac == 'off') $mqtt->publish("Renault/$zoename/HvAcStatusBin", 0, 0, 1);
	if ($hvac == 'on') $mqtt->publish("Renault/$zoename/HvAcStatusBin", 1, 0, 1);
	$phptime = str_split($timestamp_now, 4);
	$mqtt->publish("Renault/$zoename/phpCall", $phptime[2], 0, 1);

        $mqtt->close();
    } else {
        renault_log('ERROR', 'MQTT-Verbindung fehlgeschlagen (Broker '.$creds['brokerhost'].':'.$creds['brokerport'].').');
        echo "MQTT connection failed";
    }





//Cache data
if ($update_ok === TRUE || $cmd_cron == TRUE || (isset($_POST['bl']) && is_numeric($_POST['bl']))) {
  $session[3] = $md5;
  $session[4] = $timestamp_now;
  $session = implode('|', $session);
  file_put_contents('session', $session);
}
LBWeb::lbfooter();
?>
