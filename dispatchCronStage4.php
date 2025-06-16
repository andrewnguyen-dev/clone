<?php
// load global libraries
include('global.php');

// Get all leads to be sent to unsigned suppliers for free
$SQL = ' SELECT record_num, leadType FROM leads WHERE openFree = "Y";';

$result = db_query($SQL);

while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
	dispatchFree($row['record_num'], $row['leadType']);
	
	// Change field on the leads table so that it doesn't get ran again
	db_query('UPDATE leads SET openFree = "N" WHERE record_num = ' . $row['record_num']);
}
?>