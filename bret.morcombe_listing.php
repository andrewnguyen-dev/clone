<?php
	$noSession = 1;
	require_once('global.php');

	$content = '';

	$installers_SQL = "SELECT
		CacheFeedbackTotals.record_num AS `CacheFeedbackTotals__record_num`,
		CacheFeedbackTotals.supplierName AS `CacheFeedbackTotals__supplierName`,
		CacheFeedbackTotals.supplierURLName AS `CacheFeedbackTotals__supplierURLName`,
		CacheFeedbackTotals.timeframe AS `CacheFeedbackTotals__timeframe`,
		CacheFeedbackTotals.feedbackTotal AS `CacheFeedbackTotals__feedbackTotal`,
		CacheFeedbackTotals.feedbackCount AS `CacheFeedbackTotals__feedbackCount`,
		CacheFeedbackTotals.feedbackTotalWeighted AS `CacheFeedbackTotals__feedbackTotalWeighted`,
		CacheFeedbackTotals.statesServed AS `CacheFeedbackTotals__statesServed`,
		CacheFeedbackTotals.supplierStatus AS `CacheFeedbackTotals__supplierStatus`";

	$installers_SQL .= " FROM cache_feedback_total CacheFeedbackTotals";
	$installers_SQL .= " WHERE timeframe = 'all'";
	$installers_SQL .= " GROUP BY supplierName ORDER BY supplierName";

	$installers_result = db_query($installers_SQL);

	if ($installers_result->num_rows) {

		$installers = mysqli_fetch_all($installers_result, MYSQLI_ASSOC);
		$content .= "<h3>Installers</h3>";

		foreach ($installers as $installer) {

			$installer_name = $installer['CacheFeedbackTotals__supplierName'];
			$installer_url_name = $installer['CacheFeedbackTotals__supplierURLName'];

			$content .= htmlspecialchars('<li><a href="https://www.solarquotes.com.au/installer-review/'. $installer_url_name .'/?utm_source=SPS&utm_content=revlist" rel="nofollow">'. $installer_name .'</a></li>');
			$content .= "<br>";
		}
	}

	$content .= "<br><br>";

	$panels_SQL = "SELECT ParserSolar.Manufacturer AS `ParserSolar__Manufacturer`";
	$panels_SQL .= " FROM parser_solar ParserSolar";
	$panels_SQL .= " GROUP BY Manufacturer ORDER BY Manufacturer";

	$panels_result = db_query($panels_SQL);

	if ($panels_result->num_rows) {

		$panels = mysqli_fetch_all($panels_result, MYSQLI_ASSOC);
		$content .= "<h3>Panels</h3>";

		foreach ($panels as $panel) {
			$panel_name = $panel['ParserSolar__Manufacturer'];
			$panel_name_for_url = str_replace(' ', '-', strtolower($panel_name));

			$content .= htmlspecialchars('<li><a href="https://www.solarquotes.com.au/panels/'. $panel_name_for_url .'-review.html?utm_source=SPS&utm_content=revlist" rel="nofollow">'. $panel_name .'</a></li>');
			$content .= "<br>";
		}
	}

	$content .= "<br><br>";

	$inverters_sql = "SELECT ParserSolarAccreditationInverters.Manufacturer AS `ParserSolarAccreditationInverters__Manufacturer`";
	$inverters_sql .= " FROM parser_solaraccreditation_inverter ParserSolarAccreditationInverters";
	$inverters_sql .= " GROUP BY Manufacturer ORDER BY Manufacturer";

	$inverters_result = db_query($inverters_sql);

	if ($inverters_result->num_rows) {

		$inverters = mysqli_fetch_all($inverters_result, MYSQLI_ASSOC);
		$content .= "<h3>Inverters</h3>";

		foreach ($inverters as $inverter) {
			$inverter_name = $inverter['ParserSolarAccreditationInverters__Manufacturer'];
			$inverter_name_for_url = str_replace(' ', '-', strtolower($inverter_name));

			$content .= htmlspecialchars('<li><a href="https://www.solarquotes.com.au/inverters/'. $inverter_name_for_url .'-review.html?utm_source=SPS&utm_content=revlist" rel="nofollow">'. $inverter_name .'</a></li>');
			$content .= "<br>";
		}
	}

	$content .= "<br><br>";

	uploadFileToCake(
		fileName: 'bret.morcombe_listing.html',
		content: $content
	);