<?php
	// load global libraries
    include('global.php');

    $SQL = "SELECT S.*, SP.parentName,
    COALESCE(AVG(NULLIF(IFNULL(F.one_year_rate_value, F.rate_value), 0)), 0) AS rating_value,
    COALESCE(AVG(NULLIF(IFNULL(F.one_year_rate_system_quality, F.rate_system_quality), 0)), 0) AS rating_system_quality,
    COALESCE(AVG(NULLIF(IFNULL(F.one_year_rate_customer_service, F.rate_customer_service), 0)), 0) AS rating_customer_service,
    COALESCE(AVG(NULLIF(IFNULL(F.one_year_rate_installation, F.rate_installation), 0)), 0) AS rating_installation,
    COALESCE(SUM(IF(F.one_year_rate_value IS NULL, (CASE WHEN F.rate_avg >= 1 THEN 1 ELSE 0 END), (CASE WHEN F.one_year_rate_avg >= 1 THEN 1 ELSE 0 END))), 0) AS reviews_count,
    COALESCE(LS.lastYearLeads, 0) AS lastYearLeads
    FROM suppliers S
    LEFT JOIN suppliers_parent SP ON S.parent = SP.record_num AND S.parent != 1
    LEFT JOIN 
    (SELECT supplier, COUNT(*) AS lastYearLeads 
     FROM lead_suppliers 
     WHERE dispatched >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY supplier) LS 
    ON LS.supplier = S.record_num
    LEFT JOIN feedback F ON S.record_num = F.supplier_id AND F.public = 1 AND ( F.feedback_date > (NOW() - INTERVAL 3 YEAR) OR F.one_year_submitted > (NOW() - INTERVAL 3 YEAR) )
    GROUP BY S.record_num;";
    
    $result = db_query($SQL);
    $supplierCount = mysqli_num_rows($result);
    if ($supplierCount > 0) {
        $insertSQL = "INSERT INTO cache_installer_ratings (supplier, supplierName, parentUseReview, trustBadgeUseParent, reviewonly, parent, parentName, feedbackCount, feedbackRating, status, extraLeads, lastYearLeads) VALUES ";
        $insertValues = [];
        while ($supplier = mysqli_fetch_assoc($result)) {
            $rating = ( $supplier['rating_value'] + $supplier['rating_system_quality'] + $supplier['rating_customer_service'] + $supplier['rating_installation'] ) / 4;
            $insertValues []= "('{$supplier['record_num']}', '{$supplier['company']}', '{$supplier['parentUseReview']}', '{$supplier['trustBadgeUseParent']}', '{$supplier['reviewonly']}', '{$supplier['parent']}', '{$supplier['parentName']}', '{$supplier['reviews_count']}', '{$rating}', '{$supplier['status']}', '{$supplier['extraLeads']}', '{$supplier['lastYearLeads']}')";  
        }
        $insertSQL .= implode(', ', $insertValues);
        db_query("TRUNCATE cache_installer_ratings");
        db_query($insertSQL);
    }