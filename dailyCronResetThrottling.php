<?php

require_once('global.php');

function write_log($text) {
    global $nowSql;
    db_query("INSERT INTO log_postcode_throttling (content, submitted) VALUES ('$text', ".$nowSql.")");
}

$throttling = db_getVal("SELECT postcode_throttling FROM settings LIMIT 1");
if ($throttling != '1' && $throttling != 1) {
    write_log("Daily reset terminated as postcode throttling is turned off in settings.");
    return;
}

db_query("TRUNCATE TABLE postcode_throttle");

// JB to update this list nightly.  The idea is that we take yesterday's report and 
// automatically add some postcodes to the list.
$postcodesToAdd = ['6055','6025','6153','6101','2627','6030','6164','6430','6230','6060','6059','6018','2738','6510','6312','6225','6157','6154','6150','6110','6020','6009','3978','3810','2680'];
foreach ($postcodesToAdd as $p) {
    db_query("INSERT INTO postcode_throttle (postcode, status) VALUES ('" . addslashes($p) . "', '2qs')");
}

$logText = 'Inserted postcodes from opportunity report ' . implode(', ', $postcodesToAdd);
write_log($logText);

write_log("Postcode throttling has been reset");