<?php
// MUS SOU MANO — live server status JSON API for the auto-refreshing bar.
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$out = ['servers' => [], 'online' => 0];
foreach ($cfg['servers'] as $srv) {
    $info = a2s_info($cfg['public_ip'], (int)$srv['port'], 0.5);
    $entry = [
        'name'   => $srv['name'],
        'icon'   => $srv['icon'],
        'port'   => (int)$srv['port'],
        'up'     => $info !== null,
        'humans' => $info ? (int)$info['humans'] : 0,
        'bots'   => $info ? (int)$info['bots'] : 0,
        'max'    => $info ? (int)$info['max'] : 0,
        'map'    => $info ? $info['map'] : '',
    ];
    if ($info) $out['online'] += (int)$info['humans'];
    $out['servers'][] = $entry;
}

echo json_encode($out);
