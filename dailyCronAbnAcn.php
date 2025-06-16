<?php
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

    // load global libraries
    include('global.php');
    set_time_limit(0);
    $debugging = 0;

	$apiResponses = ['abn'=>[], 'acn'=>[]];

	$count = 0;
	
    foreach(['suppliers', 'suppliers_parent'] as $table) {
    	//get suppliers and parents with abn or acn
	    $SQL =  "SELECT record_num, REPLACE(abn, ' ', '') as abn, REPLACE(acn, ' ', '') as acn, abnStatus, abnDate, abnName, abnNames, abnType, acnStatus, acnDate, acnName, " . (($table == 'suppliers') ? 'company' : 'parentName');
	    $SQL .= " FROM {$table} WHERE (( abn IS NOT NULL AND abn != '' ) OR ( acn IS NOT NULL AND acn != '' )) ORDER BY abn, acn";
	    $rows = db_query($SQL);
	    $updatesQueue = [];

	    while ($row = mysqli_fetch_array($rows, MYSQLI_ASSOC)) {
	    	$count++;
	    	
	        prepareUpdateAbnAcn($row, $table);

	        if ($count >= 50) {
	        	// Force a sleep every 50 records so that we don't spam ASIC silly
				sleep(2);
				$count = 0;
	        }
		}

		if(count($updatesQueue)>0) {
			// Try to optimize updates
			$currentQueue = [];
			foreach($updatesQueue as $q) {
				if(count($currentQueue)==0)
					$currentQueue[] = $q;
				elseif($currentQueue[0]['abn'] == $q['abn'] && $currentQueue[0]['acn'] == $q['acn'])
					$currentQueue[] = $q;
				else { // run the optimized UPDATE for currentQueue and start a new one
					$record_nums = [];
					foreach($currentQueue as $cq)
						$record_nums[] = $cq['record_num'];
					$SQL =  "UPDATE {$table} SET " . implode(", ", $currentQueue[0]['columns']);
					$SQL .= " WHERE record_num IN (". implode(", ", $record_nums) . ");";
					db_query($SQL);
					$currentQueue = [];
					$currentQueue[] = $q;
				}
			}
			//last run to empty currentQueue
			$record_nums = [];
			foreach($currentQueue as $cq)
				$record_nums[] = $cq['record_num'];
			$SQL =  "UPDATE {$table} SET " . implode(", ", $currentQueue[0]['columns']);
			$SQL .= " WHERE record_num IN (". implode(", ", $record_nums) . ");";	
			db_query($SQL);
		}
	}
	
	echo 'Done';

	function prepareUpdateAbnAcn($entity, $table) {
		global $abnAPIGuid, $apiResponses, $updatesQueue, $acnAPI, $_connection, $zenDeskSubdomain, $zenDeskEmail, $zenDeskKey, $siteURLSSL;
		$updateColumns = [];
		$changedColumns = [];
		
		//---- request API using ABN -------
		if($entity['abn'] && $entity['abn']!='') {
			if(array_key_exists($entity['abn'], $apiResponses['abn'])) {
				$response = $apiResponses['abn'][$entity['abn']];	// response for this ABN is "cached"
			}
			else {
				$response = file_get_contents('https://abr.business.gov.au/abrxmlsearch/AbrXmlSearch.asmx/SearchByABNv201408?searchString='.$entity['abn'].'&includeHistoricalDetails=N&authenticationGuid='.$abnAPIGuid);
				$apiResponses['abn'][$entity['abn']] = $response; 	// to avoid requesting the API for the same ABN
			}
			if($response) {
				$response = (simplexml_load_string($response))->response->businessEntity201408;
				if($response) {
					$rAbn['abnStatus'] = $response->entityStatus->entityStatusCode;
					$rAbn['abnDate'] = $response->entityStatus->effectiveFrom;
					$rAbn['abnNames'] = getAbnNamesArray($response);
					// keep the current abnName if it's still valid (exists in $rAbn['abnNames'])
					$rAbn['abnName'] = in_array($entity['abnName'], $rAbn['abnNames']) ? $entity['abnName'] : false;

					if($rAbn['abnName']===false)
						$rAbn['abnName'] = (($response->mainName) ? $response->mainName->organisationName : false);

					if($rAbn['abnName']===false) {
						$entityName = ($table == 'suppliers' ? $entity['company'] : $entity['parentName']);
						$i=0;

						do{	// test all businessNames looking for a match
							$currentBusinessName = $response->businessName[$i];
							if($currentBusinessName) {
								if(str_replace(" ", "", strtolower($currentBusinessName->organisationName)) == 
									str_replace(" ", "", strtolower($entityName)))
									$rAbn['abnName'] = $currentBusinessName->organisationName;
							}
							$i++;
						} while ($currentBusinessName!==null);

						if($rAbn['abnName']===false) //no perfect match found, just use the first one
							$rAbn['abnName'] = $response->businessName->organisationName;
					}
					$rAbn['abnNames'] = json_encode($rAbn['abnNames']);
					$rAbn['abnType'] = $response->entityType->entityDescription;
					foreach($rAbn as $k=>$v) {
						if($entity[$k] != $v)
							$updateColumns[] = $k . " = '".mysqli_escape_string($_connection, $v)."'";
						if($entity[$k] && $entity[$k] != $v && $k!='abnNames')
							$changedColumns[] = $k . ' changed from ' . $entity[$k] . ' to ' . $v;
					}
				}
			}
		}
		
		//---- Request API using ACN (ASIC)-------
		if($entity['acn'] && $entity['acn']!='') {
			try {
				if(array_key_exists($entity['acn'], $apiResponses['acn'])) {
					$response = $apiResponses['acn'][$entity['acn']];	// response for this ACN is "cached"
				}
				else {			
					$response = acnCall($entity['acn']);
					$apiResponses['acn'][$entity['acn']] = $response;	// to avoid requesting the API for the same ACN
				}
				if($response) {
					$rAcn['acnStatus'] = $response['status'];
					$rAcn['acnDate'] = $response['date'];
					$rAcn['acnName'] = $response['name'];
					foreach($rAcn as $k=>$v) {
						if($entity[$k] != $v)
							$updateColumns[] = $k . " = '".mysqli_escape_string($_connection, $v)."'";
						if($entity[$k] && $entity[$k] != $v)
							$changedColumns[] = $k . ' changed from ' . $entity[$k] . ' to ' . $v;
					}
				}
			} catch( Exception $e) {
				$agent_id = db_getVal('SELECT a.zendeskid FROM settings s INNER JOIN admins a ON s.abn_acn_notification = a.record_num;');
				$company = (array_key_exists('company', $entity) ? $entity['company'] : $entity['parentName']);

				$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
				$client->setAuth('token', $zenDeskKey);

				$subject = 'ACN issue - '. $company;
				$href = $siteURLSSL . "leads/supplier_";
				if(array_key_exists('company', $entity))
					$href .= 'edit/' . $entity['record_num'] .'/';
				else
					$href .= 'parent_edit/' . $entity['record_num'] .'/';

				$body = "<a href='{$href}'>{$company}</a> - ACN ";
				$body .= "<a href='https://connectonline.asic.gov.au/RegistrySearch/faces/landing/panelSearch.jspx?searchText=".$entity['acn']."&searchType=OrgAndBusNm'>".$entity['acn']."</a><br>";
				
				$body .= $e->getMessage();

				$ticket = $client->tickets()->create([
					'tags' => ['acn-issue'],
					'subject' => $subject,
					'comment' => [
						'html_body' => $body,
						'public' => false
					],
					'priority' => 'normal',
					'assignee_id' => $agent_id
				]);					
			}
		}
			
		if(count($updateColumns)>0) {	// Queue
			$updatesQueue[] = [
				'abn' => $entity['abn'],
				'acn' => $entity['acn'],
				'record_num' => $entity['record_num'],
				'columns' => $updateColumns
			];
		}

		
		if(count($changedColumns)>0) {	// Create a new ticket
			$agent_id = db_getVal('SELECT a.zendeskid FROM settings s INNER JOIN admins a ON s.abn_acn_notification = a.record_num;');
			$company = (array_key_exists('company', $entity) ? $entity['company'] : $entity['parentName']);

			$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
			$client->setAuth('token', $zenDeskKey);

			$subject = 'ABN/ACN change - '. $company;
			$href = $siteURLSSL . "leads/supplier_";
			if(array_key_exists('company', $entity))
				$href .= 'edit/' . $entity['record_num'] .'/';
			else
				$href .= 'parent_edit/' . $entity['record_num'] .'/';

			$body = "<a href='{$href}'>{$company}</a><br>";
			$body .= implode(", ", $changedColumns);

			$ticket = $client->tickets()->create([
				'tags' => ['abn-acn-change'],
				'subject' => $subject,
				'comment' => [
					'html_body' => $body,
					'public' => false
				],
				'priority' => 'normal',
				'assignee_id' => $agent_id
			]);	
		}
		
	}

	function getAbnNamesArray($response) {
		$return = [];
		$nodes = ['mainName', 'mainTradingName', 'businessName'];
		foreach($nodes as $n) {
			$loop = true;
			$i = 0;
			do{
				if($response->{$n}[$i])
					$return[] = (string)$response->{$n}[$i]->organisationName;
				else
					$loop = false;
				$i++;
			} while ($loop);
		}
		return $return;
	}

	function acnCall($acn) {
		global $acnAPI;
		$soap_request = '<?xml version="1.0" encoding="UTF-8" standalone="no"?><env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope" xmlns:ns1="uri:v2.external.get.nni.asic.gov.au"><env:Body><uri:request xmlns:uri="uri:v3.external.get.nni.asic.gov.au">
						 <uri1:businessDocumentHeader xmlns:uri1="uri:business.document.header.types.asic.gov.au">
								<uri1:messageType>getNni</uri1:messageType>
								<uri1:messageReferenceNumber>1</uri1:messageReferenceNumber>
								<uri1:messageVersion>3</uri1:messageVersion>
								<uri1:senderId>'.$acnAPI['senderId'].'</uri1:senderId>
								<uri1:senderType>'.$acnAPI['senderType'].'</uri1:senderType>
						 </uri1:businessDocumentHeader>
						 <uri:businessDocumentBody>
								<uri:nniNumber>'.$acn.'</uri:nniNumber>
								<uri:document>
									 <uri:maxDocuments>1</uri:maxDocuments>
								</uri:document>
						 </uri:businessDocumentBody>
					</uri:request></env:Body></env:Envelope>';

		if($acnAPI['isUAT'])
			$url = "https://www.gateway.uat.asic.gov.au:443/gateway/ExternalGetNniNamePortV3";
		else
			$url = "https://www.gateway.asic.gov.au:443/gateway/ExternalGetNniNamePortV3";

		$headers = array(
			"Accept: text/xml",
			"Cache-Control: no-cache",
			"Pragma: no-cache",
			"SOAPAction: ".$url); 

		for($i=0; $i<2; $i++) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_USERPWD, $acnAPI['username'].':'.$acnAPI['password']);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
			curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_request);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT,10);
			 
			$output = curl_exec($ch);
			$raw_response = $output;
			
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			curl_close($ch);

			if($http_code != 0)	//connection ok,
				break;			//don't try again

			sleep(2);	//connection failed, sleep and then retry
		}

		if($http_code != 200)
			throw new Exception("ACN API call - Invalid ACN number - Got HTTP code ".$http_code, 1);

		if(!$output)
			return false;

		$output = simplexml_load_string($output);
		if(!is_object($output))
			return false;

		if(@((string)$output->children('env', true)->Body->children('external.get.nni', true)->children('business.document.header.types', true)->businessDocumentHeader->messageEvents->messageEvent->errorCode) == '0047')
			throw new Exception("Organisation not found", 2);		
		
		$output = $output->children('env', true);

		if(!is_object($output) || !count($output))
			return false;

		$output = $output->Body;

		if(!is_object($output) || !count($output)) 
			return false;

		$output = $output->children('external.get.nni', true);

		if(!is_object($output) || !count($output))
			return false;

		$output = $output->children('external.get.nni', true);

		if(!is_object($output) || !count($output))
			return false;

		$output = $output->businessDocumentBody;
		if(!is_object($output) || !count($output))
			return false;

		$output = $output->children('external.get.nni', true)->nniEntity;
		if(!is_object($output) || !count($output))
			return false;

		$output = $output->children('nni.types', true);
		return array(
			'status'=>(string)$output->status->children('types', true)->description, 
			'date'=>(string)$output->dateRegistered, 
			'name'=>(string)$output->name->children('types', true)->name
		);
	}
?>
