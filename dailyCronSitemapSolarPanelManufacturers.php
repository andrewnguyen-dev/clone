<?php
	// load global libraries
    include('global.php');
    set_time_limit(120);

    $sitemapFile = "sitemapsolarpanelmanufacturers.xml";
    $sitemapFileRaw = "sitemapsolarpanelmanufacturers-raw.xml";

    // Build up the new sitemap in a string
    $sitemap = sitemapHeader();

    $SQL = "SELECT DISTINCT PS.SupplierName FROM parser_supplier PS WHERE PS.Blurb != '' ORDER BY PS.SupplierName ASC";

    $manufacturers = db_query($SQL);
    while ($manufacturer = mysqli_fetch_array($manufacturers, MYSQLI_ASSOC)) {
        extract($manufacturer, EXTR_PREFIX_ALL, 'm');

        $manufacturerEx = sanitizeURL($m_SupplierName);

        $sitemap .= sitemapEntrySolarPanelManufacturer($manufacturerEx);
    }

    $sitemap .= sitemapFooter();

    // Save new sitemap
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

    function sitemapEntrySolarPanelManufacturer($manufacturer) {
    	global $siteURLSSL;

		$entry = '<url>';
		$entry .= '<loc>' . $siteURLSSL . 'panels/' . $manufacturer . '-review.html</loc>';
		$entry .= '<lastmod>' . date('Y-m-d', time()) . '</lastmod>';
		$entry .= '<changefreq>weekly</changefreq>';
		$entry .= '<priority>0.8</priority>';
		$entry .= '</url>' . "\n";

		return $entry;
    }

    function sitemapFooter() {
		$footer = '</urlset>';

		return $footer;
    }
?>