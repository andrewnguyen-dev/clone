<?php
	// load global libraries
	require_once('global.php');

	global $nowSql;

	$SQLsilver = "SELECT COUNT(*) AS 'count' FROM cache_trust_badges WHERE trustBadge = 'Silver' AND STATUS = 'active' AND POSITION = 'current' GROUP BY trustBadge;";
	$SQLgold = "SELECT COUNT(*) AS 'count' FROM cache_trust_badges WHERE trustBadge = 'Gold' AND STATUS = 'active' AND POSITION = 'current' GROUP BY trustBadge;";
	$SQLplat = "SELECT COUNT(*) AS 'count' FROM cache_trust_badges WHERE trustBadge = 'Platinum' AND STATUS = 'active' AND POSITION = 'current' GROUP BY trustBadge;";
	$SQLlegend = "SELECT COUNT(*) AS 'count' FROM cache_trust_badges WHERE trustBadge = 'Legendary' AND STATUS = 'active' AND POSITION = 'current' GROUP BY trustBadge;";

	$counts['silver'] = db_query($SQLsilver)->fetch_row();
	$counts['gold'] = db_query($SQLgold)->fetch_row();
	$counts['platinum'] = db_query($SQLplat)->fetch_row();
	$counts['legendary'] = db_query($SQLlegend)->fetch_row();

	foreach($counts as $badge => $value){
		if(is_array($value)){
			db_query("UPDATE system_health SET value=$value[0], updated={$nowSql} WHERE title = 'SolarQuotes Trust Badge {$badge} Suppliers'");
		} else {
			db_query("UPDATE system_health SET value=0, updated={$nowSql} WHERE title = 'SolarQuotes Trust Badge {$badge} Suppliers'");
		}
	}

	echo "\nDone\n";
?>