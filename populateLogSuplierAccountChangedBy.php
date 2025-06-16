<?php
	require("global.php");

	$updatedLogs = 0;
	$totalLogs = 0;

	$SQL = "SELECT *FROM log_supplier_account WHERE field_name = 'status' and changed_by = 'unknown' ORDER BY created DESC;";
	$logsSupplierAccountResults = db_query($SQL);

	$SQL = "SELECT *FROM log_supplier WHERE description LIKE '%Status changed from%' ORDER BY submitted DESC;";
	$logsSupplierResults = db_query($SQL);

	$logsAccount = [];
	while ($log = mysqli_fetch_assoc($logsSupplierAccountResults)) {
		$supplierId = $log['supplier_id'];
		$logsAccount[$supplierId] ??= [];
		$logsAccount[$supplierId][] = $log;
		$totalLogs++;
	}

	$logsSupplier = [];
	while ($log = mysqli_fetch_assoc($logsSupplierResults)) {
		$supplierId = $log['supplier'];
		$logsSupplier[$supplierId] ??= [];
		$logsSupplier[$supplierId][] = $log;
	}

	echo sizeof($logsAccount) . " suppliers to process\n";

	foreach($logsAccount as $supplier => $logs) {
		foreach($logs as $log) {
			$oldValue = $log['old_value'];
			$newValue = $log['new_value'];
			$supplierId = $log['supplier_id'];
			$created = $log['created'];
			$createdTime = strtotime($created);

			$logsThisSupplier = $logsSupplier[$supplierId] ?? [];
			foreach($logsThisSupplier as $logSupplier) {
				$description = $logSupplier['description'];
				$submitted = $logSupplier['submitted'];

				// set submitted to adelaide timezone
				$submitted = new DateTime($submitted, new DateTimeZone('Australia/Adelaide'));
				$timezoneOffset = $submitted->getOffset();

				// calculate created/submitted date difference in minuts
				$submitted = $submitted->getTimestamp();
				$submittedTime = $submitted - $timezoneOffset;
				$submitted = date('Y-m-d H:i:s', $submittedTime);

				$diff = $createdTime - $submittedTime;
				$diff = $diff / 60;
				$diff = abs(round($diff,2));

				$changedBy = 'staff';
				if(strpos($description, "(Supplier Change)") !== false) {
					$changedBy = 'supplier';
				}

				// look for changed from and remove everything before it including it
				$description = trim(explode("changed from", $description)[1]);
				$description = explode(" ", $description);
				$oldStatus = $description[0];
				$newStatus = $description[2];

				// If the difference between the 2 logs is less than 30 seconds
				if($diff < 0.5 && $oldStatus == $oldValue && $newStatus == $newValue) {
					$SQL = "UPDATE log_supplier_account SET changed_by = '$changedBy' WHERE record_num = " . $log['record_num'] . ";";
					db_query($SQL);
					$updatedLogs++;
					break;
				}
			}
		}
	}

	echo "Updated $updatedLogs out of $totalLogs logs\n";
?>