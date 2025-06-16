<?php

	include("global.php");

	// Call CakePHP endpoint to clean up cakephp tmp files
	$endpoint = $cakePhpWsURL . 'save_log_files_to_db/';

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $endpoint);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Authorization: ' . $cakePhpWSApiKey,
	]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	curl_close($ch);

	echo $response . "\n";