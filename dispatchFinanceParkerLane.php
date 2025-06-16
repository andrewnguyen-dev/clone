<?php
error_reporting(E_ALL);

include('global.php');

$SQL = " 
SELECT lf.record_num, lead_id, partner, lf.status, l.fName, l.lName, l.phone, l.email, l.iAddress, l.iPostcode, iState, iCity,
systemDetails, quoteDetails
FROM lead_finance lf
inner join leads l on lf.lead_id = l.record_num
WHERE lf.status = 'sending' AND lf.partner = 'ParkerLane'
ORDER BY lf.record_num ASC LIMIT 5;
";
$leads = db_query($SQL);

while ($lead = mysqli_fetch_array($leads, MYSQLI_ASSOC)) {
    $SQL = "UPDATE lead_finance SET status = 'processing', updated = $nowSql WHERE record_num = {$lead['record_num']}";
    db_query($SQL);

    processParkerLane($lead);
}

function processParkerLane($lead)
{
    global $parkerlane, $nowSql;
    echo 'Process ParkerLane' . PHP_EOL;
    $accessToken = parkerlaneToken();

    $url = $parkerlane['url'] . '/ReferralLeadIntegration';
    $headers = array(
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    );

    $systemDetails = unserialize(base64_decode($lead['systemDetails']));
    $quoteDetails = unserialize(base64_decode($lead['quoteDetails']));

    $data = array(
        'productType' => 'Solar',     // Solar
        'cFName' => $lead['fName'],
        'cLName' => $lead['lName'],
        'cEmail' => $lead['email'],
        'cMobilePhone' => $lead['phone'],
        'InstallationAddress' => $lead['iAddress'] . ', ' . $lead['iCity'] . ', ' . $lead['iState'] . ', ' . $lead['iPostcode'],
        'systemType' => 'Residential',  // Residential
        'systemSize' => $systemDetails['System Size:'] ?? '',
        'QuarterlyEnergyBill' => $quoteDetails['Quarterly Bill:'] ?? '',
    );

    echo json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    echo 'ParkerLane Endpoint Response: ' . json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;

    // Get the status code
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Check the status code
    if (in_array($status_code, [200, 202])) {
        $jsonResponse = json_decode($response, true);

        echo PHP_EOL . 'Finished Processing ParkerLane' . PHP_EOL;

        $SQL = "UPDATE lead_finance SET partner_request_id = '{$jsonResponse['LeadId']}', status = 'dispatched', updated = $nowSql WHERE record_num = {$lead['record_num']}";
        db_query($SQL);

        return true;
    } else {
        echo 'Error Processing Parkerlane' . PHP_EOL;
        echo 'Error Status Code: ' . $status_code . PHP_EOL;
        echo $status_code . PHP_EOL;
        return false;
    }
}

function parkerlaneToken()
{
    global $parkerlane, $phpdir;
    echo 'Request Parkerlane Token ' . PHP_EOL;
    // Path to the JSON file
    $jsonFile = "$phpdir/finance_tokens/parkerlane.json";
    $jsonContent = '';
    $refreshFile = false;
    if (!file_exists($jsonFile)) {
        $refreshFile = true;
    } else {
        $jsonContent = json_decode(file_get_contents($jsonFile), true);
        if (is_null($jsonContent) || $jsonContent['expires_at'] < time()) {
            $refreshFile = true;
        }
    }

    if ($refreshFile) {
        echo 'Refreshing ParkerLane Token' . PHP_EOL;

        $ch = curl_init();

        $postFields = [
            'grant_type' => 'password',
            'client_id' => $parkerlane['client_id'],
            'client_secret' => $parkerlane['client_secret'],
            'username' => $parkerlane['username'],
            'password' => $parkerlane['password']
        ];

        $options = [
            CURLOPT_URL => $parkerlane['url_token'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ];

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        echo 'ParkerLane TOKEN Endpoint Response: ' . json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;

        // Get the HTTP status code
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        echo 'ParkerLane TOKEN Endpoint Response Status code: ' . $httpStatusCode . PHP_EOL;

        if ($httpStatusCode != 200) {
            $error = curl_error($ch);
            curl_close($ch);
            echo $error . PHP_EOL;
            return false;
        } else {
            echo 'ParkerLane TOKEN success, now generate token file' . PHP_EOL;

            $jsonResponse = json_decode($response, true);
            // As opposed to smartme token, parkerlane doesn't send when it expires, so refresh every 12 hours ( SmartMe lasts 24 hours)
            $jsonResponse['expires_at'] = round($jsonResponse['issued_at'] / 1000) + 21600;
            file_put_contents($jsonFile, json_encode($jsonResponse));
        }

        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return false;
        }
    }

    // Get file information
    $jsonContent = json_decode(file_get_contents($jsonFile), true);
    return $jsonContent['access_token'];
}
