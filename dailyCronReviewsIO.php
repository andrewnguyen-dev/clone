<?php
	require_once('global.php');

	$reviewsjson = file_get_contents('https://api.reviews.io/merchant/latest?store=www.solarquotes.com.au');
	$reviews = json_decode($reviewsjson);
	if ( !isset($reviews->reviews) || (json_last_error() !== JSON_ERROR_NONE) ) {
        sleep(30);
        $reviewsjson = file_get_contents('https://api.reviews.io/merchant/latest?store=www.solarquotes.com.au');
        $reviews = json_decode($reviewsjson);
        if ( !isset($reviews->reviews) || (json_last_error() !== JSON_ERROR_NONE) ) {
            exit;
        }
    }
	$system_health_hours_range = 72;
	$system_health_count = 0;

	$unixtimegmt = strtotime(gmdate('Y-m-d H:i:s'));
	if (!empty($reviews) && isset($reviews->reviews) && is_array($reviews->reviews) && count($reviews->reviews) > 0) {
		foreach($reviews->reviews as $review) {
			if ($unixtimegmt - strtotime($review->date_created) < $system_health_hours_range*60*60) {
				$system_health_count++;
			}
		}
	}

	db_query("UPDATE system_health SET value={$system_health_count}, updated={$nowSql} WHERE record_num='1'");
?>