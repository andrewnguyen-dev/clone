<?php
	function AddLeadToBrevo($l_record_num, $campaign, $source) {
		global $brevo_api;

		$campaignBrevo = GetBrevoCampaign($campaign);

		// Select the lead details
        $result = db_query("SELECT * FROM leads WHERE record_num='{$l_record_num}'");

        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
            extract($row, EXTR_PREFIX_ALL, 'l');

		try{
			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => 'https://api.brevo.com/v3/contacts',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS =>'
					{
						"attributes": {
							"SUBSCRIPTION_SOURCE": "' . $source . '",
							"FIRSTNAME": "' . $l_fName . '"
						},
						"updateEnabled": true,
						"email": "' . $l_email . '",
						"listIds": [
							' . $campaignBrevo['id'] . '
						]
					}
				',
				CURLOPT_HTTPHEADER => array(
					'accept: application/json',
					'content-type: application/json',
					'api-key: '.$brevo_api
				),
			));

			$response = curl_exec($curl);

			curl_close($curl);
		} catch(\Exception $ex){
			error_log($ex->getMessage(), 0);
			SendMail("it@solarquotes.com.au", "John Burcher", 'Brevo Error - AddLeadToBrevo', nl2br($ex->getMessage()));
		}
	}

	function GetBrevoCampaign($campaign) {
		global $brevo_api;

		try {
			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => 'https://api.brevo.com/v3/contacts/lists?limit=50&offset=0&sort=desc',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'GET',
				CURLOPT_HTTPHEADER => array(
					'accept: application/json',
					'api-key: '.$brevo_api
				),
			));

			$response = curl_exec($curl);

			curl_close($curl);

			$responseArray = json_decode($response, true);

			$specificCampaignDetails = null;
			
			// Iterate through the result array
			foreach ($responseArray['lists'] as $singleCampaign) {
				if ($singleCampaign['name'] === $campaign) {
					$specificCampaignDetails = $singleCampaign;
					break; // Stop iterating once the campaign is found
				}
			}

			if($specificCampaignDetails) {
				return $specificCampaignDetails;
			} else {
				throw new Exception("Brevo error - Campaign not found: {$campaign}");	
			}
		} catch(\Exception $ex){
			error_log($ex->getMessage(), 0);
			SendMail("it@solarquotes.com.au", "John Burcher", 'Brevo Error - GetBrevoCampaign', nl2br($ex->getMessage()));
		}
	}
?>