<?php
// load global libraries
include('global.php');
set_time_limit(0);
$debugging = 0;

// Fetch all leads that haven't been assigned to a supplier yet
$SQL = "SELECT * FROM lead_resend WHERE supplier = -1 LIMIT 5";
$resendLeads = db_query($SQL);

while ($resend = mysqli_fetch_array($resendLeads, MYSQLI_ASSOC)) {
	$matchedSupplier =  matchSupplier($resend['lead_id'], $resend['dispatchType']);
	if($matchedSupplier !== -1)
		db_query("UPDATE lead_resend SET supplier='{$matchedSupplier}', status = 'assigned' WHERE lead_id = '{$resend['lead_id']}';");
	else{
		db_query("DELETE FROM lead_resend WHERE record_num = {$resend['record_num']}");
	}
}

// Move all assigned resend leads to the lead_suppliers table
$SQL = "SELECT * FROM lead_resend WHERE supplier != -1";
$resendLeads = db_query($SQL);
while ($resend = mysqli_fetch_array($resendLeads, MYSQLI_ASSOC)) {
	moveAssignedLead($resend);
}


/**
 * Find a supplier that is able to receive the lead
 */
function moveAssignedLead($resend)
{
	global $_connection, $nowSql, $siteURLSSL;
	$leadData = loadLeadData($resend['lead_id']);
	$lead = mysqli_fetch_assoc(db_query("SELECT * FROM leads WHERE record_num = " . $resend['lead_id']));
	$supplier = mysqli_fetch_assoc(db_query("SELECT * FROM suppliers WHERE record_num = " . $resend['supplier']));
	extract($supplier, EXTR_PREFIX_ALL, 's');
	extract($lead, EXTR_PREFIX_ALL, 'l');

	db_query("INSERT INTO lead_suppliers SET lead_id='{$l_record_num}', type='regular', supplier='{$s_record_num}', dispatched={$nowSql}, status='sent', leadPrice=0, priceType='{$resend['priceType']}', resentQuote='Y';");

	$lsid = mysqli_insert_id($_connection);

	if ($lsid) {
		$resendId = $resend['record_num'];
		db_query("DELETE FROM lead_resend WHERE record_num = {$resendId}");
	}

	// send e-mail
	// Add our parsed content header into the leadData so it can be used when sending the out email
	$leadData['content_header'] = applyTemplate($resend['emailTemplate'], $leadData);
	// Now instruct a subject overwrite
	$leadData['content_header_subject'] = applyTemplate($resend['emailSubject'], $leadData);
	$data = array_merge($leadData, loadSupplierData($s_record_num));
	$data['rejectLink'] = $siteURLSSL . "leads/lead-reject.php?l={$lsid}&s={$s_record_num}&c=" . base64_encode($s_record_num . $l_email);

	// Default to the "Normal" email templates
	if ($s_verboseEmailSubject == 'Y')
		$emailTemplate = 'supplierQuoteVerbose';
	else
		$emailTemplate = 'supplierQuote';
		
	switch($resend['dispatchType']){
		case 'EV Charger':
			if ($s_verboseEmailSubject == 'Y')
				$emailTemplate = 'supplierQuoteVerboseEV';
			else
				$emailTemplate = 'supplierQuoteEV';
			break;
		case 'Commercial':
				$emailTemplate = 'supplierCommercialQuote';
			break;
	}

	$data['specialNotification'] = 'Free Lead: ';
	if ($s_csvattachment == 'Y') {
		$csvfile = GenerateLeadCSV($l_record_num, $s_record_num, ['Lead Type' => $resend['priceType']]);
		sendMailWithAttachment($s_email, "{$s_fName} {$s_lName}", $emailTemplate, $data, $csvfile);
		sendMailWithAttachment('johnb@solarquotes.com.au', "{$s_fName} {$s_lName}", $emailTemplate, $data, $csvfile);

		if ($s_emailcc != '') {
			$emails = explode(',', $s_emailcc);
			foreach ($emails as $emailcc){
				sendMailWithAttachment(trim($emailcc), "{$s_fName} {$s_lName}", $emailTemplate, $data, $csvfile);
			}
		}
	} else {
		sendTemplateEmail($s_email, "{$s_fName} {$s_lName}", $emailTemplate, $data);
		sendTemplateEmail('johnb@solarquotes.com.au', "{$s_fName} {$s_lName}", $emailTemplate, $data);
	}

	// Additional email to handle sms
	if ($s_mobile != '' && $s_sendMobile == 'Y') {
		$smsEmail = $s_mobile . '@sms.utbox.net';
		$smsEmail = preg_replace('/\s/', '', $smsEmail);

		$smsBody = "New SolarQuote lead - {$l_fName} {$l_lName} - {$l_iAddress} {$l_iCity} - {$l_phone}";
		$smsBody = substr($smsBody, 0, 1000);
		SendMail($smsEmail, "", "", $smsBody);
	}
}

function matchSupplier($leadId, $type){
	global $techEmail, $techName, $_connection;
	try {
		$areasServedPass = array("ultra", "standard");

		$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
		$extraConds = " AND record_num='{$leadId}' ";

		// Select the lead details
		$query = "SELECT *, {$extraCols} FROM leads WHERE 1 = 1 {$extraConds} ORDER BY record_num ASC";
		$r0 = db_query($query);
		$resend = mysqli_fetch_array($r0, MYSQLI_ASSOC);

		extract($resend, EXTR_PREFIX_ALL, 'l');
		$leadData = loadLeadData($l_record_num);
		$suppliers = loadPreviouslyMatchedSuppliers($l_record_num);
		$parents = $suppliers['parents'];
		$suppliers = $suppliers['suppliers'];

		$extraCondsSystem = "";
		$extraCondsQuote = "";
		$orderBy = "";

		switch($type){
			case 'Normal':
				foreach (unserialize(base64_decode($leadData['rawsystemDetails'])) as $a => $b) {
					if ($a == "System Size:" && $b != "")
						$extraCondsSystem = "AND SS.system_size = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					elseif ($a == "Features:") {
						$pos = strpos($b, "For a home that has not been built yet");
						$pos2 = strpos($b, "For a home that is currently under construction");
						$pos3 = strpos($b, "A home to begin construction soon - plans are available to the installer.");
						$pos4 = strpos($b, "A home currently under construction");
	
						if (($pos === false) && ($pos2 === false) && ($pos3 === false) && ($pos4 === false)) {
							$extraCondsQuote .= "AND (S.acceptUnbuiltHouse = 'N' OR S.acceptUnbuiltHouse = 'B') ";
						} else {
							$extraCondsQuote .= "AND (S.acceptUnbuiltHouse = 'Y' OR S.acceptUnbuiltHouse = 'B') ";
						}
	
						$pos = strpos($b, "Finance / Payment Plan");
						if ($pos === false) {
							$extraCondsQuote .= "AND (S.acceptFinance = 'N' OR S.acceptFinance = 'B') ";
						} else {
							$siteDetails = unserialize(base64_decode($leadData['rawsiteDetails']));
							if (stripos($siteDetails['Anything Else:'], 'Lead wants options for both cash and monthly instalments on the quote') !== false) {
								$extraCondsQuote .= "AND (S.acceptFinance = 'B') ";
							} else {
								$extraCondsQuote .= "AND (S.acceptFinance = 'Y' OR S.acceptFinance = 'B') ";
							}
						}
	
						$pos = strpos($b, "Off Grid / Remote Area System");
						if ($pos !== false) {
							$extraCondsQuote .= "AND S.offGrid = 'Y' ";
						}
	
						$pos = strpos($b, "On Grid Solar");
						if ($pos !== false) {
							$extraCondsQuote .= "AND S.onGridSolar = 'Y' ";
						}
	
						$pos = strpos($b, "Battery Ready");
						if ($pos !== false) {
							$extraCondsQuote .= "AND S.batteryReady = 'Y' ";
						}
	
						$pos = strpos($b, "Battery Storage System");
						$pos2 = strpos($b, "Hybrid System (Grid Connect with Batteries)");
						if (($pos !== false) || ($pos2 !== false)) {
							$extraCondsQuote .= "AND S.batteryStorage = 'Y' ";
						}
	
						$pos = strpos($b, "Adding Batteries");
						if ($pos !== false) {
							$extraCondsQuote .= "AND S.addBatteries = 'Y' ";
						}
	
						$pos = strpos($b, "Microinverters");
						$pos2 = strpos($b, "Micro Inverters or Power Optimisers");
						if (($pos !== false) || ($pos2 !== false)) {
							$extraCondsQuote .= "AND S.microInverters = 'Y' ";
						}
	
						$pos = strpos($b, "Upgrading an existing solar system");
						$pos2 = strpos($b, "Increase size of existing solar system");
						if (($pos !== false) || ($pos2 !== false)) {
							$extraCondsQuote .= "AND S.solarSystemUpgrade = 'Y' ";
						}
	
						$pos = stripos($b, "EV Charger");
						if ($pos !== false) {
							if (stripos($b, "battery") !== false)
								$extraCondsQuote .= "AND S.evChargersSolarBattery = 'Y' ";
							elseif (stripos($b, "solar") !== false)
								$extraCondsQuote .= "AND S.evChargersSolar = 'Y' ";
							else
								$extraCondsQuote .= "AND S.evChargers = 'Y' ";
						}
					}
				}
	
				foreach (unserialize(base64_decode($leadData['rawquoteDetails'])) as $a => $b) {
					if ($a == "Timeframe for purchase:") {
						if ($b == 'Timeframe unsure')
							$b = 'No solid time frame, just looking for a price';
	
						$extraCondsQuote .= "AND T.timeframe = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					} elseif ($a == "Asked for home visit?") {
						$extraCondsQuote .= "AND HV.home_visits = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					} elseif (($a == "Price Type:") && ($b != "")) {
						$extraCondsQuote .= "AND PT.price_type = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					} elseif ($a == 'Supplier preference size:') {
						$orderBy = $b;
					}
				}
	
				$extraCondsSite = "";
				foreach (unserialize(base64_decode($leadData['rawsiteDetails'])) as $a => $b) {
					$b = mysqli_real_escape_string($_connection, $b);
	
					switch ($a) {
						case "Type of Roof:":
							$extraCondsSite .= "AND RT.roof_type = '" . $b . "' ";
							break;
						case "How many storeys?":
							$extraCondsSite .= "AND HS.home_stories = '" . $b . "' ";
							break;
						case "Anything Else:":
							// Check VIC Rebates
							if ($leadData['iState'] == 'VIC' && stripos($b, 'would like to receive the VIC rebate') !== false) {
								// Check if user wants or doesn't want
								$extraCondsSite .= " AND S.vicRebate = 'Y' ";
							}
							break;
					}
				}
	
				// Reset the where clause
				$extraConds = $extraCondsSystem . $extraCondsQuote . $extraCondsSite;
				$orderBy = supplierSizeOrderBy($orderBy);
				// Check spreading order by
				$leadSpreading = leadSpreading($leadData, $orderBy);
				$orderBy = $leadSpreading['orderby'];
				$requestedInstallerEntitiesArray = getEntityIdsArray($leadData['requestedInstallerEntity']);
				$orderBy = orderByEntityIdsArray($requestedInstallerEntitiesArray, $orderBy);
				$extraConds .= $leadSpreading['conds'];
	
				// Grab the list of suppliers that served this list before and remove them from the possible suppliers this time around
				[$oldLeadsIds, $skipSuppliersList] = getInvalidSuppliersList($leadId);
				if (!empty($skipSuppliersList)) {
					$extraConds .= ' AND S.record_num NOT IN ( ' . implode(',', $skipSuppliersList) . ' ) ';
				}
	
				// Build the SQL to select the potential supplier details
				$SQL = "SELECT DISTINCT S.record_num, S.* FROM suppliers S ";
				$SQL .= "INNER JOIN suppliers_system_size SSS ON S.record_num = SSS.suppliers_record ";
				$SQL .= "INNER JOIN system_size SS ON SSS.system_size_record = SS.record_num ";
				$SQL .= "INNER JOIN suppliers_timeframe ST ON S.record_num = ST.suppliers_record ";
				$SQL .= "INNER JOIN timeframe T ON ST.timeframe_record = T.record_num ";
				$SQL .= "INNER JOIN suppliers_roof_type SRT ON S.record_num = SRT.supplier ";
				$SQL .= "INNER JOIN roof_type RT ON SRT.roof_type = RT.record_num ";
				$SQL .= "INNER JOIN suppliers_price_type SPT ON S.record_num = SPT.supplier_record ";
				$SQL .= "INNER JOIN price_type PT ON SPT.price_type_record = PT.record_num ";
				$SQL .= "INNER JOIN suppliers_home_stories SHS ON S.record_num = SHS.supplier ";
				$SQL .= "INNER JOIN home_stories HS ON SHS.home_stories = HS.record_num ";
				$SQL .= "INNER JOIN suppliers_home_visits SHV ON S.record_num = SHV.supplier ";
				$SQL .= "INNER JOIN home_visits HV ON SHV.home_visits = HV.record_num ";
				// Check for suppliers with needed matching pricing types
				$SQL .= "INNER JOIN suppliers_parent SP ON S.parent = SP.record_num ";
				$SQL .= "WHERE S.status='active' AND S.integrationResidential='Y' AND S.reviewonly='N' {$extraConds} ";
				$SQL .= $orderBy;
	
				$r = db_query($SQL);
	
				foreach ($areasServedPass as $areaServedPass) {
					// Reset the recordset
					mysqli_data_seek($r, 0);
	
					// Loop the suppliers
					while ($d = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
						extract($d, EXTR_PREFIX_ALL, 's');
						$supplierData = $d;
						if (in_array($s_record_num, $suppliers)) continue;
						if (in_array($s_parent, $parents)) continue; // never dispatch to more than 1 child of same parent
						$isRequestedInstaller = in_array($supplierData['entity_id'], $requestedInstallerEntitiesArray);
	
						// if current supplier is the one requested by the lead, we need to bypass
						// the areas category condition, so it matches even if the area is "standard"
						$areaCategoryCond = $isRequestedInstaller ? "" : " AND category = '{$areaServedPass}' ";
						$isValid = db_getVal("SELECT record_num FROM supplier_areas WHERE supplier='{$s_record_num}' AND type='state' AND details='{$l_iState}' AND status = 'active'" . $areaCategoryCond);
	
						// Check geometric areas
						if (!$isValid) {
							$r2 = db_query("SELECT * FROM supplier_areas WHERE supplier='{$s_record_num}' AND type!='state' AND status = 'active' " . $areaCategoryCond . " ORDER BY record_num ASC");
	
							while ($d = mysqli_fetch_array($r2, MYSQLI_ASSOC)) {
								extract($d, EXTR_PREFIX_ALL, 'a');
	
								switch ($a_type) {
									case 'circle':
										if (geoPointInCircleArea($l_latitude, $l_longitude, $a_details)) $isValid = 1;
										break;
									case 'drivingdistance':
										if (geoPointInDrivingDistance($l_latitude, $l_longitude, $a_details)) $isValid = 1;
										break;
									case 'polygon':
										if (geoPointInPolyArea($l_latitude, $l_longitude, $a_details)) $isValid = 1;
										break;
								}
	
								if ($isValid) break;
							}
						}
	
						// Check Postcode
						if (!$isValid) {
							// Is this supplier restricted to postcodes
							$isPostcodeEnabled = db_query("SELECT record_num FROM suppliers_postcode WHERE supplier_id='{$s_record_num}' " . $areaCategoryCond . " AND status = 'active'; ");
	
							if (mysqli_num_rows($isPostcodeEnabled) > 0) {
								// This supplier is bound by postcodes, check here
								$SQL = "SELECT P.record_num FROM postcode P ";
								$SQL .= "INNER JOIN suppliers_postcode SP ON P.record_num = SP.postcode_id ";
								$SQL .= "WHERE P.postcode = '{$l_iPostcode}' ";
								$SQL .= "AND (P.state = '{$l_iState}' OR P.state = '' OR P.state IS NULL) ";
								if (!$isRequestedInstaller)
									$SQL .= "AND SP.category = '{$areaServedPass}' ";
								$SQL .= "AND SP.status = 'active' ";
								$SQL .= "AND SP.supplier_id = '{$s_record_num}';";
	
								$Postcode = db_query($SQL);
	
								if (mysqli_num_rows($Postcode) > 0)
									$isValid = 1;
							}
						}
	
						if ($isValid && !in_array($s_record_num, $suppliers)) {
							return $s_record_num;
						}
					}
				}

				return -1;
			break;

			case 'EV Charger':
				$extraCondsSystem = "";
				$extraCondsQuote = "";
				$orderBy = "";

				foreach (unserialize(base64_decode($leadData['rawsystemDetails'])) as $a => $b) {
					$pos = stripos($b, "EV Charger");
					if ($pos !== false) {
						if (stripos($b, "battery") !== false)
							$extraCondsQuote .= "AND S.evChargersSolarBattery = 'Y' ";
						elseif (stripos($b, "solar") !== false)
							$extraCondsQuote .= "AND S.evChargersSolar = 'Y' ";
						else
							$extraCondsQuote .= "AND S.evChargers = 'Y' ";
					}
				}
	
				$extraCondsSite = "";
				foreach (unserialize(base64_decode($leadData['rawsiteDetails'])) as $a => $b) {
					$b = mysqli_real_escape_string($_connection, $b);
	
					if($a == 'How many storeys?'){
						$extraCondsSite .= "AND HS.home_stories = '" . $b . "' ";
					}
				}
	
				// Reset the where clause
				$extraConds = $extraCondsSystem . $extraCondsQuote . $extraCondsSite;
				$orderBy = supplierSizeOrderBy($orderBy);
				// Check spreading order by
				$leadSpreading = leadSpreading($leadData, $orderBy);
				$orderBy = $leadSpreading['orderby'];
				$requestedInstallerEntitiesArray = getEntityIdsArray($leadData['requestedInstallerEntity']);
				$orderBy = orderByEntityIdsArray($requestedInstallerEntitiesArray, $orderBy);
				$extraConds .= $leadSpreading['conds'];
				$isWithinSpreadingRegion = (array_key_exists('spreading_region_ids', $leadSpreading) && $leadSpreading['spreading_region_ids']!="");
				$internalCapsLoop = $isWithinSpreadingRegion ? [true,false] : [false];
	
				// Grab the list of suppliers that served this list before and remove them from the possible suppliers this time around
				[$oldLeadsIds, $skipSuppliersList] = getInvalidSuppliersList($leadId);
				if(!empty($skipSuppliersList)){
					$extraConds .= ' AND S.record_num NOT IN ( ' . implode(',', $skipSuppliersList) . ' ) ';
				}
	
				// Build the SQL to select the potential supplier details
				$SQL = "SELECT DISTINCT S.record_num, S.* FROM suppliers S ";
				$SQL .= "INNER JOIN suppliers_home_stories SHS ON S.record_num = SHS.supplier ";
				$SQL .= "INNER JOIN home_stories HS ON SHS.home_stories = HS.record_num ";
				// Check for suppliers with needed matching pricing types
				$SQL .= "INNER JOIN suppliers_parent SP ON S.parent = SP.record_num ";
				$SQL .= "WHERE S.status='active' AND S.integrationResidential='Y' AND S.reviewonly='N' {$extraConds} ";
				$SQL .= $orderBy;
	
				$r = db_query($SQL);
	
				// Caps loop (if within spreading region, first check internal/secondary caps, then normal caps)
				foreach($internalCapsLoop as $checkingInternalCaps) {
					// Loop the areas served category
					foreach ($areasServedPass AS $areaServedPass) {
						// Reset the recordset
						mysqli_data_seek($r, 0);
	
						// Loop the suppliers
						while ($d = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
							extract($d, EXTR_PREFIX_ALL, 's');
							$supplierData = $d;
							if(in_array($s_record_num, $suppliers)) continue;
							if(in_array($s_parent, $parents)) continue; // never dispatch to more than 1 child of same parent
							$isRequestedInstaller = in_array($supplierData['entity_id'], $requestedInstallerEntitiesArray);
	
							// if current supplier is the one requested by the lead, we need to bypass
							// the areas category condition, so it matches even if the area is "standard"
							$areaCategoryCond = $isRequestedInstaller ? "" : " AND category = '{$areaServedPass}' ";
							$isValid = db_getVal("SELECT record_num FROM supplier_areas WHERE supplier='{$s_record_num}' AND type='state' AND details='{$l_iState}' AND status = 'active'" . $areaCategoryCond);
	
							// Check geometric areas
							if (!$isValid) {
								$r2 = db_query("SELECT * FROM supplier_areas WHERE supplier='{$s_record_num}' AND type!='state' AND status = 'active' ". $areaCategoryCond ." ORDER BY record_num ASC");
	
								while ($d = mysqli_fetch_array($r2, MYSQLI_ASSOC)) {
									extract($d, EXTR_PREFIX_ALL, 'a');
	
									switch ($a_type) {
										case 'circle': if (geoPointInCircleArea($l_latitude, $l_longitude, $a_details)) $isValid = 1; break;
										case 'drivingdistance': if (geoPointInDrivingDistance($l_latitude, $l_longitude, $a_details)) $isValid = 1; break;
										case 'polygon': if (geoPointInPolyArea($l_latitude, $l_longitude, $a_details)) $isValid = 1; break;
									}
	
									if ($isValid) break;
								}
							}
	
							// Check Postcode
							if (!$isValid) {
								// Is this supplier restricted to postcodes
								$isPostcodeEnabled = db_query("SELECT record_num FROM suppliers_postcode WHERE supplier_id='{$s_record_num}' ". $areaCategoryCond ." AND status = 'active'; ");
	
								if (mysqli_num_rows($isPostcodeEnabled) > 0) {
									// This supplier is bound by postcodes, check here
									$SQL = "SELECT P.record_num FROM postcode P ";
									$SQL .= "INNER JOIN suppliers_postcode SP ON P.record_num = SP.postcode_id ";
									$SQL .= "WHERE P.postcode = '{$l_iPostcode}' ";
									$SQL .= "AND (P.state = '{$l_iState}' OR P.state = '' OR P.state IS NULL) ";
									if(!$isRequestedInstaller)
										$SQL .= "AND SP.category = '{$areaServedPass}' ";
									$SQL .= "AND SP.status = 'active' ";
									$SQL .= "AND SP.supplier_id = '{$s_record_num}';";
	
									$Postcode = db_query($SQL);
	
									if (mysqli_num_rows($Postcode) > 0)
										$isValid = 1;
								}
							}
	
							if ($isValid && !in_array($s_record_num, $suppliers)) {
								return $s_record_num;
							}
						}
					}
				}
				return -1;
				break;
			case 'Commercial':
				$extraConds = '';
				foreach (unserialize(base64_decode($leadData['rawquoteDetails'])) as $a => $b) {
					if ($a == "Timeframe for purchase:")
						$extraConds .= "AND T.timeframe = '" . mysqli_real_escape_string($_connection, $b) . "' ";
				}
	
				foreach (unserialize(base64_decode($leadData['rawsystemDetails'])) as $a => $b) {
					if ($a == "Features:") {
						$pos = strpos($b, "Finance / Payment Plan");
						if ($pos === false) {
							$extraConds .= "AND (S.acceptFinance = 'N' OR S.acceptFinance = 'B') ";
						} else {
							$siteDetails = unserialize(base64_decode($leadData['rawsiteDetails']));
							if(stripos($siteDetails['Anything Else:'], 'Lead wants options for both cash and monthly instalments on the quote') !== false){
								$extraConds .= "AND (S.acceptFinance = 'B') ";
							} else {
								$extraConds .= "AND (S.acceptFinance = 'Y' OR S.acceptFinance = 'B') ";
							}
						}
					}
				}

				// Check spreading order by
				$leadSpreading = leadSpreading($leadData);
				$orderBy = $leadSpreading['orderby'];
				$requestedInstallerEntitiesArray = getEntityIdsArray($leadData['requestedInstallerEntity']);
				$orderBy = orderByEntityIdsArray($requestedInstallerEntitiesArray, $orderBy);
				$extraConds .= $leadSpreading['conds'];
				$isWithinSpreadingRegion = (array_key_exists('spreading_region_ids', $leadSpreading) && $leadSpreading['spreading_region_ids']!="");
				$internalCapsLoop = $isWithinSpreadingRegion ? [true,false] : [false];
	
				// Grab the list of suppliers that served this list before and remove them from the possible suppliers this time around
				[$oldLeadsIds, $skipSuppliersList] = getInvalidSuppliersList($leadId);
				if(!empty($skipSuppliersList)){
					$extraConds .= ' AND S.record_num NOT IN ( ' . implode(',', $skipSuppliersList) . ' ) ';
				}
	
				// Build the SQL to select the potential supplier details
				$SQL = "SELECT DISTINCT S.record_num, S.* FROM suppliers S ";
				$SQL .= "INNER JOIN suppliers_timeframe ST ON S.record_num = ST.suppliers_record ";
				$SQL .= "INNER JOIN timeframe T ON ST.timeframe_record = T.record_num ";
				$SQL .= "WHERE S.status='active' AND S.reviewonly='N' AND S.integrationCommercial='Y' ";
				$SQL .= $extraConds;
				$SQL .= $orderBy;
	
				$r = db_query($SQL);
	
				// Caps loop (if within spreading region, first check internal/secondary caps, then normal caps)
				foreach($internalCapsLoop as $checkingInternalCaps) {
					mysqli_data_seek($r, 0);
					// Loop the suppliers
					while ($d = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
						$supplierData = $d;
						$isValid = 0;
	
						extract($d, EXTR_PREFIX_ALL, 's');
						if(in_array($s_record_num, $suppliers)) continue;
						if(in_array($s_parent, $parents)) continue;
	
						// Is this supplier restricted to postcodes
						$isPostcodeEnabled = db_query("SELECT record_num FROM suppliers_postcode WHERE supplier_id='{$s_record_num}' AND status = 'active'");
	
						if (mysqli_num_rows($isPostcodeEnabled) > 0) {
							// This supplier is bound by postcodes, check here
							$SQL = "SELECT P.record_num FROM postcode P ";
							$SQL .= "INNER JOIN suppliers_postcode SP ON P.record_num = SP.postcode_id ";
							$SQL .= "WHERE P.postcode = '{$l_iPostcode}' ";
							$SQL .= "AND (P.state = '{$l_iState}' OR P.state = '' OR P.state IS NULL) ";
							$SQL .= "AND SP.status = 'active' ";
							$SQL .= "AND SP.supplier_id = '{$s_record_num}';";
	
							$Postcode = db_query($SQL);
	
							if (mysqli_num_rows($Postcode) > 0)
								$isValid = 1;
						}
	
						if (!$isValid) {
							$SQL = "SELECT COUNT(*) FROM supplier_areas WHERE type = 'state' AND details = '{$l_iState}' AND supplier = '{$s_record_num}' ";
							$count = db_getVal($SQL);
	
							if ($count > 0)
								$isValid = 1;
						}
	
						if (!$isValid) {
							$SQL = "SELECT * FROM supplier_areas WHERE supplier='{$s_record_num}' AND type!='state' AND status = 'active' ORDER BY record_num ASC";
							$areas = db_query($SQL);
	
							while ($area = mysqli_fetch_array($areas, MYSQLI_ASSOC)) {
								extract($area, EXTR_PREFIX_ALL, 'a');
	
								switch ($a_type) {
									case 'circle': if (geoPointInCircleArea($l_latitude, $l_longitude, $a_details)) $isValid = 1; break;
									case 'drivingdistance': if (geoPointInDrivingDistance($l_latitude, $l_longitude, $a_details)) $isValid = 1; break;
									case 'polygon': if (geoPointInPolyArea($l_latitude, $l_longitude, $a_details)) $isValid = 1; break;
								}
	
								if ($isValid) break;
							}
						}
	
						if ($isValid && !in_array($s_record_num, $suppliers)) {
							return $s_record_num;
						}
					}
				}
				return -1;
				break;
		}

		return -1;
	} catch (Throwable $e) {
		SendMail($techEmail, $techName, "Error", $e->getMessage());
	}
}