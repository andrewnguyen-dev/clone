<?php
	function trustPilotGenerateReviewURL($leadID) {
		GLOBAL $trustPilot;
		
		try {
		    $lead = loadLeadData($leadID);
		    
		    $a = $lead['record_num'];
		    $b = base64_encode($lead['email']);
		    $c = urlencode($lead['fName'] . ' ' . $lead['lName']);
		    $e = sha1($trustPilot . $lead['email'] . $lead['record_num']);

			$url = "https://au.trustpilot.com/evaluate/solarquotes.com.au?a={$a}&b={$b}&c={$c}&e={$e}";
			
			return $url;
		} catch (Exception $e) {
			return "";	
		}
	}
?>