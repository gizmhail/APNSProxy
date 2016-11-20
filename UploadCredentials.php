<?php

// Paths to credentialsDir. Should be set to an unreachable place
$credentialsDir = dirname(__FILE__).'/AppleCredentials';


$fileField = 'p12';
$passphraseField = 'passphrase';
$appField = 'applicationName';
$authField = 'auth';
$prodField = 'isProd';

// Upload
$applicationName = isset($_REQUEST[$appField])?$_REQUEST[$appField]:null;
$passphrase = isset($_REQUEST[$appField])?$_REQUEST[$passphraseField]:null;
$useProd = isset($_REQUEST[$prodField])?($_REQUEST[$prodField]=='true'):false;
$auth = isset($_REQUEST[$authField])?$_REQUEST[$authField]:null;

echo "<pre>";var_dump($_REQUEST);echo "</pre>";
echo "<pre>";var_dump($_FILES);echo "</pre>";

$logs = array();
if( isset($_FILES[$fileField]) && $passphrase ){
	$fileName = basename($_FILES[$fileField]['name']);
	if($applicationName == null){
		$applicationName = str_ireplace(".p12", "", $fileName);
	}
	// Sanatize application name
	$applicationName = str_replace(array("'",'"'," ", "!"), "", $applicationName);
	$applicationName = escapeshellcmd($applicationName);

	$p12Suffix = $useProd ? 'prod' : 'dev';
	$targetName = $applicationName . '_' . $p12Suffix;
	$p12File = $credentialsDir . '/' . $targetName . '.p12';
	$passphraseFile = $credentialsDir . '/' . $targetName . '.pass';
	$authFile = $credentialsDir . '/' . $targetName . '.auth';
	$p12File = $credentialsDir . '/' . $targetName . '.p12';
	if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $p12File)) {
		$logs[] = "Saved p12 file as ".basename($p12File);

		file_put_contents($passphraseFile, trim($passphrase));
		$logs[] = "Saved passphrase file as ".basename($passphraseFile);

		if($auth != null){
			file_put_contents($authFile, trim($auth));
			$logs[] = "Saved auth file as ".basename($authFile);
		} else {
			$logs[] = "No auth received, you must use passphrase as auth";			
		}

		// Try to convert to pem with openssl 
		$pemFile = $credentialsDir . '/' . $targetName . '.pem';
		$logs[] = 'Converting with OpenSSL p12 file '.basename($p12File).' to pem file '.basename($pemFile);
		$pemFile = escapeshellarg($pemFile);
		$p12File = escapeshellarg($p12File);
		$passphrase = escapeshellarg("pass:$passphrase");
		exec("openssl pkcs12 -in $p12File -out $pemFile -nodes -clcerts -passin $passphrase 2>&1", $execResults, $statusCode);
		$logs[] = "Conversion status code (0 = success): $statusCode";
		$logs[] = "Conversion details:";
		$logs = array_merge($logs, $execResults);
	} else {
		$logs[] = "[Error] Failed to store file";		
	}
}

?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
	<meta charset="utf-8">
	<style>
		body{
			font-family: arial;
		}

		label,h1 {
			color: #0B615E;
		}

		.info {
			border:2px solid #0B615E;;	
		}
	</style>
</head>
<body>
	<h1>APNSProxy p12 uploader</h1>
	<p>
		<i>Once all your certificates uploaded, you should remove the UploadCredentials.php file</i>
	</p>

	<?php if(count($logs)>0){?>
		<div class='info'>
			<ul>
			<?php foreach ($logs as $log) {
				echo "<li>$log</li>";
			}?>
			</ul>
		</div>
	<?php }?>

	<form method='post' enctype="multipart/form-data">
		<label for='<?php echo $fileField;?>'>P12 file</label><br/>
		<input type='file' name='<?php echo $fileField;?>'/><br/>

		<label for='<?php echo $passphraseField;?>'>P12 passphrase</label><br/>
		<input type='password' name='<?php echo $passphraseField;?>'/><br/>

		<label for='<?php echo $prodField;?>'>Use prod APNS (<i>will use sandbox one otherwise</i>)</label><br/>
		<input type='checkbox' name='<?php echo $prodField;?>' value='true'/><br/>

		<label for='<?php echo $appField;?>'>App name (<i>will use P12 file name if not filled</i>)</label><br/>
		<input type='text' name='<?php echo $appField;?>'/><br/>

		<label for='<?php echo $authField;?>'>Application password (<i>will use passphrase if not filled</i>)</label><br/>
		<input type='password' name='<?php echo $authField;?>'/><br/>

		<input type='submit'/>
	</form>
</body>
<?

//echo 'Voici quelques informations de débogage :';
//print_r($_FILES);

