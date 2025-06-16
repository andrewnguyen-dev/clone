<?php
include('global.php');

// Start timer
$start_time = microtime(true);

$output_file = $argv[2] ?? 'leadStatsReportByMonth.csv';
$fromMonths = isset($argv[1]) ? (int) $argv[1] : 12;
// $argv[0] is always the script name
echo "Month in the Past: " . $argv[1] . PHP_EOL;

echo "Output file: " . $output_file . PHP_EOL;

echo "Processing lead by type counts " . PHP_EOL;

// Define the date ranges
$initialDate = date('Y-m-d', strtotime("-$fromMonths months"));
$initialMonth = date('Y-m', strtotime("-$fromMonths months"));
$currentMonth = date('Y-m');

$dataMonth = $initialMonth;

$months = [];
// Create the months range
while($dataMonth <= $currentMonth){
    $months[date('M Y', strtotime($dataMonth))] = $dataMonth;
    $dataMonth = date('Y-m', strtotime("$dataMonth +1 month"));
}

// Initialize the data array
$dataMonth = $initialMonth;
$data = [];
while($dataMonth <= $currentMonth){
    $from = $dataMonth . '-01';
    $to = date('Y-m-d', strtotime("$dataMonth +1 month"));
    if($from < $initialDate)
        $from = $initialDate;

    $md = leadByTypePerMonth($from, $to);
    $data = array_merge_recursive($data, $md);
    $dataMonth = date('Y-m', strtotime("$dataMonth +1 month"));
}

// Open the CSV file for writing
$fp = fopen($output_file, 'w');

// Write the header row (Months)
fputcsv($fp, array_merge([''], array_keys($months)));
fputcsv($fp, ['Leads']);

// Write each lead type row with respective counts
foreach ($data as $leadType => $values) {
    $row = [$leadType];
    foreach (array_keys($months) as $month) {
        $row[] = $data[$leadType][$month] ?? ''; // Fill empty cells with ''
    }
    fputcsv($fp, $row);
}

echo "Processing Rates " . PHP_EOL;

flush();
$query = "SELECT 
    SUM(CASE WHEN LeadSuppliers.status = 'rejected' THEN 0 ELSE LeadSuppliers.leadPrice END) / COUNT(DISTINCT Leads.record_num) AS revenue_by_lead, 
    DATE_FORMAT(created, \"%b %Y\") AS month
    FROM lead_suppliers LeadSuppliers 
    RIGHT JOIN leads Leads ON (LeadSuppliers.type = 'regular' AND Leads.record_num = LeadSuppliers.lead_id) 
    WHERE 
        Leads.status NOT IN ('duplicate') 
        AND leadType IN ('Residential', 'Commercial') 
        AND created >= '$initialDate'
    GROUP BY month
    ORDER BY created ASC;
";

// Execute the query
$result = db_query($query);

// Initialize the data array
$data = [];

// Fetch results and structure as [month][leadType] = count
$dataMonths = [];
$leadTypes = [];

echo "Processing Revenue Per Lead " . PHP_EOL;
flush();
// Process query results into a structured format
while ($row = mysqli_fetch_assoc($result)) {
    $month = $row['month'];
    $revenuePerLead = $row['revenue_by_lead'];

    // Store unique months for header row
    if (!in_array($month, $dataMonths)) {
        $dataMonths[] = $month;
    }

    // Store lead count data
    $data[$month] = $revenuePerLead;
}

$emptyMonths = array_fill_keys(array_keys($months), 0);
$data = array_merge($emptyMonths, array_intersect_key($data, $emptyMonths));

fputcsv($fp, ['']);
fputcsv($fp, array_merge(['Revenue per Lead'], array_values($data)));

echo "Processing Utilization and Reject Rates " . PHP_EOL;
flush();
// Initialize the data array
$dataMonth = $initialMonth;
$data = [];
while($dataMonth <= $currentMonth){
    $from = $dataMonth . '-01';
    $to = date('Y-m-d', strtotime("$dataMonth +1 month"));
    if($from < $initialDate)
        $from = $initialDate;

    $md = ratesByMonth($from, $to);
    $data = array_merge_recursive($data, $md);
    $dataMonth = date('Y-m', strtotime("$dataMonth +1 month"));
}

fputcsv($fp, array_merge(['Utilization Rate'], $data['utilizationRate']));
fputcsv($fp, array_merge(['Reject Rate'], $data['rejectRate']));

echo "Processing Review Counts " . PHP_EOL;
flush();
# Handle Feedbacks
$feedbacks_query = "SELECT count(*) feedback_count, 
    DATE_FORMAT(feedback_date, '%b %Y') AS month
    from feedback
    WHERE feedback_date >= '$initialMonth-01'
    group by month
;";

// Execute the query
$feedback_result = db_query($feedbacks_query);

// Initialize the data array
$data = [];

// Fetch results and structure as [month][leadType] = count
$months = [];
$leadTypes = [];

// Process query results into a structured format
while ($row = mysqli_fetch_assoc($feedback_result)) {
    $month = $row['month'];

    // Store unique months for header row
    if (!in_array($month, $months)) {
        $months[] = $month;
    }

    // Store feedback count data
    $data[$month] = $row['feedback_count'];
}

fputcsv($fp, array_merge(['Reviews'], array_values($data)));

echo "Processing Referrers " . PHP_EOL;
flush();
$stats = [];
$dataMonth = date('Y-m', strtotime("-$fromMonths months"));
$currentMonth = date('Y-m');

while($dataMonth <= $currentMonth){
    $from = $dataMonth . '-01';
    $to = date('Y-m-d', strtotime("$dataMonth +1 month"));
    if($from < $initialDate)
        $from = $initialDate;

    $md = refererDataPerMonth($from, $to);
    $stats = array_merge($stats, $md);
    $dataMonth = date('Y-m', strtotime("$dataMonth +1 month"));
}

foreach($stats as $month => $stat){
    ksort($stat);
    $stats[$month] = $stat;
}

// Write data rows
fputcsv($fp, ['']);
fputcsv($fp, ['Affiliates']);
$dataMonths = array_values($months);
$emptyMonths = array_fill_keys(array_values($months), array_fill_keys(array_keys($stats[end($dataMonths)]),0));
$stats = array_merge($emptyMonths, array_intersect_key($stats, $emptyMonths));


foreach($stats[$dataMonths[0]] as $index => $value){
    $refererData = [];
    foreach($dataMonths as $month){
        $refererData[] = $stats[$month][$index] ?? 0;
    }
    fputcsv($fp, array_merge([$index],$refererData));
}

// Close the CSV file
fclose($fp);

// End timer
$end_time = microtime(true);

// Calculate total execution time
$execution_time = $end_time - $start_time;

// Print execution time in seconds
echo "Execution Time: " . number_format($execution_time, 4) . " seconds\n";
echo "CSV file generated successfully: $output_file";


/*
* Function that retrieves information by month like it exists in the LM lead_list page
$month is in yyyy-mm format
*/
function refererDataPerMonth($from, $to){
    $between = "AND submitted >= '$from' AND submitted < '$to'";
    echo "Processing Referrers " . PHP_EOL;
    flush();
    // Handle Referrers
    $leadsRefQuery = "SELECT Leads.referer AS referer, 
            DATE_FORMAT(submitted,\"%b %Y\") AS month, 
            LeadReferers.landingPage AS landingPage, 
            LeadReferers.url AS url, 
            LeadReferers.type AS type, 
            LeadReferers.query AS query, 
            LeadReferers.host AS host, 
            Leads.source AS source 
        FROM lead_suppliers as LeadSuppliers 
        INNER JOIN leads Leads ON LeadSuppliers.lead_id = Leads.record_num 
        left JOIN leads_referers LeadReferers ON LeadReferers.record_num = Leads.referer 
        WHERE Leads.status != 'duplicate'
            $between
        GROUP BY Leads.record_num
        ORDER BY submitted asc;";
    
    $data = [];
    // Execute the query
    $referer_result = db_query($leadsRefQuery);
    // Process query results into a structured format
    $processingMonth = '';
    $stats = [];
    $affiliatesAdvM = getAffiliates(true);
    $affiliateList = array_keys($affiliatesAdvM);
    sort($affiliateList);
    
    while ($lead = mysqli_fetch_assoc($referer_result)) {
        $leadMonth = $lead['month'];
        if($processingMonth != $leadMonth){
            echo "Processing Referrers For Month " . $leadMonth . PHP_EOL;
            flush();
            $processingMonth = $leadMonth;
        }
    
        if(! isset($stats[$leadMonth])){
            $stats[$leadMonth] = [];
            foreach($affiliateList as $affiliate){
                $stats[$leadMonth][$affiliate] = 0;
            }
        }
        
        $caption = refererCaption(json_decode(json_encode($lead)), false, $affiliatesAdvM);
        if(!array_key_exists($caption, $stats[$leadMonth]))
            $stats[$leadMonth][$caption] = 0;	
        $stats[$leadMonth][$caption]++;
    }

    return $stats;
}

function leadByTypePerMonth($from, $to){
    $between = "LeadSuppliers.dispatched >= '$from' AND LeadSuppliers.dispatched < '$to'";
    // Query for lead counts grouped by month
    $query = "
    SELECT 
        DATE_FORMAT(created_date, '%b %Y') AS month, 
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
                $between
                AND Leads.iState IN ('NSW', 'ACT', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA') 
                AND Leads.status != 'duplicate' AND Leads.source != 'SolarQuoteReused'
            )
            group by Leads.record_num
    ) t
    GROUP BY month, priceType
    ORDER BY created_date ASC, priceType ASC;
    ";

    // Execute the query
    $result = db_query($query);

    // Initialize the data array
    $data = [];

    // Fetch results and structure as [month][leadType] = count
    $months = [];
    $leadTypes = [];

    // Process query results into a structured format
    while ($row = mysqli_fetch_assoc($result)) {
    $month = $row['month'];
    $leadType = $row['priceType'];
    $leadCount = $row['lead_count'];

    // Store unique months for header row
    if (!in_array($month, $months)) {
        $months[] = $month;
    }

    // Store unique lead types for the first column
    if (!in_array($leadType, $leadTypes)) {
        $leadTypes[] = $leadType;
    }

    // Store lead count data
    $data[$leadType][$month] = $leadCount;
    }

    return $data;
}

function ratesByMonth($from, $to){
    $between = "LeadSuppliers.dispatched >= '$from' AND LeadSuppliers.dispatched < '$to'";

    $query = "SELECT 
        DATE_FORMAT(LeadSuppliers.dispatched, '%b %Y') AS month,
        COALESCE(SUM(CASE WHEN LeadSuppliers.status = 'rejected' THEN 1 ELSE 0 END), 0) AS reject_rate_month,
        COALESCE(SUM(CASE WHEN LeadSuppliers.status = 'missing' THEN 1 ELSE 0 END), 0) AS missing_rate_month,
        
        COALESCE(SUM(CASE WHEN type = 'regular' AND extraLead = 'N' AND manualLead = 'N' THEN 1 ELSE 0 END), 0) AS month_count_noextra,
        COALESCE(SUM(CASE WHEN type = 'regular' AND manualLead = 'N' THEN 1 ELSE 0 END), 0) AS month_count_extra,
        COALESCE(SUM(CASE WHEN type = 'regular' THEN 1 ELSE 0 END), 0) AS month_count_manual,
        COALESCE(SUM(CASE WHEN type = 'regular' THEN 1 ELSE 0 END), 0) AS month_count,
        COALESCE(SUM(CASE WHEN LeadSuppliers.status NOT IN ('rejected', 'scrapped') AND LeadSuppliers.invoice = 'Y' THEN ROUND(LeadSuppliers.leadPrice * (1 - Suppliers.otherDiscount / 100 - (IF(Suppliers.invoiceTerms = 'standard', 0, Suppliers.invoiceDiscountRate) / 100)), 2) ELSE 0 END), 0) AS month_total,
        COALESCE(SUM(CASE WHEN LeadSuppliers.status NOT IN ('rejected', 'scrapped') AND LeadSuppliers.invoice = 'Y' AND LeadSuppliers.extraLead = 'Y' THEN ROUND(LeadSuppliers.leadPrice * (1 - Suppliers.otherDiscount / 100 - (IF(Suppliers.invoiceTerms = 'standard', 0, Suppliers.invoiceDiscountRate) / 100)), 2) ELSE 0 END), 0) AS month_total_v2,
        COALESCE(SUM(CASE WHEN LeadSuppliers.status NOT IN ('rejected', 'scrapped') AND LeadSuppliers.invoice = 'Y' AND (LeadSuppliers.extraLead = 'Y' OR LeadSuppliers.manualLead = 'Y') THEN ROUND(LeadSuppliers.leadPrice * (1 - Suppliers.otherDiscount / 100 - (IF(Suppliers.invoiceTerms = 'standard', 0, Suppliers.invoiceDiscountRate) / 100)), 2) ELSE 0 END), 0) AS month_total_v3
    FROM lead_suppliers LeadSuppliers 
    INNER JOIN leads Leads ON Leads.record_num = LeadSuppliers.lead_id
    INNER JOIN suppliers Suppliers ON Suppliers.record_num = LeadSuppliers.supplier 
    WHERE 
        $between
        AND Leads.iState IN ('ACT' , 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA')
    ;";

    $month_requested = "SELECT 
        DATE_FORMAT(Leads.submitted, '%b %Y') AS month,
        COALESCE(COUNT(*), 0) AS count,
        COALESCE(SUM(Leads.requestedQuotes), 0) AS quotes,
        COALESCE(AVG(Leads.requestedQuotes), 0) AS quotes_avg
    FROM leads Leads
    WHERE 
        Leads.submitted >= '$from' AND Leads.submitted < '$to'
        AND Leads.iState IN ('ACT' , 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA')
        AND Leads.status != 'duplicate'
        AND Leads.source != 'SolarQuoteReused'
    ";

    // Execute the query
    $result = db_query($query);
    $month_requested_result = db_query($month_requested);

    // Initialize the data array
    $data = [];

    // Fetch results and structure as [month][leadType] = count
    $months = [];
    $leadTypes = [];

    // Process query results into a structured format
    while ($row = mysqli_fetch_assoc($result)) {
        $month = date('Y M', strtotime($from));
        $month_count = $row['month_count'];
        $reject_count = $row['reject_rate_month'];

        // Store unique months for header row
        if (!in_array($month, $months)) {
            $months[] = $month;
        }

        // Store lead count data
        $data[$month]['month_count'] = $month_count;
        $data[$month]['reject_count'] = $reject_count;
    }

    $utilizationRate = [];
    $rejectRate = [];
    // Process query results into a structured format
    while ($row = mysqli_fetch_assoc($month_requested_result)) {
        $month = date('Y M', strtotime($from));
        $month_count = $data[$month]['month_count'];
        $reject_count = $data[$month]['reject_count'];

        // Store unique months for header row
        if (!in_array($month, $months)) {
            $months[] = $month;
        }

        $utilizationRate[$month] = 0;
        $rejectRate[$month] = 0;

        if($month_count == 0){
            continue;
        }
        $utilizationRate[$month] = number_format($month_count / $row['quotes'] * 100, 2);
        $rejectRate[$month] = number_format($reject_count / $month_count * 100, 2);
    }

    return [
        'utilizationRate' => $utilizationRate,
        'rejectRate' => $rejectRate
    ];
}
?>
