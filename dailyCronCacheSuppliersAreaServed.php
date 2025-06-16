<?php
	// load global libraries
	include('global.php');
	set_time_limit(1200);

	// Setup cache array
	$supplierCachedRegions = array();
	
	// Scrub table
	db_query("DELETE FROM cache_suppliers_postcode_region_served");

	// Select postcodes
	$postcodes = db_query("SELECT postcode, state, lat, lon FROM postcode_definition WHERE type = 'Delivery Area'");
	$postcodesArray = array();
	while ($postcode = mysqli_fetch_array($postcodes, MYSQLI_ASSOC)) {
		extract(HTMLEntitiesRecursive($postcode), EXTR_PREFIX_ALL, 'p');

		if (!array_key_exists($p_postcode, $postcodesArray)) {
			$postcodesArray[$p_postcode] = array("state" => $p_state, "lat" => $p_lat, "lon" => $p_lon);
		}
	}

	// Select suppliers
	$suppliers = db_query("SELECT * FROM suppliers WHERE status = 'active' AND reviewonly = 'N' ORDER BY record_num ASC");

	// Loop every supplier
	while ($supplier = mysqli_fetch_array($suppliers, MYSQLI_ASSOC)) {
		extract(HTMLEntitiesRecursive($supplier), EXTR_PREFIX_ALL, 's');
		
		$supplierRegions = db_query("SELECT P.postcode FROM postcode P INNER JOIN suppliers_postcode SP ON P.record_num = SP.postcode_id WHERE SP.supplier_id = '{$s_record_num}' ORDER BY postcode ASC");

		while ($supplierRegion = mysqli_fetch_array($supplierRegions, MYSQLI_ASSOC)) {
			extract(HTMLEntitiesRecursive($supplierRegion), EXTR_PREFIX_ALL, 'sr');

			insertCache($s_record_num, $sr_postcode);
		}

		// Now handle circles, polygons and states
		$supplierRegions = db_query("SELECT * FROM supplier_areas WHERE supplier = '{$s_record_num}' AND status = 'active'");
		
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
					
					if ($updateServedArea == true)
						insertCache($s_record_num, $postcodeKey);
				}
			}
		}
	}
	
	function insertCache($supplierID, $postcode) {
		GLOBAL $supplierCachedRegions;
		
		if ($postcode > 0) {
			if (!in_array($supplierID . "_" . $postcode, $supplierCachedRegions)) {
				$state = strtolower(lookupState($postcode));
				
				// Select feedback average
				$supplierFeedbackAverage = 0;
				$supplierFeedbackAverageCount = 0;
				$supplierFeedbacks = db_query("SELECT * FROM feedback WHERE supplier_id = '{$supplierID}' AND LOWER(iState) = '{$state}' AND public = 1 AND purchased = 'Yes'");
				$supplierFeedbackCount = mysqli_num_rows($supplierFeedbacks);

				if ($supplierFeedbackCount > 0) {
					while ($supplierFeedback = mysqli_fetch_array($supplierFeedbacks, MYSQLI_ASSOC)) {
						extract(HTMLEntitiesRecursive($supplierFeedback), EXTR_PREFIX_ALL, 'f');
						
						if (is_numeric($f_one_year_rate_value)) {
							$f_rate_value = $f_one_year_rate_value;
							$f_rate_system_quality = $f_one_year_rate_system_quality;
							$f_rate_installation = $f_one_year_rate_installation;
							$f_rate_customer_service = $f_one_year_rate_customer_service;
						}

    					$feedbackTotal = calculateFeedback($f_rate_value, $f_rate_system_quality, $f_rate_installation, $f_rate_customer_service);
    					
    					if ($feedbackTotal != false) {
    						$supplierFeedbackAverage += $feedbackTotal;
							$supplierFeedbackAverageCount++;
    					}
					}
					
					$supplierFeedbackAverage = $supplierFeedbackAverage / $supplierFeedbackAverageCount / 4;
				}
		
				db_query("INSERT INTO cache_suppliers_postcode_region_served (supplier, postcode, feedbackAverage) VALUES ('{$supplierID}', '{$postcode}', '{$supplierFeedbackAverage}')");
				
				$supplierCachedRegions[] = $supplierID . "_" . $postcode;
			}
		}
	}
	
	function calculateFeedback($rate_value, $rate_system_quality, $rate_installation, $rate_customer_service) {
		// Get the current values for this feedback
    	$feedbackSum = 0;
    	$average = 4;
    	
    	if ($rate_value > 0)
    		$feedbackSum = $rate_value;
    	else
    		$average -= 1;
    		
    	if ($rate_system_quality > 0)
    		$feedbackSum += $rate_system_quality;
    	else
    		$average -= 1;
    		
    	if ($rate_installation > 0) 
    		$feedbackSum += $rate_installation;
    	else 
    		$average -= 1;
    		
    	if ($rate_customer_service > 0) 
    		$feedbackSum += $rate_customer_service;
    	else 
    		$average -= 1;
    	
    	// This is now taking into consideration N/A
    	if ($average > 0)
			return $feedbackSum / $average * 4;
		else
			return false;
	}
?>