<?php
	// load global libraries
	require_once('global.php');

	global $nowSql, $phpdir;

	$filePath = "$phpdir/temp";
	$fileName = 'anythingElse_dump.csv';
	// Empty existing files
	file_put_contents($filePath.'/'.$fileName, '');
	file_put_contents($filePath.'/temp_'.$fileName, '');
	$excludes = [
		'lead wants to pay cash',
		'lead wants to pay through a monthly instalment plan ($0 down)',
		'lead wants options for both cash and monthly instalments on the quote',
		'has verified this phone number',
		'this is a choice lead',
		'customer wants a battery upgrade',
		'amount of storage required:',
		'existing inverter type:',
		'required for:',
		'this customer has indicated that they do not want the vic rebate',
		'battery ready system required',
		'would like to receive the vic rebate',
		'the customer has explicitly requested consumption monitoring',
		'lead wants finance options',
		'please discuss with lead specific home battery',
		'please discuss with lead size of solar required',
		'lead wants cash and finance options',
		'please discuss smart solar charging options with lead',
		'exclusive lead from your review page'
	];
	$columns = [
		'anythingElse',
		'created',
		'type',
		'status',
		'duration',
		'requestedQuotes',
		'Timeframe for purchase:',
		'Asked for home visit?',
		'Quarterly Bill:',
		'Price Type:',
		'Type of Roof:',
		'How many storeys?',
		'monitoring',
		'paying',
		'Amount of storage required:',
		'Existing inverter type:',
		'Required for:',
		'System Size:',
		'Car Make/Model:',
		'Existing solar size:',
		'Have battery?',
		'EV Installation Type:',
		'Distance between charger and switchboard:',
		'Supplier 1',
		'Supplier 1 reject reason',
		'Supplier 2',
		'Supplier 2 reject reason',
		'Supplier 3',
		'Supplier 3 reject reason'
	];

    for ($i = 365; $i >= 0; $i--) {
        $startDate = date('Y-m-d 00:00:00', strtotime("-$i days"));
        $endDate = date('Y-m-d 23:59:59', strtotime("-$i days"));

		$sql = 'SELECT record_num, created, status, requestedQuotes, quoteDetails, rebateDetails, siteDetails, systemDetails, extraDetails, leadType, duration FROM leads WHERE created >= "'.$startDate.'" AND created <= "'.$endDate.'" ORDER BY record_num ASC';

		$leads = db_query($sql);
		while ($l = mysqli_fetch_array($leads, MYSQLI_ASSOC)){
			$siteDetails = unserialize(base64_decode($l['siteDetails']));
			$anyThingElse = $siteDetails['Anything Else:'] ?? '';

			if (empty($anyThingElse)) continue;
		
			$details = array_filter(array_map('strtolower', array_map('trim', explode("\n", $anyThingElse))));
			foreach ($details as $dindex => $d) {
				// Questions added to Anything else
				if (strpos($d, strtolower('The customer has explicitly requested consumption monitoring')) !== false){
					$results[$l['record_num']]['monitoring'] = 'Consumption monitoring';
				} else {
					if(!isset($results[$l['record_num']]['monitoring']))
						$results[$l['record_num']]['monitoring'] = 'Performance monitoring';
				}

				if (strpos($d, strtolower('lead wants to pay cash')) !== false){
					$results[$l['record_num']]['paying'] = 'Cash';
				} elseif (strpos($d, strtolower('lead wants finance options')) !== false) {
					$results[$l['record_num']]['paying'] = 'Finance';
				} elseif (strpos($d, strtolower('lead wants cash and finance options')) !== false || 
						strpos($d, strtolower('lead wants to pay through a monthly instalment plan ($0 down)')) !== false || 
						strpos($d, strtolower('lead wants options for both cash and monthly instalments on the quote')) !== false) {
					$results[$l['record_num']]['paying'] = 'Cash and finance';
				} else {
					if(!isset($results[$l['record_num']]['paying']))
						$results[$l['record_num']]['paying'] = '';
				}

				if (strpos($d, strtolower('Amount of storage required:')) !== false){
					$results[$l['record_num']]['Amount of storage required:'] = str_replace(strtolower("Amount of storage required: "), "", $d);;
				} else {
					if(!isset($results[$l['record_num']]['Amount of storage required:']))
						$results[$l['record_num']]['Amount of storage required:'] = '';
				}

				if (strpos($d, strtolower('Existing inverter type:')) !== false){
					$results[$l['record_num']]['Existing inverter type:'] = str_replace(strtolower("Existing inverter type: "), "", $d);;
				} else {
					if(!isset($results[$l['record_num']]['Existing inverter type:']))
						$results[$l['record_num']]['Existing inverter type:'] = '';
				}

				if (strpos($d, strtolower('Required for:')) !== false){
					$results[$l['record_num']]['Required for:'] = str_replace(strtolower("Required for: "), "", $d);;
				} else {
					if(!isset($results[$l['record_num']]['Required for:']))
						$results[$l['record_num']]['Required for:'] = '';
				}

				// Strip out everything that wasn't added by the user
				foreach ($excludes as $ex) {
					if (stripos($d, strtolower($ex)) !== false) {
						unset($details[$dindex]);
					}
				}
			}

			if (empty($details))
				continue;

			$results[$l['record_num']]['anythingElse'] = implode(". ", $details);
			$results[$l['record_num']]['anythingElse'] = str_replace('"', "'", $results[$l['record_num']]['anythingElse']);
			$results[$l['record_num']]['created'] = $l['created'];
			$results[$l['record_num']]['status'] = $l['status'];
			$results[$l['record_num']]['duration'] = $l['duration'];
			$results[$l['record_num']]['requestedQuotes'] = $l['requestedQuotes'];

			$quoteDetails = unserialize(base64_decode($l['quoteDetails']));
			$rebateDetails = unserialize(base64_decode($l['rebateDetails']));
			$systemDetails = unserialize(base64_decode($l['systemDetails']));
			$extraDetails = unserialize(base64_decode($l['extraDetails']));

			if($l['leadType'] == 'Commercial'){
				$results[$l['record_num']]['type'] = 'Commercial';
			} else if ($l['leadType'] == 'Repair Residential') {
				$results[$l['record_num']]['type'] = 'Repair Residential';
			} else if( stripos($systemDetails['Features:'], 'Upgrading an') !== false || stripos($systemDetails['Features:'], 'Increase size') !== false){
				$results[$l['record_num']]['type'] = 'Upgrades';
			} else if(stripos($systemDetails['Features:'], 'Off Grid') !== false){
				$results[$l['record_num']]['type'] = 'Off Grid Solar';
			} else if(stripos($systemDetails['Features:'], 'Hybrid') !== false ){
				$results[$l['record_num']]['type'] = 'Hybrid';
			} else if(isset($siteDetails['Car Make/Model:']) && !empty($siteDetails['Car Make/Model:'])){
				if (stripos($systemDetails['Features:'], "battery") !== false)
					$results[$l['record_num']]['type'] = "EV charger + solar and / or battery";
				elseif (stripos($systemDetails['Features:'], "solar") !== false)
					$results[$l['record_num']]['type'] = "EV charger + solar only";
				else
					$results[$l['record_num']]['type'] = "EV Chargers";
			} else if(stripos($systemDetails['Features:'], 'Adding Batteries') !== false ){
				$results[$l['record_num']]['type'] = 'Add Batteries';
			} else if(stripos($systemDetails['Features:'], 'Battery Ready') !== false ){
				$results[$l['record_num']]['type'] = 'Battery Ready';
			} else if(stripos($systemDetails['Features:'], 'Microinverter') !== false || stripos($systemDetails['Features:'], 'Micro Inverter') !== false){
				$results[$l['record_num']]['type'] = 'Microinverter';
			} else {
				$results[$l['record_num']]['type'] = 'On Grid Solar';
			}

			$arrayFieldsToSkip = ['Available for a conversation:','Do you own the roofspace?','At least 10 square metres North-facing?','Exact direction:','Supplier preference size:','Other:'];

			foreach($quoteDetails as $key => $value){
				if(in_array($key, $arrayFieldsToSkip))
					continue;
				if (!in_array($key, $columns)) {
					$columns[] = $key;
				}
				$results[$l['record_num']][$key] = str_replace('"', "'", $value);
			}
			foreach($rebateDetails as $key => $value){
				if(in_array($key, $arrayFieldsToSkip))
					continue;
				if (!in_array($key, $columns)) {
					$columns[] = $key;
				}
				$results[$l['record_num']][$key] = str_replace('"', "'", $value);
			}
			foreach($siteDetails as $key => $value){
				if($key == 'Features:' || $key == 'Anything Else:' || in_array($key, $arrayFieldsToSkip))
					continue;
				if (!in_array($key, $columns)) {
					$columns[] = $key;
				}
				$results[$l['record_num']][$key] = str_replace('"', "'", $value);
			}
			foreach($systemDetails as $key => $value){
				if($key == 'Features:' || in_array($key, $arrayFieldsToSkip))
					continue;
				if (!in_array($key, $columns)) {
					$columns[] = $key;
				}
				$results[$l['record_num']][$key] = str_replace('"', "'", $value);
			}
			foreach($extraDetails as $key => $value){
				if($key == 'Anything Else Original:' || in_array($key, $arrayFieldsToSkip))
					continue;
				if (!in_array($key, $columns)) {
					$columns[] = $key;
				}
				$results[$l['record_num']][$key] = str_replace('"', "'", $value);
			}

			$results[$l['record_num']]['suppliers'] = [];
			$sql = "SELECT s.company, ls.rejectReason
				FROM lead_suppliers ls
				JOIN suppliers s ON ls.supplier = s.record_num
				JOIN leads l ON ls.lead_id = l.record_num
				WHERE l.record_num = {$l['record_num']}";
			$suppliers = db_query($sql);
			$n = 0;
			while ($s = mysqli_fetch_array($suppliers, MYSQLI_ASSOC)){
				$n++;
				$results[$l['record_num']]['Supplier '.$n] = str_replace('"', "'", $s['company']);
				$results[$l['record_num']]['Supplier '.$n.' reject reason'] = str_replace('"', "'", $s['rejectReason']);
			}

			$lastCol = count($columns) - 1;
			foreach($columns as $col){
				if(isset($results[$l['record_num']][$col]))
					file_put_contents($filePath.'/temp_'.$fileName, '"'.$results[$l['record_num']][$col].'"', FILE_APPEND);
				if($col != $columns[$lastCol]){
					file_put_contents($filePath.'/temp_'.$fileName, ',', FILE_APPEND);
				} else {
					file_put_contents($filePath.'/temp_'.$fileName, "\n", FILE_APPEND);
				}
			}

			$results = [];
			$siteDetails = [];
			$quoteDetails = [];
			$rebateDetails = [];
			$systemDetails = [];
			$extraDetails = [];
		}
		sleep(1);
	}

	$lastCol = count($columns) - 1;
	foreach($columns as $col){
		file_put_contents($filePath.'/'.$fileName, '"'.$col.'"', FILE_APPEND);
		if($col != $columns[$lastCol]){
			file_put_contents($filePath.'/'.$fileName, ',', FILE_APPEND);
		} else {
			file_put_contents($filePath.'/'.$fileName, "\n", FILE_APPEND);
		}
	}
	$temp = fopen($filePath.'/temp_'.$fileName, 'r');

	$new = fopen($filePath.'/'.$fileName, 'w');
	$columnsHeader = implode(',', $columns) . "\n";
	fwrite($new, $columnsHeader);
	while (!feof($temp)) {
		$line = fgets($temp);
		fwrite($new, $line);
	}
	fclose($temp);
	fclose($new);
	unlink($filePath.'/temp_'.$fileName);

	echo "\n";

?>