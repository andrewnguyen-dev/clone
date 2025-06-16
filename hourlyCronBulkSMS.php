<?php
	// load global libraries
	include('global.php');


	// Set all scheduled SMS to 'waiting' if the scheduled time is now or has already passed
	$SQL = "
		UPDATE bulk_sms_leads BSL
		JOIN bulk_sms BS 
			ON BS.record_num = BSL.bulk_sms_id
		SET status = 'waiting'
		WHERE 
			status = 'scheduled'
			AND scheduled_date <= $nowSql    
	";
	db_query($SQL);

	// Select up to 1000 pending SMS
	$SQL = "
		SELECT BSL.record_num, text, phone FROM bulk_sms_leads BSL
		JOIN bulk_sms BS 
			ON BS.record_num = BSL.bulk_sms_id
		WHERE 
			status = 'waiting' 
		ORDER BY 
			BSL.record_num ASC
		LIMIT 1000
	";
	$result = db_query($SQL);

	// stop the execution if no 'waiting' sms is found
	if(mysqli_num_rows($result) == 0) {
		die();
	}

	// Mark all of the SMS to be dispatched as "processing"
	$smsIds = [];
	while ($sms = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
		$smsIds[] = $sms['record_num'];
	}
	$SQL = "UPDATE bulk_sms_leads SET status = 'processing' WHERE record_num in (". implode(',', $smsIds) .");";
	db_query($SQL);

	// Send all the processing SMS
	mysqli_data_seek($result, 0); // rewind $result to the first row
	while ($sms = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
		extract($sms, EXTR_PREFIX_ALL, 's');
		$response = sendSms($s_phone, $s_text, $twilioNumberBulkSMS);
		if($response) {
			$response = json_decode($response);
			$to = isset($response->to) ? $response->to : 'invalid';
			$status = in_array($response->status, ['queued', 'sent', 'delivered']) ? 'sent' : 'failed';
			$SQL = "UPDATE bulk_sms_leads SET status = '$status', sent = $nowSql, phone = '$to' WHERE record_num = $s_record_num";
		} else {
			$SQL = "UPDATE bulk_sms_leads SET status = 'failed' WHERE record_num = $s_record_num";
		}
		db_query($SQL);
	}
?>
