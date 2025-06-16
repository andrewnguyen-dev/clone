<?php
	require("global.php");

	$bc = new Basecamp();
    global $bcTrialCampfireID;
	$message = "This is a test message by John Burcher.  Please disregard.  Thank you.";
    $return = $bc->new_campfire_message($bcTrialCampfireID, $message);

	echo $return;
?>