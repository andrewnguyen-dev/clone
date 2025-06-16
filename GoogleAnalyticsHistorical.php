<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

	// load global libraries
	include('global.php');
	set_time_limit(360);
	global $nowSql, $phpdir, $privateHtmlDir;

	//
	// This should only be run once to initially pull all google historical values.  For regular updates use hourlyGoogleAnalytics.php
	//

	$body = array(
		"reportRequests" => array(array(
			"viewId" => "35320842",  // View "solarquotes.com.au (full URLS)"
			"dateRanges" => array(array(
				"startDate" => "2020-07-18",  // Values before 18/07/2020 aren't accurate.  Before this date the goals (11 & 2) weren't measuring the conversion rates
				"endDate" => "yesterday"
			)),
			"metrics" => array(
				array("expression"=>"ga:goal11Starts","alias"=>"desktop_funnel_rate_starts"),
				array("expression"=>"ga:goal11Completions","alias"=>"desktop_funnel_rate_completes"),
				array("expression"=>"ga:goal2Starts","alias"=>"mobile_funnel_rate_starts"),
				array("expression"=>"ga:goal2Completions","alias"=>"mobile_funnel_rate_completes")
			),
			"dimensions"=>array(array("name"=>"ga:date")),  // How to group results
			"orderBys"=>array(array(
				"fieldName"=>"ga:date",
				"sortOrder"=>"ASCENDING"
			)),
			"hideTotals" => "TRUE",  // Don't need the extra 'Total'
			"hideValueRanges" => "TRUE"  // or 'Min', 'Max' fields
		))
	);

	// https://github.com/google/oauth2l
	// https://cloud.google.com/service-usage/docs/getting-started
	$auth = trim(shell_exec("$phpdir/google_auth/oauth2l fetch --cache $phpdir/google_auth/.oauth2l --credentials $privateHtmlDir/sqlm-analytics.json --scope analytics.readonly"));

	$initUrl = "https://content-analyticsreporting.googleapis.com/v4/reports:batchGet?alt=json";
	$curlHandler = curl_init($initUrl);
	curl_setopt($curlHandler, CURLOPT_POST, 1);
	curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curlHandler, CURLOPT_HTTPHEADER, array(
		"Content-Type: application/json",
		"Content-Length: " . strlen(json_encode($body)),
		"Authorization: Bearer " . $auth
	));
	curl_setopt($curlHandler, CURLOPT_POSTFIELDS, json_encode($body));

	$response = curl_exec($curlHandler);
	$analyticsData = json_decode($response,true);

	foreach($analyticsData['reports'][0]['data']['rows'] as $data) {
		$desktop = $data['metrics'][0]['values'][1] / $data['metrics'][0]['values'][0] * 100;
		$mobile = $data['metrics'][0]['values'][3] / $data['metrics'][0]['values'][2] * 100;
		
		$insertTSQL = "INSERT INTO cache_google_analytics (DATE,desktop_conversion_rate,mobile_conversion_rate,last_updated) VALUES (DATE_FORMAT('" . $data['dimensions'][0] . "', '%Y-%m-%d')," . $desktop . "," . $mobile . "," . $nowSql . ")";
		echo date("Y-m-d",strtotime($data['dimensions'][0])) . "\tDesktop: " . $desktop . "   \tMobile: " . $mobile . "\n";
		db_query($insertTSQL);
	}

	echo "\nDone\n";
?>