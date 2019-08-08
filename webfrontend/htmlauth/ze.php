#!/usr/bin/php
<?php

error_log("------------------------------------------------------");

include_once "loxberry_system.php";
include_once "loxberry_io.php";
require_once "./phpMQTT/phpMQTT.php";
require_once "defines.php";


//
// Query parameter 
//

// Convert commandline parameters to query parameter
foreach ($argv as $arg) {
    $e=explode("=",$arg);
    if(count($e)==2)
        $_GET[$e[0]]=$e[1];
    else    
        $_GET[$e[0]]=0;
}

// Parse query paraeters

// Default action
$action = "summary";
$VIN = false;

// Define vehicle
if(!empty($_GET["vehicle"])) { 
	$VIN = $_GET["vehicle"];
} elseif (!empty($_GET["v"])) { 
	$VIN = $_GET["v"];
} elseif (!empty($_GET["vin"])) { 
	$VIN = $_GET["vin"];
}

// Unlink at: Delete a cronjob after this timestamp
if(!empty($_GET["unlinkat"])) {
	$unlinkat = $_GET["unlinkat"];
}

// Actions
if(isset($_GET["a"])) {
	$_GET["action"] = $_GET["a"];
}
if(isset($_GET["action"])) { 
	switch($_GET["action"]) {
		case "summary":
		case "sum":
			$action = "summary";
			break;
		case "battery":
		case "batt": 
			$action = "battery";
			break;
		case "condition":
		case "cond":
			$action = "condition";
			break;
		case "conditionlast":
		case "condlast":
			$action = "conditionlast";
			break;
		case "charge":
			$action = "charge";
			break;
		case "relogin":
			$action = "relogin";
			break;
		default: 
			echo "Action '" . $_GET["action"] . "' not supported. Exiting.\n";
			exit(1);
	}
}

echo "Calling parameters:\n";
echo "  action : $action\n";
echo "  vin    : $VIN\n";

// Validy check
if( ( $action == "battery" || $action == "condition" || $action == "conditionlast" ) && $VIN == false ) {
	echo "Action '$action' requires parameter vin/vehicle. Exiting.\n";
	exit(1);
}


// Init
$token = false;
if( ! file_exists(CONFIGFILE) ) {
	echo "You need to create a json config file. Exiting.\n";
	exit(1);
} else {
	echo "Using configfile " . CONFIGFILE . "\n";
	$configdata = file_get_contents(CONFIGFILE);
	$config = json_decode($configdata);
	if( empty($config) ) {
		echo "Config file exists, but seems to be empty or invalid. Exiting.\n";
		if( !empty(json_last_error()) ) {
			echo "JSON Error: " . json_last_error() . " " . json_last_error_msg() . "\n";
		}
		exit(1);
	}
	$user = $config->user;
	$pass = $config->pass;
}
if( empty($user) || empty($pass) ) {
	echo "User and/or pass not set. Exiting.\n";
	exit(1);
}

// Check if this is a LoxBerry
if ( function_exists("currtime") ) {
	echo "Running on a LoxBerry\n";
	$islb = true;
	$msnr = isset($config->Loxone->msnr) ? $config->Loxone->msnr : 1 ;
} else {
	$islb = false;
}

// MQTT support
if( isset($config->MQTT->enabled) && zoe_is_enabled($config->MQTT->enabled) ) {
	$mqttenabled = true;
	$mqtttopic = !empty($config->MQTT->topic) ? $config->MQTT->topic : "renault-ze";
	define ("MQTTTOPIC", $mqtttopic);

} else {
	$mqttenabled = false;
}

if ( $mqttenabled == true && !empty($config->MQTT->host) ) {
	$brokeraddress = $config->MQTT->host;
	$brokeruser = !empty($config->MQTT->user) ? $config->MQTT->user : "";
	$brokerpass = !empty($config->MQTT->pass) ? $config->MQTT->pass : "";
}

if ($islb && $mqttenabled && empty($brokeraddress) ) {
	// Check if MQTT plugin in installed
	$mqttplugindata = LBSystem::plugindata("mqttgateway");
	if( !empty($mqttplugindata) ) {
		$mqttconf = json_decode(file_get_contents(LBHOMEDIR . "/config/plugins/" . $mqttplugindata['PLUGINDB_FOLDER'] . "/mqtt.json" ));
		$mqttcred = json_decode(file_get_contents(LBHOMEDIR . "/config/plugins/" . $mqttplugindata['PLUGINDB_FOLDER'] . "/cred.json" ));
		
		$brokeraddress = $mqttconf->Main->brokeraddress;
		$brokeruser = $mqttcred->Credentials->brokeruser;
		$brokerpass = $mqttcred->Credentials->brokerpass;
		
		echo "Using broker settings from MQTT Gateway plugin:\n";
	}
}

// Final MQTT check
if ( $mqttenabled ) {
	if ( empty($brokeraddress) ) {
		echo "MQTT is enabled, but no broker is set. Disabling MQTT.\n";
		$mqttenabled = false;
	} else {
		echo "Broker host : $brokeraddress\n";
		echo "Broker user : $brokeruser\n";
		echo "Broker pass : " . substr($brokerpass, 0, 1) . str_repeat("*", strlen($brokerpass)-1) . "\n";		
	}
}

// Read login data from disk, if exists
if ( $action != "relogin" ) {
	$login = zoe_readlogin();
} else {
	$action = "summary";
}

// Call Login
if ( empty($token) ) {
	$login = zoe_login($user, $pass);
}


// What should we do?

if( $action == "summary" ) {
	zoe_summary( $login );
	exit(0);
} 
if( $action == "battery" ) {
	zoe_battery( $VIN );
	exit(0);
}
if( $action == "condition" ) {
	zoe_condition( $VIN, true);
	exit(0);
}
if( $action == "conditionlast" ) {
	zoe_condition( $VIN, false);
	exit(0);
}

if( $action == "charge" ) {
	zoe_charge( $VIN );
	exit(0);
}

echo "Don't know what to do (action '$action', VIN '$VIN'). Exiting.\n";
exit(1);



function zoe_summary( $login )
{

	// Show some data
	// echo "Auth Token: " . $login->token . "\n";
	echo "User: " . $login->user->first_name . " " . $login->user->last_name . "\n";
	echo "Vehicles:\n";
	foreach ($login->user->associated_vehicles as $vehicle) {
		$vin = $vehicle->VIN;
		echo "-> " . $vin . "\n";
		zoe_battery( $vin );
		
		// var_dump($battery);
		// Looping all properties
		
	}
}

function zoe_login ( $user, $pass ) {
	global $token;

	$payload = [ "username" => $user, "password" => $pass ]; 
	$logindata = zoe_curl_send( BASEURL."/user/login", $payload, true);
	$login = json_decode($logindata);
	if ( empty($login) ) {
		echo "JSON error, or JSON is empty: Error code " . json_last_error() . " " . json_last_error_msg() . "\n";
		return;
	}
	
	// Write data to ramdisk
	file_put_contents(LOGINFILE, $logindata);
	
	// Read token
	if( empty($login->token) ) {
		echo "Data error, no token found. Response: $logindata\n";
		return;
	} else {
		$token = $login->token;
	}
	// echo $logindata . "\n";
	return $login;
}


// Reads login data from disk, and checks for expiration of the token
function zoe_readlogin ()
{
	global $token;
	
	if( ! file_exists(LOGINFILE) ) {
		return;
	}
	$logindata = file_get_contents(LOGINFILE);
	$login = json_decode($logindata);
	
	// Read token
	if( empty($login->token) ) {
		echo "File data error, no token found. Fallback to re-login\n";
		return;
	}
	
	// Get date part of token
	$tokenparts = explode(".", $login->token);
	$tokenexpires = json_decode( base64_decode($tokenparts[1]) )->exp;
	echo "Token expires: " . $tokenexpires . " (" . date('c', $tokenexpires) . ")\n";
	
	if( $tokenexpires > time()-10 ) {
		// Token is valid
		$token = $login->token;
	} else {
		echo "Token expired - refreshing token\n";
	}
		
	// echo $logindata . "\n";
	return $login;
}

function zoe_battery ( $VIN ) {
	
	global $islb;
	
	$tmpfilename = TMPPREFIX . "batt_" . $VIN;
	unlink($tmpfilename);
	
	
	$batterydata = zoe_curl_send( BASEURL."/vehicle/$VIN/battery", false );
	$battery = json_decode($batterydata);
		
	$basekey = $VIN."_batt";
	$sendbuffer = array ( );
	
	// Default values;
	$sendbuffer[$basekey."_charging"] = 0;
	$sendbuffer[$basekey."_charge_level"] = 0;
	$sendbuffer[$basekey."_charging_point"] = "";
	$sendbuffer[$basekey."_last_update"] = 0;
	$sendbuffer[$basekey."_last_update_lox"] = 0;
	$sendbuffer[$basekey."_last_update_text"] = 0;
	$sendbuffer[$basekey."_plugged"] = 0;
	$sendbuffer[$basekey."_remaining_range"] = "";
	$sendbuffer[$basekey."_remaining_time"] = "";
		
	foreach ( get_object_vars($battery) as $key => $value ) {
		// Do some data manipulations
		if(empty($value)) {
			// Convert false to 0
			$value = 0;
		} elseif ( zoe_is_enabled($value) ) {
			// Convert true to 1
			$value = 1;
		} elseif ( $key == "last_update" ) {
			// Convert epoch in ms to Loxone time
			if ($islb) {
				$sendbuffer[$basekey."_last_update_lox"] = epoch2lox(round($value/1000));
			}
			$sendbuffer[$basekey."_last_update_text"] = date("d.m.y H:i", round($value/1000));
		}
			
		// echo "Key: '$key' Value: '$value'\n";
		
		$sendkey = $basekey."_".$key;
		$sendval = $value;
		
		$sendbuffer[$sendkey] = $sendval;
		
		
	}
	
	file_put_contents( $tmpfilename, json_encode($sendbuffer) );
	relay( $sendbuffer );
	
	return json_decode($batterydata);
}

function zoe_condition( $VIN, $condenable=false )
{
	
	global $unlinkat;
	
	echo "zoe_condition\n";
	
	$tmpfilename = TMPPREFIX . "cond_" . $VIN;
	unlink($tmpfilename);
	
	$cronpath = LBHOMEDIR."/system/cron/cron.01min/".LBPPLUGINDIR."_condupdate_${VIN}";
	
	// Enable
	if( $condenable == true ) {
		$conddata = zoe_curl_send( BASEURL."/vehicle/$VIN/air-conditioning", false, true );
		// We create a cronjob that updates the status in the background
		$cronentrystr = 
			"#!/bin/bash".PHP_EOL.
			"cd ".LBPHTMLAUTHDIR.PHP_EOL.
			"php ".LBPHTMLAUTHDIR."/ze.php action=conditionlast vehicle=$VIN unlinkat=".(time()+5*60).PHP_EOL;
		echo "Creating cron for condition auto-refresh: $cronpath\n";
		if (!file_put_contents($cronpath, $cronentrystr)) {
			echo "Could not write crontab.\n";
		}
		chmod($cronpath, 0755); 
	}
	$lastconddata = zoe_curl_send( BASEURL."/vehicle/$VIN/air-conditioning/last", false );
	$lastcond = json_decode($lastconddata);
	
	$basekey = $VIN."_cond";
	
	$sendbuffer[$basekey."_type"] = "";
	$sendbuffer[$basekey."_result"] = "";
	$sendbuffer[$basekey."_date"] = 0;
	$sendbuffer[$basekey."_date_lox"] = 0;
	$sendbuffer[$basekey."_date_text"] = "";
	
	if( empty($lastcond) ) {
		if( !empty(json_last_error()) ) {
			echo "JSON Error: " . json_last_error() . " " . json_last_error_msg() . "\n";
			$sendbuffer[$basekey."_result"] = json_last_error_msg();
		} else {
			$sendbuffer[$basekey."_result"] = "NO DATA";
		}
	} else {
		$sendbuffer[$basekey."_result"] = $lastcond->result;
		$sendbuffer[$basekey."_type"] = $lastcond->type;
		$sendbuffer[$basekey."_date"] = $lastcond->date;
		$sendbuffer[$basekey."_date_lox"] = epoch2lox(round($lastcond->date/1000));
		$sendbuffer[$basekey."_date_text"] = date("d.m.y H:i", round($lastcond->date/1000));
	}
	
	file_put_contents( $tmpfilename, json_encode($sendbuffer) );
	relay( $sendbuffer );
	
	// If the $unlinkat parameter is higher than time, delete the cronjob
	if( !empty($unlinkat) && time() > $unlinkat ) {
		echo "Removing outdated cronjob\n";
		unlink($cronpath);
	}
	
	
	
	return $lastconddata;

}

// Central sending function to relay to Loxone and/or MQTT
function relay ( $sendbuffer )
{
	global $islb, $config, $msnr, $mqttenabled;
	
		// Show values
	foreach ($sendbuffer as $key => $value) {
		echo "   $key: $value\n";
	}
	
	
	// Send via HTTP to Loxone Miniserver
	if( $islb && isset($config->Loxone->enabled) && zoe_is_enabled($config->Loxone->enabled) ) {
		echo "Sending data to Loxone Miniserver No. $msnr...\n";
		mshttp_send_mem( $msnr, $sendbuffer );
	}
	// Send to MQTT
	if( $mqttenabled ) {
		mqtt_publish( $sendbuffer );
	}
}


function zoe_curl_send( $url, $payload, $post=false ) 
{
	global $token;
	$curl = curl_init();

	if( !empty($payload) ) {
		$payload = json_encode ( $payload );
	} else {
		$payload = "";
	}
	
	$header = [ ];
	
	if( !empty($token) ) {
		echo "Token given.\n";
		array_push( $header, "Authorization: Bearer $token" );
	}
	
	if($post==true) {
		array_push( $header, "Content-Type: application/json;charset=UTF-8" );
		array_push( $header, "Content-Length: " . strlen($payload) );
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
	}
	
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header );

	echo "curl_send URL: $url\n";
	$response = curl_exec($curl);
	echo "curl_exec finished\n";
	// Debugging
	$crlinf = curl_getinfo($curl);
	echo "Status: " . $crlinf['http_code'] . "\n";
	
	curl_close($curl);
	
	return $response;
	
}

####################################################
# MQTT handler
####################################################
function mqtt_publish ( $keysandvalues ) {
	
	global $brokeraddress, $brokeruser, $brokerpass;
	
	$broker = explode(':', $brokeraddress, 2);
	$broker[1] = !empty($broker[1]) ? $broker[1] : 1883;
	
	$client_id = uniqid(gethostname()."_zoe");
	$mqtt = new Bluerhinos\phpMQTT($broker[0],  $broker[1], $client_id);
	if( $mqtt->connect(true, NULL, $brokeruser, $brokerpass) ) {
		foreach ($keysandvalues as $key => $value) {
			$keysplit=explode("_", $key, 2);
			
			echo "MQTT publishing " . MQTTTOPIC . "/$keysplit[0]/$keysplit[1]: $value...\n";
			$mqtt->publish(MQTTTOPIC . "/$keysplit[0]/$keysplit[1]", $value, 0, 1);
		}
		$mqtt->close();
	}
}


####################################################
# is_enabled - tries to detect if a string says 'True'
####################################################
function zoe_is_enabled($text)
{ 
	$text = trim($text);
	$text = strtolower($text);
	
	$words = array("true", "yes", "on", "enabled", "enable", "1", "check", "checked", "select", "selected");
	if (in_array($text, $words)) {
		return 1;
	}
	return NULL;
}

