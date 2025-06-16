<?php
	// load global libraries
    include('global.php');
    set_time_limit(0);
	
	$timeframeBreakup = array();

    // Remove all items from the table
    $SQL = "DELETE FROM cache_inverter_feedback_total";
    db_query($SQL);
    
    // Select all feedback that has been marked as public
    $SQL = "SELECT DISTINCT inverter_brand FROM feedback F ";
    $SQL .= "INNER JOIN parser_solaraccreditation_inverter PSI ON F.inverter_brand = PSI.Manufacturer ";
	$SQL .= "WHERE F.public = 1 AND F.inverter_brand != '' ";
	$SQL .= "ORDER BY F.one_year_submitted ASC";
	$inverters = db_query($SQL);
	
	while ($inverter = mysqli_fetch_array($inverters, MYSQLI_ASSOC)) {
		resetTimeframeBreakup();
    	extract(htmlentitiesRecursive($inverter), EXTR_PREFIX_ALL, 'i');
    	
		if(stristr($i_inverter_brand, '(other)') === FALSE) {			
			$i_inverter_brand_URL = sanitizeURL($i_inverter_brand);
			$i_inverter_brand = mysqli_real_escape_string($_connection, $i_inverter_brand);			
			
			getInverterFeedbackCount($i_inverter_brand);
			getInverterFeedbackTotal($i_inverter_brand);
			getInverterFeedbackTotalWeighted($i_inverter_brand);
			
			foreach($timeframeBreakup as $idx => $timeframe) {
				$feedbackCount = $timeframe['count'];
				$feedbackTotal = $timeframe['total'];
				$feedbackTotalWeighted = $timeframe['total_weighted'];

				db_query("INSERT INTO cache_inverter_feedback_total(manufacturerName, manufacturerURLName, count, total, totalWeighted, timeframe) VALUES ('{$i_inverter_brand}', '{$i_inverter_brand_URL}', {$feedbackCount}, {$feedbackTotal}, {$feedbackTotalWeighted}, '{$idx}')");
			}
		}
	}
	
	function getInverterFeedbackCount($manufacturer) {
		global $timeframeBreakup;

		foreach($timeframeBreakup as $idx => $timeframe) {
			$from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');
			$SQL = "
				SELECT COUNT(*) 
				FROM feedback 
				WHERE public = 1 AND inverter_brand = '{$manufacturer}' AND inverter_rating > 0
					AND feedback_date >= '{$from}'
					AND verified = 'Yes'
					AND category_id = 4;
			";			
			$timeframeBreakup[$idx]['count'] = db_getVal($SQL);			
		}
	}
	
	function getInverterFeedbackTotal($manufacturer) {
		global $timeframeBreakup;

		foreach($timeframeBreakup as $idx => $timeframe) {
			$from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');
			$SQL = "
				SELECT IFNULL(SUM(inverter_rating), 0)
				FROM feedback 
				WHERE public = 1 AND inverter_brand = '{$manufacturer}' AND inverter_rating > 0
					AND feedback_date >= '{$from}'
					AND verified = 'Yes'
					AND category_id = 4;
			";			
			$timeframeBreakup[$idx]['total'] = db_getVal($SQL);			
		}		
	}
	
	function getInverterFeedbackTotalWeighted($manufacturer) {
		global $timeframeBreakup;

		foreach($timeframeBreakup as $idx => $timeframe) {
			$total = 0;			
			$from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');

			$SQL = "
				SELECT inverter_rating
				FROM feedback 
				WHERE public = 1 AND inverter_brand = '{$manufacturer}' AND inverter_rating > 0
					AND feedback_date >= '{$from}'
					AND verified = 'Yes'
					AND category_id = 4;
			";

			$ratings = db_query($SQL);

			while ($rating = mysqli_fetch_array($ratings, MYSQLI_ASSOC)) {
				extract(htmlentitiesRecursive($rating), EXTR_PREFIX_ALL, 'r');				
				$total += rawToWeightedRating($r_inverter_rating);
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
