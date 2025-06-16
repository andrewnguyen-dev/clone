<?php
	// load global libraries
    include('global.php');
    set_time_limit(0);

    // Setup the variables
    $currentSupplierID = 0;

    $feedbackCount = 0;
    $feedbackTotalWeighted = 0;
    $feedbackTotal = 0;
    $supplierName = "";
    $supplierStatus = "";
    $supplierURLName = "";

    $timeframeBreakup = array(
    	'6m' => array(
    		'lowerlimit' => (new \DateTime())->modify('-6 months'),
    		'total' => 0, 'total_weighted' => 0, 'count' => 0
    	),
    	'12m' => array(
    		'lowerlimit' => (new \DateTime())->modify('-12 months'),
    		'total' => 0, 'total_weighted' => 0, 'count' => 0
    	),
    	'all' => array(
    		'lowerlimit' => (new \DateTime('19700101')),
    		'total' => 0, 'total_weighted' => 0, 'count' => 0
    	)
    );

    $stateBreakup = $reset_stateBreakup = array(
        'ACT' => array(),
        'NSW' => array(),
        'NT' => array(),
        'QLD' => array(),
        'SA' => array(),
        'TAS' => array(),
        'VIC' => array(),
        'WA' => array(),
	    'ALL' => array()
    );

    // Select postcodes
	$postcodes = db_query("SELECT postcode, state, lat, lon FROM postcode_definition WHERE type = 'Delivery Area'");
	$postcodesArray = array();
	while ($postcode = mysqli_fetch_array($postcodes, MYSQLI_ASSOC)) {
		extract(HTMLEntitiesRecursive($postcode), EXTR_PREFIX_ALL, 'p');

		if (!array_key_exists($p_postcode, $postcodesArray)) {
			$postcodesArray[$p_postcode] = array("state" => $p_state, "lat" => $p_lat, "lon" => $p_lon);
		}
	}

// Remove all items from the table
$SQL = "truncate table cache_feedback_total";
db_query($SQL);

// These are computed columns that can remove some of the processing from PHP
$extraCols = [
	'IF(feedback_date > NOW() - INTERVAL 6 MONTH, 1, 0) 6m',
	'IF(feedback_date > NOW() - INTERVAL 12 MONTH, 1, 0) 12m'
];

// Select all feedback that has been marked as public
$SQL = "SELECT F.record_num as feedback_id, F.rate_value, F.rate_system_quality, F.rate_installation,F.rate_customer_service, F.one_year_rate_value,F.one_year_rate_system_quality,F.one_year_rate_installation,F.one_year_rate_customer_service,F.iState, F.feedback_date,";
$SQL .= ' S.record_num as row_supplier_id,';
$SQL .= " S.company, S.reviewonly, case when ( S.status = 'active' OR extraLeads = 'Y' ) AND reviewOnly != 'Y' then 'active' else 'paused' end AS supplierStatus, ";
$SQL .= " IF(S.parent > 1 AND S.parentUseReview = 'Y', SP.parentName, S.company) as supplierName, ";
$SQL .= " IF(S.parent > 1 AND S.parentUseReview = 'Y', CONCAT('SP_', SP.record_num), CONCAT('S_', S.record_num)) as supplier_id, ";
if(!empty($extraCols)){
	$SQL .= implode(',', $extraCols);
}
$SQL .= " FROM feedback F ";
$SQL .= "INNER JOIN suppliers S ON F.supplier_id = S.record_num ";
$SQL .= "INNER JOIN suppliers_parent SP ON S.parent  = SP.record_num ";
$SQL .= "WHERE public = 1 AND purchased='Yes' AND iState != '' ";
$SQL .= "AND verified = 'Yes' AND category_id IN (4,5) ";
// Ordering by supplierName first is not enough because a supplier parent may have the same parentName as another supplier child (not child of the parent)
// We therefore need to order by the supplier_id as well so the list separates the feedback of each supplier correctly, which we'll use to know when all feedback of a supplier has been processed
$SQL .= "ORDER BY supplierName ASC, supplier_id, F.record_num DESC ";

$feedback = db_query($SQL);

// Builds an array of status 'active' parents that have at least a child that is active or pause and accepting
$SQL = " SELECT SP.record_num, 'active' AS status ";
$SQL .= " FROM suppliers_parent SP  ";
$SQL .= " INNER JOIN suppliers S on SP.record_num = S.parent ";
$SQL .= " WHERE SP.record_num > 1 and S.parentUseReview = 'Y' AND S.reviewOnly != 'Y' AND ( S.status = 'active' OR S.extraLeads = 'Y') ";
$SQL .= " GROUP BY SP.record_num ";

$parentsStatus = mysqli_fetch_all(db_query($SQL), MYSQLI_ASSOC);
$parentsStatus = array_combine(array_column($parentsStatus, 'record_num'), array_column($parentsStatus, 'status'));

while($feedbackRow = mysqli_fetch_array($feedback, MYSQLI_ASSOC)){
	extract(htmlentitiesRecursive($feedbackRow), EXTR_PREFIX_ALL, 'f');
	$iState = strtoupper($feedbackRow['iState']);

	if ($f_supplier_id !== $currentSupplierID) {
		$_currentSupplierID = str_replace(['SP_', 'S_'], '', $currentSupplierID); // Sanitize the current ID
		if ($_currentSupplierID > 0) {
			$feedbackTotalWeighted = round($feedbackTotalWeighted);
			$feedbackTotal = round($feedbackTotal);

			if(stripos($currentSupplierID, 'SP_') !== false){
				$statesServed = updateSuppliersStatesServed(selectParentChildren($_currentSupplierID));
			} else {
				$statesServed = updateSuppliersStatesServed($_currentSupplierID);
			}

			$stateBreakup = array_filter($stateBreakup);

			foreach($stateBreakup as $stateName => $_timeframeBreakup){
				foreach($_timeframeBreakup as $idx => $timeframe){
					$feedbackTotal = $timeframe['total'];
					$feedbackTotalWeighted = $timeframe['total_weighted'];
					$feedbackCount = $timeframe['count'];
					if(! $feedbackTotal && ! $feedbackTotalWeighted)
						continue;

					if(stripos($currentSupplierID, 'SP_') !== false){
						$supplierStatus = $parentsStatus[$_currentSupplierID] ?? 'paused';
					}

					$SQL = 'INSERT INTO cache_feedback_total (supplierName, supplierURLName, state, timeframe, feedbackTotal, feedbackCount, feedbackTotalWeighted, statesServed, supplierStatus) ';
					$SQL .= "VALUES ('{$supplierName}', '{$supplierURLName}', '{$stateName}', '{$idx}', {$feedbackTotal}, {$feedbackCount}, {$feedbackTotalWeighted}, {$statesServed}, '{$supplierStatus}')";
					db_query($SQL);
				}
			}
		}
		// Reset the variables
		$feedbackCount = 0;
		$feedbackTotalWeighted = 0;
		$feedbackTotal = 0;
		$supplierName = $f_supplierName;
		$supplierStatus = $f_supplierStatus;
		$supplierURLName = sanitizeURL($f_supplierName);
		$currentSupplierID = $f_supplier_id;
		$stateBreakup = $reset_stateBreakup;
	}

	// Are we using the original or one year followup figures
	if (is_numeric($f_one_year_rate_value)) {
		$f_rate_value = $f_one_year_rate_value;
		$f_rate_system_quality = $f_one_year_rate_system_quality;
		$f_rate_installation = $f_one_year_rate_installation;
		$f_rate_customer_service = $f_one_year_rate_customer_service;
	}

	$calculatedTotalWeighted = calculateReviewWeighted($f_rate_value, $f_rate_system_quality, $f_rate_installation, $f_rate_customer_service);
	$calculatedTotal = calculateReview($f_rate_value, $f_rate_system_quality, $f_rate_installation, $f_rate_customer_service);

	if ($calculatedTotalWeighted > -100) {
		if($f_6m > 0){
			if(! isset($stateBreakup[$iState]['6m'])) $stateBreakup[$iState]['6m'] = ['total' => 0, 'total_weighted' => 0, 'count' => 0 ];
			$stateBreakup[$iState]['6m']['total'] += $calculatedTotal;
			$stateBreakup[$iState]['6m']['total_weighted'] += $calculatedTotalWeighted;
			$stateBreakup[$iState]['6m']['count']++;

			if(! isset($stateBreakup['ALL']['6m'])) $stateBreakup['ALL']['6m'] = ['total' => 0, 'total_weighted' => 0, 'count' => 0 ];
			$stateBreakup['ALL']['6m']['total'] += $calculatedTotal;
			$stateBreakup['ALL']['6m']['total_weighted'] += $calculatedTotalWeighted;
			$stateBreakup['ALL']['6m']['count']++;
		}
		if($f_12m > 0){
			if(! isset($stateBreakup[$iState]['12m'])) $stateBreakup[$iState]['12m'] = ['total' => 0.0, 'total_weighted' => 0.0, 'count' => 0 ];
			$stateBreakup[$iState]['12m']['total'] += $calculatedTotal;
			$stateBreakup[$iState]['12m']['total_weighted'] += $calculatedTotalWeighted;
			$stateBreakup[$iState]['12m']['count']++;

			if(! isset($stateBreakup['ALL']['12m'])) $stateBreakup['ALL']['12m'] = ['total' => 0, 'total_weighted' => 0, 'count' => 0 ];
			$stateBreakup['ALL']['12m']['total'] += $calculatedTotal;
			$stateBreakup['ALL']['12m']['total_weighted'] += $calculatedTotalWeighted;
			$stateBreakup['ALL']['12m']['count']++;
		}
		// Always add to "all" timeframe
		if(! isset($stateBreakup[$iState]['all'])) $stateBreakup[$iState]['all'] = ['total' => 0, 'total_weighted' => 0, 'count' => 0 ];
		$stateBreakup[$iState]['all']['total'] += $calculatedTotal;
		$stateBreakup[$iState]['all']['total_weighted'] += $calculatedTotalWeighted;
		$stateBreakup[$iState]['all']['count']++;

		if(! isset($stateBreakup['ALL']['all'])) $stateBreakup['ALL']['all'] = ['total' => 0, 'total_weighted' => 0, 'count' => 0 ];
		$stateBreakup['ALL']['all']['total'] += $calculatedTotal;
		$stateBreakup['ALL']['all']['total_weighted'] += $calculatedTotalWeighted;
		$stateBreakup['ALL']['all']['count']++;

		$feedbackTotalWeighted += $calculatedTotalWeighted;
		$feedbackTotal += $calculatedTotal;
		$feedbackCount++;
	}
}

// Final insert to handle the last supplier
if (str_replace(['SP_', 'S_'], '', $currentSupplierID) > 0) {
	$feedbackTotalWeighted = round($feedbackTotalWeighted);
		$feedbackTotal = round($feedbackTotal);

	$statesServed = 0;
	if(stripos($_currentSupplierID, 'SP_') !== false){
		$statesServed = updateSuppliersStatesServed(selectParentChildren($f_row_supplier_id));
	} else {
		$statesServed = updateSuppliersStatesServed($f_row_supplier_id);
	}


	$stateBreakup = array_filter($stateBreakup);
	foreach($stateBreakup as $stateName => $_timeframeBreakup){
		foreach($_timeframeBreakup as $idx => $timeframe){
			$feedbackTotal = $timeframe['total'];
			$feedbackTotalWeighted = $timeframe['total_weighted'];
			$feedbackCount = $timeframe['count'];
			if(! $feedbackTotal && ! $feedbackTotalWeighted)
				continue;

			$SQL = 'INSERT INTO cache_feedback_total (supplierName, supplierURLName, state, timeframe, feedbackTotal, feedbackCount, feedbackTotalWeighted, statesServed, supplierStatus) ';
			$SQL .= "VALUES ('{$supplierName}', '{$supplierURLName}', '{$stateName}', '{$idx}', {$feedbackTotal}, {$feedbackCount}, {$feedbackTotalWeighted}, {$statesServed}, '{$supplierStatus}')";
			db_query($SQL);
		}
	}
}

	executeSupplierAreasServed();

	SendMail($techEmail, $techName, 'COMPLETED SQ.dailyCronCacheFeedback.php', 'Doneski');

	// Set created date off all rows to now
	$SQL = "UPDATE cache_feedback_total SET created = $nowSql";
	db_query($SQL);
// Finally, calculate the suppliers per city
function executeSupplierAreasServed() {
	db_query("truncate table cache_feedback_cities");

	$results = db_query("SELECT distinct supplierName, supplierURLName FROM cache_feedback_total WHERE supplierStatus = 'active';");

		while ($result = mysqli_fetch_array($results, MYSQLI_ASSOC)) {
    		extract(HTMLEntitiesRecursive($result), EXTR_PREFIX_ALL, 'c');

    		$supplierParentID = selectSupplierMatchParent($c_supplierName);

    		if ($supplierParentID == false) {
				$supplierID = selectSupplier($c_supplierName);

				if ($supplierID == false) {
					// This supplier does not serve the defined areas
				} else {
					updateSuppliersAreasServed($supplierID, $c_supplierName, $c_supplierURLName);
				}
    		} else {
				// Get all active children
				$childrenID = selectParentChildren($supplierParentID);

				if ($childrenID == false) {
					// This parent has no active children
				} else {
					updateSuppliersAreasServed($childrenID, $c_supplierName, $c_supplierURLName);
				}
    		}
		}
	}

	function selectSupplierMatchParent($supplierName) {
		$SQL = "SELECT * FROM suppliers_parent WHERE parentName = '{$supplierName}'";
		$results = db_query($SQL);

		if (mysqli_num_rows($results) == 0)
			return false;

		while ($result = mysqli_fetch_array($results, MYSQLI_ASSOC)) {
			extract(HTMLEntitiesRecursive($result), EXTR_PREFIX_ALL, 'p');

			return $p_record_num;
		}

		return false;
	}

	function selectSupplier($supplier) {
		$SQL = "SELECT * FROM suppliers WHERE company = '{$supplier}' AND (status = 'active' OR (status = 'paused' AND extraLeads = 'Y')) AND reviewOnly = 'N'";
		$results = db_query($SQL);

		if (mysqli_num_rows($results) == 0)
			return false;

		while ($result = mysqli_fetch_array($results, MYSQLI_ASSOC)) {
			extract(HTMLEntitiesRecursive($result), EXTR_PREFIX_ALL, 's');

			return $s_record_num;
		}

		return false;
	}

	function selectParentChildren($parentID) {
		$children = array();

		$SQL = "SELECT * FROM suppliers WHERE parent = {$parentID} AND (status = 'active' OR (status = 'paused' AND extraLeads = 'Y')) AND reviewOnly = 'N'";
		$results = db_query($SQL);

		if (mysqli_num_rows($results) == 0)
			return false;
		else {
			while ($result = mysqli_fetch_array($results, MYSQLI_ASSOC)) {
				extract(HTMLEntitiesRecursive($result), EXTR_PREFIX_ALL, 's');

				$children[] = $s_record_num;
			}

			return implode(",", $children);
		}
	}

	function updateSuppliersStatesServed($suppliersID) {
		GLOBAL $postcodesArray;

		$statesServed = 0;
		$states = array();

		if ($suppliersID == '')
			return 0;

		// Postcodes
		$SQL = "SELECT DISTINCT postcode FROM postcode P ";
		$SQL .= "INNER JOIN suppliers_postcode SP ON SP.postcode_id = P.record_num ";
		$SQL .= "WHERE SP.supplier_id IN ({$suppliersID})";
		$results = db_query($SQL);
		while ($result = mysqli_fetch_array($results, MYSQLI_ASSOC)) {
			extract(HTMLEntitiesRecursive($result), EXTR_PREFIX_ALL, 's');

			$state = postcodeToState($s_postcode);

			if ($state != '' && !in_array(strtoupper($state),$states)) {
				$states[] = strtoupper($state);
				$statesServed++;
			}
		}

		$supplierRegions = db_query("SELECT * FROM supplier_areas WHERE supplier IN ({$suppliersID}) AND status = 'active'");
		if (mysqli_num_rows($supplierRegions) > 0) {
			foreach ($postcodesArray as $postcodeKey => $postcodeValue) {
				// Reset pointer
				mysqli_data_seek($supplierRegions, 0);

				while ($supplierRegion = mysqli_fetch_array($supplierRegions, MYSQLI_ASSOC)) {
					extract(HTMLEntitiesRecursive($supplierRegion), EXTR_PREFIX_ALL, 'a');

					$updateServedArea = false;

					switch ($a_type) {
						case 'state':
							if (strtolower($a_details) == strtolower($postcodeValue['state']))
								$updateServedArea = true;
							break;
						case 'circle':
							if (geoPointInCircleArea($postcodeValue['lat'], $postcodeValue['lon'], $a_details))
								$updateServedArea = true;
							break;
						case 'drivingdistance': 
							if (geoPointInDrivingDistance($postcodeValue['lat'], $postcodeValue['lon'], $a_details)) 
								$updateServedArea = true;
							break;
						case 'polygon':
							if (geoPointInPolyArea($postcodeValue['lat'], $postcodeValue['lon'], $a_details))
								$updateServedArea = true;
							break;
					}

					if ($updateServedArea == true) {
						if (!in_array(strtoupper($postcodeValue['state']),$states)) {
							$states[] = strtoupper($postcodeValue['state']);
							$statesServed++;
						}
					}
				}
			}
		}

		return $statesServed;
	}

	function updateSuppliersAreasServed($suppliersID, $supplierName, $supplierURL) {
		GLOBAL $areasLatLon;

		foreach ($areasLatLon AS $key => $area) {
			$updateServedArea = false;

			// Direct postcode first
			$SQL = "SELECT * FROM suppliers_postcode SP INNER JOIN postcode P ON SP.postcode_id = P.record_num WHERE SP.supplier_id IN ({$suppliersID}) AND P.postcode = {$area[0]}";
			$result = db_query($SQL);

			if (mysqli_num_rows($result) > 0) {
				$updateServedArea = true;
			} else {
				$SQL = "SELECT * FROM supplier_areas WHERE supplier IN ({$suppliersID}) AND status = 'active'";
				$results = db_query($SQL);

				while ($result = mysqli_fetch_array($results, MYSQLI_ASSOC)) {
					extract(HTMLEntitiesRecursive($result), EXTR_PREFIX_ALL, 'a');

					switch ($a_type){
						case 'state':
							if (strtolower($a_details) == strtolower($area[1]))
								$updateServedArea = true;
							break;
						case 'circle':
							if (geoPointInCircleArea($area[2], $area[3], $a_details))
								$updateServedArea = true;
							break;
						case 'drivingdistance': 
							if (geoPointInDrivingDistance($area[2], $area[3], $a_details)) 
								$updateServedArea = true;
							break;
						case 'polygon':
							if (geoPointInPolyArea($area[2], $area[3], $a_details))
								$updateServedArea = true;
							break;
					}
				}
			}

			if ($updateServedArea == true) {
				$SQL = "INSERT INTO cache_feedback_cities (supplierName, supplierURLName, cityURLName, cityName) VALUES ('{$supplierName}', '{$supplierURL}', '{$key}', '{$area[4]}')";
				db_query($SQL);
			}
		}
	}

	function calculateReviewWeighted($f_rate_value, $f_rate_system_quality, $f_rate_installation, $f_rate_customer_service) {
		// Get the current values for this feedback
    	$feedbackSum = 0;
    	$average = 4;

    	if ($f_rate_value > 0)
    		$feedbackSum = rawToWeightedFeedback($f_rate_value);
    	else
    		$average -= 1;

    	if ($f_rate_system_quality > 0)
    		$feedbackSum += rawToWeightedFeedback($f_rate_system_quality);
    	else
    		$average -= 1;

    	if ($f_rate_installation > 0)
    		$feedbackSum += rawToWeightedFeedback($f_rate_installation);
    	else
    		$average -= 1;

    	if ($f_rate_customer_service > 0)
    		$feedbackSum += rawToWeightedFeedback($f_rate_customer_service);
    	else
    		$average -= 1;

    	// This is now taking into consideration N/A
    	if ($average > 0)
			return $feedbackSum / $average * 4;
		else
			return -100;
	}

	function calculateReview($f_rate_value, $f_rate_system_quality, $f_rate_installation, $f_rate_customer_service) {
		// Get the current values for this feedback
    	$feedbackSum = 0;
    	$average = 4;

    	if ($f_rate_value > 0)
    		$feedbackSum = $f_rate_value;
    	else
    		$average -= 1;

    	if ($f_rate_system_quality > 0)
    		$feedbackSum += $f_rate_system_quality;
    	else
    		$average -= 1;

    	if ($f_rate_installation > 0)
    		$feedbackSum += $f_rate_installation;
    	else
    		$average -= 1;

    	if ($f_rate_customer_service > 0)
    		$feedbackSum += $f_rate_customer_service;
    	else
    		$average -= 1;

    	// This is now taking into consideration N/A
    	if ($average > 0)
			return $feedbackSum / $average * 4;
		else
			return false;
	}

	function rawToWeightedFeedback($raw) {
		switch ($raw) {
			case 1:
				return -3;
				break;
			case 2:
				return -2;
				break;
			case 3:
				return -1;
				break;
			case 4:
				return 1;
				break;
			case 5:
				return 2;
				break;
			default:
				return 0;
				break;
		}
	}

	function postcodeToState($postcode) {
		$SQL = "SELECT DISTINCT state FROM postcode_definition WHERE postcode = '{$postcode}' AND type = 'Delivery Area'";

		return db_getVal($SQL);
	}
?>