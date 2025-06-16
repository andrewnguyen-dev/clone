<?php
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

	// load global libraries
	include('global.php');
	set_time_limit(0);
	$debugging = 0;

	$access_tokens = [];

	$count = 0;

	$SQL =  "SELECT state, number, checked FROM entity_supplier_electrical_licenses ";
	$SQL .= "WHERE state='NSW' ";	//Change this line when we have other states APIs
	$SQL .= "GROUP BY state, number ";
	$SQL .= "ORDER BY checked ASC LIMIT 50";
	$rows = db_query($SQL);

	while ($row = mysqli_fetch_array($rows, MYSQLI_ASSOC)) {
		$continue = licenseNSW($row);
		if(!$continue) break;
		
		sleep(2);
	}
	echo 'Done';

	function licenseNSW($entity) {
		global $_connection, $access_tokens, $licensesNSW, $nowSql;

		// NSW access token can be reused for multiple calls - check if we have it already
		if(array_key_exists('NSW', $access_tokens)) {	// yes
			$access_token = $access_tokens['NSW'];
		}
		else { // no, lets get the token
			$url = "https://api.onegov.nsw.gov.au/oauth/client_credential/accesstoken?grant_type=client_credentials";
			$headers = ['authorization: Basic '.base64_encode($licensesNSW['key'].':'.$licensesNSW['secret'])];

			for($i=0; $i<2; $i++) {
				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_POST, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_TIMEOUT,10);

				$output = curl_exec($ch);

				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				curl_close($ch);

				if($http_code != 0)	//connection ok,
					break;			//don't try again

				sleep(2);	//connection failed, sleep and then retry
			}
			$output = json_decode($output);
			if(!property_exists($output, 'access_token')) {
				raiseTicketLicenses('Authentication error - NSW API', 'NSW Licenses were not checked/updated', 'NSW');
				die();
			}
			$access_token = $output->access_token;
			$access_tokens['NSW'] = $access_token;
		}

		$url = 'https://api.onegov.nsw.gov.au/tradesregister/v1/verify?licenceNumber=' . trim($entity['number']);
		$headers = ['Authorization: Bearer '.$access_token, 'Accept: application/json', 'dataType: json', 'apikey: '.$licensesNSW['key']];
		for($i=0; $i<3; $i++) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_POST, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT,10);

			$output = curl_exec($ch);
			
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			curl_close($ch);

			if($http_code != 0)	//connection ok,
				break;			//don't try again

			sleep(2);	//connection failed, sleep and then retry
		}
		if(empty($output) || strlen($output)<5) {
			$message = "No response received when checking Electrical License ".
				"<a href='https://www.onegov.nsw.gov.au/publicregister/#/publicregister/search/Trades' target='_blank'>NSW ".$entity['number']."</a>";
			raiseTicketLicenses('No response received from Electrical Licenses API', $message, 'NSW', $entity['number']);
			return true;
		}
		$output = json_decode($output);
		
		if(!is_array($output)) {
			raiseTicketLicenses('Electrical Licenses API Error', $output->message, 'NSW', $entity['number']);
			
			// If this request wasn't successfull due to quota limit return false to stop the loop, none of the following requests will work
			return !(strpos($output->message, 'Quota limit') !== false);
		}

		$sqlCondition = " WHERE state LIKE '".$entity['state']."' AND number = '".mysqli_escape_string($_connection, $entity['number'])."';";
		if($output[0]->status=="Current") {
			$SQL = "UPDATE entity_supplier_electrical_licenses SET ";
			$SQL .= "checked ={$nowSql}, name = '".mysqli_escape_string($_connection, $output[0]->licensee)."', origin = 'api', valid='Y'";
			$SQL .= $sqlCondition;
			db_query($SQL);
			return true;
		} else {
			$SQL = "UPDATE entity_supplier_electrical_licenses SET ";
			$SQL .= "valid='N'";
			$SQL .= $sqlCondition;		
			db_query($SQL);

			$message = "Electrical License status is not 'Current' - ".
				"<a href='https://www.onegov.nsw.gov.au/publicregister/#/publicregister/search/Trades' target='_blank'>NSW ".$entity['number']."</a> (".
				$output[0]->status.")";
			raiseTicketLicenses('Electrical License change - NSW', $message, 'NSW', $entity['number']);
			return true;
		}
	}

	function raiseTicketLicenses($title, $message, $state, $number=false) {
		global $zenDeskSubdomain, $zenDeskEmail, $zenDeskKey, $siteURLSSL;
		$agent_id = db_getVal('SELECT a.zendeskid FROM settings s INNER JOIN admins a ON s.abn_acn_notification = a.record_num;');

		$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
		$client->setAuth('token', $zenDeskKey);

		$subject = $title;
		$body = $message;

		if($number) {
			$body .= "<br>The following suppliers and parents are associated to this Electrical License:<br>";
			$suppliersAndParents = "SELECT COALESCE(s.record_num,p.record_num) as record_num, s.company, p.parentName FROM entity_supplier_electrical_licenses esl LEFT JOIN suppliers s ON esl.entity_id = s.entity_id LEFT JOIN suppliers_parent p ON esl.entity_id=p.entity_id WHERE esl.state LIKE '".$state."' AND esl.number LIKE '".$number."';";
			$rows = db_query($suppliersAndParents);
			while ($row = mysqli_fetch_array($rows, MYSQLI_ASSOC)) {
				$href = $siteURLSSL . "leads/supplier_";
				if(array_key_exists('company', $row) && $row['company']) {
					$href .= 'edit/' . $row['record_num'] .'/';
					$company = $row['company'];
				}
				else {
					$href .= 'parent_edit/' . $row['record_num'] .'/';
					$company = $row['parentName'] . " (parent)";
				}

				$body .= "<a href='{$href}'>{$company}</a><br>";
			}
		}

		$ticket = $client->tickets()->create([
			'tags' => ['electrical-license-issue', $state],
			'subject' => $subject,
			'comment' => [
				'html_body' => $body,
				'public' => false
			],
			'priority' => 'normal',
			'assignee_id' => $agent_id
		]);				
	}

?>
