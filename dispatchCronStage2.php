<?php
	// load global libraries
	include('global.php');

	global $nowSql, $siteURLSSL, $zenDeskSubdomain, $zenDeskEmail, $zenDeskKey, $zenDeskAssignees, $sg_use_autoresponder;

	$expireTime = businessHoursAgo($claimExpireHours);
	set_time_limit(0);
	$debugging = 0;

	// Select Manual Lead Counts Filters
	$SQL = " SELECT dispatch_manual_lead_conditions FROM settings; ";
	$leadCountPerState = json_decode(db_getVal($SQL));

	foreach($leadCountPerState as $state => $leadCountFilters){
		$leadCountFilters = implode(',', array_filter($leadCountFilters, function($val){
			return $val !== "-1";
		}));

		//Move all leads that have all slots filled OR have expired, to status "sending"
		// There's no Manual Lead Filters set - Default
		$SQL  = " UPDATE leads SET status = 'sending' WHERE record_num in ( SELECT record_num FROM ( SELECT l.record_num FROM leads l LEFT JOIN lead_claims lc ON l.record_num = lc.lead_id WHERE l.status = 'waiting' AND l.iState = '{$state}' GROUP BY l.record_num HAVING COUNT(lc.record_num) >= MAX(l.requestedQuotes) OR min(l.updated) <= '{$expireTime->format('Y-m-d H:i:s')}' ) t );";
		if($leadCountFilters != ''){ // Don't set as sending any Manual Lead Filters Matches
			$SQL  = " UPDATE leads SET status = 'sending' WHERE record_num in ( SELECT record_num FROM ( SELECT l.record_num FROM leads l LEFT JOIN lead_claims lc ON l.record_num = lc.lead_id WHERE l.status = 'waiting' AND l.iState = '{$state}' GROUP BY l.record_num HAVING COUNT(lc.record_num) >= MAX(l.requestedQuotes) OR ( min(l.updated) <= '{$expireTime->format('Y-m-d H:i:s')}' AND COUNT(lc.record_num) NOT IN(".$leadCountFilters.") ) ) t );";
		}

		db_query($SQL);
	}

	$SQL = " SELECT record_num FROM leads WHERE status = 'waiting' AND openClaims = 'N' AND TIMESTAMPDIFF(MINUTE, updated, ".$nowSql.") >= 10 ORDER BY record_num ASC LIMIT 10";

	$leads = db_query($SQL);

	while ($lead = mysqli_fetch_array($leads, MYSQLI_ASSOC)) {
		extract($lead, EXTR_PREFIX_ALL, 'l');

		if ($sg_use_autoresponder == false)
			AddLeadToGR($l_record_num, 'sq_incomplete');
		else
			AddLeadToSG($l_record_num, 'sq_incomplete');
		
		fillSlots($l_record_num);
	}

	// Initialize Zendesk client outside of the loop
	$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
	$client->setAuth('token', $zenDeskKey);

	foreach($leadCountPerState as $state => $leadCountFilters){
		$leadCountFilters = implode(',', array_filter($leadCountFilters, function($val){
			return $val !== "-1";
		}));

		if($leadCountFilters != ''){
			// Select All leads that match the Lead Manual Filters - update to "manual" and create ZenDesk ticket
			// If it's a failed manual attempt (run manual lead again through the dispatch but didn't fill all the quotes), ignore the expire time and set it as manual
			$SQL = " 
				SELECT l.record_num, l.requestedQuotes, l.fName, l.lName, l.email, l.leadType, count(lc.record_num) as supplier_count, l.manualAttempts, l.notes, l.updated
				FROM leads l LEFT JOIN lead_claims lc ON l.record_num = lc.lead_id WHERE l.status = 'waiting' AND l.iState = '{$state}' 
				GROUP BY l.record_num HAVING COUNT(lc.record_num) IN(".$leadCountFilters.") 
				AND (
					min(l.updated) <= '{$expireTime->format('Y-m-d H:i:s')}' 
					OR ( min(l.manualAttempts) > 0 AND min(l.openClaims)='Y' )
				)";

			$manualSupplierLeads = db_query($SQL);

			while ($manualSupplierLead = mysqli_fetch_array($manualSupplierLeads, MYSQLI_ASSOC)) {

				/* This will only happen when the lead is being run through the overnight dispatch (try to dispatch again claim leads and manual leads)
					If the lead was claimable when dispatchManualsCron.php was run, it can't be set as manual now, so we keep it as waiting and also set the updated date to what it was before. Otherwise this lead would expire later than it was supposed to
				*/
				$notes = $manualSupplierLead['notes'];
				$wasClaim = strpos($notes, 'Lead has been run through the dispatch a second time, from a claim status') !== false;
				if ($wasClaim) {
					$startUpdated = strpos($notes, 'The first time was at: ') + strlen('The first time was at: ');
					$previousUpdated = explode("\n", substr($notes,$startUpdated))[0];
					$previousUpdatedDT = new \DateTime($previousUpdated);
					if($previousUpdatedDT > $expireTime) { /* If the lead hasn't expired yet */
						if($previousUpdated != $manualSupplierLead['updated']){ /* If the updated date is already equals we don't need to set it again */
							$SQL = " UPDATE leads SET updated = '$previousUpdated' WHERE record_num = " . $manualSupplierLead['record_num'];
							db_query($SQL);							
						}
						continue; // Don't set as manual yet (the claim hasn't expired)
					}
				}				

				$subject = sprintf("Lead requested %d quotes but got " . $manualSupplierLead['supplier_count'], $manualSupplierLead['requestedQuotes']);
				$body = sprintf(
					"Lead Link: <a href='%sleads/lead_view?lead_id=%d&manual=true'>%d</a><br />Lead Type: %s",
					$siteURLSSL, $manualSupplierLead['record_num'], $manualSupplierLead['record_num'], $manualSupplierLead['leadType']
				);
				$notificationTargetByState = json_decode(db_getVal('SELECT dispatch_v3_notifications FROM settings'), true);
				
				if($state !== '')
					list($stateNotification, $userid) = $notificationTargetByState[$state];
				else { // If state is empty assign the ticket to Rino
					$stateNotification = "0"; $userid = 108;
				}

				// Don't notify via zendesk if it's a manual attempt (it was already notified before). 
				// However, if it's a manual attempt but the lead had claim status, the ticket for this lead was never raised, so raise it
				if($stateNotification === "0" && ($manualSupplierLead['manualAttempts'] == 0 || $wasClaim)){
					try {
						$response = $client->users()->createOrUpdate([
							'name' => $manualSupplierLead['fName'] . ' ' . $manualSupplierLead['lName'],
							'email' => $manualSupplierLead['email'],
							'verified' => true
						]);

						$agent_id = db_getVal('SELECT zendeskid FROM admins WHERE record_num = ' . $userid);

						// Create a new ticket
						$newTicket = $client->tickets()->create([
							'subject' => $subject,
							'comment' => [
								'html_body' => $body,
								'public' => false
							],
							'priority' => 'high',
							'requester_id' => $response->user->id,
							'assignee_id' => $agent_id
						]);
					} catch (Exception $e) {
						echo $e->getMessage();
					}
				}
					

				// Move lead status to manual and remove from global_data ( Not claimable anymore)
				$SQL = " UPDATE leads SET status = 'manual' WHERE record_num = " . $manualSupplierLead['record_num'];
				db_query($SQL);

				$SQL = "DELETE FROM global_data WHERE type = 'supplier' AND name = 'claimleads' AND description like '%\"lead\":\"{$manualSupplierLead['record_num']}\"%'";
				db_query($SQL);
			}
		}
	}

	/**
	* Returns how many hours ago $max was
	*
	* @param int $max - Numbers of hours ago to retrieve the business hour past moment
	*/
	function businessHoursAgo($max){
		global $nowSql;
		$old_tz = date_default_timezone_get();
		date_default_timezone_set('Australia/Adelaide');
		$now = new \DateTime(db_getVal("SELECT {$nowSql} as now"));
		$past = new \DateTime(db_getVal("SELECT DATE_SUB({$nowSql}, INTERVAL {$max} HOUR) as past"));
		$minTime = clone $now;
		$maxTime = clone $now;
		$businessHours = [
			'min' => 9,
			'max' => 17
		];
		$businessHours['minTime'] = $minTime->setTime($businessHours['min'], 0, 0);
		$businessHours['maxTime'] = $maxTime->setTime($businessHours['max'], 0, 0);

		// Check if the past date is in a non-weekday, if so set it on a friday
		if(isPublicHoliday($past)){
			// If it's a public holiday set the past day a day before
			$past->modify("-1 day");
		}

		$return = '';

		// If both $past ( -Max hours ) and $now are inside the business hours range, return $past hour
		if($past->format('H') >= $businessHours['min'] && $past->format('H') <= $businessHours['max']
			&& $now->format('H') >= $businessHours['min'] && $now->format('H') <= $businessHours['max']){
			$return = $past;
		} elseif($now->format('H') >= $businessHours['min'] && $now->format('H') <= $businessHours['max']){
			// If the runtime hour is between business hours but the past MAX hours ago doesn't
			// fall into a valid hour ( previous condition)
			if(isPublicHoliday($now)){
				$now->setTime($businessHours['min'],0,0);
			}
			$diffMin = $now->diff($businessHours['minTime']);
			$diffMin = $businessHours['minTime']->diff($now);
			$_aux = new \DateTime("{$max}:00:00");
			$_aux->sub($diffMin);
			$dt = new \DateInterval("PT".$_aux->format('H\Hi\Ms\S'));
			do{
				$businessHours['maxTime']->modify('-1 day');
			}while(isPublicHoliday($businessHours['maxTime']));
			$businessHours['maxTime']->sub($dt);
			$return = $businessHours['maxTime'];
		} elseif($now->format('H') < $businessHours['min']){
			// If the runtime hour is before the initial business hour
			do{
				$businessHours['maxTime']->modify('-1 day');
			}while(isPublicHoliday($businessHours['maxTime']));
			$return = $businessHours['maxTime']->modify("-{$max} hours");
		} elseif($now->format('H') > $businessHours['max']){
			// If the runtime hour is after the final business hour
			while(isPublicHoliday($businessHours['maxTime'])){
				$businessHours['maxTime']->modify('-1 day');
			}
			$return = $businessHours['maxTime']->modify("-{$max} hours");
		}
		date_default_timezone_set($old_tz);
		return $return;
	}

	function isPublicHoliday($date){
		global $publicHolidays;
		if(in_array($date->format('Y-m-d'), $publicHolidays))
			return true; // Public holidays work as a saturday so that it only decrements 1 day ( Sunday is 2 days to get a friday)
		return false;
	}
?>
