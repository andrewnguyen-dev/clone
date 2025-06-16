<?php
	include('global.php');
	set_time_limit(0);
	
	$surveyTitle = date('F jS Y'). ' Survey';
	
	// Prepare current week survey
	#Get maximum idsurvey that has associated questions
	$SQL = " SELECT MAX(idsurvey) as survey_id FROM survey_link_questions_answers; ";
	$result = mysqli_fetch_assoc(db_query($SQL));
	$current_survey_id = $result['survey_id'];
	
	#Clone last survey
	$SQL = " INSERT INTO surveys (title, blurb, created) ";
	$SQL .= " SELECT '{$surveyTitle}', blurb, NOW() ";
	$SQL .= " FROM surveys ";
	$SQL .= " WHERE record_num = $current_survey_id";
	db_query($SQL);
	
	#Get Cloned Survey Id
	$SQL = " SELECT max(record_num) as new_survey_id FROM surveys ";
	$new_survey_id = db_getVal($SQL);
	
	#Insert survey questions
	$SQL = " INSERT INTO survey_link_questions_answers(idsurvey, idsurvey_question) VALUES ";
	$SQL .= "({$new_survey_id}, 1),"; // How do you feel about last months lead quality?
	$SQL .= "({$new_survey_id}, 2),"; // Last month - how many SolarQuotes leads were converted to a sale?
	$SQL .= "({$new_survey_id}, 5);"; // Why did you give this score?

	db_query($SQL);
	
	// Preparation End
	$SQL = "SELECT mainContactEmail, mainContactfName, entity_id ";
	$SQL .= "FROM suppliers ";
	$SQL .= "WHERE status = 'active' AND surveyOptOut = 'N' ";
	$SQL .= "GROUP BY mainContactEmail ";
	$SQL .= "ORDER BY record_num DESC ";
	
	$active_suppliers = db_query($SQL);
	
	$surveyId = $new_survey_id;
	while ($supplier = mysqli_fetch_array($active_suppliers, MYSQLI_ASSOC)) {
		try {
    		extract(htmlentitiesRecursive($supplier), EXTR_PREFIX_ALL, 's');
    		
    		$json = json_encode(array(
    			'surveyid' => $surveyId,
    			'entityid' => $s_entity_id
    		));
    		
    		$rateLinkBase = $siteURLSSL . 'surveys/supplier/' . Encrypt($json);
    		$scoreOptions = [];
    		for($i=1;$i<=10;$i++){
				$scoreOptions[] = '<td align="center" style="width:10%;background-color:#f7f7f7;border-color:#cacaca;border-style:solid;border-width:1px'.($i!==1 ? ';border-left:0' : '').'" valign="middle"><a href="'."{$rateLinkBase}/{$i}/".'" style="display:block;line-height:50px;text-decoration:none;font-size:18px;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-weight:bold" target="_blank" ><span dir="ltr" style="color:#3e2265">'.$i.'</span></a></td>';
    		}
    		
    		$supplier['OptOut'] = $siteURLSSL . 'surveys/optout/' . Encrypt($s_entity_id);
    		$supplier['scoreOptions'] = implode("\r\n", $scoreOptions);

    		$from = 'silvana@solarquotes.com.au';
    		$fromName = 'Silvana Griggs';
    		
			sendTemplateEmail($s_mainContactEmail, $s_mainContactfName, 'monthlySurvey', $supplier, $from, $fromName);
		} catch (Exception $e) {
			//echo $e->getMessage();
		}
	}
?>