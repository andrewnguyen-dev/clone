<?php
	// load global libraries
	require_once('global.php');

	global $nowSql, $leadcsvdir;

	$cacheDir = $leadcsvdir;
	$cachePrefix = 'sgContactsCount_';

	$now = new DateTime();
	$now->modify('-1 day');
	$yesterday = $now->format('Y-m-d');

	// Name => [account => key]
	// Name: Name to use in system_health table
	// account: Account name to use with on-behalf-of to get email stats
	// key: api key name with access to marketing to get contacts total count
	$sgAccounts = [
		'Main' => ['main' => 'main_contacts'],
		'AutoResponder' => ['solarquotes_autoresponders' => 'autoresponder_contacts'],
		'Installers' => ['solarquotes_installers' => 'installers_contacts'],
		'Affiliates' => ['solarquotes_affiliates' => 'affiliates_contacts'],
		'Communications' => ['solarquotes_installer_communications' => 'installer_communications_contacts']
	];

	foreach($sgAccounts as $test => $details){
		foreach($details as $account => $key){
			// Get email stats
			$stats = GetSGEmailStats($yesterday, $yesterday, $account);
			if(!isset($stats[0]['stats'][0]['metrics'])){
				error_log('Error retrieving SendGrid Email Data - '."$account\n".print_r($stats['errors'][0]['message'],true));
				$stats[0]['stats'][0]['metrics']['delivered'] = 0; // Set delivered to 0 to indicate a problem
			}
			$stats = $stats[0]['stats'][0]['metrics'];
			$delivered = $stats['delivered'];
			$deliveryRate = 0;
			if($delivered > 0)
				$deliveryRate = (1 - ($stats['blocks'] + $stats['bounces'] + $stats['invalid_emails']) / $stats['delivered']) * 100;

			$delivered = round($delivered);
			$deliveryRate = round($deliveryRate, 2);
			$requests = intval($stats['requests'] ?? 0);
			$uniqueOpens = intval($stats['unique_opens'] ?? 0);
			$openRate = ($requests > 0) ? ($uniqueOpens / $requests) * 100 : 0;
			$openRate = round($openRate, 2);
			db_query("UPDATE system_health SET value={$delivered}, updated={$nowSql} WHERE title = 'SendGrid {$test} - Emails Sent'");
			db_query("UPDATE system_health SET value={$deliveryRate}, updated={$nowSql} WHERE title = 'SendGrid {$test} - Delivery Rate'");
			db_query("UPDATE system_health SET value={$openRate}, updated={$nowSql} WHERE title = 'SendGrid {$test} - Open Rate'");

			// Get contact stats
			$count = GetSGContacsCount($key);
			if(!isset($count['contact_count'])){
				error_log('Error retrieving SendGrid Contact Data - '."$account\n".print_r($count['errors'][0]['message'],true));
				$count['contact_count'] = 0; // Set contacts to 0 so the diff will be negative, ie. a problem
			}
			$count = $count['contact_count'];

			$cacheFile = $cacheDir.'/'.$cachePrefix.$account;
			if (!file_exists($cacheFile))
				file_put_contents($cacheFile, '0');
			$cacheCount = file_get_contents($cacheFile);

			$diff = $count - $cacheCount;

			db_query("UPDATE system_health SET value={$diff}, updated={$nowSql} WHERE title = 'SendGrid {$test} - Contacts Count'");

			file_put_contents($cacheFile, $count);
		}
	}
?>