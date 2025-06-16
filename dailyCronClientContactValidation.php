<?php
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

	// load global libraries
	include('global.php');
	set_time_limit(0);
	$debugging = 0;

	$limit = 50;

	// Start with parents, their children will be filtered out from the suppliers select
	foreach(['suppliers_parent', 'suppliers'] as $table) {
		if($table=='suppliers') {
			$extraLeads = " OR extraLeads = 'Y'";
			$company = 'company';
		} else {
			$extraLeads = "";
			$company = "parentName";
		}
		// Get suppliers and parents with their contactDetailsLastUpdate timer expired
		$SQL =  "SELECT record_num, $company AS company";
		$SQL .= " FROM {$table} WHERE (status='active' {$extraLeads}) AND contactDetailsLastUpdate <= DATE_SUB(NOW(), INTERVAL 3 MONTH) ";
		$SQL .= " AND record_num!=1 LIMIT $limit";
		$rows = db_query($SQL);
		$resetTimer = [];

		while ($row = mysqli_fetch_array($rows, MYSQLI_ASSOC)) {
			$resetTimer[] = $row['record_num'];	// Hold record_num for a batch update
			$limit--;

			if(!isset($zendeskClient)) { // set these only once for every ticket to be raised
				$zendeskClient = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
				$zendeskClient->setAuth('token', $zenDeskKey);
				$agent_id = db_getVal('SELECT a.zendeskid FROM settings s INNER JOIN admins a ON s.supplier_contact_validation = a.record_num;');
			}

			raiseTicket($row, $table, $zendeskClient, $agent_id);
		}

		if(count($resetTimer)>0) {	// Batch update, reset timers
			$SQL =  "UPDATE {$table} SET contactDetailsLastUpdate = NOW() ";
			$SQL .= " WHERE record_num IN (". implode(", ", $resetTimer) . ");";	
			db_query($SQL);
			if($table=='suppliers_parent') { // When resetting parents' timers, reset their children's timers too
				$SQL =  "UPDATE suppliers SET contactDetailsLastUpdate = NOW() ";
				$SQL .= " WHERE parent IN (". implode(", ", $resetTimer) . ");";	
				db_query($SQL);
			}
		}
	}
	
	echo 'Done';

	function raiseTicket($entity, $table, $zendeskClient, $agent_id) {
		global $siteURLSSL;

		$company = $entity['company'];

		$subject = 'Contact Information Validation - '. $company;
		$href = $siteURLSSL . "leads/supplier_";
		if($table=='suppliers')
			$href .= 'edit/' . $entity['record_num'] .'/';
		else
			$href .= 'parent_edit/' . $entity['record_num'] .'/';

		$body = "<p><strong>{$company}</strong> is due to have their contact information verified. ";
		$body .= "Their details can be seen in <a href='{$href}'>Lead Manager</a>.</p>";
		$body .= "<p>You will need to call their public number to check that they are still reachable on that number. ";
		$body .= "When they answer the phone let them know that:</p>";
		$body .= "<ol><li>You are calling from SolarQuotes to check that we have their most up to date contact information.</li>";
		$body .= "<li>If possible, double-check their email address whilst on the phone and ";
		$body .= "ask if they are expecting any other changes in their business soon.</li>";
		$body .= "<li>If you don't get through, send them an email on their contact address and follow up from there.</li></ol>";

		$ticket = $zendeskClient->tickets()->create([
			'tags' => ['client-verification'],
			'subject' => $subject,
			'comment' => [
				'html_body' => $body,
				'public' => false
			],
			'priority' => 'normal',
			'assignee_id' => $agent_id
		]);	
	}
?>
