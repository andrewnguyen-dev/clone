<?php
	require("global.php");

	echo 'start';
	print_r(GetBrevoCampaign('sq_news_weekly'));
	//print_r(GetBrevoCampaign("sq_news_weekly"));
	echo 'end';

	//AddLeadToBrevo(754652, 'sq_news_weekly', 'Quoting System');
?>