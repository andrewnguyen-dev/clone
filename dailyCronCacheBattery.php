<?php
	// load global libraries
    include('global.php');
    set_time_limit(0);
	
	$timeframeBreakup = array();
	
    // Remove all items from the table
    $SQL = "DELETE FROM cache_battery_feedback_total";
    db_query($SQL);
    
    // Select all feedback that has been marked as public
    $SQL = "SELECT DISTINCT BR.manufacturer FROM battery_reviews BR ";
    $SQL .= "INNER JOIN parser_solaraccreditation_battery PSB ON BR.manufacturer = PSB.Manufacturer ";
	$SQL .= "WHERE BR.public = 1 AND BR.manufacturer != ''";
	
	$batteries = db_query($SQL);
	
	while ($battery = mysqli_fetch_array($batteries, MYSQLI_ASSOC)) {
		resetTimeframeBreakup();
    	extract(htmlentitiesRecursive($battery), EXTR_PREFIX_ALL, 'b');
    	
		if(stristr($b_manufacturer, '(other)') === FALSE) {
			$b_battery_brand_URL = sanitizeURL($b_manufacturer);
			$b_manufacturer = mysqli_real_escape_string($_connection, $b_manufacturer);			

			getBatteryFeedbackCount($b_manufacturer);
			getBatteryFeedbackTotal($b_manufacturer);
			getBatteryFeedbackTotalWeighted($b_manufacturer);
			
			foreach($timeframeBreakup as $idx => $timeframe) {
				$feedbackCount = $timeframe['count'];
				$feedbackTotal = $timeframe['total'];
				$feedbackTotalWeighted = $timeframe['total_weighted'];

				db_query("INSERT INTO cache_battery_feedback_total(manufacturerName, manufacturerURLName, count, total, totalWeighted, timeframe) VALUES ('{$b_manufacturer}', '{$b_battery_brand_URL}', {$feedbackCount}, {$feedbackTotal}, {$feedbackTotalWeighted}, '{$idx}')");
			}
		}
	}
	
	function getBatteryFeedbackCount($manufacturer) {
		global $timeframeBreakup;

		foreach($timeframeBreakup as $idx => $timeframe) {
			$from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');
			$SQL = "
				SELECT COUNT(*)
				FROM battery_reviews BR
				LEFT JOIN feedback F ON F.record_num = BR.feedback_id
				WHERE 
					(BR.public = 1 OR F.public = 1)
					AND BR.manufacturer = '{$manufacturer}'
					AND BR.rate_avg >=1
					AND (BR.verified = 'Yes' OR F.verified = 'Yes')
					AND IF(
						BR.feedback_id is null, 
						BR.review_date, 
						IF(F.one_year_submitted is null, F.feedback_date, F.one_year_submitted)
					) >= '{$from}';
			";			
			$timeframeBreakup[$idx]['count'] = db_getVal($SQL);			
		}		
	}
	
	function getBatteryFeedbackTotal($manufacturer) {
		global $timeframeBreakup;

		foreach($timeframeBreakup as $idx => $timeframe) {
			$from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');
			$SQL = "
				SELECT IFNULL(SUM(BR.rate_avg), 0)
				FROM battery_reviews BR
				LEFT JOIN feedback F ON F.record_num = BR.feedback_id
				WHERE 
					(BR.public = 1 OR F.public = 1)
					AND BR.manufacturer = '{$manufacturer}'
					AND BR.rate_avg >=1
					AND (BR.verified = 'Yes' OR F.verified = 'Yes')
					AND IF(
						BR.feedback_id is null, 
						BR.review_date, 
						IF(F.one_year_submitted is null, F.feedback_date, F.one_year_submitted)
					) >= '{$from}';
			";
			$timeframeBreakup[$idx]['total'] = db_getVal($SQL);
		}
	}
	
	function getBatteryFeedbackTotalWeighted($manufacturer) {
		global $timeframeBreakup;
		
		foreach($timeframeBreakup as $idx => $timeframe) {
			$total = 0;

			$from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');
			$SQL = "
				SELECT BR.rate_avg
				FROM battery_reviews BR
				LEFT JOIN feedback F ON F.record_num = BR.feedback_id
				WHERE 
					(BR.public = 1 OR F.public = 1)
					AND BR.manufacturer = '{$manufacturer}'
					AND BR.rate_avg >=1
					AND (BR.verified = 'Yes' OR F.verified = 'Yes')
					AND IF(
						BR.feedback_id is null, 
						BR.review_date, 
						IF(F.one_year_submitted is null, F.feedback_date, F.one_year_submitted)
					) >= '{$from}';
			";
			$ratings = db_query($SQL);

			while ($rating = mysqli_fetch_array($ratings, MYSQLI_ASSOC)) {
				extract(htmlentitiesRecursive($rating), EXTR_PREFIX_ALL, 'r');			
				$total += rawToWeightedRating($r_rate_avg);
			}			
			$timeframeBreakup[$idx]['total_weighted'] = $total;
		}	
	}
	
	function rawToWeightedRating($raw) {
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

	function resetTimeframeBreakup() {
		global $timeframeBreakup;
		$timeframeBreakup = array(
			'6m' => array(
				'lowerlimit' => (new \DateTime())->modify('-6 months'),
				'total' => 0, 'total_weighted' => 0, 'count' => 0
			),
			'12m' => array(
				'lowerlimit' => (new \DateTime())->modify('-12 months'),
				'total' => 0, 'total_weighted' => 0, 'count' => 0
			),
			'all' => array(
				'lowerlimit' => (new \DateTime('19700101')),
				'total' => 0, 'total_weighted' => 0, 'count' => 0
			)
		);
	}
?>
