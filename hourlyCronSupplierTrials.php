<?php

require_once('global.php');

$trials = db_query("SELECT * FROM suppliers_trials WHERE trial_status != 'ended'");
    
while ($trial = mysqli_fetch_assoc($trials)) {

    trigger_trial($trial["record_num"]);

}