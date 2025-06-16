<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

    include('global.php');

    $start = time();

    db_query("DELETE FROM cache_percentiles WHERE status='old'");

	$timeframes = ['all','36months','24months','12months','6months'];
	$types = ['avg-score', 'most-good'];
	$states = ['', 'ACT','NSW','NT','QLD','SA','TAS','VIC','WA'];
	$entities = ['installer','panel','inverter'];
	$i=1;

	foreach($entities as $entity) {
		foreach($states as $state) {
			foreach($types as $type) {
				foreach($timeframes as $timeframe) {
					// when type is most-good, there's no timeframe filter
					if($type=='most-good' && $timeframe != 'all')
						continue;
					// when entity is != installer, there's no state filter
					if($entity != 'installer' && $state != '')
						continue;
					echo "Caching variation $i/66 (entity=$entity, state=$state, type=$type, timeframe=$timeframe)\n";
					save_pending_cache_percentile($timeframe, $type, $state, $entity);
					$i++;
					sleep(1);
				}
			}
		}
	}

	updateCacheTableRecords("cache_percentiles");
	die("\nFinished in ".(time() - $start)." seconds.\n");

	function save_pending_cache_percentile($timeframe, $type, $state, $entity) {
		$query = percentile_query($timeframe, $type, $state, $entity);
		$insert =  "INSERT INTO cache_percentiles ";
		$insert .= "(s_id, feedbackWeighted, average, count, entity, state, timeframe, type) ";
		$insert .= $query;
		db_query($insert);
	}

	function percentile_query($timeframe, $type, $state, $entity) {
		$time_range = '';
		switch($timeframe){
			case '36months':
				$time_range = "AND feedback_date > NOW() - INTERVAL 36 MONTH";
				break;
			case '24months':
				$time_range = "AND feedback_date > NOW() - INTERVAL 24 MONTH";
				break;
			case '12months':
				$time_range = "AND feedback_date > NOW() - INTERVAL 12 MONTH";
				break;
			case '6months':
				$time_range = "AND feedback_date > NOW() - INTERVAL 6 MONTH";
				break;
			default:
				$timeframe = 'all';
				break;
		}

		switch($entity) {
			case 'installer':
				$sql_rate = 'case when one_year_rate_value IS NULL then rate_avg else one_year_rate_avg end';
				$sql_id = 'case when parent = 1 OR parentUseReview = \'N\' then s.record_num else CONCAT(\'P_\', s.parent) end';
				break;
			case 'panel':
				$sql_rate = 'panel_rating';
				$sql_id = 'lower(panel_brand)';
				break;
			case 'inverter':
				$sql_rate = 'inverter_rating';
				$sql_id = 'lower(inverter_brand)';
				break;			
		}

		if($type == 'most-good'){
			$sql = ' select  ';
			$sql .= '   id as s_id, ';
			$sql .= '   sum(average) feedbackWeighted, ';
			$sql .= '   avg(average) fullAverage, ';
			$sql .= '   count(*) as count, ';
			$sql .= '   "'.$entity.'" as entity, ';
			$sql .= '   "'.$state.'" as state, ';
			$sql .= '   "'.$timeframe.'" as timeframe, ';
			$sql .= '   "'.$type.'" as type ';
			$sql .= ' from ( ';
			$sql .= ' 	select '.$sql_rate.' as average, ';
			$sql .= ' 		'.$sql_id.' as id ';
			$sql .= ' 	FROM feedback f ';
			$sql .= ' 	INNER JOIN suppliers s ON s.record_num = f.supplier_id ';
			$sql .= ' 	WHERE category_id IN (\'4\', \'5\') AND f.purchased = \'Yes\' ';
			$sql .= ' 		AND public = 1 ';
			if($state != '') {
				$sql .= ' AND f.iState = \''.$state.'\' ';
			}
			$sql .= ' ) t ';
			$sql .= ' left join suppliers s on t.id = s.record_num ';
			$sql .= ' left join suppliers_parent sp on REPLACE(t.id, \'P_\', "") = sp.record_num ';
			$sql .= ' group by id ';
			$sql .= ' order by sum(average) asc; ';
		} else {
			$sql =  "SELECT  ";
			$sql .= "	(".$sql_id.") AS s_id, ";
			$sql .= "	NULL as feedbackWeighted, ";
			$sql .= "	ROUND(AVG(".$sql_rate."), 1) AS reviewAverage, ";
			$sql .= " 	count(*) as count, ";
			$sql .= '   "'.$entity.'" as entity, ';
			$sql .= '   "'.$state.'" as state, ';
			$sql .= '   "'.$timeframe.'" as timeframe, ';
			$sql .= '   "'.$type.'" as type ';
			$sql .= "FROM ";
			$sql .= "	feedback f ";
			$sql .= "INNER JOIN suppliers s ON s.record_num = f.supplier_id ";
			$sql .= "WHERE category_id IN ('4', '5') AND f.purchased = 'Yes' AND public = 1 $time_range ";
			if($state != '') {
				$sql .= ' AND f.iState = \''.$state.'\' ';		
			}
			$sql .= "group by (".$sql_id.") ";
			$sql .= "having count(*) >= 5 ";
			$sql .= "order by ROUND(AVG(".$sql_rate."), 1); ";
		}
		return $sql;
	}

?>