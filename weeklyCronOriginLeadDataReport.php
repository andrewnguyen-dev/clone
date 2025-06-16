<?php

include_once 'origin.php';
include_once 'global.php';
/*
	* Generates a csv file containing daily lead data reporting
	* Daily - All days since the start of the financial year
	* */

global $nowSql, $now, $origin, $leadcsvdir;

$fileName = $leadcsvdir.'/origin-daily-'.date('Y-m-d').'.csv';
echo "Starting daily lead metrics report generation for file: $fileName\n\n";

$file = fopen($fileName, 'w');

// Function to generate the list of days from the start of the financial year to the current date
function dayRange() {
	// Get today's date
	$today = new DateTime();

	// Determine financial year start and end
	$currentYear = (int) $today->format('Y');
	$currentMonth = (int) $today->format('m');

	if ($currentMonth < 7) {
		// Before July, still in previous financial year
		$startYear = $currentYear - 1;
	} else {
		// After July, new financial year started
		$startYear = $currentYear;
	}

	// Start at 1st July
	$startDate = new DateTime("$startYear-07-01");
	$endDate = $today;

	$interval = new DateInterval('P1D');
	$dateRange = new DatePeriod($startDate, $interval, $endDate);

	$days = [];
	foreach ($dateRange as $date) {
		$days[] = $date->format('Y-m-d');
	}

	echo "Generated " . count($days) . " days from " . $days[0] . " to " . end($days) . "\n\n";
	return $days;
}

// Function to generate the daily report
function dailyReport($file, $dayRange, $reportType) {
	$leadTypes = listOfLeadTypes();
	$stateList = ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'];
	$tableHeaders = array_merge(
		['Date', 'Report Type', 'Total Customers', 'Utilization Rate', 'Total Leads/Quotes', 'Reject Rate'],
		$leadTypes,
		$stateList
	);
    
	if ($reportType === 'Origin') {
        fputcsv($file, $tableHeaders);
		$origin = true;
		echo "Processing Origin rows...\n\n";
	} else {
		$origin = false;
		echo "Processing SolarQuotes rows...\n\n";
	}

	$startDate = reset($dayRange);
	$endDate   = end($dayRange);

	// Fetch metrics for the entire range once
	$leadMetricsByDay      = leadMetricsRange($startDate, $endDate, $origin);
	$supplierMetricsByDay  = leadsSuppliersMetricsRange($startDate, $endDate, $origin);
	$leadTypeMetricsByDay  = leadMetricsByTypeRange($startDate, $endDate, $origin);
	$leadStateMetricsByDay = leadStateMetricsRange($startDate, $endDate, $origin);

	foreach ($dayRange as $date) {
		$leadSuppliersMetrics = $supplierMetricsByDay[$date] ?? [
			'total_leads' => 0,
			'total_count' => 0,
			'reject_rate' => 0,
		];
		$leadMetrics       = $leadMetricsByDay[$date] ?? ['quotes' => 0];
		$leadTypeMetrics   = $leadTypeMetricsByDay[$date] ?? [];
		$leadStateMetrics  = $leadStateMetricsByDay[$date] ?? [];

		$total_count = $leadSuppliersMetrics['total_count'];
		$reject_count = $leadSuppliersMetrics['reject_rate'];
		$quotes = $leadMetrics['quotes'] ?? 0;

		$utilizationRate = $quotes > 0 ? number_format($total_count / $quotes * 100, 2) : 0;
		$rejectRate = $total_count > 0 ? number_format($reject_count / $total_count * 100, 2) : 0;

		$row = [
			$date,
			$reportType,
			$leadSuppliersMetrics['total_leads'],
			$utilizationRate,
			$total_count,
			$rejectRate,
		];

		foreach ($leadTypes as $leadType) {
			$row[] = isset($leadTypeMetrics[$leadType]) ? number_format($leadTypeMetrics[$leadType]['percentage_of_total'], 2) : '0.00';
		}
		foreach ($stateList as $state) {
			$row[] = isset($leadStateMetrics[$state]) ? number_format($leadStateMetrics[$state]['percentage_of_total'], 2) : '0.00';
		}
		fputcsv($file, $row);
	}
}

function leadMetrics($fromDate, $toDate, $origin = false, $isWeekly = false) {
	// Validate input
	if (empty($fromDate) || empty($toDate)) {
		throw new Exception('Both fromDate and toDate must be provided.');
	}

	$originAdd = " AND Leads.originLead = 'N' ";
	if ($origin) {
		$originAdd = " AND Leads.originLead = 'Y' ";
	}

	$statusCondition = " AND Leads.status != 'duplicate' ";
	if($isWeekly) {
		$statusCondition = " AND Leads.status IN ('dispatched', 'manual', 'followedup') ";
	}

	// Build safe full-day datetime ranges
	$fromDateTime = $fromDate.' 00:00:00';   // start of first day
	$toDateTime = $toDate.' 23:59:59';       // end of last day

	$query = "
		SELECT 
			CONCAT('$fromDate', ' to ', '$toDate') AS date_range,
			COALESCE(COUNT(*), 0) AS count,
			COALESCE(SUM(Leads.requestedQuotes), 0) AS quotes,
			COALESCE(AVG(Leads.requestedQuotes), 0) AS quotes_avg
		FROM leads Leads
		WHERE 
			Leads.submitted BETWEEN '$fromDateTime' AND '$toDateTime'
			AND Leads.iState IN ('ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA', '')
			$statusCondition
			AND Leads.source != 'SolarQuoteReused'
			$originAdd
	;";

	$result = db_query($query);
	if (false === $result) {
		exit('Error executing the query: '.db_error());
	}

	$metrics = [];
	if ($row = $result->fetch_assoc()) {
		$metrics = [
			'date_range' => $row['date_range'],
			'count' => (int) $row['count'],
			'quotes' => (int) $row['quotes'],
			'quotes_avg' => (float) $row['quotes_avg'],
		];
	}

	return $metrics;
}

function leadsSuppliersMetrics($fromDate, $toDate, $origin = false) {
	if (empty($fromDate) || empty($toDate)) {
		throw new Exception('Both fromDate and toDate must be provided.');
	}

	$originAdd = " AND Leads.originLead = 'N' ";
	if ($origin) {
		$originAdd = " AND Leads.originLead = 'Y' ";
	}

	$fromDateTime = $fromDate.' 00:00:00';
	$toDateTime = $toDate.' 23:59:59';

	$query = "
	SELECT 
		CONCAT('$fromDate', ' to ', '$toDate') AS date_range,

		COALESCE(SUM(CASE WHEN LeadSuppliers.status = 'rejected' THEN 1 ELSE 0 END), 0) AS reject_rate,
		COALESCE(SUM(CASE WHEN LeadSuppliers.status = 'missing' THEN 1 ELSE 0 END), 0) AS missing_rate,

		COALESCE(SUM(CASE WHEN type = 'regular' AND extraLead = 'N' AND manualLead = 'N' THEN 1 ELSE 0 END), 0) AS count_noextra,
		COALESCE(SUM(CASE WHEN type = 'regular' AND manualLead = 'N' THEN 1 ELSE 0 END), 0) AS count_extra,
		COALESCE(SUM(CASE WHEN type = 'regular' THEN 1 ELSE 0 END), 0) AS count_manual,
		COALESCE(SUM(CASE WHEN type = 'regular' THEN 1 ELSE 0 END), 0) AS total_count,

		COALESCE(SUM(CASE 
			WHEN LeadSuppliers.status NOT IN ('rejected', 'scrapped') AND LeadSuppliers.invoice = 'Y' 
			THEN ROUND(LeadSuppliers.leadPrice * (1 - Suppliers.otherDiscount / 100 - 
				(IF(Suppliers.invoiceTerms = 'standard', 0, Suppliers.invoiceDiscountRate) / 100)), 2) 
			ELSE 0
		END), 0) AS total,

		COALESCE(SUM(CASE 
			WHEN LeadSuppliers.status NOT IN ('rejected', 'scrapped') AND LeadSuppliers.invoice = 'Y' 
				AND LeadSuppliers.extraLead = 'Y'
			THEN ROUND(LeadSuppliers.leadPrice * (1 - Suppliers.otherDiscount / 100 - 
				(IF(Suppliers.invoiceTerms = 'standard', 0, Suppliers.invoiceDiscountRate) / 100)), 2) 
			ELSE 0
		END), 0) AS total_extra,

		COALESCE(SUM(CASE 
			WHEN LeadSuppliers.status NOT IN ('rejected', 'scrapped') AND LeadSuppliers.invoice = 'Y' 
				AND (LeadSuppliers.extraLead = 'Y' OR LeadSuppliers.manualLead = 'Y')
			THEN ROUND(LeadSuppliers.leadPrice * (1 - Suppliers.otherDiscount / 100 - 
				(IF(Suppliers.invoiceTerms = 'standard', 0, Suppliers.invoiceDiscountRate) / 100)), 2) 
			ELSE 0
		END), 0) AS total_manual,

		-- only count each lead once, on its first_dispatch
		COALESCE(
			COUNT(DISTINCT CASE 
				WHEN LeadSuppliers.dispatched = FirstDispatch.first_dispatched 
				THEN Leads.record_num 
			END),
		0) AS distinct_lead_count

	FROM lead_suppliers LeadSuppliers
	INNER JOIN leads Leads ON Leads.record_num = LeadSuppliers.lead_id
	INNER JOIN suppliers Suppliers ON Suppliers.record_num = LeadSuppliers.supplier
	LEFT JOIN (
		SELECT lead_id, MIN(dispatched) AS first_dispatched
		FROM lead_suppliers
		GROUP BY lead_id
	) AS FirstDispatch ON FirstDispatch.lead_id = LeadSuppliers.lead_id

	WHERE 
		LeadSuppliers.dispatched BETWEEN '$fromDateTime' AND '$toDateTime'
		AND Leads.iState IN ('ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA', '')
		{$originAdd}
	";

	$result = db_query($query);
	if (false === $result) {
		exit('Error executing the query: '.db_error());
	}

	$row = $result->fetch_assoc();

	return [
		'date_range' => $row['date_range'],
		'reject_rate' => (int) $row['reject_rate'],
		'missing_rate' => (int) $row['missing_rate'],
		'count_noextra' => (int) $row['count_noextra'],
		'count_extra' => (int) $row['count_extra'],
		'count_manual' => (int) $row['count_manual'],
		'total_count' => (int) $row['total_count'],
		'total' => (float) $row['total'],
		'total_extra' => (float) $row['total_extra'],
		'total_manual' => (float) $row['total_manual'],
		'total_leads' => (int) $row['distinct_lead_count'],
	];
}

function listOfLeadTypes() {
	return [
		'Battery Ready',
		'Battery Upgrade',
		'Commercial',
		'EV + Battery',
		'EV + Solar',
		'EV + Solar & Battery',
		'EV Only',
		'HWHP + Battery',
		'HWHP + Solar',
		'HWHP + Solar & Battery',
		'HWHP Only',
		'Hybrid',
		'OffGrid',
		'Repair',
		'Solar',
	];
}

function leadMetricsByType($date, $origin = false) {
	if (empty($date)) {
		throw new Exception('Both fromDate and toDate must be provided.');
	}

	$originAdd = " AND Leads.originLead = 'N' ";
	if ($origin) {
		$originAdd = " AND Leads.originLead = 'Y' ";
	}

	// Escape the input if necessary (depends on your db layer)

	$dateStart = "$date 00:00:00";
	$dateEnd = "$date 23:59:59";

	$query = "
		SELECT 
			priceType, 
			COUNT(*) AS lead_count
			FROM (
				SELECT 
					Leads.record_num,
					MAX(DATE(LeadSuppliers.dispatched)) AS created_date,
					systemDetails, Leads.status,
					CASE 
						-- EV Charger Matching
						WHEN LOCATE('ev charger', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN 
							CASE 
								WHEN LOCATE('ev charger + solar & battery', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 
									THEN 'EV + Solar & Battery'
								WHEN LOCATE('ev charger + battery', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 
									THEN 'EV + Battery'
								WHEN LOCATE('ev charger + solar', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 
									THEN 'EV + Solar'
								ELSE 'EV Only'
							END

						-- HWHP Matching                
						WHEN LOCATE('Hot Water Heat Pump', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN 
							CASE 
								WHEN LOCATE('Hot Water Heat Pump + solar & battery', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 
									THEN 'HWHP + Solar & Battery'
								WHEN LOCATE('Hot Water Heat Pump + battery', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 
									THEN 'HWHP + Battery'
								WHEN LOCATE('Hot Water Heat Pump + solar', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 
									THEN 'HWHP + Solar'
								ELSE 'HWHP Only'
							END
						
						-- Battery Ready System Matching
						WHEN LOCATE('Battery ready system required', LOWER(CONVERT(FROM_BASE64(Leads.siteDetails) USING utf8))) > 0 
							THEN 'Battery Ready'

						-- Battery Upgrade Matching (if 'Off Grid' is NOT in systemDetails)
						WHEN LOCATE('Customer wants a battery upgrade', LOWER(CONVERT(FROM_BASE64(Leads.siteDetails) USING utf8))) > 0 
							AND LOCATE('Off Grid', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) = 0 
							THEN 'Battery Upgrade'

						-- PHP Matching Conditions for Hybrid, OffGrid, and HW
						WHEN LOCATE('Battery', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 
							OR LOCATE('Hybrid', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 
							THEN 'Hybrid'
						WHEN LOCATE('Off Grid', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 
							THEN 'OffGrid'
						WHEN leadType = 'Repair Residential' THEN 'Repair'
						WHEN leadType = 'Commercial' THEN 'Commercial'
						ELSE 'Solar' -- No match
					END AS priceType

					FROM lead_suppliers LeadSuppliers 
					left JOIN leads Leads ON Leads.record_num = LeadSuppliers.lead_id 
					WHERE ( 
						LeadSuppliers.dispatched BETWEEN '$dateStart' AND '$dateEnd'
						AND Leads.iState IN ('NSW', 'ACT', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA', '') 
						AND Leads.status != 'duplicate' AND Leads.source != 'SolarQuoteReused'
						$originAdd
					)
					group by Leads.record_num
			) t
			GROUP BY priceType
			ORDER BY created_date ASC, priceType ASC;
	";

	$result = db_query($query);
	if (false === $result) {
		exit('Error executing the query: '.db_error());
	}

	$leadMetrics = [];
	$totalLeads = 0;

	// Step 1: Fetch results and calculate total leads
	while ($row = $result->fetch_assoc()) {
		$priceType = $row['priceType'] ?? 'Unknown';
		$lead_count = (int) $row['lead_count'];

		$leadMetrics[$priceType] = [
			'priceType' => $priceType,
			'lead_count' => $lead_count,
		];

		$totalLeads += $lead_count;
	}

	// Step 2: Add percentage for each type
	foreach ($leadMetrics as &$data) {
		$data['percentage_of_total'] = $totalLeads > 0
			? round(($data['lead_count'] / $totalLeads) * 100, 2)
			: 0;
	}
	unset($data); // break reference

	return $leadMetrics;
}

/**
	* Fetches the % of leads by state.
	*/
function leadStateMetrics($fromDate, $toDate, $origin = false) {
	if (empty($fromDate) || empty($toDate)) {
		throw new Exception('Both fromDate and toDate must be provided.');
	}

	$originAdd = " AND Leads.originLead = 'N' ";
	if ($origin) {
		$originAdd = " AND Leads.originLead = 'Y' ";
	}

	$query = "
		SELECT 
			Leads.iState AS state,
			COUNT(*) AS lead_count
		FROM lead_suppliers LeadSuppliers
		LEFT JOIN leads Leads ON Leads.record_num = LeadSuppliers.lead_id
		WHERE 
			DATE(LeadSuppliers.dispatched) BETWEEN '$fromDate' AND '$toDate'
			AND Leads.iState IN ('NSW', 'ACT', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA')
			AND Leads.status != 'duplicate'
			AND Leads.source != 'SolarQuoteReused'
			$originAdd
		GROUP BY Leads.iState
		ORDER BY Leads.iState ASC
	;";

	$result = db_query($query);
	if (false === $result) {
		exit('Error executing the query: '.db_error());
	}

	$stateCounts = [];
	$totalLeads = 0;

	// Step 1: Fetch and build array
	while ($row = $result->fetch_assoc()) {
		$state = $row['state'] ?? 'Unknown';
		$lead_count = (int) $row['lead_count'];

		$stateCounts[$state] = [
			'state' => $state,
			'lead_count' => $lead_count,
		];

		$totalLeads += $lead_count;
	}

	// Step 2: Add percentage per state
	foreach ($stateCounts as &$data) {
		$data['percentage_of_total'] = $totalLeads > 0
			? round(($data['lead_count'] / $totalLeads) * 100, 2)
			: 0;
	}
	unset($data); // break reference

	return $stateCounts;
}

// ---------- Range-based helper functions to speed up reporting ----------

function leadMetricsRange($fromDate, $toDate, $origin = false) {
	$originAdd = " AND Leads.originLead = 'N' ";
	if ($origin) {
		$originAdd = " AND Leads.originLead = 'Y' ";
	}

	$fromDateTime = $fromDate.' 00:00:00';
	$toDateTime   = $toDate.' 23:59:59';

	$query = "
		SELECT DATE(Leads.submitted) AS day,
			   COUNT(*) AS count,
			   COALESCE(SUM(Leads.requestedQuotes),0) AS quotes
		  FROM leads Leads
		 WHERE Leads.submitted BETWEEN '$fromDateTime' AND '$toDateTime'
		   AND Leads.iState IN ('ACT','NSW','NT','QLD','SA','TAS','VIC','WA','')
		   AND Leads.status != 'duplicate'
		   AND Leads.source != 'SolarQuoteReused'
		   $originAdd
		 GROUP BY day
		 ORDER BY day";

	$result = db_query($query);
	if (false === $result) {
		exit('Error executing the query: '.db_error());
	}

	$data = [];
	while ($row = $result->fetch_assoc()) {
		$data[$row['day']] = [
			'count'  => (int)$row['count'],
			'quotes' => (int)$row['quotes'],
		];
	}
	return $data;
}

function leadsSuppliersMetricsRange($fromDate, $toDate, $origin = false) {
	$originAdd = " AND Leads.originLead = 'N' ";
	if ($origin) {
		$originAdd = " AND Leads.originLead = 'Y' ";
	}

	$fromDateTime = $fromDate.' 00:00:00';
	$toDateTime   = $toDate.' 23:59:59';

	$query = "
		SELECT DATE(LeadSuppliers.dispatched) AS day,
			   SUM(CASE WHEN LeadSuppliers.status = 'rejected' THEN 1 ELSE 0 END) AS reject_rate,
			   SUM(CASE WHEN LeadSuppliers.status = 'missing' THEN 1 ELSE 0 END) AS missing_rate,
			   SUM(CASE WHEN type = 'regular' AND extraLead = 'N' AND manualLead = 'N' THEN 1 ELSE 0 END) AS count_noextra,
			   SUM(CASE WHEN type = 'regular' AND manualLead = 'N' THEN 1 ELSE 0 END) AS count_extra,
			   SUM(CASE WHEN type = 'regular' THEN 1 ELSE 0 END) AS count_manual,
			   SUM(CASE WHEN type = 'regular' THEN 1 ELSE 0 END) AS total_count,
			   SUM(CASE
					   WHEN LeadSuppliers.status NOT IN ('rejected','scrapped') AND LeadSuppliers.invoice = 'Y'
					   THEN ROUND(LeadSuppliers.leadPrice * (1 - Suppliers.otherDiscount/100 - (IF(Suppliers.invoiceTerms = 'standard',0,Suppliers.invoiceDiscountRate)/100)),2)
					   ELSE 0
				   END) AS total,
			   SUM(CASE
					   WHEN LeadSuppliers.status NOT IN ('rejected','scrapped') AND LeadSuppliers.invoice = 'Y' AND LeadSuppliers.extraLead = 'Y'
					   THEN ROUND(LeadSuppliers.leadPrice * (1 - Suppliers.otherDiscount/100 - (IF(Suppliers.invoiceTerms = 'standard',0,Suppliers.invoiceDiscountRate)/100)),2)
					   ELSE 0
				   END) AS total_extra,
			   SUM(CASE
					   WHEN LeadSuppliers.status NOT IN ('rejected','scrapped') AND LeadSuppliers.invoice = 'Y' AND (LeadSuppliers.extraLead = 'Y' OR LeadSuppliers.manualLead = 'Y')
					   THEN ROUND(LeadSuppliers.leadPrice * (1 - Suppliers.otherDiscount/100 - (IF(Suppliers.invoiceTerms = 'standard',0,Suppliers.invoiceDiscountRate)/100)),2)
					   ELSE 0
				   END) AS total_manual,
			   COUNT(DISTINCT CASE WHEN LeadSuppliers.dispatched = FirstDispatch.first_dispatched THEN Leads.record_num END) AS distinct_lead_count
		  FROM lead_suppliers LeadSuppliers
			   INNER JOIN leads Leads ON Leads.record_num = LeadSuppliers.lead_id
			   INNER JOIN suppliers Suppliers ON Suppliers.record_num = LeadSuppliers.supplier
			   LEFT JOIN (
					SELECT lead_id, MIN(dispatched) AS first_dispatched
					  FROM lead_suppliers
				  GROUP BY lead_id
			   ) AS FirstDispatch ON FirstDispatch.lead_id = LeadSuppliers.lead_id
		 WHERE LeadSuppliers.dispatched BETWEEN '$fromDateTime' AND '$toDateTime'
		   AND Leads.iState IN ('ACT','NSW','NT','QLD','SA','TAS','VIC','WA','')
		   $originAdd
		 GROUP BY day
		 ORDER BY day";

	$result = db_query($query);
	if (false === $result) {
		exit('Error executing the query: '.db_error());
	}

	$data = [];
	while ($row = $result->fetch_assoc()) {
		$data[$row['day']] = [
			'reject_rate'  => (int)$row['reject_rate'],
			'missing_rate' => (int)$row['missing_rate'],
			'count_noextra'=> (int)$row['count_noextra'],
			'count_extra'  => (int)$row['count_extra'],
			'count_manual' => (int)$row['count_manual'],
			'total_count'  => (int)$row['total_count'],
			'total'        => (float)$row['total'],
			'total_extra'  => (float)$row['total_extra'],
			'total_manual' => (float)$row['total_manual'],
			'total_leads'  => (int)$row['distinct_lead_count'],
		];
	}
	return $data;
}

function leadMetricsByTypeRange($fromDate, $toDate, $origin = false) {
	$originAdd = " AND Leads.originLead = 'N' ";
	if ($origin) {
		$originAdd = " AND Leads.originLead = 'Y' ";
	}

	$fromDateTime = $fromDate.' 00:00:00';
	$toDateTime   = $toDate.' 23:59:59';

	$query = "
		SELECT day, priceType, COUNT(*) AS lead_count
		  FROM (
				SELECT Leads.record_num,
					   DATE(LeadSuppliers.dispatched) AS day,
					   CASE
						   WHEN LOCATE('ev charger', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN
							   CASE
								   WHEN LOCATE('ev charger + solar & battery', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN 'EV + Solar & Battery'
								   WHEN LOCATE('ev charger + battery', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN 'EV + Battery'
								   WHEN LOCATE('ev charger + solar', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN 'EV + Solar'
								   ELSE 'EV Only'
							   END
						   WHEN LOCATE('Hot Water Heat Pump', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN
							   CASE
								   WHEN LOCATE('Hot Water Heat Pump + solar & battery', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN 'HWHP + Solar & Battery'
								   WHEN LOCATE('Hot Water Heat Pump + battery', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN 'HWHP + Battery'
								   WHEN LOCATE('Hot Water Heat Pump + solar', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN 'HWHP + Solar'
								   ELSE 'HWHP Only'
							   END
						   WHEN LOCATE('Battery ready system required', LOWER(CONVERT(FROM_BASE64(Leads.siteDetails) USING utf8))) > 0 THEN 'Battery Ready'
						   WHEN LOCATE('Customer wants a battery upgrade', LOWER(CONVERT(FROM_BASE64(Leads.siteDetails) USING utf8))) > 0 AND LOCATE('Off Grid', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) = 0 THEN 'Battery Upgrade'
						   WHEN LOCATE('Battery', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 OR LOCATE('Hybrid', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN 'Hybrid'
						   WHEN LOCATE('Off Grid', LOWER(CONVERT(FROM_BASE64(Leads.systemDetails) USING utf8))) > 0 THEN 'OffGrid'
						   WHEN leadType = 'Repair Residential' THEN 'Repair'
						   WHEN leadType = 'Commercial' THEN 'Commercial'
						   ELSE 'Solar'
					   END AS priceType
				  FROM lead_suppliers LeadSuppliers
					   LEFT JOIN leads Leads ON Leads.record_num = LeadSuppliers.lead_id
				 WHERE LeadSuppliers.dispatched BETWEEN '$fromDateTime' AND '$toDateTime'
				   AND Leads.iState IN ('NSW','ACT','NT','QLD','SA','TAS','VIC','WA','')
				   AND Leads.status != 'duplicate' AND Leads.source != 'SolarQuoteReused'
				   $originAdd
				 GROUP BY Leads.record_num, day
		   ) t
		 GROUP BY day, priceType
		 ORDER BY day, priceType";

	$result = db_query($query);
	if (false === $result) {
		exit('Error executing the query: '.db_error());
	}

	$data = [];
	while ($row = $result->fetch_assoc()) {
		$data[$row['day']][$row['priceType']]['lead_count'] = (int)$row['lead_count'];
	}

	foreach ($data as $day => &$types) {
		$total = 0;
		foreach ($types as $info) {
			$total += $info['lead_count'];
		}
		foreach ($types as $type => &$info) {
			$info['percentage_of_total'] = $total > 0 ? round($info['lead_count'] / $total * 100, 2) : 0;
		}
		unset($info);
	}
	unset($types);

	return $data;
}

function leadStateMetricsRange($fromDate, $toDate, $origin = false) {
	$originAdd = " AND Leads.originLead = 'N' ";
	if ($origin) {
		$originAdd = " AND Leads.originLead = 'Y' ";
	}

	$fromDateTime = $fromDate.' 00:00:00';
	$toDateTime   = $toDate.' 23:59:59';

	$query = "
		SELECT DATE(LeadSuppliers.dispatched) AS day,
			   Leads.iState AS state,
			   COUNT(*) AS lead_count
		  FROM lead_suppliers LeadSuppliers
			   LEFT JOIN leads Leads ON Leads.record_num = LeadSuppliers.lead_id
		 WHERE LeadSuppliers.dispatched BETWEEN '$fromDateTime' AND '$toDateTime'
		   AND Leads.iState IN ('NSW','ACT','NT','QLD','SA','TAS','VIC','WA')
		   AND Leads.status != 'duplicate'
		   AND Leads.source != 'SolarQuoteReused'
		   $originAdd
		 GROUP BY day, Leads.iState
		 ORDER BY day, Leads.iState";

	$result = db_query($query);
	if (false === $result) {
		exit('Error executing the query: '.db_error());
	}

	$data = [];
	while ($row = $result->fetch_assoc()) {
		$data[$row['day']][$row['state']]['lead_count'] = (int)$row['lead_count'];
	}

	foreach ($data as $day => &$states) {
		$total = 0;
		foreach ($states as $info) {
			$total += $info['lead_count'];
		}
		foreach ($states as $state => &$info) {
			$info['percentage_of_total'] = $total > 0 ? round($info['lead_count'] / $total * 100, 2) : 0;
		}
		unset($info);
	}
	unset($states);

	return $data;
}

// Generate the daily report
$dayRange = dayRange();
dailyReport($file, $dayRange, 'Origin');
dailyReport($file, $dayRange, 'SolarQuotes');
fclose($file);
echo "CSV file written successfully\n\n";

// Send the file via email
sendMailWithAttachmentNoTemplate(
	$origin['emailRecipient'],
	'',
	'Daily Metrics Data Report',
	'Please find the attached CSV file with the daily lead metrics report.',
	$fileName
);
echo "Email sent\n\n";
?>