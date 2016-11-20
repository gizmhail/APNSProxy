<?php

/*
 * APNSProxy.php
 * Copyright (c) 2016 Sébastien Poivre, aka Gizmhail
 * Licence: MIT Licence (https://opensource.org/licenses/MIT)
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * Usage: sends notification requests to Apple Push notification server
 *  based on stored pem files
 * The credential direcctory should contains for each appName which s using the proxy :
 * - appName_dev.pem, appName_dev.pass, appName_dev.auth
 * AND/OR
 * - appName_prod.pem, appName_prod.pass, appName_prod.auth
 * 
 * Note: to obtain .pem file from .p12 file:
 *  openssl pkcs12 -in apns-dev-cert.p12 -out apns-dev-cert.pem -nodes -clcerts
 */

// Paths to credentialsDir. Should be set to an unreachable place
$credentialsDir = dirname(__FILE__).'/AppleCredentials';

$expectedParameters = array(
		"appName" => "APNSProxy app name",
		"auth" => "APNSProxy auth token",
		"deviceToken" => "Device token for the device to target",
		"payload" => "(optional) Full apns payload as json string",
		"title" => "(optional ; incompatible with payload) Simple notification title",
		"message" => "(optional ; incompatible with payload) Simple notification message",
		"category" => "(optional ; incompatible with payload) Simple notification category",
	);
/**
 * Notification building
 */

// Create the payload body
function buildSimplePayloadString($title, $message, $category = null) {
	$payload = array();
	$payload["aps"] = array(
		"alert" => array(
			'title' => $title,
			'body' => $message,
		),
		'sound' => 'default',
		'badge' => 1,
	);
	if($category != null){
		$payload["aps"]["category"] = $category;
	}

	// Encode the payload as JSON
	$payload = json_encode($payload);
	return $payload;
}

// Connect to APNS server
function sendNotification($payload, $deviceToken, $apnsUrl, $pemCertificateFilePath, $pemPassPhrase){
	$result = array(
		"sucess" => true,
		"log" => array()
	);
	$ctx = stream_context_create();
	stream_context_set_option($ctx, 'ssl', 'local_cert', $pemCertificateFilePath);
	stream_context_set_option($ctx, 'ssl', 'passphrase', $pemPassPhrase);
	// Open a connection to the APNS server
	$fp = stream_socket_client(
		'ssl://'.$apnsUrl.':2195', $err,
		$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

	if (!$fp) {
		$result['sucess'] = false;
		$result['log'][] = "Failed to connect: $err $errstr";
		return $result;
	}

	$result['log'][] =  'Connected to APNS';


	$result['log'][] = "Sending payload: $payload";


	// Build the binary notification
	$msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;

	// Send it to the server
	$connexionResult = fwrite($fp, $msg, strlen($msg));

	if (!$connexionResult){
		$result['log'][] = 'Message not delivered';
		$result['sucess'] = false;
	}else{
		$result['log'][] = 'Message successfully delivered';
	}
	// Close the connection to the server
	fclose($fp);
	return $result;
}


// Device token received on the device from Apple
$deviceToken = null;
// App name has defined in the proxy configuration
$appName = null;
// True to use PANS Prod server, false for APNS sandbox dev server
$useProd = false;
// Full payload json string
$payload = null;
// If full payload is not defined, simple notification description
$message = null;
$title = null;
$category = null;
// APNSProxy auth string
$auth = null;

$output = array();

/*
 * Load parameters
 */

// Load parameters from REQUEST
$parameters = array('appName', 'payload', 'title', 'message', 'category', 'auth', 'deviceToken');
foreach ($parameters as $parameter) {
	$$parameter = isset($_REQUEST[$parameter])?$_REQUEST[$parameter]:null;
}
$useProd = isset($_REQUEST['useProd'])?$_REQUEST['useProd']:false;

// TODO: alternatively, load parameters from command line

// Configuration files for the app which wants to send the notification
$pemSuffix = $useProd ? 'prod' : 'dev';
$pemCertificateFilePath = $credentialsDir.'/'.$appName.'_'.$pemSuffix.'.pem';
$pemPassphraseFilePath = $credentialsDir.'/'.$appName.'_'.$pemSuffix.'.pass';
$authFilePath = $credentialsDir.'/'.$appName.'_'.$pemSuffix.'.auth';

$output = array();
if($deviceToken == null){
	$output['error'] = "Missing device token";
	$output['expectedParameters'] = $expectedParameters;
} else if(is_file($pemCertificateFilePath) && is_file($pemPassphraseFilePath)) {
	$output['appName'] = $appName;
	// Private key's passphrase
	$pemPassPhrase = trim(file_get_contents($pemPassphraseFilePath));

	// APNS Proxy authentification (by default, uses the passphrase)
	$expectedAuth = $pemPassPhrase;
	if(is_file($authFilePath)){
		$expectedAuth = trim(file_get_contents($authFilePath));
	}

	if($auth == $expectedAuth){
		if(!$useProd){
			//Server sandbox dev
			$apnsUrl = "gateway.sandbox.push.apple.com";
		}else{
			//Server prod
			$apnsUrl = "gateway.push.apple.com";
		}

		if($payload == null){
			$payload = buildSimplePayloadString($title, $message, $category);
		}
		$output['payload'] = json_decode($payload);

		$output['result'] = sendNotification($payload, $deviceToken, $apnsUrl, $pemCertificateFilePath, $pemPassPhrase);
	} else {
		// Authorization failed
		$output['error'] = "Authorization failed";
		$output['expectedParameters'] = $expectedParameters;
	}
} else {
	// Unknow application
	$output['error'] = "Unknown application";
	$output['expectedParameters'] = $expectedParameters;
}

// Result
header('Content-Type: application/json');
$outputStr = json_encode ( $output, JSON_PRETTY_PRINT);
if (isset ( $_GET ['callback'] )) {
    // JSONP
	echo $_GET ['callback'] . '(' . $outputStr . ')';
} else {
	echo $outputStr;
}

