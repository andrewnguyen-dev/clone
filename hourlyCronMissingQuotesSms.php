<?php
    // load global libraries
    include('global.php');
    require('solar_locations.php');

    // Get all missing quotes sms pending
    $SQL = "
        SELECT 
            M.*, S.company, S.state as state,
            L.fName, L.lName, L.iAddress as leadAddress, 
            L.created as leadCreated, L.record_num as leadId
        FROM 
        missing_quote_sms M 
            JOIN suppliers S ON S.record_num = M.supplierID
            JOIN leads L on L.record_num = M.leadId
        WHERE 
            sentStatus IS NULL
        ORDER BY 
        M.created_at ASC LIMIT 10;
    ";
    $result = db_query($SQL);

    $smsList = [];
    // Group all the sms in an array
    while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
        if(!checkTime($row)) continue;
        $supplierId = $row['supplierId'];

        if(!isset($smsList[$supplierId])) {
            $smsList[$supplierId] = [
                'leads' => [],
                'supplierPhone' => $row['phone'],
            ];
        }

        // Add supplier to the supplier's leads array
        $smsList[$supplierId]['leads'][] =  [
            'missingQuotesSmsId' => $row['record_num'],
            'leadFName' => $row['fName'],
            'leadLName' => $row['lName'],
            'leadAddress' => $row['leadAddress'],
            'leadCreated' => $row['leadCreated'],
            'leadId' => $row['leadId'],
        ];
    }


    foreach($smsList as $id => $sms) {
        $response = sendSupplierSms($sms);
        if($response) {
            $response = json_decode($response);
            $to = isset($response->to) ? $response->to : 'invalid';
            $status = $response->status;
            if(in_array($status, ['queued', 'sent', 'delivered'])) {
                $status = 'sent';
            } else $status = 'failed';
        } else {
            $status = 'failed';
        }
        $responsetxt = isset($response->body) ? $response->body : $response->message;
        foreach($sms['leads'] as $lead) {
            $SQL = " UPDATE missing_quote_sms SET sent = $nowSql, phone = '$to', sentStatus = '$status', text = '" . mysqli_escape_string($_connection, $responsetxt) . "' WHERE record_num = {$lead['missingQuotesSmsId']};";
            db_query($SQL);
        }
    }


    function sendSupplierSms($supplierSMS) {
        global $twilioNumberMissingQuotesSms;
        $leadsCount = sizeof($supplierSMS['leads']);
        
        if($leadsCount == 1) {
            $lead = $supplierSMS['leads'][0];
            $leadId = $lead['leadId'];
            $leadName = $lead['leadFName'] . ' ' . $lead['leadLName'];
            $leadAddress = $lead['leadAddress'];
            $date = date('d/m/Y', strtotime($lead['leadCreated']));
            $message = "SolarQuotes lead $leadId, $leadName, located at $leadAddress, has marked your company's quote as missing. They requested a quote on $date. Please follow up with them.";
        }else{
            $date = date('d/m/Y', strtotime($supplierSMS['leads'][0]['leadCreated']));
            $message = "$leadsCount SolarQuotes leads have marked your company's quote as missing. They requested a quote on $date. Please follow up with them:\n\n";
            foreach($supplierSMS['leads'] as $lead) {
                $leadId = $lead['leadId'];
                $leadName = $lead['leadFName'] . ' ' . $lead['leadLName'];
                $leadAddress = $lead['leadAddress'];
                $message .= "Lead $leadId, $leadName, located at $leadAddress.\n";
            }
        }

        if ($supplierSMS['supplierPhone'] != '') {
            // Add the country code if it doesn't have it
            if(strpos($supplierSMS['supplierPhone'], '+') === false){
                $supplierSMS['supplierPhone'] = '+61'.$supplierSMS['supplierPhone'];
            }
            $smsBody = substr($message, 0, 1000);
            return sendSMS(
                number: $supplierSMS['supplierPhone'], 
                sms: $smsBody, 
                from: $twilioNumberMissingQuotesSms,
            );
        }
        return false;
    }

    // Check if it is some time between 9am and 5pm (accordingly to the state's timezone)
    function checkTime($sms){
        global $states_timezone_names;
        $state = $sms['state'];

        $timezone = $states_timezone_names[$state];
        $now = new DateTime('now', new DateTimeZone($timezone));
        
        $allowedFrom = 900;
        $allowedTo = 1700;

        $now = intval($now->format('Gi'));
        return $now >= $allowedFrom && $now <= $allowedTo;
    }
?>
