<?php
	include('global.php');
	set_time_limit(0);
	
	$rejectArray = array();
	$emailArray = array();
	
	$SQL = "SELECT S.company, LS.supplier, COUNT(*) AS Count, LS.status FROM lead_suppliers LS ";
	$SQL .= "INNER JOIN suppliers S ON LS.supplier = S.record_num ";
    $SQL .= "WHERE LS.dispatched BETWEEN DATE_SUB(CURRENT_DATE(), INTERVAL 4 WEEK) AND CURRENT_DATE() ";
    $SQL .= "GROUP BY LS.supplier, LS.status;";
    $RejectRate = db_query($SQL);
    
    while ($RejectRateRow = mysqli_fetch_array($RejectRate, MYSQLI_ASSOC)) {
    	extract(htmlentitiesRecursive($RejectRateRow), EXTR_PREFIX_ALL, 's');
    	
    	$rejectArray[$s_company][$s_status] = $s_Count;
	}
	
	// Now loop the array and add in the reject rate
	foreach ($rejectArray AS $rejectArrayKey => $rejectArrayValue) {
		$totalSent = 0;
		
		foreach ($rejectArrayValue AS $rejectKey => $rejectValue) {
			if ($rejectKey == 'missing') {
				// Do nothing
			} else {
				$totalSent += $rejectValue;
			}
		}
		
		if (isset($rejectArrayValue['rejected'])) {
			$rejectArray[$rejectArrayKey]['rejectedPercentage'] = number_format($rejectArrayValue['rejected'] / $totalSent * 100, 0);
			$emailArray[$rejectArrayKey]['Total'] = $totalSent;
			$emailArray[$rejectArrayKey]['Percentage'] = number_format($rejectArrayValue['rejected'] / $totalSent * 100, 0);
		}
	}
	
	// Sort the array
	$emailArray = subval_sort($emailArray, 'Percentage', 'desc');
	
	// Create the email
	$body = "<html>";
	$body .= "<head></head>";
	$body .= "<body>";
	$body .= "<p>The data is reporting on a rolling 4 week period and not month on month</p>";
	$body .= "<table width = 500px>";
	$body .= "<tr><th align = left>Supplier</th><th align = left>Total Sent</th><th align = left>Reject Rate</th></tr>";
	
	foreach ($emailArray AS $emailKey => $emailValue) {
		$body .= "<tr>";
		$body .= "<td>{$emailKey}</td><td>{$emailValue['Total']}</td><td>{$emailValue['Percentage']}%</td>";
		$body .= "</tr>";
	}

	$body .= "</table>";
	$body .= "</body>";
	$body .= "</html>";
	
	SendMail('ned@solarquotes.com.au', 'Ned', 'Weekly Reject Rate Notification', $body);

	function subval_sort($a, $subkey, $order='asc') {
		foreach( $a as $k=>$v )
			$b[$k] = strtolower( $v[$subkey] );
		
		if( $order === 'desc' )
			arsort( $b );
		else
			asort( $b );
			
		foreach( $b as $key=>$val )
			$c[$key] = $a[$key];
		
		return $c;
	}
?>