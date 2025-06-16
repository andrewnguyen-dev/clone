<?php
include_once('global.php');

function refreshOriginToken() {
    global $origin, $nowSql;
    $apiEndpoint = $origin['authEndpoint'];
    $curl = curl_init();
    $originClientId = $origin['clientId'];
    $originClientSecret = $origin['clientSecret'];

    curl_setopt_array($curl, [
        CURLOPT_URL => $apiEndpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{
            "client_id": "'.$originClientId.'",
            "client_secret": "'.$originClientSecret.'",
            "audience": "https://solarquotes-api",
            "grant_type": "client_credentials"
        }',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        echo 'Error:'.curl_error($curl);
    } else {
        $responseData = json_decode($response, true);
        if (isset($responseData['access_token'])) {
            $accessToken = $responseData['access_token'];
            $expiresIn = $responseData['expires_in'];

            // Retrieve existing tokens from the database
            $query = "INSERT INTO global_data(id, type, name, description, created) VALUES(1, 'origin_auth','origintoken','$accessToken',$nowSql);";
            $queryExpire = "INSERT INTO global_data(id, type, name, description, created) VALUES(2, 'origin_auth','originexpire','$expiresIn',$nowSql);";
            $existingToken = getOriginToken(false);
            if ($existingToken) {
                // Update existing tokens
                $query = "UPDATE global_data SET description = '$accessToken', created = $nowSql WHERE name = 'origintoken'";
                $queryExpire = "UPDATE global_data SET description = '$expiresIn', created = $nowSql WHERE name = 'originexpire'";
            }
            db_query($query);
            db_query($queryExpire);

            echo 'Access token updated successfully: '.$accessToken;
        } else {
            echo 'Failed to retrieve access token.';
        }
    }

    curl_close($curl);
    echo $response;
}

/**
 * Get the Origin token from the database
 * if it has expired and $validate is true return false
 * 
 * @param bool $validate
 */
function getOriginToken($validate = true) {
    global $conn, $nowSql;
    if($validate){
        $queryExpire = "SELECT $nowSql > created + INTERVAL description SECOND as expired FROM global_data WHERE name = 'originexpire'";
        $resultExpire = db_query($queryExpire);
        if ($resultExpire && mysqli_num_rows($resultExpire) > 0) {
            $rowExpire = mysqli_fetch_assoc($resultExpire);
            $expired = $rowExpire['expired'];
            if($expired > 0) {
                return false;
            }
        }
    }

    $query = "SELECT id, type, name, description, created FROM global_data WHERE type = 'origin_auth'";
    $result = db_query($query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        return $row['description'];
    }

    return null;
}

function getLeadPayload($leadId) {
    $lead = loadLeadData($leadId);
    $payload = [];
    $quoteDetails = unserialize(base64_decode($lead['rawquoteDetails']));
    $siteDetails = unserialize(base64_decode($lead['rawsiteDetails']));
    $systemDetails = unserialize(base64_decode($lead['rawsystemDetails']));

    $sql = "
        SELECT ls.record_num, s.record_num supplierId, s.company, ls.status, 
        ls.leadStatus, ls.extraLead, ls.priceType, ls.leadPrice, 
        s.record_num supplierId, ls.rejectReason, ls.leadPrice, s.parent supplierParentId, 
        sp.parentName supplierParentName
        FROM lead_suppliers ls
        LEFT JOIN suppliers s ON ls.supplier = s.record_num
        INNER JOIN suppliers_parent sp ON s.parent = sp.record_num
        WHERE ls.lead_id = $leadId
        order by ls.leadPrice DESC
    ";
    $leadSuppliersResult = db_query($sql);
    $leadSuppliers = [];

    $hasClaimedLeads = 'No';
    $leadType = 'On Grid Solar';
    while ($row = mysqli_fetch_array($leadSuppliersResult, MYSQLI_ASSOC)) {
        $leadSuppliers[$row['supplierId']] = $row;
        if ('On Grid Solar' == $leadType && 'On Grid Solar' != $row['priceType']) {
            $leadType = $row['priceType'];
        }
        if ('Y' === $row['extraLead'] && 'No' === $hasClaimedLeads) {
            $hasClaimedLeads = 'Yes';
        }
    }

    // Quote details
    $installTimeFrame = $quoteDetails['Timeframe for purchase:'] ?? '';
    $billAmount = isset($quoteDetails['Quarterly Bill:']) ? (string) $quoteDetails['Quarterly Bill:'] : '';
    $homeVisit = $quoteDetails['Asked for home visit?'] ?? '';
    $priceType = $quoteDetails['Price Type:'] ?? '';

    // System details
    $systemSize = isset($systemDetails['System Size:']) ? (string) $systemDetails['System Size:'] : '';
    $features = $systemDetails['Features:'] ?? '';

    // Site details
    $roofType = $siteDetails['Type of Roof:'] ?? '';
    $numberOfStoreys = isset($siteDetails['How many storeys?']) ? (string) $siteDetails['How many storeys?'] : '';
    $anythingElseSplit = '';
    if (isset($siteDetails['Anything Else:']) && '' != $siteDetails['Anything Else:']) {
        $anythingElseSplit = str_replace(["\n\r", "\n", "\r"], ':', $siteDetails['Anything Else:']);
        $anythingElseSplit = clearNonPrintableChars($anythingElseSplit);
    }

    $payload = [
        'leadId' => $lead['record_num'],
        'submittedDate' => $lead['submitted'],
        'updatedDate' => $lead['submitted'],
        'leadStatus' => $lead['status'],
        'requestedQuotes' => (string) $lead['requestedQuotes'],
        'hasClaimedLeads' => $hasClaimedLeads,
        'systemSize' => $systemSize,
        'systemPriceType' => $priceType,
        'installTimeFrame' => $installTimeFrame,
        'billFrequency' => 'quarterly',
        'billAmount' => $billAmount,
        'roofType' => $roofType,
        'numberOfStoreys' => $numberOfStoreys,
        'features' => $features,
        'homeVisit' => $homeVisit,
        'importantNotesSplit' => $anythingElseSplit,
        'type' => $leadType,
    ];

    $payload['customer'] = getCustomer($lead);
    $payload['address'] = getAddress($lead);

    if (isset($lead['evType'])) {
        $payload['evCharger'] = getEVCharger($siteDetails);
    } else if (isset($lead['hwhpType'])) {
        $payload['hotWaterHeatPump'] = getHWHP($siteDetails);
    }

    $payload['leadSuppliers'] = getLeadSuppliersUpdated($leadSuppliers);
    return $payload;
}

function sendToOriginApi($payloadArray) {
    global $origin, $techName, $techEmail;
    $originEndpoint = $origin['apiEndpoint'];
    $maxRetries = 4;
    $retryTime = 4;
    $timeoutLimit = 5;
    set_time_limit(120);

    try {
        $leadId = isset($payloadArray['leadId']) ? $payloadArray['leadId'] : '';

        $apiToken = getOriginToken(true);
        if($apiToken === false){
            $errorMessage = "OriginElectrify: Expired Access Token";
            error_log($errorMessage);

            // Extra condition to account for any issues in the processing getting the lead_id
            if($leadId) {
                $query = 'UPDATE leads SET status = "processing" WHERE record_num = '.$leadId;
                db_query($query);
            }
            return false;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $originEndpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $timeoutLimit,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payloadArray),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer '.$apiToken,
            ],
        ]);

        for ($attempt = 0; $attempt <= $maxRetries; ++$attempt) {
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if (false !== $response && 200 === $httpCode) {
                $success = true;
                break;
            } else {
                if ($attempt == $maxRetries) {
                    break;
                }
                $retryDelay = pow($retryTime, $attempt);
                sleep($retryDelay);
            }
        }

        curl_close($curl);

        if (isset($success)) {
            return true;
        } else {
            $errorTitle = 'OriginElectrify: Failed API Call';
            $errorMessage = "OriginElectrify: POST request to URL $originEndpoint with data from lead $leadId was dropped after $maxRetries retries.";

            // Replace this with your logging function
            error_log($errorMessage);

            SendMail($techEmail, $techName, $errorTitle, $errorMessage);

            return false;
        }
    } catch (Exception $ex) {
        error_log("OriginElectrify: Failed to send lead $leadId to $originEndpoint - ".$ex->getMessage());

        return false;
    }
}


/** HELPER FUNCTIONS */
function clearNonPrintableChars($string) {
    return preg_replace('/[^[:print:]\n]/u', '', $string);
}

function getLeadSuppliersUpdated($leadSuppliers) {
    $leadSuppliersData = [];
    foreach ($leadSuppliers as $supplierId => $leadSupplier) {
        $leadSupplierData = [
            'id' => (string) $leadSupplier['record_num'],
            'supplierParentId' => '',
            'supplierParentName' => '',
            'supplierId' => (string) $supplierId,
            'supplierName' => $leadSupplier['company'],
            'status' => $leadSupplier['status'],
            'leadStatus' => $leadSupplier['leadStatus'] ?? '',
            'rejectedReason' => $leadSupplier['rejectReason'] ?? '',
            'leadPrice' => (string) $leadSupplier['leadPrice'],
            'claimed' => 'Y' === $leadSupplier['extraLead'] ? 'Yes' : 'No',
        ];
        if (1 != $leadSupplier['supplierParentId']) {
            $leadSupplierData['supplierParentId'] = (string) $leadSupplier['supplierParentId'];
            $leadSupplierData['supplierParentName'] = $leadSupplier['supplierParentName'];
        }

        $leadSuppliersData[] = $leadSupplierData;
    }

    return $leadSuppliersData;
}

function getCustomer($lead) {
    $customer = [
        'firstName' => $lead['fName'],
        'lastName' => $lead['lName'],
        'businessName' => $lead['companyName'] ?? '',
        'phone' => (string) $lead['phone'],
        'email' => $lead['email'],
    ];

    return $customer;
}

function getAddress($lead) {
    $address = [
        'lineOne' => $lead['iAddress'],
        'lineTwo' => $lead['iAddress2'],
        'suburb' => $lead['iCity'],
        'state' => $lead['iState'],
        'postcode' => (string) $lead['iPostcode'],
        'latitude' => (string) $lead['latitude'],
        'longitude' => (string) $lead['longitude'],
    ];

    return $address;
}

function getHWHP($siteDetails) {
    $hwhpData = [
        'numberOfResidents' => isset($siteDetails['Number of Residents:']) ? (string) $siteDetails['Number of Residents:'] : '',
        'existingSystem' => $siteDetails['Existing Hot Water System:'] ?? '',
        'locationAccessibility' => $siteDetails['Location Accessibility:'] ?? '',
        'switchboardDistance' => $siteDetails['Distance between heat pump and switchboard:'] ?? '',
    ];

    return $hwhpData;
}

function getEVCharger($siteDetails) {
    $evChargerData = [
        'existingSolarSize' => isset($siteDetails['Existing solar size:']) ? (string) $siteDetails['Existing solar size:'] : '',
        'existingBattery' => $siteDetails['Have battery?'] ?? '',
        'installationType' => $siteDetails['EV Installation Type:'] ?? '',
        'distanceChargerSwitchboard' => $siteDetails['Distance between charger and switchboard:'] ?? '',
        'carMakeModel' => $siteDetails['Car Make/Model:'] ?? '',
    ];

    return $evChargerData;
}
