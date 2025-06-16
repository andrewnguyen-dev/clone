<?php
	// load global libraries
	include('global.php');
	set_time_limit(240);

	$sitemapFile = "sitemapreviewsuppliers.xml";
	$sitemapFileRaw = "sitemapreviewsuppliers-raw.xml";
	/* The names used in the exclusion list should be the ones that appear in the urls, for example:
		"andrew-hanna-electrical" and not "Andrew Hanna Electrical"
	*/
	$exclusionList = [ 'andrew-hanna-electrical', 'custom-solar', 'solagex-australia-adelaide', 'now-energy', 'allsafe', 'blackmores-power-and-water', 'australian-water-and-solar', 'the-switch-all-electrical-services', 'isolux', 'australian-solar-access-pty-ltd', 'yello-energy-group', 'freedom-solar', 'commodore-australia', 'ecoplus', 'scott-burke-electrical', 'marsol-industries', 'energy-choice', 'sparkys-a-class-electrical', 'simon-barclay', 'construct-solar-pty-ltd', 'australian-water-and-solar', 'solar-eco' ];

	// Select the top 20 suppliers, who we assign a greater priority too
	$topSuppliersArray = array();
	$SQL = "SELECT * FROM cache_feedback_total WHERE supplierStatus = 'active' AND timeframe = 'all' ORDER BY feedbackTotalWeighted DESC LIMIT 20";
	$topSuppliers = db_query($SQL);

	while ($topSupplier = mysqli_fetch_array($topSuppliers, MYSQLI_ASSOC)) {
		extract($topSupplier, EXTR_PREFIX_ALL, 'ts');

		$topSuppliersArray[] = $ts_supplierURLName;
	}

	// Build up the new sitemap in a string
	$sitemap = sitemapHeader();

	$SQL = " SELECT DISTINCT S.parent, S.parentUseReview, parent, ";
	$SQL .= " 	CASE WHEN parentUseReview = 'Y' AND parent > 1 THEN SP.parentName ELSE S.company END as company, ";
	$SQL .= " 	CASE WHEN parentUseReview = 'Y' AND parent > 1 THEN SP.record_num ELSE S.record_num END as record_num ";
	$SQL .= " FROM suppliers S ";
	$SQL .= " INNER JOIN suppliers_parent SP ON SP.record_num = S.parent ";
	$SQL .= " ORDER BY company ASC ";

	$supplier = db_query($SQL);
	$inserts = array();
	while ($supplierRow = mysqli_fetch_array($supplier, MYSQLI_ASSOC)) {
		extract($supplierRow, EXTR_PREFIX_ALL, 's');

		if ($s_parent > 1 && $s_parentUseReview == 'Y') {
			$SQL = "SELECT COUNT(*) FROM feedback F ";
			$SQL .= "INNER JOIN suppliers S ON F.supplier_id = S.record_num ";
			$SQL .= "WHERE S.parent = {$s_record_num} AND F.purchased ='Yes' AND F.public = 1;";
		} else
			$SQL = "SELECT COUNT(*) FROM feedback WHERE supplier_id = {$s_record_num} AND purchased = 'Yes' AND public = 1;";


	$companyEx = strtolower(sanitizeURL($s_company));
	if (db_getVal($SQL) > 0 && ! in_array($companyEx, $exclusionList)) {
		if ($companyEx != '' && !isset($inserts[$companyEx])){
			$sitemap .= sitemapEntryReviewSupplier($companyEx);
			$inserts[$companyEx] = '';
		}
	}
	}

	$sitemap .= sitemapFooter();

	uploadFileToCake(
		fileName: $sitemapFile,
		content: $sitemap,
	);

	uploadFileToCake(
		fileName: $sitemapFileRaw,
		content: $sitemap,
	);

	function sitemapHeader() {
		global $siteURL, $siteURLSSL;

		$header = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$header .= '<?xml-stylesheet type="text/xsl" href="' . $siteURLSSL . 'webroot/sitemap.xsl"?>' . "\n";
		$header .= '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		return $header;
	}

	function sitemapEntryReviewSupplier($supplier) {
		global $siteURLSSL, $topSuppliersArray;

		$entry = '<url>';
		$entry .= '<loc>' . $siteURLSSL . 'installer-review/' . $supplier . '/</loc>';
		$entry .= '<lastmod>' . date('Y-m-d', time()) . '</lastmod>';
		$entry .= '<changefreq>weekly</changefreq>';

		if (in_array($supplier, $topSuppliersArray))
			$entry .= '<priority>1.0</priority>';
		else
			$entry .= '<priority>0.8</priority>';

		$entry .= '</url>' . "\n";

		return $entry;
	}

	function sitemapFooter() {
		$footer = '</urlset>';

		return $footer;
	}
?>