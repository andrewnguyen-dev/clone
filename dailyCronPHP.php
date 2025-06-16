<?php
    // load global libraries
    include('global.php');
    set_time_limit(600);
    $debugging = 0;
	$yesterday = db_getVal("select DATE_FORMAT({$nowSql}- INTERVAL 1 DAY, '%Y-%m-%d');");
	$yesterdayMin = "{$yesterday} 00:00:00";
	$yesterdayMax = "{$yesterday} 23:59:59";
    
    $leads = array();
    
    $data = array();
	$r = db_query("SELECT record_num FROM leads WHERE summaryEmailed IS NULL AND status!='incomplete' AND status!='duplicate' AND leadType IN ('Residential', 'Commercial') AND (suspendedUntil IS NULL OR suspendedUntil < {$nowSql}) AND created BETWEEN '{$yesterdayMin}' AND '{$yesterdayMax}' ORDER BY record_num ASC");
    
    $numSubmitted = $numMatched = $numSuppliers = 0;
    while ($d = mysqli_fetch_row($r)) {
        $leads[] = $d[0];
        
        $numSubmitted += 1;
        $numS = db_getVal("SELECT COUNT(*) FROM lead_suppliers WHERE lead_id='{$d[0]}' AND type='regular'");
        
        if ($numS > 0) 
            $numMatched += 1;
            
        $numSuppliers += $numS;
    }

	$runningTotals = SelectPreviousRunningTotals('Nationwide');
	// Get deleted count
	$deletedCount = db_getVal("SELECT delete_count FROM daily_summary_deleted");
	$deletedCount == '' ? 0 : $deletedCount;

	$data['State']['Nationwide']['numSubmitted'] = (string) $numSubmitted;
	$data['State']['Nationwide']['numMatched'] = (string) $numMatched;
	$data['State']['Nationwide']['numDeleted'] = (string) $deletedCount;
	$data['State']['Nationwide']['percentMatched'] = number_format(100 * $numMatched / $numSubmitted, 1);
	$data['State']['Nationwide']['avgSuppliers'] = number_format($numSuppliers / $numSubmitted, 2);
	$data['State']['Nationwide']['avgMatched'] = number_format($numSuppliers / $numMatched, 2);
	$data['State']['Nationwide']['numSubmittedPrevious'] = SelectPreviousSubmittedLeads('Nationwide');
	$data['State']['Nationwide']['avgSuppliersPrevious'] = $runningTotals[0];
	$data['State']['Nationwide']['avgMatchedPrevious'] = $runningTotals[1];
	$data['State']['Nationwide']['income'] = CalculateIncome();
	$data['State']['Nationwide']['mortgageLeadsReceived'] = 0;

	//Delete current delete count
	db_query("DELETE FROM daily_summary_deleted");
	
	$loanMarketTotalsQuery = db_query('SELECT iState, COUNT(*) as count, group_concat(record_num) as leadIds FROM leads WHERE summaryEmailed IS NULL AND leadType = \'LoanMarket\' AND status != \'duplicate\' GROUP BY iState WITH ROLLUP;');
	$loanMarketTotals = array();
	
	while($row = mysqli_fetch_assoc($loanMarketTotalsQuery)){
		if(is_null($row['iState'])){
			$data['State']['Nationwide']['mortgageLeadsReceived'] = $row['count'];
			$leads = array_merge($leads, explode(',',$row['leadIds']));
		} else {
			$loanMarketTotals[$row['iState']] = $row['count'];
		}
	}
	    
    // Loop through the states
    foreach ($states AS $state => $stateValue) {
    	$numSubmitted = $numMatched = $numSuppliers = 0;
		$r = db_query("SELECT record_num FROM leads WHERE status!='incomplete' AND status!='duplicate' AND summaryEmailed IS NULL AND iState='{$state}' AND leadType IN ('Residential', 'Commercial') AND (suspendedUntil IS NULL OR suspendedUntil < {$nowSql}) AND created BETWEEN '{$yesterdayMin}' AND '{$yesterdayMax}' ORDER BY record_num ASC");
		
		while ($d = mysqli_fetch_row($r)) {
	        $numSubmitted += 1;
	        $numS = db_getVal("SELECT COUNT(*) FROM lead_suppliers WHERE lead_id='{$d[0]}' AND type='regular'");
	        
	        if ($numS > 0) 
	            $numMatched += 1;
	            
	        $numSuppliers += $numS;
	    }
	    
	    $data['State'][$state]['income'] = 0;
	    if ($numSubmitted == 0 || $numMatched == 0) {
	    	$data['State'][$state]['numSubmitted'] = 0;
		    $data['State'][$state]['numMatched'] = 0;
		    $data['State'][$state]['numDeleted'] = 0;
		    $data['State'][$state]['percentMatched'] = 100;
		    $data['State'][$state]['avgSuppliers'] = 0;
		    $data['State'][$state]['avgMatched'] = 0;
		} else {
			$data['State'][$state]['numDeleted'] = 0;
	    	$data['State'][$state]['numSubmitted'] = (string) $numSubmitted;
		    $data['State'][$state]['numMatched'] = (string) $numMatched;
		    $data['State'][$state]['percentMatched'] = number_format(100 * $numMatched / $numSubmitted, 1);
		    $data['State'][$state]['avgSuppliers'] = number_format($numSuppliers / $numSubmitted, 2);
		    $data['State'][$state]['avgMatched'] = number_format($numSuppliers / $numMatched, 2);
		}
		
		if(isset($loanMarketTotals[$state]))
			$data['State'][$state]['mortgageLeadsReceived'] = $loanMarketTotals[$state];
		else
			$data['State'][$state]['mortgageLeadsReceived'] = 0;
		
		// Find the leads submitted from a week ago
		$data['State'][$state]['numSubmittedPrevious'] = SelectPreviousSubmittedLeads($state);

		// Finally select the rolling averages
		$runningTotals = SelectPreviousRunningTotals($state);
		
		$data['State'][$state]['avgSuppliersPrevious'] = $runningTotals[0];
		$data['State'][$state]['avgMatchedPrevious'] = $runningTotals[1];
	}
    
    // Start the body
    $body = "This is a daily summary of the important supplier changes and leads requested/dispatched on {$siteURLSSL}.<br /><br />";
    
    RenderSupplierChanges();
    
    RenderSupplierParentChanges();
    
    RenderIncome();
    
    RenderIncompletes();
    
    // Update the leads that we have executed
	$SQL  = "UPDATE leads SET summaryEmailed={$nowSql} WHERE record_num IN (" . implode(",", $leads) . ")"; 
    db_query($SQL);
    
    // Add in the util stats
    foreach ($data['State'] AS $state => $value) {		
		$body .= "<b>{$state}</b><br />";
	    $body .= "-----------<br />";
	    $body .= "{$value['numSubmitted']} lead(s) submitted | same day last week: {$value['numSubmittedPrevious']}<br />";
	    $body .= "{$value['numMatched']} lead(s) where matched to at least one (signed) supplier.<br />";
	    $body .= "{$value['mortgageLeadsReceived']} loan market lead(s) were submitted.<br />";
	    $body .= "{$value['percentMatched']}% Match Rate.<br />";
	    $body .= "{$value['avgSuppliers']} Suppliers per submitted lead (average) | 7 day avg {$value['avgSuppliersPrevious']}<br />";
	    $body .= "{$value['avgMatched']} Suppliers per matched lead (average) | 7 day avg {$value['avgMatchedPrevious']}\n<br /><br />";
	    
	    // Insert values into database
	    $SQL = "INSERT INTO daily_summary (region, income, leads_submitted, leads_matched, leads_deleted, mortgage_leads_received, match_rate, suppliers_per_submitted, suppliers_per_match, submitted) VALUES (";
	    $SQL .= "'{$state}', '{$value['income']}', '{$value['numSubmitted']}', '{$value['numMatched']}', '{$value['numDeleted']}', '{$value['mortgageLeadsReceived']}', '{$value['percentMatched']}', '{$value['avgSuppliers']}', '{$value['avgMatched']}', DATE_SUB({$nowSql}, INTERVAL 1 DAY))";
	    db_query($SQL);
    }

    $tables = "permissions AS p LEFT JOIN admin_permissions AS ap ON ap.permission=p.record_num LEFT JOIN admins AS a ON ap.admin=a.record_num";
    $rs = db_query("SELECT a.name, a.email FROM {$tables} WHERE p.code='emailSummary'");
    while ($d = mysqli_fetch_array($rs, MYSQLI_ASSOC)) {
        extract($d, EXTR_PREFIX_ALL, 'a');
        
        SendMail($a_email, $a_name, $siteURLSSL . ' Daily Summary', $body, '', '', ['bigTitle'=>'Daily Summary']);
    }
    
    // Do a final clean up
    $SQL  = "UPDATE leads SET summaryEmailed={$nowSql} WHERE updated <= '{$yesterdayMax}' AND summaryEmailed IS NULL AND status = 'dispatched' "; 
    db_query($SQL);
    
    function CalculateIncome() {
		global $leads;
		
		$SQL = "SELECT SUM(leadPrice) ";
		$SQL .= "FROM lead_suppliers LS ";
		$SQL .= "WHERE LS.lead_id IN (" . implode(",", $leads) . ")";
		return db_getVal($SQL);
    }
    
    function RenderIncome() {
    	global $body;
    	
		$total = CalculateIncome();

		$body .= "<b>Income</b><br />";
		$body .= "$" . number_format($total) . "<br /><br />";
    }
    
    function RenderIncompletes() {
		global $body, $leads, $yesterdayMin, $yesterdayMax, $data;

		// Get all cleared incomplete leads in the past 24 hours
		$SQL = "SELECT COUNT(*) FROM leads WHERE notes LIKE '%incomplete%' AND status = 'dispatched' AND summaryEmailed IS NULL";
		$incompleteLeadsCleared = db_getVal($SQL);
		
		// Now get the count of all current incomplete leads
		$SQL = "SELECT COUNT(*) FROM leads WHERE status = 'incomplete' AND summaryEmailed IS NULL AND leadType NOT IN ('Repair Residential', 'Repair Commercial') AND record_num NOT IN ( SELECT lead_id FROM leads_spam WHERE spam_field LIKE '%battery reference' OR spam_field LIKE 'Tesla reference')";
		$incompleteLeadsSubmitted = db_getVal($SQL);

		// Get count of missing quotes submitted
		$missingQuotesSubmitted = db_getVal("SELECT COUNT(*) FROM lead_suppliers WHERE status = 'missing' AND type='regular' AND ( updated BETWEEN '{$yesterdayMin}' AND '{$yesterdayMax}')");

		// Get count of deleted leads
		$incompleteLeadsDeleted = $data['State']['Nationwide']['numDeleted'];
		$incompleteLeadsDeleted = (intval($incompleteLeadsDeleted > 0)) ? $incompleteLeadsDeleted : 0;


		$body .= "<b>Incomplete Leads Submitted:</b> {$incompleteLeadsSubmitted}<br />";
		$body .= "<b>Incomplete Leads Cleared:</b> {$incompleteLeadsCleared}<br />";
		$body .= "<b>Old Incomplete Leads Deleted:</b> {$incompleteLeadsDeleted}<br />";
		$body .= "<b>Missing Quotes Submitted:</b> {$missingQuotesSubmitted}<br /><br /><br />";
    }
    
    function RenderSupplierChanges() {
        global $body;
        
        $currentSupplierID = -1;

        $SQL = " SELECT ls.record_num, COALESCE(ls.supplier, 0) as supplier_id, ls.description, s.company";
		$SQL .= " FROM log_supplier ls ";
		$SQL .= " LEFT JOIN suppliers s ON ls.supplier = s.record_num ";
		$SQL .= " WHERE cronInclude = 'Y' AND cronSent = 'N' ";
		$SQL .= " ORDER BY s.company ASC, ls.submitted DESC; ";
		
        $result = db_query($SQL);
        $count = mysqli_num_rows($result);
        
        if ( $count > 0) {
        	$body .= "<b>Supplier Changes</b>";	
        }
        
        while ($row = mysqli_fetch_array($result)) {
            extract($row, EXTR_PREFIX_ALL, 'a');
            
            if ($a_supplier_id != $currentSupplierID) {
            	if($a_supplier_id == 0)
            		$a_company = 'Unauthorized / Unknown';
            	$currentSupplierID = $a_supplier_id;
                $body .= "<br /><br /><b>{$a_company}</b><br />";
            }
            
            db_query("UPDATE log_supplier SET cronSent = 'Y' WHERE record_num = {$a_record_num}");
            
            $body .= "<br />{$a_description}\n";
        }
        
        if ( $count > 0) {
        	$body .= "<br /><br /><br />";
		}
    }
    
    function RenderSupplierParentChanges() {
        global $body;
        
        $currentSupplierID = -1;
        
        $SQL = " SELECT lsp.record_num, COALESCE(lsp.parent, 0) as supplier_parent_id, lsp.description, sp.parentName";
		$SQL .= " FROM log_supplier_parent lsp ";
		$SQL .= " LEFT JOIN suppliers_parent sp ON lsp.parent = sp.record_num ";
		$SQL .= " WHERE cronInclude = 'Y' AND cronSent = 'N' ";
		$SQL .= " ORDER BY sp.parentName ASC, lsp.submitted DESC; ";
		
		$result = db_query($SQL);
        $count = mysqli_num_rows($result);
        $aux_body = '';
        
		while ($row = mysqli_fetch_array($result)) {
			extract($row, EXTR_PREFIX_ALL, 'a');

			if($a_supplier_parent_id != 0 && $a_parentName == '')
				continue;
			if ($a_supplier_parent_id != $currentSupplierID) {
				if($a_supplier_parent_id == 0)
					$a_parentName = 'Unauthorized / Unknown';
				$currentSupplierID = $a_supplier_parent_id;
				$aux_body .= "<br /><br /><b>{$a_parentName}</b><br />";
			}

			db_query("UPDATE log_supplier_parent SET cronSent = 'Y' WHERE record_num = {$a_record_num}");

			$aux_body .= "<br />{$a_description}\n";
		}
        if ($aux_body != '') {
        	$body .= "<b>Supplier Parent Changes</b>";
        	$body .= $aux_body . "<br /><br /><br />";
		}
    }
    
    function SelectPreviousSubmittedLeads($state) {
		global $nowSql;
		$SQL = "SELECT region, leads_submitted FROM daily_summary WHERE submitted BETWEEN DATE_SUB({$nowSql}, INTERVAL 195 HOUR) AND DATE_SUB({$nowSql}, INTERVAL 165 HOUR) AND region='{$state}'";
		$previousResults = db_query($SQL);
		
		while ($row = mysqli_fetch_array($previousResults, MYSQLI_ASSOC)) {
			extract($row, EXTR_PREFIX_ALL, 's');
			
			return $s_leads_submitted;
		}
		
		return 0;
    }
    
    function SelectPreviousRunningTotals($state) {
		global $nowSql;
    	$returnValue = array(0, 0);
    	
		$SQL = "SELECT region, suppliers_per_submitted, suppliers_per_match FROM daily_summary WHERE submitted BETWEEN {$nowSql} - INTERVAL 9 DAY AND {$nowSql} - INTERVAL 1 DAY AND region='{$state}' ORDER BY submitted DESC LIMIT 7";
		$previousResults = db_query($SQL);
		while ($row = mysqli_fetch_array($previousResults, MYSQLI_ASSOC)) {
			extract($row, EXTR_PREFIX_ALL, 's');
			
			$returnValue[0] += ($s_suppliers_per_submitted / 7);
			$returnValue[1] += ($s_suppliers_per_match / 7);
		}
		
		$returnValue[0] = number_format($returnValue[0], 2);
		$returnValue[1] = number_format($returnValue[1], 2);
		
		return $returnValue;
    }
?>