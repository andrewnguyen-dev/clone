<?php
    include('global.php');
    
    $contentPages = array();
    
    $SQL = "SELECT * FROM cms_page_systems CPS ";
    $SQL .= "INNER JOIN cms_page_urls CPU ON CPS.PageID = CPU.PageID ";
    $SQL .= "INNER JOIN cms_silos CS ON CPU.SiloID = CS.SiloID ";
    $SQL .= "WHERE CPS.Content LIKE '%pmgcms%'";
	$result = db_query($SQL);
	
	while($page = mysqli_fetch_assoc($result)) {
		extract($page, EXTR_PREFIX_ALL, 'p');
		
		$contentPages[] = array('Website' => 'https://www.solarquotes.com.au' . $p_Path . $p_Url, 'CMS' => 'http://www.pmgcms.com/page_systems/edit/' . $p_PageID);
	}
	
	if (!empty($contentPages)) {
		$subject = "CMS Content Issues - SQ";
		$body = "The following pages are found to have the text 'pmgcms' within the content.  This usually occurs because an image has not been linked correctly.  ";
		$body .= "It does however also provide an early warning if something goes wrong with the internal sync.";
		$body .= "<br /><br />";
		
		$body .= "<table width = '100%'>";
		$body .= "<tr><th align='left'>Content Page URL</th><th align='left'>CMS Edit Page URL</th></tr>";
		
		foreach ($contentPages AS $contentPage) {
			$body .= "<tr><td>{$contentPage['Website']}</td>";
			$body .= "<td>{$contentPage['CMS']}</td></tr>";
		}
		
		$body .= "</table>";
		
		
		SendMail("johnb@solarquotes.com.au", "John Burcher", $subject, $body);
		SendMail("jonathon@solarquotes.com.au", "Jonathon Wedge", $subject, $body);
		SendMail("finnvip@solarquotes.com.au", "Finn Peacock", $subject, $body);
	}
?>