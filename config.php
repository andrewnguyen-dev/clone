<?php

	// ********************************************************************************
	// *** Test Environment Settings
	// ********************************************************************************
	$emailSendingEnabled		= $_ENV['EMAIL_SENDING'] ?? true;
	$smsSendingEnabled			= $_ENV['SMS_SENDING'] ?? true;
	$overrideEmailTo			= $_ENV['OVERRIDE_EMAIL_TO'] ?? null;
	$overrideSMSTo				= $_ENV['OVERRIDE_SMS_TO'] ?? null;

	// ********************************************************************************
	// MySQL Database Settings
	// ********************************************************************************
	// $db_host                  = 'database.sq.oregon.peacockmediagroup';
	$db_host					= $_ENV['DB_HOST'];
	$db_user					= $_ENV['DB_USER'];
	$db_pass					= $_ENV['DB_PASS'];
	$db_name					= $_ENV['DB_NAME'];

	// ********************************************************************************
	// MySQL SQWP Database Settings
	// ********************************************************************************
	$db_sqwp_host				= $_ENV['DB_SQWP_HOST'];
	$db_sqwp_user				= $_ENV['DB_SQWP_USER'];
	$db_sqwp_pass				= $_ENV['DB_SQWP_PASS'];
	$db_sqwp_name				= $_ENV['DB_SQWP_NAME'];

	// ********************************************************************************
	// MySQL Blog Database Settings
	// ********************************************************************************
	$db_blog_host				= $_ENV['DB_BLOG_HOST'];
	$db_blog_user				= $_ENV['DB_BLOG_USER'];
	$db_blog_pas				= $_ENV['DB_BLOG_PASS'];
	$db_blog_name				= $_ENV['DB_BLOG_NAME'];

	// ********************************************************************************
	// *** Cookie Settings
	// ********************************************************************************
	$sessionNam					= 'SolarQuote';
	$cookiePath					= '/';

	// ********************************************************************************
	// *** CakePHP WS Settings
	// ********************************************************************************
	$cakePhpWsURL				= $_ENV['CAKEPHP_WS_URL'];
	$cakePhpWsUploadFilesApiKey	= $_ENV['CAKEPHP_WS_UPLOAD_FILES_API_KEY'];
	$cakePhpWSApiKey			= $_ENV['CAKEPHP_WS_API_KEY'];

	// ********************************************************************************
	// *** SendGrid Settings
	// ********************************************************************************
	$sm_user					= $_ENV['SM_USER'];
	$sm_pass					= $_ENV['SM_PASS'];
	$sm_host					= 'smtp.sendgrid.net';
	$sm_port					= 587;
	$sg_autoresponder_api_key	= $_ENV['SG_AUTORESPONDER_API_KEY'];
	$sg_use_autoresponder		= ($_ENV['SG_USE_AUTORESPONDER'] ?? 0) == 1;

	$sg_keys = [
		'main' => $sm_pass,
		'autoresponder_contacts' => $sg_autoresponder_api_key,
		'autoresponder_mail' => $_ENV['SG_AUTORESPONDER_MAIL_API_KEY'],
		'marketing' => $_ENV['SG_MARKETING_API_KEY'],
		'installers' => $_ENV['SG_INSTALLERS_API_KEY'],
		'main_stats' => $_ENV['SG_MAIN_STATS_API_KEY'], // The main account can use on-behalf-of to get email stats from sub accounts
		'main_contacts' => $_ENV['SG_MAIN_CONTACTS_API_KEY'],
		'affiliates_contacts' => $_ENV['SG_AFFILIATES_CONTACTS_API_KEY'],
		'installers_contacts' => $_ENV['SG_INSTALLERS_CONTACTS_API_KEY'],
		'installer_communications_contacts' => $_ENV['SG_INSTALLER_COMMUNICATIONS_CONTACTS_API_KEY'],
		'affiliates_mail' => $_ENV['SG_AFFILIATES_MAIL_API_KEY']
	];

	// ********************************************************************************
	// *** Google Settings
	// ********************************************************************************
	$googleOAuthApplicationName 		= $_ENV['GOOGLE_OAUTH_APPLICATION_NAME'];
	$incompletesTrackingSpreadsheetId 	= $_ENV['GOOGLE_INCOMPLETES_TRACKING_SPREADSHEET_ID'];
	$googleIntegrationDocumentId 		= $_ENV['GOOGLE_INTEGRATION_DOCUMENT_ID'];
	$googleDecadeReportSpreadsheetId	= $_ENV['GOOGLE_DECADE_REPORT_SPREADSHEET_ID'];

	// ********************************************************************************
	// *** Brevo Settings
	// ********************************************************************************
	$brevo_api					= $_ENV['BREVO_API_KEY'];

	// ********************************************************************************
	// *** OpenWeather API Settings
	// ********************************************************************************
	$openWeatherAPIKey			= $_ENV['OPENWEATHER_API_KEY'];

	// ********************************************************************************
	// *** Tinyurl Settings
	// ********************************************************************************
	$tinyurl_token				= $_ENV['TINYURL_TOKEN'];
	$tinyurl_token_analytics	= $_ENV['TINYURL_TOKEN_ANALYTICS'];
	$tinyurl_domain				= $_ENV['TINYURL_DOMAIN'];

	// ****************************************
	// ** Twilio Keys
	// ****************************************
	$twilioAccountSID				= $_ENV['TWILIO_ACCOUNT_SID'];
	$twilioAuthToken 				= $_ENV['TWILIO_AUTH_TOKEN'];
	$twilioNumber 					= $_ENV['TWILIO_NUMBER'];
	$twilioNumberBulkSMS 			= $_ENV['TWILIO_NUMBER_BULK_SMS'];
	$twilioNumberMissingQuotesSms 	= $_ENV['TWILIO_NUMBER_MISSING_QUOTES_SMS'];


	// ********************************************************************************
	// *** Other Settings
	// ********************************************************************************
	$loginTimeout             	= 180 * 60; // 3-hour timeout
	$claimExpireHours			= 4;
	$publicHolidays				= array( // Format: YYYY-mm-dd
									'2017-02-10',
									'2017-02-09',
									'2017-02-08');

	$firebaseProjectId			= $_ENV['FIREBASE_PROJECT_ID'];
	$firebaseServiceAccountPath	= $_ENV['FIREBASE_SERVICE_ACCOUNT_PATH'];
	$firbaseOverrideUserToken	= $_ENV['FIREBASE_OVERRIDE_FCM_USER_TOKEN'];
	// This is used for the primary site
	$trustPilot					= $_ENV['TRUST_PILOT'];
	// This is used for the book.  Both this and the line above are required
	$trustPilotKey				= $_ENV['TRUST_PILOT_KEY'];
	$trustPilotSecret			= $_ENV['TRUST_PILOT_SECRET'];
	$trustPilotUsername			= $_ENV['TRUST_PILOT_USERNAME'];
	$trustPilotPassword			= $_ENV['TRUST_PILOT_PASSWORD'];

	$siteURL                  	= 'http://www.solarquotes.com.au/';
	$siteURLSSL               	= 'https://www.solarquotes.com.au/';

	$adminName                	= "SolarQuotes";
	$adminEmail               	= "finn@solarquotes.com.au";

	$adminPAName 				= "Robert Moffa";
	$adminPAEmail 				= "robert@solarquotes.com.au";

	$salesName 					= "Finn Peacock";
	$salesEmail 				= "finn@solarquotes.com.au";

	$techName 					= "John Burcher";
	$techEmail 					= $_ENV['TECH_EMAIL'] ?? "finnp@solarquotes.com.au";

	$dispatchName 				= "Finn Peacock";
	$dispatchEmail 				= "finnp@solarquotes.com.au";

	$affiliatesName				= "SolarQuotes";
	$affiliatesEmail			= "affiliates@solarquotes.com.au";

	$leadcsvdir 				= $_ENV['LEAD_CSV_DIR'] ?? "/var/www/private_html_2024/php/temp";
	$phpdir 					= $_ENV['PHP_DIR'] ?? "/var/www/private_html_2024/php";
	$privateHtmlDir 			= $_ENV['PRIVATE_HTML_DIR'] ?? "/var/www/private_html_2024";

	$encryptionMethod			= $_ENV['SURVEY_ENCRYPTION_METHOD'] ?? 'aes-256-cbc';
	$surveyEncryptionKey		= $_ENV['SURVEY_ENCRYPTION_KEY'];

	// ********************************************************************************
	// ** API KEYS & Other Configs
	// ********************************************************************************
	$implixApiKey 				= $_ENV['IMPLIX_API_KEY'];

	$awsKey 					= $_ENV['AWS_KEY'];
	$awsSecret					= $_ENV['AWS_SECRET'];


	// ********************************************************************************
	// ** ZenDesk settings
	// ********************************************************************************
	$zenDeskEmail 				= $_ENV['ZENDESK_EMAIL'];
	$zenDeskKey 				= $_ENV['ZENDESK_KEY'];
	$zenDeskSubdomain 			= $_ENV['ZENDESK_SUBDOMAIN'];
	// List of requester_id of the users that should be assigned to the tickets
	$zenDeskAssignees = array(
		'NoSupplierLeads' => $_ENV['ZENDESK_ASSIGNEE_ID_NOSUPPLIERLEADS'],
	);
	// The target id that should be reset to active every hour by hourlyCronResetZendeskTarget.php
	$zendeskResetTargetId 		= $_ENV['ZENDESK_RESET_TARGET_ID'];

	// ********************************************************************************
	// ** Basecamp authentication
	// ********************************************************************************
	$bcAuthClientID 			= $_ENV['BASECAMP_AUTH_CLIENT_ID'];
	$bcAuthClientSecret 		= $_ENV['BASECAMP_AUTH_CLIENT_SECRET'];
	$bcAuthRedirectURL 			= $_ENV['BASECAMP_AUTH_REDIRECT_URL'];

	
	// ********************************************************************************
	// ** Basecamp settings
	// ********************************************************************************
	$bcAccountID 				= $_ENV['BASECAMP_ACCOUNT_ID'];
	$bcProjectID 				= $_ENV['BASECAMP_PROJECT_ID'];
	$bcAppName 					= $_ENV['BASECAMP_APP_NAME'];
	$bcAppOwner 				= $_ENV['BASECAMP_APP_OWNER'];
	$bcTrialCampfireID 			= $_ENV['BASECAMP_TRIAL_CAMPFIRE_ID'];
	
	// ********************************************************************************
	// ** States and State/Postcode lookup
	// ********************************************************************************
	$states = array(
		'ACT' => 'Australian Capital Territory',
		'NSW' => 'New South Wales',
		'NT'  => 'Northern Territory',
		'QLD' => 'Queensland',
		'SA'  => 'South Australia',
		'TAS' => 'Tasmania',
		'VIC' => 'Victoria',
		'WA'  => 'Western Australia',
	);
	$statePostCodes = array(
		'ACT' => array(array(2600, 2618), array(2900, 2999)),
		'NSW' => array(array(2000, 2599), array(2619, 2899)),
		'NT' => array(800, 999),
		'QLD' => array(4000, 4999),
		'SA' => array(5000, 5999),
		'TAS' => array(7000, 7999),
		'VIC' => array(3000, 3999),
		'WA' => array(6000, 6999)
	);

	// ********************************************************************************
	// ** Timeframes lookup
	// ********************************************************************************
	$timeframe = array(
		'Immediately',
		'In the next 4 weeks',
		'In the next 3 months',
		'In the next 6 months',
		'In the next year',
		'No solid time frame, just looking for a price'
	);

	// ********************************************************************************
	// ** Areas lookup
	// ********************************************************************************
	$areasLatLon = array(
		"canberra" => array(2600, 'ACT', -35.306768, 149.126355, 'Canberra', 40),

		"sydney" => array(2000, 'NSW', -33.867139, 151.207114, 'Sydney', 40),
		"newcastle" => array(2300, 'NSW', -32.926357, 151.78122, 'Newcastle', 40),
		"centralcoast" => array(2259, 'NSW', -33.28348, 151.422404, 'Central Coast', 100),
		"wollongong" => array(2500, 'NSW', -34.425878, 150.899818, 'Wollongong', 40),
		"albury" => array(2640, 'NSW', -36.082137, 146.910174, 'Albury', 100),
		"maitland" => array(2320, 'NSW', -32.734714, 151.558573, 'Maitland', 100),
		"waggawagga" => array(2650, 'NSW', -35.109861, 147.370515, 'Wagga Wagga', 100),
		"portmacquarie" => array(2444, 'NSW', -31.434259, 152.908481, 'Port Macquarie', 100),
		"tamworth" => array(2340, 'NSW', -31.091743, 150.930821, 'Tamworth', 100),
		"orange" => array(2800, 'NSW', -33.276948, 149.099775, 'Orange', 100),
		"dubbo" => array(2830, 'NSW', -32.245192, 148.604212, 'Dubbo', 100),
		"lismore" => array(2480, 'NSW', -28.812725, 153.278721, 'Lismore', 100),
		"bathurst" => array(2795, 'NSW', -33.41978, 149.574258, 'Bathurst', 100),
		"coffsharbour" => array(2450, 'NSW', -30.282279, 153.128593, 'Coffs Harbour', 100),
		"richmond" => array(2753, 'NSW', -33.597753, 150.75289, 'Richmond', 100),
		"nowra" => array(2541, 'NSW', -34.872698, 150.60342, 'Nowra', 100),

		"darwin" => array(800, 'NT', -12.801028, 130.955789, 'Darwin', 40),
		"alicesprings" => array(870, 'NT', -12.436101, 130.84059, 'Alice Springs', 40),

		"brisbane" => array(4000, 'QLD', -27.46758, 153.027892, 'Brisbane', 40),
		"goldcoast" => array(4217, 'QLD', -28.00228, 153.431052, 'Gold Coast', 40),
		"sunshinecoast" => array(4558, 'QLD', -26.652713, 153.08974, 'Sunshine Coast', 40),
		"townsville" => array(4810, 'QLD', -19.267358, 146.80654, 'Townsville', 40),
		"cairns" => array(4870, 'QLD', -16.925397, 145.775178, 'Cairns', 40),
		"toowoomba" => array(4350, 'QLD', -27.561302, 151.955505, 'Toowoomba', 100),
		"rockhampton" => array(4700, 'QLD', -23.378941, 150.512323, 'Rockhampton', 100),
		"mackay" => array(4740, 'QLD', -21.14342, 149.186845, 'Mackay', 100),
		"bundaberg" => array(4670, 'QLD', -24.866109, 152.348847, 'Bundaberg', 100),
		"herveybay" => array(4655, 'QLD', -25.290392, 152.850367, 'Hervey Bay', 100),
		"gladstone" => array(4680, 'QLD', -23.842101, 151.250819, 'Gladstone', 100),

		"adelaide" => array(5000, 'SA', -34.92577, 138.599732, 'Adelaide', 40),
		"mountgambier" => array(5290, 'SA', -37.826321, 140.783303, 'Mount Gambier', 100),

		"hobart" => array(7000, 'TAS', -42.882743, 147.330234, 'Hobart', 40),
		"launceston" => array(7250, 'TAS', -41.440282, 147.139353, 'Launceston', 40),

		"melbourne" => array(3000, 'VIC', -37.814563, 144.970267, 'Melbourne', 40),
		"geelong" => array(3220, 'VIC', -38.14729, 144.360735, 'Geelong', 40),
		"ballarat" => array(3350, 'VIC', -37.563318, 143.863715, 'Ballarat', 100),
		"bendigo" => array(3550, 'VIC', -36.758492, 144.280075, 'Bendigo', 40),
		"shepparton" => array(3630, 'VIC', -36.381171, 145.39929, 'Shepparton', 100),
		"melton" => array(3337, 'VIC', -37.683206, 144.58315, 'Melton', 100),
		"mildura" => array(3500, 'VIC', -34.181714, 142.163072, 'Mildura', 100),
		"warrnambool" => array(3280, 'VIC', -38.383313, 142.482959, 'Warrnambool', 100),
		"sunbury" => array(3429, 'VIC', -37.576859, 144.731425, 'Sunbury', 100),

		"perth" => array(6000, 'WA', -31.924074, 115.91223, 'Perth', 40),
		"rockingham" => array(6168, 'WA', -32.288048, 115.745967, 'Rockingham', 100),
		"mandurah" => array(6210, 'WA', -32.53301, 115.732537, 'Mandurah', 100),
		"bunbury" => array(6230, 'WA', -33.327112, 115.636993, 'Bunbury', 100),
		"kalgoorlie" => array(6430, 'WA', -30.747667, 121.472302, 'Kalgoorlie', 100),
		"geraldton" => array(6530, 'WA', -28.773068, 114.611265, 'Geraldton', 100),
		"albany" => array(6330, 'WA', -35.023873, 117.883543, 'Albany', 100)
	);

	// ********************************************************************************
	// ** ABN Lookup API
	// ********************************************************************************
	$abnAPIGuid = $_ENV['ABN_API_GUID'];

	// ********************************************************************************
	// ** ACN Lookup API (ASIC)
	// ********************************************************************************	
	$acnAPI = array(
		'username' => $_ENV['ACN_API_USERNAME'],
		'password' => $_ENV['ACN_API_PASSWORD'],
		'senderId' => $_ENV['ACN_API_SENDER_ID'],
		'senderType' => $_ENV['ACN_API_SENDER_TYPE'],
		'isUAT' => $_ENV['ACN_API_IS_UAT'] == 1,
	);

	// ********************************************************************************
	// ** NSW Electrical Licenses Lookup API
	// ********************************************************************************	    
	$licensesNSW = array(
		'key' => $_ENV['LICENSES_NSW_KEY'], 
		'secret' => $_ENV['LICENSES_NSW_SECRET']
	);

	// ****
	// SMART ME Credentials
	// ****
	$smartMe = array(
		'client_id' => $_ENV['SMARTME_CLIENT_ID'],
		'client_secret' => $_ENV['SMARTME_CLIENT_SECRET'],
		'url_token' => $_ENV['SMARTME_URL_TOKEN'],
		'audience' => $_ENV['SMARTME_AUDIENCE'],
		'url' => $_ENV['SMARTME_URL']
	);

	// ****
	// DRIVA Credentials
	// ****
	$driva = array(
		'apiKey' => $_ENV['DRIVA_API_KEY'],
		'partnerId' => $_ENV['DRIVA_PARTNER_ID'],
		'url' => 'https://api-staging.driva.com.au/v1'
	);

	// ****
	// ParkerLane Credentials
	// ****
	$parkerlane = array(
		'client_id' => $_ENV['PARKERLANE_CLIENT_ID'],
		'client_secret' => $_ENV['PARKERLANE_CLIENT_SECRET'],
		'username' => $_ENV['PARKERLANE_USERNAME'],
		'password' => $_ENV['PARKERLANE_PASSWORD'],
		'url' => $_ENV['PARKERLANE_URL'],
		'url_token' => $_ENV['PARKERLANE_URL_TOKEN'],
	);

	// ****
	// Origin Credentials
	// ****
	$origin = array(
		'clientId' => $_ENV['ORIGIN_CLIENT_ID'],
		'clientSecret' => $_ENV['ORIGIN_CLIENT_SECRET'],
		'authEndpoint' => $_ENV['ORIGIN_API_URL_TOKEN'],
		'apiEndpoint' => $_ENV['ORIGIN_API_URL'],
		'emailRecipient' => $_ENV['ORIGIN_EMAIL_RECIPIENT'],
		'enabled' => $_ENV['ORIGIN_ENABLED'],
	);
?>