<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../vendor/autoload.php';

use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\OperatingSystem;

try {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (trim($userAgent) === '') {
        http_response_code(400);
        echo json_encode(['status' => 'erro']);
        exit;
    }

    $dd = new DeviceDetector($userAgent);
    $dd->parse();

    $isBot = $dd->isBot();

    $client = $dd->getClient() ?: [];
    $os     = $dd->getOs() ?: [];

    $osShortName = $os['short_name'] ?? '';
    $osFamily    = $osShortName ? OperatingSystem::getOsFamily($osShortName) : null;

    echo json_encode([
        'status'                => 'success',
        'bot'                   => $isBot ? 'true' : 'false',
        'client_name'           => $client['name'] ?? null,
        'client_type'           => $client['type'] ?? null,
        'client_version'        => $client['version'] ?? null,
        'device_brand'          => $dd->getBrandName() ?: null,
        'device_model'          => $dd->getModel() ?: null,
        'device_type'           => $dd->getDeviceName() ?: null,
        'os_name'               => $os['name'] ?? null,
        'os_platform'           => $os['platform'] ?? null,
        'os_version'            => $os['version'] ?? null,
        'os_family'             => $osFamily,
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'erro']);
}
