<?php

/**
    Monitors paused and claiming suppliers and sends a notification to basecamp if a supplier hasn't claimed in:
        - 2 months
        - 6 months
        - 11 months
 */

include('global.php');
set_time_limit(0);
global $siteURLSSL;

// First grab a list of all suppliers that are paused and claiming but haven't gotten a lead in more than 2 months
$SQL = "SELECT S.record_num, S.company, TIMESTAMPDIFF(MONTH, max(LS.dispatched), NOW()) AS since, max(LS.dispatched) last_lead ";
$SQL .= " FROM suppliers S";
$SQL .= " INNER JOIN lead_suppliers LS ON S.record_num = LS.supplier";
$SQL .= " WHERE S.status = 'paused' AND LS.status = 'sent' AND S.extraLeads = 'Y'";
$SQL .= " GROUP BY S.record_num";
$SQL .= " HAVING since >= 2";
$SQL .= " ORDER BY S.company ASC";

$suppliers = db_query($SQL);
$suppliersList = [];

// Next get how long they have been paused for - Making sure the claiming
while ($supplier = mysqli_fetch_array($suppliers, MYSQLI_ASSOC)) {
    extract(htmlentitiesRecursive($supplier), EXTR_PREFIX_ALL, 's');
    $suppliersList[$s_record_num] = [
        'company' => $s_company,
        'since' => $s_since,
        'last_lead' => $s_last_lead
    ];
}

if(empty($suppliersList)){
    exit('No suppliers with more than 2 months since last lead' . PHP_EOL);   
}

// Now we get the log_supplier_account entries for the suppliers that had leads dispatched more than 2 months ago
$SQL = "SELECT LSA.supplier_id supplier_id, LSA.field_name, MAX(LSA.record_num) lsa_id, MAX(LSA.created) created";
$SQL .= " FROM log_supplier_account LSA";
$SQL .= " WHERE LSA.supplier_id IN (" . implode(',', array_keys($suppliersList)) . ")";
$SQL .= " GROUP BY LSA.field_name, LSA.supplier_id";;

$logs = db_query($SQL);
while ($log = mysqli_fetch_array($logs, MYSQLI_ASSOC)) {
    extract(htmlentitiesRecursive($log), EXTR_PREFIX_ALL, 'l');
    $suppliersList[$l_supplier_id][$l_field_name] = [
        'lsa_id' => $l_lsa_id,
        'created' => $l_created
    ];
}

foreach ($suppliersList as $supplierId => $supplier) {
    $company = $supplier['company'];
    $since = $supplier['since'];
    $lastLead = $supplier['last_lead'];

    // If no entry exists for this supplier on the given interval, send a notification
    if(! previouslySentNotification($supplierId, $since, $lastLead)){
        $message = sprintf('%s has been %s for %d months. The last lead received was %s. %s',
            $company,
            'paused',
            $since,
            $lastLead,
            sprintf('%sleads/supplier_view?supplier_id=%d', $siteURLSSL, $supplierId )
        );

        if(sendBasecampNotification($message)){
            $SQL = "INSERT INTO log_supplier (supplier, category, title, description, submitted, page) VALUES ($supplierId, 'notification', 'Basecamp Notification', '$since Months Without Leads', NOW(), 'basecamp')";
            db_query($SQL);
        }
    }
}

function sendBasecampNotification($message)
{
    $bc = new Basecamp();
    global $bcTrialCampfireID;
    $return = $bc->new_campfire_message($bcTrialCampfireID, $message);
    return $return;
}

/**
 * Checks if the passed user already has a notification for the given time period,
 * if it has, return true ( exists ) if not add it to the log suppliers table 
 * and return false
 */
function previouslySentNotification($supplierId, $interval)
{
    if ($interval >= 2 && $interval < 6) {
        $interval = 2;
    } elseif ($interval >= 6 && $interval < 11) {
        $interval = 6;
    } else { // 11+
        $interval = 11;
    }

    $SQL = "SELECT * FROM log_supplier WHERE supplier = $supplierId AND category = 'notification' and title = 'Basecamp Notification' and description LIKE '%Months Without Leads' and TIMESTAMPDIFF(MONTH, submitted, NOW()) <= $interval";
    $log = db_query($SQL);
    if (mysqli_num_rows($log) > 0) {
        return true;
    }
    return false;
}