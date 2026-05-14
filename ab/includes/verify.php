<?php
// Auto-detect web path to the ab/ folder (works regardless of project location)
$_abDir = dirname(__DIR__);
$_abDocRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_abWebPath = str_replace($_abDocRoot, '', str_replace('\\', '/', $_abDir));

// ── Verificação server-side por IP + fingerprint ──
// Antes do JS carregar, checa se este visitante já tem registro bloqueado no banco.
// Pega bots que pulam o antibot.js e tentam acessar a página protegida direto.
$_abIp = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';

// Em desenvolvimento local, substituir localhost pelo TEST_IP do .env
if (in_array($_abIp, ['127.0.0.1', '::1'], true)) {
    $_abEnvFile = __DIR__ . '/../.env';
    $_abEnv = file_exists($_abEnvFile) ? parse_ini_file($_abEnvFile) : [];
    if (!empty($_abEnv['TEST_IP'])) {
        $_abIp = $_abEnv['TEST_IP'];
    }
}

if ($_abIp !== '') {
    $_abUa = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Extrai fingerprint do User-Agent
    $_abClientName = null;
    if (preg_match('/Edg\//i', $_abUa)) $_abClientName = 'Edge';
    elseif (preg_match('/OPR\/|Opera/i', $_abUa)) $_abClientName = 'Opera';
    elseif (preg_match('/Firefox\//i', $_abUa)) $_abClientName = 'Firefox';
    elseif (preg_match('/Chrome\//i', $_abUa)) $_abClientName = 'Chrome';
    elseif (preg_match('/Safari\//i', $_abUa)) $_abClientName = 'Safari';

    $_abOsName = null;
    if (preg_match('/Windows/i', $_abUa)) $_abOsName = 'Windows';
    elseif (preg_match('/Android/i', $_abUa)) $_abOsName = 'Android';
    elseif (preg_match('/iPhone|iPad|iPod/i', $_abUa)) $_abOsName = 'iOS';
    elseif (preg_match('/Mac OS/i', $_abUa)) $_abOsName = 'Mac';
    elseif (preg_match('/Linux/i', $_abUa)) $_abOsName = 'GNU/Linux';

    $_abDeviceType = 'desktop';
    if (preg_match('/Mobi/i', $_abUa)) $_abDeviceType = 'smartphone';
    elseif (preg_match('/Tablet|iPad/i', $_abUa)) $_abDeviceType = 'tablet';

    // Monta query com fingerprint
    $_abConditions = ['ip = :ip'];
    $_abParams = [':ip' => $_abIp];

    if ($_abClientName !== null) {
        $_abConditions[] = 'client_name LIKE :client_name';
        $_abParams[':client_name'] = $_abClientName . '%';
    }
    if ($_abOsName !== null) {
        $_abConditions[] = 'os_name = :os_name';
        $_abParams[':os_name'] = $_abOsName;
    }
    $_abConditions[] = 'device_type = :device_type';
    $_abParams[':device_type'] = $_abDeviceType;

    $_abDb = new SQLite3(__DIR__ . '/../db/antibot.db');
    $_abStmt = $_abDb->prepare(
        'SELECT bloqueado FROM acessos WHERE '
        . implode(' AND ', $_abConditions)
        . ' ORDER BY id DESC LIMIT 1'
    );
    foreach ($_abParams as $_abKey => $_abVal) {
        $_abStmt->bindValue($_abKey, $_abVal, SQLITE3_TEXT);
    }
    $_abResult = $_abStmt->execute();
    $_abRow = $_abResult->fetchArray(SQLITE3_ASSOC);

    // Se não achou com fingerprint exato, fallback só por IP
    // (mesmo usuário pode acessar pelo computador e celular no mesmo IP)
    if (!$_abRow) {
        $_abStmt2 = $_abDb->prepare(
            'SELECT bloqueado FROM acessos WHERE ip = :ip ORDER BY id DESC LIMIT 1'
        );
        $_abStmt2->bindValue(':ip', $_abIp, SQLITE3_TEXT);
        $_abResult2 = $_abStmt2->execute();
        $_abRow = $_abResult2->fetchArray(SQLITE3_ASSOC);
    }

    $_abDb->close();

    // Se tem registro bloqueado, redireciona imediatamente
    if ($_abRow && $_abRow['bloqueado'] === 'true') {
        header('Location: ' . $_abWebPath . '/templates/404.html');
        exit;
    }

    // Se não tem nenhum registro, nunca passou pelo antibot
    if (!$_abRow) {
        header('Location: ' . $_abWebPath . '/templates/404.html');
        exit;
    }
}
?>
<script>
(function () {
    var AB_PATH = <?= json_encode($_abWebPath) ?>;
    var id = sessionStorage.getItem('ab_id_verify');

    if (!id) {
        window.location.href = AB_PATH + '/templates/404.html';
        return;
    }

    fetch(AB_PATH + '/apis/sessao.php?id=' + encodeURIComponent(id))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.status !== 'aprovado') {
                sessionStorage.removeItem('ab_id_verify');
                window.location.href = AB_PATH + '/templates/404.html';
            }
        })
        .catch(function () {
            window.location.href = AB_PATH + '/templates/404.html';
        });
})();
</script>
