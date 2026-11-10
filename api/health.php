<?php
/**
 * api/health.php — Cek status layanan analisis FastAPI (proxy sisi server).
 * Dipakai kartu "Status Layanan" di dashboard admin.
 */

require_once __DIR__ . '/../includes/auth.php';

$ch = curl_init(API_BASE . '/health');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
$res = curl_exec($ch);
curl_close($ch);

$online = false;
if ($res !== false) {
    $j = json_decode($res, true);
    $online = !empty($j['ok']);
}

json_out(['ok' => true, 'api' => $online ? 'online' : 'offline',
          'url' => API_BASE]);
