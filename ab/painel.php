<?php
session_start();
define('ANTIBOT_INTERNAL', true);
$config = include __DIR__ . '/config.php';

$painelUsuario = $config['painel_usuario'] ?? '';
$painelSenha = $config['painel_senha'] ?? '';
$loginErro = false;

if ($painelUsuario !== '' && $painelSenha !== '') {
    // Logout
    if (($_GET['acao'] ?? '') === 'logout') {
        session_destroy();
        header('Location: painel.php');
        exit;
    }

    // Processar login
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'login') {
        if ($_POST['usuario'] === $painelUsuario && $_POST['senha'] === $painelSenha) {
            $_SESSION['ab_painel_auth'] = true;
            header('Location: painel.php');
            exit;
        } else {
            $loginErro = true;
        }
    }

    // Se não autenticado, mostrar form de login
    if (empty($_SESSION['ab_painel_auth'])) {
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Antibot - Login</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #0f1117;
                    color: #e1e4e8;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .login-box {
                    background: #161b22;
                    border: 1px solid #30363d;
                    border-radius: 10px;
                    padding: 36px 32px;
                    width: 100%;
                    max-width: 380px;
                }
                .login-box h1 {
                    font-size: 1.3rem;
                    font-weight: 600;
                    color: #f0f0f0;
                    margin-bottom: 24px;
                    text-align: center;
                }
                .login-box label {
                    display: block;
                    font-size: 0.82rem;
                    color: #8b949e;
                    margin-bottom: 6px;
                }
                .login-box input[type="text"],
                .login-box input[type="password"] {
                    width: 100%;
                    padding: 10px 12px;
                    font-size: 0.9rem;
                    background: #0f1117;
                    border: 1px solid #30363d;
                    border-radius: 6px;
                    color: #e1e4e8;
                    outline: none;
                    margin-bottom: 16px;
                }
                .login-box input:focus { border-color: #1f6feb; }
                .login-box button {
                    width: 100%;
                    padding: 10px;
                    font-size: 0.9rem;
                    font-weight: 600;
                    background: #1f6feb;
                    border: none;
                    border-radius: 6px;
                    color: #fff;
                    cursor: pointer;
                }
                .login-box button:hover { background: #1a5fd1; }
                .login-erro {
                    padding: 10px 14px;
                    margin-bottom: 16px;
                    background: rgba(248, 81, 73, 0.15);
                    border: 1px solid #f85149;
                    border-radius: 6px;
                    color: #f85149;
                    font-size: 0.82rem;
                    text-align: center;
                }
            </style>
        </head>
        <body>
        <div class="login-box">
            <h1>Antibot - Painel</h1>
            <?php if ($loginErro): ?>
                <div class="login-erro">Usuário ou senha incorretos.</div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="acao" value="login">
                <label>Usuário</label>
                <input type="text" name="usuario" autofocus required>
                <label>Senha</label>
                <input type="password" name="senha" required>
                <button type="submit">Entrar</button>
            </form>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}

$db = new SQLite3(__DIR__ . '/db/antibot.db');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'limpar') {
    $db->exec('DELETE FROM acessos');
    $db->exec('DELETE FROM navegacao');
    header('Location: painel.php?msg=limpo');
    exit;
}

$aba = $_GET['aba'] ?? 'acessos';

$filtro = $_GET['filtro'] ?? 'todos';
$busca = trim($_GET['busca'] ?? '');
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 50;
$offset = ($pagina - 1) * $porPagina;

$where = [];
$params = [];

if ($filtro === 'bloqueados') {
    $where[] = "bloqueado = 'true'";
} elseif ($filtro === 'liberados') {
    $where[] = "(bloqueado IS NULL OR bloqueado != 'true')";
}

if ($busca !== '') {
    $where[] = "(ip LIKE :busca OR url LIKE :busca OR city LIKE :busca OR provider LIKE :busca OR hostname LIKE :busca OR os_name LIKE :busca OR client_name LIKE :busca)";
    $params[':busca'] = '%' . $busca . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM acessos $whereSql");
foreach ($params as $k => $v) {
    $countStmt->bindValue($k, $v, SQLITE3_TEXT);
}
$total = $countStmt->execute()->fetchArray(SQLITE3_ASSOC)['total'];
$totalPaginas = max(1, ceil($total / $porPagina));

// Contadores
$totalTodos = $db->querySingle("SELECT COUNT(*) FROM acessos");
$totalBloqueados = $db->querySingle("SELECT COUNT(*) FROM acessos WHERE bloqueado = 'true'");
$totalLiberados = $totalTodos - $totalBloqueados;

// Registros
$stmt = $db->prepare("SELECT * FROM acessos $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, SQLITE3_TEXT);
}
$stmt->bindValue(':limit', $porPagina, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

$registros = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $registros[] = $row;
}

// ── Navegação ──
$navFiltro = $_GET['nav_filtro'] ?? 'todos';
$navBusca = trim($_GET['nav_busca'] ?? '');
$navPagina = max(1, (int) ($_GET['nav_pagina'] ?? 1));
$navOffset = ($navPagina - 1) * $porPagina;

$navWhere = [];
$navParams = [];

if ($navFiltro === 'bloqueados') {
    $navWhere[] = "a.bloqueado = 'true'";
} elseif ($navFiltro === 'liberados') {
    $navWhere[] = "(a.bloqueado IS NULL OR a.bloqueado != 'true')";
}

if ($navBusca !== '') {
    $navWhere[] = "(n.ip LIKE :busca OR n.url LIKE :busca OR a.city LIKE :busca OR a.provider LIKE :busca OR a.client_name LIKE :busca)";
    $navParams[':busca'] = '%' . $navBusca . '%';
}

$navWhereSql = $navWhere ? 'WHERE ' . implode(' AND ', $navWhere) : '';

$navFrom = "navegacao n LEFT JOIN acessos a ON n.acesso_id = a.id";

$navCountStmt = $db->prepare("SELECT COUNT(*) as total FROM $navFrom $navWhereSql");
foreach ($navParams as $k => $v) $navCountStmt->bindValue($k, $v, SQLITE3_TEXT);
$navTotal = $navCountStmt->execute()->fetchArray(SQLITE3_ASSOC)['total'];
$navTotalPaginas = max(1, ceil($navTotal / $porPagina));

// Contadores de navegação
$navTotalTodos = $db->querySingle("SELECT COUNT(*) FROM navegacao");
$navTotalBloqueados = $db->querySingle("SELECT COUNT(*) FROM navegacao n LEFT JOIN acessos a ON n.acesso_id = a.id WHERE a.bloqueado = 'true'");
$navTotalLiberados = $navTotalTodos - $navTotalBloqueados;

$navStmt = $db->prepare("SELECT n.id, n.data_hora, n.ip, n.url, n.referrer, n.acesso_id,
    a.city, a.isocode, a.provider, a.proxy, a.vpn, a.bot,
    a.client_name, a.client_version, a.os_name, a.os_version,
    a.device_type, a.bloqueado, a.motivo_bloqueio
    FROM $navFrom $navWhereSql ORDER BY n.id DESC LIMIT :limit OFFSET :offset");
foreach ($navParams as $k => $v) $navStmt->bindValue($k, $v, SQLITE3_TEXT);
$navStmt->bindValue(':limit', $porPagina, SQLITE3_INTEGER);
$navStmt->bindValue(':offset', $navOffset, SQLITE3_INTEGER);
$navResult = $navStmt->execute();

$navRegistros = [];
while ($row = $navResult->fetchArray(SQLITE3_ASSOC)) {
    $navRegistros[] = $row;
}

$db->close();

function queryString($overrides) {
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}

function esc($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antibot - Painel</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f1117;
            color: #e1e4e8;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        h1 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #f0f0f0;
        }

        /* Contadores */
        .stats {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stat-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 14px 20px;
            min-width: 140px;
        }

        .stat-card .number {
            font-size: 1.6rem;
            font-weight: 700;
            color: #f0f0f0;
        }

        .stat-card .label {
            font-size: 0.78rem;
            color: #8b949e;
            margin-top: 2px;
        }

        .stat-card.bloqueados .number { color: #f85149; }
        .stat-card.liberados .number { color: #3fb950; }

        /* Toolbar */
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filtros {
            display: flex;
            gap: 0;
            border: 1px solid #30363d;
            border-radius: 6px;
            overflow: hidden;
        }

        .filtros a {
            padding: 8px 16px;
            font-size: 0.82rem;
            color: #8b949e;
            text-decoration: none;
            background: #161b22;
            border-right: 1px solid #30363d;
            transition: background 0.15s, color 0.15s;
        }

        .filtros a:last-child { border-right: none; }
        .filtros a:hover { background: #1c2129; color: #e1e4e8; }
        .filtros a.ativo { background: #1f6feb; color: #fff; }

        .search-box {
            display: flex;
            gap: 0;
        }

        .search-box input {
            padding: 8px 12px;
            font-size: 0.82rem;
            background: #161b22;
            border: 1px solid #30363d;
            border-right: none;
            border-radius: 6px 0 0 6px;
            color: #e1e4e8;
            outline: none;
            width: 220px;
        }

        .search-box input:focus { border-color: #1f6feb; }
        .search-box input::placeholder { color: #484f58; }

        .search-box button {
            padding: 8px 14px;
            font-size: 0.82rem;
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 0 6px 6px 0;
            color: #8b949e;
            cursor: pointer;
        }

        .search-box button:hover { background: #30363d; color: #e1e4e8; }

        /* Tabela */
        .table-wrap {
            overflow-x: auto;
            border: 1px solid #30363d;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        th {
            background: #161b22;
            color: #8b949e;
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 10px 12px;
            text-align: left;
            position: sticky;
            top: 0;
            border-bottom: 1px solid #30363d;
        }

        td {
            padding: 9px 12px;
            border-bottom: 1px solid #21262d;
            color: #c9d1d9;
        }

        tr:hover td { background: #161b22; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-red { background: rgba(248, 81, 73, 0.15); color: #f85149; }
        .badge-green { background: rgba(63, 185, 80, 0.15); color: #3fb950; }
        .badge-yellow { background: rgba(210, 153, 34, 0.15); color: #d29922; }
        .badge-gray { background: rgba(139, 148, 158, 0.15); color: #8b949e; }

        .empty-msg {
            text-align: center;
            padding: 48px 20px;
            color: #484f58;
            font-size: 0.9rem;
        }

        /* Paginação */
        .paginacao {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 16px;
            font-size: 0.8rem;
            color: #8b949e;
            flex-wrap: wrap;
            gap: 10px;
        }

        .paginacao a {
            padding: 6px 14px;
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
            color: #8b949e;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .paginacao a:hover { background: #21262d; color: #e1e4e8; }

        .pag-links {
            display: flex;
            gap: 6px;
        }

        /* Abas */
        .abas {
            display: flex;
            gap: 0;
            border-bottom: 1px solid #30363d;
            margin-bottom: 20px;
        }

        .abas a {
            padding: 10px 20px;
            font-size: 0.85rem;
            color: #8b949e;
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: color 0.15s, border-color 0.15s;
        }

        .abas a:hover { color: #e1e4e8; }
        .abas a.ativo { color: #f0f0f0; border-bottom-color: #1f6feb; }

        @media (max-width: 600px) {
            .container { padding: 14px 10px; }
            .stats { gap: 8px; }
            .stat-card { padding: 10px 14px; min-width: 100px; }
            .stat-card .number { font-size: 1.2rem; }
            .search-box input { width: 140px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <h1 style="margin-bottom:0;">Antibot - Painel</h1>
        <?php if (!empty($_SESSION['ab_painel_auth'])): ?>
            <a href="?acao=logout" style="font-size:0.82rem;color:#8b949e;text-decoration:none;padding:6px 14px;background:#161b22;border:1px solid #30363d;border-radius:6px;">Sair</a>
        <?php endif; ?>
    </div>

    <div class="abas">
        <a href="<?= queryString(['aba' => 'acessos', 'pagina' => 1]) ?>" class="<?= $aba === 'acessos' ? 'ativo' : '' ?>">Acessos</a>
        <a href="<?= queryString(['aba' => 'navegacao', 'nav_pagina' => 1]) ?>" class="<?= $aba === 'navegacao' ? 'ativo' : '' ?>">Navegação</a>
    </div>

    <?php if (($_GET['msg'] ?? '') === 'limpo'): ?>
        <div style="padding:10px 16px;margin-bottom:16px;background:rgba(63,185,80,0.15);border:1px solid #3fb950;border-radius:6px;color:#3fb950;font-size:0.85rem;">Todos os registros foram excluídos.</div>
    <?php endif; ?>

    <?php if ($aba === 'acessos'): ?>
    <div class="stats">
        <div class="stat-card">
            <div class="number"><?= $totalTodos ?></div>
            <div class="label">Total</div>
        </div>
        <div class="stat-card bloqueados">
            <div class="number"><?= $totalBloqueados ?></div>
            <div class="label">Bloqueados</div>
        </div>
        <div class="stat-card liberados">
            <div class="number"><?= $totalLiberados ?></div>
            <div class="label">Liberados</div>
        </div>
    </div>

    <div class="toolbar">
        <div class="filtros">
            <a href="<?= queryString(['filtro' => 'todos', 'pagina' => 1]) ?>" class="<?= $filtro === 'todos' ? 'ativo' : '' ?>">Todos</a>
            <a href="<?= queryString(['filtro' => 'bloqueados', 'pagina' => 1]) ?>" class="<?= $filtro === 'bloqueados' ? 'ativo' : '' ?>">Bloqueados</a>
            <a href="<?= queryString(['filtro' => 'liberados', 'pagina' => 1]) ?>" class="<?= $filtro === 'liberados' ? 'ativo' : '' ?>">Liberados</a>
        </div>
        <form class="search-box" method="get">
            <input type="hidden" name="filtro" value="<?= esc($filtro) ?>">
            <input type="text" name="busca" placeholder="Buscar IP, cidade, provedor..." value="<?= esc($busca) ?>">
            <button type="submit">Buscar</button>
        </form>
        <form method="post" onsubmit="return confirm('Excluir TODOS os registros de acesso?');" style="margin-left:auto;">
            <input type="hidden" name="acao" value="limpar">
            <button type="submit" style="padding:8px 14px;font-size:0.82rem;background:#da3633;border:1px solid #f85149;border-radius:6px;color:#fff;cursor:pointer;">Limpar tudo</button>
        </form>
    </div>

    <div class="table-wrap">
        <?php if (empty($registros)): ?>
            <div class="empty-msg">Nenhum registro encontrado.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data/Hora</th>
                    <th>IP</th>
                    <th>URL</th>
                    <th>Cidade</th>
                    <th>País</th>
                    <th>Provedor</th>
                    <th>Proxy</th>
                    <th>VPN</th>
                    <th>Bot</th>
                    <th>Navegador</th>
                    <th>SO</th>
                    <th>Dispositivo</th>
                    <th>Status</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $r): ?>
                <tr>
                    <td><?= esc($r['id']) ?></td>
                    <td><?= $r['data_hora'] ? date('d/m/Y H:i:s', strtotime($r['data_hora'])) : '-' ?></td>
                    <td><?= esc($r['ip']) ?></td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;" title="<?= esc($r['url']) ?>"><?= esc($r['url']) ?></td>
                    <td><?= esc($r['city']) ?></td>
                    <td><?= esc($r['isocode']) ?></td>
                    <td><?= esc($r['provider']) ?></td>
                    <td>
                        <?php if ($r['proxy'] === 'yes'): ?>
                            <span class="badge badge-red">sim</span>
                        <?php else: ?>
                            <span class="badge badge-gray">não</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['vpn'] === 'yes'): ?>
                            <span class="badge badge-yellow">sim</span>
                        <?php else: ?>
                            <span class="badge badge-gray">não</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['bot'] === 'true'): ?>
                            <span class="badge badge-red">sim</span>
                        <?php else: ?>
                            <span class="badge badge-gray">não</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($r['client_name']) ?> <?= esc($r['client_version']) ?></td>
                    <td><?= esc($r['os_name']) ?> <?= esc($r['os_version']) ?></td>
                    <td><?= esc($r['device_type']) ?></td>
                    <td>
                        <?php if ($r['bloqueado'] === 'true'): ?>
                            <span class="badge badge-red">bloqueado</span>
                        <?php else: ?>
                            <span class="badge badge-green">liberado</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.72rem"><?= nl2br(esc(str_replace(', ', "\n", $r['motivo_bloqueio'] ?? ''))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <div class="paginacao">
        <span>Página <?= $pagina ?> de <?= $totalPaginas ?> (<?= $total ?> registros)</span>
        <div class="pag-links">
            <?php if ($pagina > 1): ?>
                <a href="<?= queryString(['pagina' => $pagina - 1]) ?>">Anterior</a>
            <?php endif; ?>
            <?php if ($pagina < $totalPaginas): ?>
                <a href="<?= queryString(['pagina' => $pagina + 1]) ?>">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($aba === 'navegacao'): ?>
    <div class="stats">
        <div class="stat-card">
            <div class="number"><?= $navTotalTodos ?></div>
            <div class="label">Total</div>
        </div>
        <div class="stat-card bloqueados">
            <div class="number"><?= $navTotalBloqueados ?></div>
            <div class="label">Bloqueados</div>
        </div>
        <div class="stat-card liberados">
            <div class="number"><?= $navTotalLiberados ?></div>
            <div class="label">Liberados</div>
        </div>
    </div>

    <div class="toolbar">
        <div class="filtros">
            <a href="<?= queryString(['aba' => 'navegacao', 'nav_filtro' => 'todos', 'nav_pagina' => 1]) ?>" class="<?= $navFiltro === 'todos' ? 'ativo' : '' ?>">Todos</a>
            <a href="<?= queryString(['aba' => 'navegacao', 'nav_filtro' => 'bloqueados', 'nav_pagina' => 1]) ?>" class="<?= $navFiltro === 'bloqueados' ? 'ativo' : '' ?>">Bloqueados</a>
            <a href="<?= queryString(['aba' => 'navegacao', 'nav_filtro' => 'liberados', 'nav_pagina' => 1]) ?>" class="<?= $navFiltro === 'liberados' ? 'ativo' : '' ?>">Liberados</a>
        </div>
        <form class="search-box" method="get">
            <input type="hidden" name="aba" value="navegacao">
            <input type="hidden" name="nav_filtro" value="<?= esc($navFiltro) ?>">
            <input type="text" name="nav_busca" placeholder="Buscar IP, URL, cidade, provedor..." value="<?= esc($navBusca) ?>">
            <button type="submit">Buscar</button>
        </form>
    </div>

    <div class="table-wrap">
        <?php if (empty($navRegistros)): ?>
            <div class="empty-msg">Nenhum registro de navegação encontrado.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data/Hora</th>
                    <th>IP</th>
                    <th>URL</th>
                    <th>Cidade</th>
                    <th>País</th>
                    <th>Provedor</th>
                    <th>Proxy</th>
                    <th>VPN</th>
                    <th>Bot</th>
                    <th>Navegador</th>
                    <th>SO</th>
                    <th>Dispositivo</th>
                    <th>Status</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($navRegistros as $n): ?>
                <tr>
                    <td><?= esc($n['id']) ?></td>
                    <td><?= $n['data_hora'] ? date('d/m/Y H:i:s', strtotime($n['data_hora'])) : '-' ?></td>
                    <td><?= esc($n['ip']) ?></td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;" title="<?= esc($n['url']) ?>"><?= esc($n['url']) ?></td>
                    <td><?= esc($n['city']) ?></td>
                    <td><?= esc($n['isocode']) ?></td>
                    <td><?= esc($n['provider']) ?></td>
                    <td>
                        <?php if ($n['proxy'] === 'yes'): ?>
                            <span class="badge badge-red">sim</span>
                        <?php else: ?>
                            <span class="badge badge-gray">não</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($n['vpn'] === 'yes'): ?>
                            <span class="badge badge-yellow">sim</span>
                        <?php else: ?>
                            <span class="badge badge-gray">não</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($n['bot'] === 'true'): ?>
                            <span class="badge badge-red">sim</span>
                        <?php else: ?>
                            <span class="badge badge-gray">não</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($n['client_name']) ?> <?= esc($n['client_version']) ?></td>
                    <td><?= esc($n['os_name']) ?> <?= esc($n['os_version']) ?></td>
                    <td><?= esc($n['device_type']) ?></td>
                    <td>
                        <?php if ($n['bloqueado'] === 'true'): ?>
                            <span class="badge badge-red">bloqueado</span>
                        <?php else: ?>
                            <span class="badge badge-green">liberado</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.72rem"><?= nl2br(esc(str_replace(', ', "\n", $n['motivo_bloqueio'] ?? ''))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($navTotalPaginas > 1): ?>
    <div class="paginacao">
        <span>Página <?= $navPagina ?> de <?= $navTotalPaginas ?> (<?= $navTotal ?> registros)</span>
        <div class="pag-links">
            <?php if ($navPagina > 1): ?>
                <a href="<?= queryString(['aba' => 'navegacao', 'nav_filtro' => $navFiltro, 'nav_busca' => $navBusca, 'nav_pagina' => $navPagina - 1]) ?>">Anterior</a>
            <?php endif; ?>
            <?php if ($navPagina < $navTotalPaginas): ?>
                <a href="<?= queryString(['aba' => 'navegacao', 'nav_filtro' => $navFiltro, 'nav_busca' => $navBusca, 'nav_pagina' => $navPagina + 1]) ?>">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
