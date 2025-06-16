<?php
	// load global libraries
    include('global.php');
    set_time_limit(0);

    $sitemapFile = "sitemap.xml";

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
		$header .= '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		$header .= '<url><loc>' . $siteURLSSL . 'sitemap1.xml</loc><priority>1.0</priority><lastmod>' . date('Y-m-d', time()) . '</lastmod><changefreq>daily</changefreq></url>' . "\n";
		$header .= '<url><loc>' . $siteURLSSL . 'sitemapreviewsuppliers.xml</loc><priority>0.8</priority><lastmod>' . date('Y-m-d', time()) . '</lastmod><changefreq>daily</changefreq></url>' . "\n";
		$header .= '<url><loc>' . $siteURLSSL . 'sitemapsolarpanelmanufacturers.xml</loc><priority>0.8</priority><lastmod>' . date('Y-m-d', time()) . '</lastmod><changefreq>daily</changefreq></url>' . "\n";
		$header .= '<url><loc>' . $siteURLSSL . 'sitemapsolarinvertermanufacturers.xml</loc><priority>0.8</priority><lastmod>' . date('Y-m-d', time()) . '</lastmod><changefreq>daily</changefreq></url>' . "\n";

		return $header;
    }

    function sitemapFooter() {
		$footer = '</urlset>';

		return $footer;
    }
?>