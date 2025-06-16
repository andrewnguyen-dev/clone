<?php

	require_once('global.php');

	foreach ($areasLatLon as $area => $area_info) {
		$centerLat = $area_info[2];
		$centerLng = $area_info[3];
		$region = $area_info[4];
		$region_state = $area_info[1];
		$radius = $area_info[5];

		$lon1 = $centerLng - $radius / abs(cos(deg2rad($centerLat))*111);
		$lon2 = $centerLng + $radius / abs(cos(deg2rad($centerLat))*111);
		$lat1 = $centerLat - ($radius/111);
		$lat2 = $centerLat + ($radius/111);

		$yesterday = strtotime("yesterday");
        $date_start = date("Y-m-d 00:00:00", $yesterday);
		$date_end = date("Y-m-d 23:59:59", $yesterday);
		$SQL = "SELECT
			6371 * 2 * ASIN(
				SQRT(
					POWER(
						SIN(({$centerLat} - latitude) * pi() / 180 / 2),2)
						+
						COS({$centerLat} * pi() / 180) * COS(latitude * pi() / 180)
						* POWER(SIN(({$centerLng} - longitude) * pi() / 180 / 2), 2)
				)
			) AS distance,
			record_num,
			updated,
			longitude,
			latitude";

		$SQL .= " FROM leads";
		$SQL .= " WHERE status = 'dispatched' AND updated >= '{$date_start}' AND updated <= '{$date_end}' AND longitude BETWEEN {$lon1} AND {$lon2} AND latitude BETWEEN {$lat1} AND {$lat2}";
		$SQL .= " HAVING distance < {$radius}";

		$leads_count = 0;
		$leads_count_where_in = '';
		$lead_record_num = [];
		$utilization_rate = 0;
		$lead_suppliers_count = 0;
		$lead_requested_count = 0;

		$result = db_query($SQL);

		if ($result) {
			foreach ($result as $key => $lead) {
				$lead_record_num[] = $lead['record_num'];
			}
		}

		if (!empty($lead_record_num)) {
			$leads_count = count($lead_record_num);
			$leads_count_where_in = implode(',', $lead_record_num);

			$SQL = "SELECT SUM(requestedQuotes) FROM leads WHERE record_num IN ({$leads_count_where_in})";
			$lead_requested_count = db_getVal($SQL);

			$SQL = "SELECT COUNT(*) AS suppliers_count FROM lead_suppliers WHERE lead_id IN ({$leads_count_where_in})";
			$lead_suppliers_count = db_getVal($SQL);

			$utilization_rate = round(($lead_suppliers_count / $lead_requested_count) * 3, 2, PHP_ROUND_HALF_EVEN);

			$SQL = "INSERT INTO cache_utilization(date, region, region_state, lead_count, utilization_rate, created) VALUES('{$date_end}', '{$region}', '{$region_state}', {$leads_count}, {$utilization_rate}, {$nowSql})";
		} else {
            $SQL = "INSERT INTO cache_utilization(date, region, region_state, lead_count, utilization_rate, created) VALUES('{$date_end}', '{$region}', '{$region_state}', 0, 0, {$nowSql})";
		}

		db_query($SQL);
	}
?>