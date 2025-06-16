<?php
	// load global libraries
	require_once('global.php');

	global $nowSql;

	// Oldest claimable lead
	$SQL = "SELECT record_num, submitted, TIMESTAMPDIFF(HOUR, submitted, $nowSql) AS 'hours' FROM leads WHERE status = 'waiting' ORDER BY updated ASC LIMIT 1;";
	$leads = db_query($SQL);
	$oldest = mysqli_fetch_array($leads, MYSQLI_ASSOC);

	if(!empty($oldest)) {
		$hours = $oldest['hours'];
		db_query("UPDATE system_health SET value={$hours}, updated={$nowSql} WHERE title = 'SolarQuotes Claimable Leads - Oldest'");
	} else { // If no records returned, there are no claimable leads (everything's good), set value to 0
		db_query("UPDATE system_health SET value=0, updated={$nowSql} WHERE title = 'SolarQuotes Claimable Leads - Oldest'");
	}

	// Youngest claimable lead
	$SQL = "SELECT record_num, submitted, TIMESTAMPDIFF(HOUR, submitted, $nowSql) AS 'hours' FROM leads WHERE openClaims = 'Y' ORDER BY record_num DESC LIMIT 1;";
	$lead = db_query($SQL);
	$lead = mysqli_fetch_array($lead, MYSQLI_ASSOC);
	if (isset($lead) && is_array($lead) && isset($lead['hours'])) {
		$hours = $lead['hours'];
	} else {
		$hours = 9999;
	}
	db_query("UPDATE system_health SET value={$hours}, updated={$nowSql} WHERE title = 'SolarQuotes Claimable Leads - Youngest'");

	// Number of claimable leads
	$SQL = "SELECT COUNT(*) as count FROM leads WHERE openClaims = 'Y' AND status = 'waiting';";
	$result = db_query($SQL);
	$result = mysqli_fetch_array($result, MYSQLI_ASSOC);
	if (isset($result) && is_array($result) && isset($result['count'])) {
		$count = $result['count'];
	} else {
		$count = 0;
	}
	db_query("UPDATE system_health SET value={$count}, updated={$nowSql} WHERE title = 'SolarQuotes Claimable Leads - Count'");

	// Lead addresses containing the "unit" keyword
	$SQL = "SELECT COUNT(*) as count FROM leads WHERE iAddress LIKE '%unit%' AND submitted >= DATE_SUB(NOW(), INTERVAL 1 WEEK);";
	$result = db_query($SQL);
	$result = mysqli_fetch_array($result, MYSQLI_ASSOC);
	if (isset($result) && is_array($result) && isset($result['count'])) {
		$count = $result['count'];
	} else {
		$count = 0;
	}
	db_query("UPDATE system_health SET value={$count}, updated={$nowSql} WHERE title = 'SolarQuotes Leads with Unit in Address'");

	// SolarQuotes My Energy Group Sydney
	$SQL = "SELECT COUNT(*) AS count FROM suppliers WHERE record_num = '12742' AND xeroContactID != ''";
	$result = db_query($SQL);
	$result = mysqli_fetch_array($result, MYSQLI_ASSOC);
	if (isset($result) && is_array($result) && isset($result['count'])) {
		$count = $result['count'];
	} else {
		$count = 0;
	}
	db_query("UPDATE system_health SET value={$count}, updated={$nowSql} WHERE title = 'SolarQuotes My Energy Group Sydney'");
?>