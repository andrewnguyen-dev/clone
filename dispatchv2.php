<?php
        include('dispatchAudits.php');
        function doDispatch($leadId = 0) {
		global $techEmail, $techName, $_connection;
		try {
			global $nowSql, $siteURLSSL;

			$areasServedPass = array("ultra", "standard");

			$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
			if ($leadId) $extraConds = "AND record_num='{$leadId}' "; else $extraConds = '';

			// Select the lead details
			$r0 = db_query("SELECT *, {$extraCols} FROM leads WHERE status='pending' {$extraConds} ORDER BY record_num ASC");

			// While loop for the lead, this should only occur once
			while ($d = mysqli_fetch_array($r0, MYSQLI_ASSOC)) {
				extract($d, EXTR_PREFIX_ALL, 'l');
				$leadData = loadLeadData($l_record_num);
				$suppliers = loadPreviouslyMatchedSuppliers($l_record_num);
				$parents = $suppliers['parents'];
				$suppliers = $suppliers['suppliers'];

				// Update the lead status now
				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");
				if (count($suppliers) >= $l_requestedQuotes) break;

				$extraCondsSystem = "";
				$extraCondsQuote = "";
				$orderBy = "";
				$pricing_types = leadPricingOptions($leadData);

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

						$siteDetails = unserialize(base64_decode($leadData['rawsiteDetails']));
						$pos = strpos($b, "Finance / Payment Plan");
						if ($pos === false) {
							$extraCondsQuote .= "AND (S.acceptFinance = 'N' OR S.acceptFinance = 'B') ";
						} else {
							if(stripos($siteDetails['Anything Else:'], 'Lead wants options for both cash and monthly instalments on the quote') !== false){
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

						$pos = stripos($b, "Hot water heat pump");
						if ($pos !== false) {
							if (stripos($b, "battery") !== false)
								$extraCondsQuote .= "AND S.hwhpSolarBattery = 'Y' ";
							elseif (stripos($b, "solar") !== false)
								$extraCondsQuote .= "AND S.hwhpSolar = 'Y' ";
							else
								$extraCondsQuote .= "AND S.hwhp = 'Y' ";
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
							if($leadData['iState'] == 'VIC' && stripos($b, 'would like to receive the VIC rebate') !== false){
								// Check if user wants or doesn't want
								$extraCondsSite .= " AND S.vicRebate = 'Y' ";
							}
						break;
					}
				}
				$extraConds .= " AND matched_pricing = " . count($pricing_types);	

				if (isNSWRebateEligible($leadData, $pricing_types)) {
					$extraCondsQuote .= "AND (S.acceptNSWBatteryRebate = 'Y') ";
				}

				// Check if supplier handles Origin leads
				if ($leadData['originLead'] == 'Y') {
					$extraCondsQuote .= "AND S.acceptOriginLead = 'Y' ";
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
				$SQL .= "INNER JOIN ( ";
				$SQL .= "	SELECT entity_id, COUNT(*) AS matched_pricing FROM entity_supplier_pricing esp ";
				$SQL .= "	WHERE pricing_type IN ('" . implode("','", $pricing_types ) . "') ";
				$SQL .= "	GROUP BY entity_id ";
				$SQL .= ") ESP "; 
				$SQL .= "ON ESP.entity_id = ( CASE WHEN S.parentUseInvoice = 'Y' AND parent > 1 THEN SP.entity_id ELSE S.entity_id END )";
				$SQL .= "WHERE S.status='active' AND S.integrationResidential='Y' AND S.reviewonly='N' {$extraConds} ";
				$SQL .= $orderBy;				

				$r = db_query($SQL);

				// Caps loop (if within spreading region, first check internal/secondary caps, then normal caps)
				foreach($internalCapsLoop as $checkingInternalCaps) { 
					if (count($suppliers) >= $l_requestedQuotes) break;
					mysqli_data_seek($r, 0);
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
							// "choice" referer is the only reason to skip (normal/primary) caps check
							$checkNormalCaps = !($supplierData['uncappedChoiceLeads']=='Y' && $leadData['isChoice']);

							if($checkNormalCaps || $checkingInternalCaps) {
								$CLTfilter = " AND CLT.is_internal='".($checkingInternalCaps ? 'Y':'N')."'";
									
								// Check the limits - Non Claim
								$limits = db_query(" SELECT cap_id, max, length, title FROM supplier_cap_limit SCL INNER JOIN cap_limit_type CLT ON SCL.cap_id = CLT.record_num WHERE supplier_id = {$s_record_num} AND is_claim = 'N' AND type = 'residential' {$CLTfilter};");

								if($checkingInternalCaps && mysqli_num_rows($limits)==0)	// On the caps loop first execution 
									continue;						// (spreading region) we want only installers with internal caps

								// First check global limits
								if(checkSupplierCapLimits($limits, $s_record_num)){
									continue;
								}			

								// New version for cap limits per leadType - Returns a true if it goes over the lead type cap
								if(supplierIsCapLimited($supplierData, $leadData)){
									continue;
								}
							}

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
								$suppliers[] = $s_record_num;
								if ($s_parent != 1)
									$parents[] = $s_parent;
								list($priceType, $leadPrice) = array_values(leadPricingInfo($supplierData, $leadData));
								db_query("INSERT INTO lead_claims SET lead_id='{$l_record_num}', supplier='{$s_record_num}', claimed={$nowSql}, priceType = '{$priceType}', leadPrice = '{$leadPrice}';");
								$lsid = mysqli_insert_id($_connection);
								if (count($suppliers) == $l_requestedQuotes) break;
							}
						}

						if (count($suppliers) == $l_requestedQuotes) break;
					}
				}

				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");
				if($l_manualAttempts < 1) { /* If it's a manual attempt this query has already been executed */
					if($isWithinSpreadingRegion) {
						foreach(explode(",", $leadSpreading['spreading_region_ids']) as $spreading_region_id) {
							db_query("INSERT INTO lead_dispatch_regions (lead_id, dispatch_region_id) VALUES ('{$l_record_num}', '{$spreading_region_id}')");
						}
					}
					addNoteSkippedSuppliers($suppliers, $oldLeadsIds, $l_record_num);
				}
				checkIfRequestedSupplierWasMatched($requestedInstallerEntitiesArray, $suppliers, $l_record_num);
			}
		} catch (Throwable $e) {
			SendMail($techEmail, $techName, "Error - doDispatch: " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
		}
	}

	function doDispatchCommercial($leadId = 0) {
		global $techEmail, $techName, $_connection;
		try {
			global $nowSql, $siteURLSSL;
			$suppliersEmailArray = array();

			$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
			if ($leadId) $extraConds = "AND record_num='{$leadId}' "; else $extraConds = '';

			// Select the lead details
			$r0 = db_query("SELECT *, {$extraCols} FROM leads WHERE status='pending' {$extraConds} ORDER BY record_num ASC");

			// While loop for the lead, this should only occur once
			while ($d = mysqli_fetch_array($r0, MYSQLI_ASSOC)) {
				extract($d, EXTR_PREFIX_ALL, 'l');
				$leadData = loadLeadData($l_record_num);

				$extraConds = "";

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

				// Update the lead status now
				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");

				$suppliers = loadPreviouslyMatchedSuppliers($l_record_num);
				$parents = $suppliers['parents'];
				$suppliers = $suppliers['suppliers'];
				if (count($suppliers) >= $l_requestedQuotes) break;

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
					if (count($suppliers) >= $l_requestedQuotes) break;
					mysqli_data_seek($r, 0);
					// Loop the suppliers
					while ($d = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
						$supplierData = $d;
						$isValid = 0;

						extract($d, EXTR_PREFIX_ALL, 's');
						if(in_array($s_record_num, $suppliers)) continue;
						if(in_array($s_parent, $parents)) continue;
						// "choice" referer is the only reason to skip (normal/primary) caps check
						$checkNormalCaps = !($supplierData['uncappedChoiceLeads']=='Y' && $leadData['isChoice']);

						if($checkNormalCaps || $checkingInternalCaps) {
							$CLTfilter = " AND CLT.is_internal='".($checkingInternalCaps ? 'Y':'N')."'";

							// Check the limits - Non Claim
							$limits = db_query(" SELECT cap_id, max, length, title FROM supplier_cap_limit SCL INNER JOIN cap_limit_type CLT ON SCL.cap_id = CLT.record_num WHERE supplier_id = {$s_record_num} AND is_claim = 'N' AND type = 'commercial'  {$CLTfilter};");
							
							if($checkingInternalCaps && mysqli_num_rows($limits)==0)	// On the caps loop first execution 
								continue;						// (spreading region) we want only installers with internal caps

							// First check global limits
							if(checkSupplierCapLimits($limits, $s_record_num, ['limitType' => 'commercial'])){
								continue;
							}			

							// New version for cap limits per leadType - Returns a true if it goes over the lead type cap
							if(supplierIsCapLimited($supplierData, $leadData)){
								continue;
							}
						}

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
							$suppliersEmailArray[] = formatSupplierForCommercial($s_record_num);

							$suppliers[] = $s_record_num;
							if ($s_parent != 1)
								$parents[] = $s_parent;
							list($priceType, $leadPrice) = array_values(leadPricingInfo($supplierData, $leadData));

							db_query("INSERT INTO lead_claims SET lead_id='{$l_record_num}', supplier='{$s_record_num}', claimed={$nowSql}, priceType = '{$priceType}', leadPrice = '{$leadPrice}';");

							$lsid = mysqli_insert_id($_connection);
							if (count($suppliers) == $l_requestedQuotes) break;
						}
					}
				}

				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");
				if($l_manualAttempts < 1) { /* If it's a manual attempt this query has already been executed */
					if($isWithinSpreadingRegion) {
						foreach(explode(",", $leadSpreading['spreading_region_ids']) as $spreading_region_id) {
							db_query("INSERT INTO lead_dispatch_regions (lead_id, dispatch_region_id) VALUES ('{$l_record_num}', '{$spreading_region_id}')");
						}
					}
					addNoteSkippedSuppliers($suppliers, $oldLeadsIds, $l_record_num);
				}
				checkIfRequestedSupplierWasMatched($requestedInstallerEntitiesArray, $suppliers, $l_record_num);
			}
		} catch (Throwable $e) {
			SendMail($techEmail, $techName, "Error - doDispatchCommercial: " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
		}
	}

	function doDispatchMobile($leadId = 0) {
		global $techEmail, $techName, $_connection;
		try {
			global $nowSql, $siteURLSSL;

			$suppliers = array();
			$parents = array();
			$areasServedPass = array("ultra", "standard");

			$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
			if ($leadId) $extraConds = "AND record_num='{$leadId}' "; else $extraConds = '';

			// Select the lead details
			$r0 = db_query("SELECT *, {$extraCols} FROM leads WHERE status='pending' {$extraConds} ORDER BY record_num ASC");

			// While loop for the lead, this should only occur once
			while ($d = mysqli_fetch_array($r0, MYSQLI_ASSOC)) {
				extract($d, EXTR_PREFIX_ALL, 'l');
				$leadData = loadLeadData($l_record_num);

				// Update the lead status now
				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");

				$suppliers = loadPreviouslyMatchedSuppliers($l_record_num);
				$parents = $suppliers['parents'];
				$suppliers = $suppliers['suppliers'];
				if (count($suppliers) >= $l_requestedQuotes) break;

				$extraCondsSystem = "";
				$extraCondsQuote = "";

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

						$siteDetails = unserialize(base64_decode($leadData['rawsiteDetails']));
						$pos = strpos($b, "Finance / Payment Plan");
						if ($pos === false) {
							$extraCondsQuote .= "AND (S.acceptFinance = 'N' OR S.acceptFinance = 'B') ";
						} else {
							if(stripos($siteDetails['Anything Else:'], 'Lead wants options for both cash and monthly instalments on the quote') !== false){
								$extraCondsQuote .= "AND (S.acceptFinance = 'B') ";	
							} else {
								$extraCondsQuote .= "AND (S.acceptFinance = 'Y' OR S.acceptFinance = 'B') ";	
							}
						}

						$pos = strpos($b, "Hybrid System (Grid Connect with Batteries)");
						if ($pos !== false) {
							$extraCondsQuote .= "AND S.batteryStorage != 'N' ";
						}

						$pos = strpos($b, "On Grid Solar");
						if ($pos !== false) {
							$extraCondsQuote .= "AND S.onGridSolar != 'N' ";
						}

						$pos = strpos($b, "Battery Ready");
						if ($pos !== false) {
							$extraCondsQuote .= "AND S.batteryReady = 'Y' ";
						}

						$pos = strpos($b, "Off Grid / Remote Area System");
						if ($pos !== false) {
							$extraCondsQuote .= "AND S.offGrid = 'Y' ";
						}

						$pos = strpos($b, "Adding Batteries");
						if ($pos !== false) {
							$extraCondsQuote .= "AND S.addBatteries = 'Y' ";
						}

						$pos = strpos($b, "Increase size of existing solar system");
						if ($pos !== false) {
							$extraCondsQuote .= "AND S.solarSystemUpgrade = 'Y' ";
						}

						$pos = strpos($b, "Microinverters");
						$pos2 = strpos($b, "Micro Inverters or Power Optimisers");
						if (($pos !== false) || ($pos2 !== false)) {
							$extraCondsQuote .= "AND S.microInverters = 'Y' ";
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

						$pos = stripos($b, "Hot water heat pump");
						if ($pos !== false) {
							if (stripos($b, "battery") !== false)
								$extraCondsQuote .= "AND S.hwhpSolarBattery = 'Y' ";
							elseif (stripos($b, "solar") !== false)
								$extraCondsQuote .= "AND S.hwhpSolar = 'Y' ";
							else
								$extraCondsQuote .= "AND S.hwhp = 'Y' ";
						}
					}
				}

				foreach (unserialize(base64_decode($leadData['rawquoteDetails'])) as $a => $b) {
					if ($a == "Timeframe for purchase:")
						$extraCondsQuote .= "AND T.timeframe = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					elseif (($a == "Price Type:") && ($b != ""))
						$extraCondsQuote .= "AND PT.price_type = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					elseif ($a == "Asked for home visit?") {
						$extraCondsQuote .= "AND HV.home_visits = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					}
				}

				// Aditional filters can either be Y or No so ignore

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
							if($leadData['iState'] == 'VIC' && stripos($b, 'would like to receive the VIC rebate') !== false){
								// Check if user wants or doesn't want
								$extraCondsSite .= " AND S.vicRebate = 'Y' ";
							}
						break;
					}
				}

				$pricing_types = leadPricingOptions($leadData);

				$extraConds .= " AND matched_pricing = " . count($pricing_types);

				if (isNSWRebateEligible($leadData, $pricing_types)) {
					$extraCondsQuote .= "AND (S.acceptNSWBatteryRebate = 'Y') ";
				}

				// Check if supplier handles Origin leads
				if ($leadData['originLead'] == 'Y') {
					$extraCondsQuote .= "AND S.acceptOriginLead = 'Y' ";
				}

				// Reset the where clause
				$extraConds = $extraCondsSystem . $extraCondsQuote . $extraCondsSite;
				$orderBy = 'ORDER BY S.priority ASC; ';
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
				$SQL .= "INNER JOIN suppliers_system_size SSS ON S.record_num = SSS.suppliers_record ";
				$SQL .= "INNER JOIN system_size SS ON SSS.system_size_record = SS.record_num ";
				$SQL .= "INNER JOIN suppliers_timeframe ST ON S.record_num = ST.suppliers_record ";
				$SQL .= "INNER JOIN timeframe T ON ST.timeframe_record = T.record_num ";
				$SQL .= "INNER JOIN suppliers_roof_type SRT ON S.record_num = SRT.supplier ";
				$SQL .= "INNER JOIN roof_type RT ON SRT.roof_type = RT.record_num ";
				$SQL .= "INNER JOIN suppliers_home_stories SHS ON S.record_num = SHS.supplier ";
				$SQL .= "INNER JOIN home_stories HS ON SHS.home_stories = HS.record_num ";
				$SQL .= "INNER JOIN suppliers_home_visits SHV ON S.record_num = SHV.supplier ";				
				$SQL .= "INNER JOIN home_visits HV ON SHV.home_visits = HV.record_num ";
				$SQL .= "INNER JOIN suppliers_price_type SPT ON S.record_num = SPT.supplier_record ";
				$SQL .= "INNER JOIN price_type PT ON SPT.price_type_record = PT.record_num ";
				// Check for suppliers with needed matching pricing types
				$SQL .= "INNER JOIN suppliers_parent SP ON S.parent = SP.record_num ";
				$SQL .= "INNER JOIN ( ";
				$SQL .= "	SELECT entity_id, COUNT(*) AS matched_pricing FROM entity_supplier_pricing esp ";
				$SQL .= "	WHERE pricing_type IN ('" . implode("','", $pricing_types ) . "') ";
				$SQL .= "	GROUP BY entity_id ";
				$SQL .= ") ESP "; 
				$SQL .= "ON ESP.entity_id = ( CASE WHEN S.parentUseInvoice = 'Y' AND parent > 1 THEN SP.entity_id ELSE S.entity_id END )";
				$SQL .= "WHERE S.status='active' AND S.mobileLeads='Y' AND S.integrationResidential='Y' AND S.reviewonly='N' {$extraConds} ";
				$SQL .= $orderBy;


				$r = db_query($SQL);

				// Check what type of cap limits should be applied to this lead
				if($leadData['leadType'] == 'Commercial'){
					$limitType = 'commercial';
				} else {
					$limitType = 'residential';
				}

				// Caps loop (if within spreading region, first check internal/secondary caps, then normal caps)
				foreach($internalCapsLoop as $checkingInternalCaps) {
					if (count($suppliers) >= $l_requestedQuotes) break;
					mysqli_data_seek($r, 0);
					// Loop the areas served category
					foreach ($areasServedPass AS $areaServedPass) {
						// Reset the recordset
						mysqli_data_seek($r, 0);

						// Loop the suppliers
						while ($d = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
							extract($d, EXTR_PREFIX_ALL, 's');
							if(in_array($s_record_num, $suppliers)) continue;
							if(in_array($s_parent, $parents)) continue;
							$supplierData = $d;
							$isRequestedInstaller = in_array($supplierData['entity_id'], $requestedInstallerEntitiesArray);
							// "choice" referer is the only reason to skip (normal/primary) caps check
							$checkNormalCaps = !($supplierData['uncappedChoiceLeads']=='Y' && $leadData['isChoice']);

							if($checkNormalCaps || $checkingInternalCaps) {
								$CLTfilter = " AND CLT.is_internal='".($checkingInternalCaps ? 'Y':'N')."'";
									
								// Check the limits - Non Claim
								$limits = db_query(" SELECT cap_id, max, length, title FROM supplier_cap_limit SCL INNER JOIN cap_limit_type CLT ON SCL.cap_id = CLT.record_num WHERE supplier_id = {$s_record_num} AND is_claim = 'N' AND type = '{$limitType}' {$CLTfilter};");
								
								if($checkingInternalCaps && mysqli_num_rows($limits)==0)	// On the caps loop first execution 
									continue;						// (spreading region) we want only installers with internal caps

								// First check global limits
								if(checkSupplierCapLimits($limits, $s_record_num)){
									continue;
								}
								
								// New version for cap limits per leadType - Returns a true if it goes over the lead type cap
								if(supplierIsCapLimited($supplierData, $leadData)){
									continue;
								}
							}

							// if current supplier is the one requested by the lead, we need to bypass
							// the areas category condition, so it matches even if the area is "standard"
							$areaCategoryCond = $isRequestedInstaller ? "" : " AND category = '{$areaServedPass}' ";
							$isValid = db_getVal("SELECT record_num FROM supplier_areas WHERE supplier='{$s_record_num}' AND type='state' AND details='{$l_iState}' AND status = 'active'" . $areaCategoryCond);

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
								$suppliers[] = $s_record_num;
								if ($s_parent != 1)
									$parents[] = $s_parent;
								list($priceType, $leadPrice) = array_values(leadPricingInfo($supplierData, $leadData));

								db_query("INSERT INTO lead_claims SET lead_id='{$l_record_num}', supplier='{$s_record_num}', claimed={$nowSql}, priceType = '{$priceType}', leadPrice = '{$leadPrice}';");
								$lsid = mysqli_insert_id($_connection);

								if (count($suppliers) == $l_requestedQuotes) break;
							}
						}

						if (count($suppliers) == $l_requestedQuotes) break;
					}
				}
				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");
				if($l_manualAttempts < 1) { /* If it's a manual attempt this query has already been executed */
					if($isWithinSpreadingRegion) {
						foreach(explode(",", $leadSpreading['spreading_region_ids']) as $spreading_region_id) {
							db_query("INSERT INTO lead_dispatch_regions (lead_id, dispatch_region_id) VALUES ('{$l_record_num}', '{$spreading_region_id}')");
						}
					}
					addNoteSkippedSuppliers($suppliers, $oldLeadsIds, $l_record_num);
				}

				checkIfRequestedSupplierWasMatched($requestedInstallerEntitiesArray, $suppliers, $l_record_num);
			}
		} catch (Throwable $e) {
			SendMail($techEmail, $techName, "Error - doDispatchMobile: " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
		}
	}

	function doDispatchReuseRoofSlope($roofSlope) {
		$returnValue = $roofSlope;

		if ($returnValue == 'Medium: 18 to 40 degrees')
			$returnValue = 'Medium: 18 to 45 degrees';
		elseif ($returnValue = 'Steep: over 40 degrees')
			$returnValue = 'Steep: over 45 degrees';

		return $returnValue;
	}

	function doDispatchReuseRoofType($roofType) {
		$returnValue = $roofType;

		if ($returnValue == 'Unknown')
			$returnValue = 'Other';

		return $returnValue;
	}

	function doDispatchReuse($leadId = 0) {
		global $techEmail, $techName, $_connection, $sg_use_autoresponder;
		try {
			global $nowSql, $siteURLSSL;

			$suppliers = array();
			$parents = array();
			$areasServedPass = array("ultra", "standard");

			$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
			if ($leadId) $extraConds = "AND record_num='{$leadId}' "; else $extraConds = '';

			// Select the lead details
			$r0 = db_query("SELECT *, {$extraCols} FROM leads WHERE status='pending' {$extraConds} ORDER BY record_num ASC");

			// While loop for the lead, this should only occur once
			while ($d = mysqli_fetch_array($r0, MYSQLI_ASSOC)) {
				// First step is to resend the leads to any suppliers that have not been liquidated or blacklisted
				$SQL = "SELECT * FROM lead_suppliers LS ";
				$SQL .= "INNER JOIN suppliers S ON LS.supplier = S.record_num ";
				$SQL .= "WHERE LS.lead_id = {$leadId} AND S.liquidated = 'N' AND S.status = 'active' ";
				$SQL .= "AND LS.type = 'regular' ";
				$result = db_query($SQL);

				while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
					extract($row, EXTR_PREFIX_ALL, 'l');

					$leadData = loadLeadData($leadId);
					$data = array_merge($leadData, loadSupplierData($l_supplier));

					// Update the database, this lead is not to be invoiced
					db_query("UPDATE lead_suppliers SET dispatched = {$nowSql}, Invoice = 'N' WHERE lead_id = {$leadId} AND supplier = {$l_supplier};");

					// Send email
					sendTemplateEmail($l_email, $l_fName, 'reusedLead', $data);

					$suppliers[] = $l_supplier;
					$parent = loadSupplierData($l_supplier)['parent'];
					if($parent != 1)
						$parents[] = $parent;
				}

				// Now we execute the primary (but modified) dispatch
				if (count($suppliers) < 3) {
					extract($d, EXTR_PREFIX_ALL, 'l');
					$leadData = loadLeadData($l_record_num);

					// Update the lead status now
					db_query("UPDATE leads SET status='dispatched' WHERE record_num='{$l_record_num}'");

					$extraCondsQuote = "";
					$extraCondsSite = "";
					$extraCondsSystem = "";

					foreach (unserialize(base64_decode($leadData['rawsystemDetails'])) as $a => $b) {
						if ($a == "Features:") {
							$pos = strpos($b, "For a home that has not been built yet");
							$pos2 = strpos($b, "For a home that is currently under construction");
							if (($pos === false) && ($pos2 === false)) {
								$extraCondsQuote .= "AND (S.acceptUnbuiltHouse = 'N' OR S.acceptUnbuiltHouse = 'B') ";
							} else {
								$extraCondsQuote .= "AND (S.acceptUnbuiltHouse = 'Y' OR S.acceptUnbuiltHouse = 'B') ";
							}

							$pos = strpos($b, "Finance / Payment Plan");
							if ($pos === false) {
								$extraCondsQuote .= "AND (S.acceptFinance = 'N' OR S.acceptFinance = 'B') ";
							} else {
								$extraCondsQuote .= "AND (S.acceptFinance = 'Y' OR S.acceptFinance = 'B') ";
							}

							$pos = strpos($b, "Off Grid / Remote Area System");
							if ($pos !== false) {
								$extraCondsQuote .= "AND S.offGrid != 'N' ";
							}

							$pos = strpos($b, "Battery Storage System");
							$pos2 = strpos($b, "Hybrid System (Grid Connect with Batteries)");
							if (($pos !== false) || ($pos2 !== false)) {
								$extraCondsQuote .= "AND S.batteryStorage != 'N' ";
							}

							$pos = strpos($b, "Adding Batteries");
							if ($pos !== false) {
								$extraCondsQuote .= "AND S.addBatteries != 'N' ";
							}

							$pos = strpos($b, "Microinverters");
							$pos2 = strpos($b, "Micro Inverters or Power Optimisers");
							if (($pos !== false) || ($pos2 !== false)) {
								$extraCondsQuote .= "AND S.microInverters != 'N' ";
							}

							$pos = strpos($b, "On Grid Solar");
							if ($pos !== false) {
								$extraCondsQuote .= "AND S.onGridSolar != 'N' ";
							}

							$pos = strpos($b, "Battery Ready");
							if ($pos !== false) {
								$extraCondsQuote .= "AND S.batteryReady != 'N' ";
							}
						}
					}

					foreach (unserialize(base64_decode($leadData['rawquoteDetails'])) as $a => $b) {
						$extraCondsQuote .= "AND T.timeframe = '" . mysqli_real_escape_string($_connection, $b) . "' ";
						break;
					}

					foreach (unserialize(base64_decode($leadData['rawsiteDetails'])) as $a => $b) {
						$b = mysqli_real_escape_string($_connection, $b);

						switch ($a) {
							case "Type of Roof:":
								$extraCondsSite .= "AND RT.roof_type = '" . doDispatchReuseRoofType($b) . "' ";
								break;
							case "How many storeys?":
								$extraCondsSite .= "AND HS.home_stories = '" . $b . "' ";
								break;
						}
					}

					// Reset the where clause
					$extraConds = $extraCondsSystem . $extraCondsQuote . $extraCondsSite;

					// Build the SQL to select the potential supplier details
					$SQL = "SELECT DISTINCT S.record_num, S.* FROM suppliers S ";
					$SQL .= "INNER JOIN suppliers_system_size SSS ON S.record_num = SSS.suppliers_record ";
					$SQL .= "INNER JOIN suppliers_timeframe ST ON S.record_num = ST.suppliers_record ";
					$SQL .= "INNER JOIN timeframe T ON ST.timeframe_record = T.record_num ";
					$SQL .= "INNER JOIN suppliers_roof_type SRT ON S.record_num = SRT.supplier ";
					$SQL .= "INNER JOIN roof_type RT ON SRT.roof_type = RT.record_num ";
					$SQL .= "INNER JOIN suppliers_home_stories SHS ON S.record_num = SHS.supplier ";
					$SQL .= "INNER JOIN home_stories HS ON SHS.home_stories = HS.record_num ";
					$SQL .= "WHERE S.status='active' AND S.reviewonly='N' {$extraConds} AND S.integrationResidential='Y' AND S.reusedLeads='Y' AND S.liquidated='N' ";
					$SQL .= "ORDER BY S.priority ASC; ";

					$r = db_query($SQL);

					// Loop the areas served category
					foreach ($areasServedPass AS $areaServedPass) {
						// Reset the recordset
						mysqli_data_seek($r, 0);

						// Loop the suppliers
						while ($d = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
							if (count($suppliers) == $l_requestedQuotes) break;

							extract($d, EXTR_PREFIX_ALL, 's');
							// "choice" referer is the only reason to skip caps check
							$checkCaps = !($d['uncappedChoiceLeads']=='Y' && $leadData['isChoice']);

							if($checkCaps) {
								// Check the limits - Non Claim
								$limits = db_query(" SELECT title, cap_id, max, length FROM supplier_cap_limit SCL INNER JOIN cap_limit_type CLT ON SCL.cap_id = CLT.record_num WHERE supplier_id = {$s_record_num} AND is_claim = 'N' AND type = 'residential' AND CLT.is_internal = 'N';");

								// First check global limits
								if(checkSupplierCapLimits($limits, $s_record_num, ['useInternals' => false])){
									continue;
								}			

								// New version for cap limits per leadType - Returns a true if it goes over the lead type cap
								if(supplierIsCapLimited($supplierData, $leadData)){
									continue;
								}
							}

							$isValid = db_getVal("SELECT record_num FROM supplier_areas WHERE supplier='{$s_record_num}' AND type='state' AND details='{$l_iState}' AND status = 'active' AND category = '{$areaServedPass}'");

							if (!$isValid) {
								$r2 = db_query("SELECT * FROM supplier_areas WHERE supplier='{$s_record_num}' AND type!='state' AND status = 'active' AND category = '{$areaServedPass}' ORDER BY record_num ASC");

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

							if ($areaServedPass == 'standard') {
								if (!$isValid) {
									// Is this supplier restricted to postcodes
									$isPostcodeEnabled = db_query("SELECT record_num FROM suppliers_postcode WHERE supplier_id='{$s_record_num}' AND status = 'active';");

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
								}
							}

							// Final checks to make sure we are not doubling up on the suppliers
							if ($isValid) {
								if (in_array($s_record_num, $suppliers))
									continue;

								$SQL = "SELECT COUNT(*) AS Count FROM lead_suppliers LS ";
								$SQL .= "INNER JOIN suppliers S ON LS.supplier = S.record_num ";
								$SQL .= "WHERE LS.lead_id={$l_record_num} AND LS.supplier={$s_record_num} AND LS.type='regular' ";
								$existingSupplierCount = db_getVal($SQL);

								if ($existingSupplierCount > 0)
									continue;
							}

							if ($isValid && !in_array($s_record_num, $suppliers) && !in_array($s_parent, $parents)) {
								$suppliers[] = $s_record_num;
								if($s_parent != 1)
									$parents[] = $s_parent;
								$leadPrice = leadPrice($s_record_num, "Residential", $leadData);
								$priceType = 'Residential';
								//If the leadPrice is an array then it's a structured price
								if(is_array($leadPrice)){
									list($leadPrice, $priceType) = $leadPrice;
								}

								db_query("INSERT INTO lead_suppliers SET lead_id='{$l_record_num}', type='regular', supplier='{$s_record_num}', dispatched={$nowSql}, status='sent', leadPrice='{$leadPrice}', priceType='{$priceType}'");
								$lsid = mysqli_insert_id($_connection);

								// send e-mail
								$data = array_merge($leadData, loadSupplierData($s_record_num));
								$data['rejectLink'] = $siteURLSSL . "leads/lead-reject.php?l={$lsid}&s={$s_record_num}&c=" . base64_encode($s_record_num . $l_email);

								if ($s_verboseEmailSubject == 'Y')
									$emailTemplate = 'supplierQuoteVerbose';
								else
									$emailTemplate = 'supplierQuote';

								if ($s_csvattachment == 'Y') {
									$csvfile = GenerateLeadCSV($l_record_num, $s_record_num, ['Lead Type'=>$priceType]);
									sendMailWithAttachment($s_email, "{$s_fName} {$s_lName}", $emailTemplate, $data, $csvfile);

									if ($s_emailcc != ''){
										$emails = explode(',', $s_emailcc);
										foreach($emails as $emailcc)
											sendMailWithAttachment(trim($emailcc), "{$s_fName} {$s_lName}", $emailTemplate, $data, $csvfile);
									}
								} else {
									//sendTemplateEmail('johnb@solarquotes.com.au', "{$s_fName} {$s_lName}", $emailTemplate, $data);
									sendTemplateEmail($s_email, "{$s_fName} {$s_lName}", $emailTemplate, $data);

									if ($s_emailcc != ''){
										$emails = explode(',', $s_emailcc);
										foreach($emails as $emailcc)
											sendTemplateEmail(trim($emailcc), "{$s_fName} {$s_lName}", $emailTemplate, $data);
									}
								}

								// Additional email to handle sms
								if ($s_mobile != '' && $s_sendMobile == 'Y') {
									$smsEmail = $s_mobile . '@sms.utbox.net';
									$smsEmail = preg_replace('/\s/', '', $smsEmail);

									$smsBody = "New SolarQuote lead - {$l_fName} {$l_lName} - {$l_iAddress} {$l_iCity} - {$l_phone}";
									$smsBody = substr($smsBody, 0, 1000);

									SendMail($smsEmail, "", "", $smsBody);
								}

								// Email that gets sent to administrators
								$tables = "permissions AS p LEFT JOIN admin_permissions AS ap ON ap.permission=p.record_num LEFT JOIN admins AS a ON ap.admin=a.record_num";
								$rs = db_query("SELECT a.name, a.email FROM {$tables} WHERE p.code='emailSupplier'");
								while ($d = mysqli_fetch_array($rs, MYSQLI_ASSOC)) {
									extract($d, EXTR_PREFIX_ALL, 'a');

									if ($a_email != '') {
										//sendTemplateEmail('johnb@solarquotes.com.au', $a_name, $emailTemplate, $data);
										sendTemplateEmail($a_email, $a_name, $emailTemplate, $data);
									}
								}

								if (count($suppliers) == $l_requestedQuotes) break;
							}
						}

						if (count($suppliers) == $l_requestedQuotes) break;
					}
				}

				// Update the submitted date and notes
				$lead = loadLeadData($leadId);

				$SQL = "SELECT {$nowSql};";
				$now = db_getVal($SQL);

				$comments = trim(trim($lead['notes']) . "\n\nLead redispatch was successful.  Original submission date was {$lead['submitted']}:\n{$now}");
				$comments = mysqli_escape_string($_connection, $comments);
				db_query("UPDATE leads SET status='dispatched', notes = '{$comments}' WHERE record_num = {$leadId}");

				// Send notification to lead affiliate if any
				send_affiliate_lead_notification($lead['referer'] ?? '');

				$supplierCount = db_getVal("SELECT COUNT(*) FROM lead_suppliers WHERE lead_id = {$leadId}");
				if ($supplierCount > 3)
				$supplierCount = 3;

				$GRCampaign = "reuse_sq_" . $supplierCount . "q";

				// Assign this lead into another GR campaign
				if ($sg_use_autoresponder == false)
					AddLeadToGR($leadId, $GRCampaign);
				else
					AddLeadToSG($leadId, $GRCampaign);
			}
		} catch (Throwable $e) {
			SendMail($techEmail, $techName, "Error - doDispatchReuse: " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
		}
	}

	function doDispatchSolarEstimator($leadId = 0) {
		global $techEmail, $techName, $_connection;
		try {
			global $nowSql, $siteURLSSL;

			$suppliers = array();
			$parents = array();
			$areasServedPass = array("ultra", "standard");

			$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
			if ($leadId) $extraConds = "AND record_num='{$leadId}' "; else $extraConds = '';

			// Select the lead details
			$r0 = db_query("SELECT *, {$extraCols} FROM leads WHERE status='pending' {$extraConds} ORDER BY record_num ASC");

			// While loop for the lead, this should only occur once
			while ($d = mysqli_fetch_array($r0, MYSQLI_ASSOC)) {
				extract($d, EXTR_PREFIX_ALL, 'l');
				$leadData = loadLeadData($l_record_num);

				// Update the lead status now
				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");

				$extraCondsSystem = "";
				foreach (unserialize(base64_decode($leadData['rawsystemDetails'])) as $a => $b) {
					$extraCondsSystem = "AND SS.system_size = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					break;
				}

				$extraCondsQuote = "";
				foreach (unserialize(base64_decode($leadData['rawquoteDetails'])) as $a => $b) {
					$extraCondsQuote = "AND T.timeframe = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					break;
				}

				$suppliers = loadPreviouslyMatchedSuppliers($l_record_num);
				$parents = $suppliers['parents'];
				$suppliers = $suppliers['suppliers'];
				if (count($suppliers) >= $l_requestedQuotes) break;

				// Reset the where clause
				$extraConds = $extraCondsSystem . $extraCondsQuote;

				// Build the SQL to select the potential supplier details
				$SQL = "SELECT DISTINCT S.record_num, S.* FROM suppliers S ";
				$SQL .= "INNER JOIN suppliers_system_size SSS ON S.record_num = SSS.suppliers_record ";
				$SQL .= "INNER JOIN system_size SS ON SSS.system_size_record = SS.record_num ";
				$SQL .= "INNER JOIN suppliers_timeframe ST ON S.record_num = ST.suppliers_record ";
				$SQL .= "INNER JOIN timeframe T ON ST.timeframe_record = T.record_num ";
				$SQL .= "INNER JOIN suppliers_roof_type SRT ON S.record_num = SRT.supplier ";
				$SQL .= "INNER JOIN roof_type RT ON SRT.roof_type = RT.record_num ";
				$SQL .= "WHERE S.status='active' AND S.reviewonly='N' AND S.integrationSE='Y' AND S.onGridSolar = 'Y' AND S.integrationResidential='Y' {$extraConds} ";
				$SQL .= "ORDER BY S.priority ASC; ";

				$r = db_query($SQL);

				// Loop the areas served category
				foreach ($areasServedPass AS $areaServedPass) {
					// Reset the recordset
					mysqli_data_seek($r, 0);

					// Loop the suppliers
					while ($d = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
						extract($d, EXTR_PREFIX_ALL, 's');
						// "choice" referer is the only reason to skip caps check
						$checkCaps = !($d['uncappedChoiceLeads']=='Y' && $leadData['isChoice']);

						if($checkCaps) {
							// Check the limits - Non Claim
							$limits = db_query(" SELECT cap_id, max, length, title FROM supplier_cap_limit SCL INNER JOIN cap_limit_type CLT ON SCL.cap_id = CLT.record_num WHERE supplier_id = {$s_record_num} AND is_claim = 'N' AND type = 'residential' AND CLT.is_internal = 'N';");

							// First check global limits
							if(checkSupplierCapLimits($limits, $s_record_num, ['useInternals' => false])){
								continue;
							}			

							// New version for cap limits per leadType - Returns a true if it goes over the lead type cap
							if(supplierIsCapLimited($supplierData, $leadData)){
								continue;
							}
						}

						$isValid = db_getVal("SELECT record_num FROM supplier_areas WHERE supplier='{$s_record_num}' AND type='state' AND details='{$l_iState}' AND status = 'active' AND category = '{$areaServedPass}'");

						if (!$isValid) {
							$r2 = db_query("SELECT * FROM supplier_areas WHERE supplier='{$s_record_num}' AND type!='state' AND status = 'active' AND category = '{$areaServedPass}' ORDER BY record_num ASC");

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

						if ($areaServedPass == 'standard') {
							if (!$isValid) {
								// Is this supplier restricted to postcodes
								$isPostcodeEnabled = db_query("SELECT record_num FROM suppliers_postcode WHERE supplier_id='{$s_record_num}' AND status = 'active';");

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
							}
						}

						if ($isValid && !in_array($s_record_num, $suppliers) && !in_array($s_parent, $parents)) {
							$suppliers[] = $s_record_num;
							if($s_parent != 1)
								$parents[] = $s_parent;
							$priceType = 'Residential';

							db_query("INSERT INTO lead_claims SET lead_id='{$l_record_num}', supplier='{$s_record_num}', claimed={$nowSql}, priceType = '{$priceType}';");
							$lsid = mysqli_insert_id($_connection);
							if (count($suppliers) == $l_requestedQuotes) break;
						}
					}

					if (count($suppliers) == $l_requestedQuotes) break;
				}

				// Update the lead status now
				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");

				$numQuotes = count($suppliers);
			}
		} catch (Throwable $e) {
			SendMail($techEmail, $techName, "Error - doDispatchSolarEstimator: " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
		}
	}

	function doDispatchRepairResidential($leadId = 0) {
		global $techEmail, $techName, $adminPAEmail, $adminPAName;
		try {
			global $nowSql, $siteURLSSL;
			$suppliersEmailArray = array();

			$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
			if ($leadId) $extraConds = "AND record_num='{$leadId}' "; else $extraConds = '';

			// Select the lead details
			$r0 = db_query("SELECT *, {$extraCols} FROM leads WHERE status='pending' {$extraConds} ORDER BY record_num ASC");

			// While loop for the lead, this should only occur once
			while ($d = mysqli_fetch_array($r0, MYSQLI_ASSOC)) {
				extract($d, EXTR_PREFIX_ALL, 'l');
				$leadData = loadLeadData($l_record_num);

				if(dispatchQA($leadData, ['revertStatus' => 'pending']))
					return "SUSPENDED";
				$extraConds = "";

				// Update the lead status now
				db_query("UPDATE leads SET status='dispatched' WHERE record_num='{$l_record_num}'");

				// Send notification to lead affiliate if any
				send_affiliate_lead_notification($l_referer ?? '');
			}
		} catch (Throwable $e) {
			SendMail($techEmail, $techName, "Error - doDispatchRepairResidential: " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
		}
	}

	function doDispatchRepairCommercial($leadId = 0) {
		global $techEmail, $techName, $adminPAEmail, $adminPAName;
		try {
			global $nowSql, $siteURLSSL;
			$suppliersEmailArray = array();

			$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
			if ($leadId) $extraConds = "AND record_num='{$leadId}' "; else $extraConds = '';

			// Select the lead details
			$r0 = db_query("SELECT *, {$extraCols} FROM leads WHERE status='pending' {$extraConds} ORDER BY record_num ASC");

			// While loop for the lead, this should only occur once
			while ($d = mysqli_fetch_array($r0, MYSQLI_ASSOC)) {
				extract($d, EXTR_PREFIX_ALL, 'l');
				$leadData = loadLeadData($l_record_num);

				$extraConds = "";

				// Update the lead status now
				db_query("UPDATE leads SET status='dispatched' WHERE record_num='{$l_record_num}'");

				// Send notification to lead affiliate if any
				send_affiliate_lead_notification($l_referer ?? '');
			}
		} catch (Throwable $e) {
			SendMail($techEmail, $techName, "Error - doDispatchRepairCommercial: " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
		}
	}

	// Specific function for EV leads, this customization allow for an optimized SQL query that removes unnecessary joins/filters
	function doDispatchEV($leadId = 0) {
		global $techEmail, $techName, $_connection;
		try {
			global $nowSql, $siteURLSSL;

			$areasServedPass = array("ultra", "standard");

			$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
			if ($leadId) $extraConds = "AND record_num='{$leadId}' "; else $extraConds = '';

			// Select the lead details
			$r0 = db_query("SELECT *, {$extraCols} FROM leads WHERE status='pending' {$extraConds} ORDER BY record_num ASC");

			// While loop for the lead, this should only occur once
			while ($d = mysqli_fetch_array($r0, MYSQLI_ASSOC)) {
				extract($d, EXTR_PREFIX_ALL, 'l');
				$leadData = loadLeadData($l_record_num);
				$suppliers = loadPreviouslyMatchedSuppliers($l_record_num);
				$parents = $suppliers['parents'];
				$suppliers = $suppliers['suppliers'];

				// Update the lead status now
				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");
				if (count($suppliers) >= $l_requestedQuotes) break;

				$extraCondsSystem = "";
				$extraCondsQuote = "";
				$orderBy = "";
				$joinRoofType = false;
				$pricing_types = leadPricingOptions($leadData);

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

				foreach (unserialize(base64_decode($leadData['rawquoteDetails'])) as $a => $b) {
					if (($a == "Price Type:") && ($b != "")) {
						$extraCondsQuote .= "AND PT.price_type = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					}
				}

				$extraCondsSite = "";
				foreach (unserialize(base64_decode($leadData['rawsiteDetails'])) as $a => $b) {
					$b = mysqli_real_escape_string($_connection, $b);

					if($a == 'How many storeys?'){
						$extraCondsSite .= "AND HS.home_stories = '" . $b . "' ";
					}
					elseif($a == "Type of Roof:" && $b != "") {	// EV lead only have this information if they also want quotes for solar
						$extraCondsSite .= "AND RT.roof_type = '" . $b . "' ";
						$joinRoofType = true;
					}
				}
				$extraConds .= " AND matched_pricing = " . count($pricing_types);

				if (isNSWRebateEligible($leadData, $pricing_types)) {
					$extraCondsQuote .= "AND (S.acceptNSWBatteryRebate = 'Y') ";
				}

				// Check if supplier handles Origin leads
				if ($leadData['originLead'] == 'Y') {
					$extraCondsQuote .= "AND S.acceptOriginLead = 'Y' ";
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
				if($joinRoofType) {
					$SQL .= "INNER JOIN suppliers_roof_type SRT ON S.record_num = SRT.supplier ";
					$SQL .= "INNER JOIN roof_type RT ON SRT.roof_type = RT.record_num ";
				}
				$SQL .= "INNER JOIN suppliers_price_type SPT ON S.record_num = SPT.supplier_record ";
				$SQL .= "INNER JOIN price_type PT ON SPT.price_type_record = PT.record_num ";
				$SQL .= "INNER JOIN suppliers_home_stories SHS ON S.record_num = SHS.supplier ";
				$SQL .= "INNER JOIN home_stories HS ON SHS.home_stories = HS.record_num ";
				// Check for suppliers with needed matching pricing types
				$SQL .= "INNER JOIN suppliers_parent SP ON S.parent = SP.record_num ";
				$SQL .= "INNER JOIN ( ";
				$SQL .= "	SELECT entity_id, COUNT(*) AS matched_pricing FROM entity_supplier_pricing esp ";
				$SQL .= "	WHERE pricing_type IN ('" . implode("','", $pricing_types ) . "') ";
				$SQL .= "	GROUP BY entity_id ";
				$SQL .= ") ESP ";
				$SQL .= "ON ESP.entity_id = ( CASE WHEN S.parentUseInvoice = 'Y' AND parent > 1 THEN SP.entity_id ELSE S.entity_id END )";
				$SQL .= "WHERE S.status='active' AND S.integrationResidential='Y' AND S.reviewonly='N' {$extraConds} ";
				$SQL .= $orderBy;

				$r = db_query($SQL);

				// Caps loop (if within spreading region, first check internal/secondary caps, then normal caps)
				foreach($internalCapsLoop as $checkingInternalCaps) {
					if (count($suppliers) >= $l_requestedQuotes) break;
					mysqli_data_seek($r, 0);
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
							// "choice" referer is the only reason to skip (normal/primary) caps check
							$checkNormalCaps = !($supplierData['uncappedChoiceLeads']=='Y' && $leadData['isChoice']);

							if($checkNormalCaps || $checkingInternalCaps) {
								$CLTfilter = " AND CLT.is_internal='".($checkingInternalCaps ? 'Y':'N')."'";

								// Check the limits - Non Claim
								$limits = db_query(" SELECT cap_id, max, length, title FROM supplier_cap_limit SCL INNER JOIN cap_limit_type CLT ON SCL.cap_id = CLT.record_num WHERE supplier_id = {$s_record_num} AND is_claim = 'N' AND type = 'residential' {$CLTfilter};");

								if($checkingInternalCaps && mysqli_num_rows($limits)==0)	// On the caps loop first execution
									continue;						// (spreading region) we want only installers with internal caps

								// First check global limits
								if(checkSupplierCapLimits($limits, $s_record_num)){
									continue;
								}			

								// New version for cap limits per leadType - Returns a true if it goes over the lead type cap
								if(supplierIsCapLimited($supplierData, $leadData)){
									continue;
								}
							}

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
								$suppliers[] = $s_record_num;
								if ($s_parent != 1)
									$parents[] = $s_parent;
								list($priceType, $leadPrice) = array_values(leadPricingInfo($supplierData, $leadData));
								db_query("INSERT INTO lead_claims SET lead_id='{$l_record_num}', supplier='{$s_record_num}', claimed={$nowSql}, priceType = '{$priceType}', leadPrice = '{$leadPrice}';");
								$lsid = mysqli_insert_id($_connection);
								if (count($suppliers) == $l_requestedQuotes) break;
							}
						}

						if (count($suppliers) == $l_requestedQuotes) break;
					}
				}

				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");
				if($l_manualAttempts < 1) { /* If it's a manual attempt this query has already been executed */
					if($isWithinSpreadingRegion) {
						foreach(explode(",", $leadSpreading['spreading_region_ids']) as $spreading_region_id) {
							db_query("INSERT INTO lead_dispatch_regions (lead_id, dispatch_region_id) VALUES ('{$l_record_num}', '{$spreading_region_id}')");
						}
					}
					addNoteSkippedSuppliers($suppliers, $oldLeadsIds, $l_record_num);
				}
				checkIfRequestedSupplierWasMatched($requestedInstallerEntitiesArray, $suppliers, $l_record_num);
			}
		} catch (Throwable $e) {
			SendMail($techEmail, $techName, "Error - doDispatchEV: " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
		}
	}

	// Specific function for HWHP leads, this customization allow for an optimized SQL query that removes unnecessary joins/filters
	function doDispatchHWHP($leadId = 0) {
		global $techEmail, $techName, $_connection;
		try {
			global $nowSql, $siteURLSSL;

			$areasServedPass = array("ultra", "standard");

			$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
			if ($leadId) $extraConds = "AND record_num='{$leadId}' "; else $extraConds = '';

			// Select the lead details
			$r0 = db_query("SELECT *, {$extraCols} FROM leads WHERE status='pending' {$extraConds} ORDER BY record_num ASC");

			// While loop for the lead, this should only occur once
			while ($d = mysqli_fetch_array($r0, MYSQLI_ASSOC)) {
				extract($d, EXTR_PREFIX_ALL, 'l');
				$leadData = loadLeadData($l_record_num);
				$suppliers = loadPreviouslyMatchedSuppliers($l_record_num);
				$parents = $suppliers['parents'];
				$suppliers = $suppliers['suppliers'];

				// Update the lead status now
				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");
				if (count($suppliers) >= $l_requestedQuotes) break;

				$extraCondsSystem = "";
				$extraCondsQuote = "";
				$orderBy = "";
				$joinRoofType = false;
				$pricing_types = leadPricingOptions($leadData);

				foreach (unserialize(base64_decode($leadData['rawsystemDetails'])) as $a => $b) {
					$pos = stripos($b, "Hot water heat pump");
					if ($pos !== false) {
						if (stripos($b, "battery") !== false)
							$extraCondsQuote .= "AND S.hwhpSolarBattery = 'Y' ";
						elseif (stripos($b, "solar") !== false)
							$extraCondsQuote .= "AND S.hwhpSolar = 'Y' ";
						else
							$extraCondsQuote .= "AND S.hwhp = 'Y' ";
					}
				}

				foreach (unserialize(base64_decode($leadData['rawquoteDetails'])) as $a => $b) {
					if (($a == "Price Type:") && ($b != "")) {
						$extraCondsQuote .= "AND PT.price_type = '" . mysqli_real_escape_string($_connection, $b) . "' ";
					}
				}

				$extraCondsSite = "";
				foreach (unserialize(base64_decode($leadData['rawsiteDetails'])) as $a => $b) {
					$b = mysqli_real_escape_string($_connection, $b);

					if($a == 'How many storeys?'){
						$extraCondsSite .= "AND HS.home_stories = '" . $b . "' ";
					}
					elseif($a == "Type of Roof:" && $b != "") {	// HWHP only have this information if they also want quotes for solar
						$extraCondsSite .= "AND RT.roof_type = '" . $b . "' ";
						$joinRoofType = true;
					}
				}
				$extraConds .= " AND matched_pricing = " . count($pricing_types);

				// Check if supplier handles Origin leads
				if ($leadData['originLead'] == 'Y') {
					$extraCondsQuote .= "AND S.acceptOriginLead = 'Y' ";
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
				if($joinRoofType) {
					$SQL .= "INNER JOIN suppliers_roof_type SRT ON S.record_num = SRT.supplier ";
					$SQL .= "INNER JOIN roof_type RT ON SRT.roof_type = RT.record_num ";
				}
				$SQL .= "INNER JOIN suppliers_price_type SPT ON S.record_num = SPT.supplier_record ";
				$SQL .= "INNER JOIN price_type PT ON SPT.price_type_record = PT.record_num ";
				$SQL .= "INNER JOIN suppliers_home_stories SHS ON S.record_num = SHS.supplier ";
				$SQL .= "INNER JOIN home_stories HS ON SHS.home_stories = HS.record_num ";
				// Check for suppliers with needed matching pricing types
				$SQL .= "INNER JOIN suppliers_parent SP ON S.parent = SP.record_num ";
				$SQL .= "INNER JOIN ( ";
				$SQL .= "	SELECT entity_id, COUNT(*) AS matched_pricing FROM entity_supplier_pricing esp ";
				$SQL .= "	WHERE pricing_type IN ('" . implode("','", $pricing_types ) . "') ";
				$SQL .= "	GROUP BY entity_id ";
				$SQL .= ") ESP ";
				$SQL .= "ON ESP.entity_id = ( CASE WHEN S.parentUseInvoice = 'Y' AND parent > 1 THEN SP.entity_id ELSE S.entity_id END )";
				$SQL .= "WHERE S.status='active' AND S.integrationResidential='Y' AND S.reviewonly='N' {$extraConds} ";
				$SQL .= $orderBy;

				$r = db_query($SQL);

				// Caps loop (if within spreading region, first check internal/secondary caps, then normal caps)
				foreach($internalCapsLoop as $checkingInternalCaps) {
					if (count($suppliers) >= $l_requestedQuotes) break;
					mysqli_data_seek($r, 0);
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
							// "choice" referer is the only reason to skip (normal/primary) caps check
							$checkNormalCaps = !($supplierData['uncappedChoiceLeads']=='Y' && $leadData['isChoice']);

							if($checkNormalCaps || $checkingInternalCaps) {
								$CLTfilter = " AND CLT.is_internal='".($checkingInternalCaps ? 'Y':'N')."'";

								// Check the limits - Non Claim
								$limits = db_query(" SELECT cap_id, max, length, title FROM supplier_cap_limit SCL INNER JOIN cap_limit_type CLT ON SCL.cap_id = CLT.record_num WHERE supplier_id = {$s_record_num} AND is_claim = 'N' AND type = 'residential' {$CLTfilter};");

								if($checkingInternalCaps && mysqli_num_rows($limits)==0)	// On the caps loop first execution
									continue;						// (spreading region) we want only installers with internal caps

								// First check global limits
								if(checkSupplierCapLimits($limits, $s_record_num)){
									continue;
								}			

								// New version for cap limits per leadType - Returns a true if it goes over the lead type cap
								if(supplierIsCapLimited($supplierData, $leadData)){
									continue;
								}
							}

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
								$suppliers[] = $s_record_num;
								if ($s_parent != 1)
									$parents[] = $s_parent;
								list($priceType, $leadPrice) = array_values(leadPricingInfo($supplierData, $leadData));
								db_query("INSERT INTO lead_claims SET lead_id='{$l_record_num}', supplier='{$s_record_num}', claimed={$nowSql}, priceType = '{$priceType}', leadPrice = '{$leadPrice}';");
								$lsid = mysqli_insert_id($_connection);
								if (count($suppliers) == $l_requestedQuotes) break;
							}
						}

						if (count($suppliers) == $l_requestedQuotes) break;
					}
				}

				db_query("UPDATE leads SET status='waiting', updated={$nowSql} WHERE record_num='{$l_record_num}'");
				if($l_manualAttempts < 1) { /* If it's a manual attempt this query has already been executed */
					if($isWithinSpreadingRegion) {
						foreach(explode(",", $leadSpreading['spreading_region_ids']) as $spreading_region_id) {
							db_query("INSERT INTO lead_dispatch_regions (lead_id, dispatch_region_id) VALUES ('{$l_record_num}', '{$spreading_region_id}')");
						}
					}
					addNoteSkippedSuppliers($suppliers, $oldLeadsIds, $l_record_num);
				}
				checkIfRequestedSupplierWasMatched($requestedInstallerEntitiesArray, $suppliers, $l_record_num);
			}
		} catch (Throwable $e) {
			SendMail($techEmail, $techName, "Error - doDispatchEV: " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
		}
	}

	function formatSupplierForCommercial($supplier) {
		global $siteURLSSL;

		$returnString = '';

		$SQL = "SELECT S.company, S.parent, SP.parentName, S.parentUseReview, S.commercialfName, S.commerciallName, S.commercialPhone, S.emailCommercial, S.emailContactCommercial ";
		$SQL .= "FROM suppliers S ";
		$SQL .= "INNER JOIN suppliers_parent SP ON S.parent = SP.record_num ";
		$SQL .= "WHERE S.record_num = '{$supplier}'";
		$supplier = db_query($SQL);

		while ($supplierRow = mysqli_fetch_array($supplier, MYSQLI_ASSOC)) {
			extract($supplierRow, EXTR_PREFIX_ALL, 's');

			if (($s_parent > 1) && ($s_parentUseReview == 'Y'))
				$companyURL = sanitizeURL($s_parentName);
			else
				$companyURL = sanitizeURL($s_company);

			$companyURL = '<a href="' . $siteURLSSL . 'installer-review/' . $companyURL . '/' . $companyURL . '-review.html">Review Link</a>';

			$returnString = "{$s_company} | {$companyURL} | {$s_commercialfName} {$s_commerciallName} | {$s_commercialPhone} | {$s_emailContactCommercial}";
		}

		return $returnString;
	}

	function leadPricingOptions($leadData){
		global $techEmail, $techName, $_connection;
		$pricing_types = [];
		foreach (unserialize(base64_decode($leadData['rawsystemDetails'])) as $a => $b) {
			if ($a == "Features:") {
				$pos = strpos($b, "Off Grid / Remote Area System");
				if ($pos !== false) {
					$pricing_types[] = 'Off Grid';
				}

				$pos = strpos($b, "On Grid Solar");
				if ($pos !== false) {
					$pricing_types[] = 'On Grid Solar';
				}

				$pos = strpos($b, "Battery Ready");
				if ($pos !== false) {
					$pricing_types[] = 'Battery Ready';
				}

				$pos = strpos($b, "Battery Storage System");
				$pos2 = strpos($b, "Hybrid System (Grid Connect with Batteries)");
				if (($pos !== false) || ($pos2 !== false)) {
					$pricing_types[] = 'Hybrid Systems';
				}

				$pos = strpos($b, "Adding Batteries");
				if ($pos !== false) {
					$pricing_types[] = 'Add Batteries to Existing';
				}

				$pos = strpos($b, "Microinverters");
				$pos2 = strpos($b, "Micro Inverters or Power Optimisers");
				if (($pos !== false) || ($pos2 !== false)) {
					$pricing_types[] = 'Micro Inverters / Power Optimisers';
				}

				$pos = strpos($b, "Upgrading an existing solar system");
				$pos2 = strpos($b, "Increase size of existing solar system");
				if (($pos !== false) || ($pos2 !== false)) {
					$pricing_types[] = 'Solar System Upgrade';
				}

				$pos = stripos($b, "EV Charger");
				if ($pos !== false) {
					if (stripos($b, "battery") !== false)
						$pricing_types[] = "EV charger + solar and / or battery";
					elseif (stripos($b, "solar") !== false)
						$pricing_types[] = "EV charger + solar only";
					else
						$pricing_types[] = "EV Chargers";
				}

				$pos = stripos($b, "Hot water heat pump");
				if ($pos !== false) {
					if (stripos($b, "battery") !== false)
						$pricing_types[] = "HWHP + solar and / or battery";
					elseif (stripos($b, "solar") !== false)
						$pricing_types[] = "HWHP + solar only";
					else
						$pricing_types[] = "HWHP";
				}
			}
		}

		if(empty($pricing_types))
			$pricing_types[] = 'On Grid Solar';
		return $pricing_types;
	}

	function leadPricingInfo($supplier, $leadData){
		extract($supplier, EXTR_PREFIX_ALL, 's');
		$pricing_types = leadPricingOptions($leadData);
		if($leadData['leadType'] == 'Commercial')
			$pricing_types[] = 'Commercial';
		// Now is it child supplier with parentUsePricing or a non-child supplier?
		$entity_id = $s_entity_id;
		if($s_parent > 1 && $s_parentUsePricing == 'Y'){
			$entity_id = db_getVal("SELECT entity_id FROM suppliers_parent WHERE record_num = " . $s_parent);	
		}

		$SQL = " SELECT pricing_type, price FROM entity_supplier_pricing WHERE entity_id = '{$entity_id}' AND pricing_type IN ('" . implode("','", $pricing_types ) . "') ORDER BY price DESC, pricing_type ASC LIMIT 1;"; 
		$result = db_query($SQL);
		$pricing_info = mysqli_fetch_assoc($result);
		return $pricing_info;
	}

	function leadPrice($supplier, $pricing_type, $leadData = null) {
		GLOBAL $salesEmail, $salesName;
		$supplier_id = $supplier;

		// Does this supplier use a parents price per lead
		$supplier = db_query("SELECT * FROM suppliers WHERE record_num = '{$supplier}'");
		$supplierRow = mysqli_fetch_array($supplier, MYSQLI_ASSOC);

		$leadPrice = leadPriceStructured($supplierRow, $leadData);

		if($leadPrice == 0 || $leadPrice == ''){
			#SendMail($salesEmail, $salesName, "SQ Supplier {$supplier_id} Price Issue", "Either the supplier or the parent does not have a price setup for {$pricing_type}");
		}

		return $leadPrice;
	}

	function fieldMapping($leadData){
		$leadData['quoteDetails'] = unserialize(base64_decode($leadData['rawquoteDetails']));
		$leadData['rebateDetails'] = unserialize(base64_decode($leadData['rawrebateDetails']));
		$leadData['systemDetails'] = unserialize(base64_decode($leadData['rawsystemDetails']));
		$leadData['siteDetails'] = unserialize(base64_decode($leadData['rawsiteDetails']));

		$leadData['quoteDetails']['TimeFrame'] = $leadData['quoteDetails']['Timeframe for purchase:'];
		$leadData['siteDetails']['RoofType'] = $leadData['siteDetails']['Type of Roof:'];
		$features_list = explode("\n", $leadData['systemDetails']['Features:']);
		foreach($features_list as $feature){
			if( stripos($feature, 'Upgrading an') !== false ||
			stripos($feature, 'Increase Size') !== false){
				$leadData['Features']['System Upgrades'] = 'System Upgrades';
			}

			if(stripos($feature, 'Off Grid') !== false){
				$leadData['Features']['Off Grid'] = 'Off Grid';
			}

			if(stripos($feature, 'hot water') !== false){
				$leadData['Features']['PV + HW'] = 'PV + HW';
			}

			if( stripos($feature, 'Battery') !== false ||
			stripos($feature, 'Hybrid') !== false ){
				$leadData['Features']['Hybrid'] = 'Hybrid';
			}

		}
		return $leadData;
	}

	/**
	* For leads that haven't had there "requestedQuotes" available slots filled,
	* notify possible suppliers
	*
	* @param mixed $leadId
	*/
	function fillSlots($leadId = 0) {
		global $techEmail, $techName, $nowSql, $siteURLSSL, $_connection;
		$excludeSuppliers = [];
		$excludeParents = [];
		try {
			$suppliers = array();
			$areasServedPass = array("ultra", "standard");

			$extraCols = "EXTRACT(YEAR_MONTH FROM submitted) AS month, EXTRACT(WEEK FROM submitted) AS week, EXTRACT(DAY FROM submitted) AS day";
			if ($leadId)
				$extraConds = "AND record_num='{$leadId}' "; else $extraConds = '';

			// Load already set lead suppliers
			$suppliersResult = db_query(" SELECT supplier FROM lead_claims WHERE lead_id = {$leadId};");
			while($row = mysqli_fetch_assoc($suppliersResult)){
				$excludeSuppliers[] = $row['supplier'];
			}
			// Exclude any siblings of the already matched suppliers
			if(!empty($excludeSuppliers)) {
				$supList = implode(",", $excludeSuppliers);
				$parentsResult = db_query("SELECT parent FROM suppliers WHERE parent != 1 AND record_num IN ({$supList});");
				while($row = mysqli_fetch_assoc($parentsResult)){
					$excludeParents[] = $row['parent'];
				}
			}

			// Grab the list of suppliers that served this list before and remove them from the possible suppliers this time around
			[$oldLeadsIds, $skipSuppliersList] = getInvalidSuppliersList($leadId);
			if(!empty($skipSuppliersList)){
				$excludeSuppliers = array_merge($excludeSuppliers, $skipSuppliersList);
			}

			// Select the lead details
			$r0 = db_query("SELECT *, {$extraCols} FROM leads WHERE status='waiting' {$extraConds} ORDER BY record_num ASC");
			$leadData = [];
			// While loop for the lead, this should only occur once
			while ($d = mysqli_fetch_array($r0, MYSQLI_ASSOC)) {
				extract($d, EXTR_PREFIX_ALL, 'l');
				$leadData = loadLeadData($l_record_num);
				$pricing_types = leadPricingOptions($leadData);
				$isEVOnly = (count($pricing_types)==1 && $pricing_types[0]==="EV Chargers");
				$isHWHPOnly = (count($pricing_types)==1 && $pricing_types[0]==="HWHP");

				// Exclude already set suppliers from the possible suppliers
				if(!empty($excludeSuppliers))
					$extraConds = " AND S.record_num NOT IN (" . implode(',', $excludeSuppliers) . ") ";
				else
					$extraConds = '';

				if(!empty($excludeParents))
					$extraConds .= " AND S.parent NOT IN (" . implode(',', $excludeParents) . ") ";

				if(!$isEVOnly && !$isHWHPOnly) { // Filter out EV-only suppliers, unless this is an EV-only lead
					$extraConds .= " AND (S.onGridSolar != 'N' OR S.batteryReady != 'N' OR S.offGrid != 'N' OR ";
					$extraConds .= " S.solarSystemUpgrade != 'N' OR S.batteryStorage != 'N' OR S.addBatteries != 'N') ";
				}

				if($leadData['leadType'] == 'Commercial'){
					$extraConds .= " AND S.integrationCommercial = 'Y' ";
					$limitType = 'commercial';
				} else {
					$extraConds .= " AND S.integrationResidential = 'Y' ";
					$limitType = 'residential';
				}

				$extraConds .= " AND matched_pricing = " . count($pricing_types);	
				$orderBy = supplierSizeOrderBy('');

				// Build the SQL to select the potential supplier details
				$SQL = "SELECT DISTINCT S.record_num, S.*";
				$SQL .= "FROM suppliers S ";
				// Check for suppliers with needed matching pricing types
				$SQL .= "INNER JOIN suppliers_parent SP ON S.parent = SP.record_num ";
				$SQL .= "INNER JOIN ( ";
				$SQL .= "	SELECT entity_id, COUNT(*) AS matched_pricing FROM entity_supplier_pricing esp ";
				$SQL .= "	WHERE pricing_type IN ('" . implode("','", $pricing_types ) . "') ";
				$SQL .= "	GROUP BY entity_id ";
				$SQL .= ") ESP "; 
				$SQL .= "ON ESP.entity_id = ( CASE WHEN S.parentUseInvoice = 'Y' AND parent > 1 THEN SP.entity_id ELSE S.entity_id END )";
				$SQL .= "WHERE S.extraLeads = 'Y' AND S.reviewonly='N' {$extraConds} AND S.extraLeadsLockOut = 'N'";
				$SQL .= $orderBy;

				$r = db_query($SQL);

				// Loop the areas served category
				foreach ($areasServedPass AS $areaServedPass) {
					// Reset the recordset
					mysqli_data_seek($r, 0);

					// Loop the suppliers
					while ($supplierData = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
						extract($supplierData, EXTR_PREFIX_ALL, 's');
						// "choice" referer is the only reason to skip caps check
						$checkCaps = !($supplierData['uncappedChoiceLeads']=='Y' && $leadData['isChoice']);
						if($checkCaps) {
							// Check supplier cap limits
							// Check the limits - Claims
							$limits = db_query("SELECT cap_id, max, length FROM supplier_cap_limit SCL INNER JOIN cap_limit_type CLT ON SCL.cap_id = CLT.record_num WHERE supplier_id = {$s_record_num} AND is_claim = 'Y' AND type = '{$limitType}' AND CLT.is_internal = 'N';");

							$elements = [];
							while($row = mysqli_fetch_assoc($limits)){
								// 0 - Is unlimited
								extract($row, EXTR_PREFIX_ALL, 'c');
								if($row['max'] != 0)
									$elements["cap_{$row['cap_id']}"] = "SUM(CASE WHEN date > DATE_FORMAT(( {$nowSql} - INTERVAL {$row['length']} HOUR), '%Y%m%d') then claimed else 0 end) > {$row['max']} ";
							}

							if(!empty($elements)){
								$isMaxed = db_getVal(" SELECT count(*) as maxed FROM log_leads_claimed WHERE supplier = {$s_record_num} GROUP BY supplier HAVING " . implode(' OR ', $elements));
								if($isMaxed != '') // Supplier exceeds the Extra Cap Limits set
									continue;
							}
						}

						$isValid = db_getVal("SELECT record_num FROM supplier_areas WHERE supplier='{$s_record_num}' AND type='state' AND details='{$l_iState}' AND status = 'active' AND category = '{$areaServedPass}'");

						if (!$isValid) {
							$r2 = db_query("SELECT * FROM supplier_areas WHERE supplier='{$s_record_num}' AND type!='state' AND status = 'active' AND category = '{$areaServedPass}' ORDER BY record_num ASC");

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

						if ($areaServedPass == 'standard') {
							if (!$isValid) {
								// Is this supplier restricted to postcodes
								$isPostcodeEnabled = db_query("SELECT record_num FROM suppliers_postcode WHERE supplier_id='{$s_record_num}' AND status = 'active';");

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
							}
						}

						if ($isValid && !in_array($s_record_num, $suppliers)) {
							$suppliers[] = $s_record_num;
						}
					}
				}
			}
		} catch (Throwable $e) {
			SendMail($techEmail, $techName, "Error - fillSlots: " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
		}

		$count = db_getVal(" SELECT count(*) FROM lead_claims WHERE lead_id = {$leadId};");
		$slotSize = $l_requestedQuotes - $count;

		if(empty($suppliers) || $slotSize <= 0){
			$SQL = " UPDATE leads SET status = 'sending' WHERE record_num = {$leadId}; ";
			db_query($SQL);
		} else {
			$SQL = " UPDATE leads SET openClaims = 'Y' WHERE record_num = {$leadId};";
			db_query($SQL);

			if($l_manualAttempts < 1){
					notifyLeadManualProcess($leadData);
			}

			foreach($suppliers as $supplier){
				$SQL = " SELECT record_num, mainContactfName, mainContactlName, company, mainContactEmail, extraLeadsDeliveryMethod, mobile, sendMobile, claimContactfName, claimContactlName, claimContactEmail, entity_id, parent, parentUseInvoice, parentUsePricing FROM suppliers WHERE record_num = {$supplier} ";
				$result = db_query($SQL);
				$result = mysqli_fetch_assoc($result);
				EXTRACT($result, EXTR_PREFIX_ALL, 's');

				$quoteDetails = unserialize(base64_decode($leadData['rawquoteDetails']));
				$rebateDetails = unserialize(base64_decode($leadData['rawrebateDetails']));
				$siteDetails = unserialize(base64_decode($leadData['rawsiteDetails']));
				$systemDetails = unserialize(base64_decode($leadData['rawsystemDetails']));

				$i = Encrypt(base64_encode(json_encode([
					'lead' => $leadId,
					'supplier_id' => $supplier
				])));

				$supplierOptOutLink = Encrypt(base64_encode(json_encode([
					'supplier_id' => $supplier
				])));

				list($leadType, $leadCost) = array_values(leadPricingInfo($result, $leadData));
				if($leadCost == 0 || $leadCost == ''){
					continue;
				}
				$systemPriceType = isset($quoteDetails['Price Type:']) ? $quoteDetails['Price Type:'] : '';
				$companyCountText = $slotSize == 1 ? 'company' : 'companies';
				$companyCountTotalText = $l_requestedQuotes == 1 ? 'company' : 'companies';
				$requestedQuotesText = $l_requestedQuotes . ( $l_requestedQuotes == 1 ? ' quote' : ' quotes');

				$data = [
					'specialNotification' => '',
					'slotSize' => $slotSize,
					'companyCountText' => $companyCountText,
					'companyCountTotalText' => $companyCountTotalText,
					'leadCost' => number_format($leadCost,0),
					'leadClaimLink' => '<a href="'.$siteURLSSL.'leads/claim/?i=' . $i . '&source=email" style="font-size: 13px!important;font-family: Helvetica Neue, Helvetica, Arial, sans-serif!important;color: white!important;border: none!important;letter-spacing: 1.44px!important;padding: 16px 38px!important;display: inline-block!important;border-radius: 34px!important;margin-bottom: 34px!important;font-weight: bold!important;text-decoration: none;background: rgb(243,112,31);background: -moz-linear-gradient(90deg, rgba(243,112,31,1) 0%, rgba(194,36,44,1) 85%, rgba(190,30,45,1) 100%);background: -webkit-linear-gradient(90deg, rgba(243,112,31,1) 0%, rgba(194,36,44,1) 85%, rgba(190,30,45,1) 100%);background: linear-gradient(90deg, rgba(243,112,31,1) 0%, rgba(194,36,44,1) 85%, rgba(190,30,45,1) 100%);">CLAIM LEAD</a>',
					'lead' => $leadId,
					'leadType' => $leadType,
					'systemSize' => $systemDetails['System Size:'],
					'systemPriceType' => $systemPriceType == 'Top quality (most expensive)' ? "<strong>$systemPriceType</strong>" : $systemPriceType,
					'features' => str_replace(PHP_EOL, '<br />', $systemDetails['Features:']),
					'timeframe' => $quoteDetails['Timeframe for purchase:'],
					'askedForHome' => isset($quoteDetails['Asked for home visit?']) ? $quoteDetails['Asked for home visit?'] : '',
					'quarterlyBill' => isset($quoteDetails['Quarterly Bill:']) ? str_replace('$', '&#36;', ($quoteDetails['Quarterly Bill:'])) : '',
					'phone' => $leadData['phone'],
					'iCity' => $leadData['iCity'],
					'installationAddress' => $leadData['iCity'] . ', ' . $leadData['iState'] . ', ' . $leadData['iPostcode'],
					'roofType' => $siteDetails['Type of Roof:'],
					'storeys' => $siteDetails['How many storeys?'] ?? "",
					'staffNotes' => $leadData['supplierNotes'],
					'optoutLink' => '<a href="'.$siteURLSSL.'leads/claim_optout/?c=' . $supplierOptOutLink . '">here</a>',
					'requestedQuotes' => $l_requestedQuotes,
					'requestedQuotesText' => $requestedQuotesText,
					'capLimitWarning' => '',
					'quoteBoundText' => 'but they need '.$slotSize.' more '.$companyCountText.' to quote to make up '.$requestedQuotesText,
					'submitted' => $leadData['submitted'],
					'updated' => $leadData['updated'],

					// EV Information
					'carMake' => (isset($siteDetails['Car Make/Model:']) ? $siteDetails['Car Make/Model:'] : ''),
					'existingSolar' => isset($siteDetails['Existing solar size:']) ? $siteDetails['Existing solar size:'] : '',
					'existingBattery' => isset($siteDetails['Have battery?']) ? $siteDetails['Have battery?'] : '',
					'installationType' => isset($siteDetails['EV Installation Type:']) ? $siteDetails['EV Installation Type:'] : '',
					'chargerDistance' => isset($siteDetails['Distance between charger and switchboard:']) ? $siteDetails['Distance between charger and switchboard:'] : '',

					// HWHP Information
					'existingWaterSystem' => (isset($siteDetails['Existing Hot Water System:']) ? $siteDetails['Existing Hot Water System:'] : ''),
					'numberOfResidents' => isset($siteDetails['Number of Residents:']) ? $siteDetails['Number of Residents:'] : '',
					'locationAccess' => isset($siteDetails['Location Accessibility:']) ? $siteDetails['Location Accessibility:'] : '',
					'switchboardDistance' => isset($siteDetails['Distance between heat pump and switchboard:']) ? $siteDetails['Distance between heat pump and switchboard:'] : ''
				];

				if($l_requestedQuotes > 2){
					$data['capLimitWarning'] = "Please be aware that we will never send a lead to more than ".$data['requestedQuotes']." ".$data['companyCountTotalText']." in total.";
				}
				if($l_requestedQuotes == 1) {
					$data['quoteBoundText'] = 'but they need '.$slotSize.' more '.$companyCountText.' to make up the quote';
					$data['specialNotification'] = 'Exclusive Lead: ';
				}

				// Check if there is any entries for the current supplier in the log_leads_claimed
				$SQL = " SELECT supplier, sent, claimed, date FROM log_leads_claimed WHERE supplier = {$s_record_num} AND date = DATE_FORMAT({$nowSql}, '%Y%m%d'); ";
				$logClaimed = db_query($SQL);

				// No records then insert new one
				if(mysqli_num_rows($logClaimed) === 0){
					$SQL = " INSERT INTO log_leads_claimed (supplier, sent, date) VALUES({$s_record_num}, 1, DATE_FORMAT({$nowSql}, '%Y%m%d')); ";
					db_query($SQL);
				} else {
					$SQL = " UPDATE log_leads_claimed SET sent = sent + 1 WHERE supplier = {$s_record_num} AND date = DATE_FORMAT({$nowSql}, '%Y%m%d'); ";
					db_query($SQL);
				}

				$isEVChargerLead = isset($siteDetails['Car Make/Model:']) && $siteDetails['Car Make/Model:'] !== '';
				$isHWHPLead = isset($siteDetails['Location Accessibility:']) && $siteDetails['Location Accessibility:'] !== '';

				// If supplier requests to be notified by both SMS AND/OR EMAIL
				if(in_array($s_extraLeadsDeliveryMethod, ['Both', 'Email'])){
					$template = 'extraLead' . $leadData['leadType'];
					// If it's an EV charger use the extraLeadEV template
					if($isEVChargerLead){
						$template = 'extraLeadEV';
					}

					if($isHWHPLead){
						$template = 'extraLeadHWHP';
					}
					sendTemplateEmail($s_claimContactEmail, $s_claimContactfName . ' ' . $s_claimContactlName, $template , $data);
					//sendTemplateEmail('johnb@solarquotes.com.au', $s_claimContactfName . ' ' . $s_claimContactlName, $template , $data);
				}

				// If supplier requests to be notified by both Email AND/OR SMS
				if(in_array($s_extraLeadsDeliveryMethod, ['Both', 'SMS'])){
					try {
						// Check if mobile field is filled and is valid
						if($s_mobile != '' && $s_sendMobile == 'Y' && ( substr($smsEmail, 0, 2) != '04' || substr($smsEmail, 0, 1) != '4')){
							$smsEmail = str_replace([' ', '(', ')'], [''], $s_mobile) . '@sms.utbox.net';
							$smsEmail = preg_replace('/\s/', '', $smsEmail);

							$URL = $siteURLSSL.'leads/claim_optout/?c=' . $supplierOptOutLink;
							$URL = shortenURL($URL);

							$message = "Extra lead in {$leadData['iCity']}.  Size {$systemDetails['System Size:']}.  More details here\n{$URL}";

							if($isEVChargerLead){
								$message = "Extra EV charger lead in {$leadData['iCity']}. More details here\n{$URL}";
							}
							if($leadData['leadType'] == 'Commercial'){
								$monthly = $quoteDetails['Electricity Bill per month:'];
								$message = "Extra lead in {$leadData['iCity']}.  Monthly Bill {$monthly}.  More details here\n{$URL}";
							}

							SendMail($smsEmail, '', '', $message);
						}
					} catch (Throwable $e) {
						SendMail($techEmail, $techName, "Error - fillSlots (2): " . ($leadId ? $leadId : "missing lead ID"), $e->getMessage());
					}
				}

				// Write data sent to supplier into global table
				$data['leadPrice'] = $data['leadCost'];
				$json = mysqli_real_escape_string($_connection, json_encode($data));
				db_query(" INSERT INTO global_data(id, type, name, description, created) VALUES ( {$s_record_num}, 'supplier', 'claimleads', '{$json}', ".$nowSql.");");
			}
		}
	}

	/**
	* Dispatch leads to all claiming suppliers
	*
	* @param mixed $leadId
	*/
	function dispatchClaims($leadId = 0){
		global $nowSql, $siteURLSSL, $_connection, $dispatchName, $dispatchEmail, $emailTemplatePrepend, $emailTemplateFinnSignature, $emailTemplateAppend, $techEmail, $techName, $siteURLSSL, $sg_use_autoresponder, $phpdir;
		// Grouping will prevent duplication of entries in the lead_suppliers table if the entries are duplicated in the claims table for the same supplier
		$leadClaimsResult = db_query(" SELECT MAX(lc.record_num) as l_record_num, lc.lead_id, lc.extraLead, lc.supplier, lc.priceType, lc.leadPrice, lc.manuallySelectedByLead FROM lead_claims lc WHERE lead_id = {$leadId} GROUP BY lc.lead_id, lc.extraLead, lc.supplier, lc.priceType, lc.leadPrice, lc.manuallySelectedByLead; ");

		$supplierCount = 0;
		$leadData = loadLeadData($leadId);
		$isEVChargerLead = stripos($leadData['systemDetails'], 'EV Charger') !== false;
		$isHWHPLead = stripos($leadData['systemDetails'], 'Hot water heat pump') !== false;
		if(dispatchQA($leadData, ['leadClaims' => mysqli_fetch_all($leadClaimsResult, MYSQLI_ASSOC)])) {
			return "SUSPENDED";
		}
		mysqli_data_seek($leadClaimsResult, 0);
		if(is_null($leadData['suspendedUntil']) || $leadData['suspendedUntil'] === "") {
			if(strpos($leadData['systemDetails'],'On Grid Solar') !== false){
				if(suspendLeadDispatchForAudit($leadId) === true)
					return "SUSPENDED";	// This lead's dispatch has just been suspended, so return
			}
			mysqli_data_seek($leadClaimsResult, 0);
		}
		// Loop the claims table ( 1 iteration = 1 supplier )
		$supplierReviewPages = [];
		$matchedInstallersListHtml = [];
		while($leadClaims = mysqli_fetch_assoc($leadClaimsResult)){
			extract($leadClaims, EXTR_PREFIX_ALL, 'lc');
			$supplierData = mysqli_fetch_assoc(db_query( " SELECT * FROM suppliers WHERE record_num = {$lc_supplier}; "));
			extract($supplierData, EXTR_PREFIX_ALL, 's');

			$leadPrice = $lc_leadPrice;

			$manualLead = 'N';
			$invoice = 'Y';

			//Check to see if Lead claims table price type contains Manual
			if(stripos($lc_priceType, 'Manual') !== false){
				$manualLead = 'Y';
			}

			if($leadPrice == 0 && $s_reviewonly == 'Y'){
				$invoice = 'N';
			}

			$alreadyExists = db_getVal("SELECT COUNT(*) FROM lead_suppliers WHERE lead_id='{$leadData['record_num']}' AND supplier='{$lc_supplier}';");
			if($alreadyExists > 0) {
				// Another cronjob is still working on this lead because the row already exists on lead_suppliers - skip
				return true;
			}
			db_query("INSERT INTO lead_suppliers SET lead_id='{$leadData['record_num']}', type='regular', supplier='{$lc_supplier}', dispatched={$nowSql}, status='sent', leadPrice='{$lc_leadPrice}', manuallySelectedByLead='{$lc_manuallySelectedByLead}', priceType='{$lc_priceType}', extraLead='{$lc_extraLead}', manualLead='{$manualLead}', Invoice='{$invoice}'");
			$lsid = mysqli_insert_id($_connection);

			// If dispatched during a trial, add to leads acquired
			$strial = db_query("SELECT * FROM suppliers_trials WHERE trial_status = 'active' AND supplier='{$lc_supplier}' ORDER BY trial_start DESC LIMIT 1");
			while ($trial = mysqli_fetch_assoc($strial)) {
				extract($trial, EXTR_PREFIX_ALL, 't');
				$lead_ids = empty($t_lead_ids) ? [] : explode(',', $t_lead_ids);
				$lead_ids[] = $leadData['record_num'];
				$leads_got = count($lead_ids);
				$lead_ids_s = implode(',', $lead_ids);
				db_query("UPDATE suppliers_trials SET lead_ids = '{$lead_ids_s}' WHERE record_num = {$t_record_num}");
				if ($leads_got >= $t_max_leads) {
					trigger_trial($t_record_num);
				}
			}

			// send e-mail
			$data = array_merge($leadData, loadSupplierData($lc_supplier));
			$specificQuoteRequestAppend = false;
			if($lc_manuallySelectedByLead == 'Y') {
				$specificQuoteRequestAppend = $leadData['fName']." specifically requested a quote from ".$data['sCompany'];
				$systemDetails = unserialize(base64_decode($data['rawsystemDetails']));
				if(!array_key_exists("Features:", $systemDetails))
					$systemDetails["Features:"] = $specificQuoteRequestAppend;
				$systemDetails["Features:"] .= PHP_EOL . $specificQuoteRequestAppend;
				$systemDetails = base64_encode(serialize($systemDetails));
				$data['systemDetailsCells'] = decodeArrayTemplateCells($systemDetails);
				$data['systemDetails'] = decodeArray($systemDetails);
			}
			$data['rejectLink'] = $siteURLSSL . "leads/lead-reject.php?l={$lsid}&s={$lc_supplier}&c=" . base64_encode($lc_supplier . $leadData['email']);
			$data['specialNotification'] = '';

			if($leadData['requestedQuotes'] == 1)
				$data['specialNotification'] = 'Exclusive Lead: ';

			if ($s_verboseEmailSubject == 'Y')
				$emailTemplate = 'supplierQuoteVerbose';
			else
				$emailTemplate = 'supplierQuote';

			if($isEVChargerLead){
				if(isset($data['leadImages'])){
					$data['systemDetailsCells'] .= $data['leadImages'];
				}
				$emailTemplate .= 'EV';
			}

			if($isHWHPLead){
				if(isset($data['leadImages'])){
					$data['systemDetailsCells'] .= $data['leadImages'];
				}
				$emailTemplate .= 'HWHP';
			}

			$targetEmail = $s_email;
			$targetEmailCC = $s_emailcc;
			$targetName = "{$s_fName} {$s_lName}";

			// Change target email if Commercial lead
			if($s_emailCommercial != '' && $leadData['leadType'] == 'Commercial'){
				$targetEmail = $s_emailCommercial;
				$targetEmailCC = $s_emailCommercialCC;
				$targetName = "{$s_commercialfName} {$s_commerciallName}";
			}

			if ($s_csvattachment == 'Y') {
				$csvOptions = ['Lead Type'=>$lc_priceType, 'specificQuoteRequestAppend'=>$specificQuoteRequestAppend];
				$csvfile = GenerateLeadCSV($leadId, $s_record_num, $csvOptions);

				//sendMailWithAttachment('johnb@solarquotes.com.au', $targetName , $emailTemplate, $data, $csvfile);
				sendMailWithAttachment($targetEmail, $targetName , $emailTemplate, $data, $csvfile);

				if ($targetEmailCC != ''){
					$emails = explode(',', $targetEmailCC);
					foreach($emails as $emailcc) {
						//sendMailWithAttachment(trim('johnb@solarquotes.com.au'), "{$s_fName} {$s_lName}", $emailTemplate, $data, $csvfile);
						sendMailWithAttachment(trim($emailcc), "{$s_fName} {$s_lName}", $emailTemplate, $data, $csvfile);
					}
				}
			} else {
				//sendTemplateEmail('johnb@solarquotes.com.au', $targetName, $emailTemplate, $data);
				sendTemplateEmail($targetEmail, $targetName, $emailTemplate, $data);

				if ($targetEmailCC != ''){
					$emails = explode(',', $targetEmailCC);
					foreach($emails as $emailcc) {
						//sendTemplateEmail(trim('johnb@solarquotes.com.au'), "{$s_fName} {$s_lName}", $emailTemplate, $data);
						sendTemplateEmail(trim($emailcc), "{$s_fName} {$s_lName}", $emailTemplate, $data);
					}
				}
			}

			// Email that gets sent to administrators
			$tables = "permissions AS p LEFT JOIN admin_permissions AS ap ON ap.permission=p.record_num LEFT JOIN admins AS a ON ap.admin=a.record_num";
			$rs = db_query("SELECT a.name, a.email FROM {$tables} WHERE p.code='emailSupplier'");
			while ($d = mysqli_fetch_array($rs, MYSQLI_ASSOC)) {
				extract($d, EXTR_PREFIX_ALL, 'a');

				if ($a_email != '') {
					//sendTemplateEmail('johnb@solarquotes.com.au', $a_name, $emailTemplate, $data);
					sendTemplateEmail($a_email, $a_name, $emailTemplate, $data);
				}
			}

			// If it's a child that uses the parent reviews, get the parent path instead
			$installerName = '';
			$installerURL = '';
			if($s_parent != 1 && $s_parentUseReview == 'Y'){
				$parentData = mysqli_fetch_assoc(db_query( " SELECT parentName FROM suppliers_parent WHERE record_num = {$s_parent}; "));
				$installerName = $parentData['parentName'];
				$installerURL = sprintf("$siteURLSSL%s/%s/",'installer-review',sanitizeURL($parentData['parentName']));
			} else {
				$installerName = $s_company;
				$installerURL = sprintf("$siteURLSSL%s/%s/",'installer-review',sanitizeURL($supplierData['company']));
			}
			$supplierReviewPages[$installerName] = $installerURL;
			$requestedSupplierString = ($specificQuoteRequestAppend !== false) ? " (as you asked)" : "";
			$vcard = sprintf("$siteURLSSL%s/?s=%s",'supplier/vcard',base64_encode($supplierData['record_num']));
			$matchedInstallersListHtml[] = sprintf('<li><a href="%s">%s</a>%s&nbsp;&nbsp;&nbsp;<a href="%s" style="text-decoration: none;">&#128100;+</a></li>', $installerURL, $installerName, $requestedSupplierString, $vcard);
			
			$supplierCount++;
		}

		// Remove lead claim entry
		$SQL = " DELETE FROM lead_claims WHERE lead_id = '{$leadId}'; ";
		db_query($SQL);

		// Remove from global data
		$SQL = "DELETE FROM global_data WHERE (type = 'supplier' AND name = 'claimleads' AND description like '%\"lead\":\"{$leadId}\"%') OR (id = $leadId AND type='lead' AND name='leadexcludesuppliers')";
		db_query($SQL);

		$SQL = " UPDATE leads SET status = 'dispatched', submitted = {$nowSql}, updated = {$nowSql} WHERE record_num = {$leadId}; ";
		db_query($SQL);

		$grCampaignNone = 'sq_no_quotes';
		$grCampaignAll = 'sq_all_quotes';

		if($isEVChargerLead){
			$grCampaignNone = 'sq_no_ev_quotes';
			$grCampaignAll = 'sq_all_ev_quotes';
		}

		if($isHWHPLead){
			$grCampaignNone = 'sq_no_hp_quotes';
			$grCampaignAll = 'sq_all_hp_quotes';
		}

		if($supplierCount === 0){
			if ($sg_use_autoresponder == false)
				AddLeadToGR($leadId, $grCampaignNone);
			else
				AddLeadToSG($leadId, $grCampaignNone);
		} else {
			if ($sg_use_autoresponder == false)
				AddLeadToGR($leadId, $grCampaignAll);
			else
				AddLeadToSG($leadId, $grCampaignAll);
		}

		extract($leadData, EXTR_PREFIX_ALL, 'l');

		// Get the number of available quote slots for unsigned suppliers
		$matched = $supplierCount;
		$unfilledSlots =  abs($matched - $l_requestedQuotes);

		// Before processing unsigned, check if v4 is allowed for current lead state
		$v4Settings = json_decode(db_getVal('SELECT dispatch_v4_conditions FROM settings'));
		$v4ByState = $v4Settings->$l_iState;
		$v4Enabled = $v4ByState[0] == 0;

		// Check v4 lead conditions
		// Features can't have "Increase size of existing solar system"
		// Time frame needs to be different from "In the next 6 months" and "In the next year"
		// If features contains any reference to indicate that the house haven't been built yet

		
		// $data has only been defined if one or more suppliers were matched to this lead
		if($matched > 0) {
			$quoteDetails = unserialize(base64_decode($data['rawquoteDetails']));
			$systemDetails = unserialize(base64_decode($data['rawsystemDetails']));

			$featuresNopList = [
				'For a home that has not been built yet',
				'For a home that is currently under construction',
				'A home to begin construction soon - plans are available to the installer.',
				'A home currently under construction',
				'Increase size of existing solar system'
			];
			$features = explode("\n", $systemDetails['Features:']);
	
			// If there's any value that exists in both arrays we've found an exclusion rule for V4
			if(count(array_intersect($featuresNopList, $features)) > 0) {
				$v4Enabled = false;
			}
	
			if($v4Enabled && in_array($quoteDetails['Timeframe for purchase:'], ['In the next 6 months', 'In the next year'])){
				$v4Enabled = false;
			}
		}

		$body = '';
		$subject = '{first_name}, thanks for requesting {requested_quote_count} {text_quote_or_quotes} for {text_quote_type}';
		$layout = file_get_contents("$phpdir/email_templates/dispatch/layout.html");
		$all = '_matched-all.html';
		$none = '_matched-none.html';
		$partial = '_matched-partial.html';

		if($isEVChargerLead){
			$subject = '{first_name}, thanks for requesting {requested_quote_count} {text_quote_or_quotes} for {text_quote_type}';
			$layout = file_get_contents("$phpdir/email_templates/dispatch/layout-ev.html");
			$all = '_ev_matched-all.html';
			$none = '_ev_matched-none.html';
			$partial = '_ev_matched-partial.html';
		}

		if($isHWHPLead){
			$subject = '{first_name}, thanks for requesting {requested_quote_count} {text_quote_or_quotes} for {text_quote_type}';
			$layout = file_get_contents("$phpdir/email_templates/dispatch/layout-hwhp.html");
			$all = '_hwhp_matched-all.html';
			$none = '_hwhp_matched-none.html';
			$partial = '_hwhp_matched-partial.html';
		}

		//  Check if the lead has finance options

		$SQL = " SELECT * FROM lead_finance WHERE lead_id = {$leadId} order by partner asc; ";
		$financeResult = db_query($SQL);
		$financePartners = [];
		$hasFinance = false;
		$financeText = '';

		while($leadFinance = mysqli_fetch_assoc($financeResult)){
			$parterName = $leadFinance['partner'];
			switch($leadFinance['partner']){
				case 'SmartMe':
					$parterName = 'SmartMe';
					break;
				case 'ParkerLane':
					$parterName = 'Parker Lane';
					break;
			}
			$financePartners[] = $parterName;
			$hasFinance = true;
		}

		if($hasFinance){
			$all = '_finance-matched-all.html';
			$partial = '_finance-matched-partial.html';

			$lastPartner = array_splice($financePartners, -1);
			$financePartners = array_filter($financePartners);
			if(! empty($financePartners)){
				$financeText = implode(', ', $financePartners) . ' and ' . $lastPartner[0];
			} else {
				$financeText = $lastPartner[0];
			}
		}

		if($l_requestedQuotes == $matched){
			$body = str_replace('{content}', file_get_contents("$phpdir/email_templates/dispatch/$all"), $layout);
		} elseif ($matched === 0){
			$body = str_replace('{content}', file_get_contents("$phpdir/email_templates/dispatch/$none"), $layout);
		} else {
			$body = str_replace('{content}', file_get_contents("$phpdir/email_templates/dispatch/$partial"), $layout);
		}

		$leadFeatures = unserialize(base64_decode($leadData['rawsystemDetails']))['Features:'];

		$tile_roof_message = '';
		$free_notes_text = ''; // This is a markup for any "free notes" that are added to the lead, this can be specific to quote type or information to lead
		$leadSiteDetails = unserialize(base64_decode($leadData['rawsiteDetails']));
		if (!empty($leadSiteDetails['Type of Roof:']) && strtolower($leadSiteDetails['Type of Roof:']) == 'tile') {
			$tile_roof_message = 'Also, as you mentioned you have a tile roof, please make sure you have some spare tiles on hand. Our in-house installer Anthony <a href="https://www.solarquotes.com.au/lp/solar-tiled-roof/" target="_blank">explains why</a>.<br><br>';
		}

		$textQuoteType = grTextQuoteType($leadData['leadType'], $leadFeatures);

		switch($textQuoteType){
			case 'battery storage':
				$free_notes_text = '
					<div>To set expectations, here\'s ballpark pricing for good quality batteries, including federal rebates:<ul>
						<li>10 kWh of storage: &#36;8,000-&#36;11,000 installed</li>
						<li>13 kWh of storage: &#36;9,000-&#36;12,000 installed</li>
						<li>15 kWh of storage: &#36;10,000-&#36;13,000 installed</li>
					</ul>
					</div>
				';
				break;
			case 'solar and batteries':
				$free_notes_text = '
					<div>To set expectations, here\'s ballpark pricing for good quality solar + batteries, including federal rebates:
					<ul>
						<li>6.6kW Solar + 13 kWh battery: &#36;15,000 - &#36;20,000 installed</li>
						<li>10kW Solar + 13 kWh battery: &#36;17,000 - &#36;25,000 installed</li>
					</ul>
					</div>'
				;
				break;
			default:
				$free_notes_text = '';
				break;
		}

		if (in_array($textQuoteType, ['battery storage', 'solar and batteries']) && $l_iState === 'NSW') {
			$free_notes_text .= '<div>Those prices don\'t include the NSW government rebate. As a rough guide, you might see around &#36;150 - &#36;160 per kWh reduction. So, for a 13 kWh battery, that\'s approximately &#36;2,000 off.<br><br>For a tailored estimate, use our NSW Battery Rebate Calculator.</div>';
		}

		$data = [
			'first_name' => trim($l_fName),
			'matched_text' => $matched == 1 ? '' : ( $matched == 2 ? 'two' : 'three' ),
			'partial_matched_text' => $matched == 1 ? 'one' : ( $matched == 2 ? 'two' : 'three' ),
			'requested_quote_count' => ($l_requestedQuotes == 1 ? 'a' : $l_requestedQuotes),
			'text_quote_or_quotes' => ($l_requestedQuotes == 1 ? 'quote' : 'quotes'),
			'actual_quote_count' => $matched > 1 ? $matched : '',
			'text_solarcompany_or_companies' => ($matched == 1 ? 'solar company' : 'solar companies'),
			'text_this_these' => ($matched == 1 ? 'this' : 'these'),
			'text_this_these_company_ies_is_are' => ($matched == 1 ? 'this company is' : 'these companies are'),
			'text_company_companies_prefixed' => ($matched == 1 ? 'a company' : 'companies'),
			'text_company_companies' => ($matched == 1 ? 'company' : 'companies'),
			'text_install' => ($matched == 1 ? 'installs' : 'install'),
			'text_quote_type' => $textQuoteType,
			'installer_links' => "<a href='{$siteURLSSL}quote/unsigned.php?l={$l_record_num}&emailcode={$l_emailCode}'>{$siteURLSSL}quote/unsigned.php?l={$l_record_num}&emailcode={$l_emailCode}</a>",
			'installers_url' => "{$siteURLSSL}quote/unsigned.php?l={$l_record_num}&emailcode={$l_emailCode}",
			'text_one_two' => ( $unfilledSlots == 3 ? 'Three' : ($unfilledSlots == 2 ? 'Two' : 'One')),
			'text_installer_installers' => ($matched == 1 ? 'installer' : 'installers'),
			'text_our_referred_installers' => ($matched == 0 ? 'installers' : 'our referred installers'),
			'text_is_are' => ($unfilledSlots == 1 ? 'is' : 'are'),
			'text_an_additional' => ( $unfilledSlots == 1 ? 'an additional' : 'additional'),
			'text_got_quotes' => ($l_requestedQuotes == 1 ? 'got your quote' : "got all {$l_requestedQuotes} quotes"),
			'matched_installers_list' => "<ul>" . implode($matchedInstallersListHtml) . "</ul>",
			'finance_partners' => $financeText,
			'tile_roof_message' => $tile_roof_message,
			'vcard_text_installer_installers' => ($matched == 1 ? 'installer' : 'installers\''),
			'free_notes_text' => $free_notes_text,
			'postcode' => $l_iPostcode,
		];

		if($v4Enabled){
			$subject = '{first_name}, thanks for requesting {requested_quote_count} solar {text_quote_or_quotes}';
			$layout = file_get_contents("$phpdir/email_templates/dispatch/layout-v4.html");
			if($l_requestedQuotes == $matched){
				$body = str_replace('{content}', file_get_contents("$phpdir/email_templates/dispatch/_matched-all-v4.html"), $layout);
			} elseif ($matched === 0){
				$body = str_replace('{content}', file_get_contents("$phpdir/email_templates/dispatch/_matched-none-v4.html"), $layout);
			} else {
				$body = str_replace('{content}', file_get_contents("$phpdir/email_templates/dispatch/_matched-partial-v4.html"), $layout);
			}

			// Add specific information of v4 for email
			$missingQuotes = $l_requestedQuotes - $matched;
			$missingQuoteCount = '3 quotes';
			switch($l_requestedQuotes){
				case '1': $missingQuoteCount = 'quote'; break;
				case '2': $missingQuoteCount = '2 quotes'; break;
			}

			$ordinalMissingQuotes = '';
			if($missingQuotes == 1){
				if($l_requestedQuotes == 3)
					$ordinalMissingQuotes = 'third quote';
				else
					$ordinalMissingQuotes = 'second quote';
			} elseif($missingQuotes == 2) {
				if($l_requestedQuotes == 3)
					$ordinalMissingQuotes = 'second and third quote';
			}

			$data['company_that_is_are'] = $l_requestedQuotes > 1 ? 'companies that are' : 'a company that is';
			$data['missing_quote_count_with_text'] = $missingQuoteCount;
			$data['quote_count_with_text'] = $l_requestedQuotes . ( $l_requestedQuotes > 1 ? ' quotes' : 'quote');
			$data['that_those_quotes'] = $missingQuotes > 1 ? "those $missingQuotes quotes" : 'that quote'; 
			$data['unsigned_accept'] = $siteURLSSL . 'quote/unsigned_selection?emailcode=' . $l_emailCode . '&l=' . $l_record_num;
			$data['unsigned_skip'] = $siteURLSSL . 'thanks-unsigned-v4.html?skip=true';
			$data['matched_count_installers'] = $matched > 1 ? "$matched great installers" : '1 great installer';
			$data['ordinal_missing_quote_count'] = $ordinalMissingQuotes;
			$data['company_that_is_are'] = $missingQuotes == 1 ? 'company that is' : 'companies that are';
			$data['matched_quote_count_with_text'] = $matched == 1 ? '1 quote' : "$matched quotes";
			$data['matched_installers_list'] = "<ul>" . implode($matchedInstallersListHtml) . "</ul>";
			$data['vcard_text_installer_installers'] = ($matched == 1 ? 'installer' : 'installers\'');
		}
		$bigTitle = $subject;
		if($isEVChargerLead){
			$data['text_quote_type'] = grTextEVSolarBatteries($leadFeatures);
			if($matched>0)
				$bigTitle = "Here's who will be in touch soon to discuss {text_quote_type} for your property";
		}
		elseif($isHWHPLead){
			$data['text_quote_type'] = sgTextHwhpSolarBatteries($leadFeatures)['text_hp_quote_type'];
			$data['hp_checklist_url'] = 'https://www.solarquotes.com.au/wp-content/uploads/2025/02/checklist_hp.pdf';
			switch($data['text_quote_type']){
				case 'solar and a heat pump': 
					$data['hp_checklist_url'] = 'https://www.solarquotes.com.au/wp-content/uploads/2025/02/checklist_shp.pdf';
				break;
				case 'solar, batteries and a heat pump':
					$data['hp_checklist_url'] = 'https://www.solarquotes.com.au/wp-content/uploads/2025/02/checklist_sbhp.pdf';
				break;
				case 'batteries and a heat pump': 
					$data['hp_checklist_url'] = 'https://www.solarquotes.com.au/wp-content/uploads/2025/02/checklist_bhp.pdf';
				break;
			}
			if($matched>0)
				$bigTitle = "Here's who will be in touch soon to discuss {text_quote_type} for your property";
		}
		else {
			if($matched>0)
				$bigTitle = "Here's who will be in touch soon to discuss {text_quote_type} for your property";
		}

		$body = $emailTemplatePrepend . $body . $emailTemplateAppend;
		$data['templateFooter'] =''; 
		$data['templateAfterFooterContents'] = '';
		$data['templateSmallTitle'] = 'SOLARQUOTES<strong>.</strong>COM<strong>.</strong>AU';
		$data['templateBigTitle'] = applyTemplate($bigTitle, $data);

		$body = applyTemplate($body, $data);
		$subject = applyTemplate($subject, $data);

		if($isEVChargerLead) {
			// Finn specifically wants no attachment for EV leads
			sendMailNoTemplate($l_email, "{$l_fName} {$l_lName}", $subject, $body, $dispatchEmail, $dispatchName, 'autoresponder_mail');
			//sendMailNoTemplate('johnb@solarquotes.com.au', "{$l_fName} {$l_lName}", $subject, $body, $dispatchEmail, $dispatchName);
		}elseif($isHWHPLead){
			// Finn specifically wants no attachment for HWHP leads
			sendMailNoTemplate($l_email, "{$l_fName} {$l_lName}", $subject, $body, $dispatchEmail, $dispatchName, 'autoresponder_mail');
		}else {
			$filePath = "$phpdir/email_templates/dispatch/checklist-v14.pdf";

			sendMailWithAttachmentNoTemplate($l_email, "{$l_fName} {$l_lName}", $subject, $body, $filePath, $dispatchEmail, $dispatchName, 'autoresponder_mail');
			//sendMailWithAttachmentNoTemplate('johnb@solarquotes.com.au', "{$l_fName} {$l_lName}", $subject, $body, $filePath, $dispatchEmail, $dispatchName);
		}

		// Send notification to lead affiliate if any
		send_affiliate_lead_notification($l_referer ?? '');

		global $zenDeskSubdomain, $zenDeskEmail, $zenDeskKey, $zenDeskAssignees;
		$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
		$client->setAuth('token', $zenDeskKey);
		// Create a new ticket
		try {
			$newTicket = $client->tickets()->create([
				'subject' => 'New lead dispatched (ID: '.$l_record_num.')',
				'comment' => [
					'html_body' => '<style>#emailcontainer table tr td { border: none; }</style><h3>The following E-mail was sent to the lead when dispatched:</h3><div id="emailcontainer">'.$body.'</div>',
					'public' => false
				],
				'status' => 'solved',
				'requester' => [
					'name' => "{$l_fName} {$l_lName}",
					'email' => $l_email,
					'verified' => true
				]
			]);
		} catch (Exception $e) {
			echo $e->getMessage();
			error_log("Caught $e");
		}

		if($leadData['newsletter'] == 'Yes'){
			AddLeadToBrevo($leadId, 'sq_news_weekly', 'Quoting System');
		}
		
		return $supplierCount;
	}

	/**
	* Dispatch leads to all claiming suppliers
	*
	* @param mixed $leadId
	*/
	function dispatchFree($leadId = 0, $leadType = 'Residential'){
		global $nowSql, $siteURLSSL, $_connection, $zenDeskSubdomain, $zenDeskEmail, $zenDeskKey;

		$SQL = " SELECT * FROM global_data WHERE description LIKE '%\"lead_id\":$leadId%' and name = 'unsignedassign'; ";
		$gd_result = db_query($SQL);

		while($global_data = mysqli_fetch_array($gd_result, MYSQLI_ASSOC)){
			$leadPrice = 0;
			$manualLead = 'N';
			$suId = $global_data['id'];

			db_query("INSERT INTO lead_suppliers SET lead_id='{$leadId}', type='unsigned', supplier='{$suId}', dispatched={$nowSql}, status='sent', leadPrice=0, priceType='{$leadType}';");
			$lsid = mysqli_insert_id($_connection);

			$leadData = loadLeadData($leadId);
			$data = array_merge($leadData, loadUnsignedData($suId));

			// Set email data for main contact - new V4 changes for backward compatibility
			$data['sFName'] = $data['sMainContactfName'];
			$data['sLName'] = $data['sMainContactlName'];
			$data['sEmail'] = $data['sMainContactEmail'];
			$data['sContact'] = $data['sFName'] . ' ' . $data['sLName'];

			//sendTemplateEmail('johnb@solarquotes.com.au', $data['sContact'], 'unsignedQuote', $data, $data['email'], "{$data['fName']} {$data['lName']}");
			sendTemplateEmail($data['sEmail'], $data['sContact'], 'unsignedQuote', $data, $data['email'], "{$data['fName']} {$data['lName']}");

			// Delete global data entry
			db_query('DELETE FROM global_data WHERE type = "unsignedsupplier" AND name = "unsignedassign" AND id = ' . $suId);

			$count = db_getVal('SELECT count(*) AS count FROM lead_suppliers WHERE type = "unsigned" AND supplier = ' . $suId);

			if($count == 20){
				$agent_id = db_getVal('SELECT a.zendeskid FROM settings s INNER JOIN admins a ON s.dispatch_v4_notify_agent = a.record_num;');
				$company = db_getVal('SELECT company FROM suppliers_unsigned WHERE record_num = ' . $suId);

				$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
				$client->setAuth('token', $zenDeskKey);

				$subject = sprintf('%s just received %d leads', $company, $count);
				$body = sprintf('%s just received %d leads. This is a notification only, no changes occurred on the supplier account.', $company, $count);

				// Create a new ticket
				$ticket = $client->tickets()->create([
					'tags' => ['unsigned-supplier-20-leads'],
					'subject' => $subject,
					'comment' => [
						'html_body' => $body,
						'public' => false
					],
					'priority' => 'normal',
					'assignee_id' => $agent_id
				]);	
			}
		}
	}

	/**
	* Returns a total price, given a lead information array, and a pricing structure array.
	*
	* The total price is determined by adding the base price for the lead system, and the highest individual rule matched within that system.
	*
	* @param array $lead
	* @param array $pricingStructure
	* @return number
	*/
	function findPriceWithLeadInformation($lead, $pricingStructure) {
		$basePrice = $pricingStructure['BasePrice'];

		// Could we found any rules for this system?
		$rules = $pricingStructure['Items'];
		$topRulePrice = NULL;
		$lineText = NULL;
		if (isset($rules)) {
			// Check rules for additional price.
			foreach ($rules as $rule) {
				// Check each rule:
				$template = $rule['Template'];
				$add = $rule['Add'];
				$price = $rule['Price'] + ( $add ? $basePrice : 0) ;

				// Match?
				if (doesLeadInformationSatisfyTemplate($lead, $template)) {
					// Rule matches the lead information!
					// Is this a new top price?
					if ($topRulePrice == NULL || $topRulePrice < $price) {
						$topRulePrice = $price;
						$lineText = $rule['LineText'];
					}
				}
			}
		}

		// Add base price to top rule price, if found.
		if ($topRulePrice != NULL)
			$totalPrice = $topRulePrice;
		else
			$totalPrice = $basePrice;

		$leadPrice = ( $totalPrice > $basePrice ? $totalPrice : $basePrice );
		return ($leadPrice == $basePrice ? $leadPrice : array($leadPrice, $lineText));
	}

	/**
	* Determines wheter given lead information in a flat array satisfies the conditions in a template.
	*
	* Converts lead keys and values into an array of keyvalues (i.e. key.value).
	* These are compared against the template to see if the conditions are met.
	*
	* @param array $lead
	* @param array $template
	* @return boolean
	*/
	function doesLeadInformationSatisfyTemplate($lead, $template) {
		// Assume no match until verified.
		$isMatch = false;

		// Prepare lead values for use in formula.
		$leadKeyvalues = array();


		foreach($lead as $key => $value) {
			if(is_array($value)){
				foreach($value as $sub_key => $sub_value){
					if(in_array($key, array('Features'))){
						array_push($leadKeyvalues, str_replace(array(' ', ':'), '', $key).'.'.$sub_value);
					} else {
						array_push($leadKeyvalues, str_replace(array(' ', ':'), '', $sub_key).'.'.$sub_value);
					}
				}

			}else
				array_push($leadKeyvalues, str_replace(array(' ', ':'), '', $key).'.'.$value);
		}
		// Break apart template to components.
		$formula = parseFormulaStringIntoArray($template);

		// Convert formula into a array of strings that make valid boolean logic.
		$booleanFormulaOneLiner = convertFormulaElementsToBoolean($formula, $leadKeyvalues);

		// Evaluate the formula.
		eval("\$result=$booleanFormulaOneLiner;");
		if (isset($result) && $result == 1)
			$isMatch = 1;

		return $isMatch;
	}

	/**
	* Replaces keyvalues in a multi-dimensional formula with a corresponding boolean expression.
	*
	* This function converts:
	* - AND to &&
	* - OR to ||
	* - everything else to a 1 or 0 depending if a match is found in $leadKeyvalues
	* It combinines the resulting array of boolean expressions into one executable boolean "one-liner".
	*
	* The results can be printed, but are meant to be executed by PHP eval.
	*
	* @param array $formula
	* @param array $leadKeyvalues
	* @return string
	*/
	function convertFormulaElementsToBoolean($formula, $leadKeyvalues) {
		$booleanFormula = array();

		// Here is the tricky part...
		// We are going to convert this formula into a boolean expression for PHP to run using eval.
		for($i=0; $i<count($formula); $i++) {
			$keyvalue = $formula[$i];
			switch($keyvalue) {
				// 1) Convert Operators
				//  - Convert AND, OR and NOT into code equivalent
				case 'AND':
					array_push($booleanFormula, '&&');
					break;
				case 'OR':
					array_push($booleanFormula, '||');
					break;
				case 'NOT':
					array_push($booleanFormula, '!');
					break;
					// 2) Convert Keyvalues
					//  - Replace a value with a 1 if it is matched by lead, 0 if not
				default:
					if(is_array($keyvalue)) {
						// Recursion to go into arrays (aka parenthesis).
						array_push($booleanFormula, '(');
						array_push($booleanFormula, convertFormulaElementsToBoolean($keyvalue, $leadKeyvalues));
						array_push($booleanFormula, ')');
					} else {
						// 1 if matches lead info, 0 if not
						array_push($booleanFormula, in_array($keyvalue, $leadKeyvalues) ? 'true' : 'false');
					}
					break;
			}
		}

		// Create the formula one liner of code to execute.
		return implode(' ',$booleanFormula);
	}

	/**
	* Replaces a formula string with an array of components in the formula.
	*
	* Each component must be seperated by a space or parentesis.
	* Brackets result in an array being added to the current array (a dimension is added).
	* Any elements inside those brackets, are then added to the array.
	*
	* The results can be printed, but are meant to be executed by PHP eval.
	*
	* @param string $formulaString
	* @return array
	*/
	function parseFormulaStringIntoArray($formulaString) {
		$split_pattern = '/( AND | OR |\\(|\\))/m';
		$formula_content = preg_split($split_pattern, $formulaString, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		return array_map(function($a){ return trim($a); }, $formula_content);
	}

	function supplierSizeOrderBy($orderBy) {
		if ($orderBy == '')
			return "ORDER BY S.priority ASC;";
		else {
			if ($orderBy == 'Smaller') {
				$return = "ORDER BY (CASE WHEN S.companySize = 'S' THEN 0 ";
				$return .= "WHEN S.companySize = 'M' THEN 1 ";
				$return .= "WHEN S.companySize = 'L' THEN 2 END), S.priority ASC;";

				return $return;
			} elseif ($orderBy == 'Larger') {
				$return = "ORDER BY (CASE WHEN S.companySize = 'L' THEN 0 ";
				$return .= "WHEN S.companySize = 'M' THEN 1 ";
				$return .= "WHEN S.companySize = 'S' THEN 2 END), S.priority ASC;";

				return $return;
			} else {
				return "ORDER BY S.priority ASC;";
			}
		}
	}

	function leadSpreading($lead, $default = null){
		$default = $default ?? 'ORDER BY S.priority ASC;';

		// First check for postcode and state ( Fastest checks )
		$count = db_getVal("SELECT GROUP_CONCAT(record_num) FROM dispatch_regions WHERE ((type = 'postcode' AND details = '{$lead['iPostcode']}') OR (type = 'state' AND details = '{$lead['iState']}')) AND status = 'active'" );

		if($count && $leadSpreadingConds = leadSpreadingConds($count))
			return ['orderby'=>"ORDER BY RAND() ASC;", 'conds'=>$leadSpreadingConds, 'spreading_region_ids'=>$count];

		$lat = $lead['latitude'];
		$lng = $lead['longitude'];

		// Now check the circle since it's also direct - but more performance intensive than the postcode
		$count = db_getVal("SELECT GROUP_CONCAT(dr.record_num) FROM dispatch_regions dr WHERE floor(( 6371 * acos( cos( radians($lat) ) * cos( radians( SUBSTRING_INDEX(center, '|', 1) ) ) * cos( radians(SUBSTRING_INDEX(center, '|', -1)) - radians($lng)) + sin(radians($lat)) * sin( radians(SUBSTRING_INDEX(center, '|', 1)))))) <= dr.radius and dr.type = 'circle' and dr.status = 'active';");

		if($count && $leadSpreadingConds = leadSpreadingConds($count))
			return ['orderby'=>"ORDER BY RAND() ASC;", 'conds'=>$leadSpreadingConds, 'spreading_region_ids'=>$count];

		// Now check polygons - we'll use the first step like the circle to narrow down the possibilities
		$polys = db_query("SELECT * FROM dispatch_regions dr WHERE floor(( 6371 * acos( cos( radians($lat) ) * cos( radians( SUBSTRING_INDEX(center, '|', 1) ) ) * cos( radians(SUBSTRING_INDEX(center, '|', -1)) - radians($lng)) + sin(radians($lat)) * sin( radians(SUBSTRING_INDEX(center, '|', 1)))))) <= dr.radius and dr.type = 'polygon' and dr.status = 'active';");

		while ($poly = mysqli_fetch_array($polys, MYSQLI_ASSOC)) {
			// Now check each polygon to see if the lead is really inside them
			$inRegion = geoPointInPolyArea($lat, $lng, str_replace(' ', '|', $poly['details']));
			if($inRegion && $leadSpreadingConds = leadSpreadingConds($poly['record_num']))
				return ['orderby'=>"ORDER BY RAND() ASC;", 'conds'=>$leadSpreadingConds, 'spreading_region_ids'=>$poly['record_num']];
		}

		return ['orderby'=>$default, 'conds'=>''];	
	}

	function leadSpreadingConds($dispatch_regions) {
		$suppliersQuery = db_query("SELECT supplier_id FROM suppliers_lead_spreading WHERE dispatch_region_id IN ({$dispatch_regions})");
		$suppliers = [];
		while ($s = mysqli_fetch_array($suppliersQuery, MYSQLI_ASSOC)) {
			$suppliers[] = $s["supplier_id"];
		}
		if(empty($suppliers))
			return false;
		return " AND S.record_num IN (".implode(",", $suppliers).") ";
	}

	function orderByEntityIdsArray($entitiesArray, $defaultOrderBy) {
		// if entitiesArray is not empty, change defaultOrderBy to put the corresponding
		// entity(or entities) at the beggining of the testing/dispatching process
		if(!$entitiesArray)
			return $defaultOrderBy;
		$prepend = "ORDER BY FIELD(S.entity_id,'" . implode("','", $entitiesArray) . "') DESC";
		return $prepend . str_ireplace("ORDER BY", ",", $defaultOrderBy);
	}

	function getEntityIdsArray($requestedInstallerEntity) {
		// if requestedInstallerEntity is a parent, returns an array of all their children entity_ids
		// if not, the array will contain only the supplier entity_id
		$result = [];
		if($requestedInstallerEntity) {
			$query = db_query("SELECT entity_id FROM suppliers WHERE entity_id='{$requestedInstallerEntity}' UNION SELECT S.entity_id FROM suppliers S INNER JOIN suppliers_parent SP ON S.parent=SP.record_num WHERE SP.entity_id='{$requestedInstallerEntity}'");
			while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
				$result[] = $row['entity_id'];
			}
		}
		return $result;
	}

	function checkIfRequestedSupplierWasMatched($requestedInstallerEntitiesArray, $suppliers, $l_record_num) {
		global $techEmail, $techName, $zenDeskSubdomain, $zenDeskEmail, $zenDeskKey, $siteURLSSL;
		if(!$requestedInstallerEntitiesArray) // no specific supplier was requested, just return
			return;
		if(is_array($suppliers) && is_array($requestedInstallerEntitiesArray)) {
			$query = db_query("SELECT entity_id FROM suppliers WHERE record_num IN ('".implode("','", $suppliers)."')");
			while ($matchedSupplier = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
				if(in_array($matchedSupplier['entity_id'], $requestedInstallerEntitiesArray))
					return; // lead was matched with requested supplier (or parents child), nothing to do
			}
		}
		$agent_id = db_getVal("SELECT zendeskid FROM admins WHERE email = 'johnb@solarquotes.com.au';");

		$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
		$client->setAuth('token', $zenDeskKey);

		$subject = "Dispatch issue";
		$body = "<a href=\"{$siteURLSSL}leads/lead_view?lead_id={$l_record_num}\">Lead {$l_record_num}</a> requested a quote from a specific supplier, but the match didn't happen.";

		// Disabling just the zendesk ticket creation for now.  This code might still be helpful in the future
		/*
		$ticket = $client->tickets()->create([
			'tags' => ['requested-supplier-not-matched'],
			'subject' => $subject,
			'comment' => [
				'html_body' => $body,
				'public' => false
			],
			'priority' => 'normal',
			'assignee_id' => $agent_id
		]);
		*/
	}

	/** Returns an array of supplier ids that SHOULD NOT be considered as installers for a particular lead that could
	 * otherwise be a valid target meaning this doesn't exclude based on caps, area, options selected, etc
	 * @param $lead_id - Id of the lead that will be checked to create the invalid Suppliers List
	 */
	function getInvalidSuppliersList($lead_id){
		global $_connection, $nowSql;
		// List of location suffixes, the expanded ones ( Like Road ) will be checked before to prevent any replace issues
		$replaceSuffixes = [
			'Alley', 'Arcade', 'Avenue', 'Boulevard', 'Circuit', 'Close', 'Corner', 'Court',
			'Crescent', 'Cul-de-sac', 'Drive', 'Esplanade', 'Green', 'Grove', 'Highway', 'Junction',
			'Parade', 'Place', 'Ridge', 'Road', 'Square', 'Street', 'Terrace',
			'Ally',  'Arc',  'Ave', 'Bvd', 'Bypa', 'Cct', 'Cl', 'Bypass', 'Crn',
			'Ct', 'Cres', 'Cds', 'Dr', 'Esp', 'Grn', 'Gr', 'Hwy', 'Jnc',
			'Lane', 'Link', 'Mews', 'Pde', 'Pl', 'Rdge', 'Rd', 'Sq', 'St', 'Tce'
		];

		$lead = loadLeadData($lead_id);
		$leadType = getSimplifiedLeadType($lead);

		$SQL = ' SELECT l.record_num, l.iAddress, l.leadType, l.systemDetails, ';
		$SQL .= ' GROUP_CONCAT(s.record_num) suppliers FROM leads l ';
		$SQL .= ' INNER JOIN lead_suppliers ls on l.record_num = ls.lead_id ';
		$SQL .= ' INNER JOIN suppliers s on s.record_num = ls.supplier ';
		$SQL .= ' WHERE l.iPostcode IN (  ';
		$SQL .= " 	SELECT iPostcode FROM leads WHERE record_num = $lead_id ";
		$SQL .= ' ) ';
		$SQL .= " 	AND created > $nowSql - INTERVAL 5 YEAR ";
		$SQL .= ' GROUP BY l.record_num; ';

		// Select the lead details
		$result = db_query($SQL);

		$invalidSuppliers = [];
		$oldLeadsIds = [];
		$replaceLeadAddress = str_replace($replaceSuffixes, '', $lead['iAddress']);
		while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
			$replaceRowAddress = str_replace($replaceSuffixes, '', $row['iAddress']);
			$addressMatch = strtolower($replaceLeadAddress) === strtolower($replaceRowAddress);
			if($addressMatch && getSimplifiedLeadType($row) === $leadType){
				$invalidSuppliers= array_merge($invalidSuppliers, explode(',', $row['suppliers']));
				$oldLeadsIds[] = $row['record_num'];
			}
		}
		/* Now look for suppliers that were excluded through the LM */
		$SQL  = "SELECT description FROM global_data WHERE id = $lead_id";
		$SQL .= " AND type='lead' AND name='leadexcludesuppliers' LIMIT 1;";
		$leadexcludesuppliers = db_getVal($SQL);
		if($leadexcludesuppliers && $leadexcludesuppliers != "")
			$invalidSuppliers = array_merge($invalidSuppliers, explode(',', $leadexcludesuppliers));
		return [$oldLeadsIds, array_unique($invalidSuppliers)];
	}

	/*
	*	Returns the "simplified" lead type, one of ['Solar only',
	*	'Hybrid', 'Add batteries', 'Add panels', 'Commercial', 'EV Chargers']
	*/
	function getSimplifiedLeadType($lead) {
		if($lead['leadType']==='Commercial')
			return $lead['leadType'];
		if(!array_key_exists('rawsystemDetails', $lead))
			$lead['rawsystemDetails'] = $lead['systemDetails'];
		$pricing = strtolower(implode("", leadPricingOptions($lead)));
		if(strpos($pricing, 'ev charger')!==false)
			return 'EV Chargers';	// regardless of including solar and/or batteries
		if(strpos($pricing, 'hwhp')!==false)
			return 'HWHP';	// regardless of including solar and/or batteries
		if(strpos($pricing, 'upgrade')!==false)
			return 'Add panels';
		if(strpos($pricing, 'add batteries')!==false)
			return 'Add batteries';
		if(strpos($pricing, 'off grid')!==false || strpos($pricing, 'hybrid')!==false)
			return 'Hybrid';	// hybrid includes off-grid
		return 'Solar only';	// either on grid or battery-ready
	}

	/*
	*	Add a note indicating that suppliers were skipped because 
	*	they were already assigned to this lead in the past, if needed
	*/
	function addNoteSkippedSuppliers($suppliers, $oldLeadsIds, $l_record_num) {
		if(count($suppliers)==0 && !empty($oldLeadsIds)) {
			$note = "Dispatch skipped installers who already received this lead as ref. ";
			$note .= implode(", ", $oldLeadsIds) . ".";
			db_query("UPDATE leads SET notes = CONCAT(notes, (IF(notes='', '', '\n\n')),'{$note}') WHERE record_num='{$l_record_num}'");
		}
	}
	/*
	*	Lead/Dispatch Quality Assurance
	*	Performs final lead/dispatch checks
	*/
	function dispatchQA($leadData, $options=[]) {
		global $siteURLSSL, $states, $techEmail, $techName;
		$revertStatus = 'sending'; // If the lead fails QA check what status should we inform the Tech team to revert to from the "locked" status
		$issues = [];
		if(!array_key_exists('requestedQuotes', $leadData) || $leadData['requestedQuotes']==="" || $leadData['requestedQuotes']===null)
			$issues[] = "Requested quotes info missing or null";
		if(array_key_exists('requestedQuotes', $leadData) && $leadData['requestedQuotes'] > 3)
			$issues[] = "Requested more than 3 quotes (".$leadData['requestedQuotes'].")";
		if(array_key_exists('leadClaims', $options)) {
			$supplierIds = [];
			foreach($options['leadClaims'] as $leadClaim) {
				$supplierId = $leadClaim['supplier'];
				if(in_array($supplierId, $supplierIds)) {
					$issues[] = "Duplicate supplier " . $supplierId . " in the lead_claims table";
				}
				$supplierIds[] = $supplierId;
			}
			if(array_key_exists('requestedQuotes', $leadData)) {
				$leadClaimsCount = count($options['leadClaims']);
				if($leadClaimsCount > $leadData['requestedQuotes']) {
					$issues[] = "Requested ".$leadData['requestedQuotes']." quotes, but was assigned to ".$leadClaimsCount." suppliers";
				}
			}
		}
		if(array_key_exists('revertStatus', $options))
			$revertStatus = $options['revertStatus'];
		if(!isset($leadData['iState']) || !in_array($leadData['iState'], array_keys($states)))
			$issues[] = "Missing or invalid state";
		if(!isset($leadData['iCity']) || $leadData['iCity'] === '')
			$issues[] = "Missing city";
		if($leadData['mapStatus']!=='found' && !($leadData['mapStatus']==='noGeoCode' && strpos($leadData['leadType'], "Repair")===0))
			$issues[] = "Address not found";
		if(!isset($leadData['latitude']) || $leadData['latitude'] === '')
			$issues[] = "Missing latitude";
		if(!isset($leadData['longitude']) || $leadData['longitude'] === '')
			$issues[] = "Missing longitude";

		// Get the lead price to see if it's valid
		$invalidPrice = db_getVal("SELECT COUNT(*) FROM lead_claims WHERE lead_id = {$leadData['leadId']} AND (leadPrice IS NULL OR leadPrice = 0);");

		if ($invalidPrice > 0)
			$issues[] = "Invalid lead price";
		
		if (!empty($issues)) {
			db_query("UPDATE leads SET status = 'locked' WHERE record_num = ".$leadData['record_num']." LIMIT 1;");
			$body = '<p>The dispatch QA function detected issues in a lead/dispatch:</p>';
			$body .= '<ul><li>'.(implode("</li><li>", $issues)).'</li></ul>';
			$body .= '<p><strong>Lead Information</strong></p>';
			$body .= '<ul><li>Name: ' . $leadData['fName'] . ' ' . $leadData['lName'] . '</li>';
			$body .= '<li>Link: ' . $siteURLSSL . 'leads/lead_view?lead_id=' . $leadData['record_num'] . '</li>';
			$body .= '</ul><p>The lead is now <strong>locked</strong>. Once this issue is solved, please manually set it\'s status to <strong>sending</strong> again. No installers were notified about this lead yet.</p>';
			SendMail($techEmail, $techName, 'Dispatch QA just locked a lead', $body);
			return true;
		}
		return false; // Passed all checks
}

	/*
	*/
	function supplierIsCapLimited($supplierData, $leadData){
		// Get the Lead Type that will be used going forward ( Highest value lead type )
		list($leadType, $leadCost) = array_values(leadPricingInfo($supplierData, $leadData));
		extract($supplierData, EXTR_PREFIX_ALL, 's');

		$SQL = "SELECT clt.record_num cap_id, sclt.maxLimit max, lt.typeName, clt.title, clt.length, clt.is_claim, clt.is_internal ";
		$SQL .= "FROM supplier_cap_limit_by_type sclt ";
		$SQL .= "INNER JOIN suppliers s ON sclt.supplier_id = s.record_num ";
		$SQL .= "INNER JOIN cap_limit_type clt ON sclt.capType_id = clt.record_num ";
		$SQL .= "INNER JOIN lead_types lt ON sclt.leadType_id = lt.record_num ";
		$SQL .= "WHERE sclt.status = 'Enabled' AND lt.status = 'Enabled' ";
		$SQL .= "	AND s.record_num = '{$s_record_num}' ";
		$SQL .= "	AND lt.typeName = '{$leadType}' ";

		// Check the limits - Non Claim
		$limits = db_query($SQL);
		return checkSupplierCapLimits($limits, $s_record_num, ['leadType' => $leadType]);
	}

	function checkSupplierCapLimits($limits, $supplierId, array $options = []){
		$useInternals = $options['useInternals'] ?? true; // Reuse and Estimator have the internals removed from the titles
		$limitType = $options['limitType'] ?? 'residential';
		$leadType = $options['leadType'] ?? null;

		$capTitles = ['Daily Leads', 'Weekly Leads', 'Monthly Leads', 'Daily Leads (Internal)', 'Weekly Leads (Internal)', 'Monthly Leads (Internal)'];
		if(!$useInternals)
			$capTitles = array_diff($capTitles, ['Daily Leads (Internal)', 'Weekly Leads (Internal)', 'Monthly Leads (Internal)']);

		global $nowSql;

		while ($row = mysqli_fetch_assoc($limits)) {
			$elements = [];
			// 0 - Is unlimited
			if ($row['max'] != 0 && !in_array($row['title'], ['Daily Leads', 'Weekly Leads', 'Monthly Leads', 'Daily Leads (Internal)', 'Weekly Leads (Internal)', 'Monthly Leads (Internal)'])) {
				$elements["cap_{$row['cap_id']}"] = "AND l.submitted > ( {$nowSql} - INTERVAL {$row['length']} HOUR) ";
			} else {
				$limit_name = str_replace(' ', '_', strtolower($row['title']));
				if (stripos($limit_name, 'daily') !== false) {
					$elements["cap_" . $limit_name] = "AND DATE_FORMAT(l.submitted, '%Y%m%d') = DATE_FORMAT({$nowSql}, '%Y%m%d') ";
				} elseif (stripos($limit_name, 'weekly') !== false) {
					$elements["cap_" . $limit_name] = "AND DATE_FORMAT(l.submitted, '%Y%u') = DATE_FORMAT({$nowSql}, '%Y%u') ";
				} else {
					$elements["cap_" . $limit_name] = "AND DATE_FORMAT(l.submitted, '%Y%m') = DATE_FORMAT({$nowSql}, '%Y%m') ";
				}
			}

			$SQL = "SELECT COALESCE(SUM(maxed), 0) FROM ";
			$SQL .= "( ";
			$SQL .= "SELECT 1, COUNT(*) AS maxed FROM lead_suppliers ls ";
			$SQL .= "LEFT JOIN leads l ON l.record_num = ls.lead_id WHERE ls.supplier = '{$supplierId}' AND ls.type='regular' AND ls.status!='scrapped' " . implode(' AND ', $elements);
			$SQL .= " AND ls.extraLead = 'N' ";
			if($leadType)
				$SQL .= " AND ls.priceType = '{$leadType}' ";
			else
				$SQL .= " AND ls.priceType " . ($limitType=='residential'?'NOT':'') . " LIKE 'commercial' ";

			$SQL .= "UNION ";
			$SQL .= "SELECT 2, COUNT(*) AS maxed FROM lead_claims lc LEFT JOIN leads l ON l.record_num = lc.lead_id WHERE lc.supplier = '{$supplierId}' AND lc.claimed >= ({$nowSql} - INTERVAL 4 DAY) AND lc.extraLead = 'N' AND l.status NOT IN ('duplicate', 'incomplete') ";
			if($leadType)
				$SQL .= " AND lc.priceType = '{$leadType}' ";
			else
				$SQL .= " AND lc.priceType " . ($limitType=='residential'?'NOT':'') . " LIKE 'commercial' ";
			$SQL .= ") t;";

			$count = db_getVal($SQL);

			// Supplier exceeds the cap limits set
			if ($count >= $row['max']){
				return true;
			}
		}
		return false;
	}

	function send_affiliate_lead_notification($l_referer) {
		if (isset($l_referer) && !empty($l_referer)) {
			global $affiliatesName, $affiliatesEmail;
			$affSQL = "SELECT * FROM affiliate_contacts WHERE affiliate_id = ( SELECT initialAffiliate FROM leads_referers WHERE record_num = '$l_referer' )";
			$affContacts = db_query($affSQL);
			while ($contact = mysqli_fetch_array($affContacts, MYSQLI_ASSOC)) {
				if ($contact['lead_notifications'] != 'Y') continue;
				if (empty($contact['email']) || !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) continue;
				$affiliate_id = $contact['affiliate_id'];
				$secret = md5('||'.$contact['email'].'*'.$affiliate_id.'||');
				$key = base64_encode(json_encode(['email' => $contact['email'], 'affiliate_id' => $affiliate_id, 'secret' => $secret]));
				$data['affFName'] = $contact['first_name'];
				$data['affUnsubLink'] = 'https://www.solarquotes.com.au/leads/unsubscribe_affiliate_lead_notifications?key='.$key;
				sendTemplateEmail($contact['email'], $contact['first_name'], 'affiliateLead', $data, $affiliatesEmail, $affiliatesName, 'affiliates_mail');
			}
		}
	}

	function isNSWRebateEligible($leadData, $pricingOptions) {
		if ($leadData['iState'] != 'NSW') return false;

		// 'Battery Ready' initially not accepted for NSW Rebate
		$acceptedBatteryTypes = ['Hybrid Systems', 'Add Batteries to Existing', 'EV charger + solar and / or battery'];
		foreach ($pricingOptions as $pricingOption) {
			if (in_array($pricingOption, $acceptedBatteryTypes)) {
				return true;
			}
		}
		return false;
	}

	function notifyLeadManualProcess($leadData){
		global $dispatchEmail, $dispatchName;

		sendTemplateEmail(
			$leadData['email'],
			$leadData['fName'] . ' ' . $leadData['lName'],
			'manualProcess',
			$leadData,
			$dispatchEmail,
			$dispatchName
		);

		if(!empty($leadData['phone'])){
			$rawPhone = preg_replace('/[^0-9+]/', '', $leadData['phone']);
			$phone = $rawPhone;

			// Sanitize the phone number for validation
			$sanitizedPhone = preg_replace('/^\\+61/', '0', $phone);
			$sanitizedPhone = preg_replace('/^0000061/', '0', $sanitizedPhone);

			// Validate mobile prefix
			$validMobilePrefixes = ['04'];
			$isMobile = in_array(substr($sanitizedPhone, 0, 2), $validMobilePrefixes);

			if ($isMobile) {
				// Format for sending (international format)
				if(strpos($phone, '+') === false){
					$phone = '+61' . ltrim($phone, '0');  // Avoid leading zero if already present
				}

				$sms = sprintf(
					"%s, thanks for requesting quotes from SolarQuotes. We weren't able to automatically match your quote request with the number of installers you asked for - which means it's time for humans to step in. This should be completed within one business day - thanks for your patience!",
					$leadData['fName']
				);
				sendSMS($phone, $sms);
			}
		}
	}
?>
