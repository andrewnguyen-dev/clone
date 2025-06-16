<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

	// load global libraries
    include('global.php');
    set_time_limit(360);

	// Delete paused suppliers current badges by setting them to historical
	db_query("UPDATE cache_trust_badges SET position = 'historical', trustBadge = 'Removed', changed = {$nowSql} WHERE status = 'paused' AND position = 'current';");
	
	// Delete all old records
    db_query("DELETE FROM cache_trust_badges WHERE position = 'previous'");
    db_query("UPDATE cache_trust_badges SET position = 'previous' WHERE position = 'current'");

    // Loop through every parent with at least a single active supplier
    $SQL = "SELECT * FROM suppliers_parent WHERE record_num > 1 AND trustBadgeEnabled = 'Y'";
    $parents = db_query($SQL);

    while ($parent = mysqli_fetch_array($parents, MYSQLI_ASSOC)) {
    	extract(htmlentitiesRecursive($parent), EXTR_PREFIX_ALL, 'p');

    	// Continue if we have at least a single active child supplier
    	$activeChildren = db_getVal("SELECT COUNT(*) AS Count FROM suppliers WHERE parent = '{$p_record_num}' AND reviewonly = 'N' AND trustBadgeUseParent = 'Y' AND ( status = 'active' OR extraLeads = 'Y' )");
    	if ($activeChildren > 0) {
    		// Variables for the database
    		$dbTrustBadge = '';
    		$dbSupplierName = $p_parentName;
    		$dbSupplierURLName = sanitizeURL($p_parentName);
    		$dbParent = $p_record_num;
    		$dbFirstLead = '';
    		$dbFeedbackCount = '';
    		$dbFeedbackTotal = '';
    		$dbFeedbackTotalWeighted = '';

    		// Get all the children
			$allChildren = selectAllChildren($p_record_num);

			// Select all feedback for all children
			$feedbacks = selectAllFeedback($allChildren);

			$dbFirstLead = selectFirstLeadDate($allChildren);
			$dbFeedbackCount = mysqli_num_rows($feedbacks);
			$dbFeedbackTotalValues = calculateAllFeedbacks($feedbacks);
			$dbFeedbackTotalWeighted = $dbFeedbackTotalValues[0];

			$dbTrustBadge = calculateBadge($dbFeedbackCount, $dbFeedbackTotalWeighted);

			if ($dbTrustBadge != 'None') {
				// All information has been gatherered, INSERT here
				$SQL = "INSERT INTO cache_trust_badges (trustBadge, supplierName, supplierURLName, parent, firstLead, feedbackCount, feedbackValue, feedbackInstall, feedbackQuality, feedbackService, feedbackTotal, feedbackTotalWeighted) ";
				$SQL .= "VALUES ('{$dbTrustBadge}', '{$dbSupplierName}', '{$dbSupplierURLName}', '{$dbParent}', '{$dbFirstLead}', '{$dbFeedbackCount}', '{$dbFeedbackTotalValues[1]}', '{$dbFeedbackTotalValues[2]}', '{$dbFeedbackTotalValues[3]}', '{$dbFeedbackTotalValues[4]}', '{$dbFeedbackTotalWeighted}', '{$dbFeedbackTotalWeighted}')";
				db_query($SQL);
			}
    	}
	}

    // Loop through every active child that either does not belong to a parent or is unlinked from the parent
    $SQL = "SELECT * FROM suppliers WHERE trustBadgeEnabled = 'Y' AND reviewonly = 'N' AND (parent = 1 OR trustBadgeUseParent = 'N') AND ( status = 'active' OR extraLeads = 'Y' )";
    $suppliers = db_query($SQL);
    while ($supplier = mysqli_fetch_array($suppliers, MYSQLI_ASSOC)) {
    	extract(htmlentitiesRecursive($supplier), EXTR_PREFIX_ALL, 's');

    	// Variables for the database
    	$dbTrustBadge = '';
    	$dbSupplierName = $s_company;
    	$dbSupplierURLName = sanitizeURL($s_company);
    	$dbSupplier = $s_record_num;
    	$dbFirstLead = '';
    	$dbFeedbackCount = '';
    	$dbFeedbackTotal = '';
    	$dbFeedbackTotalWeighted = '';

    	// To make life easy, put this single element into an array
    	$allChildren = array($s_record_num);

    	// Select all feedback for this supplier
		$feedbacks = selectAllFeedback($allChildren);

		$dbFirstLead = selectFirstLeadDate($allChildren);
		$dbFeedbackCount = mysqli_num_rows($feedbacks);
		$dbFeedbackTotalValues = calculateAllFeedbacks($feedbacks);
		$dbFeedbackTotalWeighted = $dbFeedbackTotalValues[0];

		$dbTrustBadge = calculateBadge($dbFeedbackCount, $dbFeedbackTotalWeighted);

		if ($dbTrustBadge != 'None') {
			// All information has been gatherered, INSERT here
			$SQL = "INSERT INTO cache_trust_badges (trustBadge, supplierName, supplierURLName, supplier, firstLead, feedbackCount, feedbackValue, feedbackInstall, feedbackQuality, feedbackService, feedbackTotal, feedbackTotalWeighted) ";
			$SQL .= "VALUES ('{$dbTrustBadge}', '{$dbSupplierName}', '{$dbSupplierURLName}', '{$dbSupplier}', '{$dbFirstLead}', '{$dbFeedbackCount}', '{$dbFeedbackTotalValues[1]}', '{$dbFeedbackTotalValues[2]}', '{$dbFeedbackTotalValues[3]}', '{$dbFeedbackTotalValues[4]}', '{$dbFeedbackTotalWeighted}', '{$dbFeedbackTotalWeighted}')";
			db_query($SQL);
		}
	}

	calculateChanges();

	echo "Done";

    // **********************************************
    // Helper Functions
    // **********************************************
    function calculateChanges() {
		global $techEmail, $techName;

		$previousBadges = array();
		$currentBadges = array();

		// store new suppliers and who have changed rank
		$changedBadges = [
			'new' => [],
			'changed' => [],
			'removed' => []
		];

		// Load all information into array
		$SQL = "
			SELECT TB.*, IFNULL(S.publicAddress, P.publicAddress) as publicAddress, IF(TBH.record_num IS NULL, 'Yes', 'No') as firstTimeChange
			FROM cache_trust_badges TB
			LEFT JOIN suppliers S ON S.record_num = TB.supplier
			LEFT JOIN suppliers_parent P ON P.record_num = TB.parent
			LEFT JOIN cache_trust_badges TBH 
				ON	TBH.position = 'historical' 
					AND (TBH.parent = TB.parent OR TBH.supplier = TB.supplier)
					AND TBH.trustBadge = TB.trustBadge
			WHERE TB.position <> 'historical'
			GROUP BY TB.record_num
		";
		$results = db_query($SQL);

		while ($result = mysqli_fetch_array($results, MYSQLI_ASSOC)) {
    		extract(htmlentitiesRecursive($result), EXTR_PREFIX_ALL, 'r');

    		if ($r_position == 'current')
    			$currentBadges[] = array("Supplier" => $r_supplierName, "SupplierURL" => $r_supplierURLName, "Rank" => $r_trustBadge, "SupplierID" => $r_supplier, "ParentID" => $r_parent, 'score' => round($r_feedbackTotal, 1), 'publicAddress' => $r_publicAddress, 'count' => $r_feedbackCount, 'firstTimeChange' => $r_firstTimeChange);
    		else
    			$previousBadges[] = array("Supplier" => $r_supplierName, "SupplierURL" => $r_supplierURLName, "Rank" => $r_trustBadge, "SupplierID" => $r_supplier, "ParentID" => $r_parent, 'score' => round($r_feedbackTotal, 1), 'publicAddress' => $r_publicAddress, 'count' => $r_feedbackCount, 'firstTimeChange' => $r_firstTimeChange);
		}

		// Match new suppliers
		foreach ($currentBadges AS $currentSupplier) {
			$found = false;

			foreach ($previousBadges AS $previousSupplier) {
				if ($currentSupplier['Supplier'] == $previousSupplier['Supplier'])
					$found = true;
			}

			if ($found == false) {
				sendTrustBadgeEmail($currentSupplier, "", "new");
				$changedBadges['new'][] = $currentSupplier;
			}				
		}

		// Match suppliers who have changed rank
		foreach ($currentBadges AS $currentSupplier) {
			foreach ($previousBadges AS $previousSupplier) {
				if ($currentSupplier['Supplier'] == $previousSupplier['Supplier']) {
					if ($currentSupplier['Rank'] != $previousSupplier['Rank']) {
						sendTrustBadgeEmail($currentSupplier, $previousSupplier['Rank'], "changed");
						$currentSupplier['PreviousRank'] = $previousSupplier['Rank'];
						$changedBadges['changed'][] = $currentSupplier;
					}
				}
			}
		}

		// Match suppliers who dropped out
		foreach ($previousBadges AS $previousSupplier) {
			$found = false;

			foreach ($currentBadges AS $currentSupplier) {
				if ($previousSupplier['Supplier'] == $currentSupplier['Supplier'])
					$found = true;
			}

			if ($found == false) {
				sendTrustBadgeEmail($previousSupplier, "", "removed");
				$changedBadges['removed'][] = $previousSupplier;
			}				
		}

		saveHistoricalChanges($changedBadges);

		$csvFile = generateBadgesCsv($changedBadges);
		sendMailWithAttachmentNoTemplate(
			$techEmail,
			$techName,
			"Monthly Trust Badges Cron",
			"Attached the csv file with all the suppliers that were changed on the cron executed at " . date('d/m/Y'),
			$csvFile
		);

    }

	function saveHistoricalChanges($changedBadges) {
		global $nowSql;
		foreach($changedBadges as $changeType => $changedSuppliers) {
			foreach($changedSuppliers as $supplier) {
				$trustBadge = $changeType == 'removed' ? 'Removed' : $supplier['Rank'];
				$position = $changeType == 'removed' ? 'previous' : 'current';
				$SQL = "
					INSERT INTO cache_trust_badges (trustBadge, supplierName, supplierURLName, supplier, parent, firstLead, feedbackCount, feedbackValue, feedbackInstall, feedbackQuality, feedbackService, feedbackTotal, feedbackTotalWeighted, changed, position) 
						SELECT '{$trustBadge}', supplierName, supplierURLName, supplier, parent, firstLead, feedbackCount, feedbackValue, feedbackInstall, feedbackQuality, feedbackService, feedbackTotal, feedbackTotalWeighted, {$nowSql}, 'historical'
						FROM cache_trust_badges WHERE position = '{$position}'
				";
				if($supplier['SupplierID'] > 0) {
					$SQL .= " AND supplier = {$supplier['SupplierID']}";
				}else{
					$SQL .= " AND parent = {$supplier['ParentID']}";
				}
				db_query($SQL);
			}
		}
	}

    function sendTrustBadgeEmail($supplier, $supplierPreviousRank, $emailType) {
    	global $salesName, $salesEmail, $techEmail;

    	try {
    		// Select the correct email template
			if (($emailType == "new") || ($emailType == "changed" && ($supplierPreviousRank == "Silver" || $supplier['Rank'] == "Legendary"))) {
				if ($supplier['Rank'] == "Legendary")
					$emailTemplate = "trustBadgeNewLegendary";
				elseif ($supplier['Rank'] == "Platinum")
					$emailTemplate = "trustBadgeNewPlatinum";
				elseif ($supplier['Rank'] == "Gold")
					$emailTemplate = "trustBadgeNewGold";
				else
					$emailTemplate = "trustBadgeNewSilver";
			} elseif ($emailType == "changed") {
				if ($supplier['Rank'] == "Platinum") {
					$emailTemplate = "trustBadgeDemotionPlatinum";
				} elseif ($supplier['Rank'] == "Gold") {
					$emailTemplate = "trustBadgeDemotionGold";
				} else {
					$emailTemplate = "trustBadgeDemotionSilver";
				}
			} elseif ($emailType == "removed") {
				$emailTemplate = "trustBadgeDemotionTotal";
			}			

			// Load the supplier details
			if ($supplier['SupplierID'] > 0) {
				$data = loadSupplierData($supplier['SupplierID']);

				$data['reviewLink'] = "https://www.solarquotes.com.au/installer-review/{$supplier['SupplierURL']}/{$supplier['SupplierURL']}-review.html";
				$data['sFName'] = $data['sMainContactfName'];
				$data['sLName'] = $data['sMainContactlName'];
				$data['email'] = $data['sMainContactEmail'];

				sendTemplateEmail($data['email'], "{$data['sFName']} {$data['sLName']}", $emailTemplate, $data, $salesEmail, $salesName);
			} else {
				$SQL = "SELECT MainContactfName, MainContactlName, MainContactEmail FROM suppliers WHERE parent = '{$supplier['ParentID']}' AND trustBadgeEnabled = 'Y' AND trustBadgeUseParent = 'Y' AND MainContactEmail != '' GROUP BY MainContactEmail";

				$children = db_query($SQL);

				while ($child = mysqli_fetch_array($children, MYSQLI_ASSOC)) {
					extract(htmlentitiesRecursive($child), EXTR_PREFIX_ALL, 's');

					$data['sFName'] = $s_MainContactfName;
					$data['sLName'] = $s_MainContactlName;
					$data['email'] = $s_MainContactEmail;
					$data['sCompany'] = $supplier['Supplier'];
					$data['reviewLink'] = "https://www.solarquotes.com.au/installer-review/{$supplier['SupplierURL']}/{$supplier['SupplierURL']}-review.html";

					sendTemplateEmail($data['email'], "{$data['sFName']} {$data['sLName']}", $emailTemplate, $data, $salesEmail, $salesName);
				}
			}

			echo $data['email'] . '<br />';

			// Send
			sendTemplateEmail($techEmail, "{$data['sFName']} {$data['sLName']}", $emailTemplate, $data, $salesEmail, $salesName);
    	} catch (Exception $e) {
    		// Do nothing, just skip over
		}
    }

    function calculateAllFeedbacks($feedbacks) {
		$total = 0;

		$quality = 0;
		$countQuality = 0;
		$value = 0;
		$countValue = 0;
		$service = 0;
		$countService = 0;
		$install = 0;
		$countInstall = 0;

		while ($feedback = mysqli_fetch_array($feedbacks, MYSQLI_ASSOC)) {
    		extract(htmlentitiesRecursive($feedback), EXTR_PREFIX_ALL, 'f');

			$rate_value = $f_rate_value;
			$rate_system_quality = $f_rate_system_quality;
			$rate_installation = $f_rate_installation;
			$rate_customer_service = $f_rate_customer_service;

			if($f_one_year_rate_value != '' && $f_one_year_submitted != ''){
				$rate_value = $f_one_year_rate_value;
				$rate_system_quality = $f_one_year_rate_system_quality;
				$rate_installation = $f_one_year_rate_installation;
				$rate_customer_service = $f_one_year_rate_customer_service;
			}

			$temp = CalculateFeedbackIndividualItem($rate_value);
			if ($temp != false) {
				$value += $temp;
				$countValue++;
			}

			$temp = CalculateFeedbackIndividualItem($rate_system_quality);
			if ($temp != false) {
				$quality += $temp;
				$countQuality++;
			}

			$temp = CalculateFeedbackIndividualItem($rate_installation);
			if ($temp != false) {
				$install += $temp;
				$countInstall++;
			}

			$temp = CalculateFeedbackIndividualItem($rate_customer_service);
			if ($temp != false) {
				$service += $temp;
				$countService++;
    		}
		}

		// Calculate the averages
		if ($countValue > 0) $value = $value / $countValue;
		if ($countInstall > 0) $install = $install / $countInstall;
		if ($countQuality > 0) $quality = $quality / $countQuality;
		if ($countService > 0) $service = $service / $countService;

		$total = ($value + $install + $quality + $service) / 4;

		// Round off the figures
		$total = round($total, 2);
		$value = round($value, 2);
		$install = round($install, 2);
		$quality = round($quality, 2);
		$service = round($service, 2);

		// Return
		return array($total, $value, $install, $quality, $service);
    }

    function calculateBadge($feedbackCount, $feedbackTotalWeighted) {
		// Legendary >= 4.85, 200+ reviews
    	// Platinum >= 4.40 stars, 100+ reviews
		// Gold >= 4.3 stars, 50-100 reviews
		// Silver >= 4.2 stars, 15-50 reviews

		$badge = 'None';

		if ($feedbackCount == 0)
			return $badge;

		// Rounded to 2 decimal points
		$averageStarRating = $feedbackTotalWeighted;

		// Platinum
		if ($averageStarRating >= 4.85) {
			if($feedbackCount >= 200)
				$badge = 'Legendary';
			elseif ($feedbackCount >= 100)
				$badge = 'Platinum';
			elseif ($feedbackCount >= 50)
				$badge = 'Gold';
			elseif ($feedbackCount >= 15)
				$badge = 'Silver';
		} else if ($averageStarRating >= 4.4) {
			if ($feedbackCount >= 100)
				$badge = 'Platinum';
			elseif ($feedbackCount >= 50)
				$badge = 'Gold';
			elseif ($feedbackCount >= 15)
				$badge = 'Silver';
		} elseif ($averageStarRating >= 4.3) {
			if ($feedbackCount >= 50)
				$badge = 'Gold';
			elseif ($feedbackCount >= 15)
				$badge = 'Silver';
		} elseif ($averageStarRating >= 4.2 && $feedbackCount >= 15)
			$badge = 'Silver';

		return $badge;
    }

    function calculateFeedbackTotal($f_rate_value, $f_rate_system_quality, $f_rate_installation, $f_rate_customer_service) {
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
			return $feedbackSum / $average;
		else
			return false;
	}

	function CalculateFeedbackIndividualItem($item) {
		if ($item > 0)
			return $item;
		else
			return false;
	}

    function selectAllChildren($parent) {
		$return = array();

		$SQL = "SELECT * FROM suppliers WHERE parent = '{$parent}' AND trustBadgeEnabled = 'Y' AND trustBadgeUseParent = 'Y'";
		$children = db_query($SQL);

		while ($child = mysqli_fetch_array($children, MYSQLI_ASSOC)) {
    		extract(htmlentitiesRecursive($child), EXTR_PREFIX_ALL, 's');

    		$return[] = $s_record_num;
		}

		return $return;
    }

    function selectAllFeedback($suppliers) {
		$suppliers = implode(",", $suppliers);
		$SQL = "SELECT * FROM feedback WHERE public = 1 AND supplier_id IN ({$suppliers}) AND ( feedback_date > (NOW() - INTERVAL 3 YEAR) OR one_year_submitted > (NOW() - INTERVAL 3 YEAR) )";
		$feedbacks = db_query($SQL);
		return $feedbacks;
    }

    function selectFirstLeadDate($suppliers) {
		$suppliers = implode(",", $suppliers);

		$SQL = "SELECT MIN(dispatched) FROM lead_suppliers WHERE type = 'regular' AND supplier IN ({$suppliers})";
		return db_getVal($SQL);
	}
	
	function generateBadgesCsv($changedBadges) {
        global $leadcsvdir;		

        $fileName = $leadcsvdir . '/trust_badges' . time() . '.csv';

        // Open the file
        $file = fopen($fileName, 'w');
        
        // Write header line
		fwrite ($file, "Company Name, Previous Badge Type, Current Badge Type, # of reviews, Current Score, Public Address, First Time Change\n");
		
		$badges = [ 'Removed' => 0, 'None' => 0, 
					'Silver' => 1, 'Gold' => 2, 'Platinum' => 3, 'Legendary' => 4 ];

		foreach($changedBadges as $type => $suppliers) {			
			foreach($suppliers as $supplier) {
				$rank = $supplier['Rank'];
				$previousRank = $supplier['PreviousRank'] ?? '';
				if($type == 'removed') {
					$previousRank = $supplier['Rank'];
					$rank = 'Removed';					
				} else if($type == 'new') {
					$previousRank = 'None';
				}
							
				if($badges[$rank] <= $badges[$previousRank]) {
					$supplier['firstTimeChange'] = 'No';
				}
				

				fwrite ($file, '"'.$supplier['Supplier'].'"');
				fwrite ($file, ',"'.$previousRank.'"');
				fwrite ($file, ',"'.$rank.'"');				
				fwrite ($file, ',"'.$supplier['count'].'"');
				fwrite ($file, ',"'.$supplier['score'].'"');
				fwrite ($file, ',"'.$supplier['publicAddress'].'"');
				fwrite ($file, ',"'.$supplier['firstTimeChange'].'"');					
				fwrite ($file, "\n");
			}
		}		
        
        // Close the file
        fclose($file);
        return $fileName;
    }
?>
