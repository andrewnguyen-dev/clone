<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

    include('global.php');


	$key = $trustPilotKey;
	$secret = $trustPilotSecret;

	$data = array(
		'grant_type' => 'password',
		'username' => $trustPilotUsername,
		'password' => $trustPilotPassword,
	);

	$authorization = 'Basic '. base64_encode($key . ':' . $secret);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,"https://api.trustpilot.com/v1/oauth/oauth-business-users-for-applications/accesstoken");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Authorization: ' . $authorization,
		'Content-Type: application/x-www-form-urlencoded'
	));

	$server_output = curl_exec($ch);
	curl_close($ch);

	$array = json_decode($server_output, true);

	if (isset($array['access_token'])) {
		$SQL = "UPDATE trustpilot SET token = '{$array['access_token']}', updated = {$nowSql}";

		db_query($SQL);
	}
?>