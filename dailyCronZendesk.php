<?php
	// load global libraries
	require_once('global.php');

	global $zenDeskSubdomain, $zenDeskEmail, $zenDeskKey;

	$zdTests = [
		'Zendesk Tickets Created' => 'created>24hours type:ticket',
		'Zendesk Tickets From Email Forwarders' => 'created>24hours type:ticket recipient:support@solarquotes.zendesk.com'
	];

	$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
	$client->setAuth('token', $zenDeskKey);

	foreach($zdTests as $name => $query){
		try{
			$zSearch = $client->search([
				'query' => $query
			]);
		} catch (Exception $e) {
			sleep(30);
			try {
				$zSearch = $client->search([
					'query' => $query
				]);
			} catch (Exception $e) {
				echo 'Error retrieving ZenDesk data for '.$name."\n".print_r($client->getDebug(),true);
				exit(1);
			}
		}

		if(is_object($zSearch) && property_exists($zSearch,'count')) {
			db_query("UPDATE system_health SET value={$zSearch->count}, updated={$nowSql} WHERE title = '{$name}'");
		} else {
			echo 'Error retrieving ZenDesk data for '.$name."\n".print_r($client->getDebug(),true);
		}
	}
?>