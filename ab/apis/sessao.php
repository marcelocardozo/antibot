<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'erro']);
    exit;
}

$db = new SQLite3(__DIR__ . '/../db/antibot.db');

// Se tem id, consulta pelo id
$id = $_GET['id'] ?? null;

if ($id !== null && $id !== '') {
    $stmt = $db->prepare('SELECT bloqueado FROM acessos WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', (int) $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();

    if (!$row) {
        echo json_encode(['status' => 'novo']);
        exit;
    }

    echo json_encode([
        'status' => $row['bloqueado'] === 'true' ? 'bloqueado' : 'aprovado',
    ]);
    exit;
}

// Sem id — busca registro semelhante pelo IP + fingerprint do navegador
$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';

if ($ip === '') {
    $db->close();
    echo json_encode(['status' => 'novo']);
    exit;
}

// Monta query com os campos disponíveis
$conditions = ['ip = :ip'];
$params = [':ip' => $ip];

$fingerprint = [
    'client_name'  => $_GET['client_name'] ?? null,
    'os_name'      => $_GET['os_name'] ?? null,
    'device_type'  => $_GET['device_type'] ?? null,
];

foreach ($fingerprint as $col => $val) {
    if ($val !== null && $val !== '') {
        if ($col === 'client_name') {
            $conditions[] = "$col LIKE :$col";
            $params[":$col"] = $val . '%';
        } else {
            $conditions[] = "$col = :$col";
            $params[":$col"] = $val;
        }
    }
}

$sql = 'SELECT bloqueado FROM acessos WHERE '
    . implode(' AND ', $conditions)
    . ' ORDER BY id DESC LIMIT 1';

$stmt = $db->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, SQLITE3_TEXT);
}
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);

// Se não achou com fingerprint exato, fallback só por IP
// (mesmo usuário pode acessar pelo computador e celular no mesmo IP)
if (!$row) {
    $stmt2 = $db->prepare('SELECT bloqueado FROM acessos WHERE ip = :ip ORDER BY id DESC LIMIT 1');
    $stmt2->bindValue(':ip', $ip, SQLITE3_TEXT);
    $result2 = $stmt2->execute();
    $row = $result2->fetchArray(SQLITE3_ASSOC);
}

$db->close();

if (!$row) {
    echo json_encode(['status' => 'novo']);
    exit;
}

echo json_encode([
    'status' => $row['bloqueado'] === 'true' ? 'bloqueado' : 'aprovado',
]);
