<?php
    // load global libraries
    include('global.php');
    set_time_limit(0);
    $debugging = 0;

    global $nowSql;

    $SQL = "
        UPDATE leads SET 
            notes = CONCAT(notes, 
                '\nLead has been run through the dispatch a second time, from a ', 
                if(status='waiting', 'claim', 'manual'), ' status\nThe first time was at: ',updated,
                '\n', $nowSql, '\n'),
            status = 'pending', openClaims='Y', manualAttempts=1
        WHERE status in('manual', 'waiting') 
            AND leadType in ('Residential', 'Commercial') 
            AND manualAttempts=0 AND created >= ($nowSql - INTERVAL 24 hour);
    ";

    db_query($SQL);    
?>
