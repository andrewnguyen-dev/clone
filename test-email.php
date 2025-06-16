<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	
	require("global.php");
	
	SendMail("johnb@solarquotes.com.au", "JB", "Solargain TEST", "TEST ONLY EMAIL FROM SQ.COM.AU");
?>

<html>
	<head></head>
	<body>
		<p>HI!</p>
	</body>
</html>