<?php

    require_once('global.php');
    
    // Preparing dates for query
    $yesterday = (new DateTime("yesterday", new DateTimeZone("Australia/Adelaide")))->format('U');
    $date_start = date("Y-m-d 00:00:00", $yesterday);
    $date_end = date("Y-m-d 23:59:59", $yesterday);

    $body = '';
    $codesdone = [];
    $codelists = [
        'force_2_quotes_postcodes' => 'Forced 2 Quotes Postcodes',
        'suggest_2_quotes_postcodes' => 'Suggest 2 Quotes Postcodes'
    ];

    foreach($codelists as $list => $listtitle) {

    // Get the busy postcodes from settings
    $codesSQL = "SELECT ".$list." FROM settings LIMIT 1;";
    $codes = array_map(function($v) { return intval(trim($v)); }, explode(',', db_getVal($codesSQL)));

    if (empty($codes)) continue;
    $codes = array_diff($codes, $codesdone);
    $codesdone = array_merge($codesdone, $codes);

    // Building the condition to get leads
    $leadsCondition = "WHERE L.status = 'dispatched' AND L.source != 'SolarQuoteReused' AND LS.type = 'regular' AND L.updated >= '{$date_start}' AND L.updated <= '{$date_end}' AND L.iPostcode IN ('".implode("','", $codes)."')";
    
    // Total requested quotes per postcode
    $totalReqSQL = "SELECT iPostcode as code, SUM(requestedQuotes) as requested, COUNT(record_num) as leadcount FROM leads WHERE status = 'dispatched' AND source != 'SolarQuoteReused' AND updated >= '{$date_start}' AND updated <= '{$date_end}' AND status = 'dispatched' AND iPostcode IN ('".implode("','", $codes)."') GROUP BY iPostcode";
    $totalReq = db_query($totalReqSQL);

    $totalRequested = [];
    $totalLeads = [];
    foreach($totalReq as $t) {
        $totalRequested[$t['code']] = $t['requested'];
        $totalLeads[$t['code']] = $t['leadcount'];
    }

    if (!empty($totalRequested)) {
    
        // Getting the leads

        $leadsSQL = "SELECT DISTINCT(LS.lead_id) FROM lead_suppliers AS LS INNER JOIN leads AS L ON LS.lead_id = L.record_num ".$leadsCondition;
        $dbleads = db_query($leadsSQL);

        $leads = [];
        foreach($dbleads as $lead) {
            $leads[] = $lead;
        }
        $lead_ids = array_column($leads, 'lead_id');

        // Getting data of the leads that got matched to the suppliers
        
        $matchedSQL = "SELECT iPostcode, COUNT(*) AS matched FROM lead_suppliers AS LS INNER JOIN leads AS L ON L.record_num = LS.lead_id WHERE manualLead = 'N' AND extraLead = 'N' AND lead_id IN ('".implode("','", $lead_ids)."') GROUP BY iPostcode";
        $matchResult = db_query($matchedSQL);
        
        $matches = [];
        foreach($matchResult as $m) {
            $matches[$m['iPostcode']] = $m['matched'];
        }
        
        // Getting data of the leads that were claimed by the suppliers

        $claimedSQL = "SELECT iPostcode, COUNT(*) AS claimed FROM lead_suppliers AS LS INNER JOIN leads AS L ON L.record_num = LS.lead_id WHERE manualLead = 'N' AND lead_id IN ('".implode("','", $lead_ids)."') GROUP BY iPostcode";
        $claimResult = db_query($claimedSQL);

        $claims = [];
        foreach($claimResult as $c) {
            $claims[$c['iPostcode']] = $c['claimed'];
        }

        // Getting data of the manual leads

        $manualSQL = "SELECT iPostcode, COUNT(*) AS allocated FROM lead_suppliers AS LS INNER JOIN leads AS L ON L.record_num = LS.lead_id WHERE manualLead = 'Y' AND lead_id IN ('".implode("','", $lead_ids)."') GROUP BY iPostcode";
        $manualResult = db_query($manualSQL);
        
        $manuals = [];
        foreach($manualResult as $m) {
            $manuals[$m['iPostcode']] = $m['allocated'];
        }

        // Getting data of postcodes for requested quote numbers
        $countsSQL = "SELECT iPostcode, requestedQuotes, COUNT(*) AS count FROM leads AS L WHERE L.status = 'dispatched' AND source != 'SolarQuoteReused' AND L.updated >= '{$date_start}' AND L.updated <= '{$date_end}' AND L.status = 'dispatched' AND iPostcode IN ('".implode("','", $codes)."') GROUP BY iPostcode";
        $countsResult = db_query($countsSQL);

        foreach($countsResult as $c) {
            $quoteCounts[$c['iPostcode']][$c['requestedQuotes']] = $c['count'];
        }

        // Calculate the stats and send

        $utilRate = [];
        $claimRate = [];
        $manualRate = [];
        $okcodes = [];

        
        // Building the arrays

        foreach($codes as $code) {
            
            // Make sure there are leads associated with this postcode
            
            if ( isset($totalRequested[$code]) && $totalRequested[$code] >= 1 ) {

                $okcodes[] = $code;
                
                // Calculating the utilization rate per postcode
                $utilRate[$code] = isset($matches[$code]) ? round(($matches[$code] / $totalRequested[$code]) * 3, 2) : 0;
                
                // Calculating the claim rate per postcode
                $claimRate[$code] = isset($claims[$code]) ? round(($claims[$code] / $totalRequested[$code]) * 3, 2) : 0;
                
                // Calculating the manual rate per postcode
                $manualRate[$code] = isset($manuals[$code]) ? round(($manuals[$code] / $totalRequested[$code]) * 3, 2) : 0;

            }
            
        }
        
        // Sorting the postcodes
        asort($okcodes);

        // Printing the table
        
        $cellStyle = 'style="border: 1px solid #aaa; padding: 3px 6px; text-align: center;"';
        $headcellStyle = 'style="padding: 3px 6px; text-align: center;"';

        $table = '<table style="border-collapse: collapse;"><thead style="background: #2064a2; color: white; border: none;"><tr><th '.$headcellStyle.'>Postcode</th><th '.$headcellStyle.'>Lead Count</th><th '.$headcellStyle.'>1 Quote</th><th '.$headcellStyle.'>2 Quotes</th><th '.$headcellStyle.'>3 Quotes</th><th '.$headcellStyle.'>Utilization Rate</th><th '.$headcellStyle.'>Rate with Claim</th><th '.$headcellStyle.'>Rate with Manual</th><th '.$headcellStyle.'>Rate with Claim and Manual</th></tr></thead><tbody>';

        foreach($okcodes as $code) {
            $utilCellStyle = ($utilRate[$code] > 2) ? $cellStyle : substr($cellStyle, 0, -1).' background: #'.($utilRate[$code] > 1 ? 'fed8b1' : 'ff0000').';"';
            $table .= '<tr><th '.$cellStyle.'>'.$code.'</th><td '.$cellStyle.'>'.$totalLeads[$code].'<td '.$cellStyle.'>'.(isset($quoteCounts[$code]['1']) ? $quoteCounts[$code]['1'] : '').'</td><td '.'<td '.$cellStyle.'>'.(isset($quoteCounts[$code]['2']) ? $quoteCounts[$code]['2'] : '').'</td><td '.'<td '.$cellStyle.'>'.(isset($quoteCounts[$code]['3']) ? $quoteCounts[$code]['3'] : '').'</td><td '.$utilCellStyle.'>'.$utilRate[$code].'</td><td '.$cellStyle.'>'.$claimRate[$code].'</td><td '.$cellStyle.'>'.$manualRate[$code].'</td><td '.$cellStyle.'>'.round((isset($claims[$code]) ? (($claims[$code] / $totalRequested[$code]) * 3) : 0) + (isset($manuals[$code]) ? (($manuals[$code] / $totalRequested[$code]) * 3) : 0),2).'</td></tr>';
        }

        $table .= '</tbody></table>';

    } else {
        // No requested quotes found
        $table = 'No quotes requested.';
    }

    $body .= '<br><h3>'.$listtitle.'</h3>'.$table.'<br>';
    
    }

    $subject = 'Rate Statistics for '.date("Y-m-d", $yesterday);
    SendMail('robert@solarquotes.com.au', 'Robert Moffa', $subject, $body,'','',['emailTemplate' => 'wide']);
    SendMail('trevor@solarquotes.com.au', 'Trevor Glen', $subject, $body,'','',['emailTemplate' => 'wide']);
    SendMail('johnb@solarquotes.com.au', 'John Burcher', $subject, $body,'','',['emailTemplate' => 'wide']);

?>
