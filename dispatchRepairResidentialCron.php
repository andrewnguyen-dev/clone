<?php
    // load global libraries
    include('global.php');
    set_time_limit(0);
    $debugging = 0;
    
    $SQL = "SELECT * FROM leads WHERE status='pending' AND leadType = 'Repair Residential' AND TIMESTAMPDIFF(MINUTE, created, ".$nowSql.") >= 2 ORDER BY record_num ASC";
    $leads = db_query($SQL);
    
    while ($lead = mysqli_fetch_array($leads, MYSQLI_ASSOC)) {
        extract($lead, EXTR_PREFIX_ALL, 'l');
        
        doDispatchRepairResidential($l_record_num);
	}
?>