<?php
	require('solar_locations.php');
	require('global.php');
	require('forecast_includes/include_solar_rebate.php');
	require('forecast_includes/forecast_variables.php');
	require('forecast_includes/solar_calculations.php');

	$previous_hour = 0;
	foreach($WZ_Import_Locations as $city=>$location){
		$latitude = $location['latitude'];
		$longitude = $location['longitude'];
		$timezone = $states_timezone_names[$location['state']];
		$apiKey = $openWeatherAPIKey;
		$url = "https://api.openweathermap.org/data/3.0/onecall?lat=$latitude&lon=$longitude&APPID=$apiKey";
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);
		$data = json_decode($res, true);
		$hourly = $data['hourly'];
		foreach($hourly as $hour){
			$dt = $hour['dt'];
			$cloudCover = $hour['clouds'];
			$dt = new DateTime("@$dt");
			$dt->setTimezone(new DateTimeZone($timezone));

			insertOrUpdateCloudCover($city, $dt, $cloudCover);
		}
	}

	function insertOrUpdateCloudCover($city, $time, $cloudCover){
		$query = 'SELECT record_num FROM weatherzone_cloud_cover where location ="'.$city.'" and date = "' . $time->format('Y-m-d H:i') . '"';
		$id = db_getVal($query);

		//Update
		if($id != ''){
			db_query(sprintf('UPDATE weatherzone_cloud_cover set cloud_cover = %d where record_num = %d',
				$cloudCover,
				$id
			));
		} else { // Insert
			db_query(sprintf('
				INSERT INTO weatherzone_cloud_cover(date, location, cloud_cover)
				VALUES("%s","%s","%d")',
				$time->format('Y-m-d H:i'), $city, $cloudCover)
			);
		}
	}

	generateHourlyForecastCache();

	echo 'Done';

	function getDayValuesFromDB($city, $date){
		$query = 'SELECT * FROM weatherzone_cloud_cover where location ="'.$city.'" and DATE_FORMAT(date, \'%Y%m%d\')="'.$date.'"';
		$result = db_query($query);

		$return = array();

		while($row = mysqli_fetch_array($result) ){
			$return[date('Hi', strtotime($row['date']))] = array('cover'=>$row['cloud_cover'], 'id'=>$row['record_num']);
		}
		return $return;
	}

	function generateHourlyForecastCache(){
		global $nowSql, $WZ_Import_Locations, $states_timezone_names;

		$pre_selection_state = isset($_GET['state']) ? $_GET['state'] : 'SA';
		$pre_selection_city = 'Adelaide';

		$system_sizes = array('1,5','2', '3', '4', '5', '6', '7', '8', '9', '10');
		$forecastData = array();
		date_default_timezone_set('Australia/Adelaide');

		foreach($WZ_Import_Locations as $cityName=>$cityData){
			$state = $WZ_Import_Locations[$cityName]['state'];
			if( strtoupper($state) == strtoupper($pre_selection_state)){
				$pre_selection_city = $cityName;
			}
			$tz = new DateTimeZone($states_timezone_names[$state]);
			$dateToProcess = new DateTime('now', $tz);
			$today = clone $dateToProcess;
			$latitude = $WZ_Import_Locations[$cityName]['latitude'];
			$longitude = $WZ_Import_Locations[$cityName]['longitude'];

			$SQL = '  select date, cloud_cover ';
			$SQL .= ' from weatherzone_cloud_cover ';
			$SQL .= ' WHERE location = "'.$cityName.'" ';
			$SQL .= ' AND DATE_FORMAT(date, \'%Y%m%d\') = '.$today->format('Ymd').';';

			$result = db_query($SQL);
			$aux = array();
			while($row = mysqli_fetch_row($result)){
				$aux[date('Ymd', strtotime($row[0]))][date('G', strtotime($row[0]))] = $row[1];
			}
			$cloud_cover_data_today = isset($aux[$today->format('Ymd')]) ? $aux[$today->format('Ymd')] : array();

			$cc = current($cloud_cover_data_today);
			for($i=0;$i<24;$i++){
				if(isset($cloud_cover_data_today[$i])){
					$cc = $cloud_cover_data_today[$i];
				}else{
					$cloud_cover_data_today[$i] = $cc;
				}
			}

			$solar_information_today = SolarCalculations::getSolarInformation(
				$today->format('Y/m/d'),
				$latitude,
				$longitude,
				$states_timezone_names[$state],
				$cloud_cover_data_today);

			$production_by_system_size_today = SolarCalculations::getProductionBySolarInformation($solar_information_today, $system_sizes);

			$electricity_cost = appVariables::$stateVariables[$state]['electricityPricePerState'];
			$data_structure = array('today'=>array(), 'tomorrow'=>array());
			$totals_by_system_size = array(
				'today'=>array(),
			);
			foreach($system_sizes as $system){
				$daily_sum_today = array_sum($production_by_system_size_today[$system]);
				$totals_by_system_size['today'][$system] = array(
					'total_production'=>round($daily_sum_today),
					'total_savings_day'=>number_format($daily_sum_today*$electricity_cost, 2),
					'total_savings_year'=>number_format($daily_sum_today*$electricity_cost*365, 0),
					'hourly'=>$production_by_system_size_today[$system]
				);
			}

			$forecastData[$cityName] = $totals_by_system_size;
			$forecastData[$cityName]['tz'] = $dateToProcess->getOffset();
		}

		$insert_query = 'INSERT INTO cache_forecast_widget (cached_json, created_date) VALUES(\''.json_encode($forecastData).'\', '.$nowSql.');';
		db_query($insert_query);


		$delete_query = 'DELETE FROM cache_forecast_widget WHERE created_date < '.$nowSql.' - INTERVAL 30 MINUTE;';
		db_query($delete_query);
	}
?>