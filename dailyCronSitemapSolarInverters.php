<?php
	// load global libraries
    include('global.php');
    set_time_limit(120);
    
    $sitemapFile = "sitemapsolarinverters.xml";

    // Build up the new sitemap in a string
    $sitemap = sitemapHeader();
    
    $SQL = "SELECT * FROM parser_solaraccreditation_inverter ORDER BY Manufacturer, ModelNumber ASC";

    $manufacturers = db_query($SQL);
    while ($manufacturer = mysqli_fetch_array($manufacturers, MYSQLI_ASSOC)) {
        extract($manufacturer, EXTR_PREFIX_ALL, 'm');

        $manufacturerEx = sanitizeURL($m_Manufacturer);
        $modelEx = sanitizeURL($m_ModelNumber);
        
        $sitemap .= sitemapEntrySolarInverter($manufacturerEx, $modelEx);
    }

    $sitemap .= sitemapFooter();
    
    // Save new sitemap
	  uploadFileToCake(
		  fileName: $sitemapFile,
		  content: $sitemap,
	  );

    function sitemapHeader() {
    	global $siteURL;
    	
		$header = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$header .= '<?xml-stylesheet type="text/xsl" href="' . $siteURL . 'sitemap.xsl"?>' . "\n";
		$header .= '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		
		return $header;
    }
    
    function sitemapEntrySolarInverter($manufacturer, $model) {
    	global $siteURLSSL;
    	
		$entry = '<url>';
		$entry .= '<loc>' . $siteURLSSL . 'inverters/' . $manufacturer . '/' . $model . '.html</loc>';
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