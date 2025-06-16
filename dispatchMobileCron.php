<?php
    // load global libraries
    include('global.php');
    set_time_limit(0);
    $debugging = 0;
    
    $SQL = "SELECT * FROM leads WHERE status='pending' AND source='SolarQuoteMobile' AND leadType='Residential' AND ".
        "((TIMESTAMPDIFF(MINUTE, created, ".$nowSql.") >= 2 AND manuallySelectedSupplierEnabled != 'Y') ".
            "OR (TIMESTAMPDIFF(MINUTE, created, ".$nowSql.") >= 17))". 
        "ORDER BY record_num ASC LIMIT 6";
    $leads = db_query($SQL);

    while ($lead = mysqli_fetch_array($leads, MYSQLI_ASSOC)) {
        extract($lead, EXTR_PREFIX_ALL, 'l');

        $systemDetails = unserialize(base64_decode($l_systemDetails));
        $siteDetails = unserialize(base64_decode($l_siteDetails));

        if(stripos($systemDetails['Features:'], 'EV Charger') !== false && isset($siteDetails['Car Make/Model:']) && $siteDetails['Car Make/Model:'] != '' ){
            doDispatchEV($l_record_num);
        } elseif(stripos($systemDetails['Features:'], 'Hot water heat pump') !== false && isset($siteDetails['Location Accessibility:']) && $siteDetails['Location Accessibility:'] != '' ){
            doDispatchHWHP($l_record_num);
        } else {
            doDispatchMobile($l_record_num);
        }
    }
?>