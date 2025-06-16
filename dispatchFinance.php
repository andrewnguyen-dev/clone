<?php
error_reporting(E_ALL);

include('global.php');
$start_full = microtime(true);

global $smartMe, $driva;

$SQL = " 
SELECT lf.record_num, lead_id, partner, lf.status, lf.request_type, 
l.fName, l.lName, l.phone, l.email, l.iAddress, l.iPostcode, iState, iCity, lr.landingPage
FROM lead_finance lf
INNER JOIN leads l on lf.lead_id = l.record_num
LEFT JOIN leads_referers lr ON l.referer = lr.record_num
WHERE lf.status = 'sending' AND lf.partner != 'ParkerLane'
ORDER BY lf.record_num ASC LIMIT 10;
";
$leads = db_query($SQL);

while ($lead = mysqli_fetch_array($leads, MYSQLI_ASSOC)) {
    // Set lead as "processing"
    $SQL = "UPDATE lead_finance SET status = 'processing', updated = $nowSql WHERE record_num = {$lead['record_num']}";
    db_query($SQL);
    // Check which partner we need to handle
    switch ($lead['partner']) {
        case 'SmartMe':
            processSmartMe($lead);
            break;
        case 'Driva':
            processDriva($lead);
            break;
        default:
            break;
    }
}

function processSmartMe($lead)
{
    echo 'Processing SmartMe Step 1' . PHP_EOL;
    global $smartMe, $nowSql;
    // We need to retrieve the token first
    $accessToken = smartMeToken();
    if (!$accessToken) {
        echo 'Error retrieving SmartMe Authentication token' . PHP_EOL;
        return false;
    }
    $ch = curl_init();

    $blankPos = strpos($lead['iAddress'], ' ');
    $streetNumber = substr($lead['iAddress'], 0, $blankPos);
    $streetName = substr($lead['iAddress'], $blankPos + 1);

    $postFields = [
        'property' => [
            'streetNumber' => $streetNumber,
            'streetName' => $streetName,
            'streetType' => 'Road',
            'postcode' => $lead['iPostcode'],
            'suburb' => $lead['iCity'],
            'state' => $lead['iState'],
            'occupancyType' => 'Current',
        ],
        'serviceTypes' => [
            [
                'serviceType' => 'SolarFinance',
            ]
        ],
        'customer' => [
            'id' => $lead['lead_id'],
            'emailAddress' => $lead['email'],
            'firstName' => $lead['fName'],
            'surname' => $lead['lName'],
            'mobilePhoneNumber' => $lead['phone']
        ]
    ];

    if($lead['request_type'] == 'Maybe')
        $apiEndpoint = $smartMe['url'] . '/api/v1/compare/property';
    else
        $apiEndpoint = $smartMe['url'] . '/api/v1/lead/property';

    $options = [
        CURLOPT_URL => $apiEndpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postFields),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]
    ];

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    echo 'SmartMe Step 1 Endpoint Response: ' . json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;

    // Get the status code
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Check the status code
    if (in_array($status_code, [200, 202])) {
        $jsonResponse = json_decode($response, true);
        // Now update the entry with the partner_request_id
        $SQL = "UPDATE lead_finance SET partner_request_id = '{$jsonResponse['referenceCode']}', status = 'dispatched', updated = $nowSql WHERE record_num = {$lead['record_num']}";
        db_query($SQL);
        echo PHP_EOL . 'Finished Processing SmartMe Step 1' . PHP_EOL;
    } else {
        echo 'Error Processing SmartMe Step 1' . PHP_EOL;
        echo 'Error: ' . curl_error($ch) . PHP_EOL;
        return false;
    }

    echo 'Processing SmartMe Step 2' . PHP_EOL;

    // Now that the intial call has been made, do we need another status API call
    if ($lead['landingPage']) {
        $queryString = parse_url($lead['landingPage'], PHP_URL_QUERY);

        // Parse the query string into an array of parameters
        parse_str($queryString ?? '', $parameters);

        if ((isset($parameters['utm_source'])) && ($parameters['utm_source'] == 'smartme') && (isset($parameters['utm_content']))) {
            $utmContentValue = $parameters['utm_content'];
            
            $ch = curl_init();

            $postFields = [
                'referenceId' => $utmContentValue,
                'Status' => 'Converted'
            ];

            $options = [
                CURLOPT_URL => $smartMe['url'] . '/api/v1/affiliate/status',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postFields),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken
                ]
            ];

            curl_setopt_array($ch, $options);

            $response = curl_exec($ch);
            echo 'SmartMe Step 2 Endpoint Response: ' . json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;
        } else {
            echo PHP_EOL . 'Finished Processing SmartMe Step 2.  No API call required' . PHP_EOL;
        }
    } else {
        echo PHP_EOL . 'Finished Processing SmartMe Step 2' . PHP_EOL;
    }

    return true;
}

function smartMeToken()
{
    global $phpdir;
    echo 'Request SmartMe Token ' . PHP_EOL;
    // Path to the JSON file
    $jsonFile = "$phpdir/finance_tokens/smartme.json";
    $jsonContent = '';
    $refreshFile = false;
    if (!file_exists($jsonFile)) {
        // Create file doesn't exist
        fopen($jsonFile, 'w');
        $refreshFile = true;
    } else {
        $jsonContent = json_decode(file_get_contents($jsonFile), true);
        if (is_null($jsonContent) || $jsonContent['expires_at'] < time()) {
            $refreshFile = true;
        }
    }

    if ($refreshFile) {
        echo 'Refreshing SmartMe token' . PHP_EOL;
        global $smartMe;
        $ch = curl_init();

        $postFields = [
            'client_id' => $smartMe['client_id'],
            'client_secret' => $smartMe['client_secret'],
            'grant_type' => 'client_credentials',
            'audience' => $smartMe['audience']
        ];

        $options = [
            CURLOPT_URL => $smartMe['url_token'] . '/oauth/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postFields),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ];

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        echo 'SmartMe TOKEN Endpoint Response: ' . json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;
        // Get the HTTP status code
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpStatusCode != 200) {
            $error = curl_error($ch);
            curl_close($ch);
            return false;
        } else {
            $jsonResponse = json_decode($response, true);
            $jsonResponse['expires_at'] = time() + $jsonResponse['expires_in'];
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

function processDriva($lead)
{
    global $driva, $nowSql;
    echo 'Processing Driva' . PHP_EOL;

    $blankPos = strpos($lead['iAddress'], ' ');
    $streetNumber = substr($lead['iAddress'], 0, $blankPos);
    $streetName = substr($lead['iAddress'], $blankPos + 1);

    $data = array(
        'user' => array(
            'firstName' => $lead['fName'],
            'lastName' => $lead['lName'],
            'mobile' => $lead['phone'],
            'email' => $lead['email'],
            'address' => array(
                'streetNumber' => $streetNumber,
                'street' => $streetName,
                'state' => $lead['iState'],
                'suburb' => $lead['iCity'],
                'postCode' => $lead['iPostcode']
            )
        )
    );

    $ch = curl_init();

    $options = [
        CURLOPT_URL => $driva['url'] . '/quote/partial/personal-loan' ,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            'x-api-key: ' . $driva['apiKey'],
            'partnerId: ' . $driva['partnerId'],
            'Content-Type: application/json'
          ),
        CURLOPT_RETURNTRANSFER => true,
    ];

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    
    echo 'Driva Endpoint Response: ' . json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;

    // Get the status code
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Check the status code
    if (in_array($status_code, [200, 202])) {
        $jsonResponse = json_decode($response, true);
        // Now update the entry with the partner_request_id
        $SQL = "UPDATE lead_finance SET partner_request_id = '{$jsonResponse['uuid']}', status = 'dispatched', updated = $nowSql WHERE record_num = {$lead['record_num']}";
        db_query($SQL);
        
        echo PHP_EOL . 'Finished Processing Driva' . PHP_EOL;

        return true;
    } else {
        echo 'Error Processing Driva' . PHP_EOL;
        echo 'Error: ' . curl_error($ch) . PHP_EOL;
        return false;
    }
}
