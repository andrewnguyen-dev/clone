<?php
	// Load global libraries
	require_once('global.php');

	global $nowSql;

	// Determine the hour for the previous period
	$prevHour = (int)db_getVal("SELECT DATE_FORMAT(DATE_SUB($nowSql, INTERVAL 1 HOUR), '%H')");
	echo $prevHour;

	$SystemHealthName = '';

	// Decide which system_health record to update
	if ($prevHour >= 10 && $prevHour < 21) {
		$SystemHealthName = 'SolarQuotes Leads Dispatched - Peak';
	} elseif (($prevHour > 7 && $prevHour < 10) || ($prevHour >= 21 && $prevHour < 23)) {
		$SystemHealthName = 'SolarQuotes Leads Dispatched - Offpeak';
	}

	if ($SystemHealthName) {
		// Count dispatched leads in the previous hour
		$SQL = "SELECT COUNT(*) AS count FROM leads WHERE status = 'dispatched' AND updated >= DATE_SUB($nowSql, INTERVAL 1 HOUR) AND updated < $nowSql";
		echo "SQL: $SQL\n"; // Debugging output

		$result = db_query($SQL);
		$row = mysqli_fetch_assoc($result);
		$dispatchCount = (int)$row['count'];

		$SQL = "UPDATE system_health SET value={$dispatchCount}, updated={$nowSql} WHERE title='{$SystemHealthName}' LIMIT 1";
		echo "SQL: $SQL\n";

		db_query($SQL);
	}
?>
