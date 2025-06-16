<?php
	/** VARIABLES **/
	$TICKET_NAME = 'Ticket FullName';
	$TICKET_EMAIL = 'johnnie_test@solarquotes.com';
	$RECORD_NUM = 999999;
	$REQUESTED_COUNT = 3;

	include('global.php');

	global $nowSql, $siteURLSSL, $zenDeskSubdomain,
	$zenDeskEmail, $zenDeskKey, $zenDeskAssignees;

	$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
	$client->setAuth('token', $zenDeskKey);

	$subject = sprintf("Lead requested %d quotes but got zero", $REQUESTED_COUNT);
	$body = sprintf(
		"Lead Link: <a href='%sleads/lead_view?lead_id=%d'>%d</a>",
		$siteURLSSL, $RECORD_NUM, $RECORD_NUM
	);

	$response = $client->users()->createOrUpdate([
		'name' => $TICKET_NAME,
		'email' => $TICKET_EMAIL,
		'verified' => true
	]);

	// Create a new ticket
	$newTicket = $client->tickets()->create([
		'subject' => $subject,
		'comment' => [
			'html_body' => $body,
			'public' => false
		],
		'priority' => 'high',
		'requester_id' => $response->user->id,
		'assignee_id' => $zenDeskAssignees['NoSupplierLeads']
	]);
?>
