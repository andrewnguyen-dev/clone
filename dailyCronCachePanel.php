<?php
	// load global libraries
    include('global.php');
	set_time_limit(0);
	
	$timeframeBreakup = array();
    
    // Remove all items from the table
    $SQL = "DELETE FROM cache_panel_feedback_total";
    db_query($SQL);
    
    // Select all feedback that has been marked as public
    $SQL = "SELECT DISTINCT panel_brand FROM feedback F ";
    $SQL .= "INNER JOIN parser_solar PS ON F.panel_brand = PS.Manufacturer ";
	$SQL .= "WHERE F.public = 1 AND F.panel_brand != '' ";
	$SQL .= "ORDER BY F.one_year_submitted ASC";
	$panels = db_query($SQL);

	while ($panel = mysqli_fetch_array($panels, MYSQLI_ASSOC)) {
		resetTimeframeBreakup();
    	extract(htmlentitiesRecursive($panel), EXTR_PREFIX_ALL, 'p');
    	
		if(stristr($p_panel_brand, '(other)') === FALSE) {
			$p_panel_brand_URL = sanitizeURL($p_panel_brand);
			$p_panel_brand = mysqli_real_escape_string($_connection, $p_panel_brand);			

			getPanelFeedbackCount($p_panel_brand);
			getPanelFeedbackTotal($p_panel_brand);
			getPanelFeedbackTotalWeighted($p_panel_brand);
			
			foreach($timeframeBreakup as $idx => $timeframe) {
				$feedbackCount = $timeframe['count'];
				$feedbackTotal = $timeframe['total'];
				$feedbackTotalWeighted = $timeframe['total_weighted'];

				db_query("INSERT INTO cache_panel_feedback_total(manufacturerName, manufacturerURLName, count, total, totalWeighted, timeframe) VALUES ('{$p_panel_brand}', '{$p_panel_brand_URL}', {$feedbackCount}, {$feedbackTotal}, {$feedbackTotalWeighted}, '{$idx}')");
			}				
		}
	}
	
	function getPanelFeedbackCount($manufacturer) {
		global $timeframeBreakup;

		foreach($timeframeBreakup as $idx => $timeframe) {
			$from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');
			$SQL = "
				SELECT COUNT(*) 
				FROM feedback 
				WHERE public = 1 AND panel_brand = '{$manufacturer}' AND panel_rating > 0
					AND feedback_date >= '{$from}'
					AND verified = 'Yes'
					AND category_id = 4;
			";			
			$timeframeBreakup[$idx]['count'] = db_getVal($SQL);			
		}
	}
	
	function getPanelFeedbackTotal($manufacturer) {
		global $timeframeBreakup;

		foreach($timeframeBreakup as $idx => $timeframe) {
			$from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');
			$SQL = "
				SELECT IFNULL(SUM(panel_rating), 0)
				FROM feedback 
				WHERE public = 1 AND panel_brand = '{$manufacturer}' AND panel_rating > 0
					AND feedback_date >= '{$from}'
					AND verified = 'Yes'
					AND category_id = 4;
			";			
			$timeframeBreakup[$idx]['total'] = db_getVal($SQL);			
		}
	}
	
	function getPanelFeedbackTotalWeighted($manufacturer) {
		global $timeframeBreakup;

		foreach($timeframeBreakup as $idx => $timeframe) {
			$total = 0;
			$from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');

			$SQL = "
				SELECT panel_rating
				FROM feedback 
				WHERE public = 1 AND panel_brand = '{$manufacturer}' AND panel_rating > 0
					AND feedback_date >= '{$from}'
					AND verified = 'Yes'
					AND category_id = 4;
			";
			$ratings = db_query($SQL);

			while ($rating = mysqli_fetch_array($ratings, MYSQLI_ASSOC)) {
				extract(htmlentitiesRecursive($rating), EXTR_PREFIX_ALL, 'r');			
				$total += rawToWeightedRating($r_panel_rating);
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
