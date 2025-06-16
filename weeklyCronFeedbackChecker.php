<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

	include('global.php');

	// Search feedback based on duplicate IP addresses
	$SQL = "SELECT ipAddress, COUNT(*) AS duplicationCount FROM feedback GROUP BY ipAddress HAVING (COUNT(*) > 1) ORDER BY duplicationCount DESC";
	$result = db_query($SQL);

	// Create the HTML
	$message = "<html><head></head><body>";
	$message .= "<h1>Duplicates By IP Address</h1>";
	CreateTable();

	while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {    	
		extract($row, EXTR_PREFIX_ALL, 'f');

		if ($f_ipAddress != '') {
			$SQL = "SELECT * FROM feedback WHERE ipAddress = '{$f_ipAddress}' ORDER BY feedback_date DESC";
			$feedbackResult = db_query($SQL);

			while ($rowFeedback = mysqli_fetch_array($feedbackResult, MYSQLI_ASSOC)) {
				OutputRow($rowFeedback);
			}
		}
	}

	$message .= "</table>";
	
	// Search feedback based on duplicate email addresses
	$SQL = "SELECT email, COUNT(*) AS duplicationCount FROM feedback GROUP BY email HAVING (COUNT(*) > 1) ORDER BY duplicationCount DESC";
	$result = db_query($SQL);

	$message .= "<br /><br />";
	$message .= "<h1>Duplicates By Email Address</h1>";
	CreateTable();
	
	while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {    	
		extract($row, EXTR_PREFIX_ALL, 'f');

		if ($f_email != '') {
			$SQL = "SELECT * FROM feedback WHERE email = '{$f_email}' ORDER BY feedback_date DESC";
			$feedbackResult = db_query($SQL);

			while ($rowFeedback = mysqli_fetch_array($feedbackResult, MYSQLI_ASSOC)) {
				OutputRow($rowFeedback);
			}
		}
	}

	$message .= "</table>";
	
	// Complete the HTML
	$message .= "</body></html>";

	// Send out email
	//echo $message;
	SendMail('johnb@solarquotes.com.au', 'John', 'Feedback Duplicate Report', $message);

	function CreateTable() {
		GLOBAL $message;

		$message .= "<table width = 100%><tr><th align=left>ID</th><th align=left>Submitted</th><th align=left>Email</th><th align=left>Supplier ID</th><th align=left>Supplier Name</th><th align=left>Published</th><th align=left>IP Address</th>";	
	}

	function OutputRow($row) {
		GLOBAL $message;

		extract($row, EXTR_PREFIX_ALL, 'd');

		if ($d_public == 1)
			$d_public = 'Yes';
		else
			$d_public = 'No';

        $d_record_num = '<a href="http://www.solarquotes.com.au/leads/feedback-view.php?l=' . $d_record_num . '" target="_blank">' . $d_record_num . '</a>';
		
		$message .= "<tr>";
		$message .= "<td>{$d_record_num}</td>";
		$message .= "<td>{$d_feedback_date}</td>";
		$message .= "<td>{$d_email}</td>";
		$message .= "<td>{$d_supplier_id}</td>";
		$message .= "<td>{$d_other_supplier}</td>";
		$message .= "<td>{$d_public}</td>";
		$message .= "<td>{$d_ipAddress}</td>";
		$message .= "</tr>";
	}
?>