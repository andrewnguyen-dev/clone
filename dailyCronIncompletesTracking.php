<?php
require_once 'googleAPICommonFunctions.php';
include('global.php');
global $incompletesTrackingSpreadsheetId;
$spreadsheetId = $incompletesTrackingSpreadsheetId;

$startDate = new DateTime();
$startDate->modify('-7 days');
$startDateYmd = $startDate->format('Y-m-d');

$values = [];
$query = "SELECT DATE_FORMAT(created, '%Y-%m-%d') AS day, COUNT(*) AS received, SUM(CASE WHEN status!='incomplete' THEN 1 ELSE 0 END) AS cleared FROM leads WHERE (status='incomplete' OR notes LIKE '%originally submitted as incomplete%') AND created >= '{$startDateYmd} 00:00:00' GROUP BY day";
$qResult = db_query($query);
while ($row = mysqli_fetch_row($qResult)) {
	list($day, $received, $cleared) = $row;
	$values[$day] = ['received'=>$received, 'cleared'=>$cleared];
}

$client = getClient();
$service = new Google\Service\Sheets($client);
$currentValuesCache = [];
foreach($values as $dayYmd=>$v) {
	$day = new DateTime($dayYmd);
	updateSheetRow($service, $spreadsheetId, $v, $day);
}

function addNewSheet($service, $spreadsheetId, $newSheetTitle) {
	$request = new Google\Service\Sheets\Request([
		'addSheet' => [
			'properties' => [
				'title' => $newSheetTitle,
				'index' => 0
			]
		]
	]);
	$requests = new Google\Service\Sheets\BatchUpdateSpreadsheetRequest(['requests'=>[$request]]);
	$response = $service->spreadsheets->batchUpdate($spreadsheetId, $requests);
	$newSheetId = $response->replies[0]->addSheet->properties->sheetId;
	formatSheet($service, $spreadsheetId, $newSheetId);
	// Initialize the new sheet by writing the column headers (A1:D1) and the last row (totals)
	$range = "'{$newSheetTitle}'!A1:D33";
	$postBody = (new Google\Service\Sheets\ValueRange());
	$postBody->setValues([
		['Date', "Incompletes\nReceived", "Incompletes\nCleared", "Incompletes\n% Cleared"],
		[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],[],
		['Total', "=SUM(B2:B32)", "=SUM(C2:C32)", '=CONCAT(ROUND((C33/B33)*100,0), "%")']
	]);
	$service->spreadsheets_values->update($spreadsheetId, $range, $postBody, ['valueInputOption'=>'USER_ENTERED']);
}


/*
*	Applies the default formatting to a specific sheet (Calibri 11, cell alignments etc)
*/
function formatSheet($service, $spreadsheetId, $sheetId){
	$requests = [];
	$requests[] = new Google\Service\Sheets\Request([	// Calibri 11
		'repeatCell' => [
			'range' => [
				'sheetId'=>$sheetId
			],
			'cell' => [
				'userEnteredFormat' => [
					'textFormat'=>[
						'fontFamily'=>'Calibri', 
						'fontSize'=>11
					]
				]
			],
			"fields" => "userEnteredFormat(textFormat)"
		]
	]);
	$requests[] = new Google\Service\Sheets\Request([	// Center-align headers
		'repeatCell' => [
			'range' => [
				'sheetId'=>$sheetId
			],
			'cell' => [
				'userEnteredFormat' => [
					'horizontalAlignment'=>'CENTER'
				]
			],
			"fields" => "userEnteredFormat(horizontalAlignment)"
		]
	]);
	$requests[] = new Google\Service\Sheets\Request([	// Bold and italic the "Total" cell only (A33)
		'repeatCell' => [
			'range' => [
				'sheetId'=>$sheetId,
				'startColumnIndex'=>0,
				'endColumnIndex'=>1,
				'startRowIndex'=>32,
				'endRowIndex'=>33
			],
			'cell' => [
				'userEnteredFormat' => [
					'textFormat'=>[
						'bold'=>true,
						'italic'=>true
					]
				]
			],
			"fields" => "userEnteredFormat(textFormat)"
		]
	]);
	$formatRequests = new Google\Service\Sheets\BatchUpdateSpreadsheetRequest(['requests'=>$requests]);
	$service->spreadsheets->batchUpdate($spreadsheetId, $formatRequests);
}


function updateSheetRow($service, $spreadsheetId, $rowValues, $day) {
	try {
		$sheetTitle = $day->format('Y-m');
		$rowNumber = intval($day->format('d'))+1;
		$range = "'{$sheetTitle}'!A{$rowNumber}:D{$rowNumber}";
		$postBody = (new Google\Service\Sheets\ValueRange());
		// If the spreadsheet already has an "incompletes received" number greater than one retrieved from DB, keep the current number
		$currentReceivedValue = getValueFromCache($sheetTitle, $rowNumber);
		if($currentReceivedValue!==false && $currentReceivedValue > $rowValues['received']) 
			$rowValues['received'] = $currentReceivedValue;
		$percentage = '=CONCAT(ROUND((C'.$rowNumber.'/B'.$rowNumber.')*100,0), "%")';
		$postBody->setValues([
			[$day->format('d/m/Y'), $rowValues['received'], $rowValues['cleared'], $percentage]
		]);
		$service->spreadsheets_values->update($spreadsheetId, $range, $postBody, ['valueInputOption'=>'USER_ENTERED']);
	} catch(Exception $e) {
		$errorMessage = json_decode($e->getMessage())->error->message;
		if(strpos($errorMessage, "Unable to parse range")!==false){	 	// that month's sheet doesn't exist yet
			addNewSheet($service, $spreadsheetId, $sheetTitle);			// so first create it and then
			updateSheetRow($service, $spreadsheetId, $rowValues, $day);	// run this same function again
		}
		else {
			SendMail('it@solarquotes.com.au', 'John', 'Exception caught on dailyCronIncompletesTracking.php', $e->getMessage());
			die();
		}
	}
}

/*
*	Because there's a reading quota, we need to read entire sheets once, then retrieve the values from the cache
*/
function getValueFromCache($sheetTitle, $rowNumber){
	global $service, $spreadsheetId, $currentValuesCache;
	if(array_key_exists($sheetTitle, $currentValuesCache)) {	// cached sheet
		if($currentValuesCache[$sheetTitle]===false)	// This is a new sheet, there was no values stored before running this script
			return false;
		if(!array_key_exists(($rowNumber-1), $currentValuesCache[$sheetTitle]) || count($currentValuesCache[$sheetTitle][$rowNumber-1])<1)
			$currentValuesCache[$sheetTitle][$rowNumber-1][0] = false;
		return $currentValuesCache[$sheetTitle][$rowNumber-1][0];
	}
	else {	// Not cached, retrieve from Google Sheets
		$range = "'{$sheetTitle}'!B1:B32";
		try {
			$currentValuesCache[$sheetTitle] = $service->spreadsheets_values->get($spreadsheetId, $range)->values;
			return getValueFromCache($sheetTitle, $rowNumber);
		} catch(Exception $e) {	// Sheet not yet created, no need to cache anything (no risk of overwriting values)
			$currentValuesCache[$sheetTitle] = false;
			return false;
		}
	}
}