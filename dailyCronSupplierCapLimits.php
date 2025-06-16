<?php
	// load global libraries
	include('global.php');
	set_time_limit(0);

	$SQL = "SELECT * FROM suppliers ORDER BY record_num ASC";
	$suppliers = db_query($SQL);

	while ($supplier = mysqli_fetch_array($suppliers, MYSQLI_ASSOC)) {
		extract(htmlentitiesRecursive($supplier), EXTR_PREFIX_ALL, 's');

		if($s_status == 'active'){ // Only active suppliers should be checked for "normal" limits
			// Check the limits - Non Claim
			$limits = db_query(" SELECT cap_id, max, length, title, type FROM supplier_cap_limit SCL INNER JOIN cap_limit_type CLT ON SCL.cap_id = CLT.record_num WHERE supplier_id = {$s_record_num} AND SCL.report = 'Y' AND CLT.is_claim = 'N' AND CLT.is_internal = 'N' ;");
			$cap_limits = [];
			$elements = [];
			while($row = mysqli_fetch_assoc($limits)){
				if($row['type']=='commercial') // if it's a commercial cap limit, only count commercial leads
					$commercial_or_residential = " AND ls.priceType LIKE 'commercial' ";
				else
					$commercial_or_residential = " AND ls.priceType NOT LIKE 'commercial' ";
				// 0 - Is unlimited
				if($row['max'] != 0 && ! in_array($row['title'], ['Daily Leads', 'Weekly Leads', 'Monthly Leads'])){
					$elements["cap_{$row['cap_id']}"] = "SUM(CASE WHEN l.submitted > ( NOW() - INTERVAL {$row['length']} HOUR) {$commercial_or_residential} then 1 else 0 end) as cap_{$row['cap_id']} ";
					$cap_limits["cap_{$row['cap_id']}"] = [
						'limit' => $row['max'],
						'title' => $row['title']
					];
				} else {
					$limit_name = str_replace(' ', '_', strtolower($row['title']))."_".$row['type'];
					if(stripos($limit_name, 'daily') !== false){
						$elements["cap_".$limit_name] = "SUM(CASE WHEN DATE_FORMAT(l.submitted, '%Y%m%d') = DATE_FORMAT(NOW(), '%Y%m%d') {$commercial_or_residential} then 1 else 0 end) as cap_{$limit_name} ";
					} elseif(stripos($limit_name, 'weekly') !== false){
						$elements["cap_".$limit_name] = "SUM(CASE WHEN DATE_FORMAT(l.submitted, '%Y%u') = DATE_FORMAT(NOW(), '%Y%u') {$commercial_or_residential} then 1 else 0 end) as cap_{$limit_name} ";
					} else {
						$elements["cap_".$limit_name] = "SUM(CASE WHEN DATE_FORMAT(l.submitted, '%Y%m') = DATE_FORMAT(NOW(), '%Y%m') {$commercial_or_residential} then 1 else 0 end)  as cap_{$limit_name}  ";
					}
					$cap_limits["cap_".$limit_name] = [
						'limit' => $row['max'],
						'title' => $row['title'],
						'is_commercial' => ($row['type'] == 'commercial')
					];
				}
			}

			if(! empty($elements)){
				$cap_by_limit = db_query(" SELECT " . implode(', ', $elements) . " FROM leads l INNER JOIN lead_suppliers ls ON ls.lead_id = l.record_num WHERE ls.supplier = {$s_record_num} AND ls.type='regular' AND ls.status!='scrapped' AND ls.extraLead = 'N' GROUP BY ls.supplier; ");
				$caps = mysqli_fetch_assoc($cap_by_limit);
				if(is_array($caps)){
					foreach($caps as $key=>$lead_count){
						$cap = $cap_limits[$key];
						if($lead_count >= $cap['limit']){
							ExecuteReport($s_record_num, $s_company, $s_email, $s_fName, $s_lName, $cap['limit'], $lead_count, $cap['title'], $cap['is_commercial']);
						}
					}
				}
			}
		}

		if($s_extraLeads == 'Y'){ // If supplier has extraLeads assigned to him, always run the claim limits
			// Check the limits - Claims
			$limits = db_query(" SELECT cap_id, max, length, title FROM supplier_cap_limit SCL INNER JOIN cap_limit_type CLT ON SCL.cap_id = CLT.record_num WHERE supplier_id = {$s_record_num} AND SCL.report = 'Y' AND CLT.is_claim = 'Y' AND CLT.is_internal = 'N' ;");
			$cap_limits = [];
			$elements = [];
			while($row = mysqli_fetch_assoc($limits)){
				// 0 - Is unlimited
				extract($row, EXTR_PREFIX_ALL, 'c');
				if($row['max'] != 0){
					$elements["cap_{$row['cap_id']}"] = "SUM(CASE WHEN date > DATE_FORMAT(( NOW() - INTERVAL {$row['length']} HOUR), '%Y%m%d') then sent else 0 end) as cap_{$row['cap_id']} ";
					$cap_limits["cap_{$row['cap_id']}"] = [
						'limit' => $row['max'],
						'title' => $row['title']
					];
				}
			}

			if(! empty($elements)){
				$cap_by_limit = db_query(" SELECT " . implode(', ', $elements) . " FROM log_leads_claimed WHERE supplier = {$s_record_num} GROUP BY supplier; ");

				$caps = mysqli_fetch_assoc($cap_by_limit);
				if(is_array($caps)){
					foreach($caps as $key=>$lead_count){
						$cap = $cap_limits[$key];
						if($lead_count >= $cap['limit']){
							ExecuteReport($s_record_num, $s_company, $s_email, $s_fName, $s_lName, $cap['limit'], $lead_count, $cap['title']);
						}
					}
				}
			}
		}
	}

	function ExecuteReport($supplierID, $company, $email, $firstName, $lastName, $limit, $lead_count, $title, $isCommercial = false) {
		global $adminPAEmail, $adminPAName;

		$tables = "lead_suppliers AS ls LEFT JOIN leads AS l ON l.record_num=ls.lead_id";
		$Conds = "AND l.submitted >= NOW() - INTERVAL 1 DAY";
		$SQL = "SELECT COUNT(*) FROM {$tables} WHERE ls.type='regular' AND ls.status!='scrapped' AND ls.supplier='{$supplierID}' {$Conds}";

		$total = db_getVal($SQL);

		// If there's leads sent in the last day, then send the email out
		if ($total >= 1) {
			// Only send out the email if the last lead was within 24 hours
			$subject = ($isCommercial?"Commercial ":"")."Cap Limit Reached For {$company}";

			$message = "Hi {$firstName},<br /><br />";
			$message .= "This is a courtesy email to say that your cap {$title}, of {$limit} SolarQuotes ".($isCommercial?"commercial ":"")."leads has now been reached.  If you want to change this cap (or you don't want to receive an email when you reach your cap) please either reply to this email or give me a call.<br /><br />";
			$message .= "Cheers,<br />";
			$message .= "Robert Moffa<br />";
			$message .= "Client Operations<br /><br />";
			$message .= "M: 0434 193 199<br />";
			$message .= "E: robert@solarquotes.com.au<br /><br />";
			SendMail($email, "{$firstName} {$lastName}", $subject, $message, $adminPAEmail, $adminPAName);
		}
	}
?>