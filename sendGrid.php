<?php
	function AddLeadToSG($l_record_num, $campaign) {
		global $sg_autoresponder_api_key, $siteURLSSL;

		$campaignName = $campaign;

        // Select the lead details
        $result = db_query("SELECT * FROM leads WHERE record_num='{$l_record_num}'");

        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
            extract($row, EXTR_PREFIX_ALL, 'l');

        // Calculate all other variables
        $trustPilotURL = trustPilotGenerateReviewURL($l_record_num);

        // Update leads campaign
        db_query("UPDATE leads SET campaign='{$campaign}' WHERE record_num='{$l_record_num}'");

		try {
			$campaignSG = GetSGCampaign($campaign);
			$campaignSGID = $campaignSG['id'];

			$customFieldsMap = [
				'w6_T' => $l_record_num,						// ref
				'w19_T' => $l_emailCode,						// emailcode
				'w29_T' => $trustPilotURL,						// trustpilotreviewurl
				'w7_T' => $l_reviewioLink						// reviewio
			];

			// Add originLead value
			$customFieldsMap['w37_T'] = ($l_originLead === 'Y') ? 'true' : 'false';

			if(($campaignName == 'sq_all_quotes') || ($campaignName == 'sq_all_ev_quotes') || ($campaignName == 'sq_all_hp_quotes')) {
				// Grab number of suppliers that received lead
				$leadCount = db_getVal("SELECT COUNT(*) FROM lead_suppliers WHERE lead_id = {$l_record_num}");

				$unfilledSlots =  abs($leadCount - $l_requestedQuotes);
				$systemDetails = unserialize(base64_decode($l_systemDetails));
				$features = is_array($systemDetails) && array_key_exists("Features:", $systemDetails) ? $systemDetails["Features:"] : false;
				$quoteType = sgTextQuoteType($l_leadType, $features);

				$customFieldsMap2 = [
					'w20_T' => $l_requestedQuotes,												// requested_quote_count
					'w2_T' => ($leadCount == 1 ? 'quote' : 'quotes'),							// text_quote_or_quotes
					'w5_T' => $quoteType,														// text_quote_type
					'w3_T' => $leadCount,														// actual_quote_count
					'w25_T' => ($leadCount == 1 ? 'solar company' : 'solar companies'),			// text_solarcompany_or_companies
					'w26_T' => ($leadCount == 1 ? 'this' : 'these'),							// text_this_these
					'w34_T' => ($leadCount == 1 ? 'company' : 'companies'),						// text_company_companies
					'w32_T' => "<a href='{$siteURLSSL}quote/unsigned.php?l={$l_record_num}&emailcode={$l_emailCode}'>{$siteURLSSL}quote/unsigned.php?l={$l_record_num}&emailcode={$l_emailCode}</a>",		// installer_links
					'w30_T' => ( $unfilledSlots == 3 ? 'Three' : ($unfilledSlots == 2 ? 'Two' : 'One')),		// text_one_two
					'w23_T' => ($unfilledSlots == 1 ? 'installer' : 'installers'),								// text_installer_installers
					'w24_T' => ($unfilledSlots == 1 ? 'is' : 'are'),											// text_is_are
					'w31_T' => ( $unfilledSlots == 1 ? 'an additional' : 'additional'),							// text_an_additional
					'w22_T' => ($leadCount == 1 ? 'got your quote' : "got all {$leadCount} quotes")				// text_got_your_quote
				];

				if($campaignName == 'sq_all_ev_quotes') {
					$customFieldsMap['w1_T'] = sgTextEVSolarBatteries($features);				// text_ev_solar_batteries
				}

				if($campaignName == 'sq_all_hp_quotes') {
					$resultCustomHWHP = sgTextHwhpSolarBatteries($features);					// text_hwhp_solar_batteries
					$customFieldsMap['w35_T'] = $resultCustomHWHP['text_hp_quote_type'];
					$customFieldsMap['w36_T'] = $resultCustomHWHP['checklist_type'];
				}

				$customFieldsMap = $customFieldsMap + $customFieldsMap2;
			}
			
			$customFieldsMapJSON = json_encode($customFieldsMap);

			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => 'https://api.sendgrid.com/v3/marketing/contacts',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json',
					'Authorization: Bearer '.$sg_autoresponder_api_key
				),
				CURLOPT_POSTFIELDS =>'{
					"list_ids": ["' . $campaignSGID . '"], 
					"contacts": [{
						"email": "' . $l_email . '",
						"first_name": "' . $l_fName . '",
						"last_name": "' . $l_lName . '",
						"custom_fields": ' . $customFieldsMapJSON . '
					}]
				}',
			));

			$response = curl_exec($curl);

			curl_close($curl);

			print_r($response);
		} catch(\Exception $ex){
			error_log($ex->getMessage(), 0);
			SendMail("it@solarquotes.com.au", "John Burcher", 'Sendgrid Error - Add lead to campaign', nl2br($ex->getMessage()));
		}
	}

	function GetSGCampaign($campaign) {
		global $sg_autoresponder_api_key;

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://api.sendgrid.com/v3/marketing/lists',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer '.$sg_autoresponder_api_key
			),
		));

		$response = curl_exec($curl);

		curl_close($curl);

		$responseArray = json_decode($response, true);

		$specificCampaignDetails = null;

		// Iterate through the result array
		foreach ($responseArray['result'] as $singleCampaign) {
			if ($singleCampaign['name'] === $campaign) {
				$specificCampaignDetails = $singleCampaign;
				break; // Stop iterating once the campaign is found
			}
		}

		if ($specificCampaignDetails === null) {
			throw new Exception("Campaign not found: $campaign");
		}

		return $specificCampaignDetails;
	}

	function GetSGContact($l_record_num) {
		global $sg_autoresponder_api_key;

		// Select the lead details
		$result = db_query("SELECT email FROM leads WHERE record_num='{$l_record_num}'");

		while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
			extract($row, EXTR_PREFIX_ALL, 'l');

		if(!isset($l_email))
			throw new Exception("GetSGContact lead not found: $l_record_num");

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://api.sendgrid.com/v3/marketing/contacts/search/emails',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS =>'{"emails": ["' . $l_email . '"]}',
			CURLOPT_HTTPHEADER => array(
			  'Content-Type: application/json',
			  'Authorization: Bearer '.$sg_autoresponder_api_key
			),
		  ));

		$response = curl_exec($curl);

		curl_close($curl);

		$responseArray = json_decode($response, true);

		if (!isset($responseArray['result'][$l_email]['contact'])) {
			throw new Exception("GetSGContact lead not found: $l_record_num - $l_email");
		}

		return $responseArray['result'][$l_email]['contact'];

	}

	function RemoveSGContactFromList($l_record_num, $campaign) {
		global $sg_autoresponder_api_key;

		try {
			$SGContactID = GetSGContact($l_record_num)['id'];
			$SGCampaignID = GetSGCampaign($campaign)['id'];
			
			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => 'https://api.sendgrid.com/v3/marketing/lists/'.$SGCampaignID.'/contacts?contact_ids='.$SGContactID,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'DELETE',
				CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer '.$sg_autoresponder_api_key
				),
			));

			$response = curl_exec($curl);

			curl_close($curl);

			$responseArray = json_decode($response, true);

			return $responseArray;

		} catch(\Exception $ex){
			error_log($ex->getMessage(), 0);
			SendMail("it@solarquotes.com.au", "John Burcher", 'Sendgrid Error - RemoveSGContactFromList', nl2br($ex->getMessage()));
		}
	}

	function GetSGEmailStats($start, $end, $account = null){
		global $sg_keys;

		$headers = [
			'Authorization: Bearer '.$sg_keys['main_stats'], // Always use main account stats key for email stats since it can pull from sub acocunts
			'Content-Type: application/x-www-form-urlencoded'
		];
		if(isset($account) && $account !== '' && $account !== 'main')
			$headers[] = 'on-behalf-of: '.$account;

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://api.sendgrid.com/v3/stats?start_date='.$start.'&end_date='.$end,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => $headers
		));

		$response = curl_exec($curl);

		curl_close($curl);

		$responseArray = json_decode($response, true);
		return $responseArray;
	}

	function GetSGContacsCount($key){
		global $sg_keys;

		if (!isset($sg_keys[$key])){
			throw new Exception("GetSGContacsCount - key doesn't exist: $key");
		}

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => 'https://api.sendgrid.com/v3/marketing/contacts/count',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'GET',
		  CURLOPT_HTTPHEADER => array(
			'Authorization: Bearer '.$sg_keys[$key]
		  ),
		));

		$response = curl_exec($curl);

		curl_close($curl);

		$responseArray = json_decode($response, true);
		return $responseArray;
	}

	function sgTextQuoteType($leadType, $features) {
		$quoteType = 'solar';
		if($leadType==='Commercial')
			$quoteType = 'commercial solar';
		elseif(strpos($features, 'Adding Batteries') !== false)
			$quoteType = 'battery storage';
		elseif(strpos($features, 'Hybrid System') !== false || strpos($features, 'Off Grid') !== false)
			$quoteType = 'solar and batteries';
		return $quoteType;
	}

	function sgTextEVSolarBatteries($features) {
		$and = [];
		if(stripos($features, 'solar') !== false)
			$and[] = 'solar';
		if(stripos($features, 'battery') !== false)
			$and[] = 'batteries';
		if(count($and)>0)
			return implode(", ", $and) . ' and an EV charger';
		return 'an EV charger';
	}

	function sgTextHwhpSolarBatteries($features) {
		$hasSolar = stripos($features, 'solar') !== false;
		$hasBattery = stripos($features, 'battery') !== false;
		
		if ($hasSolar && $hasBattery) {
			$textHpQuoteType = 'solar, batteries and a heat pump';
			$checklistType = 'sbhp';
		} elseif ($hasSolar) {
			$textHpQuoteType = 'solar and a heat pump';
			$checklistType = 'shp';
		} elseif ($hasBattery) {
			$textHpQuoteType = 'batteries and a heat pump';
			$checklistType = 'bhp';
		} else {
			$textHpQuoteType = 'a heat pump';
			$checklistType = 'hp';
		}
		
		return [
			'text_hp_quote_type' => $textHpQuoteType,
			'checklist_type' => $checklistType
		];
	}
?>