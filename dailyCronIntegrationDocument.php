<?php
require('global.php');

global $googleIntegrationDocumentId;

require_once 'googleAPICommonFunctions.php';

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Docs($client);

/* Grab information from the suppliers table
	Only ReviewOnly = Y suppliers and no parents
*/

$query = "SELECT company, phone, email FROM suppliers WHERE reviewOnly = 'N' AND status != 'deleted' order by company desc;";
$qResult = db_query($query);

$requests = [];
$doc = $service->documents->get($googleIntegrationDocumentId);
$content = array_reverse($doc->getBody()->getContent());
$endIndex = json_encode($content[0]['endIndex'] - 1);

// Only delete content if there's any, deleting an empty throws a fatal google exception
if($endIndex > 1){
	$request = new \Google_Service_Docs_Request([
		'updateTextStyle' => [
			'textStyle' => [
				'bold' => false,
				'fontSize' => [
					'unit' => 'PT',
					'magnitude' => 11
				]
			],
			'range' => [
				'startIndex' => 1,
				'endIndex' => $endIndex
			],
			'fields' => '*'
		]
	]);
	$requests[] = $request;
	
	$request = new \Google_Service_Docs_Request([
		'deleteContentRange' => [
			'range' => [
				'startIndex' => 1,
				'endIndex' => $endIndex
			]
		]
	]);
	$requests[] = $request;
}

while ($row = mysqli_fetch_row($qResult)) {
	list($name, $phone, $email) = $row;
	$textLenghts = [];

	$emailText = "E: $email\n\n";
	$textLenghts['email'] = strlen($emailText);
	$request = new \Google_Service_Docs_Request([
		'insertText' => [
			'text' => $emailText,
			'location' => [
				'index' => 1
			]
		]
	]);
	$requests[] = $request;

	$phoneText = "P: $phone\n";
	$textLenghts['phone'] = strlen($phoneText);
	$request = new \Google_Service_Docs_Request([
		'insertText' => [
			'text' => $phoneText,
			'location' => [
				'index' => 1
			]
		]
	]);
	$requests[] = $request;

	$nameText = "$name\n";
	$textLenghts['name'] = strlen($nameText);
	$request = new \Google_Service_Docs_Request([
		'insertText' => [
			'text' => $nameText,
			'location' => [
				'index' => 1
			]
		]
	]);
	$requests[] = $request;

	// Email should be link
	$request = new \Google_Service_Docs_Request([
		'updateTextStyle' => [
			'fields' => 'link',
			'textStyle' => [
				'link' => [
					'url' => "mailto:$email"
				]
			],
			'range' => [
				'startIndex' => $textLenghts['name'] + $textLenghts['phone'] + 4,
				'endIndex' => $textLenghts['name'] + $textLenghts['phone'] + $textLenghts['email'],
			],
		]
	]);

	$requests[] = $request;
}

$request = new \Google_Service_Docs_Request([
	'insertText' => [
		'text' => sprintf("Last update on: %s\n\n", date('Y-m-d H:i:s')),
		'location' => [
			'index' => 1
		]
	]
]);
$requests[] = $request;

$request = new \Google_Service_Docs_Request([
	'insertText' => [
		'text' => "INSTALLERS CONTACT DETAILS\n",
		'location' => [
			'index' => 1
		]
	]
]);

$requests[] = $request;
$request = new \Google_Service_Docs_Request([
	'updateTextStyle' => [
		'textStyle' => [
			'bold' => true,
			'fontSize' => [
				'unit' => 'PT',
				'magnitude' => 19
			]
		],
		'range' => [
			'startIndex' => 1,
			'endIndex' => 27
		],
		'fields' => '*'
	]
]);

$requests[] = $request;

$batchUpdateRequest = new \Google_Service_Docs_BatchUpdateDocumentRequest(['requests' => $requests]);
$response = $service->documents->batchUpdate($googleIntegrationDocumentId, $batchUpdateRequest);

printf("The document title is: %s\n", $doc->getTitle());

