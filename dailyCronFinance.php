<?php
	// load global libraries
	require_once('global.php');

	global $nowSql;

	// get the oldest finance request that's processing
	$SQL = "SELECT TIMESTAMPDIFF(MINUTE, created, $nowSql) AS oldest
		FROM lead_finance WHERE STATUS = 'processing' ORDER BY created ASC LIMIT 1";
	$finOld = db_query($SQL);
	$oldest = mysqli_fetch_array($finOld, MYSQLI_ASSOC);

	if(!empty($oldest)) {
		$hours = $oldest['oldest'];
		db_query("UPDATE system_health SET value={$hours}, updated={$nowSql} WHERE title = 'SolarQuotes Finance - Oldest'");
	} else { // If no records returned, there are no processing finance requests (everything's good), set value to 0
		db_query("UPDATE system_health SET value=0, updated={$nowSql} WHERE title = 'SolarQuotes Finance - Oldest'");
	}

	// Number of finance requests in the last 24 hours
	$SQL = "SELECT COUNT(*) AS count FROM lead_finance WHERE created >= DATE_SUB($nowSql, INTERVAL 24 HOUR)";
	$finCount = db_query($SQL);
	$count = mysqli_fetch_array($finCount, MYSQLI_ASSOC);

	if(!empty($count)) {
		$finReqCount = $count['count'];
		db_query("UPDATE system_health SET value={$finReqCount}, updated={$nowSql} WHERE title = 'SolarQuotes Finance - Requests'");
	} else { // Should always return a value, but if it doesn't, set value to 0 to indicate a problem
		db_query("UPDATE system_health SET value=0, updated={$nowSql} WHERE title = 'SolarQuotes Finance - Requests'");
	}

	// Number of leads requesting finance in the last 24 hours
	$SQL = "SELECT COUNT(DISTINCT(lead_id)) AS count FROM lead_finance WHERE created >= DATE_SUB($nowSql, INTERVAL 24 HOUR)";
	$finLead = db_query($SQL);
	$countLead = mysqli_fetch_array($finLead, MYSQLI_ASSOC);

	if(!empty($countLead)) {
		$finLeadCount = $countLead['count'];
		db_query("UPDATE system_health SET value={$finLeadCount}, updated={$nowSql} WHERE title = 'SolarQuotes Finance - Leads'");
	} else { // Should always return a value, but if it doesn't, set value to 0 to indicate a problem
		db_query("UPDATE system_health SET value=0, updated={$nowSql} WHERE title = 'SolarQuotes Finance - Leads'");
	}

?>