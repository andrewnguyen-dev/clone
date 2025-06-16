<?php
	require_once("libs/phpdotenv.phar");
	$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
	$dotenv->load();
	
    require_once("config.php");
    require_once("database.php");
	require_once("dispatchv2.php");		
    require_once("lead.php");
    require_once("geo.php");
    require_once("jsonRPCClient.php");
    require_once("sendGrid.php");
    require_once("getResponse.php");
	require_once("brevo.php");
    require_once('getresponse/GetResponse.php');
    require_once("libs/mail/symfony-7.0.3.phar");
	require_once("libs/mail/smtpApiHeader.php");
	require_once("trustPilot.php");
	require_once("emailTemplate.php");
    require_once("supplierTrials.php");
    require_once("basecamp.php");
	require_once("firebase.php");
	require_once("libs/zendesk_api.phar");

	use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
	use Symfony\Component\Mailer\Mailer;
	use Symfony\Component\Mime\Email;
	use Symfony\Component\Mime\Address as MimeAddress;
	use Symfony\Component\Mime\Part\DataPart;
	use Symfony\Component\Mime\Part\File as MimeFile;

	function errorHandler($errno, $errstr='', $errfile='', $errline='') {
		global $techEmail, $techName, $debugEnabled;

		echo $errno . '<br />';
		echo $errstr . '<br />';
		echo $errfile . '<br />';
		echo $errline . '<br />';
		
	    // if error has been supressed with an @
	    if (error_reporting() == 0) {
	        return;
	    }

	    // check if function has been called by an exception
	    if(func_num_args() == 5) {
	        // called by trigger_error()
	        $exception = null;
	        list($errno, $errstr, $errfile, $errline) = func_get_args();

	        $backtrace = array_reverse(debug_backtrace());
	    } else {
	        // caught exception
	        $exc = func_get_arg(0);
	        $errno = $exc->getCode();
	        $errstr = $exc->getMessage();
	        $errfile = $exc->getFile();
	        $errline = $exc->getLine();

	        $backtrace = $exc->getTrace();
	    }

	    $errorType = array (
	               E_ERROR            	=> 'ERROR',
	               E_WARNING        	=> 'WARNING',
	               E_PARSE          	=> 'PARSING ERROR',
	               E_NOTICE         	=> 'NOTICE',
	               E_CORE_ERROR     	=> 'CORE ERROR',
	               E_CORE_WARNING   	=> 'CORE WARNING',
	               E_COMPILE_ERROR  	=> 'COMPILE ERROR',
	               E_COMPILE_WARNING 	=> 'COMPILE WARNING',
	               E_USER_ERROR     	=> 'USER ERROR',
	               E_USER_WARNING   	=> 'USER WARNING',
	               E_USER_NOTICE    	=> 'USER NOTICE',
	               E_STRICT         	=> 'STRICT NOTICE',
	               E_RECOVERABLE_ERROR  => 'RECOVERABLE ERROR'
	               );

	    // create error message
	    if (array_key_exists($errno, $errorType)) {
	        $err = $errorType[$errno];
	    } else {
	        $err = 'CAUGHT EXCEPTION';
	    }

	    $errMsg = "$err: $errstr in $errfile on line $errline";
	    $errorText = $errMsg;

	    // display error msg, if debug is enabled
	    if($debugEnabled == 1) {
	        echo '<h2>Debug Msg</h2>' . nl2br($errMsg) . '<br />Trace: ' . nl2br($trace) . '<br />';
	    }

	    // what to do
	    switch ($errno) {
	        case E_NOTICE:
	        case E_USER_NOTICE:
	            return;
	            break;

	        default:            
	            if($debugEnabled == 0) {
	                // send email to admin
	                SendMail($techEmail, $techName, 'Critical error on ' . $_SERVER['HTTP_HOST'], $errorText);

	                // end and display error msg
	                exit('We are sorry but an error has occurred.  The IT department has been notified.');
	            }
	            else
	                exit('<p>aborting.</p>');
	            break;

	    }
	}
	
	function ParseStringForSEO($input) {
		// Needs to be case sensitive so that when doing the find / replace, the case on the underlying text remains the same
		$find = array("solar power", "Solar power", "solar Power", "Solar Power", "solar energy", "Solar energy", "solar Energy", "Solar Energy", "solar electricity", "Solar electricity", "solar Electricity", "Solar Electricity", "solar panels", "Solar panels", "solar Panels", "Solar Panels");		
		$output = $input;
		
		$startTag = '<a href="/">';
		$endTag = '</a>';
		
		// Execute the replace now
		foreach ($find as $keyword)
			$output = str_replace($keyword, $startTag . $keyword . $endTag, $output);
		
		return $output;
	}
	
	function shortenURL($URL) {
		global $tinyurl_token, $tinyurl_domain;
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => 'https://api.tinyurl.com/create',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => '{"url": "' . $URL . '","domain": "'.$tinyurl_domain.'"}',
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer ' . $tinyurl_token,
				'Content-Type: application/json'
			],
		]);
		$response = curl_exec($curl);
		curl_close($curl);
		if($response !== false) {
			$json = @json_decode($response, true);
			if(isset($json['data']['tiny_url'])) {
				return $json['data']['tiny_url'];
			}
		}
		return $URL;
	}
	
	function DuplicateLeadProcessing($l_record_num) {
		global $_connection;
		$leadData = loadLeadData($l_record_num);
		
		extract($leadData, EXTR_PREFIX_ALL, 'l');
		
		$l_fName = mysqli_real_escape_string($_connection, strtolower($l_fName));
		$l_lName = mysqli_real_escape_string($_connection, strtolower($l_lName));
		$l_email = mysqli_real_escape_string($_connection, strtolower($l_email));
		
		$SQL = "SELECT COUNT(*) FROM leads ";
		$SQL .= "WHERE LOWER(fName) = '{$l_fName}' AND LOWER(lName) = '{$l_lName}' ";
		$SQL .= "AND LOWER(email) = '{$l_email}' AND iPostcode = {$l_iPostcode} ";
		$SQL .= "AND submitted >= DATE_SUB(CURDATE(),INTERVAL 1 MONTH) AND record_num != {$l_record_num}";
		
		if (db_getVal($SQL) > 0) {
			// Duplicate found, update accordingly
			$SQL = "UPDATE leads SET status = 'duplicate' WHERE record_num = {$l_record_num}";
			db_query($SQL);
		}
	}


    function GenerateLeadCSV($leadID, $supplierID, $info = []) {
        global $leadcsvdir;

        // Check if it's an EV lead
	    if(stripos($info['Lead Type'], 'EV Charger') !== false) {
	    	return GenerateEVLeadCSV($leadID, $supplierID, $info);
	    }

        $result = db_query("SELECT * FROM leads WHERE record_num = '{$leadID}'");
        $resultRow = mysqli_fetch_array($result, MYSQLI_ASSOC);

        $fileName = $leadcsvdir . '/lead_' . $supplierID . '_' . $leadID . '.csv';

        // Open the file
        $file = fopen($fileName, 'w');

        if ($resultRow) {
            extract($resultRow, EXTR_PREFIX_ALL, 'l');
            $quoteDetails = unserialize(base64_decode($resultRow['quoteDetails']));
            $rebateDetails = unserialize(base64_decode($resultRow['rebateDetails']));
            $siteDetails = unserialize(base64_decode($resultRow['siteDetails']));
            $systemDetails = unserialize(base64_decode($resultRow['systemDetails']));
			if(array_key_exists('specificQuoteRequestAppend', $info) && $info['specificQuoteRequestAppend'] !== false) {
				if(array_key_exists('Features:', $systemDetails))
					$systemDetails['Features:'] .= PHP_EOL . $info['specificQuoteRequestAppend'];
				else
					$systemDetails['Features:'] = $info['specificQuoteRequestAppend'];
			}

            // Write header line
            fwrite ($file, "Lead Ref,Requested Quotes,Timeframe,Available For Chat,Home Visit,Quarterly Bill,");
            if($resultRow['leadType'] == 'Commercial') {
                fwrite ($file, "Electricity Bill per month,");
            }
            fwrite ($file, "First Name,Last Name,Company,Email,Phone,Address Line 1,Address Line 2,");
            fwrite ($file, "City,State,Postcode,Country,Type of Roof,");
            if(isset($siteDetails['Other:'])) {
                fwrite ($file, "Type of Roof (Other),");
            }
            fwrite ($file, "Storeys,Anything Else,System Size,Roof Location,");
            fwrite ($file, array_key_exists("Lead Type", $info) ? "Lead Type," : "");
            fwrite ($file, array_key_exists("Price Type:", $quoteDetails) ? "Price Type, Features" : "Features");
            fwrite ($file, "\n");

            // Primary output
            fwrite($file, $leadID);
			fwrite($file, ',"' . $l_requestedQuotes .'"');
            fwrite($file, ',"' . (isset($quoteDetails['Timeframe for purchase:']) ? $quoteDetails['Timeframe for purchase:'] : '') . '"');
            fwrite($file, ',"' . (isset($quoteDetails['Available for a conversation:']) ? $quoteDetails['Available for a conversation:'] : '') . '"');
            fwrite($file, ',"' . (isset($quoteDetails['Asked for home visit?']) ? $quoteDetails['Asked for home visit?'] : '') . '"');
            fwrite($file, ',"' . (isset($quoteDetails['Quarterly Bill:']) ? $quoteDetails['Quarterly Bill:'] : '') . '"');

            if($resultRow['leadType'] == 'Commercial') {
                fwrite($file, ',"' . (isset($quoteDetails['Electricity Bill per month:']) ? $quoteDetails['Electricity Bill per month:'] : '') . '"');
            }

            fwrite($file, ',"' . $l_fName .'"');
            fwrite($file, ',"' . $l_lName .'"');
            fwrite($file, ',"' . $l_company .'"');
            fwrite($file, ',"' . $l_email .'"');
            fwrite($file, ',"' . $l_phone .'"');
            fwrite($file, ',"' . $l_iAddress .'"');
            fwrite($file, ',"' . $l_iAddress2 .'"');
            fwrite($file, ',"' . $l_iCity .'"');
            fwrite($file, ',"' . $l_iState .'"');
            fwrite($file, ',"' . $l_iPostcode .'"');
            fwrite($file, ',"' . $l_iCountry .'"');


            fwrite($file, ',"' . (isset($siteDetails['Type of Roof:']) ? $siteDetails['Type of Roof:'] : '') . '"');
            if(isset($siteDetails['Other:'])) {
                fwrite($file, ',"' . $siteDetails['Other:'] . '"');
            }
            fwrite($file, ',"' . (isset($siteDetails['How many storeys?']) ? $siteDetails['How many storeys?'] : '') . '"');
            fwrite($file, ',"' . (isset($siteDetails['Anything Else:']) ? $siteDetails['Anything Else:'] : '') . '"');

            fwrite($file, ',"' . (isset($systemDetails['System Size:']) ? $systemDetails['System Size:'] : '') . '"');
            #Roof Location
            if ($l_mapStatus == 'noGeoCode') {
                fwrite($file, ',"' . "We were unable to map the installation address for the lead." . '"');
            } elseif ($l_mapStatus == 'userNotFound') {
                fwrite($file, ',"' . "The user was unable to find their location on a map." . '"');
            } else {
                fwrite($file, ',"' . "http://maps.google.com/maps?q={$l_latitude},{$l_longitude}&t=h&z=18" . '"');
            }
            if(array_key_exists("Lead Type", $info))
                fwrite($file, ',"' . $info["Lead Type"] . '"');
            if(array_key_exists("Price Type:", $quoteDetails))
                fwrite($file, ',"' . $quoteDetails["Price Type:"] . '"');

            fwrite($file, ',"' . (isset($systemDetails['Features:']) ? $systemDetails['Features:'] : '') . '"');

            // New line
            fwrite($file, "\n");
        }

        // Close the file
        fclose($file);

        return $fileName;
    }

	function GenerateEVLeadCSV($leadID, $supplierID, $info = []) {
		global $leadcsvdir, $siteURLSSL;

	$result = db_query("SELECT * FROM leads WHERE record_num = '{$leadID}'");
	$resultRow = mysqli_fetch_array($result, MYSQLI_ASSOC);

	$fileName = $leadcsvdir . '/lead_' . $supplierID . '_' . $leadID . '.csv';

		// Fetch the images grouped by type
		$imagesResult = db_query("select image_type, GROUP_CONCAT(image) images from lead_images where lead_id = $leadID  group by image_type;");
		$imageRows = mysqli_fetch_all($imagesResult, MYSQLI_ASSOC);

	// Open the file
	$file = fopen($fileName, 'w');

	if ($resultRow) {
		extract($resultRow, EXTR_PREFIX_ALL, 'l');
		$quoteDetails = unserialize(base64_decode($resultRow['quoteDetails']));
		$rebateDetails = unserialize(base64_decode($resultRow['rebateDetails']));
		$siteDetails = unserialize(base64_decode($resultRow['siteDetails']));
		$systemDetails = unserialize(base64_decode($resultRow['systemDetails']));
		$includeSolar = array_key_exists('Type of Roof:', $siteDetails) && $siteDetails['Type of Roof:'] && $siteDetails['Type of Roof:']!="";
		if($includeSolar) {
			$siteDetails['Existing solar size:'] .= ", but would like a quote" . (
				strpos($siteDetails['Existing solar size:'], "No")===false ? ' for a bigger one' : ''
			);
		}
		// Write header line
		fwrite ($file, "Lead Ref,Requested Quotes,");
		fwrite ($file, "First Name,Last Name,Company,Email,Phone,Address Line 1,Address Line 2,");
		fwrite ($file, "City,State,Postcode,Country,");
		fwrite ($file, "Storeys,Anything Else,Location,");
		fwrite ($file, "Car Make/Model,Solar Size,".($includeSolar?"Roof Type,":"")."Have Battery,Installation Type,Distance charger to switchboard,");
		fwrite ($file, array_key_exists("Price type", $quoteDetails) && $quoteDetails["Price Type:"] != '' ? "Price type, Features" : "Features");
		foreach ($imageRows as $row){
			fwrite($file, ',' . ucfirst($row['image_type']) . ' Images');
		}
		fwrite ($file, "\n");

		// Primary output
		fwrite($file, $leadID);
		fwrite($file, ',"' . $l_requestedQuotes .'"');

		fwrite($file, ',"' . $l_fName .'"');
		fwrite($file, ',"' . $l_lName .'"');
		fwrite($file, ',"' . $l_company .'"');
		fwrite($file, ',"' . $l_email .'"');
		fwrite($file, ',"' . $l_phone .'"');
		fwrite($file, ',"' . $l_iAddress .'"');
		fwrite($file, ',"' . $l_iAddress2 .'"');
		fwrite($file, ',"' . $l_iCity .'"');
		fwrite($file, ',"' . $l_iState .'"');
		fwrite($file, ',"' . $l_iPostcode .'"');
		fwrite($file, ',"' . $l_iCountry .'"');


		fwrite($file, ',"' . (isset($siteDetails['How many storeys?']) ? $siteDetails['How many storeys?'] : '') . '"');
		fwrite($file, ',"' . (isset($siteDetails['Anything Else:']) ? $siteDetails['Anything Else:'] : '') . '"');

		#Roof Location
		if ($l_mapStatus == 'noGeoCode') {
			fwrite($file, ',"' . "We were unable to map the installation address for the lead." . '"');
		} elseif ($l_mapStatus == 'userNotFound') {
			fwrite($file, ',"' . "The user was unable to find their location on a map." . '"');
		} else {
			fwrite($file, ',"' . "http://maps.google.com/maps?q={$l_latitude},{$l_longitude}&t=h&z=18" . '"');
		}
		fwrite($file, ',"' . (isset($siteDetails['Car Make/Model:']) ? $siteDetails['Car Make/Model:'] : '') . '"');
		fwrite($file, ',"' . (isset($siteDetails['Existing solar size:']) ? $siteDetails['Existing solar size:'] : '') . '"');
		if($includeSolar)
			fwrite($file, ',"' . (isset($siteDetails['Type of Roof:']) ? $siteDetails['Type of Roof:'] : '') . '"');
		fwrite($file, ',"' . (isset($siteDetails['Have battery?']) ? $siteDetails['Have battery?'] : '') . '"');
		fwrite($file, ',"' . (isset($siteDetails['EV Installation Type:']) ? $siteDetails['EV Installation Type:'] : '') . '"');
		fwrite($file, ',"' . (isset($siteDetails['Distance between charger and switchboard:']) ? $siteDetails['Distance between charger and switchboard:'] : '') . '"');


		if(array_key_exists("Price Type:", $quoteDetails) && $quoteDetails["Price Type:"] != '')
			fwrite($file, ',"' . $quoteDetails["Price Type:"] . '"');

			fwrite($file, ',"' . (isset($systemDetails['Features:']) ? $systemDetails['Features:'] : '') . '"');


			foreach ($imageRows as $row){
				$images = explode(',', $row['images']);
				$imageString = '';
				foreach($images as $image){
					$imageString .= $siteURLSSL . 'img/quote/ev/' . $image . "\n";
				}

				fwrite($file, ',"' . $imageString . '"');
			}
			// New line
			fwrite($file, "\n");
		}

	// Close the file
	fclose($file);

	return $fileName;
}

	function generateViewInBrowserURL($message) {
		global $nowSql, $_connection;
		$tagToReplace = "{viewInBrowserURL}";
		if(strpos($message, $tagToReplace) === false)
			return $message;    //nothing to do

		$arrayToHide = ['<a href="{viewInBrowserURL}" style="text-decoration:none;color:#2B3864;font-size: 12px;text-align: right;" target="_blank"><span style="color:#2B3864;font-size: 12px;padding-right: 2px;"></span>', 'View this email in browser</a>', "'"];

		$code = md5(uniqid());

		$SQL = "INSERT INTO sent_emails (code, content, created) VALUES ('";
		$SQL .= $code . "', '" . str_replace($arrayToHide, ["", "", "\'"], $message) . "', " . $nowSql . ");";
		
		db_query($SQL);
		$id = mysqli_insert_id($_connection);
		$url = "https://www.solarquotes.com.au/viewemail/?email=".$id."&c=".$code;
		return str_replace($tagToReplace, $url, $message);
	}

    function applyTemplate($template, $data) {
        if($template == null) return '';
        
        foreach ($data as $a => $b) {
            if (!is_array($b)) {
                $template = preg_replace('/({|%7b)' . $a. '(}|%7d)/', $b, $template);        
            } else {
                $template = preg_replace('/<p>(({|%7b)start' . $a. '(}|%7d))/', '\1<p>', $template);
                $template = preg_replace('/(({|%7b)end' . $a. '(}|%7d))</p>/', '</p>\1', $template);
                
                if (preg_match('/({|%7b)start' . $a. '(}|%7d)(.*)({|%7b)end' . $a. '(}|%7d)/', $template, $matches)) {
                    $loopTemplate = $matches[3];
                    $contents = "";
                    
                    foreach ($b as $c) {
                        $contents .= applyTemplate($loopTemplate, $c) . "\n";
                    }
                    
                    $template = preg_replace('/({|%7b)start' . $a. '(}|%7d)(.*)({|%7b)end' . $a. '(}|%7d)/', $contents, $template);          
                }
            }
        }
		$template = generateViewInBrowserURL($template);
        return $template;
    }

    function sendTemplateEmail($email, $name, $messageid, $data, $fromEmail = '', $fromName = '', $mailAccount = 'main') {
        global $adminEmail, $adminName, $emailTemplatePrepend, $emailTemplateFinnSignature, $emailTemplateAppend;
        
        if ($fromEmail == '') $fromEmail = $adminEmail;
        if ($fromName == '') $fromName = $adminName;
        list($subject, $message, $templateFooter, $templateAfterFooterContents, $templateDefaultSignature, $templateSmallTitle, $templateBigTitle) = db_getVal("SELECT subject, contents, template_footer, template_after_footer_contents, template_default_signature, template_small_title, template_big_title FROM emails WHERE code='{$messageid}'");
		if($templateDefaultSignature=='Y')
			$message = $emailTemplatePrepend . $message . $emailTemplateFinnSignature . $emailTemplateAppend;
		else
			$message = $emailTemplatePrepend . $message . $emailTemplateAppend;
    	// Check for the existence of the CustomerSpecialInstructions Variable
		if(stripos($message, 'CustomerSpecialInstructions') !== false){
			$siteDetails = unserialize(base64_decode($data['rawsiteDetails']));
			$data['CustomerSpecialInstructions'] = '';
			if($siteDetails['Anything Else:'] != ''){
				if(!(empty($data['fName']))) {
					$siteDetails['Anything Else:'] = str_replace('Lead wants', $data['fName'] .' wants', $siteDetails['Anything Else:']);
					$siteDetails['Anything Else:'] = str_replace("Customer wants", ($data['fName'] ." wants"), $siteDetails['Anything Else:']);
					$siteDetails['Anything Else:'] = str_replace("The customer has explicitly", ($data['fName'] ." has explicitly"), $siteDetails['Anything Else:']);
					$siteDetails['Anything Else:'] = str_replace("This customer has indicated that they would like", ($data['fName'] ." would like"), $siteDetails['Anything Else:']);
				}
				$anythingElse = str_replace('$', '\$', $siteDetails['Anything Else:']);
				$data['CustomerSpecialInstructions'] = '<span style="font-weight: bold; color: #BE1E2D">Please note these are special instructions from '.(empty($data['fName']) ? 'the customer' : $data['fName']).':</span><br />' . nl2br($anythingElse);	
				$data['CustomerSpecialInstructionsBox'] = !empty($anythingElse) ? "<tr><td class=\"void\" style=\"margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-top: 30px;padding-bottom: 30px;padding-right: 25px;padding-left:25px;font-size:14px;font-family: Helvetica, Arial, sans-serif !important;background-color: #F3F9FF;\"><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\"><tbody><tr><td class=\"void\" width=\"100%\" style=\"margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-bottom: 7px;padding-right: 0;padding-left: 0;font-size:16px;font-family: Helvetica, Arial, sans-serif !important;font-weight: regular;\"><span style=\"font-weight: bold; color: #BE1E2D\">Please note these are special instructions from ".(empty($data['fName']) ? "the customer" : $data['fName']).":</span><br />" . nl2br($anythingElse) . "</td></tr></tbody></table></td></tr>" : "";  
				$data['anythingElseWithLabel'] = !empty($anythingElse) ? "<p><b>Special instructions from ".(empty($data['fName']) ? "the customer" : $data['fName']).":</b> ".nl2br($anythingElse)."</p>" : "";
			}
			unset($siteDetails['Anything Else:']);
			$data['rawsiteDetails'] = base64_encode(serialize($siteDetails));
			$data['siteDetails'] = decodeArray($data['rawsiteDetails']);
            $data['siteDetailsCells'] = decodeArrayTemplateCells($data['rawsiteDetails']);
			$defaultToBlank = ['CustomerSpecialInstructionsBox', 'anythingElseWithLabel'];
			foreach($defaultToBlank as $k)
				if(!array_key_exists($k, $data))
					$data[$k] = '';
		}
		$data['currentDate'] = date('Y-m-d', time());
        $templateFooter = applyTemplate($templateFooter, $data);
        $templateSmallTitle = applyTemplate($templateSmallTitle, $data);
        $templateBigTitle = applyTemplate($templateBigTitle, $data);
		$subject = applyTemplate($subject, $data);
        $data['templateFooter'] = $templateFooter;
        $data['templateSmallTitle'] = strlen($templateSmallTitle)>2 ? $templateSmallTitle : 'SOLARQUOTES<strong>.</strong>COM<strong>.</strong>AU';
        $data['templateBigTitle'] = strlen($templateBigTitle)>2 ? $templateBigTitle : $subject;
        $data['templateAfterFooterContents'] = str_replace("$", "\\$", applyTemplate($templateAfterFooterContents, $data));
	
	    // Changing EV Installation Type wording
        if (!empty($data['siteDetailsCells'])) {
            if (stripos($data['siteDetailsCells'], 'EV Installation Type') !== false) {
                $data['siteDetailsCells'] = str_ireplace('EV Installation Type', 'Lead Already Has Charger', $data['siteDetailsCells']);
                $data['siteDetailsCells'] = str_ireplace('I need quotes for an EV charger plus its installation', 'No, it needs to be supplied', $data['siteDetailsCells']);
                $data['siteDetailsCells'] = str_ireplace('Existing Charger Brand:', 'Yes, brand: ', $data['siteDetailsCells']);
            }
        }
        if (!empty($data['installationType'])) {
            $data['installationType'] = str_ireplace('I need quotes for an EV charger plus its installation', 'No, it needs to be supplied', $data['installationType']);
            $data['installationType'] = str_ireplace('Existing Charger Brand:', 'Yes, brand: ', $data['installationType']);
        }
	
        $message = applyTemplate($message, $data);

		SendMail($email, '', $subject, $message, $fromEmail, $fromName, false, $mailAccount);
    }
    
    function SendMail($email, $name, $subject, $body, $fromEmail = '', $fromName = '', $applyBasicTemplate = true, $mailAccount = 'main') {
        // applyBasicTemplate argument can be false (don't apply), true (apply) and array: [smallTitle, bigTitle]
        global $adminEmail, $adminName, $sm_user, $sm_pass, $sm_host, $sm_port, $emailTemplatePrepend, $emailTemplateFinnSignature, $emailTemplateAppend, $emailTemplateWidePrepend, $sg_keys, $techEmail, $techName, $emailSendingEnabled, $overrideEmailTo;

        if ($fromEmail == '') $fromEmail = $adminEmail;
        if ($fromName == '') $fromName = $adminName;
        
        if ($email == '')
        	return;
		
		if(!is_null($overrideEmailTo)) {
			$email = $overrideEmailTo;
		}

		if ($mailAccount == '') $mailAccount = 'main';

		if (!isset($sg_keys[$mailAccount])) {
			SendMail($techEmail, $techName, "Invalid SendGrid API Key: \"{$mailAccount}\"", "Invalid SendGrid API Key: \"{$mailAccount}\"", '', '', '', 'main');
			error_log(new Exception("Invalid SendGrid API Key: \"{$mailAccount}\""));
			$mailAccount = 'main';
		}

        // Setup the from and to
        $from = new MimeAddress($fromEmail, $fromName);
		$to = new MimeAddress($email, $name);
        
		// Setup Symfony mailer parameters
		$transport = new EsmtpTransport(host: $sm_host, port: $sm_port);
		$transport->setUsername($sm_user);
		$transport->setPassword($sg_keys[$mailAccount]);
		$mailer = new Mailer($transport);

        // apply the basic template? (frame and colours)
        if($applyBasicTemplate !== false && strpos($email, "@sms.utbox.net")===false) {
            if(is_array($applyBasicTemplate) && array_key_exists('emailTemplate',$applyBasicTemplate)){
                switch (true) {
                    case $applyBasicTemplate['emailTemplate'] === 'wide':
                        $body = $emailTemplateWidePrepend . $body . $emailTemplateAppend;
                        break;
                    default:
                        $body = $emailTemplatePrepend . $body . $emailTemplateAppend;
                }
            } else {
                $body = $emailTemplatePrepend . $body . $emailTemplateAppend;
            }
            $data = ['templateFooter'=>'', 'templateAfterFooterContents'=>''];
            $data['templateSmallTitle'] = (is_array($applyBasicTemplate) && array_key_exists('smallTitle', $applyBasicTemplate)) ? $applyBasicTemplate['smallTitle'] : 'SOLARQUOTES<strong>.</strong>COM<strong>.</strong>AU';
            $data['templateBigTitle'] = (is_array($applyBasicTemplate) && array_key_exists('bigTitle', $applyBasicTemplate)) ? $applyBasicTemplate['bigTitle'] : $subject;
            $body = applyTemplate($body, $data);
        }
		
		// Setup the SendGrid category link
		$hdr = new SmtpApiHeader();
		$hdr->setCategory("SolarQuotes");
	

		$email = (new Email())
			->from($from)
			->to($to)
			->subject($subject)
			->html($body)
			->text(strip_tags($body));
		
		$email->getHeaders()
			->addTextHeader('X-SMTPAPI', $hdr->asJSON());
		
		// Send message 
		if($emailSendingEnabled){
			$mailer->send($email);
		}
    }
    
    function sendMailWithAttachment($email, $name, $messageid, $data, $attachment, $fromEmail = '', $fromName = '', $mailAccount = 'main') {
        global $adminEmail, $adminName, $sm_user, $sm_pass, $sm_host, $sm_port, $emailTemplatePrepend, $emailTemplateFinnSignature, $emailTemplateAppend, $sg_keys, $techEmail, $techName, $emailSendingEnabled, $overrideEmailTo;
        
        if ($fromEmail == '') $fromEmail = $adminEmail;
        if ($fromName == '') $fromName = $adminName;
        
        if ($email == '')
        	return;

		if(!is_null($overrideEmailTo)) {
			$email = $overrideEmailTo;
		}

		if ($mailAccount == '') $mailAccount = 'main';

		if (!isset($sg_keys[$mailAccount])) {
			SendMail($techEmail, $techName, "Invalid SendGrid API Key: \"{$mailAccount}\"", "Invalid SendGrid API Key: \"{$mailAccount}\"", '', '', '', 'main');
			error_log(new Exception("Invalid SendGrid API Key: \"{$mailAccount}\""));
			$mailAccount = 'main';
		}

        list($subject, $messagebody, $templateFooter, $templateAfterFooterContents, $templateDefaultSignature, $templateSmallTitle, $templateBigTitle) = db_getVal("SELECT subject, contents, template_footer, template_after_footer_contents, template_default_signature, template_small_title, template_big_title FROM emails WHERE code='{$messageid}'");
        if($templateDefaultSignature=='Y')
            $messagebody = $emailTemplatePrepend . $messagebody . $emailTemplateFinnSignature . $emailTemplateAppend;
        else
            $messagebody = $emailTemplatePrepend . $messagebody . $emailTemplateAppend;
    	// Check for the existence of the CustomerSpecialInstructions Variable
		if(stripos($messagebody, 'CustomerSpecialInstructions') !== false){
			$siteDetails = unserialize(base64_decode($data['rawsiteDetails']));
			$data['CustomerSpecialInstructions'] = '';
			if($siteDetails['Anything Else:'] != ''){
                if(!(empty($data['fName']))) {
                    $siteDetails['Anything Else:'] = str_replace('Lead wants', $data['fName'] .' wants', $siteDetails['Anything Else:']);
                    $siteDetails['Anything Else:'] = str_replace("Customer wants", ($data['fName'] ." wants"), $siteDetails['Anything Else:']);
                    $siteDetails['Anything Else:'] = str_replace("The customer has explicitly", ($data['fName'] ." has explicitly"), $siteDetails['Anything Else:']);
                    $siteDetails['Anything Else:'] = str_replace("This customer has indicated that they would like", ($data['fName'] ." would like"), $siteDetails['Anything Else:']);
                }
				$anythingElse = str_replace('$', '\$', $siteDetails['Anything Else:']);
				$data['CustomerSpecialInstructions'] = '<span style="font-weight: bold; color: #BE1E2D">Please note these are special instructions from '.(empty($data['fName']) ? 'the customer' : $data['fName']).':</span><br />' . nl2br($anythingElse);	
				$data['CustomerSpecialInstructionsBox'] = !empty($anythingElse) ? "<tr><td class=\"void\" style=\"margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-top: 30px;padding-bottom: 30px;padding-right: 25px;padding-left:25px;font-size:14px;font-family: Helvetica, Arial, sans-serif !important;background-color: #F3F9FF;\"><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\"><tbody><tr><td class=\"void\" width=\"100%\" style=\"margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-bottom: 7px;padding-right: 0;padding-left: 0;font-size:16px;font-family: Helvetica, Arial, sans-serif !important;font-weight: regular;\"><span style=\"font-weight: bold; color: #BE1E2D\">Please note these are special instructions from ".(empty($data['fName']) ? "the customer" : $data['fName']).":</span><br />" . nl2br($anythingElse) . "</td></tr></tbody></table></td></tr>" : "";  
				$data['anythingElseWithLabel'] = !empty($anythingElse) ? "<p><b>Special instructions from ".(empty($data['fName']) ? "the customer" : $data['fName']).":</b> ".nl2br($anythingElse)."</p>" : "";
			}
			unset($siteDetails['Anything Else:']);
			$data['rawsiteDetails'] = base64_encode(serialize($siteDetails));
			$data['siteDetails'] = decodeArray($data['rawsiteDetails']);
            $data['siteDetailsCells'] = decodeArrayTemplateCells($data['rawsiteDetails']);
			$defaultToBlank = ['CustomerSpecialInstructionsBox', 'anythingElseWithLabel'];
			foreach($defaultToBlank as $k)
				if(!array_key_exists($k, $data))
					$data[$k] = '';
		}
		$data['currentDate'] = date('Y-m-d', time());
        $templateFooter = applyTemplate($templateFooter, $data);
        $templateSmallTitle = applyTemplate($templateSmallTitle, $data);
        $templateBigTitle = applyTemplate($templateBigTitle, $data);
        $subject = applyTemplate($subject, $data);
        $data['templateFooter'] = $templateFooter;
        $data['templateSmallTitle'] = strlen($templateSmallTitle)>2 ? $templateSmallTitle : 'SOLARQUOTES<strong>.</strong>COM<strong>.</strong>AU';
        $data['templateBigTitle'] = strlen($templateBigTitle)>2 ? $templateBigTitle : $subject;
        $data['templateAfterFooterContents'] = str_replace("$", "\\$", applyTemplate($templateAfterFooterContents, $data));
        $messagebody = applyTemplate($messagebody, $data);

        // Setup the from and to
        $from = new MimeAddress($fromEmail, $fromName);
		$to = new MimeAddress($email, $name);
        
        // Setup Symfony mailer parameters
		$transport = new EsmtpTransport(host: $sm_host, port: $sm_port);
		$transport->setUsername($sm_user);
		$transport->setPassword($sg_keys[$mailAccount]);
		$mailer = new Mailer($transport);

		// Setup the SendGrid category link
		$hdr = new SmtpApiHeader();
		$hdr->setCategory("SolarQuotes");

		$email = (new Email())
			->from($from)
			->to($to)
			->subject($subject)
			->html($messagebody)
			->text(strip_tags($messagebody))
			->addPart(new DataPart(new MimeFile($attachment)));

		$email->getHeaders()
			->addTextHeader('X-SMTPAPI', $hdr->asJSON());

		
		// Send message 
		if($emailSendingEnabled){
			$mailer->send($email);
		}
	}

    function sendMailNoTemplate($email, $name, $subject, $messagebody, $fromEmail = '', $fromName = '', $mailAccount = 'main') {
        global $adminEmail, $adminName, $sm_user, $sm_pass, $sm_host, $sm_port, $sg_keys, $techEmail, $techName, $emailSendingEnabled, $overrideEmailTo;
        
        if ($fromEmail == '') $fromEmail = $adminEmail;
        if ($fromName == '') $fromName = $adminName;
        
        if ($email == '')
        	return;

		if(!is_null($overrideEmailTo)) {
			$email = $overrideEmailTo;
		}

		if ($mailAccount == '') $mailAccount = 'main';

		if (!isset($sg_keys[$mailAccount])) {
			SendMail($techEmail, $techName, "Invalid SendGrid API Key: \"{$mailAccount}\"", "Invalid SendGrid API Key: \"{$mailAccount}\"", '', '', '', 'main');
			error_log(new Exception("Invalid SendGrid API Key: \"{$mailAccount}\""));
			$mailAccount = 'main';
		}

		// Setup the from and to
		$from = new MimeAddress($fromEmail, $fromName);
		$to = new MimeAddress($email, $name);
		
		// Setup Symfony mailer parameters
		$transport = new EsmtpTransport(host: $sm_host, port: $sm_port);
		$transport->setUsername($sm_user);
		$transport->setPassword($sg_keys[$mailAccount]);
		$mailer = new Mailer($transport);

		// Setup the SendGrid category link
		$hdr = new SmtpApiHeader();
		$hdr->setCategory("SolarQuotes");

		$email = (new Email())
			->from($from)
			->to($to)
			->subject($subject)
			->html($messagebody)
			->text(strip_tags($messagebody));

		$email->getHeaders()
			->addTextHeader('X-SMTPAPI', $hdr->asJSON());
	
		
		// Send message 
		if($emailSendingEnabled){
			$mailer->send($email);
		}
	}
	
	function sendMailWithAttachmentNoTemplate($email, $name, $subject, $messagebody, $attachment, $fromEmail = '', $fromName = '', $mailAccount = 'main') {
        global $adminEmail, $adminName, $sm_user, $sm_pass, $sm_host, $sm_port, $sg_keys, $techEmail, $techName, $emailSendingEnabled, $overrideEmailTo;
        
        if ($fromEmail == '') $fromEmail = $adminEmail;
        if ($fromName == '') $fromName = $adminName;
        
        if ($email == '')
        	return;

		if(!is_null($overrideEmailTo)) {
			$email = $overrideEmailTo;
		}

		if ($mailAccount == '') $mailAccount = 'main';

		if (!isset($sg_keys[$mailAccount])) {
			SendMail($techEmail, $techName, "Invalid SendGrid API Key: \"{$mailAccount}\"", "Invalid SendGrid API Key: \"{$mailAccount}\"", '', '', '', 'main');
			error_log(new Exception("Invalid SendGrid API Key: \"{$mailAccount}\""));
			$mailAccount = 'main';
		}

		// Setup the from and to
        $from = new MimeAddress($fromEmail, $fromName);
		$to = new MimeAddress($email, $name);
        
        // Setup Symfony mailer parameters
		$transport = new EsmtpTransport(host: $sm_host, port: $sm_port);
		$transport->setUsername($sm_user);
		$transport->setPassword($sg_keys[$mailAccount]);
		$mailer = new Mailer($transport);

		// Setup the SendGrid category link
		$hdr = new SmtpApiHeader();
		$hdr->setCategory("SolarQuotes");

		$email = (new Email())
			->from($from)
			->to($to)
			->subject($subject)
			->html($messagebody)
			->text(strip_tags($messagebody))
			->addPart(new DataPart(new MimeFile($attachment)));

		$email->getHeaders()
			->addTextHeader('X-SMTPAPI', $hdr->asJSON());
        
		
		// Send message 
		if($emailSendingEnabled){
			$mailer->send($email);
		}
	}

	function sendMailWithAttachmentDefaultTemplate($email, $name, $subject, $messagebody, $attachment, $fromEmail = '', $fromName = '', $mailAccount = 'main') {
		global $adminEmail, $adminName, $sm_user, $sm_pass, $sm_host, $sm_port,$emailTemplatePrepend, $emailTemplateFinnSignature, $emailTemplateAppend, $techEmail, $techName, $siteURLSSL, $sg_keys, $emailSendingEnabled, $overrideEmailTo;
		
		if ($fromEmail == '') $fromEmail = $adminEmail;
		if ($fromName == '') $fromName = $adminName;
		
		if ($email == '')
			return;

		if(!is_null($overrideEmailTo)) {
			$email = $overrideEmailTo;
		}

		if ($mailAccount == '') $mailAccount = 'main';

		if (!isset($sg_keys[$mailAccount])) {
			SendMail($techEmail, $techName, "Invalid SendGrid API Key: \"{$mailAccount}\"", "Invalid SendGrid API Key: \"{$mailAccount}\"", '', '', '', 'main');
			error_log(new Exception("Invalid SendGrid API Key: \"{$mailAccount}\""));
			$mailAccount = 'main';
		}

		// Setup the from and to
		$from = new MimeAddress($fromEmail, $fromName);
		$to = new MimeAddress($email, $name);

		$data['currentDate'] = date('Y-m-d', time());
		$data['templateFooter'] = '';
		$data['templateAfterFooterContents'] = '';
		$data['templateSmallTitle'] = 'SOLARQUOTES<strong>.</strong>COM<strong>.</strong>AU';
		$data['templateBigTitle'] = applyTemplate($subject, $data);
		
		$messagebody = $emailTemplatePrepend . $messagebody . $emailTemplateAppend;
		$messagebody = applyTemplate($messagebody, $data);

		// Setup Symfony mailer parameters
		$transport = new EsmtpTransport(host: $sm_host, port: $sm_port);
		$transport->setUsername($sm_user);
		$transport->setPassword($sg_keys[$mailAccount]);
		$mailer = new Mailer($transport);

		// Setup the SendGrid category link
		$hdr = new SmtpApiHeader();
		$hdr->setCategory("SolarQuotes");

		$email = (new Email())
			->from($from)
			->to($to)
			->subject($subject)
			->html($messagebody)
			->text(strip_tags($messagebody))
			->addPart(new DataPart(new MimeFile($attachment)));

		$email->getHeaders()
			->addTextHeader('X-SMTPAPI', $hdr->asJSON());
		
		// Send message 
		if($emailSendingEnabled){
			$mailer->send($email);
		}
	}

    function sendSMS($number, $sms, $from = false) {
        global $twilioAccountSID, $twilioAuthToken, $twilioNumber, $smsSendingEnabled, $overrideSMSTo, $techEmail, $techName;

        if(!$from) $from = $twilioNumber;

        if(!is_null($overrideSMSTo)) {
            $number = $overrideSMSTo;
        }

        $fields = [
            'To' =>  $number,
            'From' => $from,
            'Body' => $sms
        ];

        if(!$smsSendingEnabled) {
            // TODO: return a fake success message (for testing)
            return "{}";
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.twilio.com/2010-04-01/Accounts/$twilioAccountSID/Messages.json",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($fields, '', '&'),
            CURLOPT_HTTPHEADER => array(
                "Authorization: Basic " .base64_encode("$twilioAccountSID:$twilioAuthToken")
            ),
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
            return false;
        } else {
            return $response;
        }
    }
    
    function Encrypt($message){
		global $surveyEncryptionKey, $encryptionMethod;
		$ivsize = openssl_cipher_iv_length($encryptionMethod);
		$iv = openssl_random_pseudo_bytes($ivsize);

		$ciphertext = openssl_encrypt(
			$message,
			$encryptionMethod,
			$surveyEncryptionKey,
			OPENSSL_RAW_DATA,
			$iv
		);

		return trim(EncryptB64($iv . $ciphertext));
	}

	function EncryptB64($string) {
		$data = base64_encode($string);
		$data = str_replace(['+', '/', '='], ['-', '_', ''], $data);

		return $data;
	}

	function Decrypt($message){
		global $surveyEncryptionKey, $encryptionMethod;
		$message = DecryptB64($message);
		$ivsize = openssl_cipher_iv_length($encryptionMethod);
		$iv = mb_substr($message, 0, $ivsize, '8bit');
		$ciphertext = mb_substr($message, $ivsize, null, '8bit');

		return openssl_decrypt(
			$ciphertext,
			$encryptionMethod,
			$surveyEncryptionKey,
			OPENSSL_RAW_DATA,
			$iv
		);
	}

	function DecryptB64($string) {
		$data = str_replace(['-', '_'], ['+', '/'], $string);
		$mod4 = strlen($data) % 4;

		if ($mod4)
			$data .= substr('====', $mod4);

		return base64_decode($data);
	}
    
    function monthsDifference($date1, $date2) {
		$d1 = strtotime($date1);
		$d2 = strtotime($date2);

		$y1 = date('Y', $d1);
		$y2 = date('Y', $d2);
		$m1 = date('n', $d1);
		$m2 = date('n', $d2);

		$month_diff = ($y2 - $y1) * 12 + ($m2 - $m1);

		return $month_diff;		
    }
    
    function logAccounts($supplierID, $description) {
        global $nowSql;

        $SQL = "INSERT INTO log_accounts (supplier, description, submitted, cronInclude, cronSent) VALUES (";
        $SQL .= $supplierID . ", '" . $description . "', " . $nowSql . ", 'N', 'N');";
        
        db_query($SQL);
    }
    
    function decodeArray($data) {
        $data = unserialize(base64_decode($data));
        $arrayFieldsToSkip = ['Do you own the roofspace?','At least 10 square metres North-facing?','Exact direction:','Supplier preference size:'];
        
        foreach ($data as $a => $b) {
            if(in_array($a, $arrayFieldsToSkip))
                unset($data[$a]);
            elseif(!empty($b)) {
                if (strpos($b, "\n") === false) {
                    $data[$a] = "<b>" . htmlentities($a) . "</b> " . str_replace('$', '\$', $b);
                } else {
                    $data[$a] = "<b>" . htmlentities($a) . "</b><br />" . nl2br(str_replace('$', '\$', $b));
                }
            }
        }

        return join("<br />", $data);
    }

	function decodeArrayTemplateCells($data) {
		$data = unserialize(base64_decode($data));
        $arrayFieldsToSkip = ['Do you own the roofspace?','At least 10 square metres North-facing?','Exact direction:','Supplier preference size:'];
		$html = "";
		foreach ($data as $a => $b) {
			if(!empty($b) && !in_array($a, $arrayFieldsToSkip))
				$html .= "<tr>
					<td class=\"void responsive\" width=\"50%\" style=\"margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-bottom: 10px;padding-right: 0;padding-left: 0;font-size:14px;font-family: Helvetica, Arial, sans-serif !important;font-weight:bold;\">".htmlentities($a)."</td>
					<td class=\"void responsive\" width=\"50%\" style=\"margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-bottom: 10px;padding-right: 0;padding-left: 0;font-size:14px;font-family: Helvetica, Arial, sans-serif !important;\">".
                    nl2br(str_replace('$', '\$', $b))."</td></tr>";
		}
		return $html;
	}

    function loadSupplierData($supplierId) {
        $r = db_query("SELECT * FROM suppliers WHERE record_num='{$supplierId}' LIMIT 1");
        $raw = mysqli_fetch_array($r, MYSQLI_ASSOC);
        $data = htmlentitiesRecursive($raw);
        extract($data, EXTR_PREFIX_ALL, 's');
        
        $data['country'] = $s_country = 'Australia';
        
        $s_fullAddress = array();
        $s_fullAddress[] = $s_address;
        if ($s_address2 != '') $s_fullAddress[] = $s_address2;
        $s_fullAddress[] = "{$s_city} {$s_state} {$s_postcode}";
        $s_fullAddress[] = $s_country;
        $data['fullAddress'] = join('<br />', $s_fullAddress);
        
        $data2 = array();
        foreach ($data as $a => $b) $data2['s' . ucfirst($a)] = $b;
        $data = $data2;
        
        $data['supplierId'] = sprintf('%03u', $s_record_num);
        $data['sFName'] = trim($data['sFName']);
        
        return $data;
    }
    
    function loadSupplierParentData($parentId) {
		$r = db_query("SELECT * FROM suppliers_parent WHERE record_num='{$parentId}' LIMIT 1");
        $raw = mysqli_fetch_array($r, MYSQLI_ASSOC);
        $data = htmlentitiesRecursive($raw);
        
        return $data;
    }

    function loadUnsignedData($supplierId) {
        $r = db_query("SELECT * FROM suppliers_unsigned WHERE record_num='{$supplierId}' LIMIT 1");
        $raw = mysqli_fetch_array($r, MYSQLI_ASSOC);
        $data = htmlentitiesRecursive($raw);
        extract($data, EXTR_PREFIX_ALL, 's');
        
        $x = explode(' ', trim($s_contact));
        $data['fName'] = trim(array_shift($x));
        $data['lName'] = trim(join(' ', $x));
        
        $url = "http://maps.google.com/maps?q={$s_latitude},{$s_longitude}&t=h&z=18";
        $data['mapLink'] = "<a href=\"{$url}\">{$url}</a>";
        
        $data2 = array();
        foreach ($data as $a => $b) $data2['s' . ucfirst($a)] = $b;
        $data = $data2;
        
        return $data;
    }

	// Conversion of the corresponding CakePHP function to load the affiliates from the database
	function getAffiliates($advancedMatch = false) {
		$query = "SELECT * FROM affiliates";
		if($advancedMatch) {
			$query .= " WHERE utm_source != ' *'";
		}
		$result = db_query($query);
		$affiliates = [];
		while ($row = mysqli_fetch_assoc($result)) {
			$affiliates[$row['name']] = $row;
		}
		if($advancedMatch){
			foreach($affiliates as $key => $affiliate){
				$query = "SELECT * FROM affiliates_advanced_match WHERE affiliate_id = $affiliate[record_num]";
				$result = db_query($query);
				$affiliates[$key]['affiliates_advanced_match'] = [];
				while ($row = mysqli_fetch_assoc($result)) {
					$affiliates[$key]['affiliates_advanced_match'][] = $row;
				}
			}
		}
		return $affiliates;
	}

	function refererCaption($info, $returnAffiliateEntity = false, $affiliates = null) {
        $host = $info->host ?? '';
        $url = $info->url ?? '';
        $query = $info->query ?? '';
        $landingPage = $info->landingPage ?? '';
        $widgetSource = $info->widgetsource ?? '';
    
        if (!$returnAffiliateEntity && $host == '' && $widgetSource == '') {
            return '(None)';
        }

        if(is_null($affiliates) || empty($affiliates)) {
            $affiliates = getAffiliates(true);
        }
    
        foreach ($affiliates as $aff) {
            $aff = json_decode(json_encode($aff));
            if($aff->use_advanced_match){
                $aff->affiliates_advanced_match = json_decode(json_encode($aff->affiliates_advanced_match));
            }
            if ($aff->use_advanced_match != 'Y' || empty($aff->affiliates_advanced_match)) {
                if (stripos($landingPage, 'utm_source=' . $aff->utm_source) !== false) {
                    return $returnAffiliateEntity ? $aff : $aff->name;
                }
            } else { // Advanced matching
                $exp = (stripos($landingPage, 'utm_source=' . $aff->utm_source) !== false ? 'true' : 'false');
                $exp .= ($aff->affiliates_advanced_match[0]->operator == "A" ? " && " : " || ") . "(";
                $openGroups = 1;
                $currentGroup = "";
    
                for ($i = 0; $i < count($aff->affiliates_advanced_match); $i++) {
                    $am = clone $aff->affiliates_advanced_match[$i];
                    $am->operator = ($i == 0) ? "" : ($am->operator == "A" ? " && " : " || ");
    
                    // Determine the haystack
                    switch ($am->condition_type) {
                        case '$host':
                        case '$host !=':
                            $haystack = $host;
                            break;
                        case '$query':
                        case '$query !=':
                            $haystack = $query;
                            break;
                        case '$url':
                        case '$url !=':
                            $haystack = $url;
                            break;
                        case '$landingPage':
                        case '$landingPage !=':
                            $haystack = $landingPage;
                            break;
                        default:
                            $haystack = '';
                            break;
                    }
    
                    $result = (stripos($haystack, $am->value) !== false ? 'true' : 'false');
                    $invert = (strpos($am->condition_type, '!=') !== false);
                    if ($invert) {
                        $result = ($result === 'true' ? 'false' : 'true');
                    }
    
                    if (str_starts_with($am->level_order, $currentGroup)) {
                        $levelInsideCurrentGroup = substr($am->level_order, strlen($currentGroup));
    
                        if (strpos($levelInsideCurrentGroup, "|") === false) {
                            $exp .= $am->operator . $result;
                        } else {
                            $exp .= $am->operator;
                            $enteringNumber = substr_count($levelInsideCurrentGroup, '|');
                            for ($n = 0; $n < $enteringNumber; $n++) {
                                $exp .= "(";
                                $currentGroup .= substr($levelInsideCurrentGroup, 0, strpos($levelInsideCurrentGroup, "|") + 1);
                                $levelInsideCurrentGroup = substr($levelInsideCurrentGroup, strlen($currentGroup));
                            }
                            $exp .= $result;
                        }
                    } else {
                        do {
                            if (substr_count($currentGroup, '|') <= 1) {
                                $currentGroup = "";
                            } else {
                                $currentGroup = substr($currentGroup, 0, -1);
                                $cutPos = strrpos($currentGroup, "|");
                                $currentGroup = substr($currentGroup, 0, $cutPos + 1);
                            }
                            $exp .= ")";
                            $moreToClose = !str_starts_with($am->level_order, $currentGroup);
                        } while ($moreToClose);
                        $i--;
                    }
                }
    
                $parenthesisToClose = substr_count($exp, '(') - substr_count($exp, ')');
                for ($n = 0; $n < $parenthesisToClose; $n++) {
                    $exp .= ")";
                }
    
                if (eval("return " . $exp . ";")) {
                    return $returnAffiliateEntity ? $aff : $aff->name;
                }
            }
        }
    
        if ($returnAffiliateEntity) {
            return false;
        }
    
        if (stripos($host, 'solarquotes.com.au') !== false) {
            return '(None)';
        }
    
        return '(Unknown)';
    }

    function ordinal($num) {
        // Special case "teenth"
        if (($num / 10) % 10 != 1) {
            // Handle 1st, 2nd, 3rd
            switch($num % 10) {
                case 1: return 'st';
                case 2: return 'nd';
                case 3: return 'rd';  
            }
        }
        
        return 'th';
    }

    function lookupState($postCode) {
        global $statePostCodes;
        if (!preg_match('/^[0-9]{4}$/', $postCode)) return false;
        
        foreach ($statePostCodes as $state => $x) {
            if (!is_array($x[0])) $x = array($x);
            
            foreach ($x as $y) if ($postCode >= $y[0] && $postCode <= $y[1]) return $state;
        }
        
        return false;
    }
    
    function sanitizeURL($val) {
    	// Convert to lower case
    	$val = strtolower($val);
    	
    	// Replace invalid strings
		$val = str_replace(" ", "-", $val);
		$val = str_replace("'", "", $val);
		$val = str_replace("&", "", $val);
		$val = str_replace("?", "", $val);
		$val = str_replace("/", "", $val);
		$val = str_replace("\"", "", $val);
		$val = str_replace("+", "", $val);
		$val = str_replace("%", "", $val);
		$val = str_replace(".", "", $val);
		$val = str_replace("quot;", "", $val);
		
		return $val;
    }

    function sanitize($val) {
        $val = str_replace('�', "'", $val);
        $val = str_replace('�', '"', $val);
        $val = str_replace('�', '"', $val);
        $val = str_replace('�', '-', $val);
    
        return $val;
    }

    function formatDateRange($startDateTs, $endDateTs, $short = 0) {
        $longMonth = $short ? 'M' : "F";
        $yearFmt = $short ? '/y' : ', Y';
        
        if ($startDateTs == $endDateTs) {
            $datesFmt = date("{$longMonth} j{$yearFmt}", $startDateTs);
        } elseif (date('Ym', $startDateTs) == date('Ym', $endDateTs)) {
            $datesFmt = date("{$longMonth} j", $startDateTs) . ' - ' . date("j{$yearFmt}", $endDateTs);
        } elseif (date('Y', $startDateTs) == date('Y', $endDateTs)) {
            $datesFmt = date('M j', $startDateTs) . ' - ' . date("M j{$yearFmt}", $endDateTs);
        } else {
            $datesFmt = date("M j{$yearFmt}", $startDateTs) . ' - ' . date("M j{$yearFmt}", $endDateTs);
        }
        
        return $datesFmt;
    }

    function getExcerpt($fullText, $maxLength, $addEllipses = -1) {
        $fullText = preg_replace("[\t\r\n ]+", ' ', strip_tags($fullText));
        $x = split("\n", wordwrap($fullText, $maxLength, "\n", true));
        $excerpt = trim($x[0]);
        if ($addEllipses == 1 || (count($x) > 1 && $addEllipses != 0)) $excerpt = preg_replace("/[^a-z0-9]+$/", '', $excerpt) . '...';
        
        return $excerpt;
    }

    function knatsort(&$input) {
        $keys = array_keys($input);
        natsort($keys);
        $output = array();
        
        foreach ($keys as $k) $output[$k] = $input[$k];
        
        $input = $output;
    }
    
    function createResizedImage($source, $w2, $h2, $forceCrop = 0, $scaleUp = 0) {
        $w1 = $w0 = imagesx($source);
        $h1 = $h0 = imagesy($source);
        
        if (!$scaleUp) {
            $w2 = min($w0, $w2);
            $h2 = min($h0, $h2);
        }
        
        if ($w0/$h0 > $w2/$h2) { // original is too wide
            if ($forceCrop)  $w1 = $h1 * ($w2/$h2);
            else $h2 = $w2 * ($h1/$w1);
        } else { // original is too tall
            if ($forceCrop) $h1 = $w1 * ($h2/$w2);
            else $w2 = $h2 * ($w1/$h1);
        }
        
        $thumb = @imagecreatetruecolor($w2, $h2);
        imagecopyresampled($thumb, $source, 0, 0, round(($w0 - $w1)/2), round(($h0 - $h1)/2), $w2, $h2, $w1, $h1);
        return $thumb;
    }

    function getSortTitle($title, $fieldName, $defaultSortOrder, $extraParams = '') {
        global $phpSelf, $sortField, $sortOrder;
        $fieldName2 = '';
        if (is_array($fieldName)) list($fieldName, $fieldName2) =  $fieldName;
        $url = "{$phpSelf}?";
        if ($extraParams) $url .= "{$extraParams}&";
        $order = $defaultSortOrder ? 1 : 0;
        
        if ($fieldName == $sortField) {
            $order = 1 - $sortOrder;
            if ($order == $defaultSortOrder && $fieldName2) $fieldName = $fieldName2;
            if ($fieldName2) {
                $title = ($sortOrder ? '&uarr;' : '&darr;') . ' ' . $title;
            } else {
                $title .= " " . ($sortOrder ? '&uarr;' : '&darr;');
            }
        } elseif ($fieldName2 && $fieldName2 == $sortField) {
            $order = 1 - $sortOrder;
            if ($order != $defaultSortOrder) $fieldName = $fieldName2;
            $title .= " " . ($sortOrder ? '&uarr;' : '&darr;');
        }
        
        return "<a href=\"{$url}sf={$fieldName}&so={$order}\" class=sorting>{$title}</a>";
    }

    function parseSorting($defaultField, $defaultOrder = 1) {
        global $phpSelf, $sortField, $sortOrder, $sortBy;
        if (isset($_SESSION["sort-{$phpSelf}"])) {
            list($sortField, $sortOrder) = $_SESSION["sort-{$phpSelf}"];
        } else {
            $sortField = $defaultField;
            $sortOrder = $defaultOrder;
        }
        
        if (isset($_GET['sf'])) $sortField = $_GET['sf'];
        if (isset($_GET['so'])) $sortOrder = $_GET['so'];
        
        $sortBy = $sortField . ($sortOrder?' ASC':' DESC');
        $_SESSION["sort-{$phpSelf}"] = array($sortField, $sortOrder);
    }
    
    function userIPAddress() { 
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else 
			$ip = $_SERVER['REMOTE_ADDR'];

		return trim($ip);
    }
    
    function secsToEnglish($seconds) {
        $secondsAbs = abs($seconds);
        if ($secondsAbs < 60) {
            $english = $secondsAbs . " second" . (($secondsAbs != 1)?"s":"");
        } elseif ($secondsAbs < 60*59.5) {
            $min = abs(floor($seconds/60));
            $english = $min . " minute" . (($min != 1)?"s":"");
        } elseif ($secondsAbs < 60*60*36) {
            $hour = abs(floor($seconds/(60*60)));
            $english = $hour . " hour" . (($hour != 1)?"s":"");
        } else {
            $day = abs(floor($seconds/(60*60*24)));
            $english = $day . " day" . (($day != 1)?"s":"");
        }

        return $english;
    }

    function mysqlEscapeRecursive($x) {
    	global $_connection;
        if (is_array($x)) {
            foreach ($x as $a => $b) {
                $x[$a] = mysqlEscapeRecursive($b);
            }
        } else {
            $x = mysqli_escape_string($_connection, $x);
        }
        return $x;
    }
    
    function stripSlashesRecursive($x) {
        if (is_array($x)) {
            foreach ($x as $a => $b) {
                $x[$a] = stripSlashesRecursive($b);
            }
        } else {
            $x = stripslashes($x);
        }
        return $x;
    }

    function htmlentitiesRecursive($x, $allowSymbols = 0) {
        if (is_array($x)) {
            foreach ($x as $a => $b) {
                $x[$a] = htmlentitiesRecursive($b, $allowSymbols);
            }
        } else {
            $x = $x == null ? '' : htmlentities($x);
            if ($allowSymbols) $x = preg_replace('/&amp;([a-z0-9]{1,10});/', '&\1;', $x);
        }

        return $x;
    }
    
	function flushBuffers() { 
		ob_end_flush(); 
		ob_flush(); 
		flush(); 
		ob_start(); 
	}

	/**
	*  Move $cache_table_name records from status="current" to "old" and from "pending" to "current"
	*  in a single transaction. This status mechanism is used by tables `cache_city_pages`, `cache_percentiles`, etc.
	**/
	function updateCacheTableRecords($cache_table_name) {
		$SQL = "UPDATE ".$cache_table_name." SET status = ";		
		$SQL .= "(CASE WHEN status = 'current' THEN 'old' WHEN status = 'pending' THEN 'current' END) ";
		$SQL .= "WHERE status != 'old'";
		db_query($SQL);
	}

	function uploadFileToCake($content, $fileName) {
		global $cakePhpWsURL, $cakePhpWsUploadFilesApiKey;

		$endpoint = $cakePhpWsURL . 'upload_file/';
		$jsonBody = ['fileName' => $fileName, 'content' => $content];
		$contentHash = sha1($content);

		// Define the authHeader as a SHA1 hash of the file name, content hash and the API key. 
		// This way even if the Auth header is intercepted, the attacker can't use it to upload files unless they are uploading the exact same file with the exact same content hash.
		$cakephpUploadAuth = "$fileName:$contentHash:$cakePhpWsUploadFilesApiKey";
		$authHeader = sha1(string: $cakephpUploadAuth);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: ' . $authHeader,
			'Content-Type: application/json',
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);

		echo $response . "\n";
		return $response;
	}

	function logLeadsEntry($leadId, $description){
		global $nowSql;
		$query = "INSERT INTO log_leads (lead_id, description, created, updated) VALUES ('{$leadId}', '{$description}', $nowSql, $nowSql)";
        db_query($query);
	}
?>
