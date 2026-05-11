# AntiBot

Sistema de detecao de bots, automacao, proxy e VPN para proteger paginas web. Combina deteccao client-side (JavaScript) com verificacao server-side (PHP + APIs externas).

## Como funciona

1. O visitante acessa a pagina protegida
2. O script `antibot.js` oculta a pagina e exibe um spinner
3. Executa 40+ regras de deteccao no navegador (WebDriver, Selenium, Puppeteer, fingerprint, etc.)
4. Consulta APIs server-side (ProxyCheck.io para IP/proxy/VPN, DeviceDetector para bot/navegador)
5. Calcula um score com base nas regras ativadas
6. Se o score >= limite ou se detectar proxy/VPN/bot/pais bloqueado, exibe pagina 404
7. Se aprovado, libera a pagina e salva o registro no banco
8. Nas proximas visitas, verifica o registro existente e libera instantaneamente

## Requisitos

- PHP 7.4+
- Extensao SQLite3 habilitada no PHP
- Composer
- Chave de API do [ProxyCheck.io](https://proxycheck.io/) (plano gratuito disponivel)

## Instalacao

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
├── index.php        (sua pagina)
└── ...
```

### 2. Instalar dependencias

```bash
cd ab
composer install
```

### 3. Configurar variaveis de ambiente

```bash
cp ab/.env.example ab/.env
```

Edite o arquivo `ab/.env`:

```ini
PROXYCHECK_API_KEY="sua_chave_aqui"
TEST_IP="um_ip_real_para_testes"
```

| Variavel | Descricao |
|----------|-----------|
| `PROXYCHECK_API_KEY` | Chave da API ProxyCheck.io (obrigatoria) |
| `TEST_IP` | IP usado no lugar de 127.0.0.1 durante desenvolvimento local (opcional) |

### 4. Garantir permissao de escrita

A pasta `ab/db/` precisa ter permissao de escrita para o PHP criar e gravar no banco SQLite:

```bash
chmod 755 ab/db/
```

### 5. Criar o banco de dados

Na primeira execucao, o banco `ab/db/antibot.db` sera criado automaticamente. Se preferir criar manualmente:

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

### Proteger uma pagina (basico)

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

### Proteger uma pagina (server-side)

Para paginas que so devem ser acessadas apos aprovacao no antibot, adicione no topo do arquivo PHP:

```php
<?php require __DIR__ . '/ab/includes/verify.php'; ?>
<!DOCTYPE html>
<html>
<!-- conteudo protegido -->
```

O `verify.php` faz duas verificacoes:

1. **PHP (server-side):** Consulta o banco pelo IP + fingerprint. Se bloqueado ou sem registro, redireciona para 404.
2. **JavaScript (client-side):** Verifica o `sessionStorage` e confirma o status com a API.

## Configuracao

Todas as opcoes ficam em `ab/config.php`. O arquivo retorna um JSON consumido pelo `antibot.js`.

### Opcoes gerais

```php
'paginaInicial' => 'index.php',    // Pagina onde a deteccao completa roda
'redirectUrl' => '',                // URL de redirecionamento apos aprovacao (vazio = fica na pagina)
'tempoMinimo' => 5000,             // Tempo minimo de verificacao em ms
'scoreMinimo' => 50,               // Score minimo para bloquear
```

### Bloqueios por API

Cada um bloqueia independentemente do score:

```php
'bloquear_bot' => true,            // Bloquear bots detectados pelo DeviceDetector
'bloquear_proxy' => true,          // Bloquear proxies
'bloquear_vpn' => true,            // Bloquear VPNs
'paises_permitidos' => ['BR'],     // Paises permitidos (ISO). Vazio = qualquer pais
```

### Regras de deteccao

Cada regra tem `ativo` (true/false) e `pts` (pontos somados ao score):

```php
'regras' => [
    'webdriver'    => ['ativo' => true, 'pts' => 80],  // navigator.webdriver === true
    'selenium'     => ['ativo' => true, 'pts' => 80],  // Variaveis do Selenium no window
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

Para ajustar a pontuacao:
```php
'webdriver' => ['ativo' => true, 'pts' => 50],
```

### Lista completa de regras

| Regra | Pts | Descricao |
|-------|-----|-----------|
| **Automacao direta** | | |
| `webdriver` | 80 | `navigator.webdriver === true` |
| `chromedriver` | 80 | Variaveis `$cdc_` no document |
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
| `stack_automacao` | 80 | Stack trace contem nomes de ferramentas |
| **User-Agent** | | |
| `ua_suspeito` | 60 | UA contem palavras-chave de bots |
| `webdriver_patched` | 60 | `navigator.webdriver` com getter customizado |
| `ua_ferramenta` | 80 | UA de ferramenta HTTP (curl, wget, etc.) |
| `ua_curto` | 30 | UA menor que 40 caracteres |
| **Headers do servidor** | | |
| `sem_accept_lang` | 15 | Header Accept-Language ausente |
| `sem_accept_enc` | 10 | Header Accept-Encoding ausente |
| `accept_generico` | 5 | Header Accept e apenas `*/*` |
| `conn_close` | 10 | Connection: close |
| **Fingerprint do navegador** | | |
| `sem_plugins` | 10 | `navigator.plugins.length === 0` |
| `sem_idiomas` | 15 | `navigator.languages` vazio |
| `platform_mismatch` | 20 | SO do UA diferente do `navigator.platform` |
| `chrome_falso` | 30 | UA diz Chrome mas `window.chrome` ausente |
| **Dimensoes / Ambiente** | | |
| `tela_zero` | 30 | `screen.width` ou `screen.height` e 0 |
| `janela_zero` | 10 | `outerWidth` ou `outerHeight` e 0 |
| `janela_minima` | 15 | Janela ocupa menos de 10% da tela |
| `timing_rapido` | 15 | `performance.now() < 50ms` |
| **Recursos ausentes** | | |
| `sem_cpu_cores` | 15 | `hardwareConcurrency` indefinido |
| `sem_speech` | 10 | Sem `speechSynthesis` |
| `sem_shared_buffer` | 5 | Chrome sem `SharedArrayBuffer` |
| `sem_notification` | 10 | Sem Notification API |
| `sem_worker` | 10 | Sem Worker/ServiceWorker |
| `sem_color_depth` | 20 | `screen.colorDepth` e 0 |
| **WebGL** | | |
| `webgl_software` | 30 | Renderer e software (SwiftShader, LLVMpipe) |
| `sem_webgl` | 15 | Sem suporte a WebGL |
| `webgl_erro` | 10 | Erro ao criar contexto WebGL |
| **Canvas** | | |
| `canvas_vazio` | 25 | `toDataURL()` retorna imagem vazia |
| `canvas_erro` | 15 | Erro ao renderizar canvas |
| **APIs do navegador** | | |
| `perm_inconsistente` | 20 | Notification.permission inconsistente com Permissions API |
| `sem_midia` | 15 | Nenhum dispositivo de midia detectado |
| `rtt_zero` | 15 | RTT da conexao e 0ms |
| `iframe` | 10 | Pagina rodando dentro de iframe |
| `historico_curto` | 5 | `history.length <= 1` |
| `sem_chrome_runtime` | 5 | Chrome sem `chrome.runtime` |
| `mobile_sem_touch` | 25 | UA mobile mas sem suporte a touch |
| `bateria_fake` | 10 | Battery API com valores padrao (desativado) |
| `audio_vazio` | 20 | AudioContext com dados zerados |
| `poucas_fontes` | 15 | Menos de 5 fontes do sistema detectadas |
| `sem_timezone` | 15 | Timezone indefinido |
| `timezone_erro` | 10 | Erro ao acessar timezone |
| `cdp_detectado` | 5 | Chrome sem `chrome.loadTimes` |
| `sem_interacao` | 10 | Nenhuma interacao em 4 segundos |
| `math_diferente` | 10 | `Math.tan(-1e300)` com valor inesperado |

## Painel administrativo

Acesse `ab/painel.php` para monitorar os acessos.

### Funcionalidades

- **Aba Acessos:** Todos os registros de deteccao com IP, cidade, pais, provedor, proxy, VPN, bot, navegador, SO, dispositivo, status e motivo do bloqueio
- **Aba Navegacao:** Historico de paginas visitadas por cada visitante, vinculado ao registro de acesso
- **Filtros:** Todos, Bloqueados, Liberados
- **Busca:** Por IP, URL, cidade, provedor, hostname, navegador, SO
- **Limpar dados:** Botao para apagar todos os registros

> **Importante:** O painel nao possui autenticacao. Em producao, proteja o acesso com autenticacao HTTP, firewall ou outra medida.

## Estrutura de arquivos

```
ab/
├── antibot.js              # Script de deteccao client-side
├── config.php              # Configuracoes (JSON)
├── painel.php              # Painel administrativo
├── composer.json           # Dependencias PHP
├── .env                    # Variaveis de ambiente (nao versionado)
├── .env.example            # Template do .env
├── apis/
│   ├── proxycheckio.php    # API ProxyCheck.io (IP, proxy, VPN, geolocalizacao)
│   ├── device_detector.php # API DeviceDetector (bot, navegador, SO, dispositivo)
│   ├── salvar.php          # Salvar registro de acesso no banco
│   ├── sessao.php          # Consultar status do visitante (aprovado/bloqueado/novo)
│   └── navegacao.php       # Registrar navegacao entre paginas
├── includes/
│   └── verify.php          # Include PHP para protecao server-side
├── templates/
│   └── 404.html            # Pagina exibida para visitantes bloqueados
└── db/
    └── antibot.db          # Banco SQLite (criado automaticamente)
```

## APIs internas

| Endpoint | Metodo | Descricao |
|----------|--------|-----------|
| `/ab/config.php` | GET | Retorna configuracoes em JSON |
| `/ab/apis/proxycheckio.php` | GET | Consulta ProxyCheck.io pelo IP do visitante |
| `/ab/apis/device_detector.php` | GET | Detecta navegador, SO e dispositivo pelo User-Agent |
| `/ab/apis/salvar.php` | POST | Salva registro de acesso no banco |
| `/ab/apis/sessao.php` | GET | Verifica status do visitante (por ID ou IP+fingerprint) |
| `/ab/apis/navegacao.php` | POST | Registra navegacao entre paginas |

## Banco de dados

SQLite com duas tabelas:

**acessos** — Registros de deteccao

| Coluna | Descricao |
|--------|-----------|
| `id` | ID auto-incremento |
| `data_hora` | Data e hora do acesso |
| `ip` | IP do visitante |
| `url` | URL acessada |
| `city`, `isocode`, `regioncode` | Localizacao |
| `provider`, `organisation`, `asn`, `hostname` | Rede |
| `proxy`, `vpn`, `bot` | Flags de deteccao |
| `client_name`, `client_type`, `client_version` | Navegador |
| `os_name`, `os_platform`, `os_version`, `os_family` | Sistema operacional |
| `device_brand`, `device_model`, `device_type` | Dispositivo |
| `bloqueado` | `"true"` ou `"false"` |
| `motivo_bloqueio` | Motivo(s) do bloqueio |

**navegacao** — Historico de paginas visitadas

| Coluna | Descricao |
|--------|-----------|
| `id` | ID auto-incremento |
| `data_hora` | Data e hora |
| `ip` | IP do visitante |
| `url` | URL da pagina visitada |
| `referrer` | Pagina anterior |
| `acesso_id` | ID do registro em `acessos` |

## Desenvolvimento local

Para testar em localhost, defina `TEST_IP` no `.env` com um IP real. As APIs `proxycheckio.php` e `navegacao.php` substituem automaticamente `127.0.0.1` / `::1` pelo IP configurado.

## Licenca

MIT
