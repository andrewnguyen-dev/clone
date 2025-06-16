<?php

require_once('global.php');

// Function to write text to log file
function write_log($text) {
    global $nowSql;
    db_query("INSERT INTO log_postcode_throttling (content, submitted) VALUES ('$text', ".$nowSql.")");
}

$throttling = db_getVal("SELECT postcode_throttling FROM settings LIMIT 1");
if ($throttling != '1' && $throttling != 1) {
    write_log("Status update terminated as postcode throttling is turned off in settings.");
    return;
}

// Status possible values
$statusnames = ['none', '2qs', '2qf', '1qs', '1qf'];

// Get Throttle Settings
$settingsSQL = "SELECT 2q_suggested_trigger as 2qs, 2q_forced_trigger as 2qf, 1q_suggested_trigger as 1qs, 1q_forced_trigger as 1qf FROM postcode_throttle_settings LIMIT 1";
$settingsDB = db_query($settingsSQL);
foreach($settingsDB as $s) {
    $settings = $s;
}

// Get the current throttle statuses of postcodes
$statusSQL = "SELECT * FROM postcode_throttle";
$statusDB = db_query($statusSQL);
$status = [];
foreach($statusDB as $st) {
    $status[$st['postcode']] = $st['status'];
}

// Preparing the query
$leadsCondition = "WHERE L.status = 'dispatched' AND L.source != 'SolarQuoteReused' AND LS.type = 'regular' AND LS.manualLead = 'N' AND LS.extraLead = 'N' AND (TIMESTAMPDIFF(MINUTE, LS.dispatched, ".$nowSql.") < 60)";
$leadsSQL = "SELECT L.record_num as lead_id, L.iPostcode as postcode, L.requestedQuotes as requested_quotes, COUNT(LS.supplier) as suppliers FROM lead_suppliers AS LS INNER JOIN leads AS L ON LS.lead_id = L.record_num ".$leadsCondition." GROUP BY lead_id";

$rateSQL = "SELECT A.postcode, (SUM(A.suppliers) / SUM(A.requested_quotes) * 3) as rate FROM (".$leadsSQL.") as A GROUP BY A.postcode";

$rates = db_query($rateSQL);

$logtext = "Starting update of postcode throttling statuses\n";

$newstatus = [];

foreach($rates as $rate) {
    $r = $rate['rate'];
    $p = $rate['postcode'];
    if ($r > $settings['2qs']) {
        $newstatus[$p] = 'none';
    } else if ($r > $settings['2qf']) {
        $newstatus[$p] = '2qs';
    } else if ($r > $settings['1qs']) {
        $newstatus[$p] = '2qf';
    } else if ($r > $settings['1qf']) {
        $newstatus[$p] = '1qs';
    } else {
        $newstatus[$p] = '1qf';
    }

    $r = number_format($r, 2, '.', '');
    $newkey = array_search($newstatus[$p], $statusnames);
    $oldkey = isset($status[$p]) ? array_search($status[$p], $statusnames) : 0;
    if ($newkey < $oldkey) {
        // For improvement, only move one step up.
        $newstatus[$p] = $statusnames[ (array_search($status[$p], $statusnames)) - 1 ];
        db_query("UPDATE postcode_throttle SET status = '".$newstatus[$p]."' WHERE postcode = '".$p."';");
        $logtext .= "Updating $p from ".$statusnames[$oldkey]." to ".$newstatus[$p]." on rate $r\n";
    } else if ($newkey > $oldkey) {
        if (isset($status[$p])) {
            db_query("UPDATE postcode_throttle SET status = '".$newstatus[$p]."' WHERE postcode = '".$p."';");
            $logtext .= "Updating $p from ".$statusnames[$oldkey]." to ".$newstatus[$p]." on rate $r\n";
        } else {
            if ($newstatus[$p] != 'none') {
                db_query("INSERT INTO postcode_throttle (postcode, status) VALUES ('".$p."', '".$newstatus[$p]."')");
                $logtext .= "Adding $p with status ".$newstatus[$p]." on rate $r\n";
            }
        }
    }
}

write_log($logtext);