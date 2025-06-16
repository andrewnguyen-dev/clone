<?php
    // load global libraries
    include('global.php');
    set_time_limit(0);

    $timeframeBreakup = array();

    // Remove all items from the table
    $SQL = "DELETE FROM cache_evcharger_feedback_total";
    db_query($SQL);

    // Select all feedback that has been marked as public
    $SQL = "SELECT DISTINCT EVR.manufacturer FROM evcharger_reviews EVR ";
    $SQL .= "INNER JOIN parser_solaraccreditation_evcharger PSEV ON EVR.manufacturer = PSEV.Manufacturer ";
    $SQL .= "WHERE EVR.public = 1 AND EVR.manufacturer != ''";

    $evchargers = db_query($SQL);

    while ($evcharger = $evchargers->fetch_assoc()) {
        resetTimeframeBreakup();
        extract(htmlentitiesRecursive($evcharger), EXTR_PREFIX_ALL, 'ev');
        
        if(stristr($ev_manufacturer, '(other)') === FALSE) {
            $ev_evcharger_brand_URL = sanitizeURL($ev_manufacturer);
            $ev_manufacturer = mysqli_real_escape_string($_connection, $ev_manufacturer);			

            getEVChargerFeedbackCount($ev_manufacturer);
            getEVChargerFeedbackTotal($ev_manufacturer);
            getEVChargerFeedbackTotalWeighted($ev_manufacturer);
            
            foreach($timeframeBreakup as $idx => $timeframe) {
                $feedbackCount = $timeframe['count'];
                $feedbackTotal = $timeframe['total'];
                $feedbackTotalWeighted = $timeframe['total_weighted'];

                db_query("INSERT INTO cache_evcharger_feedback_total(manufacturerName, manufacturerURLName, count, total, totalWeighted, timeframe) VALUES ('{$ev_manufacturer}', '{$ev_evcharger_brand_URL}', {$feedbackCount}, {$feedbackTotal}, {$feedbackTotalWeighted}, '{$idx}')");
            }
        }
    }

    function getEVChargerFeedbackCount($manufacturer) {
        global $timeframeBreakup;

        foreach($timeframeBreakup as $idx => $timeframe) {
            $from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');
            $SQL = "
                SELECT COUNT(*)
                FROM evcharger_reviews EVR
                LEFT JOIN feedback F ON F.record_num = EVR.feedback_id
                WHERE 
                    (EVR.public = 1 OR F.public = 1)
                    AND EVR.manufacturer = '{$manufacturer}'
                    AND EVR.rate_avg >=1
                    AND (EVR.verified = 'Yes' OR F.verified = 'Yes')
                    AND IF(
                        EVR.feedback_id is null, 
                        EVR.review_date, 
                        IF(F.one_year_submitted is null, F.feedback_date, F.one_year_submitted)
                    ) >= '{$from}';
            ";			
            $timeframeBreakup[$idx]['count'] = db_getVal($SQL);			
        }		
    }

    function getEVChargerFeedbackTotal($manufacturer) {
        global $timeframeBreakup;

        foreach($timeframeBreakup as $idx => $timeframe) {
            $from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');
            $SQL = "
                SELECT IFNULL(SUM(EVR.rate_avg), 0)
                FROM evcharger_reviews EVR
                LEFT JOIN feedback F ON F.record_num = EVR.feedback_id
                WHERE 
                    (EVR.public = 1 OR F.public = 1)
                    AND EVR.manufacturer = '{$manufacturer}'
                    AND EVR.rate_avg >=1
                    AND (EVR.verified = 'Yes' OR F.verified = 'Yes')
                    AND IF(
                        EVR.feedback_id is null, 
                        EVR.review_date, 
                        IF(F.one_year_submitted is null, F.feedback_date, F.one_year_submitted)
                    ) >= '{$from}';
            ";
            $timeframeBreakup[$idx]['total'] = db_getVal($SQL);
        }
    }

    function getEVChargerFeedbackTotalWeighted($manufacturer) {
        global $timeframeBreakup;
        
        foreach($timeframeBreakup as $idx => $timeframe) {
            $total = 0;

            $from = $timeframeBreakup[$idx]['lowerlimit']->format('Y-m-d');
            $SQL = "
                SELECT EVR.rate_avg
                FROM evcharger_reviews EVR
                LEFT JOIN feedback F ON F.record_num = EVR.feedback_id
                WHERE 
                    (EVR.public = 1 OR F.public = 1)
                    AND EVR.manufacturer = '{$manufacturer}'
                    AND EVR.rate_avg >=1
                    AND (EVR.verified = 'Yes' OR F.verified = 'Yes')
                    AND IF(
                        EVR.feedback_id is null, 
                        EVR.review_date, 
                        IF(F.one_year_submitted is null, F.feedback_date, F.one_year_submitted)
                    ) >= '{$from}';
            ";
            $ratings = db_query($SQL);

            while ($rating = $ratings->fetch_assoc()) {
                extract(htmlentitiesRecursive($rating), EXTR_PREFIX_ALL, 'r');			
                $total += rawToWeightedRating($r_rate_avg);
            }			
            $timeframeBreakup[$idx]['total_weighted'] = $total;
        }	
    }

    function rawToWeightedRating($raw) {
        switch ($raw) {
            case 1:
                return -3;
                break;
            case 2:
                return -2;
                break;
            case 3:
                return -1;
                break;
            case 4:
                return 1;
                break;
            case 5:
                return 2;
                break;
            default:
                return 0;
                break;
        }
    }

    function resetTimeframeBreakup() {
        global $timeframeBreakup;
        $timeframeBreakup = array(
            '6m' => array(
                'lowerlimit' => (new \DateTime())->modify('-6 months'),
                'total' => 0, 'total_weighted' => 0, 'count' => 0
            ),
            '12m' => array(
                'lowerlimit' => (new \DateTime())->modify('-12 months'),
                'total' => 0, 'total_weighted' => 0, 'count' => 0
            ),
            'all' => array(
                'lowerlimit' => (new \DateTime('19700101')),
                'total' => 0, 'total_weighted' => 0, 'count' => 0
            )
        );
    }
?>