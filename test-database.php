<?php
	$noSession = 1;

	require("global.php");

	$SQL = "SELECT record_num FROM leads ";
	$SQL .= "ORDER BY record_num DESC LIMIT 5";
	$leads = db_query($SQL);

	while ($lead = mysqli_fetch_array($leads, MYSQLI_ASSOC)) {
		echo $lead['record_num'] . PHP_EOL;
	}
?>