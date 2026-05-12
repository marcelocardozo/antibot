# AntiBot

Sistema de detecção de bots, automação, proxy e VPN para proteger páginas web. Combina detecção client-side (JavaScript) com verificação server-side (PHP + APIs externas).

## Como funciona

1. O visitante acessa a página protegida
2. O script `antibot.js` oculta a página e exibe uma tela de carregamento (configurável)
3. Executa 40+ regras de detecção no navegador (WebDriver, Selenium, Puppeteer, fingerprint, etc.)
4. Consulta APIs server-side (ProxyCheck.io para IP/proxy/VPN, DeviceDetector para bot/navegador)
5. Calcula um score com base nas regras ativadas
6. Se o score >= limite ou se detectar proxy/VPN/bot/país bloqueado, exibe página 404
7. Se aprovado, libera a página e salva o registro no banco
8. Nas próximas visitas, verifica o registro existente e libera instantaneamente

## Requisitos

- PHP 7.4+
- Extensão SQLite3 habilitada no PHP
- Composer
- Chave de API do [ProxyCheck.io](https://proxycheck.io/) (plano gratuito disponível)

## Instalação

### 1. Copiar a pasta `ab/` para o seu projeto

```
seu-projeto/
├── ab/
│   ├── antibot.js
│   ├── config.php
│   ├── painel.php
│   ├── composer.json
│   ├── .env.example
│   ├── apis/
│   ├── includes/
│   ├── templates/
│   └── db/
├── index.php        (sua página)
└── ...
```

### 2. Instalar dependências

```bash
cd ab
composer install
```

### 3. Configurar variáveis de ambiente

```bash
cp ab/.env.example ab/.env
```

Edite o arquivo `ab/.env`:

```ini
PROXYCHECK_API_KEY="sua_chave_aqui"
TEST_IP="um_ip_real_para_testes"
```

| Variável | Descrição |
|----------|-----------|
| `PROXYCHECK_API_KEY` | Chave da API ProxyCheck.io (obrigatória) |
| `TEST_IP` | IP usado no lugar de 127.0.0.1 durante desenvolvimento local (opcional) |

### 4. Garantir permissão de escrita

A pasta `ab/db/` precisa ter permissão de escrita para o PHP criar e gravar no banco SQLite:

```bash
chmod 755 ab/db/
```

### 5. Criar o banco de dados

Na primeira execução, o banco `ab/db/antibot.db` será criado automaticamente. Se preferir criar manualmente:

```sql
CREATE TABLE IF NOT EXISTS acessos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    data_hora TEXT,
    ip TEXT,
    url TEXT,
    asn TEXT,
    hostname TEXT,
    provider TEXT,
    organisation TEXT,
    isocode TEXT,
    regioncode TEXT,
    city TEXT,
    proxy TEXT,
    vpn TEXT,
    bot TEXT,
    client_name TEXT,
    client_type TEXT,
    client_version TEXT,
    device_brand TEXT,
    device_model TEXT,
    device_type TEXT,
    os_name TEXT,
    os_platform TEXT,
    os_version TEXT,
    os_family TEXT,
    bloqueado TEXT,
    motivo_bloqueio TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS navegacao (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    data_hora TEXT,
    ip TEXT,
    url TEXT,
    referrer TEXT,
    acesso_id INTEGER
);
```

## Uso

### Proteger uma página (básico)

Adicione o script antes do `</body>`:

```html
<script src="/ab/antibot.js"></script>
```

O script auto-detecta o caminho da pasta `ab/` pelo atributo `src`. Funciona com caminhos absolutos ou relativos:

```html
<!-- Ambos funcionam -->
<script src="/ab/antibot.js"></script>
<script src="ab/antibot.js"></script>
```

### Proteger uma página (server-side)

Para páginas que só devem ser acessadas após aprovação no antibot, adicione no topo do arquivo PHP:

```php
<?php require __DIR__ . '/ab/includes/verify.php'; ?>
<!DOCTYPE html>
<html>
<!-- conteúdo protegido -->
```

O `verify.php` faz duas verificações:

1. **PHP (server-side):** Consulta o banco pelo IP + fingerprint. Se bloqueado ou sem registro, redireciona para 404.
2. **JavaScript (client-side):** Verifica o `sessionStorage` e confirma o status com a API.

## Configuração

Todas as opções ficam em `ab/config.php`. O arquivo retorna um JSON consumido pelo `antibot.js`.

### Opções gerais

```php
'paginaInicial' => 'index.php',        // Página onde a detecção completa roda
'redirectUrl' => '',                    // URL de redirecionamento após aprovação (vazio = fica na página)
'tempoMinimo' => 5000,                 // Tempo mínimo de verificação em ms
'telaCarregamento' => 'spinner.html',  // Template da tela de carregamento (ver seção abaixo)
'scoreMinimo' => 50,                   // Score mínimo para bloquear
```

### Bloqueios por API

Cada um bloqueia independentemente do score:

```php
'bloquear_bot' => true,            // Bloquear bots detectados pelo DeviceDetector
'bloquear_proxy' => true,          // Bloquear proxies
'bloquear_vpn' => true,            // Bloquear VPNs
'paises_permitidos' => ['BR'],     // Países permitidos (ISO). Vazio = qualquer país
```

### Regras de detecção

Cada regra tem `ativo` (true/false) e `pts` (pontos somados ao score):

```php
'regras' => [
    'webdriver'    => ['ativo' => true, 'pts' => 80],  // navigator.webdriver === true
    'selenium'     => ['ativo' => true, 'pts' => 80],  // Variáveis do Selenium no window
    'puppeteer'    => ['ativo' => true, 'pts' => 80],  // Puppeteer detectado
    'playwright'   => ['ativo' => true, 'pts' => 80],  // Playwright detectado
    'ua_suspeito'  => ['ativo' => true, 'pts' => 60],  // User-Agent suspeito
    'chrome_falso' => ['ativo' => true, 'pts' => 30],  // UA diz Chrome mas window.chrome ausente
    'sem_plugins'  => ['ativo' => true, 'pts' => 10],  // Sem plugins no navegador
    // ... 40 regras no total
]
```

Para desativar uma regra:
```php
'webdriver' => ['ativo' => false, 'pts' => 80],
```

Para ajustar a pontuação:
```php
'webdriver' => ['ativo' => true, 'pts' => 50],
```

### Lista completa de regras

| Regra | Pts | Descrição |
|-------|-----|-----------|
| **Automação direta** | | |
| `webdriver` | 80 | `navigator.webdriver === true` |
| `chromedriver` | 80 | Variáveis `$cdc_` no document |
| `selenium` | 80 | Propriedades do Selenium no window/document |
| `puppeteer` | 80 | `__puppeteer_evaluation_script__` |
| `puppeteer_obj` | 80 | `window.puppeteer` ou `navigator.pptr` |
| `playwright` | 80 | `window.__playwright` |
| `playwright_key` | 80 | Chaves `__playwright` no window |
| `cypress` | 80 | `window.Cypress` / `window.cy` |
| `phantomjs` | 80 | `window.callPhantom` / `window._phantom` |
| `nightmare` | 80 | `window.__nightmare` |
| `webdriverio` | 80 | `window.wdio` / `window.__wdio` |
| `testcafe` | 80 | `window.__testCafe` |
| `browserless` | 80 | `window.__browserless` / `__chrome_aws_lambda` |
| `stack_automacao` | 80 | Stack trace contém nomes de ferramentas |
| **User-Agent** | | |
| `ua_suspeito` | 60 | UA contém palavras-chave de bots |
| `webdriver_patched` | 60 | `navigator.webdriver` com getter customizado |
| `ua_ferramenta` | 80 | UA de ferramenta HTTP (curl, wget, etc.) |
| `ua_curto` | 30 | UA menor que 40 caracteres |
| **Headers do servidor** | | |
| `sem_accept_lang` | 15 | Header Accept-Language ausente |
| `sem_accept_enc` | 10 | Header Accept-Encoding ausente |
| `accept_generico` | 5 | Header Accept é apenas `*/*` |
| `conn_close` | 10 | Connection: close |
| **Fingerprint do navegador** | | |
| `sem_plugins` | 10 | `navigator.plugins.length === 0` |
| `sem_idiomas` | 15 | `navigator.languages` vazio |
| `platform_mismatch` | 20 | SO do UA diferente do `navigator.platform` |
| `chrome_falso` | 30 | UA diz Chrome mas `window.chrome` ausente |
| **Dimensões / Ambiente** | | |
| `tela_zero` | 30 | `screen.width` ou `screen.height` é 0 |
| `janela_zero` | 10 | `outerWidth` ou `outerHeight` é 0 |
| `janela_minima` | 15 | Janela ocupa menos de 10% da tela |
| `timing_rapido` | 15 | `performance.now() < 50ms` |
| **Recursos ausentes** | | |
| `sem_cpu_cores` | 15 | `hardwareConcurrency` indefinido |
| `sem_speech` | 10 | Sem `speechSynthesis` |
| `sem_shared_buffer` | 5 | Chrome sem `SharedArrayBuffer` |
| `sem_notification` | 10 | Sem Notification API |
| `sem_worker` | 10 | Sem Worker/ServiceWorker |
| `sem_color_depth` | 20 | `screen.colorDepth` é 0 |
| **WebGL** | | |
| `webgl_software` | 30 | Renderer é software (SwiftShader, LLVMpipe) |
| `sem_webgl` | 15 | Sem suporte a WebGL |
| `webgl_erro` | 10 | Erro ao criar contexto WebGL |
| **Canvas** | | |
| `canvas_vazio` | 25 | `toDataURL()` retorna imagem vazia |
| `canvas_erro` | 15 | Erro ao renderizar canvas |
| **APIs do navegador** | | |
| `perm_inconsistente` | 20 | Notification.permission inconsistente com Permissions API |
| `sem_midia` | 15 | Nenhum dispositivo de mídia detectado |
| `rtt_zero` | 15 | RTT da conexão é 0ms |
| `iframe` | 10 | Página rodando dentro de iframe |
| `historico_curto` | 5 | `history.length <= 1` |
| `sem_chrome_runtime` | 5 | Chrome sem `chrome.runtime` |
| `mobile_sem_touch` | 25 | UA mobile mas sem suporte a touch |
| `bateria_fake` | 10 | Battery API com valores padrão (desativado) |
| `audio_vazio` | 20 | AudioContext com dados zerados |
| `poucas_fontes` | 15 | Menos de 5 fontes do sistema detectadas |
| `sem_timezone` | 15 | Timezone indefinido |
| `timezone_erro` | 10 | Erro ao acessar timezone |
| `cdp_detectado` | 5 | Chrome sem `chrome.loadTimes` |
| `sem_interacao` | 10 | Nenhuma interação em 4 segundos |
| `math_diferente` | 10 | `Math.tan(-1e300)` com valor inesperado |

## Tela de carregamento

A tela de carregamento é exibida enquanto o antibot verifica o visitante. Ela é um arquivo HTML dentro de `ab/templates/` e configurada pela opção `telaCarregamento` no `config.php`.

### Templates incluídos

| Template | Descrição |
|----------|-----------|
| `spinner.html` | Spinner simples centralizado (padrão) |
| `cloudflare.html` | Tela estilo Cloudflare com widget de verificação |

### Criar um template personalizado

Crie um arquivo HTML em `ab/templates/` com a tela que desejar. O arquivo deve ser uma página HTML completa e autossuficiente (CSS inline, sem dependências externas):

```html
<!-- ab/templates/meu-template.html -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificando</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #1a1a2e;
            color: #fff;
            font-family: sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container { text-align: center; }
        .loader {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255,255,255,0.2);
            border-top-color: #e94560;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <div class="loader"></div>
        <p>Verificando seu acesso...</p>
    </div>
</body>
</html>
```

Depois, configure no `config.php`:

```php
'telaCarregamento' => 'meu-template.html',
```

### Regras para templates

- O arquivo deve estar em `ab/templates/`
- Deve ser uma página HTML completa (`<!DOCTYPE html>`, `<html>`, `<head>`, `<body>`)
- Use CSS inline (tag `<style>`) — não referencie arquivos CSS externos
- O template é carregado dentro de um `<iframe>`, então os estilos não interferem na página principal
- Para exibir o domínio atual dinamicamente, use JavaScript:

```html
<script>
    document.querySelector('.dominio').textContent = window.location.hostname;
</script>
```

## Painel administrativo

Acesse `ab/painel.php` para monitorar os acessos.

### Funcionalidades

- **Aba Acessos:** Todos os registros de detecção com IP, cidade, país, provedor, proxy, VPN, bot, navegador, SO, dispositivo, status e motivo do bloqueio
- **Aba Navegação:** Histórico de páginas visitadas por cada visitante, vinculado ao registro de acesso
- **Filtros:** Todos, Bloqueados, Liberados
- **Busca:** Por IP, URL, cidade, provedor, hostname, navegador, SO
- **Limpar dados:** Botão para apagar todos os registros

> **Importante:** O painel não possui autenticação. Em produção, proteja o acesso com autenticação HTTP, firewall ou outra medida.

## Estrutura de arquivos

```
ab/
├── antibot.js              # Script de detecção client-side
├── config.php              # Configurações (JSON)
├── painel.php              # Painel administrativo
├── composer.json           # Dependências PHP
├── .env                    # Variáveis de ambiente (não versionado)
├── .env.example            # Template do .env
├── apis/
│   ├── proxycheckio.php    # API ProxyCheck.io (IP, proxy, VPN, geolocalização)
│   ├── device_detector.php # API DeviceDetector (bot, navegador, SO, dispositivo)
│   ├── salvar.php          # Salvar registro de acesso no banco
│   ├── sessao.php          # Consultar status do visitante (aprovado/bloqueado/novo)
│   └── navegacao.php       # Registrar navegação entre páginas
├── includes/
│   └── verify.php          # Include PHP para proteção server-side
├── templates/
│   ├── 404.html            # Página exibida para visitantes bloqueados
│   ├── spinner.html        # Tela de carregamento: spinner simples (padrão)
│   └── cloudflare.html     # Tela de carregamento: estilo Cloudflare
└── db/
    └── antibot.db          # Banco SQLite (criado automaticamente)
```

## APIs internas

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/ab/config.php` | GET | Retorna configurações em JSON |
| `/ab/apis/proxycheckio.php` | GET | Consulta ProxyCheck.io pelo IP do visitante |
| `/ab/apis/device_detector.php` | GET | Detecta navegador, SO e dispositivo pelo User-Agent |
| `/ab/apis/salvar.php` | POST | Salva registro de acesso no banco |
| `/ab/apis/sessao.php` | GET | Verifica status do visitante (por ID ou IP+fingerprint) |
| `/ab/apis/navegacao.php` | POST | Registra navegação entre páginas |

## Banco de dados

SQLite com duas tabelas:

**acessos** — Registros de detecção

| Coluna | Descrição |
|--------|-----------|
| `id` | ID auto-incremento |
| `data_hora` | Data e hora do acesso |
| `ip` | IP do visitante |
| `url` | URL acessada |
| `city`, `isocode`, `regioncode` | Localização |
| `provider`, `organisation`, `asn`, `hostname` | Rede |
| `proxy`, `vpn`, `bot` | Flags de detecção |
| `client_name`, `client_type`, `client_version` | Navegador |
| `os_name`, `os_platform`, `os_version`, `os_family` | Sistema operacional |
| `device_brand`, `device_model`, `device_type` | Dispositivo |
| `bloqueado` | `"true"` ou `"false"` |
| `motivo_bloqueio` | Motivo(s) do bloqueio |

**navegação** — Histórico de páginas visitadas

| Coluna | Descrição |
|--------|-----------|
| `id` | ID auto-incremento |
| `data_hora` | Data e hora |
| `ip` | IP do visitante |
| `url` | URL da página visitada |
| `referrer` | Página anterior |
| `acesso_id` | ID do registro em `acessos` |

## Desenvolvimento local

Para testar em localhost, defina `TEST_IP` no `.env` com um IP real. As APIs `proxycheckio.php` e `navegacao.php` substituem automaticamente `127.0.0.1` / `::1` pelo IP configurado.

## Licença

MIT
