<?php

require_once('global.php');

// sql query to get leads that are between 5 and 6 days old and are incomplete
global $nowSql;
$sql = "SELECT record_num FROM leads WHERE status = 'incomplete' AND leadType NOT IN ('Repair Residential','Repair Commercial') AND submitted < DATE_SUB({$nowSql}, INTERVAL 5 DAY) AND submitted > DATE_SUB({$nowSql}, INTERVAL 6 DAY)";
$leads = db_query($sql);

// foreach lead, send an email to the lead with a reminder to complete their lead
while ($lead = mysqli_fetch_assoc($leads)) {
    $data = loadLeadData($lead['record_num']);
    sendTemplateEmail($data['email'], $data['fName']." ".$data['lName'], 'incompleteLeadReminder', $data);
}