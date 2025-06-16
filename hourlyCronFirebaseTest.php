<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

	
	include('global.php');

    $token = $_GET['token'];
	$registrationIds = [ $token ];
		
	
	FirebaseNotification::sendFcmNotification(
		userTokens: $registrationIds, 
		body: 'The claim screen has been updated',
		title: 'New Claim Lead', 
		data: [
			'action' => 'go_to_claims',
			'leadID' => "-1"
		],
		sound: 'claim_lead.caf',
		androidChannel: 'claim_lead_channel',
		supplierId: 1,
	);