<?php
	// load global libraries
	include('global.php');
	set_time_limit(300);
	$debugging = 0;

	global $phpdir;

	$dateStart = db_getVal("select DATE_FORMAT({$nowSql}- INTERVAL 1 MONTH, '%Y-%m');").'-01 00:00:00';
	$dateEnd = db_getVal("select DATE_FORMAT({$nowSql}, '%Y-%m');").'-01 00:00:00';

	$SQL = "SELECT istate AS state, COUNT(*) AS leads FROM leads  WHERE STATUS = 'dispatched' AND istate != '' AND updated > '".$dateStart."' AND updated < '".$dateEnd."' GROUP BY istate ORDER BY istate ASC;";
	$result = db_query($SQL);

	$csv = '"state","leads"';
	while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
		$csv .= "\n".'"'.$row['state'].'","'.$row['leads'].'"';
	}

	$filename = "$phpdir/temp/solarquotesstates.csv";
	$file = fopen($filename, 'w+');
	fwrite($file, $csv);
	fclose($file);

	$subject = 'SolarQuotes Lead Volume for '.date('M Y',strtotime('-1 month'));
	$body = 'SolarQuotes lead volume by state CSV attached for '.date('F Y',strtotime('-1 month'));

	sendMailWithAttachmentDefaultTemplate(
		'trevor@solarquotes.com.au', 'Trevor',
		$subject, $body, $filename, $techEmail, $techName
	);

?>