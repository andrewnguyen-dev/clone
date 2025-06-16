<?php
	function AddLeadToGR($l_record_num, $campaign) {
		global $implixApiKey, $siteURLSSL;

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
			$gr = new GetResponse($implixApiKey);

			$campaign = $gr->getCampaigns(array(
				'query' => array(
					'name' => $campaign
				),
				'fields' => 'campaignId'
			));
			$campaign = @end($campaign);

			// getresponse does not handle ipv6, so just manually add in an ipv4
			if (strlen($l_ipAddress) > 20)
				$l_ipAddress = '192.168.0.1';

			$customFieldValues = [];

			$customFieldsMap = [
				'ref' => $l_record_num,
				'emailcode' => $l_emailCode,
				'trustpilotreviewurl' => $trustPilotURL,
				'reviewio' => $l_reviewioLink
			];
			$customFieldValues = grGetCustomFields($gr, $customFieldsMap, $customFieldValues);

			if(($campaignName == 'sq_all_quotes') || ($campaignName == 'sq_all_ev_quotes')) {
				// Grab number of suppliers that received lead
				$leadCount = db_getVal("SELECT COUNT(*) FROM lead_suppliers WHERE lead_id = {$l_record_num}");

				$unfilledSlots =  abs($leadCount - $l_requestedQuotes);
				$systemDetails = unserialize(base64_decode($l_systemDetails));
				$features = is_array($systemDetails) && array_key_exists("Features:", $systemDetails) ? $systemDetails["Features:"] : false;
				$quoteType = grTextQuoteType($l_leadType, $features);

				$customFieldsMap = [
					'requested_quote_count' => $l_requestedQuotes,
					'text_quote_or_quotes' => ($leadCount == 1 ? 'quote' : 'quotes'),
					'text_quote_type' => $quoteType,
					'actual_quote_count' => $leadCount,
					'text_solarcompany_or_companies' => ($leadCount == 1 ? 'solar company' : 'solar companies'),
					'text_this_these' => ($leadCount == 1 ? 'this' : 'these'),
					'text_company_companies' => ($leadCount == 1 ? 'company' : 'companies'),
					'installer_links' => "<a href='{$siteURLSSL}quote/unsigned.php?l={$l_record_num}&emailcode={$l_emailCode}'>{$siteURLSSL}quote/unsigned.php?l={$l_record_num}&emailcode={$l_emailCode}</a>",
					'text_one_two' => ( $unfilledSlots == 3 ? 'Three' : ($unfilledSlots == 2 ? 'Two' : 'One')),
					// Return the number of "unsigned supplier" possibilities by
					// checking the number lead_supplier entries to the requestedCount
					'text_installer_installers' => ($unfilledSlots == 1 ? 'installer' : 'installers'),
					'text_is_are' => ($unfilledSlots == 1 ? 'is' : 'are'),
					'text_an_additional' => ( $unfilledSlots == 1 ? 'an additional' : 'additional'),
					'text_got_quotes' => ($leadCount == 1 ? 'got your quote' : "got all {$leadCount} quotes")
				];

				if($campaignName == 'sq_all_ev_quotes') {
					$customFieldsMap['text_ev_solar_batteries'] = grTextEVSolarBatteries($features);
				}

				$customFieldValues = grGetCustomFields($gr, $customFieldsMap, $customFieldValues);
			}
			$result = $gr->addContact([
				'email'             => $l_email,
				'name'				=> "{$l_fName} {$l_lName}",
				'dayOfCycle'        => 0,
				'campaign'          => ['campaignId' => $campaign->campaignId],
				'ip'				=> $l_ipAddress,
				'customFieldValues' => $customFieldValues
			]);
			return $result;

		}catch(\Exception $ex){
			if(!in_array(json_decode($ex->getMessage())->message, ['Cannot add contact that is blocked', 'Contact already added'])) {
				error_log($ex->getMessage(), 0);
				SendMail("johnb@solarquotes.com.au", "John Burcher", 'GetResponse Error - Add lead to campaign', nl2br($ex->getMessage()));
			}
		}
    }

	function grTextEVSolarBatteries($features) {
		$and = [];
		if(stripos($features, 'solar') !== false)
			$and[] = 'solar';
		if(stripos($features, 'battery') !== false)
			$and[] = 'batteries';
		if(count($and)>0)
			return implode(", ", $and) . ' and an EV charger';
		return 'an EV charger';
	}

	function grTextQuoteType($leadType, $features) {
		$quoteType = 'solar';
		if($leadType==='Commercial')
			$quoteType = 'commercial solar';
		elseif(strpos($features, 'Adding Batteries') !== false)
			$quoteType = 'battery storage';
		elseif(strpos($features, 'Hybrid System') !== false || strpos($features, 'Off Grid') !== false)
			$quoteType = 'solar and batteries';
		return $quoteType;
	}

	function grGetCustomFields($gr, $customFieldsMap, $customFieldValues) {
		$keys = array_keys($customFieldsMap);
		$keys = implode(",", $keys);

		$customFields = $gr->getCustomFields([
			'query' => ['name' => $keys],
			'fields' => 'name,customFieldId'
		]);
		foreach($customFields as $field){
			if(!isset($customFieldsMap[$field->name]))
				continue;
			$customFieldValues[] = [
				'customFieldId' => $field->customFieldId,
				'value'			=> [$customFieldsMap[$field->name]]
			];
		}
		return $customFieldValues;
	}

    function AddSupplierToGR($S_record_num, $campaign) {
        global $implixApiKey;

        // Select the lead details
        $result = db_query("SELECT * FROM suppliers WHERE record_num='{$S_record_num}'");

        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
            extract($row, EXTR_PREFIX_ALL, 'S');

        try {
            # API 2.x URL
            $api_url = 'http://api2.getresponse.com';
            # initialize JSON-RPC client
            $client = new jsonRPCClient($api_url);

            $result = NULL;

            $result = $client->get_campaigns(
                $implixApiKey,
                array (
                    # find by name literally
                    'name' => array ( 'EQUALS' => $campaign )
                )
            );

            $CAMPAIGN_ID = array_pop(array_keys($result));

            $result = $client->add_contact(
                $implixApiKey,
                array (
                    'campaign'  => $CAMPAIGN_ID,
                    'name'      => "{$S_fName} {$S_lName}",
                    'email'     => $S_email,
                    'cycle_day' => "0",
                    'customs' => array(
                        array(
                            'name'       => 'ref',
                            'content'    => $S_record_num
                        ),
                        array(
                            'name'       => 'company',
                            'content'    => $S_company
                        ),
                        array(
                            'name'       => 'login',
                            'content'    => $S_username
                        ),
                        array(
                            'name'       => 'password',
                            'content'    => 'SQ' . $S_record_num
                        )
                    )
                )
            );

            db_query("UPDATE suppliers SET controlPanelCampaignAssigned = 'Y' WHERE record_num = '{$S_record_num}'");
        }
        catch (Exception $e) {
            //die ($e->getMessage());
            //db_query("UPDATE leads SET campaign='{$campaign} {$Err}' WHERE record_num='{$l_record_num}'");
        }
    }

    function addVariableToGRContact($GRContactID, $variable, $value) {
    	global $implixApiKey;

		try {
            # API 2.x URL
            $api_url = 'http://api2.getresponse.com';

            # initialize JSON-RPC client
            $client = new jsonRPCClient($api_url);

            $result = NULL;

            $result = $client->set_contact_customs(
                $implixApiKey,
                array (
                	'contact' => $GRContactID,
                	'customs' => array(
                		array(
                			'name' => $variable,
                			'content' => $value
                		)
                	)
            	)
            );

            print_r($result);
            echo "<br /><br />";

            return $result;
		}
        catch (Exception $e) {
        	print_r($e);
        	echo "<br />";
            //return ($e->getMessage());
        }
	}

    function moveGRContact($GRContactID, $campaign) {
		global $implixApiKey;

		try {
            # API 2.x URL
            $api_url = 'http://api2.getresponse.com';
            # initialize JSON-RPC client
            $client = new jsonRPCClient($api_url);

            $result = NULL;

            $result = $client->get_campaigns(
                $implixApiKey,
                array (
                    # find by name literally
                    'name' => array ( 'EQUALS' => $campaign )
                )
            );

            $array_pop = array_keys($result);
            $CAMPAIGN_ID = array_pop($array_pop);

            $result = $client->move_contact(
                $implixApiKey,
                array (
                	'contact' => $GRContactID,
                	'campaign' => $CAMPAIGN_ID
                )
            );

            return $result;
		}
		catch (Exception $e) {
            return ($e->getMessage());
        }
    }

	function searchGRContact($leadID, $searchAllCampaigns = false) {
		global $implixApiKey;

		try {
			# API 2.x URL
			$api_url = 'http://api2.getresponse.com';
			# initialize JSON-RPC client
			$client = new jsonRPCClient($api_url);

			$result = NULL;

			// Search through all campaigns that relate to SQ only
			// The IDs relate to campaigns 
			// 		"0q_sq_no_newsltr", "0q_sqnewsltr", "0q_sq_newsltr2", "1q_sqnewsltr", "1q_sq_newsltr2", "1q_sq_no_newsltr", "2q_sqnewsltr", "2q_sq_newsltr2", "2q_sq_no_newsltr", "3q_sqnewsltr", "3q_sq_newsltr2", "3q_sq_no_newsltr"
			//		"0q_sq_commercial_no_newsltr", "1q_sq_commercial_no_newsltr", "2q_sq_commercial_no_newsltr", "3q_sq_commercial_no_newsltr"

			if ($searchAllCampaigns == false)
				$validCampaignsIDs = array("fK0", "fKl", "V0Ij", "fK3", "V0Iq", "fKD", "fKt", "V0Ir", "fKe", "fK2", "V2ml", "fKN", "TyDV", "TyDp", "TyDn", "TyD6", "4O4pL", "4Xzc3");
			else
				$validCampaignsIDs = array("fK0", "fKl", "V0Ij", "fK3", "V0Iq", "fKD", "fKt", "V0Ir", "fKe", "fK2", "V2ml", "fKN", "TyDV", "TyDp", "TyDn", "TyD6", "VSZe", "fKg", "fK9", "4O4pL", "4Xzc3");

			$result = $client->get_contacts(
				$implixApiKey,
				array (
					'campaigns' => $validCampaignsIDs,
					'customs' => array(
						array(
							'name'       => 'ref',
							'content'    => array ('EQUALS' => $leadID)
						)
					)
				)
			);

			return $result;
		}
		catch (Exception $e) {
			return ($e->getMessage());
		}    
	}

    function searchGRCampaign($campaign) {
		global $implixApiKey;

		try {
            # API 2.x URL
            $api_url = 'http://api2.getresponse.com';
            # initialize JSON-RPC client
            $client = new jsonRPCClient($api_url);

            $result = NULL;

            $result = $client->get_campaigns(
                $implixApiKey,
                array (
                    # find by name literally
                    'name' => array ( 'EQUALS' => $campaign )
                )
            );

            return $result;
		}
	    catch (Exception $e) {
            return ($e->getMessage());
        }
    }

	function getGRContactList($since, $fields = 'all', $sort = 'ASC', $page = 1, $perpage = 1000, $campaignid = 'all') {
		// https://apireference.getresponse.com/#operation/getContactList
		// $since : YYYY-MM-DD OR YYYY-MM-DDTHH:MM:SS+0000  eg. 2022-09-28T13:30:42+1000
		// $campaignid : Campaign ID is different to its name, eg. sq_all_quotes = 4O4pL
		// $fields : Comma separated list of fields that should be returned. Id is always returned.
			// contactId, name, origin, timeZone, activities, changedOn, createdOn, campaign, email, dayOfCycle, scoring, engagementScore, href, note, ipAddress
		global $implixApiKey;

		// The + must be URL encoded
		$since = str_replace('+','%2B',$since);

		# API 3.x URL
		$initUrl = 'https://api.getresponse.com/v3/contacts'.
			'?query[createdOn][from]='.$since.
			'&sort[createdOn]='.$sort.
			'&perPage='.$perpage;
		if($fields != 'all' && $fields !== null) $initUrl .= '&fields='.$fields;
		if($page > 1) $initUrl .= '&page='.$page;
		if($campaignid != 'all' && $campaignid !== null) $initUrl .= '&query[campaignId]='.$campaignid;

		$curlHandler = curl_init($initUrl);
		curl_setopt($curlHandler, CURLOPT_POST, 0);
		curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlHandler, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"X-Auth-Token: api-key " . $implixApiKey
		));
		curl_setopt($curlHandler, CURLOPT_HEADER, 1);

		$response = curl_exec($curlHandler);
		$header_size = curl_getinfo($curlHandler, CURLINFO_HEADER_SIZE);
		curl_close ($curlHandler);

		$body = json_decode(substr($response, $header_size),true);
		
		// split header rows and convert to array
		$headers = explode("\r\n",trim(substr($response, 0, $header_size)));
		$headers[0] = 'status: '.$headers[0];
		foreach ($headers as $value) {
			if(false !== ($matches = explode(':', $value, 2))) {
				$headers_arr["{$matches[0]}"] = trim($matches[1]);
			}                
		}

		$result = ['headers' => $headers_arr, 'body' => $body];
		return $result;
	}

	function getGRAutoresponderStats($since, $fields, $campaignid) {
		// https://apireference.getresponse.com/#operation/getAutoresponderStatisticsCollection
		// $since : YYYY-MM-DD OR YYYY-MM-DDTHH:MM:SS+0000  eg. 2022-09-28T13:30:42+1000
		// $campaignid : REQUIRED, Campaign ID is different to its name, eg. sq_all_quotes = 4O4pL
		// $fields : Comma separated list of fields that should be returned. Id is always returned.
			// timeInterval, sent, totalOpened, uniqueOpened, totalClicked, uniqueClicked, goals, uniqueGoals, forwarded, unsubscribed, bounced, complaints
		global $implixApiKey;

		// The + must be URL encoded
		$since = str_replace('+','%2B',$since);

		# API 3.x URL
		$initUrl = 'https://api.getresponse.com/v3/autoresponders/statistics'.
			'?query[createdOn][from]='.$since.
			'&query[campaignId]='.$campaignid;
		if($fields != 'all' && $fields !== null) $initUrl .= '&fields='.$fields;

		$curlHandler = curl_init($initUrl);
		curl_setopt($curlHandler, CURLOPT_POST, 0);
		curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlHandler, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"X-Auth-Token: api-key " . $implixApiKey
		));
		curl_setopt($curlHandler, CURLOPT_HEADER, 1);

		$response = curl_exec($curlHandler);
		$header_size = curl_getinfo($curlHandler, CURLINFO_HEADER_SIZE);
		curl_close ($curlHandler);

		$body = json_decode(substr($response, $header_size),true);

		// split header rows and convert to array
		$headers = explode("\r\n",trim(substr($response, 0, $header_size)));
		$headers[0] = 'status: '.$headers[0];
		foreach ($headers as $value) {
			if(false !== ($matches = explode(':', $value, 2))) {
				$headers_arr["{$matches[0]}"] = trim($matches[1]);
			}                
		}

		$result = ['headers' => $headers_arr, 'body' => $body];
		return $result;
	}
?>