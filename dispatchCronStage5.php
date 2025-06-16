<?php
    // load global libraries
    include('global.php');


    // Get all the leads with dispatched SMS pending
    $SQL = "
        SELECT L.record_num, S.company, S.phone as supplierPhone, L.fName, L.phone, L.emailCode, L.iState
        FROM lead_suppliers LS
        JOIN suppliers S ON S.record_num = LS.supplier
        JOIN leads L ON LS.lead_id = L.record_num
        WHERE L.status = 'dispatched'
            AND TIMESTAMPDIFF(HOUR, LS.dispatched, $nowSql) < 14
            AND L.record_num NOT IN ( SELECT leadId FROM lead_sms WHERE leadId IS NOT NULL )
            AND (
                LEFT(REPLACE(L.phone, ' ', ''), 4) = '+614'
                OR LEFT(REPLACE(L.phone, ' ', ''), 3) = '614'
                OR LEFT(REPLACE(L.phone, ' ', ''), 2) = '04'
            )
        AND L.iState != ''
        ORDER BY L.record_num DESC;
    ";
    $result = db_query($SQL);

    $leads = [];
    // Group all the leads in an array
    while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
        if(!checkTime($row)) continue;
        $id = $row['record_num'];

        if(!isset($leads[$id])) {
            $URL = shortenURL("{$siteURLSSL}quote/unsigned.php?l={$id}&emailcode={$row['emailCode']}");            
            $leads[$id] = [
                'suppliers' => [],
                'name' => $row['fName'],
                'phone' => $row['phone'],
                'url' => $URL
            ];
        }

        // Add supplier to the lead's suppliers array
        $leads[$id]['suppliers'][] = [
            'company' => $row['company'],
            'supplierPhone' => $row['supplierPhone']
        ];
    }


    foreach($leads as $id => $lead) {
        $response = sendLeadSms($lead);
        if($response) {
            $response = json_decode($response);
            $to = isset($response->to) ? $response->to : 'invalid';
            $status = $response->status;
            if(in_array($status, ['queued', 'sent', 'delivered'])) {
                $status = 'sent';
            } else $status = 'failed';
            $SQL = " INSERT INTO lead_sms(leadId, phone, sent, sentStatus) VALUES ($id, '$to', $nowSql, '$status');";
        } else {
            $SQL = " INSERT INTO lead_sms(leadId, phone, sent, sentStatus) VALUES ($id, '".$lead['phone']."', $nowSql, 'failed');";
        }
        db_query($SQL);
    }


    function sendLeadSms($lead) {
        $installersCount = sizeof($lead['suppliers']);
        $installerString = ($installersCount > 1 ? "these " : "this ") .$installersCount . " " . ($installersCount > 1 ? "installers" : "installer");


        $message = "{$lead['name']}, SolarQuotes has referred you {$installerString}.\n\n";
        foreach($lead['suppliers'] as $supplier) {
            $message .= "{$supplier['company']} - {$supplier['supplierPhone']}\n";
        }
        $message .= "\nYou can see their reviews here: {$lead['url']}\n\n";
        $message .= "They should be in contact in the next 48 hrs. But feel free to call them if you want to move faster!";

        if ($lead['phone'] != '') {
            // Add the country code if it doesn't have it
            if(strpos($lead['phone'], '+') === false){
                $lead['phone'] = '+61'.$lead['phone'];
            }
            $smsBody = substr($message, 0, 1000);
            return sendSMS($lead['phone'], $smsBody);
        }
        return false;
    }

    // check if it is some time between 9am and 5pm a(accordingly to the state's timezone)
    function checkTime($lead){
        $state = $lead['iState'];
        $allowedTimes = [
            'SA'  => [ 'start' => '09:00', 'end' => '17:00' ], // +09:30
            'ACT' => [ 'start' => '08:30', 'end' => '16:30' ], // +10:00
            'NSW' => [ 'start' => '08:30', 'end' => '16:30' ], // +10:00
            'NT'  => [ 'start' => '09:00', 'end' => '17:00' ], // +09:30
            'QLD' => [ 'start' => '08:30', 'end' => '16:30' ], // +10:00
            'TAS' => [ 'start' => '08:30', 'end' => '16:30' ], // +10:00
            'VIC' => [ 'start' => '08:30', 'end' => '16:30' ], // +10:00
            'WA'  => [ 'start' => '10:30', 'end' => '18:30' ]  // +08:00
        ];

        if(!isset($allowedTimes[$state])) {
            return false;
        }

        $allowedTime = $allowedTimes[$state];
        $current = strtotime(date('H:i'));
        $start = strtotime($allowedTime['start']);
        $end = strtotime($allowedTime['end']);

        if($current >= $start && $current <= $end) {
            return true;
        } else {
            return false;
        }
    }
?>
