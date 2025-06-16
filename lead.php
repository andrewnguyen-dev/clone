<?php
    include_once('origin.php');

	function loadLeadData($leadId) {
        global $siteURLSSL;
        $r = db_query("SELECT * FROM leads WHERE record_num='{$leadId}' LIMIT 1");
        $raw = mysqli_fetch_array($r, MYSQLI_ASSOC);
        $data = htmlentitiesRecursive($raw);
        extract($data, EXTR_PREFIX_ALL, 'l');

        $unserializedSystemDetails = unserialize(base64_decode($raw['systemDetails']));
        $isEVChargerLead = (array_key_exists('Features:', $unserializedSystemDetails) && stripos($unserializedSystemDetails['Features:'], 'EV Charger') !== false);
        if($isEVChargerLead) {
            $evSiteDetails = unserialize(base64_decode($raw['siteDetails']));
            $includeSolar = array_key_exists('Type of Roof:', $evSiteDetails) && $evSiteDetails['Type of Roof:'] && $evSiteDetails['Type of Roof:']!="";
            if($includeSolar) {
                $evSiteDetails['Existing solar size:'] .= ", but would like a quote" . (
                    strpos($evSiteDetails['Existing solar size:'], "No")===false ? ' for a bigger one' : ''
                );
                $evSiteDetails['Existing solar size:'] = str_replace("No Solar", "No solar", $evSiteDetails['Existing solar size:']);
                $raw['siteDetails'] = base64_encode(serialize($evSiteDetails));
            }
            $data['evType'] = str_replace("EV Charg", "EV charg", $unserializedSystemDetails['Features:']);
        }

        $isHWHPLead = (array_key_exists('Features:', $unserializedSystemDetails) && stripos($unserializedSystemDetails['Features:'], 'Hot water heat pump') !== false);
        if($isHWHPLead) {
            // To get the correct type we need to clean up any extra information in the features that is not necessary for the hwhp type
            $features = $unserializedSystemDetails['Features:'];
            $data['hwhpType'] = "Hot water heat pump";

            if(stripos($features, 'Hot Water Heat Pump + solar & battery') !== false){
                $data['hwhpType'] = 'Hot Water Heat Pump + solar & battery';
            } else if(stripos($features,'Hot Water Heat Pump + solar') !== false){
                $data['hwhpType'] = 'Hot Water Heat Pump + solar';
            } else if(stripos($features, 'Hot Water Heat Pump + battery') != false){
                $data['hwhpType'] = 'Hot Water Heat Pump + battery';
            }
        }

        $data['quoteDetails'] = decodeArray($raw['quoteDetails']);
        $data['quoteDetailsCells'] = decodeArrayTemplateCells($raw['quoteDetails']);
        $data['rebateDetails'] = decodeArray($raw['rebateDetails']);
        $data['rebateDetailsCells'] = decodeArrayTemplateCells($raw['rebateDetails']);
        $data['siteDetails'] = decodeArray($raw['siteDetails']);
        $data['siteDetailsCells'] = decodeArrayTemplateCells($raw['siteDetails']);
        $data['systemDetails'] = decodeArray($raw['systemDetails']);
        $data['systemDetailsCells'] = decodeArrayTemplateCells($raw['systemDetails']);
        $data['rawquoteDetails'] = $raw['quoteDetails'];
        $data['rawrebateDetails'] = $raw['rebateDetails'];
        $data['rawsiteDetails'] = $raw['siteDetails'];
        $data['rawsystemDetails'] = $raw['systemDetails'];
        $data['requestedQuotes'] = $l_requestedQuotes;
        $data['requestedQuotesYouAndOthers'] = $data['requestedQuotes'];
        if($data['requestedQuotesYouAndOthers']>1) {
            $others = "one other";
            if($data['requestedQuotesYouAndOthers']>2)
                $others = ($data['requestedQuotesYouAndOthers']-1)." others";
            $data['requestedQuotesYouAndOthers'] .= " (you and ".$others.")";
        }

        $data['systemHeadlineString'] = systemHeadlineString($raw, $l_iCity);
        
        $l_installAddress = array();
        $l_installAddress[] = $l_iAddress;
        if ($l_iAddress2 != '') $l_installAddress[] = $l_iAddress2;
        $l_installAddress[] = "{$l_iCity} {$l_iState} {$l_iPostcode}";
        $l_installAddress[] = $l_iCountry;
        $data['installAddress'] = join('<br />', $l_installAddress);

        if ($l_mAddress == '') {
            $data['mailAddress'] = "Same as installation address";
            $data['mailAddressComplete'] = $data['installAddress'];
            $data['mAddress'] = $l_iAddress;
            $data['mAddress2'] = $l_iAddress2;
            $data['mCity'] = $l_iCity;
            $data['mState'] = $l_iState;
            $data['mPostcode'] = $l_iPostcode;
            $data['mCountry'] = $l_iCountry;
        } else {
            $l_mailAddress = array();
            $l_mailAddress[] = $l_mAddress;
            if ($l_mAddress2 != '') $l_mailAddress[] = $l_mAddress2;
            $l_mailAddress[] = "{$l_mCity} {$l_mState} {$l_mPostcode}";
            $l_mailAddress[] = $l_mCountry;
            $data['mailAddress'] = $data['mailAddressComplete'] = join('<br />', $l_mailAddress);
        }
    
        if ($l_campaign == '') $data['campaignMsg'] = "<i>This lead has not yet been added to a campaign.</i>";
        else $data['campaignMsg'] = "Lead was added to the {$l_campaign} campaign.";
        
        if ($l_mapStatus == 'noGeoCode') {
            $data['mapLink'] = "<i>We were unable to map the installation address for the lead.</i>";
        } elseif ($l_mapStatus == 'userNotFound') {
            $data['mapLink'] = "<i>The user was unable to find their location on a map.</i>";
        } else {
            if ($l_mapStatus == 'foundLot') $comment = 'Plot Location (building yet to be constructed)'; else $comment = 'Roof Location';
            $url = "http://maps.google.com/maps?q={$l_latitude},{$l_longitude}&t=h&z=18";
            $data['mapLink'] = "<div>
                            <!--[if mso]>
            <v:roundrect xmlns:v=\"urn:schemas-microsoft-com:vml\" xmlns:w=\"urn:schemas-microsoft-com:office:word\" href=\"{$url}\" style=\"height:38px;v-text-anchor:middle;width:150px;\" arcsize=\"53%\" strokecolor=\"#1B75BB\" fill=\"t\">
              <v:fill type=\"tile\" color=\"#1B75BB\" />
              <w:anchorlock/>
              <center style=\"color:#ffffff;font-family:sans-serif;font-size:11px;font-weight:bold;\">VIEW ON MAP</center>
            </v:roundrect>
          <![endif]--><a href=\"{$url}\" style=\"background-color:#1B75BB;border:1px solid #1B75BB;border-radius:20px;color:#ffffff;display:inline-block;font-family:sans-serif;font-size:11px;font-weight:bold;line-height:38px;text-align:center;text-decoration:none;width:150px;-webkit-text-size-adjust:none;mso-hide:all;\">VIEW
                                ON MAP</a></div>";
        }

        $data['newsletter'] = $l_newsletter=='Y'?'Yes':'No';
        $data['leadId'] = sprintf('%05u', $l_record_num);
        
        $data['submitted'] = $l_submitted;
        $data['updated'] = $l_updated;
        $data['adminNotesForSupplier'] = nl2br($raw['adminNotesForSupplier'] ?? '');
        $data['adminNotesForSupplierWithLabel'] = !empty($raw['adminNotesForSupplier']) ? "<p><b>Notes from SQ staff:</b> " . nl2br($raw['adminNotesForSupplier']) . "</p>" : "";
        $data['adminNotesForSupplierBox'] = strlen($data['adminNotesForSupplier']) > 2 ? "<tr><td>&nbsp;</td></tr><tr><td class=\"void\" style=\"margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-top: 30px;padding-bottom: 30px;padding-right: 25px;padding-left:25px;font-size:14px;font-family: Helvetica, Arial, sans-serif !important;background-color: #F3F9FF;\"><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\"><tbody><tr><td class=\"void\" width=\"100%\" style=\"margin: 0;padding: 0;border-collapse: collapse;margin-top: 0;margin-bottom: 0;margin-right: 0;color:#2B3864;margin-left: 0;padding-bottom: 7px;padding-right: 0;padding-left: 0;font-size:16px;font-family: Helvetica, Arial, sans-serif !important;font-weight: regular;\"><span style=\"font-weight: bold; color: #BE1E2D\">Notes from SQ staff:</span><br>".$data['adminNotesForSupplier'] . "</td></tr></tbody></table></td></tr>" : '';
        $data['companyLabelIfExists'] = !empty($data['company']) ? 'Company:' : '';
        
        $l_supplierList = array();
        $tables = "lead_suppliers AS ls LEFT JOIN suppliers AS s ON s.record_num=ls.supplier";
        $r = db_query("SELECT s.company AS supplierName FROM $tables WHERE lead_id='{$l_record_num}' AND type='regular' ORDER BY s.record_num ASC");
        while ($d = mysqli_fetch_row($r)) $l_supplierList[] = $d[0];
        $data['numSuppliers'] = count($l_supplierList);
        $data['supplierList'] = join(', ', $l_supplierList);
        $data['isChoice'] = false;
        if($data['referer']) {
            $r = db_query("SELECT * FROM leads_referers WHERE record_num='".$data['referer']."' LIMIT 1");
            $referer_raw = mysqli_fetch_array($r, MYSQLI_ASSOC);
            $referer = htmlentitiesRecursive($referer_raw);
            $data['isChoice'] = (stripos($referer['landingPage'].$referer['url'].$referer['query'], "utm_source=choice")!==false);
        }


        if($isEVChargerLead || $isHWHPLead){
            $imageFolder = $isEVChargerLead ? 'ev' : 'hwhp';

            // Fetch the images grouped by type
            $data['leadImages'] = '';
            $imagesResult = db_query("select image_type, GROUP_CONCAT(image) images from lead_images where lead_id = $l_record_num  group by image_type;");
            $imageRows = mysqli_fetch_all($imagesResult, MYSQLI_ASSOC);

            if(!empty($imageRows)) {
                $leadImages = [];
                foreach ($imageRows as $idx => $row) {
                    $images = explode(',', $row['images']);
                    $counter = 1;
                    $imageLinks = [];
                    foreach($images as $idx => $image){
                        $idx++;
                        $imageLinks[] = '<a href="' . $siteURLSSL . 'img/quote/'.$imageFolder.'/' . $image . '">' . "Image $idx" . '</a>';
                    }

                    $imageText = 'Image';
                    if(count($images) > 1)
                        $imageText = 'Images';
                    $leadImages[ucfirst($row['image_type']) . " $imageText"] = implode('<br />', $imageLinks);
                }
                // Use existing functions that turn arrays into html structured content
                $data['leadImages'] = decodeArrayTemplateCells( base64_encode(serialize($leadImages)));
            }

            if($data['leadImages'] == '')
                unset($data['leadImages']);
        }
        
        return $data;
    }
    
    function loadPreviouslyMatchedSuppliers($l_record_num){
        $suppliers = array();
        $parents = array();
        $r = db_query("SELECT supplier FROM lead_claims WHERE lead_id='{$l_record_num}' UNION SELECT supplier FROM lead_suppliers WHERE lead_id='{$l_record_num}'");
        while ($d = mysqli_fetch_array($r, MYSQLI_ASSOC))
            $suppliers[] = $d['supplier'];
        if(count($suppliers)>0) { // only enter here if there's any previously matched suppliers
            $r = db_query("SELECT parent FROM suppliers WHERE record_num IN (".implode(",", $suppliers).") AND parent != '1'");
            while ($d = mysqli_fetch_array($r, MYSQLI_ASSOC))
                $parents[] = $d['parent'];
        }
        $suppliers = ['suppliers' => $suppliers, 'parents' => $parents];
        return $suppliers;
    }

    function systemHeadlineString($raw, $l_iCity="") {
        // priceTypeString: [system type] system in [suburb] with finance using microinverters with consumption monitoring
        $quoteDetails = unserialize(base64_decode($raw['quoteDetails']));
        $siteDetails = unserialize(base64_decode($raw['siteDetails']));
        $systemDetails = unserialize(base64_decode($raw['systemDetails']));
        $budget = (array_key_exists('Price Type:', $quoteDetails) && strpos($quoteDetails['Price Type:'], 'A good budget system')  !== false);
        $topQuality = (array_key_exists('Price Type:', $quoteDetails) && strpos($quoteDetails['Price Type:'], 'Top quality (most expensive)')  !== false);

        if($raw['leadType'] == 'Commercial')
            $systemHeadlineString = 'a commercial solar system';
        elseif(strpos($systemDetails['Features:'], 'Increase size of existing solar system')!==false)
            $systemHeadlineString = 'increasing size of an existing solar system';
        elseif(strpos($systemDetails['Features:'], 'Battery Ready')!==false)
            $systemHeadlineString = 'a battery ready solar system';
        elseif(strpos($systemDetails['Features:'], 'Adding Batteries')!==false)
            $systemHeadlineString = 'adding batteries to an existing solar system';
        elseif(strpos($systemDetails['Features:'], 'Hybrid System (Grid Connect with Batteries)')!==false) {
            $systemHeadlineString = 'a hybrid solar system';
            if($budget)
                $systemHeadlineString = 'a good budget hybrid system';
            if($topQuality)
                $systemHeadlineString = 'a top quality hybrid system';
        }
        elseif(strpos($systemDetails['Features:'], 'Off Grid / Remote Area System')!==false) {
            $systemHeadlineString = 'an off grid system';
            if($budget)
                $systemHeadlineString = 'a good budget off grid system';
            if($topQuality)
                $systemHeadlineString = 'a top quality off grid system';
        }elseif(stripos($systemDetails['Features:'], 'EV Charger')!==false) {
            $including = [];
            if(stripos($systemDetails['Features:'], 'solar')!==false)
                $including[] = 'Solar';
            if(stripos($systemDetails['Features:'], 'battery')!==false)
                $including[] = 'Battery';
            $including = count($including)>0 ? " + ".implode(" & ", $including) : "";
            $systemHeadlineString = 'an EV Charger'.$including.' system';
            if($budget)
                $systemHeadlineString = 'a good budget EV Charger'.$including.' system';
            if($topQuality)
                $systemHeadlineString = 'a top quality EV Charger'.$including.' system';
        }elseif(stripos($systemDetails['Features:'], 'Hot water heat pump')!==false) {
            $including = [];
            if(stripos($systemDetails['Features:'], 'solar')!==false)
                $including[] = 'Solar';
            if(stripos($systemDetails['Features:'], 'battery')!==false)
                $including[] = 'Battery';
            $including = count($including)>0 ? " + ".implode(" & ", $including) : "";
            $systemHeadlineString = 'a Hot water heat pump'.$including.' system';
            if($budget)
                $systemHeadlineString = 'a good budget Hot water heat pump'.$including.' system';
            if($topQuality)
                $systemHeadlineString = 'a top quality Hot water heat pump'.$including.' system';
        }
        else {
            $systemHeadlineString = 'an on grid solar system';
            if($budget)
                $systemHeadlineString = 'a good budget on grid system';
            if($topQuality)
                $systemHeadlineString = 'a top quality on grid system';
        }
        if(!empty($l_iCity))
            $systemHeadlineString .= ' in ' . $l_iCity;
        if(strpos($siteDetails['Anything Else:'], 'wants to pay cash')!==false)
            $systemHeadlineString .= ' with cash';
        elseif(strpos($siteDetails['Anything Else:'], 'wants to pay through a monthly instalment')!==false)
            $systemHeadlineString .= ' with finance';

        $microinverters = (strpos($systemDetails['Features:'], 'Micro Inverters or Power Optimisers')!==false);
        if($microinverters)
            $systemHeadlineString .= ' using microinverters';
        return $systemHeadlineString;
    }

    function leadAfterDispatch($leadId) {
        global $origin;
        $lead = loadLeadData($leadId);
        if($lead['status'] == 'dispatched') {
            if($origin['enabled'] !== "false" && $lead['originLead'] == 'Y') {
                // Push lead to Origin API
                $leadPayload = getLeadPayload($leadId);
                sendToOriginApi($leadPayload);
                echo PHP_EOL . 'Lead sent to Origin API' . PHP_EOL;
            } else {
                echo PHP_EOL . 'Lead MISSED' . PHP_EOL;
            }

            // Initiate the default value
            $description = 'Dispatched to 0 Suppliers';
            if($lead['supplierList'] !== ''){
                // Add all entries to the log_leads table
                $description = "Dispatched to {$lead['supplierList']}";
                
            }

            logLeadsEntry($leadId, $description);
        }
    }
    /**
     * Using the loadLeadData function, this function will return the lead referer caption
     */
    function getLeadReferer($leadId){
        $lead = loadLeadData($leadId);
        $lead['referer'] = $lead['referer'] ?? null;
        if ($lead['referer']) {
            $r = db_query("SELECT * FROM leads_referers WHERE record_num='".$lead['referer']."' LIMIT 1");
            $referer = mysqli_fetch_object($r);
            return refererCaption($referer, true, []);
        } else {
            return '(None)';
        }

    }
?>
