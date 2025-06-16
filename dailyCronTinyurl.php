<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

	include('global.php');

	$clicks = count(getTinyurlClicks());

	if($clicks >= 0) {
		$SQL = "UPDATE system_health SET value={$clicks}, updated={$nowSql} WHERE title = 'TinyURL Clicks'";
	} else {
		$SQL = "UPDATE system_health SET value=0, updated={$nowSql} WHERE title = 'TinyURL Clicks'";
	}
	db_query($SQL);


	function getTinyurlClicks() {
		global $tinyurl_token_analytics;
		$curl = curl_init();

		$query = 'to=' . date('Y-m-d\TH:i:s', time()) . '%20ACST&from=' . date('Y-m-d\TH:i:s', strtotime('-1 day')).'%20ACST';

		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://api.tinyurl.com/analytics/raw/json?'.$query,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
			  'accept: application/json',
			  'Authorization: Bearer '.$tinyurl_token_analytics
			),
		  ));

		$response = curl_exec($curl);

		if (curl_errno($curl)) {
			throw new Exception("Error retrieving TinyURL Data - cURL error: " . curl_error($curl));
		}
		curl_close($curl);

		$json = json_decode($response, true);
		if(count($json['errors']) > 0) {
			throw new Exception('Error retrieving TinyURL Data - '.print_r($json['errors'][0], true));
		}

		return $json['data'][0];
	}
?>