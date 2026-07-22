
<?php
//$_SERVER['HTTP_REFERER']; 
$Pfad=getcwd();
// REPLACELBPHTMLAUTHDIR is replaced by the LoxBerry plugin installer with the
// real plugin folder path (LoxBerry 2.x/3.x/4.x compatible).
$_home= "REPLACELBPHTMLAUTHDIR";
    echo "Danke - Ihre Daten wurden geschrieben";


 $zoename=($_POST['zoename']);
$username=($_POST['username']);
$password=($_POST['password']);
$vin=($_POST['vin']);
$country=($_POST['country']);
$zoeph=($_POST['zoeph']);






  
$handle = fopen ( "$_home/config.php", "w" );
	
	fwrite ( $handle, "<?php");
	fwrite ( $handle, "\n" );

	fwrite ( $handle, '$zoename = ' ."'".$zoename."';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$username = ' ."'".$username."';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$password = ' ."'".$password."';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$vin = ' ."'".$vin."';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$country = ' ."'".$country."';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$zoeph = ' ."'".$zoeph."';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$save_in_db =' ."'N';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$mail_bl =' ."'N';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$exec_bl =' ."'';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$cmon_bl =' ."'N';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$mail_csf =' ."'N';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$exec_csf =' ."'';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$hide_cm =' ."'N';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$map_provider =' ."'google';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$weather_api_key =' ."'';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$abrp_token =' ."'';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$abrp_model =' ."'';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$cron_ncs =' ."'5';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, '$cron_acs =' ."'2';");
	fwrite ( $handle, "\n" );
	fwrite ( $handle, "?>");
	fwrite ( $handle, "\n" );





	fwrite ( $handle, ' ' ); 
   	fwrite ( $handle, "\n" );

    fclose ( $handle );

@unlink('session');

require_once dirname(__FILE__).'/logger.php';
renault_log('INFO', 'Einstellungen gespeichert (Name='.$zoename.', User='.$username.', VIN='.$vin.', Land='.$country.', Phase='.$zoeph.'). Session-Cache geleert.');

// v1.4: Dauerhafte Sicherung im Config-Verzeichnis (uebersteht Updates & Neuinstallation)
$_confdir = "REPLACELBPCONFIGDIR";
if (strpos($_confdir, "REPLACE") !== 0) {
    @mkdir($_confdir, 0775, true);
    @copy("$_home/config.php", $_confdir."/config.php.backup");
}


    echo "Danke - Ihre Daten wurden gespeichert";
header('Location: ./index.php'); exit;



//header('Location: ./umleit.php'); exit;
//header('Location: ./Index.php'); exit;
//header('Location:'.$_SERVER['HTTP_REFERER']);  
?>