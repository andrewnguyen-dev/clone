<?php
    // load global libraries
    include('global.php');
    set_time_limit(0);
    $debugging = 0;
    
    $SQL = "SELECT * FROM leads WHERE status='pending' AND source='SolarQuoteReused' AND leadType='Residential' ORDER BY record_num ASC";
    $leads = db_query($SQL);
    
    while ($lead = mysqli_fetch_array($leads, MYSQLI_ASSOC)) {
        extract($lead, EXTR_PREFIX_ALL, 'l');

        doDispatchReuse($l_record_num);
	}
?>