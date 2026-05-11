<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// === Carrega variáveis do .env ===
$envFile = __DIR__ . '/../.env';
$env = file_exists($envFile) ? parse_ini_file($envFile) : [];

// === Verificação server-side de headers suspeitos ===
$serverFlags = [];

// Bots geralmente não enviam Accept-Language
if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $serverFlags[] = 'no_accept_language';
}

// Bots geralmente não enviam Accept-Encoding
if (empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
    $serverFlags[] = 'no_accept_encoding';
}

// Accept genérico demais (sem text/html)
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if ($accept === '' || $accept === '*/*') {
    $serverFlags[] = 'generic_accept';
}

// Sem Referer (acesso direto à API é suspeito)
if (empty($_SERVER['HTTP_REFERER'])) {
    $serverFlags[] = 'no_referer';
}

// Connection: close (bots antigos)
$conn = $_SERVER['HTTP_CONNECTION'] ?? '';
if (strtolower($conn) === 'close') {
    $serverFlags[] = 'connection_close';
}

// UA vazio ou muito curto
$uaServer = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strlen($uaServer) < 20) {
    $serverFlags[] = 'short_ua';
}

// UA com padrões de ferramentas
if (preg_match('/python|java\/|httpclient|libwww|perl|ruby|go-http|curl|wget|httpie|scrapy|mechanize|aiohttp|node-fetch|axios|got\/|undici|okhttp|dart|apache-http/i', $uaServer)) {
    $serverFlags[] = 'tool_ua';
}

$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';

// Em desenvolvimento, usa IP fixo para testes
if (in_array($ip, ['127.0.0.1', '::1'], true)) {
    $ip = $env['TEST_IP'] ?? '127.0.0.1';
}

if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    echo json_encode(['status' => 'erro']);
    exit;
}

$apiKey = $env['PROXYCHECK_API_KEY'] ?? '';

$flags = [
    'key' => $apiKey,
    'vpn' => 3,
    'asn' => 1,
];

$url = 'https://proxycheck.io/v2/' . urlencode($ip) . '?' . http_build_query($flags);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'User-Agent: MeuApp/1.0',
    ],
]);

$response = curl_exec($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['status' => 'erro']);
    exit;
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE || ($data['status'] ?? '') !== 'ok' || !isset($data[$ip])) {
    http_response_code(502);
    echo json_encode(['status' => 'erro']);
    exit;
}

$info = $data[$ip];

echo json_encode([
    'status'       => 'success',
    'ip'           => $ip,
    'asn'          => $info['asn'] ?? null,
    'hostname'     => $info['hostname'] ?? null,
    'provider'     => $info['provider'] ?? null,
    'organisation' => $info['organisation'] ?? null,
    'isocode'      => $info['isocode'] ?? null,
    'regioncode'   => $info['regioncode'] ?? null,
    'city'         => $info['city'] ?? null,
    'proxy'        => $info['proxy'] ?? null,
    'vpn'          => $info['vpn'] ?? null,
    'server_flags' => $serverFlags,
]);
