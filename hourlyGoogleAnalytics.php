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

	$body = array(
		"dimensions" => array(array("name" => "date"),array("name" => "eventName")),
		"metrics" => array(
			array("name" => "eventCount"),
			array("name" => "activeUsers")
		),
		"dateRanges" => array(array(
			"startDate" => "7daysAgo",
			"endDate" => "today"
		)),
		"dimensionFilter" => array(
            "filter" => array(
				"inListFilter" => array(
					"values" => array(
						"desktop_funnel_start", "Desktop_Quotes_V2",
						"mobile_funnel_start", "Mobile_Quotes_V2"
					),
					"caseSensitive" => "false"
				),
				"fieldName" => "eventName"
			)
		),
		"orderBys" => array(
			array("dimension" => array(
				"orderType" => "NUMERIC",
				"dimensionName" => "date"
			)),
			array("dimension" => array(
				"orderType" => "CASE_INSENSITIVE_ALPHANUMERIC",
				"dimensionName" => "eventName"
			))
		),
		"keepEmptyRows" => "true"
	);

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
			"Content-Length: " . strlen(json_encode($body)),
			"Authorization: Bearer " . $auth
		));
		curl_setopt($curlHandler, CURLOPT_POSTFIELDS, json_encode($body));

		$response = curl_exec($curlHandler);

		$analyticsData = json_decode($response,true);

		print_r($analyticsData);

		//if(!isset($analyticsData['rowCount']) || ($analyticsData['rowCount'] % 4 !== 0) || isset($analyticsData['error'])){
		if(!isset($analyticsData['rowCount']) || isset($analyticsData['error'])){
			if($errCount < 3){
				$errCount++;
				// Clear cached auth.  Doing it this way rather than "google_auth/oauth2l reset" because that simply deletes the cache file and I want to preserve file permissions
				file_put_contents("$phpdir/google_auth/.oauth2l",'');
				sleep(30);
				$analyticsData = getGAData($body,$errCount);
			} else {
				if(isset($analyticsData['error'])){
					throw new Exception("Failed after $errCount retries to get Google Analytics data - API returned:\n".print_r($analyticsData['error'],true));
					die;
				}
				if($analyticsData['rowCount'] % 4 !== 0){
					$returnedDates = "";

					foreach($analyticsData['rows'] as $returnDate){
						$returnedDates .= "\n".$returnDate['dimensionValues'][0]['value']." - ".$returnDate['dimensionValues'][1]['value'];
					}

					echo "Failed after $errCount retries to get Google Analytics data\nRow Count: ".$analyticsData['rowCount']." (Should be multiple of 4)\nResponse contains data for: ".$returnedDates."\n";
				} else {
					throw new Exception("Failed after $errCount retries to get Google Analytics data\n".print_r($analyticsData,true));
					die;
				}
			}
		}

		return $analyticsData;
	}
	
	$analyticsData = getGAData($body);

	$stats = [];
	// Make sure today is always inculded even if google doesn't return it
	$stats[trim(`date +"%Y%m%d"`)] = ['desktop_funnel_start' => 0,'Desktop_Quotes_V2' => 0,'mobile_funnel_start' => 0,'Mobile_Quotes_V2' => 0];

	foreach($analyticsData['rows'] as $row){
		$date = $row['dimensionValues'][0]['value'];
		$eventName = $row['dimensionValues'][1]['value'];
	
		// Ensure the event array exists before assigning values
		if (!isset($stats[$date][$eventName]) || !is_array($stats[$date][$eventName])) {
			$stats[$date][$eventName] = [
				'eventCount' => 0,
				'activeUsers' => 0
			];
		}
	
		$stats[$date][$eventName]['eventCount'] = $row['metricValues'][0]['value'];
		$stats[$date][$eventName]['activeUsers'] = $row['metricValues'][1]['value'];
	}

	print_r($stats);

	foreach($stats as $day => $values){
		$date = date('Y-m-d', strtotime($day));
	
		$desktop_funnel_start = $values['desktop_funnel_start']['activeUsers'] ?? 0;
		$desktop_quotes = $values['Desktop_Quotes_V2']['activeUsers'] ?? 0;
		$mobile_funnel_start = $values['mobile_funnel_start']['activeUsers'] ?? 0;
		$mobile_quotes = $values['Mobile_Quotes_V2']['activeUsers'] ?? 0;
	
		$desktop_active_users = $values['desktop_funnel_start']['activeUsers'] ?? 1; // Prevent division by zero
		$mobile_active_users = $values['mobile_funnel_start']['activeUsers'] ?? 1;
	
		// Updated conversion rate calculation incorporating active users
		$desktopRate = ($desktop_active_users > 0) ? ($desktop_quotes / $desktop_active_users) * 100 : 0;
		$mobileRate = ($mobile_active_users > 0) ? ($mobile_quotes / $mobile_active_users) * 100 : 0;

		$sql = "SELECT record_num,desktop_conversion_rate,mobile_conversion_rate FROM cache_google_analytics WHERE DATE = '{$date}'";
		$row = db_query($sql);
	
		if($row->num_rows > 1) {
			throw new Exception("Too many rows returned from DB for date ".$date);
			die;
		}
	
		if($row->num_rows == 1){
			$updateSQL = "UPDATE cache_google_analytics SET desktop_conversion_rate = ". $desktopRate . ", mobile_conversion_rate = ". $mobileRate . ", last_updated = " . $nowSql . " WHERE date = '" . $date . "'";
			print_r($updateSQL);
			db_query($updateSQL);
		}
		if($row->num_rows < 1){
			$insertSQL = "INSERT INTO cache_google_analytics (DATE,desktop_conversion_rate,mobile_conversion_rate,last_updated) VALUES ('".$date."'," . $desktopRate . "," . $mobileRate . "," . $nowSql . ")";
			db_query($insertSQL);
		}
	}
	

	echo "\nDone\n";
?>