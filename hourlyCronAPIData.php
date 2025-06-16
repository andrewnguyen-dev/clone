<?php
	// Load global libraries
	require_once('global.php');

	global $nowSql, $_connection;

	// Define tables and friendly titles for system_health
	$tables = [
		'log_supplier_api'        => 'SolarQuotes API Logs - Supplier',
		'log_supplier_parent_api' => 'SolarQuotes API Logs - Parent',
	];

	foreach ($tables as $table => $title) {
		// Current hour count
		$SQL = "SELECT COUNT(*) AS count FROM {$table} WHERE submitted >= DATE_SUB($nowSql, INTERVAL 1 HOUR) AND submitted < $nowSql";
		echo $SQL . "\n";

		$result = db_query($SQL);
		$data = mysqli_fetch_assoc($result);
		$currentCount = (int)$data['count'];

		// Calculate average for the same hour over the past 7 days
		$sum = 0;
		for ($i = 1; $i <= 7; $i++) {
			$SQL = "SELECT COUNT(*) AS count FROM {$table} WHERE submitted >= DATE_SUB(DATE_SUB($nowSql, INTERVAL {$i} DAY), INTERVAL 1 HOUR) AND submitted < DATE_SUB($nowSql, INTERVAL {$i} DAY)";
			echo $SQL . "\n";

			$pastRes = db_query($SQL);
			$pastRow = mysqli_fetch_assoc($pastRes);
			$sum += (int)$pastRow['count'];
		}
		$average = $sum / 7;

		$diff = $average > 0 ? (($currentCount - $average) / $average) * 100 : 0;
		$diff = round($diff, 2);
		echo $diff . "\n";

		// Update the current count
		$SQL = "UPDATE system_health SET value={$diff}, updated={$nowSql} WHERE title='{$title}'";
		db_query($SQL);
	}
?>

