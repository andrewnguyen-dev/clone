<?php
	// load global libraries
    include('global.php');
    set_time_limit(0);
    
    $countTotal = db_getVal("SELECT COUNT(*) AS countTotal FROM feedback");
    $countUncategorized = db_getVal("SELECT COUNT(*) AS countUncategorized FROM feedback WHERE category_id = 1");
    $countPending = db_getVal("SELECT COUNT(*) AS countPending FROM feedback WHERE category_id = 2");
    $countArchived = db_getVal("SELECT COUNT(*) AS countArchived FROM feedback WHERE category_id = 3");
    $countPublished = db_getVal("SELECT COUNT(*) AS countPublished FROM feedback WHERE category_id = 4");
    $countSupplier = db_query("SELECT COUNT(DISTINCT(supplier_id)) AS countSupplier FROM feedback WHERE public = 1");		// db_getVal does not work nicely with SELECT DISTINCT
    
    
    
    while ($row = mysqli_fetch_array($countSupplier, MYSQLI_ASSOC))
        extract($row, EXTR_PREFIX_ALL, 'c');
    	
    $countSupplier = $c_countSupplier;
    
	// Insert the new row
	$SQL = "INSERT INTO cache_feedback_count (countTotal, countPublished, countPending, countArchived, countUncategorized, countSupplier, executedDate) ";
	$SQL .= "VALUES ({$countTotal}, {$countPublished}, {$countPending}, {$countArchived}, {$countUncategorized}, {$countSupplier}, {$nowSql});";
	db_query($SQL);
	
	// Now select the last 5 weeks
	$SQL = "SELECT * FROM cache_feedback_count ORDER BY record_num DESC LIMIT 5";
	$result = db_query($SQL);
	
	$body = "<table cellpadding='4'>";
	$body .= "<tr>";
	$body .= "<th>Executed Date</th><th>Total Count</th><th>Uncategorized Count</th><th>Archived Count</th><th>Pending Count</th><th>Published Count</th><th>Supplier Count</th>";
	$body .= "</tr>";
	
	while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
		extract($row, EXTR_PREFIX_ALL, 'f');
		
		$executedDate = date('Y-m-d', strtotime($f_executedDate));
		$body .= "<tr>";
		$body .= "<td>{$executedDate}</td><td>{$f_countTotal}</td><td>{$f_countUncategorized}</td><td>{$f_countArchived}</td><td>{$f_countPending}</td><td>{$f_countPublished}</td><td>{$f_countSupplier}</td>";
		$body .= "</tr>";
	}
	$body .= "</table>";
	$body .= "<br /><br />";
	$body .= "<p>The supplier count is the total count of all suppliers that have at least one review published</p>";

	// Send the email
	SendMail("finnvip@solarquotes.com.au", "Finn", "Weekly Feedback Stats Report", $body);
?>