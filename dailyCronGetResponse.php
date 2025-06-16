<?php
	// load global libraries
	require_once('global.php');

	$time = date('Y-m-d\TH:i:sO',strtotime('-30days',time()));

	# GR contacts
	$results = getGRContactList($time,'contactId,createdOn','ASC',1,1,'4O4pL');

	if($results['headers']['status'] == 'HTTP/1.1 200 OK'){
		db_query("UPDATE system_health SET value={$results['headers']['totalcount']}, updated={$nowSql} WHERE title = 'GetResponse Contacts Count'");
	} else {
		// Retry after 30 seconds
		sleep(30);
		$results = getGRContactList($time,'contactId,createdOn','ASC',1,1,'4O4pL');
		if($results['headers']['status'] == 'HTTP/1.1 200 OK'){
			db_query("UPDATE system_health SET value={$results['headers']['totalcount']}, updated={$nowSql} WHERE title = 'GetResponse Contacts Count'");
		} else {
			echo 'Error retrieving GetResponse Contacts Data'."\n".print_r($results['body'],true);
		}
	}

	# GR emails
	$results = getGRAutoresponderStats($time,'sent,bounced','4O4pL');
	

	if($results['headers']['status'] == 'HTTP/1.1 200 OK'){
		db_query("UPDATE system_health SET value={$results['body'][0]['sent']}, updated={$nowSql} WHERE title = 'GetResponse Emails Sent'");
		db_query("UPDATE system_health SET value=round(100-(({$results['body'][0]['bounced']}/{$results['body'][0]['sent']})*100),0), updated={$nowSql} WHERE title = 'GetResponse Delivery Rate'");
	} else {
		// Retry after 30 seconds
		sleep(30);
		$results = getGRAutoresponderStats($time,'sent,bounced','4O4pL');
		if($results['headers']['status'] == 'HTTP/1.1 200 OK'){
			db_query("UPDATE system_health SET value={$results['body'][0]['sent']}, updated={$nowSql} WHERE title = 'GetResponse Emails Sent'");
			db_query("UPDATE system_health SET value=round(100-(({$results['body'][0]['bounced']}/{$results['body'][0]['sent']})*100),0), updated={$nowSql} WHERE title = 'GetResponse Delivery Rate'");
		} else {
			echo 'Error retrieving GetResponse Email Data'."\n".print_r($results['body'],true);
		}
	}
?>