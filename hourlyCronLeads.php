<?php
	// load global libraries
	require_once('global.php');

	global $nowSql;

	# Leads submitted every $timeFrame hours
	$timeFrame = 12;

	$SQL = "SELECT COUNT(l.record_num) as 'count' FROM leads l WHERE l.created >= $nowSql - INTERVAL $timeFrame HOUR AND l.status != 'duplicate' AND l.leadType IN ('Residential','Commercial');";

	$leads = db_query($SQL);
	while ($lead = mysqli_fetch_array($leads, MYSQLI_ASSOC)) {
		$submitted = $lead;
	}

	if(isset($submitted) && $submitted !== "") {
		db_query("UPDATE system_health SET value={$submitted['count']}, updated={$nowSql} WHERE title = 'SolarQuotes Leads Submitted'");
	} else { // If no records returned, no leads have been submitted (ie. there's an issue)
		db_query("UPDATE system_health SET value=0, updated={$nowSql} WHERE title = 'SolarQuotes Leads Submitted'");
	}

	// Incompletes
	$lastLeadsCount = 20;
	$desktopPercentage = 0;
	$mobilePercentage = 0;
	$desktopIncomplete = 0;
	$mobileIncomplete = 0;

	$SQL = "SELECT status FROM leads WHERE SOURCE = 'SolarQuote' AND STATUS != 'duplicate' ORDER BY created DESC LIMIT {$lastLeadsCount}";
	$leadDesktopCountTotals = db_query($SQL);
	while ($leadDesktopCountTotal = mysqli_fetch_array($leadDesktopCountTotals, MYSQLI_ASSOC)) {
		if ($leadDesktopCountTotal['status'] == 'incomplete') {
			$desktopIncomplete++;
		}
	}

	$SQL = "SELECT status FROM leads WHERE source = 'SolarQuoteMobile' AND STATUS != 'duplicate' ORDER BY created DESC LIMIT {$lastLeadsCount}";
	$leadMobileCountTotals = db_query($SQL);
	while ($leadMobileCountTotal = mysqli_fetch_array($leadMobileCountTotals, MYSQLI_ASSOC)) {
		if ($leadMobileCountTotal['status'] == 'incomplete') {
			$mobileIncomplete++;
		}
	}

	if ($desktopIncomplete > 0) {
		$desktopPercentage = round(($desktopIncomplete / $lastLeadsCount) * 100);
	}

	if ($mobileIncomplete > 0) {
		$mobilePercentage = round(($mobileIncomplete / $lastLeadsCount) * 100);
	}

	db_query("UPDATE system_health SET value={$desktopPercentage}, updated={$nowSql} WHERE title = 'SolarQuotes Leads Incomplete - Desktop'");
	db_query("UPDATE system_health SET value={$mobilePercentage}, updated={$nowSql} WHERE title = 'SolarQuotes Leads Incomplete - Mobile'");

	// SolarQuotes missing data
	$SQL = "SELECT record_num, systemDetails, quoteDetails FROM leads WHERE created >= $nowSql - INTERVAL 12 HOUR AND status != 'duplicate' AND leadType IN ('Residential','Commercial')";
	$result = db_query($SQL);
	$missing = 0;
	
	while ($lead = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
		$systemDetails = unserialize(base64_decode($lead['systemDetails']));
		$quoteDetails = unserialize(base64_decode($lead['quoteDetails']));
		$features = strtolower($systemDetails['Features:'] ?? '');
		$timeframe = strtolower($quoteDetails['Timeframe for purchase:'] ?? '');
		$size = trim($systemDetails['System Size:'] ?? '');
		$requiresCheck = true;
		
		if (
			stripos($features, 'ev charger') !== false ||
			stripos($features, 'hot water heat pump') !== false ||
			stripos($features, 'off grid / remote area system') !== false
		) {
			$requiresCheck = false;
		}

		if ($requiresCheck == true && $size === '' && $timeframe === '') {
			$missing = 1;
			echo $lead['record_num'];
			print_r($features);
			print_r($quoteDetails);
			break;
		}
	}

	db_query("UPDATE system_health SET value={$missing}, updated={$nowSql} WHERE title = 'SolarQuotes Leads Missing Data'");
?>