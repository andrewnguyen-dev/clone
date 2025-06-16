<?php

    include 'global.php';

    // Delete all files that are older then 7 days
    exec('find /var/www/private_html_2024/php/temp/* -mtime +7 -exec rm {} \;');

    // Call CakePHP endpoint to clean up cakephp tmp files
    $endpoint = $cakePhpWsURL . 'cleanup_files/';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $cakePhpWSApiKey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    echo $response . "\n";