<?php
include('global.php');

// Set if this is first time we are running this script
$firsttime = false;

// Get Google Sheets Service
require_once 'googleAPICommonFunctions.php';
$client = getClient();
$service = new Google_Service_Sheets($client);

// Spreadsheet details
global $googleDecadeReportSpreadsheetId;
$sheetName = 'Sheet2';

if ($firsttime) {
    onetimeSheetSetup('July', '2013', 'July', '2023');
} else {
    deleteColumnFromSheet(2);
    $thismonth = getMonthColumn(date('F Y'));
    $thismonth = array_merge([date('F Y'), ''], $thismonth);
    writeArrayToSheet([[], $thismonth], 'DR1', 'COLUMNS');
}


function getMonthsArray($from_month, $from_year, $to_month, $to_year) {
    $from_date = strtotime("$from_month $from_year");
    $to_date = strtotime("$to_month $to_year");
    $months = [];  
    while ($from_date <= $to_date) {
        $formatted_month = date('F Y', $from_date);
        $months[] = $formatted_month;
        $from_date = strtotime('+1 month', $from_date);
    }
    return $months;
}

function onetimeSheetSetup($from_month, $from_year, $to_month, $to_year) {
    $months = getMonthsArray($from_month, $from_year, $to_month, $to_year);
    writeArrayToSheet([$months], 'C1');
    $monthsdata = [];
    foreach($months as $m) {
        $monthsdata[] = getMonthColumn($m);
    }
    writeArrayToSheet($monthsdata, 'C3', 'COLUMNS');
}

function writeArrayToSheet($data = [], $startCell = 'A1', $dimension = 'ROWS') {
    global $service, $googleDecadeReportSpreadsheetId, $sheetName;
    $range = $sheetName . '!' . $startCell;
    $values = $data;
    $valueRange = new Google_Service_Sheets_ValueRange([
        'majorDimension' => $dimension,
        'values' => $values,
    ]);
    try {
        $service->spreadsheets_values->update($googleDecadeReportSpreadsheetId, $range, $valueRange, ['valueInputOption' => 'RAW']);
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function deleteColumnFromSheet($columnIndex) {
    global $service, $googleDecadeReportSpreadsheetId, $sheetName;
    try {
        // Get the sheet data
        $response = $service->spreadsheets->get($googleDecadeReportSpreadsheetId);
        $sheets = $response->getSheets();
        
        $sheetId = null;
        
        // Find the sheet id of the specified sheet
        foreach ($sheets as $sheet) {
            if ($sheet->properties->title == $sheetName) {
                $sheetId = $sheet->properties->sheetId;
                break;
            }
        }
        
        if ($sheetId === null) {
            return "Sheet not found.";
        }
        
        // Delete the column
        $requests = [
            new Google_Service_Sheets_Request([
                'deleteDimension' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => $columnIndex,
                        'endIndex' => $columnIndex + 1,
                    ],
                ],
            ]),
        ];
        
        // Execute the batch update request
        $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => $requests,
        ]);
        
        $service->spreadsheets->batchUpdate($googleDecadeReportSpreadsheetId, $batchUpdateRequest);

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function str_has($str, $s) {
    if (!is_array($s)) { $s = [$s]; }
    foreach($s as $search) {
        if (stripos($str, $search) !== false) return true;
    }
    return false;
}

function getMonthData($monthyear) {
    list($month, $year) = explode(' ', $monthyear);
    $monthcondition = "(YEAR(submitted) = '{$year}' AND MONTHNAME(submitted) = '{$month}')";
    $condition = "leadType = 'Residential' AND status NOT IN ('duplicate', 'incomplete') AND {$monthcondition}";

    $types = [
        'grid-connect' => 0,
        'battery-ready' => 0,
        'hybrid' => 0,
        'add-solar' => 0,
        'add-batteries' => 0,
        'off-grid' => 0,
        'ev-chargers' => 0,
    ];
    $pricetypes = [
        'quality' => 0,
        'mix' => 0,
        'budget' => 0,
    ];
    $paytypes = [
        'cash' => 0,
        'loan' => 0,
        'both' => 0,
    ];
    $visittypes = [
        'yes' => 0,
        'zoom' => 0,
        'no' => 0,
    ];
    $systemsizes = [
        '3-5kW' => 0,
        '5-10kW' => 0,
        '10-15kW' => 0,
        '15-20kW' => 0,
        '20+' => 0,
        'fill-roof' => 0,
    ];
    $consumption = 0;
    $ptdone = [];
    $totalleads = 0;
    $totalquotes = 0;
    $leads = db_query("SELECT * FROM leads WHERE {$condition}");
    while($l = mysqli_fetch_assoc($leads)) {

        // Increment total leads and requested quotes
        $totalleads++;
        $totalquotes += intval($l['requestedQuotes']);

        // Get lead details
        $system = unserialize(base64_decode($l['systemDetails']));
        $quote = unserialize(base64_decode($l['quoteDetails']));
        $site = unserialize(base64_decode($l['siteDetails']));

        // Get type of the lead
        $features = $system['Features:'] ?? '';
        if (str_has($features, ['Upgrading an', 'Increase Size'])) {
            $types['add-solar']++;
        } else if (str_has($features, 'Off Grid')) {
            $types['off-grid']++;
        } else if (str_has($features, 'Hybrid')) {
            $types['hybrid']++;
        } else if (str_has($features, 'Adding Batteries')) {
            $types['add-batteries']++;
        } else if (str_has($features, 'Battery')) {
            $types['battery-ready']++;
        } else if (isset($site['Car Make/Model:']) && !empty($site['Car Make/Model:'])) {
            $types['ev-chargers']++;
        } else {
            $types['grid-connect']++;
        }

        // Get price type of the lead
        $priceType = $quote['Price Type:'] ?? '';
        if (str_has($priceType, 'mix of')) {
            $pricetypes['mix']++;
        } else if (str_has($priceType, 'top quality')) {
            $pricetypes['quality']++;
        } else if (str_has($priceType, 'good budget')) {
            $pricetypes['budget']++;
        }

        // Get payment type of the lead
        $anythingelse = $site['Anything Else:'] ?? '';
        if (str_has($anythingelse, 'wants to pay cash')) {
            $paytypes['cash']++;
        } else if (str_has($anythingelse, ['wants to pay through a monthly instalment plan', 'wants finance options on the quote'])) {
            $paytypes['loan']++;
        } else if (str_has($anythingelse, 'wants options for both cash and monthly instalments')) {
            $paytypes['both']++;
        }

        // Get home visit choice of the lead
        $visit = strtolower($quote['Asked for home visit?'] ?? '');
        if ($visit == 'yes') {
            $visittypes['yes']++;
        } else if ($visit == 'zoom call') {
            $visittypes['zoom']++;
        } else if ($visit == 'no') {
            $visittypes['no']++;
        }

        // Get consumption monitoring choice
        if (str_has($anythingelse, 'has explicitly requested consumption monitoring')) {
            $consumption++;
        }

        // Get system size
        $size = $system['System Size:'];
        if (str_has($size, 'not sure')) {
            // ignore for now
        } else if (str_has($size, 'fill my roof')) {
            $systemsizes['fill-roof']++;
        } else if (str_has($size, ['3 to 5']) || in_array(strtolower($size), ['3kw', '3.3kw', '4kw', '5kw'])) {
            $systemsizes['3-5kW']++;
        } else if (str_has($size, ['5 to 10', 'more than 6kw']) || in_array(strtolower($size), ['6kw', '6.6kw', '7kw', '8kw', '9kw', '10kw'])) {
            $systemsizes['5-10kW']++;
        } else if (str_has($size, ['10 to 15']) || in_array(strtolower($size), ['13kw', '15kw'])) {
            $systemsizes['10-15kW']++;
        } else if (str_has($size, ['15 to 20', '15+']) || in_array(strtolower($size), ['20kw'])) {
            $systemsizes['15-20kW']++;
        } else if (str_has($size, ['20+', '30kw'])) {
            $systemsizes['20+']++;
        }
        
    }

    $avgquotes = (empty($totalleads)) ? 0 : round($totalquotes / $totalleads, 2);

    // Get reviews data for the month
    $reviews = db_query("SELECT COUNT(*) as totalreviews, AVG(rate_avg) as avgrating FROM feedback
    WHERE public = 1 AND verified = 'Yes' AND category_id IN (4,5,6,7)
    AND rate_value >= 0 AND YEAR(feedback_date) = '{$year}' AND MONTHNAME(feedback_date) = '{$month}';");
    $r = mysqli_fetch_assoc($reviews);
    $totalreviews = $r['totalreviews'];
    $avgrating = round($r['avgrating'] ?? 0, 2);

    $result = compact('totalleads', 'types', 'avgquotes', 'pricetypes', 'paytypes', 'visittypes', 'consumption', 'systemsizes', 'totalreviews', 'avgrating');

    $percentages = ['types', 'pricetypes', 'paytypes', 'visittypes', 'consumption', 'systemsizes'];
    foreach($percentages as $pk) {
        $pv = $result[$pk];
        if (is_array($pv)) {
            foreach($pv as $cpk => $cpv) {
                $result[$pk][$cpk] = round( (($totalleads < 1) ? 0 : $cpv / $totalleads * 100), 2 ).'%';
            }
        } else {
            $result[$pk] = round( (($totalleads < 1) ? 0 : $pv / $totalleads * 100), 2 ).'%';
        }
    }

    return $result;
}

function getMonthColumn($monthyear) {
    $data = getMonthData($monthyear);
    return [
        $data['totalleads'],
        '',
        $data['types']['grid-connect'],
        $data['types']['battery-ready'],
        $data['types']['hybrid'],
        $data['types']['add-solar'],
        $data['types']['add-batteries'],
        $data['types']['off-grid'],
        $data['types']['ev-chargers'],
        '',
        $data['totalreviews'],
        $data['avgrating'],
        '',
        $data['paytypes']['cash'],
        $data['paytypes']['loan'],
        $data['paytypes']['both'],
        '',
        $data['pricetypes']['quality'],
        $data['pricetypes']['mix'],
        $data['pricetypes']['budget'],
        '',
        $data['systemsizes']['3-5kW'],
        $data['systemsizes']['5-10kW'],
        $data['systemsizes']['10-15kW'],
        $data['systemsizes']['15-20kW'],
        $data['systemsizes']['20+'],
        $data['systemsizes']['fill-roof'],
        '',
        $data['consumption'],
        '',
        $data['avgquotes'],
        '',
        $data['visittypes']['yes'],
        $data['visittypes']['zoom'],
        $data['visittypes']['no'],
    ];
}