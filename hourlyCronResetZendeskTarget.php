<?php
include('global.php');

global $nowSql, $siteURLSSL, $zenDeskSubdomain, $zenDeskEmail, $zenDeskKey, $zenDeskAssignees, $zendeskResetTargetId;

$client = new \Zendesk\API\Client($zenDeskSubdomain, $zenDeskEmail);
$client->setAuth('token', $zenDeskKey);

$response = $client->targets()->find([
    'id' => $zendeskResetTargetId
]);

if(! $response->target->active){
    $client->targets()->update([
        'id' => $zendeskResetTargetId,
        'active' => true
    ]);

    echo 'Target reactivated' . PHP_EOL;
} else {
    echo 'Target already active' . PHP_EOL;
}