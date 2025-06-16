<?php
	// load global libraries
	include('global.php');
	set_time_limit(0);

	db_query("DELETE FROM cache_city_pages WHERE status='old'");

	$SQL = "SELECT record_num, postcode, state, location, latitude, longitude, radius FROM installation_locations WHERE location != 'national'";
	$result = db_query($SQL);

	$suppliersSQL  = "SELECT S.* FROM suppliers S";
	$suppliersSQL .= " WHERE S.status = 'active' OR (S.extraLeads='Y' AND S.status='paused') GROUP BY S.record_num";
	$suppliersResult = db_query($suppliersSQL);
	while ($location = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
		mysqli_data_seek($suppliersResult, 0);
		while ($supplier = mysqli_fetch_array($suppliersResult, MYSQLI_ASSOC)) {
			if(inSupplierLocation($supplier, $location)) {
				$insertSQL = "INSERT INTO cache_city_pages (installation_location_id, supplier_id, status) VALUES ('{$location['record_num']}', '{$supplier['record_num']}', 'pending')";
				db_query($insertSQL);
			}
		}
	}

	updateCacheTableRecords('cache_city_pages');

	function inSupplierLocation($supplier, $location) {
		// first check postcode
		$SQL = "SELECT P.record_num FROM postcode P ";
		$SQL .= "INNER JOIN suppliers_postcode SP ON P.record_num = SP.postcode_id ";
		$SQL .= "WHERE P.postcode = '{$location['postcode']}' ";
		$SQL .= "AND SP.supplier_id = '{$supplier['record_num']}';";

		$Postcode = db_query($SQL);

		if (mysqli_num_rows($Postcode) > 0) 
			return true;

		// check state
		if(db_getVal("SELECT record_num FROM supplier_areas WHERE supplier='{$supplier['record_num']}' AND type='state' AND details='{$location['state']}' AND status = 'active'")) 
			return true;


		$supplierAreas = db_query("SELECT type, details FROM supplier_areas WHERE type IN ('circle', 'polygon', 'drivingdistance') AND supplier = '{$supplier['record_num']}' AND status='active'");

		$spreadCircle = [];
		$spreadCircle['center']['lat'] = floatval($location['latitude']);
		$spreadCircle['center']['lon'] = floatval($location['longitude']);
		$spreadCircle['radius'] = floatval($location['radius']);

		while ($area = mysqli_fetch_array($supplierAreas, MYSQLI_ASSOC)) {
			if($area['type'] == 'polygon' || $area['type'] == 'drivingdistance'){  // Create supplier region polygon array
				$supPoints = explode('|', $area['details']);
				if($area['type'] == 'drivingdistance') {
					$supPoints = array_splice($supPoints, 3);
					$area['details'] = implode("|", $supPoints);
					$area['type'] = 'polygon';
				}
				$supNumPoints = (count($supPoints) / 2);
				$supPoly = [];
				$supSquare = [['lat' => INF, 'lon' => INF], ['lat' => -INF, 'lon' => -INF]];
				for ($i=0; $i<$supNumPoints; $i++) {
					$supPoly[$i]['lat'] = $supPoints[$i * 2];
					$supPoly[$i]['lon'] = $supPoints[$i * 2 + 1];
					$supSquare[0]['lat'] = min($supSquare[0]['lat'], $supPoly[$i]['lat']);
					$supSquare[0]['lon'] = min($supSquare[0]['lon'], $supPoly[$i]['lon']);
					$supSquare[1]['lat'] = max($supSquare[1]['lat'], $supPoly[$i]['lat']);
					$supSquare[1]['lon'] = max($supSquare[1]['lon'], $supPoly[$i]['lon']);
				}
			}
			if($area['type'] == 'circle'){  // Create supplier region circle array
				$supPoints = explode('|', $area['details']);
				$supCircle = [];
				$supCircle['center']['lat'] = $supPoints[0];
				$supCircle['center']['lon'] = $supPoints[1];
				$supCircle['radius'] = $supPoints[2];
			}

			switch($area['type']){  // Check supplier region type
				case 'circle':
					$match = circleCircle($supCircle['center']['lon'], $supCircle['center']['lat'], $supCircle['radius'], $spreadCircle['center']['lon'], $spreadCircle['center']['lat'], $spreadCircle['radius']);
					if ($match)
						return true;
				break;
				case 'polygon':
					$match = polyCircle($supPoly, $spreadCircle['center']['lon'], $spreadCircle['center']['lat'], $spreadCircle['radius']);
					if ($match)
						return true;
				break;
			}
		}

		return false;
	}

?>
