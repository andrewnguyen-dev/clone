<?php
	// load global libraries
    include('global.php');
    set_time_limit(0);
    
    $SQL = "SELECT * FROM schedule WHERE submitted < {$nowSql} AND status = 'pending'";
    $result = db_query($SQL);
    
    while ($item = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
    	extract(htmlentitiesRecursive($item), EXTR_PREFIX_ALL, 'i');
    	
    	$i_value = strtolower($i_value);
    	
    	// Update the supplier or parent
    	if ($i_parent == '') {
			// Child update
			switch ($i_task) {
				case 'status':
					$supplier = db_getVal("SELECT status, entity_id, extraLeads FROM suppliers WHERE record_num = {$i_supplier}");
					list($s_status, $s_entity_id, $s_extraLeads) = $supplier;
					if($s_status == 'active' && $i_value == 'paused'){
						db_query("INSERT INTO historic_fields(entity_id, field, value) VALUES('{$s_entity_id}', 'extraLeads', '{$s_extraLeads}'); ");
					} elseif($s_status == 'paused' && $i_value == 'active') {
						$SQL = " UPDATE suppliers s ";
						$SQL .= " LEFT JOIN historic_fields hf ON s.entity_id = hf.entity_id AND hf.field = 'extraLeads' ";
						$SQL .= " SET s.extraLeads = COALESCE(hf.value, s.extraLeads) ";
						$SQL .= " WHERE s.record_num = {$i_supplier}; ";
						db_query($SQL);
						
						db_query(" DELETE FROM historic_fields WHERE entity_id = '{$s_entity_id}' and field = 'extraLeads'; ");
					}
					db_query("UPDATE suppliers SET status = '{$i_value}' WHERE record_num = '{$i_supplier}'");
					break;
				case 'capChange':
					db_query("UPDATE supplier_cap_limit SET max = {$i_value} WHERE supplier_id = {$i_supplier} AND cap_id = {$i_cap};");
					break;
			}
    	} else {
			// parent update
			db_query("UPDATE suppliers_parent SET status = '{$i_value}' WHERE record_num = '{$i_parent}'");
    	}
    	
    	// Update the scheduled task status
    	db_query("UPDATE schedule SET status = 'executed', executed = {$nowSql} WHERE record_num = '{$i_record_num}'");
	}
?>