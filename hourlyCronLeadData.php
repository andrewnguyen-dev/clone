<?php
	// Cron script to validate recent leads every 10 minutes
	require_once('global.php');

	// Calculate time 10 minutes ago
	$past = new \DateTime(db_getVal("SELECT DATE_SUB({$nowSql}, INTERVAL 10 MINUTE) as past"));
	$since = $past->format('Y-m-d H:i:s');

	$SQL = "SELECT record_num, systemDetails, quoteDetails, siteDetails, leadType, company FROM leads WHERE created >= '$since' AND status != 'duplicate' AND leadType IN ('Residential', 'Commercial')";
	echo $SQL;
	$result = db_query($SQL);

	$leadsByType = [
		'normal' => [],
		'ev'     => [],
		'hwhp'   => [],
		'hybrid' => [],
		'offgrid' => [],
		'commercial' => []
	];

	while ($lead = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
		$systemDetails = unserialize(base64_decode($lead['systemDetails']));
		$quoteDetails  = unserialize(base64_decode($lead['quoteDetails']));
		$siteDetails   = isset($lead['siteDetails']) ? unserialize(base64_decode($lead['siteDetails'])) : [];

		$features  = strtolower($systemDetails['Features:'] ?? '');

		if (stripos($features, 'ev charger') !== false) {
			$type = 'ev';
		} elseif (stripos($features, 'hot water heat pump') !== false) {
			$type = 'hwhp';
		} elseif (stripos($features, 'hybrid system (grid connect with batteries)') !== false) {
			$type = 'hybrid';
		} elseif (stripos($features, 'off grid / remote area system') !== false) {
			$type = 'offgrid';
		} elseif ($lead['leadType'] == 'Commercial') {
			$type = 'commercial';
		} else {
			$type = 'normal';
		}

		$missingFields = [];
		if ($type === 'normal' || $type === 'hybrid' || $type === 'offgrid') {
			if ($type === 'normal') {
				if (empty($systemDetails['System Size:'])) {
					$missingFields[] = 'System Size';
				}
			}
			if (empty($quoteDetails['Timeframe for purchase:'])) {
				$missingFields[] = 'Timeframe';
			}
			if (empty($siteDetails['Type of Roof:'])) {
				$missingFields[] = 'Type of Roof';
			}
		} elseif ($type === 'commercial') {
			if ($lead['company'] === '')  {
				$missingFields[] = 'Company Name';
			}
			if (empty($quoteDetails['Timeframe for purchase:'])) {
				$missingFields[] = 'Timeframe';
			}
			if (empty($quoteDetails['Electricity Bill per month:'])) {
				$missingFields[] = 'Electrical Bill';
			}
		} elseif ($type === 'ev') {
			if (empty($siteDetails['Car Make/Model:'])) {
				$missingFields[] = 'Car Make/Model';
			}
			if (empty($siteDetails['EV Installation Type:'])) {
				$missingFields[] = 'EV Installation Type';
			}
			if (empty($siteDetails['Distance between charger and switchboard:'])) {
				$missingFields[] = 'Charger Distance';
			}
		} elseif ($type === 'hwhp') {
			if (empty($siteDetails['Existing Hot Water System:'])) {
				$missingFields[] = 'Existing Hot Water System';
			}
			if (empty($siteDetails['Number of Residents:'])) {
				$missingFields[] = 'Number of Residents';
			}
			if (empty($siteDetails['Location Accessibility:'])) {
				$missingFields[] = 'Location Accessibility';
			}
			if (empty($siteDetails['Distance between heat pump and switchboard:'])) {
				$missingFields[] = 'Switchboard Distance';
			}
		}

		$leadsByType[$type][] = [
			'id'     => $lead['record_num'],
			'missing' => $missingFields,
		];
	}

	$message = '';
	foreach ($leadsByType as $type => $leads) {
		foreach ($leads as $data) {
			if (!empty($data['missing'])) {
				$missingStr = implode(', ', $data['missing']);
				$link = "https://www.solarquotes.com.au/leads/lead_view/?lead_id={$data['id']}";
				$message .= "Lead {$data['id']} - MISSING: {$missingStr}\nLink: {$link}\n\n";
			}
		}
	}
	echo $message;

	$htmlMessage = nl2br($message);

	if ($message)
		sendMailNoTemplate($techEmail, "SQ IT", 'IMPORTANT: Lead Missing Data', $htmlMessage, $dispatchEmail, $dispatchName, 'autoresponder_mail');
?>