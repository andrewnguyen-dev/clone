<?php

	require("global.php");
	
	// Overwrite limits here
	ini_set('memory_limit', '2048M');
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
	set_time_limit(600);
	
	// Setup arrays
	$leadArray = array();
	$leadSupplierArray = array();
	$supplierArray = array();
	$supplierParentArray = array();
	$feedbackArray = array();
	
	
	// Setup variables
	$loopValue = 1;
	$loopIncrements = 1000;
	$file;


	// Open file
	fileOpen();
	fileWriteHeader();


	// Pull some data only once
	$SQL = "SELECT * FROM suppliers ORDER BY record_num ASC";
	$suppliers = db_query($SQL);
	
	$SQL = "SELECT * FROM suppliers_parent ORDER BY record_num ASC";
	$supplierParents = db_query($SQL);
	
	
	// Populate one-off arrays
	dbStaticQueryToArray();

	
	for ($i = 0; $i < 200; $i++) {
		// Reset variables
		$leadArray = array();
		$leadSupplierArray = array();
		$feedbackArray = array();
		
		$loopValueMin = $loopValue;
		$loopValueMax = $loopValueMin + $loopIncrements - 1;
		
		
		// Populate some data over a period of loops
		$SQL = "SELECT * FROM leads WHERE record_num BETWEEN {$loopValueMin} AND {$loopValueMax} ORDER BY record_num ASC";
		$leads = db_query($SQL);
		
		$SQL = "SELECT * FROM lead_suppliers WHERE lead_id BETWEEN {$loopValueMin} AND {$loopValueMax} ORDER BY record_num ASC";
		$leadSuppliers = db_query($SQL);
		
		$SQL = "SELECT * FROM feedback WHERE lead_id BETWEEN {$loopValueMin} AND {$loopValueMax} ORDER BY record_num ASC";
		$feedbacks = db_query($SQL);
		
		
		// Convert to array
		dbDynamicQueryToArray();
		
		
		// Increment loop
		$loopValue += $loopIncrements;
		
		
		// Clean up
		mysqli_free_result($leads);
		mysqli_free_result($leadSuppliers);
		mysqli_free_result($feedbacks);
		
		
		// Output data
		processLeads();
	}
	
	
	// Clean up
	fileClose();
		
	
	// Helper functions
	function dbDynamicQueryToArray() {
		GLOBAL $leadArray, $leadSupplierArray, $feedbackArray;
		GLOBAL $leads, $leadSuppliers, $feedbacks;
		
		while ($leadSupplier = mysqli_fetch_array($leadSuppliers, MYSQLI_ASSOC)) {
			extract($leadSupplier, EXTR_PREFIX_ALL, 'ls');
			
			$leadSupplierArraySingle = array();
			$leadSupplierArraySingle['record_num'] = $ls_record_num;
            $leadSupplierArraySingle['lead'] = $ls_lead_id;
            $leadSupplierArraySingle['supplier'] = $ls_supplier;
            
			$leadSupplierArray[] = $leadSupplierArraySingle;
		}
		
		while ($feedback = mysqli_fetch_array($feedbacks, MYSQLI_ASSOC)) {
			extract($feedback, EXTR_PREFIX_ALL, 'f');
			
			$feedbackArraySingle = array();
			$feedbackArraySingle['record_num'] = $f_record_num;
			$feedbackArraySingle['lead'] = $f_lead_id;
			$feedbackArraySingle['supplier'] = $f_supplier_id;
			$feedbackArraySingle['supplierName'] = selectSupplierName($feedbackArraySingle['supplier']);
			$feedbackArraySingle['purchased'] = $f_purchased;
			$feedbackArraySingle['rateValue'] = $f_rate_value;
			$feedbackArraySingle['rateSystemQuality'] = $f_rate_system_quality;
			$feedbackArraySingle['rateInstallation'] = $f_rate_installation;
			$feedbackArraySingle['rateCustomerService'] = $f_rate_customer_service;
			$feedbackArraySingle['rateOneYearValue'] = $f_one_year_rate_value;
			$feedbackArraySingle['rateOneYearSystemQuality'] = $f_one_year_rate_system_quality;
			$feedbackArraySingle['rateOneYearInstallation'] = $f_one_year_rate_installation;
			$feedbackArraySingle['rateOneYearCustomerService'] = $f_one_year_rate_customer_service;
			
			$feedbackArray[] = $feedbackArraySingle;
		}
		
		while ($lead = mysqli_fetch_array($leads, MYSQLI_ASSOC)) {
			extract($lead, EXTR_PREFIX_ALL, 'l');

			$leadArraySingle = array();
			$leadArraySingle['record_num'] = $l_record_num;
			$leadArraySingle['iCity'] = $l_iCity;
			$leadArraySingle['iState'] = $l_iState;
			$leadArraySingle['iPostcode'] = $l_iPostcode;
			$leadArraySingle['latitude'] = $l_latitude;
			$leadArraySingle['longitude'] = $l_longitude;
			$leadArraySingle['submitted'] = $l_submitted;
			$leadArraySingle['fromWidget'] = $l_fromWidget;
			$leadArraySingle['Timeframe'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'Timeframe for purchase:');
			$leadArraySingle['homeVisit'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'Asked for home visit?');
			$leadArraySingle['finance'] = selectFeatureYesNo($l_systemDetails, 'Finance / Payment Plan');
			$leadArraySingle['supplierCount'] = selectSupplierCount($leadArraySingle['record_num']);
			$leadArraySingle['primaryResidence'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'Is This the Primary Residence?');
			$leadArraySingle['ownRoofSpace'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'Do you own the roofspace?');
			$leadArraySingle['roofType'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'Type of Roof:');
			$leadArraySingle['roofNorthFacing'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'At least 10 square metres North-facing?');
			$leadArraySingle['roofSlope'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'Approximate roof slope:');
			$leadArraySingle['shade'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'Shade on north-facing roof:');
			$leadArraySingle['direction'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'Exact direction:');
			$leadArraySingle['storeys'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'How many storeys?');
			$leadArraySingle['systemSize'] = selectDetail($l_systemDetails, $l_quoteDetails, $l_siteDetails, $l_rebateDetails, 'System Size:');
			
			$feedback = selectFeedbackDetails($leadArraySingle['record_num']);
			if ($feedback == '') {
				$leadArraySingle['reviewLeft'] = 'N';
				$leadArraySingle['reviewCompany'] = '';
				$leadArraySingle['reviewRating'] = '';			
				$leadArraySingle['reviewBuySystem'] = '';
			} else {
				$leadArraySingle['reviewLeft'] = 'Y';
				$leadArraySingle['reviewCompany'] = $feedback['supplierName'];
				
				if ($feedback['rateOneYearValue'] == '')
				    $leadArraySingle['reviewRating'] = calculateReview($feedback['rateValue'], $feedback['rateSystemQuality'], $feedback['rateInstallation'], $feedback['rateCustomerService']);
				else
					$leadArraySingle['reviewRating'] = calculateReview($feedback['rateOneYearValue'], $feedback['rateOneYearSystemQuality'], $feedback['rateOneYearInstallation'], $feedback['rateOneYearCustomerService']);

				$leadArraySingle['reviewBuySystem'] = $feedback['purchased'];
			}

			$leadArraySingle['featureMadePanels'] = selectFeatureYesNo($l_systemDetails, 'Paying a bit more for Australian, US or European made panels');
			$leadArraySingle['featureOffGrid'] = selectFeatureYesNo($l_systemDetails, 'Off Grid / Remote Area System');
			$leadArraySingle['featureBattery'] = selectFeatureYesNo($l_systemDetails, 'Battery Storage System (quite expensive!)');
			$leadArraySingle['featureMicroinverter'] = selectFeatureYesNo($l_systemDetails, 'Microinverters (leave unchecked if not sure)');
			$leadArraySingle['featureOther'] = selectFeatureOther($l_systemDetails);
			
			$leadArray[] = $leadArraySingle;
		}
	}
	
	
	function dbStaticQueryToArray() {
		GLOBAL $supplierArray, $supplierParentArray;
		GLOBAL $suppliers, $supplierParents;
		
		while ($supplier = mysqli_fetch_array($suppliers, MYSQLI_ASSOC)) {
			extract($supplier, EXTR_PREFIX_ALL, 's');
			
			$supplierArraySingle = array();
			$supplierArraySingle['record_num'] = $s_record_num;
			$supplierArraySingle['company'] = $s_company;
			$supplierArraySingle['parent'] = $s_parent;
			
			$supplierArray[] = $supplierArraySingle;
		}
		
		while ($supplierParent = mysqli_fetch_array($supplierParents, MYSQLI_ASSOC)) {
			extract($supplierParent, EXTR_PREFIX_ALL, 's');
			
			$supplierParentArraySingle = array();
			$supplierParentArraySingle['record_num'] = $s_record_num;
			$supplierParentArraySingle['company'] = $s_parentName;
			
			$supplierParentArray[] = $supplierParentArraySingle;
		}
	}
	
	function fileOpen() {
		GLOBAL $file, $phpdir;
		
		$file = fopen("$phpdir/temp/sunwiz.csv", 'w');
	}
	
	function fileWriteHeader() {
		GLOBAL $file;
		
		$lineToWrite = 'Lead ID, City, State, Postcode, Lat, Lon, Supplier Count, From Widget, Timeframe, Home Visit, Requested Finance, Primary Residence, Own Roofspace, Roof Type, Roof North Facing, ';
		$lineToWrite .= 'Roof Slop, Shade, Roof Direction, Storeys, System Size, Review Left, Company Reviewed, Review Rating, Review Bought System, ';
		$lineToWrite .= 'Paying a bit more for Australian US or European made panels, Off Grid / Remote Area System, Battery Storage System, Microinverters, Other, Submitted';
		
		fileWriteLine($lineToWrite);
	}
	
	function fileWriteLine($lineToWrite) {
		GLOBAL $file;
		
		$lineToWrite .= "\r\n";
		
		fwrite($file, $lineToWrite);
	}
	
	function fileClose() {
		GLOBAL $file;
		
		fclose($file);
	}
	
	function processLeads() {
		GLOBAL $leadArray, $leadSupplierArray, $supplierArray, $supplierParentArray, $feedbackArray;

		foreach ($leadArray AS $lead) {
			$lineToWrite = sanitizeVariable($lead['record_num']);
			$lineToWrite .= ',' . sanitizeVariable($lead['iCity']);
			$lineToWrite .= ',' . sanitizeVariable($lead['iState']);
			$lineToWrite .= ',' . sanitizeVariable($lead['iPostcode']);
			$lineToWrite .= ',' . sanitizeVariable($lead['latitude']);
			$lineToWrite .= ',' . sanitizeVariable($lead['longitude']);
			$lineToWrite .= ',' . sanitizeVariable($lead['supplierCount']);
			$lineToWrite .= ',' . sanitizeVariable($lead['fromWidget']);
			$lineToWrite .= ',' . sanitizeVariable($lead['Timeframe']);
			$lineToWrite .= ',' . sanitizeVariable($lead['homeVisit']);
			$lineToWrite .= ',' . sanitizeVariable($lead['finance']);
			$lineToWrite .= ',' . sanitizeVariable($lead['primaryResidence']);
			$lineToWrite .= ',' . sanitizeVariable($lead['ownRoofSpace']);
			$lineToWrite .= ',' . sanitizeVariable($lead['roofType']);
			$lineToWrite .= ',' . sanitizeVariable($lead['roofNorthFacing']);
			$lineToWrite .= ',' . sanitizeVariable($lead['roofSlope']);
			$lineToWrite .= ',' . sanitizeVariable($lead['shade']);
			$lineToWrite .= ',' . sanitizeVariable($lead['direction']);
			$lineToWrite .= ',' . sanitizeVariable($lead['storeys']);
			$lineToWrite .= ',' . sanitizeVariable($lead['systemSize']);
			$lineToWrite .= ',' . sanitizeVariable($lead['reviewLeft']);
			$lineToWrite .= ',' . sanitizeVariable($lead['reviewCompany']);
			$lineToWrite .= ',' . sanitizeVariable($lead['reviewRating']);
			$lineToWrite .= ',' . sanitizeVariable($lead['reviewBuySystem']);
			$lineToWrite .= ',' . $lead['featureMadePanels'];
			$lineToWrite .= ',' . $lead['featureOffGrid'];
			$lineToWrite .= ',' . $lead['featureBattery'];
			$lineToWrite .= ',' . $lead['featureMicroinverter'];
			$lineToWrite .= ',' . sanitizeVariable($lead['featureOther']);
			
			$lineToWrite .= ',' . sanitizeVariable($lead['submitted']);
			
			fileWriteLine($lineToWrite);
		}
	}
	
	function selectDetail($systemDetails, $quoteDetails, $siteDetails, $rebateDetails, $detail) {
		$systemDetails = htmlentitiesRecursive(unserialize(base64_decode($systemDetails)));
		$quoteDetails = htmlentitiesRecursive(unserialize(base64_decode($quoteDetails)));
		$siteDetails = htmlentitiesRecursive(unserialize(base64_decode($siteDetails)));
		$rebateDetails = htmlentitiesRecursive(unserialize(base64_decode($rebateDetails)));

		foreach ($systemDetails AS $systemDetailKey => $systemDetailValue) {
			if ($systemDetailKey == $detail)
				return $systemDetailValue;
		}

		foreach ($quoteDetails AS $quoteDetailKey => $quoteDetailValue) {
			if ($quoteDetailKey == $detail)
				return $quoteDetailValue;
		}
		
		foreach ($siteDetails AS $siteDetailKey => $siteDetailValue) {
			if ($siteDetailKey == $detail)
				return $siteDetailValue;
		}
		foreach ($rebateDetails AS $rebateDetailKey => $rebateDetailValue) {
			if ($rebateDetailKey == $detail)
				return $rebateDetailValue;
		}
		
		return '';
	}
	
	function selectFeedbackDetails($lead) {
		GLOBAL $feedbackArray;
		
		foreach ($feedbackArray AS $feedbackKey => $feedbackValue) {
			if ($feedbackValue['lead'] == $lead)
				return $feedbackValue;
		}
		
		return '';
	}
	
	function selectFeatureYesNo($systemDetails, $detail) {
		$systemDetails = htmlentitiesRecursive(unserialize(base64_decode($systemDetails)));
		
		foreach ($systemDetails as $a => $b) {
	        if ($a == "Features:") {	        	
				$pos = strpos($b, $detail);
    			if ($pos === false)
					return 'No';
    			else
    				return 'Yes';
	        }
	    }
	    
	    return 'No';
	}
	
	function selectFeatureOther($systemDetails) {
		$systemDetails = htmlentitiesRecursive(unserialize(base64_decode($systemDetails)));
		
		foreach ($systemDetails as $a => $b) {
	        if ($a == "Features:") {
	        	if ($b == 'None')
	        		return $b;
	        	
	        	// Strip out other features found
	        	$b = str_replace("Finance / Payment Plan", "", $b);
	        	$b = str_replace("Paying a bit more for Australian, US or European made panels", "", $b);
	        	$b = str_replace("Off Grid / Remote Area System", "", $b);
	        	$b = str_replace("Microinverters (leave unchecked if not sure)", "", $b);
	        	$b = str_replace("Battery Storage System (quite expensive!)", "", $b);
	        	
	        	if (strtolower(substr($b, 0, 5)) == 'other')
	        		$b = substr($b, 6);
	        		
	        	$b = trim($b);
	        	
	        	if ($b != '')
	        		return $b;
	        }
	    }
	    
	    return 'None';
	}
	
	function selectSupplierCount($lead) {
		GLOBAL $leadSupplierArray;
		
		$return = 0;
		
		foreach ($leadSupplierArray AS $leadSupplierKey => $leadSupplierValue) {
			if ($leadSupplierValue['lead'] == $lead)
				$return++;
				
			if ($leadSupplierValue['lead'] > $lead)
				return $return;
		}
		
		return $return;
	}
	
	function selectSupplierName($supplier) {
		GLOBAL $supplierArray;
		
		$return = '';
		
		foreach ($supplierArray AS $supplierKey => $supplierValue) {
			if ($supplierValue['record_num'] == $supplier)
				return $supplierValue['company'];
		}
		
		return $return;
	}
	
	function sanitizeVariable($line) {
		$line = str_replace("'", "", $line);
		$line = str_replace(",", "", $line);
		$line = str_replace("\r", "", $line);
		$line = str_replace("\n", "", $line);
		
		return $line;
	}
	
	function calculateReview($rateValue, $rateSystemQuality, $rateInstallation, $rateCustomerService) {
		// Get the current values for this feedback
    	$feedbackSum = 0;
    	$average = 4;
    	
    	if ($rateValue > 0) 
    		$feedbackSum = $rateValue; 
    	else 
    		$average -= 1;
    		
    	if ($rateSystemQuality > 0) 
    		$feedbackSum += $rateSystemQuality;
    	else 
    		$average -= 1;
    		
    	if ($rateInstallation > 0) 
    		$feedbackSum += $rateInstallation;
    	else 
    		$average -= 1;
    		
    	if ($rateCustomerService > 0) 
    		$feedbackSum += $rateCustomerService;
    	else 
    		$average -= 1;
    	
    	// This is now taking into consideration N/A
    	if ($average > 0)
			return round($feedbackSum / $average * 4, 2);
		else
			return false;
	}
?>