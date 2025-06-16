<?php
	require("global.php");

    $sql = "SELECT * FROM leads WHERE record_num  = '885598'";
	$leads = db_query($sql, "Error retrieving leads");

    foreach ($leads as $lead) {
        $details = unserialize(base64_decode($lead['siteDetails']));
        $details['Anything Else:'] = html_entity_decode($details['Anything Else:'], ENT_QUOTES);
        $encoded = base64_encode(serialize($details));

        echo "$encoded\n";
    }
?>