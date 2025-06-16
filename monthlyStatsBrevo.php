<?php
	// load global libraries
	$noSession = 1;
	require_once('global.php');

    global $nowSql, $brevo_api;

    $headers = [
        'Content-Type: application/json',
        'api-key: ' . $brevo_api,
    ];

    $csvdata = [];

    $csvdata[] = ['Type: Campaigns'];
    $csvdata[] = [
        'Date',
        'Total Sent',
        'Total Delivered',
        'Total Opened',
        'Delivery Rate',
        'Open Rate',
    ];
    
    $monthSent = 0;
    $monthDelivered = 0;
    $monthOpened = 0;
    $monthDeliveryRates = [];
    $monthOpenRates = [];
    
    for ($day = 1; $day <= 30; $day++) {
        
        $nextday = $day - 1;
        
        $payload = [
            'startDate' => gmdate('Y-m-d\TH:i:s.v\Z', strtotime("-$day day")),
            'endDate' => gmdate('Y-m-d\TH:i:s.v\Z', strtotime("-$nextday day")),
            'excludeHtmlContent' => true,
            'limit' => 100,
        ]; 
        
        $apiURL = 'https://api.brevo.com/v3/emailCampaigns';
        $apiURL .= '?' . http_build_query($payload);

        $ch = curl_init($apiURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if ($response === false) {
            echo 'cURL error: ' . curl_error($ch);
        } else {

            $result = json_decode($response, true);
            $campaigns = $result['campaigns'] ?? [];
            if (empty($campaigns)) $campaigns = [];
            $sent = [];
            $delivered = [];
            $opens = [];
            foreach($campaigns as $campaign) {
                $campaignstats = $campaign['statistics']['campaignStats'] ?? [];
                foreach($campaignstats as $stat) {
                    $sent[] = $stat['sent'] ?? '';
                    $delivered[] = $stat['delivered'] ?? '';
                    $opens[] = $stat['uniqueViews'] ?? '';
                }
            }
            $sent = array_filter($sent, fn($value) => trim($value) !== "");
            $totalsent = array_sum($sent);
            $monthSent += $totalsent;
            $delivered = array_filter($delivered, fn($value) => trim($value) !== "");
            $totaldelivered = array_sum($delivered);
            $monthDelivered += $totaldelivered;
            $opens = array_filter($opens, fn($value) => trim($value) !== "");
            $totalopens = array_sum($opens);
            $monthOpened += $totalopens;
            $deliveryrate = ($totalsent > 0) ? round(($totaldelivered / $totalsent) * 100) : '';
            $openrate = ($totalsent > 0) ? round(($totalopens / $totalsent) * 100) : '';
            $monthDeliveryRates[] = $deliveryrate;
            $monthOpenRates[] = $openrate;

        }

        curl_close($ch);

        echo 'Date: '.gmdate('Y-m-d', strtotime("-$day day"));
        echo "\nTotal Sent: ".$totalsent;
        echo "\nTotal Delivered: ".$totaldelivered;
        echo "\nTotal Opened: ".$totalopens;
        echo "\nDelivery Rate: ".$deliveryrate;
        echo "\nOpen Rate: ".$openrate;
        echo "\n\n";

        $csvdata[] = [
            gmdate('Y-m-d', strtotime("-$day day")),
            $totalsent,
            $totaldelivered,
            $totalopens,
            $deliveryrate,
            $openrate,
        ];

    }

    $monthOpenRate = ($monthSent > 0) ? round(($monthOpened / $monthSent) * 100) : 0;
    $monthDeliveryRate = ($monthSent > 0) ? round(($monthDelivered / $monthSent) * 100) : 0;
    $monthDeliveryRates = array_filter($monthDeliveryRates, fn($value) => trim($value) !== "");
    $monthOpenRates = array_filter($monthOpenRates, fn($value) => trim($value) !== "");
    $avgDeliveryRate = count($monthDeliveryRates) > 0 ? round(array_sum($monthDeliveryRates)/count($monthDeliveryRates)) : 0;
    $avgOpenRate = count($monthOpenRates) > 0 ? round(array_sum($monthOpenRates)/count($monthOpenRates)) : 0;
    echo "------------------\n\n";
    echo 'Whole Month Stats';
    echo "\nTotal Sent: ".$monthSent;
    echo "\nTotal Delivered: ".$monthDelivered;
    echo "\nTotal Opened: ".$monthOpened;
    echo "\nOverall Delivery Rate: ".$monthDeliveryRate;
    echo "\nOverall Open Rate: ".$monthOpenRate;
    echo "\nAverage Delivery Rate: ".$avgDeliveryRate;
    echo "\nAverage Open Rate: ".$avgOpenRate;
    echo "\n\n";

    $csvdata[] = [
        'Total',
        $monthSent,
        $monthDelivered,
        $monthOpened,
        $monthDeliveryRate,
        $monthOpenRate,
    ];

    $csvdata[] = [
        'Average Rates',
        '',
        '',
        '',
        $avgDeliveryRate,
        $avgOpenRate,
    ];

    $csvdata[] = [];
    $csvdata[] = [];

    // Transactional Email Stats

    $csvdata[] = ['Type: Transactional Emails'];

    echo "------------------\n\n";
    echo 'Transactional Email Stats';
    echo "\n\n";

    $monthSent = 0;
    $monthDelivered = 0;
    $monthOpened = 0;
    $monthDeliveryRates = [];
    $monthOpenRates = [];

    $startdate = gmdate('Y-m-d', strtotime("-30 day"));
    $enddate = gmdate('Y-m-d', strtotime("-1 day"));
    $payload = [
        'startDate' => $startdate,
        'endDate' => $enddate
    ];

    $apiURL = 'https://api.brevo.com/v3/smtp/statistics/reports';
    $apiURL .= '?' . http_build_query($payload);

    $ch = curl_init($apiURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);

    if ($response === false) {
        echo 'cURL error: ' . curl_error($ch);
        $csvdata[] = ['API Request Error'];
    } else {
        $result = json_decode($response, true);
        $reports = $result['reports'] ?? [];
        if (!empty($reports)) {
            foreach($reports as $report) {
                $date = $report['date'] ?? '';
                $sent = $report['requests'] ?? 0;
                $delivered = $report['delivered'] ?? 0;
                $opens = $report['uniqueOpens'] ?? 0;
                $deliveryrate = ($sent > 0) ? round(($delivered / $sent) * 100) : '';
                $openrate = ($sent > 0) ? round(($opens / $sent) * 100) : '';

                $monthSent += $sent;
                $monthDelivered += $delivered;
                $monthOpened += $opens;
                $monthDeliveryRates[] = $deliveryrate;
                $monthOpenRates[] = $openrate;

                echo 'Date: '.$date;
                echo "\nTotal Sent: ".$sent;
                echo "\nTotal Delivered: ".$delivered;
                echo "\nTotal Opened: ".$opens;
                echo "\nDelivery Rate: ".$deliveryrate;
                echo "\nOpen Rate: ".$openrate;
                echo "\n\n";

                $csvdata[] = [
                    $date,
                    $sent,
                    $delivered,
                    $opens,
                    $deliveryrate,
                    $openrate,
                ];
            }
        }

        $monthOpenRate = ($monthSent > 0) ? round(($monthOpened / $monthSent) * 100) : 0;
        $monthDeliveryRate = ($monthSent > 0) ? round(($monthDelivered / $monthSent) * 100) : 0;
        $monthDeliveryRates = array_filter($monthDeliveryRates, fn($value) => trim($value) !== "");
        $monthOpenRates = array_filter($monthOpenRates, fn($value) => trim($value) !== "");
        $avgDeliveryRate = count($monthDeliveryRates) > 0 ? round(array_sum($monthDeliveryRates)/count($monthDeliveryRates)) : 0;
        $avgOpenRate = count($monthOpenRates) > 0 ? round(array_sum($monthOpenRates)/count($monthOpenRates)) : 0;

        echo "------------------\n\n";
        echo 'Whole Month Stats';
        echo "\nTotal Sent: ".$monthSent;
        echo "\nTotal Delivered: ".$monthDelivered;
        echo "\nTotal Opened: ".$monthOpened;
        echo "\nOverall Delivery Rate: ".$monthDeliveryRate;
        echo "\nOverall Open Rate: ".$monthOpenRate;
        echo "\nAverage Delivery Rate: ".$avgDeliveryRate;
        echo "\nAverage Open Rate: ".$avgOpenRate;
        echo "\n\n";

        $csvdata[] = [
            'Total',
            $monthSent,
            $monthDelivered,
            $monthOpened,
            $monthDeliveryRate,
            $monthOpenRate,
        ];
    
        $csvdata[] = [
            'Average Rates',
            '',
            '',
            '',
            $avgDeliveryRate,
            $avgOpenRate,
        ];
        
    }

    $filename = 'monthlyStatsBrevo.csv';
    $file = fopen($filename, 'w');
    foreach ($csvdata as $fields) {
        fputcsv($file, $fields);
    }
    fclose($file);