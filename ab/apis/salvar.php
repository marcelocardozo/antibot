<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'erro']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'erro']);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');
$input['data_hora'] = date('Y-m-d H:i:s');

$db = new SQLite3(__DIR__ . '/../db/antibot.db');

$stmt = $db->prepare('INSERT INTO acessos (
    data_hora, ip, url, asn, hostname, provider, organisation,
    isocode, regioncode, city, proxy, vpn, bot,
    client_name, client_type, client_version,
    device_brand, device_model, device_type,
    os_name, os_platform, os_version, os_family, bloqueado, motivo_bloqueio
) VALUES (
    :data_hora, :ip, :url, :asn, :hostname, :provider, :organisation,
    :isocode, :regioncode, :city, :proxy, :vpn, :bot,
    :client_name, :client_type, :client_version,
    :device_brand, :device_model, :device_type,
    :os_name, :os_platform, :os_version, :os_family, :bloqueado, :motivo_bloqueio
)');

$campos = [
    'data_hora', 'ip', 'url', 'asn', 'hostname', 'provider', 'organisation',
    'isocode', 'regioncode', 'city', 'proxy', 'vpn', 'bot',
    'client_name', 'client_type', 'client_version',
    'device_brand', 'device_model', 'device_type',
    'os_name', 'os_platform', 'os_version', 'os_family', 'bloqueado', 'motivo_bloqueio',
];

foreach ($campos as $campo) {
    $valor = isset($input[$campo]) && $input[$campo] !== null ? (string) $input[$campo] : null;
    $stmt->bindValue(':' . $campo, $valor, $valor === null ? SQLITE3_NULL : SQLITE3_TEXT);
}

$result = $stmt->execute();

if (!$result) {
    http_response_code(500);
    echo json_encode(['status' => 'erro']);
    $db->close();
    exit;
}

$id = $db->lastInsertRowID();
$db->close();

echo json_encode(['status' => 'success', 'id' => $id]);
