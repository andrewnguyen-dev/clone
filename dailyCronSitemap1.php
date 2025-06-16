<?php
	// load global libraries
    include('global.php');
    set_time_limit(0);
    
    $sitemapFile = "sitemap1.xml";
    
    $sitemap = generateSitemap();

    $sitemap .= sitemapFooter();
    
	uploadFileToCake(
		fileName: $sitemapFile,
		content: $sitemap,
	);

    function generateSitemap() {
    	global $siteURL, $siteURLSSL;
		
		$header = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$header .= '<?xml-stylesheet type="text/xsl" href="' . $siteURLSSL . 'webroot/sitemap.xsl"?>' . "\n";
		$header .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n";
		
		$SQL = 'SELECT URL FROM cms_sitemap ORDER BY URL ASC ';
		$result = db_query($SQL);
		
		while($page = mysqli_fetch_assoc($result)){
			extract($page, EXTR_PREFIX_ALL, 'p');
			
			$p_URL = str_replace('http', 'https', $p_URL);
			
			if(stripos($p_URL, 'index.html') !== false)
				$p_URL = str_replace('index.html', '', $p_URL);
				
			$header .= '<url><loc>' . $p_URL . '</loc><priority>1.0</priority><lastmod>' . date('Y-m-d', time()) . '</lastmod><changefreq>weekly</changefreq></url>' . "\n";
		}
		
		// Now manually add in the rest
		$header .= '<url><loc>https://www.solarquotes.com.au/calc5/</loc><priority>1.0</priority><lastmod>' . date('Y-m-d', time()) . '</lastmod><changefreq>weekly</changefreq></url>' . "\n";
		$header .= '<url><loc>https://www.solarquotes.com.au/solar-power-panel-estimator.htm</loc><priority>1.0</priority><lastmod>' . date('Y-m-d', time()) . '</lastmod><changefreq>weekly</changefreq></url>' . "\n";
		$header .= '<url><loc>https://www.solarquotes.com.au/solar-panel-efficiency-weather-calculator/</loc><priority>1.0</priority><lastmod>' . date('Y-m-d', time()) . '</lastmod><changefreq>weekly</changefreq></url>' . "\n";
		$header .= '<url><loc>https://www.solarquotes.com.au/panels/comparison/chart/</loc><priority>1.0</priority><lastmod>' . date('Y-m-d', time()) . '</lastmod><changefreq>weekly</changefreq></url>' . "\n";
		$header .= '<url><loc>https://www.solarquotes.com.au/inverters/comparison/chart/</loc><priority>1.0</priority><lastmod>' . date('Y-m-d', time()) . '</lastmod><changefreq>weekly</changefreq></url>' . "\n";

		return $header;
    }
    
    function sitemapFooter() {
		$footer = '</urlset>';
		
		return $footer;
    }
?>