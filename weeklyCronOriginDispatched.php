<?php

include_once 'origin.php';
include_once 'global.php';

global $leadcsvdir, $origin, $techEmail;

$fileName = $leadcsvdir . '/origin-leads-'.date('Ymd').'.csv';

// First check the log leads entries for the week
$query = "
    SELECT distinct ll.lead_id
    FROM log_leads ll
    INNER JOIN leads l ON l.record_num = ll.lead_id
    WHERE 
        l.originLead = 'Y'
        AND ll.created BETWEEN DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE()) + 7) DAY)
        AND DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE()) + 1) DAY)
    ;
";

// Define the headers
$headers = [
    'lead_leadId', 'lead_submittedDate', 'lead_leadStatus', 'lead_requestedQuotes',
    'lead_hasClaimedLeads', 'lead_systemSize', 'lead_systemPriceType', 'lead_installTimeFrame',
    'lead_billFrequency', 'lead_billAmount', 'lead_roofType', 'lead_numberOfStoreys',
    'lead_features', 'lead_homeVisit', 'lead_importantNotesSplit', 'lead_type',
    'customer_firstName', 'customer_lastName', 'customer_businessName', 'customer_phone',
    'customer_email', 'address_lineOne', 'address_lineTwo', 'address_suburb',
    'address_state', 'address_postcode', 'leadSupplier_id', 'leadSupplier_supplierParentId',
    'leadSupplier_supplierParentName', 'leadSupplier_supplierId', 'leadSupplier_supplierName',
    'leadSupplier_status', 'leadSupplier_leadStatus', 'leadSupplier_rejectedReason',
    'leadSupplier_leadPrice', 'leadSupplier_claimed', 'hw_numberOfResidents', 'hw_existingSystem',
    'hw_locationAccessibility', 'hw_switchboardDistance', 'ev_existingSolarSize',
    'ev_existingBattery', 'ev_installationType', 'ev_distanceChargerSwitchboard',
    'ev_carMakeModel'
];

// Open the file
$file = fopen($fileName, 'w');

if ($file === false) {
    die('Error opening the file ' . $fileName);
}

// Insert headers
fputcsv($file, $headers);

// Fetch and process leads
$result = db_query($query);

while ($row = $result->fetch_assoc()) {
    $leadId = $row['lead_id'];
    $lead = loadLeadData($leadId);
    $originLead = getLeadPayload($leadId);

    // Ensure hotWaterHeatPump and evCharger exist
    $originLead['hotWaterHeatPump'] = $originLead['hotWaterHeatPump'] ?? [];
    $originLead['evCharger'] = $originLead['evCharger'] ?? [];

    // Basic row data
    $baseRowValues = [
        $lead['record_num'],
        $lead['submitted'],
        $lead['status'],
        $lead['requestedQuotes'],
        $originLead['hasClaimedLeads'] ?? '',
        $originLead['systemSize'] ?? '',
        $originLead['systemPriceType'] ?? '',
        $originLead['installTimeFrame'] ?? '',
        $originLead['billFrequency'] ?? '',
        $originLead['billAmount'] ?? '',
        $originLead['roofType'] ?? '',
        $originLead['numberOfStoreys'] ?? '',
        $originLead['features'] ?? '',
        $originLead['homeVisit'] ?? '',
        $originLead['importantNotesSplit'] ?? '',
        $originLead['type'] ?? '',
        $lead['fName'],
        $lead['lName'],
        $lead['company'],
        $lead['phone'],
        $lead['email'],
        $lead['iAddress'],
        $lead['iAddress2'],
        $lead['iCity'],
        $lead['iState'],
        $lead['iPostcode'],
    ];

    $hwData = [
        $originLead['hotWaterHeatPump']['numberOfResidents'] ?? '',
        $originLead['hotWaterHeatPump']['existingSystem'] ?? '',
        $originLead['hotWaterHeatPump']['locationAccessibility'] ?? '',
        $originLead['hotWaterHeatPump']['switchboardDistance'] ?? '',
    ];

    $evData = [
        $originLead['evCharger']['existingSolarSize'] ?? '',
        $originLead['evCharger']['existingBattery'] ?? '',
        $originLead['evCharger']['installationType'] ?? '',
        $originLead['evCharger']['distanceChargerSwitchboard'] ?? '',
        $originLead['evCharger']['carMakeModel'] ?? '',
    ];

    // Check lead suppliers
    if (!empty($originLead['leadSuppliers'])) {
        foreach ($originLead['leadSuppliers'] as $leadSupplier) {
            $supplierData = [
                $leadSupplier['id'] ?? '',
                $leadSupplier['supplierParentId'] ?? '',
                $leadSupplier['supplierParentName'] ?? '',
                $leadSupplier['supplierId'] ?? '',
                $leadSupplier['supplierName'] ?? '',
                $leadSupplier['status'] ?? '',
                $leadSupplier['leadStatus'] ?? '',
                $leadSupplier['rejectedReason'] ?? '',
                $leadSupplier['leadPrice'] ?? '',
                $leadSupplier['claimed'] ?? '',
            ];

            $fullRow = array_merge($baseRowValues, $supplierData, $hwData, $evData);
            fputcsv($file, $fullRow);
        }
    } else {
        // Empty supplier placeholders
        $emptySupplierData = array_fill(0, 10, '');
        $fullRow = array_merge($baseRowValues, $emptySupplierData, $hwData, $evData);
        fputcsv($file, $fullRow);
    }
}

fclose($file);

echo "CSV file with headers created successfully!\n";
echo 'File path is: ' . $fileName . "\n";

// Send email
sendMailWithAttachmentNoTemplate(
    $techEmail,
    "",
    'Weekly Origin Leads Report',
    'Please find the attached CSV file with the weekly Origin leads report.',
    $fileName
);
?>
