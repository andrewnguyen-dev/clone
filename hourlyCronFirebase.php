<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

    include('global.php');

	$sentNotifications = array();
    // Get all current tokens
    $SQL = "SELECT * FROM global_data WHERE name = 'firebasetoken'";
    $tokens = db_query($SQL);
    $tokenArray = array();
    $deleteFromGlobalData = array();
    foreach ($tokens AS $token) {
		$tokenDecode = json_decode($token['description'], true);
		
		$tokenArray[$token['record_num']] = array('supplier_id' => $token['id'], 'token' => $tokenDecode['token'], 'deviceID' => $tokenDecode['deviceID']);
		
		// Override token if testing so we don't send notifications to real users
		if(!empty($firbaseOverrideUserToken ?? '')) {
			$tokenArray[$token['record_num']]['token'] = $firbaseOverrideUserToken;
		}
    }
    
    // No current tokens, stop here
    if (empty($tokenArray) || count($tokenArray) == 0)
    	return;
    
    // Get all leads in the last 10 minutes
    $past = new \DateTime(db_getVal("SELECT DATE_SUB({$nowSql}, INTERVAL 10 MINUTE) as past"));
    

	$SQL = "SELECT LS.lead_id, LS.supplier, L.fName, L.iCity, LS.priceType, L.record_num, L.systemDetails FROM lead_suppliers LS ";
    $SQL .= "INNER JOIN leads L ON LS.lead_id = L.record_num ";
    $SQL .= "WHERE LS.dispatched >= '" . $past->format('Y-m-d H:i:s') . "'";
    $leads = db_query($SQL);
    
    // Now get the claims
    $SQL = "SELECT * FROM global_data WHERE name = 'claimleads' AND created >= '" . $past->format('Y-m-d H:i:s') . "'";
	$claims = db_query($SQL);
	
	// Now get the reviews
	$SQL = "SELECT * FROM global_data WHERE name IN ('reviewpublished', 'reviewpublishedreply', 'oneyearreviewpublished') AND created >= '" . $past->format('Y-m-d H:i:s') . "'";
	$reviews = db_query($SQL);
	
	// Get all the device Ids and their notification settings
	$SQL = "SELECT device_id, normal, claim, reviews FROM device_notifications GROUP BY device_id;";
	$device_notifications = [];
	foreach(db_query($SQL) as $dn){
		$device_notifications[$dn['device_id']] = [
			'normal' => $dn['normal'],
			'claim' => $dn['claim'],
			'reviews' => $dn['reviews']
		];
	}
	
    foreach ($leads AS $lead) {
    	// Does this lead match any existing tokens
    	foreach ($tokenArray AS $token_record_num => $token) {
    		if ($token['supplier_id'] == $lead['supplier']) {
				// A match is found, push out notification here
				$registrationIds = array($token['token']);
				$deviceId = $token['deviceID'];
				
				// Check notifications
				if(isset($device_notifications[$deviceId]) && $device_notifications[$deviceId]['normal'] !== 'Y'){ // Skip if the notification of normal leads is anything but Y
					continue;
				}
				
					
				// Prep the message
				$textParts = [
					$lead['fName'] . " from " . $lead['iCity'] . " wants"
				];
				$systemDetails = unserialize(base64_decode($lead['systemDetails']));
				if(stripos($systemDetails['System Size:'], 'kW') !== false){
					$textParts[] = $systemDetails['System Size:'];
				}
		
				$leadType = trim(str_replace(['Manual', 'Directly Referred'], '', $lead['priceType']));
				switch($leadType){
					case 'Hybrid Systems':
					case 'Off Grid':
						$textParts[] = ( count($textParts) > 1 ? 'of ' : '' ) . 'Solar + Battery';
					break;
					case 'Battery Ready':
					case 'Add Batteries to Existing':
						$textParts[] = 'Battery Ready';
					break;
					case 'EV Chargers':
						$textParts[] = 'EV charger';
					break;
					case 'EV charger + solar only':
						$textParts[] = 'EV charger with solar';
					break;
					case 'EV charger + solar and / or battery':
						$textParts[] = 'EV charger with solar and/or battery';
					break;
					case 'Hot water heat pump':
						$textParts[] = 'hot water heat pump';
					break;
					case 'Hot water heat pump + solar only':
						$textParts[] = 'hot water heat pump with solar';
					break;
					case 'Hot water heat pump + solar and / or battery':
						$textParts[] = 'hot water heat pump with solar and/or battery';
					break;
					case 'Commercial':
						$textParts[] = ( count($textParts) > 1 ? 'of ' : '' ) . 'commercial solar';
					break;
					case 'Repair and Maintenance':
						$textParts[] = 'solar repair/maintenance';
					break;
					default:
						$textParts[] = ( count($textParts) > 1 ? 'of ' : '' ) . 'solar';
					break;
				}
				
				$textAndDevice = array($token['deviceID'], $textParts); //make sure device won't get duplicate msgs
				if(!in_array($textAndDevice, $sentNotifications)) {
					$sentNotifications[] = $textAndDevice;

					try{
						FirebaseNotification::sendFcmNotification(
							userTokens: $registrationIds, 
							body: implode(' ', $textParts), 
							title: 'New Lead', 
							data: [
								'action' => 'go_to_lead',
								'leadID' => $lead['lead_id']
							],
							sound: 'regular_lead.caf',
							androidChannel: 'regular_lead_channel',
							supplierId: $token_record_num,
						);
					} catch (InvalidFirebaseTokenException $e) {
						if(!in_array($token_record_num, $deleteFromGlobalData))
							$deleteFromGlobalData[] = $token_record_num;
					}
				}
    		}
    	} 
    }
    
    // Now work on claims
	$leadsToSkip = array();
	
	foreach ($claims AS $claim) {
		// Does this claim match any existing tokens
    	foreach ($tokenArray AS $token_record_num => $token) {
    		if ($token['supplier_id'] == $claim['id']) {
				$deviceId = $token['deviceID'];
				// Check notifications
				if(isset($device_notifications[$deviceId]) && $device_notifications[$deviceId]['claim'] !== 'Y'){ // Skip if the notification of claim leads is anything but Y
					continue;
				}
					
				// A match is found, push out notification here
				$registrationIds = array($token['token']);
				$claimInfo = json_decode($claim['description']);
				if(in_array($claimInfo->lead, $leadsToSkip))
					continue 2;
				// Check if there's any slots available
				$SQL = "SELECT ( ";
				$SQL .= "SELECT COUNT(*) FROM lead_claims WHERE lead_id = '".$claimInfo->lead."' ";
				$SQL .= ") + ( ";
				$SQL .= "SELECT COUNT(*) FROM lead_suppliers WHERE lead_id = '".$claimInfo->lead."'";
				$SQL .= ");";
				$filledSlots = db_getVal($SQL);
				$SQL = "SELECT requestedQuotes from leads where record_num = '".$claimInfo->lead."' AND status = 'waiting' LIMIT 1;";
				$requestedQuotes = db_getVal($SQL);
				if($filledSlots >= $requestedQuotes) {	//slots have already been filled
					$leadsToSkip[] = $claimInfo->lead;	//go to the next claim
					continue 2;
				}

				$title = 'Claimable Lead Near You. Claim it now...';
				$textParts = [1 => 'Solar Lead in', 2 => $claimInfo->iCity];
				
				if(stripos($claimInfo->systemSize, 'kW') !== false){
					array_unshift($textParts, $claimInfo->systemSize);
				}
		
				switch($claimInfo->leadType){
					case 'Hybrid Systems':
					case 'Off Grid':
						$textParts[1] = 'Solar + Battery lead in';
					break;
					case 'Battery Ready':
					case 'Add Batteries to Existing':
						$textParts[1] = 'Battery Ready lead in';
					break;
					case 'Commercial':
						$textParts[1] = 'Commercial solar lead in';
					break;
					case 'EV Chargers':
						$textParts[1] = 'EV charger lead in';
					break;
					case 'EV charger + solar only':
						$textParts[1] = 'EV charger + solar lead in';
					break;
					case 'EV charger + solar and / or battery':
						$textParts[1] = 'EV charger + solar and/or battery lead in';
					break;
					case 'HWHP':
						$textParts[1] = 'Hot water heat pump lead in';
					break;
					case 'HWHP + solar only':
						$textParts[1] = 'Hot water heat pump + solar lead in';
					break;
					case 'HWHP + solar and / or battery':
						$textParts[1] = 'Hot water heat pump + solar and/or battery lead in';
					break;
				}
				
				$textAndDevice = array($token['deviceID'], $textParts); //make sure device won't get duplicate msgs
				if(!in_array($textAndDevice, $sentNotifications)) {
					$sentNotifications[] = $textAndDevice;				

					try{
						FirebaseNotification::sendFcmNotification(
							userTokens: $registrationIds, 
							body: implode(' ', $textParts), 
							title: $title, 
							data: [
								'action' => 'go_to_claims',
								'leadID' => $claimInfo->lead
							],
							sound: 'claim_lead.caf',
							androidChannel: 'claim_lead_channel',
							supplierId: $token_record_num,
						);
					} catch (InvalidFirebaseTokenException $e) {
						if(!in_array($token_record_num, $deleteFromGlobalData))
							$deleteFromGlobalData[] = $token_record_num;
					}		
				}
			}
		}
	}

	foreach ($reviews AS $review) {
		// Does this review match any existing tokens
    	foreach ($tokenArray AS $token_record_num => $token) {
    		if ($token['supplier_id'] == $review['id']) {
				$deviceId = $token['deviceID'];
				
				// Check notifications
				if(isset($device_notifications[$deviceId]) && $device_notifications[$deviceId]['reviews'] !== 'Y'){ // Skip if the notification of reviews is anything but Y
					continue;
				}
				
					
    			// A match is found, push out notification here
				$registrationIds = array($token['token']);
				$reviewInfo = json_decode($review['description']);

				$title = 'New Review';
				if (!empty($reviewInfo->city))
					$textParts = [1 => $reviewInfo->firstName, 2 => 'from', 3 => $reviewInfo->city, 4 => 'left you a', 5 => $reviewInfo->rating, 6 => 'star review'];
				else
					$textParts = [1 => $reviewInfo->firstName, 2 => 'from postcode', 3 => $reviewInfo->postcode, 4 => 'left you a', 5 => $reviewInfo->rating, 6 => 'star review'];
				
				$textAndDevice = array($token['deviceID'], $textParts); //make sure device won't get duplicate msgs
				if(!in_array($textAndDevice, $sentNotifications)) {
					$sentNotifications[] = $textAndDevice;				
					
					try{
						FirebaseNotification::sendFcmNotification(
							userTokens: $registrationIds, 
							body: implode(' ', $textParts), 
							title: $title, 
							data: [
								'action' => 'go_to_review',
								'reviewID' => "$reviewInfo->review_id"
							],
							sound: 'review.caf',
							androidChannel: 'review_channel',
							supplierId: $token_record_num,
						);
					} catch (InvalidFirebaseTokenException $e) {
						if(!in_array($token_record_num, $deleteFromGlobalData))
							$deleteFromGlobalData[] = $token_record_num;
					}	
				}
			}
		}
	}
	
	if(count($deleteFromGlobalData) > 0) {
		$SQL = "DELETE FROM global_data WHERE name = 'firebasetoken' AND record_num IN (".implode(',', $deleteFromGlobalData).") LIMIT ".count($deleteFromGlobalData).";";
		db_query($SQL);
	}

?>