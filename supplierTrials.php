<?php

function get_trial_data($trial_id) {
    $trial_id = intval($trial_id);
    if ($trial_id < 1) return false;
    $SQL = "SELECT ST.*, S.company FROM suppliers_trials AS ST INNER JOIN suppliers AS S ON ST.supplier = S.record_num WHERE ST.record_num = {$trial_id};";
    $trials = db_query($SQL);
    $trial = mysqli_fetch_assoc($trials);
    if (empty($trial)) return false;
    return $trial;
}

function is_trial_eligible($trial_id) {
    $trial_data = get_trial_data($trial_id);
    if (!$trial_data) return false;
    extract($trial_data, EXTR_PREFIX_ALL, 't');
    $trial_start = \DateTime::createfromformat('Y-m-d H:i:s', $t_trial_start);
    $trial_end = \DateTime::createfromformat('Y-m-d H:i:s', $t_trial_end);
    return ( new \DateTime() >= $trial_start && new \DateTime() < $trial_end );
}

function trial_max_leads_reached($trial_id) {
    $trial_data = get_trial_data($trial_id);
    if (!$trial_data) return false;
    extract($trial_data, EXTR_PREFIX_ALL, 't');
    $leads_acquired = empty($t_lead_ids) ? 0 : count(explode(',', $t_lead_ids));
    return ( $leads_acquired >= $t_max_leads );
}

function notify_supplier_trial_reaching_max($trial_id) {
    $trial_data = get_trial_data($trial_id);
    if (!$trial_data) return false;
    extract($trial_data, EXTR_PREFIX_ALL, 't');
    $leads_acquired = empty($t_lead_ids) ? 0 : count(explode(',', $t_lead_ids));
    $reaching_max = ( $leads_acquired >= ceil( $t_max_leads*80/100 ) );
    if ($reaching_max) {
        $bc = new Basecamp();
        global $bcTrialCampfireID;
        return $bc->new_campfire_message($bcTrialCampfireID, "Hello,\nThe installer \"$t_company\" (ID: $t_supplier) (https://www.solarquotes.com.au/leads/supplier_view/?supplier_id=$t_supplier) has reached 80% of the max leads ($t_max_leads) allowed in the trial.\nOnce the installer reaches the limit, old pricing will be applied automatically and the status of the installer will be set to \"$t_status_after\" if not already.");
    }
}

function notify_supplier_trial_reaching_end($trial_id) {
    $trial_data = get_trial_data($trial_id);
    if (!$trial_data) return false;
    extract($trial_data, EXTR_PREFIX_ALL, 't');
    $trial_end = \DateTime::createfromformat('Y-m-d H:i:s', $t_trial_end);
    $today = new \DateTime();
    $interval = $today->diff($trial_end);
    if ($interval->days < 8) {
        $bc = new Basecamp();
        global $bcTrialCampfireID;
        return $bc->new_campfire_message($bcTrialCampfireID, "Hello,\nThe installer \"$t_company\" (ID: $t_supplier) (https://www.solarquotes.com.au/leads/supplier_view/?supplier_id=$t_supplier) has only {$interval->days} days left for the trial. The trial ends at  \"$t_trial_end\" after which the old pricing will be applied automatically and the status of the installer will be set to \"$t_status_after\" if not already.");
    }
}

function trigger_trial($trial_id) {
    $trial_id = intval($trial_id);
    if ($trial_id < 1) return false;
    $trials = db_query("SELECT * FROM suppliers_trials WHERE record_num = $trial_id");
    
    // While loop for the trial, this should only occur once
    while ($dt = mysqli_fetch_assoc($trials)) {
        extract($dt, EXTR_PREFIX_ALL, 't');
        $suppliers = db_query("SELECT * FROM suppliers WHERE record_num = $t_supplier");
    
        // While loop for the supplier, this should only occur once
        while ($ds = mysqli_fetch_assoc($suppliers)) {
            extract($ds, EXTR_PREFIX_ALL, 's');

            if (is_trial_eligible($t_record_num) && $t_trial_status == "waiting") {

                $pricing_after = [];
                $supplier_pricings = db_query("SELECT * FROM entity_supplier_pricing WHERE entity_id = '$s_entity_id'");
                while ($dsp = mysqli_fetch_assoc($supplier_pricings)) {
                    $pricing_after[$dsp["pricing_type"]] = $dsp["price"];
                }
                $pricing_after = json_encode($pricing_after);

                db_query("UPDATE entity_supplier_pricing SET price = '$t_lead_price' WHERE entity_id = '$s_entity_id'");
                db_query("UPDATE suppliers_trials SET trial_status = 'active', pricing_after = '$pricing_after' WHERE record_num = $t_record_num");

            } else if ($t_trial_status == "active" && (!is_trial_eligible($t_record_num) || trial_max_leads_reached($t_record_num)) ) {

                // Delete the current pricing
                db_query("DELETE FROM entity_supplier_pricing WHERE entity_id = '$s_entity_id'");

                // Apply old pricing
                $pricing_to_apply = json_decode($t_pricing_after, true);
                foreach($pricing_to_apply as $t => $pr) {
                    db_query("INSERT INTO entity_supplier_pricing (entity_id, pricing_type, price) VALUES ('$s_entity_id', '$t', '$pr')");
                }

                // Update the trial status
                db_query("UPDATE suppliers_trials SET trial_status = 'ended' WHERE record_num = $t_record_num");

                // Update supplier status if needed
                if (!empty($t_status_after)) {
                    db_query("UPDATE suppliers SET status = '$t_status_after' WHERE record_num = $s_record_num");
                }
    
            } else if ($t_trial_status == "active") {

                if ($t_reaching_max_notified != 1) {
                    $notified = notify_supplier_trial_reaching_max($t_record_num);
                    if ($notified) {
                        db_query("UPDATE suppliers_trials SET reaching_max_notified = 1 WHERE record_num = $t_record_num");
                    }
                }
                if ($t_reaching_end_notified != 1) {
                    $notified = notify_supplier_trial_reaching_end($t_record_num);
                    if ($notified) {
                        db_query("UPDATE suppliers_trials SET reaching_end_notified = 1 WHERE record_num = $t_record_num");
                    }
                }
                
            }

        }

    }
    return true;
}