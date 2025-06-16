<?php
	// load global libraries
	$noSession = 1;
	require_once('global.php');

	global $nowSql;

    $sgAccounts = [
        'Main' => ['main' => 'main_contacts'],
        'AutoResponder' => ['solarquotes_autoresponders' => 'autoresponder_contacts'],
        'Installers' => ['solarquotes_installers' => 'installers_contacts'],
        'Affiliates' => ['solarquotes_affiliates' => 'affiliates_mail'],
        'Communications' => ['solarquotes_installer_communications' => 'installer_communications_contacts']
    ];

    $csvdata = [];
    
    foreach($sgAccounts as $test => $details){

        $csvdata[] = ['SendGrid Account: '.$test];

        $csvdata[] = [
            'Date',
            'Total Sent',
            'Total Delivered',
            'Total Opened',
            'Delivery Rate',
            'Open Rate',
        ];

        echo "\n---------------------\nAccount: $test\n";
        $monthSent = 0;
        $monthDelivered = 0;
        $monthOpened = 0;
        $monthDeliveryRates = [];
        $monthOpenRates = [];

        for ($day = 1; $day <= 30; $day++) {

            $date = date('Y-m-d', strtotime("-$day day"));

            foreach($details as $account => $key){
                $stats = GetSGEmailStats($date, $date, $account);
                if(!isset($stats[0]['stats'][0]['metrics'])){
                    error_log('Error retrieving SendGrid Email Data - '."$account\n".print_r($stats['errors'][0]['message'],true));
                    $stats[0]['stats'][0]['metrics']['delivered'] = 0; // Set delivered to 0 to indicate a problem
                }
                $stats = $stats[0]['stats'][0]['metrics'];
                $delivered = $stats['delivered'];
                $deliveryRate = 0;
                if($delivered > 0)
                    $deliveryRate = (1 - ($stats['blocks'] + $stats['bounces'] + $stats['deferred'] + $stats['invalid_emails']) / $stats['delivered']) * 100;
                $deliveryRate = round($deliveryRate, 2);
                $requests = intval($stats['requests'] ?? 0);
                $uniqueOpens = intval($stats['unique_opens'] ?? 0);
                $openRate = ($requests > 0) ? ($uniqueOpens / $requests) * 100 : 0;
                $openRate = round($openRate, 2);

                $monthSent += $requests;
                $monthDelivered += $delivered;
                $monthOpened += $uniqueOpens;
                if ($requests > 0) {
                    $monthDeliveryRates[] = $deliveryRate;
                }

                echo 'Date: '.date('Y-m-d', strtotime("-$day day"));
                echo "\nTotal Sent: ".$requests;
                echo "\nTotal Delivered: ".$delivered;
                echo "\nTotal Opened: ".$uniqueOpens;
                echo "\nDelivery Rate: ".$deliveryRate;
                echo "\nOpen Rate: ".$openRate;
                echo "\n\n";

                $csvdata[] = [
                    date('Y-m-d', strtotime("-$day day")),
                    $requests,
                    $delivered,
                    $uniqueOpens,
                    $deliveryRate,
                    $openRate,
                ];

            }
        }

        $monthDeliveryRate = count($monthDeliveryRates) > 0 ? array_sum($monthDeliveryRates)/count($monthDeliveryRates) : 0;
        $monthDeliveryRate = round($monthDeliveryRate, 2);
        $monthOpenRate = ($monthSent > 0) ? ($monthOpened / $monthSent) * 100 : 0;
        $monthOpenRate = round($monthOpenRate, 2);
        echo "------------\n\n";
        echo 'Whole Month Stats';
        echo "\nTotal Sent: ".$monthSent;
        echo "\nTotal Delivered: ".$monthDelivered;
        echo "\nTotal Opened: ".$monthOpened;
        echo "\nDelivery Rate: ".$monthDeliveryRate;
        echo "\nOpen Rate: ".$monthOpenRate;
        echo "\n\n";

        $csvdata[] = [
            'Total',
            $monthSent,
            $monthDelivered,
            $monthOpened,
            $monthDeliveryRate,
            $monthOpenRate,
        ];

        $csvdata[] = [];
        $csvdata[] = [];
    }

    $filename = 'monthlyStatsSendGrid.csv';
    $file = fopen($filename, 'w');
    foreach ($csvdata as $fields) {
        fputcsv($file, $fields);
    }
    fclose($file);

?>