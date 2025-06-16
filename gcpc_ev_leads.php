<?php

include('global.php');

$result = [];

$fields = [];
$fields[] = 'DATE(L.submitted) submit_date';
$fields[] = 'coalesce(count(L.record_num), 0) leads';
$fields[] = 'coalesce(SUM(L.requestedQuotes), 0) requested';

$conditions = [];
$conditions[] = 'L.status != "duplicate"';
$conditions[] = 'L.source != "SolarQuoteReused"';
$conditions[] = 'L.submitted >= "2023-05-29 00:00:00"';
$conditions[] = 'L.submitted <= "2023-06-04 23:59:59"';
$conditions[] = 'L.systemDetailsText LIKE "%EV Charger%"';
$conditions[] = 'LR.landingPage LIKE "%gclid=%"';

$leadsTable = '(SELECT *, CAST(from_base64(systemDetails) AS CHAR) systemDetailsText FROM leads) AS L';
$leadsRefJoin = 'JOIN leads_referers AS LR ON LR.record_num = L.referer';
$table = $leadsTable." ".$leadsRefJoin;

$leadsSQL = "SELECT ".implode(', ', $fields)." FROM ".$table." WHERE ".implode(' AND ', $conditions)." GROUP BY submit_date";

$leads = db_query($leadsSQL);

while ($lead = mysqli_fetch_array($leads, MYSQLI_ASSOC)) {
    $result[$lead['submit_date']] = $lead;
}

$fields = [];
$fields[] = 'DATE(L.submitted) submit_date';
$fields[] = 'coalesce(SUM(case when LS.type = "regular" then 1 else 0 end ), 0) sent';
$fields[] = 'coalesce(SUM(case when LS.status = "rejected" then 1 else 0 end ), 0) rejected';
$fields[] = 'coalesce(SUM(case when LS.status NOT IN ("rejected", "scrapped") AND LS.invoice = "Y" then round(LS.leadPrice * (1- S.otherDiscount/100 - (IF(S.invoiceTerms = "standard", 0, S.invoiceDiscountRate)/100)),2) else 0 end), 0) revenue';

$leadsSupJoin = 'JOIN lead_suppliers AS LS ON LS.lead_id = L.record_num';
$supplierJoin = 'JOIN suppliers AS S ON LS.supplier = S.record_num';
$table = $leadsTable." ".$leadsRefJoin." ".$leadsSupJoin." ".$supplierJoin;

$sql = "SELECT ".implode(', ', $fields)." FROM ".$table." WHERE ".implode(' AND ', $conditions)." GROUP BY submit_date";
$matches = db_query($sql);

while ($match = mysqli_fetch_array($matches, MYSQLI_ASSOC)) {
    $result[$match['submit_date']] = array_merge($result[$match['submit_date']], $match);
}

$dirname = __DIR__.'/temp';
$filename = $dirname.'/ev-gcpc-report.csv';
if (!is_dir($dirname)) mkdir($dirname, 0755, true);

$file = fopen($filename, 'w');
$cols = false;
foreach ($result as $row) {
    $newcols = [];
    $newcols['utilization_rate'] = $row['leads'] > 0 ? round($row['sent'] / $row['leads'], 2) : 0;
    $newcols['rejection_rate'] = ($row['sent'] > 0 ? round($row['rejected'] / $row['sent'] * 100, 2) : 0)."%";
    $newcols['revenue_per_lead'] = $row['leads'] > 0 ? round($row['revenue'] / $row['leads'], 2) : 0;
    $row = array_slice($row, 0, 1) + $newcols + array_slice($row, 1);
    if (!$cols) {
        $columns = array_keys($row);
        fputcsv($file, $columns);
        $cols = true;
    }
    fputcsv($file, $row);
}
fclose($file);