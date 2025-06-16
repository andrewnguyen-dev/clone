<?php
	// This script needs to purge anything else fields from all leads older then 5 years.
	// To make life simple, this equats to leads.record_num <= 381223
	require("global.php");

	// THIS IS LIVE DATA
	#$start = 1;
	#$end = 1000;
	#$max = 381223;

	// THIS IS TEST4 DATA
	$start = 529000;
	$end = 750000;
	$max = 750001;

	$sql = "SELECT * FROM leads WHERE record_num BETWEEN ($start) AND ($end) ORDER BY record_num ASC";
	$leads = db_query($sql, "Error retrieving leads");

	if ($start > $end) {
		echo "ERROR: $start is greater then $end\n";
		exit;
	}

	if ($max <= $end) {
		echo "ERROR: $max is greater then $end\n";
		exit;
	}

	foreach ($leads as $lead) {
		$leadID = $lead['record_num'];
		$updated = false;

		if ($leadID > $max) {
			echo "ERROR: $leadID is greater then $max\n";
			exit;
		}

		$extra = unserialize(base64_decode($lead['extraDetails']));
		$site = unserialize(base64_decode($lead['siteDetails']));

		if(isset($extra['Anything Else Original:']) && $extra['Anything Else Original:'] != ''){
			$extra['Anything Else Original:'] = '';
			$updated = true;
		}
		if(isset($site['Anything Else:']) && $site['Anything Else:'] != ''){
			$site['Anything Else:'] = '';
			$updated = true;
		}

		$DBextra = ($extra === NULL || $extra == '') ? "''" : "'".base64_encode(serialize($extra))."'";
		$DBsite = ($site === NULL || $site == '') ? "''" : "'".base64_encode(serialize($site))."'";
		if($updated == true) {
			echo "Updating lead $leadID\n";

			$SQL = "UPDATE leads SET extraDetails = ".$DBextra.", siteDetails = ".$DBsite." where record_num = ".$leadID." LIMIT 1";
			db_query($SQL, "Error updating lead");
		}
	}
?>
