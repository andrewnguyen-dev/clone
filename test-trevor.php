<?php
    error_reporting(E_ALL);
	ini_set('display_errors', '1');

	// load global libraries
	include('global.php');

global $siteSSLUrl, $leadcsvdir;

$base_locations = [
    'perth',
    'sydney',
    'melbourne',
    'adelaide',
    'brisbane',
    'canberra'
];

// Setup the variables
$currentSupplierID = 0;

$feedbackCount = 0;
$total_weighted = 0;
$feedbackTotal = 0;
$supplierName = "";
$supplierStatus = "";
$supplierURLName = "";


$results = [
    'perth' => [
        '36m' => [],
        'all' => []
    ],
    'sydney' => [
        '36m' => [],
        'all' => []
    ],
    'melbourne' => [
        '36m' => [],
        'all' => []
    ],
    'adelaide' => [
        '36m' => [],
        'all' => []
    ],
    'brisbane' => [
        '36m' => [],
        'all' => []
    ],
    'canberra' => [
        '36m' => [],
        'all' => []
    ]
];
$locationsCondition = '"'.implode('","', $base_locations).'"';

$query = "select location, radius, latitude, longitude from installation_locations where location IN ($locationsCondition)";
$locations = db_query($query);

// Builds an array of status 'active' parents that have at least a child that is active or pause and accepting
$SQL = " SELECT SP.record_num, 'active' AS status ";
$SQL .= " FROM suppliers_parent SP  ";
$SQL .= " INNER JOIN suppliers S on SP.record_num = S.parent ";
$SQL .= " WHERE SP.record_num > 1 and S.parentUseReview = 'Y' AND S.reviewOnly != 'Y' AND ( S.status = 'active' OR S.extraLeads = 'Y') ";
$SQL .= " GROUP BY SP.record_num ";

$parentsStatus = mysqli_fetch_all(db_query($SQL), MYSQLI_ASSOC);
$parentsStatus = array_combine(array_column($parentsStatus, 'record_num'), array_column($parentsStatus, 'status'));

while ( list($location, $radius, $latitude, $longitude) = mysqli_fetch_array($locations)) {
    $feedbackQuery = '
        select F.record_num as feedback_id, F.rate_value, F.rate_system_quality, F.rate_installation,
        F.rate_customer_service, F.one_year_rate_value,F.one_year_rate_system_quality,
        F.one_year_rate_installation,F.one_year_rate_customer_service,F.iState, F.feedback_date,
        S.record_num as row_supplier_id,
        S.company, S.reviewonly, case when ( S.status = "active" OR extraLeads = "Y" ) AND reviewOnly != "Y" then "active" else "paused" end AS supplierStatus,
        IF(S.parent > 1 AND S.parentUseReview = "Y", SP.parentName, S.company) as supplierName,
        IF(S.parent > 1 AND S.parentUseReview = "Y", CONCAT("SP_", SP.record_num), CONCAT("S_", S.record_num)) as supplier_id,
        IF(feedback_date > NOW() - INTERVAL 36 MONTH, 1, 0) 36m
        from feedback F
        inner join (
            select postcode, max(lat) lat, max(lon) lon
            from postcode_definition
            where type = "Delivery Area"
            group by postcode
        ) pd on F.iPostcode = pd.postcode
        INNER JOIN suppliers S ON F.supplier_id = S.record_num
        INNER JOIN suppliers_parent SP ON S.parent  = SP.record_num
        where 
            public = 1 
            AND purchased="Yes" 
            AND iState != ""
            AND ( 6371 * 2 * ASIN(SQRT( POWER(SIN((pd.lat - ' .  $latitude . ') *  pi()/180 / 2), 2) + COS(pd.lat * pi()/180) * COS(' .  $latitude . ' * pi()/180) * POWER(SIN((pd.lon - ' .  $longitude . ' ) * pi()/180 / 2), 2) )) <= '.$radius.' )
        ORDER BY supplierName ASC, F.record_num DESC
    ;';

    $feedbackResult = db_query($feedbackQuery);

    while($feedbackRow = mysqli_fetch_array($feedbackResult, MYSQLI_ASSOC)){
        extract(htmlentitiesRecursive($feedbackRow), EXTR_PREFIX_ALL, 'f');
        // Initialize to prevent Undefined Index warnings

        if(!isset($results[$location]['all'][$f_supplier_id])){
            $results[$location]['all'][$f_supplier_id] = [
                'total' => 0,
                'total_weighted' => 0,
                'count' => 0,
                'name' => $f_supplierName,
                'status' => $f_supplierStatus,
                'url' => sanitizeURL($f_supplierName),
                'supplier_id' => $f_supplier_id
            ];
        }
        if(!isset($results[$location]['36m'][$f_supplier_id])){
            $results[$location]['36m'][$f_supplier_id] = [
                'total' => 0,
                'total_weighted' => 0,
                'count' => 0,
                'name' => $f_supplierName,
                'status' => $f_supplierStatus,
                'url' => sanitizeURL($f_supplierName),
                'supplier_id' => $f_supplier_id
            ];
        }

        // Are we using the original or one year followup figures
        if (is_numeric($f_one_year_rate_value)) {
            $f_rate_value = $f_one_year_rate_value;
            $f_rate_system_quality = $f_one_year_rate_system_quality;
            $f_rate_installation = $f_one_year_rate_installation;
            $f_rate_customer_service = $f_one_year_rate_customer_service;
        }

        $calculatedTotalWeighted = calculateReviewWeighted($f_rate_value, $f_rate_system_quality, $f_rate_installation, $f_rate_customer_service);
        $calculatedTotal = calculateReview($f_rate_value, $f_rate_system_quality, $f_rate_installation, $f_rate_customer_service);

        if($f_36m == 1){
            $results[$location]['36m'][$f_supplier_id]['count'] += 1;
            $results[$location]['36m'][$f_supplier_id]['total'] += $calculatedTotal;
            $results[$location]['36m'][$f_supplier_id]['total_weighted'] += $calculatedTotalWeighted;    
        }
        $results[$location]['all'][$f_supplier_id]['count'] += 1;
        $results[$location]['all'][$f_supplier_id]['total'] += $calculatedTotal;
		$results[$location]['all'][$f_supplier_id]['total_weighted'] += $calculatedTotalWeighted;
    }
}

foreach($results as $location => $local_values){
    foreach($local_values as $timeframe => $time_values){
        $results[$location][$timeframe] = array_filter($time_values, function($val){
            return $val['count'] >= 15;
        });
    }
}

$resultMostGood = $results;
$resultAverageScore = $results;

foreach($resultMostGood as $location => $local_values){
    foreach($local_values as $timeframe => $time_values){
        usort($time_values, function($a, $b){
            if ($a['total_weighted'] == $b['total_weighted']) {
                return 0;
            }
            return ($a['total_weighted'] > $b['total_weighted']) ? -1 : 1;
        });
        $resultMostGood[$location][$timeframe] = $time_values;
    }
}

foreach($resultAverageScore as $location => $local_values){
    foreach($local_values as $timeframe => $time_values){
        usort($time_values, function($a, $b){
            $a_avg = $a['total'] / $a['count'];
            $b_avg = $b['total'] / $b['count'];
            if ($a_avg == $b_avg) {
                return 0;
            }
            return ($a_avg > $b_avg) ? -1 : 1;
        });
        $resultAverageScore[$location][$timeframe] = $time_values;
    }
}

foreach($base_locations as $local){
    $fileName = $leadcsvdir . '/top_installers_'.$local.'.csv';
    // Open the file
    $file = fopen($fileName, 'w');
    cityToFile($file, $local, $resultMostGood[$local], 'MOST GOOD');
    fputcsv($file, []);
    cityToFile($file, $local, $resultAverageScore[$local], 'AVERAGE SCORE');
    fclose($file);
}

function calculateReviewWeighted($f_rate_value, $f_rate_system_quality, $f_rate_installation, $f_rate_customer_service) {
    // Get the current values for this feedback
    $feedbackSum = 0;
    $average = 4;

    if ($f_rate_value > 0)
        $feedbackSum = rawToWeightedFeedback($f_rate_value);
    else
        $average -= 1;

    if ($f_rate_system_quality > 0)
        $feedbackSum += rawToWeightedFeedback($f_rate_system_quality);
    else
        $average -= 1;

    if ($f_rate_installation > 0)
        $feedbackSum += rawToWeightedFeedback($f_rate_installation);
    else
        $average -= 1;

    if ($f_rate_customer_service > 0)
        $feedbackSum += rawToWeightedFeedback($f_rate_customer_service);
    else
        $average -= 1;

    // This is now taking into consideration N/A
    if ($average > 0)
        return $feedbackSum / $average * 4;
    else
        return -100;
}

function calculateReview($f_rate_value, $f_rate_system_quality, $f_rate_installation, $f_rate_customer_service) {
    // Get the current values for this feedback
    $feedbackSum = 0;
    $average = 4;

    if ($f_rate_value > 0)
        $feedbackSum = $f_rate_value;
    else
        $average -= 1;

    if ($f_rate_system_quality > 0)
        $feedbackSum += $f_rate_system_quality;
    else
        $average -= 1;

    if ($f_rate_installation > 0)
        $feedbackSum += $f_rate_installation;
    else
        $average -= 1;

    if ($f_rate_customer_service > 0)
        $feedbackSum += $f_rate_customer_service;
    else
        $average -= 1;

    // This is now taking into consideration N/A
    if ($average > 0)
        return $feedbackSum / $average * 4;
    else
        return false;
}

function rawToWeightedFeedback($raw) {
    switch ($raw) {
        case 1:
            return -3;
            break;
        case 2:
            return -2;
            break;
        case 3:
            return -1;
            break;
        case 4:
            return 1;
            break;
        case 5:
            return 2;
            break;
        default:
            return 0;
            break;
    }
}

function cityToFile($file, $location, $data, $sectionTitle){
    global $siteSSLUrl, $leadcsvdir;

    foreach($data as $timeframe => $time_values){
        fputcsv($file, ['TIMEFRAME: ' . strtoupper($timeframe)]);
        $top = array_slice($time_values, 0, 10);
        $bottom = array_slice(array_reverse($time_values), 0, 10);
        fputcsv($file, ['','TOP ' . strtoupper($sectionTitle)]);
        fputcsv($file, ['','','Supplier Name','Review Count', 'Avg Score', 'Address', 'Email', 'Phone', 'XeroId', 'Public Address', 'Public Phone', 'URL', 'Main Contact Name']);
        foreach($top as $pos => $supplier){
            $is_parent = stripos($supplier['supplier_id'], 'SP_') !== false;
            $supplier_id = str_replace(['SP_', 'S_'], '', $supplier['supplier_id']);
            $supplier_contact = 'SELECT publicPhone, publicAddress, address, city, state, postcode, phone, mainContactfName, mainContactlName, mainContactEmail, xeroContactID, email FROM suppliers WHERE record_num = ' . $supplier_id;
            if($is_parent){
                $supplier_contact = 'SELECT publicPhone, publicAddress, address, city, state, postcode, "" AS phone, "" AS mainContactfName, "" AS mainContactlName, "" AS mainContactEmail, xeroContactID, "" AS email FROM suppliers_parent WHERE record_num = ' . $supplier_id;
            }
            $contact_info = mysqli_fetch_array(db_query($supplier_contact));
            $address = array_filter([$contact_info['address'], $contact_info['address'], $contact_info['city'], $contact_info['state']]);
            $main_name = $contact_info['mainContactfName'] . ' ' . $contact_info['mainContactlName'];
            fputcsv($file, ['','',
                $supplier['name'], 
                $supplier['count'],
                number_format( ( $supplier['total'] / $supplier['count'] ) / 4, 2),
                implode(',', $address),
                $contact_info['email'] ?? '',
                $contact_info['phone'] ?? '',
                $contact_info['xeroContactID'] ?? '',
                $contact_info['publicAddress'], 
                $contact_info['publicPhone'], 
                $siteSSLUrl . '/installer-review/' . $supplier['url'] . '/',
                $main_name
            ]);

            if($pos === 0){ // Get the latest 5 reviews of the top supplier
                $lastFive = lastFiveReviewers($location, $supplier_id, $is_parent);
                $lastFiveFile = fopen($leadcsvdir . '/top_reviews_'.$supplier['url'].'_'.$supplier['name'].'.csv', 'w');
                fputcsv($lastFiveFile, ['Money Spent', 'Rate AVG', 'First Name', 'Last Name', 'Email', 'Phone', 'Postcode', 'Use Review', 'Title', 'Final Thought']);
                foreach($lastFive as $review){
                    fputcsv($lastFiveFile,[
                        $review['money_spent'],
                        $review['rate_avg'],
                        $review['fName'],
                        $review['lName'],
                        $review['email'],
                        $review['phone'],
                        $review['iPostcode'],
                        $review['use_review'],
                        $review['title'],
                        $review['final_thought']
                    ]);
                }
                fclose($lastFiveFile);
            }
        }

        fputcsv($file, ['','BOTTOM ' . strtoupper($sectionTitle)]);
        fputcsv($file, ['','','Supplier Name','Review Count', 'Avg Score', 'Address', 'Email', 'Phone', 'XeroId', 'Public Address', 'Public Phone', 'URL', 'Main Contact Name']);
        foreach($bottom as $supplier){
            $is_parent = stripos($supplier['supplier_id'], 'SP_') !== false;
            $supplier_id = str_replace(['SP_', 'S_'], '', $supplier['supplier_id']);
            $supplier_contact = 'SELECT publicPhone, publicAddress, address, city, state, postcode, phone, mainContactfName, mainContactlName, mainContactEmail, xeroContactID, email  FROM suppliers WHERE record_num = ' . $supplier_id;
            if($is_parent){
                $supplier_contact = 'SELECT publicPhone, publicAddress, address, city, state, postcode, "" AS phone, "" AS mainContactfName, "" AS mainContactlName, "" AS mainContactEmail, xeroContactID, "" AS email FROM suppliers_parent WHERE record_num = ' . $supplier_id;
            }
            $contact_info = mysqli_fetch_array(db_query($supplier_contact));
            $address = array_filter([$contact_info['address'], $contact_info['address'], $contact_info['city'], $contact_info['state']]);
            $main_name = $contact_info['mainContactfName'] . ' ' . $contact_info['mainContactlName'];
            fputcsv($file, ['','',
                $supplier['name'], 
                $supplier['count'],
                number_format( ( $supplier['total'] / $supplier['count'] ) / 4, 2),
                implode(',', $address),
                $contact_info['email'] ?? '',
                $contact_info['phone'] ?? '',
                $contact_info['xeroContactID'] ?? '',
                $contact_info['publicAddress'], 
                $contact_info['publicPhone'], 
                $siteSSLUrl . '/installer-review/' . $supplier['url'] . '/',
                $main_name
            ]);
        }
    }
}

function lastFiveReviewers($location, $supplier, $is_parent = false){
    $query = "select radius, latitude, longitude from installation_locations where location = '$location'";
    list($radius, $lat, $lon ) = mysqli_fetch_array(db_query($query));

    $SQL =  ' select supplier_id, money_spent, rate_avg, final_thought, F.fName, F.lName, F.email, F.phone, iPostcode, use_review, title ';
    $SQL .= ' 	from feedback F ';
    $SQL .= ' 	inner join ( ';
    $SQL .= ' 		select postcode, max(lat) lat, max(lon) lon ';
    $SQL .= ' 		from postcode_definition ';
    $SQL .= ' 		where type = "Delivery Area" ';
    $SQL .= ' 		group by postcode ';
    $SQL .= ' 	) pd on F.iPostcode = pd.postcode ';
    $SQL .= ' 	INNER JOIN suppliers S ON F.supplier_id = S.record_num ';
    $SQL .= ' 	INNER JOIN suppliers_parent SP ON S.parent  = SP.record_num ';
    $SQL .= ' 	where ';
    $SQL .= ' 		public = 1 ';
    $SQL .= ' 		AND purchased="Yes" ';
    $SQL .= ' 		AND iState != "" ';
    $SQL .= ' 		AND ( 6371 * 2 * ASIN(SQRT( POWER(SIN((pd.lat - ' .  $lat . ') *  pi()/180 / 2), 2) + COS(pd.lat * pi()/180) * COS(' .  $lat . ' * pi()/180) * POWER(SIN((pd.lon - ' .  $lon . ' ) * pi()/180 / 2), 2) )) <= '.$radius.' )  ';
    if(! $is_parent){
        $SQL .= ' 		AND S.record_num = ' . $supplier;
    } else {
        $SQL .= ' 		AND S.parentUseReview = "Y" AND S.parent = ' . $supplier;
    }
    $SQL .= ' 	ORDER BY F.feedback_date DESC ';
    $SQL .= '   LIMIT 5 ';
    $SQL .= ' ; ';
    
    $result = mysqli_fetch_all(db_query($SQL), MYSQLI_ASSOC);
    return $result;
}