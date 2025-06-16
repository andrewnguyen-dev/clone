<?php

		function suspendLeadDispatchForAudit($leadId) {
		global $techEmail, $techName, $zenDeskSubdomain, $zenDeskEmail, $zenDeskKey, $siteURLSSL;
		
		$settings = db_query("SELECT manual_postcode_auditing,manual_audit_postcodes,manual_postcode_agent FROM settings")->fetch_row();
		$enabled = $settings[0];
		$forAudit = $settings[1];
		$agent_id = $settings[2];

		if($enabled[0] != 1 || $forAudit == null || $forAudit == "" || $agent_id == null || $agent_id == "")
			return false; // Manual auditing disabled
		
		$forAudit = explode(",",$forAudit);
		$forAudit = array_map('trim', $forAudit);
		$forAudit = array_map(function ($item) { // Make sure NT postcodes have the leading 0
			return str_pad($item, 4, "0", STR_PAD_LEFT);
			},$forAudit);

		$leadpc = db_query("SELECT iPostcode FROM leads WHERE record_num = '{$leadId}'")->fetch_row();

		if($leadpc !== null && $leadpc !== ""){
			$leadpc = str_pad($leadpc[0], 4, "0", STR_PAD_LEFT);
			$found = array_search($leadpc,$forAudit);
			if($found !== false){
				$suspendUntil = businessHoursFromNow(2)->format('Y-m-d H:i:s');
				db_query("UPDATE leads SET suspendedUntil = '".$suspendUntil."' WHERE record_num='{$leadId}'");

				$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
				$client->setAuth('token', $zenDeskKey);

				$subject = "Lead dispatch suspended for audit - #".$leadId;

				$body = "<p><a href=\"{$siteURLSSL}leads/lead_view?lead_id={$leadId}\">Lead {$leadId}</a> dispatch has been <strong>suspended</strong> for the next two business hours, because it was matched with postcode <strong>". $forAudit[$found] ."</strong>.</p>";
				$body .= "<p><br>The lead will only be dispatched after the two business hours, or when you click this link: <a style='font-weight:bold' href=\"{$siteURLSSL}leads/dispatch_audits/?release_lead={$leadId}\">Release Lead Now</a>.</p>";
				$body .= "<p><br>Or click <a href=\"{$siteURLSSL}leads/lead_view/?lead_id={$leadId}\">View Lead</a> to view the lead details and add notes.</p>";

				// Create a new ticket
				$ticket = $client->tickets()->create([
					'tags' => ['lead-dispatch-suspended'],
					'subject' => $subject,
					'comment' => [
						'html_body' => $body,
						'public' => true
					],
					'priority' => 'high',
					'assignee_id' => $agent_id,
					'requester_id' => $agent_id
				]);
				return true;
			}
		}
		return false;
	}

	function businessHoursFromNow($hoursToSum, $timestamp = false){
		$businessHours = ['min' => 9,'max' => 17];
		global $nowSql;
		$old_tz = date_default_timezone_get();
		date_default_timezone_set('Australia/Adelaide');
		if($timestamp)
			$now = new \DateTime($timestamp);
		else
			$now = new \DateTime(db_getVal("SELECT {$nowSql} as now"));

		if(!isBusinessHours($now, $businessHours))
			$now = nextOpening($now, $businessHours);

		$timeToClose = (nextClosing(clone $now, $businessHours))->diff($now);
		$minutesToClose = $timeToClose->i + ($timeToClose->h * 60);
		$minutesToSum = 60*$hoursToSum;
		if($minutesToClose < $minutesToSum)
			return nextOpening($now, $businessHours)->modify('+ '.($minutesToSum - $minutesToClose).' minutes');

		date_default_timezone_set($old_tz);
		return $now->modify('+ '.$hoursToSum.' hours');
	}

	function isBusinessHours($date, $businessHours) {
		global $publicHolidays;
		if(in_array($date->format('Y-m-d'), $publicHolidays)) // Public holiday
			return false;
		if($date->format('N') >= 6) // 6 (Saturday) or 7 (Sunday)
			return false;
		if($date->format('G')>=$businessHours['max'] || $date->format('G')<$businessHours['min'])
			return false;
		return true;
	}

	function nextOpening($date, $businessHours) {
		if($date->format('G') >= $businessHours['min'])
			$date->modify('+1 day');
		$date = $date->setTime($businessHours['min'], 0, 0);
		while(!isBusinessHours($date, $businessHours)) {
			$date->modify('+1 day');
		}
		return $date;
	}

	function nextClosing($date, $businessHours) {
		if(isBusinessHours($date, $businessHours))
			return $date->setTime($businessHours['max'], 0, 0);
		else
			return nextOpening($date, $businessHours)->setTime($businessHours['max'], 0, 0);
	}