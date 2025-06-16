<?php
	// load global libraries
	require_once('global.php');

	function brevoAPICall($url) {
		$curl = curl_init();

		global $brevo_api;
		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => [
				"Content-Type: application/json",
				"api-key: $brevo_api",
			],
		]);

		$response = curl_exec($curl);
		curl_close($curl);
		return $response;
	}

	function getBrevoContactsCount() {
		$time = strtotime('-7days', time());
		// substract current timezone
		$time -= date('Z', $time);
		$todayDate = date('Y-m-d\TH:i:s', $time) . 'Z';

		$url = "https://api.brevo.com/v3/contacts?createdSince=$todayDate";

		$response = [];
		for ($i = 0; $i < 3; $i++) {
			$response = json_decode(brevoAPICall($url));
			if(isset($response->count)) break;
			sleep(30); // Retry after 30 seconds
		}
		return $response->count;
	}

    function getBrevoCampaignStats() {

		$payload = [
			'startDate' => gmdate('Y-m-d\TH:i:s.v\Z', strtotime('-8 day')),
			'endDate' => gmdate('Y-m-d\TH:i:s.v\Z', strtotime('-1 day')),
			'excludeHtmlContent' => true,
			'limit' => 100,
	        ];
	    
	        $url = 'https://api.brevo.com/v3/emailCampaigns';
	        $url .= '?' . http_build_query($payload);
        
		$stats = [];

		for ($i = 0; $i < 3; $i++) {
			$response = brevoAPICall($url);
			$stats = json_decode($response);

			if(isset($stats->campaigns)) break;
			sleep(30); // Retry after 30 seconds
		}

		if(!isset($stats->campaigns)) throw new Exception('Error retrieving Brevo Campaigns Stats Data');

	        $requests = $delivered = $hardBounces = $softBounces = $uniqueOpens = $uniqueClicks = $spamReports = $unsubscribed = 0;
	
	        // For each campaign in Brevo stats
	        foreach($stats->campaigns as $campaign) {
			$campaignstats = $campaign->statistics->campaignStats ?? [];
			// For each contact list in the campaign
			foreach($campaignstats as $stat) {
				$requests += $stat->sent ?? 0;
				$delivered += $stat->delivered ?? 0;
				$hardBounces += $stat->hardBounces ?? 0;
				$softBounces += $stat->softBounces ?? 0;
				$uniqueOpens += $stat->uniqueViews ?? 0;
				$uniqueClicks += $stat->uniqueClicks ?? 0;
				$spamReports += $stat->complaints ?? 0;
				$unsubscribed += $stat->unsubscriptions ?? 0;
			}
	        }

		return [
			'requests' => $requests,
			'openRate' => $delivered == 0 ? 0 : round(($uniqueOpens / $delivered) * 100, 2),
			'CTR' => $delivered == 0 ? 0 : round(($uniqueClicks / $delivered) * 100, 2),
			'deliveryRate' => $delivered == 0 ? 0 : round(($delivered / $requests) * 100, 2),
			'unsubscribeRate' => $delivered == 0 ? 0 : round(($unsubscribed / $delivered) * 100, 2),
			'spamRate' => $delivered == 0 ? 0 : round(($spamReports / $delivered) * 100, 2), 
			'bounceRate' => $requests == 0 ? 0 : round((($hardBounces + $softBounces) / $requests) * 100, 2),
		];
	}

    function getBrevoTransactionalStats() {

        $payload = [
            'startDate' => gmdate('Y-m-d', strtotime('-8 day')),
            'endDate' => gmdate('Y-m-d', strtotime('-1 day')),
        ];
    
        $url = 'https://api.brevo.com/v3/smtp/statistics/aggregatedReport';
        $url .= '?' . http_build_query($payload);
        
		$stats = [];

		for ($i = 0; $i < 3; $i++) {
			$response = brevoAPICall($url);
            $stats = json_decode($response);

			if(isset($stats->range)) break;
			sleep(30); // Retry after 30 seconds
		}

		if(!isset($stats->range)) throw new Exception('Error retrieving Brevo Transactional Stats Data');

        $requests = $stats->requests ?? 0;
        $delivered = $stats->delivered ?? 0;
        $hardBounces = $stats->hardBounces ?? 0;
        $softBounces = $stats->softBounces ?? 0;
        $uniqueOpens = $stats->uniqueOpens ?? 0;
        $uniqueClicks = $stats->uniqueClicks ?? 0;
        $spamReports = $stats->spamReports ?? 0;
        $unsubscribed = $stats->unsubscribed ?? 0;

		return [
			'requests' => $requests,
			'openRate' => $delivered == 0 ? 0 : round(($uniqueOpens / $delivered) * 100, 2),
			'CTR' => $delivered == 0 ? 0 : round(($uniqueClicks / $delivered) * 100, 2),
			'deliveryRate' => $delivered == 0 ? 0 : round(($delivered / $requests) * 100, 2),
			'unsubscribeRate' => $delivered == 0 ? 0 : round(($unsubscribed / $delivered) * 100, 2),
			'spamRate' => $delivered == 0 ? 0 : round(($spamReports / $delivered) * 100, 2), 
			'bounceRate' => $requests == 0 ? 0 : round((($hardBounces + $softBounces) / $requests) * 100, 2),
		];
	}

    $metrics = [];
    $metrics['contactsCount'] = getBrevoContactsCount();
    
	$campaign_metrics = getBrevoCampaignStats();
    $transactional_metrics = getBrevoTransactionalStats();

    foreach($campaign_metrics as $key => $value) {
        $metrics[$key.'Campaign'] = $value;
    }
    foreach($transactional_metrics as $key => $value) {
        $metrics[$key.'Transactional'] = $value;
    }

    $titlesToFields = [
        'Brevo Contacts Count' => 'contactsCount',
        'Brevo Campaign Bounce Rate' => 'bounceRateCampaign',
        'Brevo Campaign CTR' => 'CTRCampaign',
        'Brevo Campaign Delivery Rate' => 'deliveryRateCampaign',
        'Brevo Campaign Open Rate' => 'openRateCampaign',
        'Brevo Campaign Spam Rate' => 'spamRateCampaign',
        'Brevo Campaign Unsubscribe Rate' => 'unsubscribeRateCampaign',
        'Brevo Transactional Bounce Rate' => 'bounceRateTransactional',
        'Brevo Transactional CTR' => 'CTRTransactional',
        'Brevo Transactional Delivery Rate' => 'deliveryRateTransactional',
        'Brevo Transactional Open Rate' => 'openRateTransactional',
        'Brevo Transactional Spam Rate' => 'spamRateTransactional',
        'Brevo Transactional Unsubscribe Rate' => 'unsubscribeRateTransactional',
	];

    echo 'Brevo Metrics: ' . print_r($metrics, true);

	foreach ($titlesToFields as $title => $field) {
		$SQL = "UPDATE system_health SET value={$metrics[$field]}, updated={$nowSql} WHERE title = '$title'";
		db_query($SQL);
	}
?>
