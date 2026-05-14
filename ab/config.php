<?php
$config = [

    // Página inicial onde o antibot roda a detecção completa
    // Nas demais páginas, o script apenas verifica se o visitante já foi aprovado
    'paginaInicial' => 'index.php',

    // URL de redirecionamento quando o visitante é aprovado, se vazio não redireciona
    'redirectUrl' => '',

    // Tempo mínimo da animação de verificação (ms)
    'tempoMinimo' => 5000,

    // Template da tela de carregamento exibida durante a verificação
    // Arquivo HTML dentro de ab/templates/
    // Opções disponíveis: 'spinner.html' (padrão), 'cloudflare.html'
    'telaCarregamento' => 'spinner.html',


    // Score mínimo para bloquear o visitante (padrão: 50)
    // Se a soma dos pontos das regras ativadas >= este valor, o visitante é bloqueado
    'scoreMinimo' => 50,

    // ════════════════════════════════════════════════════════════════════════
    // BLOQUEIOS POR API (proxy, VPN, bot, país)
    // ════════════════════════════════════════════════════════════════════════
    //
    // Estes bloqueios são independentes do score — cada um bloqueia sozinho.
    // Para desativar, defina 'ativo' => false.
    //

    // Bloquear visitantes detectados como bot pelo DeviceDetector
    'bloquear_bot' => true,

    // Bloquear visitantes usando proxy
    'bloquear_proxy' => true,

    // Bloquear visitantes usando VPN
    'bloquear_vpn' => true,

    // Bloquear visitantes de fora dos países permitidos
    // Se vazio ([]), aceita qualquer país
    // Exemplo: ['BR'] aceita apenas Brasil, ['BR', 'PT'] aceita Brasil e Portugal
    'paises_permitidos' => ['BR'],

    // ════════════════════════════════════════════════════════════════════════
    // PAINEL ADMINISTRATIVO
    // ════════════════════════════════════════════════════════════════════════
    //
    // Credenciais de acesso ao painel (ab/painel.php).
    // Se ambos estiverem vazios (''), o painel fica aberto sem login.
    //
    'painel_usuario' => 'admin',
    'painel_senha' => 'admin',

    // ════════════════════════════════════════════════════════════════════════
    // REGRAS DE DETECÇÃO
    // ════════════════════════════════════════════════════════════════════════
    //
    // Cada regra:
    //   'ativo' => true/false   — ativar ou desativar a verificação
    //   'pts'   => número       — pontos somados ao score quando detectado
    //
    // Se o score total >= scoreMinimo, o visitante é bloqueado.
    // Todas as regras devem estar listadas aqui.
    // Para desativar uma regra, defina 'ativo' => false.
    //
    'regras' => [

        // ── AUTOMAÇÃO DIRETA (alta confiança) ──────────────────────────────
        // Detectam ferramentas de automação conhecidas.
        // Pontuação alta (80) pois são indicadores definitivos de bot.

        // navigator.webdriver === true
        // Flag ativada automaticamente por Selenium, Puppeteer, Playwright
        // Exemplo: ChromeDriver define essa flag ao controlar o navegador
        'webdriver' => ['ativo' => true, 'pts' => 80],

        // Variáveis $cdc_ ou cdc_ injetadas no document pelo ChromeDriver
        // Exclusivo do ChromeDriver — presente em todas as versões
        // Exemplo: document.$cdc_asdjflasutopfhvcZLmcfl_
        'chromedriver' => ['ativo' => true, 'pts' => 80],

        // Propriedades globais do Selenium injetadas no window ou document
        // Exemplo: __selenium_evaluate, __webdriver_script_fn,
        //          _Selenium_IDE_Recorder, domAutomationController
        'selenium' => ['ativo' => true, 'pts' => 80],

        // window.__puppeteer_evaluation_script__ definido
        // Injetado pelo Puppeteer ao avaliar scripts na página
        'puppeteer' => ['ativo' => true, 'pts' => 80],

        // window.puppeteer ou navigator.pptr presentes
        // Objetos alternativos expostos pelo Puppeteer
        'puppeteer_obj' => ['ativo' => true, 'pts' => 80],

        // window.__playwright, window.__pw_manual ou window._playwrightInstance
        // Definidos pelo framework Playwright durante automação
        'playwright' => ['ativo' => true, 'pts' => 80],

        // Chaves do window começando com __playwright
        // Detecta variantes e versões diferentes do Playwright
        'playwright_key' => ['ativo' => true, 'pts' => 80],

        // window.Cypress, window.cy ou window.__cypress
        // Framework de teste end-to-end Cypress
        'cypress' => ['ativo' => true, 'pts' => 80],

        // window.callPhantom, window._phantom ou window.phantom
        // Navegador headless PhantomJS (descontinuado mas ainda usado)
        'phantomjs' => ['ativo' => true, 'pts' => 80],

        // window.__nightmare
        // Framework de automação NightmareJS baseado em Electron
        'nightmare' => ['ativo' => true, 'pts' => 80],

        // window.wdio ou window.__wdio
        // Framework de automação WebdriverIO
        'webdriverio' => ['ativo' => true, 'pts' => 80],

        // window.__testCafe ou window['%testCafeDriverInstance%']
        // Framework de teste end-to-end TestCafe
        'testcafe' => ['ativo' => true, 'pts' => 80],

        // window.__browserless ou window.__chrome_aws_lambda
        // Serviços de Chrome headless em nuvem (Browserless, chrome-aws-lambda)
        'browserless' => ['ativo' => true, 'pts' => 80],

        // Stack trace de Error() contém nomes de ferramentas de automação
        // Exemplo: new Error().stack inclui "puppeteer", "selenium", "playwright"
        'stack_automacao' => ['ativo' => true, 'pts' => 80],


        // ── USER AGENT ─────────────────────────────────────────────────────

        // User-Agent contém palavras-chave de bots ou ferramentas
        // Exemplo: "HeadlessChrome", "python-requests", "curl/7.68",
        //          "Googlebot", "scrapy", "wget", "axios"
        'ua_suspeito' => ['ativo' => true, 'pts' => 60],

        // navigator.webdriver com getter customizado (Object.getOwnPropertyDescriptor)
        // Detecta undetected-chromedriver que sobrescreve a flag webdriver
        // para retornar false usando um getter
        'webdriver_patched' => ['ativo' => true, 'pts' => 60],


        // ── FLAGS DO SERVIDOR (analisadas no proxycheckio.php) ─────────────

        // User-Agent é de ferramenta HTTP conhecida (curl, wget, python-requests)
        // Detectado no servidor via análise do header User-Agent
        // Exemplo: "curl/7.68.0", "python-requests/2.28.0"
        'ua_ferramenta' => ['ativo' => true, 'pts' => 80],

        // User-Agent muito curto (< 40 caracteres)
        // Navegadores reais têm UA longo (~100+ chars); bots usam UA curto
        // Exemplo: "Mozilla/5.0" sozinho, sem o restante da string
        'ua_curto' => ['ativo' => true, 'pts' => 30],

        // Header Accept-Language ausente na requisição
        // Navegadores reais sempre enviam (ex: "pt-BR,pt;q=0.9,en;q=0.8")
        // Bots e scripts HTTP frequentemente não enviam
        'sem_accept_lang' => ['ativo' => true, 'pts' => 15],

        // Header Accept-Encoding ausente na requisição
        // Navegadores reais sempre enviam (ex: "gzip, deflate, br")
        'sem_accept_enc' => ['ativo' => true, 'pts' => 10],

        // Header Accept muito genérico — apenas "*/*"
        // Navegadores enviam tipos específicos (text/html, application/xhtml+xml)
        // NOTA: fetch/AJAX do próprio antibot.js envia */* por padrão
        'accept_generico' => ['ativo' => true, 'pts' => 5],

        // Header Connection: close em vez de keep-alive
        // Navegadores reais usam keep-alive; scripts simples fecham a conexão
        'conn_close' => ['ativo' => true, 'pts' => 10],


        // ── FINGERPRINT DO NAVEGADOR ───────────────────────────────────────

        // navigator.plugins.length === 0
        // Navegadores reais têm plugins (PDF Viewer, Chrome PDF, etc.)
        // Headless e bots geralmente não têm nenhum plugin
        // NOTA: Firefox em alguns SOs pode ter 0 plugins legitimamente
        'sem_plugins' => ['ativo' => true, 'pts' => 10],

        // navigator.languages vazio ou inexistente
        // Navegadores reais sempre reportam pelo menos 1 idioma configurado
        // Exemplo esperado: ["pt-BR", "pt", "en-US", "en"]
        'sem_idiomas' => ['ativo' => true, 'pts' => 15],

        // User-Agent diz um SO mas navigator.platform reporta outro
        // Exemplo: UA contém "Windows NT 10.0" mas platform é "Linux x86_64"
        // Indica spoofing de User-Agent
        'platform_mismatch' => ['ativo' => true, 'pts' => 20],

        // User-Agent diz Chrome mas window.chrome é undefined
        // Chrome real sempre define o objeto window.chrome
        // Headless antigo ou Chrome emulado pode não ter
        'chrome_falso' => ['ativo' => true, 'pts' => 30],


        // ── DIMENSÕES / AMBIENTE ───────────────────────────────────────────

        // screen.width ou screen.height é 0
        // Headless sem display virtual (Xvfb) reportam tela zerada
        'tela_zero' => ['ativo' => true, 'pts' => 30],

        // window.outerWidth ou window.outerHeight é 0
        // Janela invisível ou headless sem dimensões de janela
        // NOTA: pode disparar com janela minimizada em Windows
        'janela_zero' => ['ativo' => true, 'pts' => 10],

        // Janela ocupa menos de 10% da largura da tela
        // Pode indicar janela minimizada ou ambiente automatizado
        // Exemplo: innerWidth=10 com screen.width=1920
        'janela_minima' => ['ativo' => true, 'pts' => 15],

        // performance.now() retorna < 50ms
        // Script executou extremamente rápido — ambiente pré-carregado
        // ATENÇÃO: pode gerar falso positivo em máquinas rápidas com cache
        'timing_rapido' => ['ativo' => true, 'pts' => 15],


        // ── RECURSOS AUSENTES (indicadores de headless) ────────────────────

        // navigator.hardwareConcurrency é undefined
        // Navegadores reais sempre reportam número de cores da CPU
        // Exemplo esperado: 4, 8, 16
        'sem_cpu_cores' => ['ativo' => true, 'pts' => 15],

        // window.speechSynthesis não existe
        // API de síntese de voz — presente em navegadores reais, ausente em headless
        'sem_speech' => ['ativo' => true, 'pts' => 10],

        // Chrome sem window.SharedArrayBuffer
        // Chrome real moderno tem SharedArrayBuffer
        // NOTA: requer headers COOP/COEP no servidor; sem eles dispara em usuários reais
        'sem_shared_buffer' => ['ativo' => true, 'pts' => 5],

        // typeof Notification é undefined
        // Navegadores reais suportam a Notification API
        // DESATIVADO: iOS Safari não suportava até 16.4+ — falso positivo em iPhones
        'sem_notification' => ['ativo' => false, 'pts' => 10],

        // typeof Worker ou typeof ServiceWorker é undefined
        // Navegadores modernos suportam Web Workers e Service Workers
        'sem_worker' => ['ativo' => true, 'pts' => 10],

        // screen.colorDepth é 0 ou undefined
        // Headless sem display reportam profundidade de cor inválida
        // Navegadores reais retornam 24 ou 32
        'sem_color_depth' => ['ativo' => true, 'pts' => 20],


        // ── WEBGL ──────────────────────────────────────────────────────────

        // Renderer WebGL é software (SwiftShader, LLVMpipe, Mesa, VirtualBox)
        // Headless usam renderização por software em vez de GPU real
        // Exemplo: renderer = "Google SwiftShader" ou "llvmpipe (LLVM 12.0)"
        'webgl_software' => ['ativo' => true, 'pts' => 30],

        // Sem suporte a WebGL (nem webgl nem experimental-webgl)
        // Navegadores modernos reais suportam WebGL
        'sem_webgl' => ['ativo' => true, 'pts' => 15],

        // Erro ao tentar criar contexto WebGL ou acessar extensões
        // Indica ambiente com renderização comprometida
        'webgl_erro' => ['ativo' => true, 'pts' => 10],


        // ── CANVAS ─────────────────────────────────────────────────────────

        // Canvas.toDataURL() retorna "data:," (imagem vazia)
        // Headless não renderizam canvas corretamente
        // Navegadores reais geram uma imagem PNG com dados
        'canvas_vazio' => ['ativo' => true, 'pts' => 25],

        // Erro ao tentar renderizar ou ler canvas
        // Indica ambiente sem suporte a canvas 2D
        'canvas_erro' => ['ativo' => true, 'pts' => 15],


        // ── APIS DO NAVEGADOR ──────────────────────────────────────────────

        // Notification.permission === "denied" mas Permissions API diz "prompt"
        // Inconsistência que indica manipulação ou spoofing de permissões
        'perm_inconsistente' => ['ativo' => true, 'pts' => 20],

        // Nenhum dispositivo de mídia detectado (câmera, microfone)
        // navigator.mediaDevices.enumerateDevices() retorna array vazio
        // Computadores reais geralmente têm pelo menos 1 dispositivo de áudio
        'sem_midia' => ['ativo' => true, 'pts' => 15],

        // RTT (Round Trip Time) da conexão é 0ms
        // navigator.connection.rtt === 0 indica conexão simulada ou localhost
        // Navegadores reais em rede real têm RTT > 0
        'rtt_zero' => ['ativo' => true, 'pts' => 15],

        // Página está rodando dentro de um iframe
        // window.self !== window.top
        // Pode indicar carregamento automatizado dentro de frame oculto
        'iframe' => ['ativo' => true, 'pts' => 10],

        // window.history.length <= 1
        // Primeira visita direta sem histórico de navegação
        // Comum em bots, mas também em links diretos ou abas novas
        'historico_curto' => ['ativo' => true, 'pts' => 5],

        // Chrome com window.chrome mas sem chrome.runtime
        // NOTA: chrome.runtime só existe se houver extensões instaladas
        // Chrome sem extensões não define chrome.runtime — falso positivo comum
        'sem_chrome_runtime' => ['ativo' => true, 'pts' => 5],

        // User-Agent indica mobile mas sem suporte a touch
        // Exemplo: UA contém "Android" mas ontouchstart não existe e maxTouchPoints=0
        // Indica emulação de mobile sem touch real
        'mobile_sem_touch' => ['ativo' => true, 'pts' => 25],

        // Battery API retorna valores padrão falsos
        // charging=true, chargingTime=0, dischargingTime=Infinity, level=1
        // Headless reportam bateria "perfeita" padronizada
        // DESATIVADO: notebooks reais carregados na tomada reportam os mesmos valores
        'bateria_fake' => ['ativo' => false, 'pts' => 10],

        // AudioContext retorna dados de frequência toda zerada
        // Audio fingerprint vazio indica ambiente sem hardware de áudio real
        // Navegadores reais geram dados de frequência não-zero
        'audio_vazio' => ['ativo' => true, 'pts' => 20],

        // Menos de 5 fontes do sistema detectadas (de 10 testadas)
        // Navegadores reais têm dezenas de fontes instaladas (Arial, Verdana, etc.)
        // Headless mínimo pode ter muito poucas fontes
        'poucas_fontes' => ['ativo' => true, 'pts' => 15],

        // Intl.DateTimeFormat().resolvedOptions().timeZone indefinido ou vazio
        // Navegadores reais sempre retornam timezone válido
        // Exemplo esperado: "America/Sao_Paulo"
        'sem_timezone' => ['ativo' => true, 'pts' => 15],

        // Erro ao tentar acessar timezone via Intl API
        'timezone_erro' => ['ativo' => true, 'pts' => 10],

        // Chrome sem chrome.loadTimes (possível automação via CDP)
        // Chrome DevTools Protocol pode não expor essa função
        // NOTA: chrome.loadTimes foi depreciado, pode gerar falso positivo
        'cdp_detectado' => ['ativo' => true, 'pts' => 5],

        // Nenhuma interação do usuário em 4 segundos
        // Monitora: mousemove, touchstart, click, keydown, scroll, pointer
        // Bots não interagem com a página; usuários reais movem o mouse
        'sem_interacao' => ['ativo' => true, 'pts' => 10],

        // Math.tan(-1e300) retorna valor diferente de -1.4214488238747245
        // Engines JS diferentes podem ter implementações matemáticas ligeiramente
        // diferentes. Pode indicar ambiente modificado ou engine não-padrão
        // DESATIVADO: Safari/JavaScriptCore retorna valor diferente do V8 — falso positivo em iPhones
        'math_diferente' => ['ativo' => false, 'pts' => 10],

    ],
];

// Quando acessado via HTTP, retorna JSON sem credenciais do painel
if (!defined('ANTIBOT_INTERNAL')) {
    header('Content-Type: application/json');
    $public = $config;
    unset($public['painel_usuario'], $public['painel_senha']);
    echo json_encode($public);
}

return $config;
