<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

	// load global libraries
	include('global.php');
	set_time_limit(360);
	global $nowSql;

	// Including some notes and links here for the next person who modifies this since some of them weren't easy to find
	// Authentication is done via Google's oauth2l tool with the service account "SQLM-analytics" in the "SQAnalytics" project using sqlm-analytics.json
	//    https://github.com/google/oauth2l
	//    https://cloud.google.com/service-usage/docs/getting-started
	// GA4 Query Explorer - https://ga-dev-tools.web.app/ga4/query-explorer/
	// API doc - https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/properties/runReport

	$stdOptYesterday = '"dateRanges":[{"startDate":"yesterday","endDate":"yesterday"}],"keepEmptyRows":true';
	$stdOpt7days = '"dateRanges":[{"startDate":"7daysAgo","endDate":"yesterday"}],"keepEmptyRows":true';
	$stdOpt30days = '"dateRanges":[{"startDate":"30daysAgo","endDate":"yesterday"}],"keepEmptyRows":true';
	$stdOpt60days = '"dateRanges":[{"startDate":"60daysAgo","endDate":"yesterday"}],"keepEmptyRows":true';
	$stdOpt120days = '"dateRanges":[{"startDate":"120daysAgo","endDate":"yesterday"}],"keepEmptyRows":true';

	$gaTests = [
		'AdWords Spend Yesterday' => '{
			"dimensions":[{"name":"googleAdsAccountName"}],
			"metrics":[{"name":"advertiserAdCost"}],'.$stdOptYesterday.'}',
		'Google Analytics Add Batteries Leads' => '{"metrics":[{"name":"conversions:Add_Batteries"}],'.$stdOptYesterday.'}',
		'Google Analytics Commercial Leads' => '{"metrics":[{"name":"conversions:Commercial_Quotes"}],'.$stdOpt7days.'}',
		'Google Analytics EV Charger Leads' => '{"metrics":[{"name":"conversions:EV_Charger_QR"}],'.$stdOptYesterday.'}',
		'Google Analytics Hybrid Leads' => '{"metrics":[{"name":"conversions:Hybrid_Quotes"}],'.$stdOptYesterday.'}',
		'Google Analytics Off Grid Leads' => '{"metrics":[{"name":"conversions:Off_Grid"}],'.$stdOpt7days.'}',
		'Google Analytics Repair Leads' => '{"metrics":[{"name":"conversions:Repair_Quotes"}],'.$stdOpt7days.'}',
		'Google Analytics Battery Ready Leads' => '{"metrics":[{"name":"conversions:Battery_Ready_Solar"}],'.$stdOpt7days.'}',
		'Google Analytics Solar Only Leads' => '{"metrics":[{"name":"conversions:Solar_Only_QR"}],'.$stdOptYesterday.'}',
		'Google Analytics Charity Leads' => '{"metrics":[{"name":"conversions:Charity_Quotes"}],'.$stdOpt60days.'}',
		'Google Analytics Corena Requests' => '{"metrics":[{"name":"conversions:Corena_Quotes"}],'.$stdOpt120days.'}',
		'Google Analytics Supplier Inquiry' => '{"metrics":[{"name":"conversions:Supplier_Inquiry"}],'.$stdOpt7days.'}',
		'Google Analytics HWHP Only' => '{"metrics":[{"name":"conversions:HWHP_Only"}],'.$stdOptYesterday.'}',
		'Google Analytics HWHP and Battery' => '{"metrics":[{"name":"conversions:HWHP_Battery"}],'.$stdOpt7days.'}',
		'Google Analytics HWHP and Solar' => '{"metrics":[{"name":"conversions:HWHP_Solar"}],'.$stdOpt7days.'}',
		'Google Analytics HWHP and Solar and Battery' => '{"metrics":[{"name":"conversions:HWHP_Solar_Battery"}],'.$stdOpt7days.'}',
		'Google Analytics All Quotes' => '{"metrics":[{"name":"conversions:All_Quotes"}],'.$stdOptYesterday.'}'
	];

	function getGAData($body, $errCount = 0){
		global $phpdir, $privateHtmlDir;
		$auth = trim(shell_exec("$phpdir/google_auth/oauth2l fetch --cache $phpdir/google_auth/.oauth2l --credentials $privateHtmlDir/sqlm-analytics.json --scope analytics.readonly"));

		if(strpos($auth,'permission denied') !== false){
			throw new Exception($auth);
			die;
		}

		// Google API for analytics V4
		$initUrl = "https://analyticsdata.googleapis.com/v1beta/properties/280094196:runReport?alt=json";
		$curlHandler = curl_init($initUrl);
		curl_setopt($curlHandler, CURLOPT_POST, 1);
		curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlHandler, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"Content-Length: " . strlen($body),
			"Authorization: Bearer " . $auth
		));
		curl_setopt($curlHandler, CURLOPT_POSTFIELDS, $body);
	
		$response = curl_exec($curlHandler);

		$analyticsData = json_decode($response,true);

		if(!isset($analyticsData['rows'][0]['metricValues'][0]['value']) || isset($analyticsData['error'])){
			if($errCount < 3){
				$errCount++;
				// Clear cached auth.  Doing it this way rather than "google_auth/oauth2l reset" because that simply deletes the cache file and I want to preserve file permissions
				file_put_contents("$phpdir/google_auth/.oauth2l",'');
				sleep(30);
				$analyticsData = getGAData($body,$errCount);
			} else {
				if(isset($analyticsData['error'])){
					echo "Failed after $errCount retries to get Google Analytics data - API returned:\n".print_r($analyticsData['error'],true)."\n";
					return false;
				}
				echo "Failed after $errCount retries to get Google Analytics data\n".print_r($analyticsData,true)."\n";
				return false;
			}
		}

		return $analyticsData;
	}

	foreach($gaTests as $name => $query){
		$analyticsData = getGAData($query);
		if($analyticsData !== false){
			if(is_numeric($analyticsData['rows'][0]['metricValues'][0]['value'])){
				if($name == 'AdWords Spend Yesterday'){
					$SQL = "UPDATE system_health SET value=round({$analyticsData['rows'][0]['metricValues'][0]['value']},0), updated={$nowSql} WHERE title = '$name'";	
				} else {
					$SQL = "UPDATE system_health SET value={$analyticsData['rows'][0]['metricValues'][0]['value']}, updated={$nowSql} WHERE title = '$name'";
				}
				db_query($SQL);
			} else {
				echo "Failed to get Google Analytics data for $name.\nReturned data is not a number\n".print_r($analyticsData,true)."\n";
			}
		} else {
			echo "Failed to get Google Analytics data for $name.\n";
		}
	}

	echo "\nDone\n";
?>