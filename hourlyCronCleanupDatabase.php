<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

    include('global.php');

    $SQL = "DELETE FROM autologins WHERE ts < NOW() - INTERVAL 1 WEEK";
    db_query($SQL);
    
    $SQL = "DELETE FROM global_data WHERE name = 'claimleads' AND created < NOW() - INTERVAL 1 WEEK";
    db_query($SQL);

	/**
	* Purge all old lead data.  This is due to privacy concerns.
	*/
	$SQL = "UPDATE leads SET lName = LEFT(lName, 1), iAddress = '', mAddress = '', latitude = '', 
		longitude = '', phone = '', email = '', notes = '', supplierNotes = '', adminNotesForSupplier = '', reviewioLink = ''
		WHERE created < NOW() - INTERVAL 5 YEAR";
	db_query($SQL);
	$SQL = "UPDATE evcharger_reviews SET lName = LEFT(lName, 1), phone = '', email = ''
		WHERE review_date < NOW() - INTERVAL 5 YEAR";
	db_query($SQL);
	$SQL = "UPDATE battery_reviews SET lName = LEFT(lName, 1), phone = '', email = ''
		WHERE review_date < NOW() - INTERVAL 5 YEAR";
	db_query($SQL);
	$SQL = "UPDATE feedback SET lName = LEFT(lName, 1), iAddress = '', phone = '', email = ''
		WHERE feedback_date < NOW() - INTERVAL 5 YEAR";
	db_query($SQL);
	$SQL = "UPDATE lead_sms SET phone = '' WHERE sent < NOW() - INTERVAL 5 YEAR AND phone != ''";
	db_query($SQL);
	$SQL = "UPDATE bulk_sms_leads SET phone = '' WHERE sent < NOW() - INTERVAL 5 YEAR AND phone != ''";
	db_query($SQL);
	$SQL = "DELETE FROM global_data WHERE type = 'pendingfeedbackeditrequest' AND created < NOW() - INTERVAL 5 YEAR";
	db_query($SQL);
	$SQL = "DELETE FROM log_leads WHERE created < NOW() - INTERVAL 5 YEAR";
	db_query($SQL);
	$SQL = "DELETE FROM log_lead_autoresponders WHERE created < NOW() - INTERVAL 5 YEAR";
	db_query($SQL);
	$SQL = "UPDATE leads_reminded SET email = '' WHERE submitted < NOW() - INTERVAL 5 YEAR AND email != ''";
	db_query($SQL);
	$SQL = "UPDATE shopify_sales SET last_name = LEFT(last_name, 1), email = '', phone = '', contactEmail = ''
		WHERE created < NOW() - INTERVAL 5 YEAR";
	db_query($SQL);

	/**
	* Purge Anything else and Anything else original from old lead data.  This is due to privacy concerns.
	*/
	$SQL = "SELECT record_num, extraDetails, siteDetails FROM leads
		WHERE created BETWEEN DATE_SUB(DATE_SUB(NOW(), INTERVAL 5 YEAR), INTERVAL 2 DAY) 
		AND DATE_SUB(NOW(), INTERVAL 5 YEAR)";
	try{
		$r = db_query($SQL);
		while ($supplierData = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
			$updated = false;
			$extra = ($supplierData['extraDetails'] === NULL || $supplierData['extraDetails'] =='') ? NULL : unserialize(base64_decode($supplierData['extraDetails']));
			$site = ($supplierData['siteDetails'] === NULL || $supplierData['siteDetails'] =='') ? NULL :unserialize(base64_decode($supplierData['siteDetails']));
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
			if($updated == true){
				$SQL = "UPDATE leads SET extraDetails = ".$DBextra.", siteDetails = ".$DBsite." where record_num = ".$supplierData['record_num']." LIMIT 1";
				db_query($SQL);
			}
		}
	} catch(Exception $e){
		echo $e->getMessage();
	}

    /**
    *  Update all licenses that have been checked more than 30 days ago or never where checked before
    * Also skip the ones already marked for manualChecking
    */
    $SQL = 'UPDATE entity_supplier_electrical_licenses SET waitingManualVerification = "Y" WHERE ( checked IS NULL OR checked < NOW() - INTERVAL 30 DAY ) AND waitingManualVerification != "Y"';
    db_query($SQL);

	/**
	*  Delete all pending sms verifications from the global_data table that are older than 24 hours
	*/
	$SQL = "DELETE FROM global_data WHERE name = 'mobileverify' AND created < NOW() - INTERVAL 24 HOUR";
	db_query($SQL);

	/**
	*  Delete all API logs that are older then 6 months
	*/
	$SQL = "DELETE FROM log_supplier_api WHERE submitted < NOW() - INTERVAL 6 MONTH";
	db_query($SQL);
	$SQL = "DELETE FROM log_supplier_parent_api WHERE submitted < NOW() - INTERVAL 6 MONTH";
	db_query($SQL);

	/**
	*  Delete incomplete leads older than 7 days (including linked tables)
	*/
	$query = db_query("SELECT * FROM leads WHERE leads.status = 'incomplete' AND leads.leadType NOT IN ('Repair Residential','Repair Commercial') AND leads.submitted < DATE_SUB(NOW(), INTERVAL 7 DAY)");
	$incompleteOldLeads = [];
	$refererRows = [];
	while ($row = mysqli_fetch_assoc($query)) {
		if($row['record_num']) {
			$incompleteOldLeads[] = $row['record_num'];
			// Add a record to lead_deleted table before deleting
			$data = getLeadJsonData($row);
			db_query("INSERT INTO lead_deleted (lead_id, duration, created, lead_data) VALUES ('{$row['record_num']}', '{$row['duration']}', '{$row['created']}', '$data')");
		}
		db_query("DELETE FROM global_data WHERE name = 'claimleads' AND description LIKE '%\"lead\":\"".$row['record_num']."\"%'");
		if(!is_null($row['referer']))
			$refererRows[] = $row['referer'];
	}
	if(count($incompleteOldLeads) > 0) {
		$leadList = "(" . implode(",", $incompleteOldLeads) . ")";

		db_query("DELETE FROM lead_finance WHERE lead_id IN ".$leadList);
		db_query("DELETE FROM lead_claims WHERE lead_id IN ".$leadList);
		db_query("DELETE FROM leads_spam WHERE lead_id IN ".$leadList);
		db_query("DELETE feedback, feedback_files, feedback_images FROM feedback LEFT JOIN feedback_files ON feedback.record_num = feedback_files.feedback_id LEFT JOIN feedback_images ON feedback.record_num = feedback_images.feedback WHERE feedback.lead_id IN ".$leadList);
		db_query("DELETE FROM lead_suppliers WHERE lead_id IN ".$leadList);
		db_query("DELETE FROM leads WHERE record_num IN ".$leadList);
		if(count($refererRows) > 0) {
			$refererList = "(" . implode(",", $refererRows) . ")";
			db_query("DELETE FROM leads_referers WHERE record_num IN ".$refererList);
		}

		if((db_getVal("SELECT count(*) FROM daily_summary_deleted"))>0) { // Update summary
			db_query("UPDATE daily_summary_deleted SET delete_count = delete_count + ".count($incompleteOldLeads));
		}
		else {	// Summary table is empty, insert as new row
			db_query("INSERT INTO daily_summary_deleted (delete_count) VALUES (".count($incompleteOldLeads).")");
		}
	}

	/**
	*  Delete all records from sent_emails older then 1 year
	*/
	$SQL = "DELETE FROM sent_emails WHERE created < NOW() - INTERVAL 1 YEAR";
	db_query($SQL);

	/**
	*  Empty the conetnt for sent_emails records older then 3 months
	*/
	$SQL = "UPDATE sent_emails SET content = '' WHERE created < NOW() - INTERVAL 3 MONTH";
	db_query($SQL);

	/**
	*  Delete all records from lead_claims older then 7 days
	*/
	$SQL = "DELETE FROM lead_claims WHERE claimed < NOW() - INTERVAL 7 DAY";
	db_query($SQL);

	/**
	 * Delete all Supplier In Area cache older than 160 days
	 */
	$SQL = "DELETE FROM supplier_in_area_cache WHERE created_at < NOW() - INTERVAL 160 DAY;";
	db_query($SQL);


	function getLeadJsonData($leadData) {
		unset($leadData['fName']);
		unset($leadData['lName']);
		unset($leadData['iAddress']);
		unset($leadData['email']);
		unset($leadData['phone']);

		// Remove Anything else from lead data, but keep its length as it may be useful for ML
		$siteDetails = unserialize(base64_decode($leadData['siteDetails']));
		$leadData['anythingElseLength'] = strlen($siteDetails['Anything Else:']);
		if($siteDetails['Anything Else:'] != "")
			unset($siteDetails['Anything Else:']);
		$leadData['siteDetails'] = base64_encode(serialize($siteDetails));

		return addslashes(json_encode($leadData));
	}

?>
