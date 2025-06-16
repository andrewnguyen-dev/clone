<?php
    error_reporting(E_ALL);
	ini_set('display_errors', '1');

	// load global libraries
	include('global.php');

    // SolarQuotes missing data
    $SQL = "SELECT record_num, systemDetails FROM leads WHERE created >= $nowSql - INTERVAL 5 DAY AND status != 'duplicate' AND leadType IN ('Residential','Commercial')";
    $result = db_query($SQL);

    $missingRecordNums = [];

    while ($lead = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $systemDetails = unserialize(base64_decode($lead['systemDetails']));
        $features = strtolower($systemDetails['Features:'] ?? '');
        $size = trim($systemDetails['System Size:'] ?? '');
        $requiresSize = true;

        if (strpos($features, 'ev charger') !== false || strpos($features, 'hot water heat pump') !== false) {
            $requiresSize = false;
        }

        if ($requiresSize && $size === '') {
            $missingRecordNums[] = $lead['record_num'];
        }
    }

    // Output missing record numbers as comma-separated string
    echo implode(',', $missingRecordNums);


?>