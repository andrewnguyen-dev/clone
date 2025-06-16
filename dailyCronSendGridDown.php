<?php
    include('global.php');
    
    $SQL = "SELECT COUNT(*) FROM leads WHERE `campaign` = '' ";
	$SQL .= "AND record_num IN (SELECT lead_id FROM lead_suppliers) AND submitted > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
	$leadCount = db_getVal($SQL);
	
	if ($leadCount > 0) {
		echo "Count {$leadCount}";
		SendMail("johnb@solarquotes.com.au", "John Burcher", "SendGrid Down", "Count {$leadCount}");
	}
?>