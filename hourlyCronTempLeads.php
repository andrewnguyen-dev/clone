<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');

    include('global.php');

    $SQL = "SELECT * FROM tmp_leads WHERE submitted < NOW() - INTERVAL 1 DAY;";
    $result = db_query($SQL);
    
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        extract($row, EXTR_PREFIX_ALL, 'f');
        
        // Clean the data
        $f_email = str_replace("'", "", $f_email);
        
        // Build the array
        $details = array();
        $details['fName'] = $f_fName;
        $details['lName'] = $f_lName;
        $details['phone'] = $f_phone;
        $details['email'] = strtolower($f_email);
        $details['conversation'] = $f_conversation;
        
        $SQL = "SELECT COUNT(*) FROM leads_reminded WHERE LOWER(email) = '{$details['email']}'";
        $count = db_getVal($SQL);
        
        // This email has already received a notification
        if ($count > 0)
        	continue;
        	
        // Does this email already exist in our lead table
        $SQL = "SELECT COUNT(*) FROM leads WHERE LOWER(email) = '{$details['email']}' AND submitted >= DATE_SUB(CURDATE(),INTERVAL 1 MONTH)";
        $count = db_getVal($SQL);
        if ($count > 0)
        	continue;

        $URL = $siteURL . 'quote/resume.php?d=' . base64_encode(json_encode($details)) . "&e=mcsq";
        
		try {
			$newURL = shortenURL($URL);
		} catch (Exception $e) {
			$newURL = $URL;
		}
        
        $message = "Hi {$f_fName},<br /><br />";
        $message .= "Thanks for starting the process of requesting 3 Solar Quotes!<br /><br />";
        $message .= "If you have time to complete the form, then I'll do my absolute best to find you 3 great quotes from reputable installers.<br /><br />";
        $message .= "<b>You can finish completing the quote form </b> <a href='" . $newURL . "'>here</a><br /><br />";
        $message .= "Or if you have changed your mind, just ignore this email and you'll never hear from me again.<br /><br />";
        $message .= "Many Thanks - and good luck in your hunt for Solar Power!<br /><br />";
        $message .= "Best Regards,<br /><br />";
        $message .= "Finn Peacock - Founder of SolarQuotes";

        try {
	        SendMail($f_email, $f_fName . ' ' . $f_lName, 'Your request for 3 Solar Quotes', $message);
	    } catch (Exception $e) {
			// Handle gracefully
	        SendMail('johnb@solarquotes.com.au', 'John Burcher', 'ERROR: Your request for 3 Solar Quotes', $e->getMessage());
		}
        
        $SQL = "INSERT INTO leads_reminded (email, submitted) VALUES ('{$details['email']}', {$nowSql})";
        db_query($SQL);
	}
	
	// Clean up
	$SQL = "DELETE FROM tmp_leads WHERE submitted < NOW() - INTERVAL 1 DAY;";
	db_query($SQL);
?>