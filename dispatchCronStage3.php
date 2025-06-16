<?php
	// load global libraries
	include('global.php');
	set_time_limit(0);
	$debugging = 0;
	
	$SQL = " SELECT record_num FROM leads WHERE status = 'sending' AND (suspendedUntil IS NULL OR suspendedUntil < ".$nowSql.") ORDER BY leads.record_num ASC LIMIT 10; ";
	$leads = db_query($SQL);
	
	while($lead = mysqli_fetch_assoc($leads)){
		extract($lead, EXTR_PREFIX_ALL, 'l');
		$dispatchResult = dispatchClaims($l_record_num);
		if($dispatchResult === "SUSPENDED")
			continue;

		// Check if lead exists in the lead_finance table with status waiting
		$SQL = " SELECT record_num, lead_id FROM lead_finance WHERE lead_id = '{$l_record_num}' AND status = 'waiting'; ";
		$finance = mysqli_fetch_assoc(db_query($SQL));

		// There's a finance lead waiting to be sent
		if(! is_null($finance)){
			// Check if the lead has assigned suppliers
			if(is_numeric($dispatchResult) && $dispatchResult > 0){
				$SQL = " UPDATE lead_finance SET status = 'sending', updated = NOW() WHERE lead_id = '{$l_record_num}'";
				db_query($SQL);
			} else {
				$SQL = " UPDATE lead_finance SET status = 'skipped', updated = NOW() WHERE lead_id = '{$l_record_num}'";
				db_query($SQL);
			}
		}

		// Send the notification e-mail
        $data = loadLeadData($l_record_num);
        
        // Now that the lead got dispatched, checked the number of lead suppliers against the requested number of quotes
        if($data['numSuppliers'] > $data['requestedQuotes']){
        	global $techEmail, $techName, $siteURLSSL;
        	$body = '';
        	$body .= '<p>A lead has been assigned to more suppliers than those requested</p>';
        	$body .= '<p><strong>Lead Information</strong></p>';
        	$body .= '<ul>';
        	$body .= '<li>Name: ' . $data['fName'] . ' ' . $data['lName']; '</li>';
        	$body .= '<li>Link: ' . $siteURLSSL . '/leads/lead_view?lead_id=' . $data['record_num'] . '</li>';
        	$body .= '</ul>';
			SendMail($techEmail, $techName, 'Supplier count above requested quote count', $body);
        }
        $tables = "permissions AS p LEFT JOIN admin_permissions AS ap ON ap.permission=p.record_num LEFT JOIN admins AS a ON ap.admin=a.record_num";
        $r = db_query("SELECT a.name, a.email FROM $tables WHERE p.code='emailDetails'");
        while ($d = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
            extract($d, EXTR_PREFIX_ALL, 'a');
            
            $isEVChargerLead = stripos($data['systemDetails'], 'EV Charger') !== false;
	        if($isEVChargerLead && isset($data['leadImages'])){
		        $data['systemDetailsCells'] .= $data['leadImages'] ?? [];
	        }
            if ($a_email != ''){
				sendTemplateEmail($a_email, $a_name, 'newLead', $data);
            }
        }

		// Was it successfully dispatched? Then fire the leadAfterDispatch function
		leadAfterDispatch($l_record_num);
	}
?>