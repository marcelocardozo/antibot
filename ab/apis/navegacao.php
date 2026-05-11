<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'erro']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || empty($input['url'])) {
    http_response_code(400);
    echo json_encode(['status' => 'erro']);
    exit;
}

$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';

// Em desenvolvimento, usa IP fixo do .env
if (in_array($ip, ['127.0.0.1', '::1'], true)) {
    $envFile = __DIR__ . '/../.env';
    $env = file_exists($envFile) ? parse_ini_file($envFile) : [];
    $ip = $env['TEST_IP'] ?? $ip;
}

date_default_timezone_set('America/Sao_Paulo');

$acessoId = $input['acesso_id'] ?? null;

$db = new SQLite3(__DIR__ . '/../db/antibot.db');
$stmt = $db->prepare('INSERT INTO navegacao (data_hora, ip, url, referrer, acesso_id) VALUES (:data_hora, :ip, :url, :referrer, :acesso_id)');
$stmt->bindValue(':data_hora', date('Y-m-d H:i:s'), SQLITE3_TEXT);
$stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
$stmt->bindValue(':url', $input['url'], SQLITE3_TEXT);
$stmt->bindValue(':referrer', $input['referrer'] ?? null, $input['referrer'] ? SQLITE3_TEXT : SQLITE3_NULL);
$stmt->bindValue(':acesso_id', $acessoId, $acessoId !== null ? SQLITE3_INTEGER : SQLITE3_NULL);
$stmt->execute();
$db->close();

echo json_encode(['status' => 'success']);
