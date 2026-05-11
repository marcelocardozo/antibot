/**
 * AntiBot — Detecção client-side de bots / automação / proxy / VPN
 *
 * Uso:
 *   <script src="/ab/antibot.js"></script>
 *
 * O script auto-detecta o caminho da pasta ab/ pelo atributo src,
 * busca as configurações via ab/config.php (API JSON) e inicializa.
 * Não gera nenhum HTML — a página deve fornecer os elementos:
 *
 *   #abProgressBar  — elemento cuja width será animada (0% → 100%)
 *   #abStatusText   — elemento cujo textContent exibe as mensagens de progresso
 *
 * Configurações em ab/config.php (JSON):
 *
 *   redirectUrl   (string)  — URL de redirecionamento em caso de sucesso
 *   tempoMinimo   (number)  — Tempo mínimo da animação em ms (padrão: 5000)
 *   scoreMinimo   (number)  — Score mínimo para bloquear (padrão: 50)
 *   mensagens     (array)   — Mensagens de progresso
 *   regras        (object)  — Regras de detecção { label: { ativo, pts } }
 */
var AntiBot = (function () {
    'use strict';

    var DEFAULTS = {
        redirectUrl: '/',
        tempoMinimo: 5000,
        scoreMinimo: 50,
        mensagens: [
            'Iniciando verificação',
            'Analisando conexão',
            'Verificando localização',
            'Validando dispositivo',
            'Finalizando'
        ]
    };

    var DEFAULT_REGRAS = {
        webdriver:          { ativo: true, pts: 80 },
        chromedriver:       { ativo: true, pts: 80 },
        selenium:           { ativo: true, pts: 80 },
        puppeteer:          { ativo: true, pts: 80 },
        puppeteer_obj:      { ativo: true, pts: 80 },
        playwright:         { ativo: true, pts: 80 },
        playwright_key:     { ativo: true, pts: 80 },
        cypress:            { ativo: true, pts: 80 },
        phantomjs:          { ativo: true, pts: 80 },
        nightmare:          { ativo: true, pts: 80 },
        webdriverio:        { ativo: true, pts: 80 },
        testcafe:           { ativo: true, pts: 80 },
        browserless:        { ativo: true, pts: 80 },
        stack_automacao:    { ativo: true, pts: 80 },
        ua_suspeito:        { ativo: true, pts: 60 },
        webdriver_patched:  { ativo: true, pts: 60 },
        ua_ferramenta:      { ativo: true, pts: 80 },
        ua_curto:           { ativo: true, pts: 30 },
        chrome_falso:       { ativo: true, pts: 30 },
        tela_zero:          { ativo: true, pts: 30 },
        webgl_software:     { ativo: true, pts: 30 },
        janela_zero:        { ativo: true, pts: 25 },
        canvas_vazio:       { ativo: true, pts: 25 },
        mobile_sem_touch:   { ativo: true, pts: 25 },
        platform_mismatch:  { ativo: true, pts: 20 },
        perm_inconsistente: { ativo: true, pts: 20 },
        audio_vazio:        { ativo: true, pts: 20 },
        sem_color_depth:    { ativo: true, pts: 20 },
        sem_idiomas:        { ativo: true, pts: 15 },
        timing_rapido:      { ativo: true, pts: 15 },
        sem_cpu_cores:      { ativo: true, pts: 15 },
        sem_webgl:          { ativo: true, pts: 15 },
        sem_midia:          { ativo: true, pts: 15 },
        rtt_zero:           { ativo: true, pts: 15 },
        sem_chrome_runtime: { ativo: true, pts: 15 },
        poucas_fontes:      { ativo: true, pts: 15 },
        sem_timezone:       { ativo: true, pts: 15 },
        janela_minima:      { ativo: true, pts: 15 },
        canvas_erro:        { ativo: true, pts: 15 },
        sem_accept_lang:    { ativo: true, pts: 15 },
        sem_plugins:        { ativo: true, pts: 10 },
        sem_speech:         { ativo: true, pts: 10 },
        sem_shared_buffer:  { ativo: true, pts: 10 },
        iframe:             { ativo: true, pts: 10 },
        bateria_fake:       { ativo: true, pts: 10 },
        sem_notification:   { ativo: true, pts: 10 },
        sem_accept_enc:     { ativo: true, pts: 10 },
        accept_generico:    { ativo: true, pts: 10 },
        conn_close:         { ativo: true, pts: 10 },
        sem_worker:         { ativo: true, pts: 10 },
        math_diferente:     { ativo: true, pts: 10 },
        webgl_erro:         { ativo: true, pts: 10 },
        timezone_erro:      { ativo: true, pts: 10 },
        sem_interacao:      { ativo: true, pts: 10 },
        historico_curto:    { ativo: true, pts: 5 },
        cdp_detectado:      { ativo: true, pts: 5 }
    };

    /* ───────── auto-detect ab/ path ───────── */

    function detectAbPath() {
        var scripts = document.getElementsByTagName('script');
        for (var i = scripts.length - 1; i >= 0; i--) {
            var src = scripts[i].src || '';
            var match = src.match(/^(.*\/ab)\/antibot\.js(\?.*)?$/i);
            if (match) {
                try {
                    var url = new URL(match[1]);
                    return url.pathname.replace(/\/+$/, '');
                } catch (e) {
                    return match[1].replace(/\/+$/, '');
                }
            }
        }
        return '/ab';
    }

    /* ───────── helpers ───────── */

    function merge(defaults, overrides) {
        var result = {};
        for (var k in defaults) {
            if (defaults.hasOwnProperty(k)) result[k] = defaults[k];
        }
        for (var k2 in overrides) {
            if (overrides.hasOwnProperty(k2) && overrides[k2] !== undefined) result[k2] = overrides[k2];
        }
        return result;
    }

    function mergeRegras(overrides) {
        var result = {};
        for (var k in DEFAULT_REGRAS) {
            if (DEFAULT_REGRAS.hasOwnProperty(k)) {
                result[k] = { ativo: DEFAULT_REGRAS[k].ativo, pts: DEFAULT_REGRAS[k].pts };
            }
        }
        if (overrides) {
            for (var k2 in overrides) {
                if (overrides.hasOwnProperty(k2) && result[k2]) {
                    if (overrides[k2].ativo !== undefined) result[k2].ativo = overrides[k2].ativo;
                    if (overrides[k2].pts !== undefined) result[k2].pts = overrides[k2].pts;
                }
            }
        }
        return result;
    }

    /* ───────── DETECÇÃO ───────── */

    function runDetection(regras) {
        var score = 0;
        var hits = [];

        function chk(label) {
            var r = regras[label];
            return (r && r.ativo !== false) ? r.pts : 0;
        }

        function add(pts, label) {
            if (pts > 0) { score += pts; hits.push(label + '+' + pts); }
        }

        // 1. WEBDRIVER FLAG
        if (navigator.webdriver === true) add(chk('webdriver'), 'webdriver');

        // 2. CHROMEDRIVER — variáveis injetadas no document
        if (chk('chromedriver')) {
            try {
                var docKeys = Object.keys(document);
                for (var i = 0; i < docKeys.length; i++) {
                    if (/^\$cdc_|^cdc_/.test(docKeys[i])) { add(chk('chromedriver'), 'chromedriver'); break; }
                }
            } catch (e) {}
        }

        // 3. SELENIUM / WEBDRIVER propriedades globais
        if (chk('selenium')) {
            var seleniumProps = [
                '__webdriver_evaluate', '__selenium_evaluate',
                '__webdriver_script_function', '__webdriver_script_func',
                '__webdriver_script_fn', '__fxdriver_evaluate',
                '__driver_unwrapped', '__webdriver_unwrapped',
                '__driver_evaluate', '__selenium_unwrapped',
                '_Selenium_IDE_Recorder', '_selenium',
                'callSelenium', 'calledSelenium',
                '_WEBDRIVER_ELEM_CACHE', 'ChromeDriverw',
                'domAutomation', 'domAutomationController'
            ];
            for (var j = 0; j < seleniumProps.length; j++) {
                if (seleniumProps[j] in window || seleniumProps[j] in document) { add(chk('selenium'), 'selenium'); break; }
            }
        }

        // 4. PUPPETEER
        if (window.__puppeteer_evaluation_script__ !== undefined) add(chk('puppeteer'), 'puppeteer');
        try { if (window.puppeteer || navigator.pptr) add(chk('puppeteer_obj'), 'puppeteer_obj'); } catch (e) {}

        // 5. PLAYWRIGHT
        try {
            if (window.__playwright || window.__pw_manual || window._playwrightInstance) add(chk('playwright'), 'playwright');
            if (chk('playwright_key')) {
                var pwKeys = Object.keys(window);
                for (var p = 0; p < pwKeys.length; p++) {
                    if (/^__playwright/.test(pwKeys[p])) { add(chk('playwright_key'), 'playwright_key'); break; }
                }
            }
        } catch (e) {}

        // 6. CYPRESS
        try { if (window.Cypress || window.cy || window.__cypress) add(chk('cypress'), 'cypress'); } catch (e) {}

        // 7. PHANTOMJS
        try { if (window.callPhantom || window._phantom || window.phantom) add(chk('phantomjs'), 'phantomjs'); } catch (e) {}

        // 8. NIGHTMAREJS
        try { if (window.__nightmare) add(chk('nightmare'), 'nightmare'); } catch (e) {}

        // 9. WEBDRIVERIO
        try { if (window.wdio || window.__wdio) add(chk('webdriverio'), 'webdriverio'); } catch (e) {}

        // 10. TESTCAFE
        try { if (window['%testCafeDriverInstance%'] || window.__testCafe) add(chk('testcafe'), 'testcafe'); } catch (e) {}

        // 11. BROWSERLESS / CHROME-AWS-LAMBDA
        try { if (window.__browserless || window.__chrome_aws_lambda) add(chk('browserless'), 'browserless'); } catch (e) {}

        // 12. UA string
        var ua = navigator.userAgent.toLowerCase();
        if (/headless|phantom|slimer|puppeteer|playwright|selenium|cypress|nightmarejs|webdriverio|testcafe|browserless|crawler|spider|bot|scrapy|wget|curl|httpie|python-requests|python-urllib|java\/|httpclient|libwww|mechanize|aiohttp|node-fetch|axios\/|got\/|undici/.test(ua)) add(chk('ua_suspeito'), 'ua_suspeito');

        // 13. FINGERPRINT — plugins e idiomas
        if (navigator.plugins.length === 0) add(chk('sem_plugins'), 'sem_plugins');
        if (!navigator.languages || navigator.languages.length === 0) add(chk('sem_idiomas'), 'sem_idiomas');

        // 14. PLATFORM MISMATCH
        var platform = (navigator.platform || '').toLowerCase();
        if (ua.includes('windows') && platform && !platform.includes('win')) add(chk('platform_mismatch'), 'platform_mismatch');
        if (ua.includes('mac') && platform && !platform.includes('mac')) add(chk('platform_mismatch'), 'platform_mismatch');
        if (ua.includes('linux') && platform && !platform.includes('linux')) add(chk('platform_mismatch'), 'platform_mismatch');

        // 15. CHROME sem objeto chrome
        if (ua.includes('chrome') && typeof window.chrome === 'undefined') add(chk('chrome_falso'), 'chrome_falso');

        // 16. DIMENSÕES SUSPEITAS
        if (screen.width === 0 || screen.height === 0) add(chk('tela_zero'), 'tela_zero');
        if (window.outerWidth === 0 || window.outerHeight === 0) add(chk('janela_zero'), 'janela_zero');

        // 17. TIMING
        if (performance.now() < 50) add(chk('timing_rapido'), 'timing_rapido');

        // 18. HEADLESS — recursos ausentes
        if (navigator.hardwareConcurrency === undefined) add(chk('sem_cpu_cores'), 'sem_cpu_cores');
        if (!window.speechSynthesis) add(chk('sem_speech'), 'sem_speech');
        if (typeof window.SharedArrayBuffer === 'undefined' && ua.includes('chrome')) add(chk('sem_shared_buffer'), 'sem_shared_buffer');

        // 19. WEBGL SOFTWARE RENDERER
        try {
            var canvas = document.createElement('canvas');
            var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (gl) {
                var debug = gl.getExtension('WEBGL_debug_renderer_info');
                if (debug) {
                    var renderer = gl.getParameter(debug.UNMASKED_RENDERER_WEBGL);
                    if (/swiftshader|llvmpipe|virtualbox|software|mesa/i.test(renderer)) add(chk('webgl_software'), 'webgl_software');
                }
            } else {
                add(chk('sem_webgl'), 'sem_webgl');
            }
        } catch (e) { add(chk('webgl_erro'), 'webgl_erro'); }

        // 20. PERMISSÕES INCONSISTENTES
        if (chk('perm_inconsistente')) {
            try {
                var perm = navigator.permissions;
                if (perm) {
                    perm.query({ name: 'notifications' }).then(function (p) {
                        if (typeof Notification !== 'undefined' && Notification.permission === 'denied' && p.state === 'prompt') {
                            add(chk('perm_inconsistente'), 'perm_inconsistente');
                        }
                    }).catch(function () {});
                }
            } catch (e) {}
        }

        // 21. CANVAS FINGERPRINT
        try {
            var cvs = document.createElement('canvas');
            cvs.width = 200; cvs.height = 50;
            var ctx = cvs.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('antibot:test', 2, 15);
            var data = cvs.toDataURL();
            if (data === 'data:,') add(chk('canvas_vazio'), 'canvas_vazio');
        } catch (e) { add(chk('canvas_erro'), 'canvas_erro'); }

        // 22. MEDIA DEVICES
        if (chk('sem_midia')) {
            try {
                if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
                    navigator.mediaDevices.enumerateDevices().then(function (devices) {
                        if (devices.length === 0) add(chk('sem_midia'), 'sem_midia');
                    }).catch(function () {});
                }
            } catch (e) {}
        }

        // 23. CONEXÃO DE REDE
        try {
            var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (conn && conn.rtt === 0) add(chk('rtt_zero'), 'rtt_zero');
        } catch (e) {}

        // 24. IFRAME OCULTO
        try { if (window.self !== window.top) add(chk('iframe'), 'iframe'); } catch (e) { add(chk('iframe'), 'iframe'); }

        // 25. HISTORY LENGTH
        if (window.history.length <= 1) add(chk('historico_curto'), 'historico_curto');

        // 26. CHROME RUNTIME
        try {
            if (ua.includes('chrome') && window.chrome && !window.chrome.runtime) add(chk('sem_chrome_runtime'), 'sem_chrome_runtime');
        } catch (e) {}

        // 27. TOUCH INCONSISTÊNCIA
        try {
            var mobileUA = /android|iphone|ipad|ipod|mobile/i.test(navigator.userAgent);
            var hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            if (mobileUA && !hasTouch) add(chk('mobile_sem_touch'), 'mobile_sem_touch');
        } catch (e) {}

        // 28. BATTERY API
        if (chk('bateria_fake')) {
            try {
                if (navigator.getBattery) {
                    navigator.getBattery().then(function (b) {
                        if (b.charging && b.chargingTime === 0 && b.dischargingTime === Infinity && b.level === 1) {
                            add(chk('bateria_fake'), 'bateria_fake');
                        }
                    }).catch(function () {});
                }
            } catch (e) {}
        }

        // 29. AUDIO CONTEXT FINGERPRINT
        if (chk('audio_vazio')) {
            try {
                var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                var oscillator = audioCtx.createOscillator();
                var analyser = audioCtx.createAnalyser();
                var gain = audioCtx.createGain();
                var scriptProcessor = audioCtx.createScriptProcessor(4096, 1, 1);
                gain.gain.value = 0;
                oscillator.connect(analyser);
                analyser.connect(scriptProcessor);
                scriptProcessor.connect(gain);
                gain.connect(audioCtx.destination);
                oscillator.start(0);
                scriptProcessor.onaudioprocess = function (e) {
                    var freqData = new Float32Array(analyser.frequencyBinCount);
                    analyser.getFloatFrequencyData(freqData);
                    var sum = 0;
                    for (var x = 0; x < freqData.length; x++) sum += Math.abs(freqData[x]);
                    if (sum === 0) add(chk('audio_vazio'), 'audio_vazio');
                    oscillator.disconnect();
                    scriptProcessor.disconnect();
                    gain.disconnect();
                    audioCtx.close();
                };
            } catch (e) {}
        }

        // 30. FONTES DO SISTEMA
        if (chk('poucas_fontes')) {
            try {
                var testFonts = ['Arial', 'Verdana', 'Times New Roman', 'Georgia', 'Courier New', 'Comic Sans MS', 'Impact', 'Trebuchet MS', 'Tahoma', 'Palatino'];
                var baseFonts = ['monospace', 'sans-serif', 'serif'];
                var testStr = 'mmmmmmmmmmlli';
                var testSize = '72px';
                var span = document.createElement('span');
                span.style.position = 'absolute';
                span.style.left = '-9999px';
                span.style.fontSize = testSize;
                span.textContent = testStr;
                document.body.appendChild(span);
                var detected = 0;
                baseFonts.forEach(function (base) {
                    span.style.fontFamily = base;
                    var baseWidth = span.offsetWidth;
                    testFonts.forEach(function (font) {
                        span.style.fontFamily = "'" + font + "', " + base;
                        if (span.offsetWidth !== baseWidth) detected++;
                    });
                });
                document.body.removeChild(span);
                if (detected < 5) add(chk('poucas_fontes'), 'poucas_fontes');
            } catch (e) {}
        }

        // 31. TIMEZONE COERÊNCIA
        try {
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (!tz || tz === 'undefined') add(chk('sem_timezone'), 'sem_timezone');
        } catch (e) { add(chk('timezone_erro'), 'timezone_erro'); }

        // 32. STACK TRACE
        if (chk('stack_automacao')) {
            try {
                var err = new Error();
                var stack = err.stack || '';
                if (/puppeteer|playwright|selenium|webdriver|cypress|nightmare|phantomjs/i.test(stack)) add(chk('stack_automacao'), 'stack_automacao');
            } catch (e) {}
        }

        // 33. NOTIFICATION API
        try { if (typeof Notification === 'undefined') add(chk('sem_notification'), 'sem_notification'); } catch (e) {}

        // 34. SCREEN COLOR DEPTH
        try {
            if (screen.colorDepth === 0 || screen.colorDepth === undefined) add(chk('sem_color_depth'), 'sem_color_depth');
        } catch (e) {}

        // 35. DEVTOOLS PROTOCOL
        try {
            var devtools = /Chrome\//.test(navigator.userAgent) && !window.chrome?.loadTimes;
            if (devtools && window.chrome) add(chk('cdp_detectado'), 'cdp_detectado');
        } catch (e) {}

        // 36. MOUSE/TOUCH TRAP
        if (chk('sem_interacao')) {
            var hadInteraction = false;
            var interactionEvents = ['mousemove', 'touchstart', 'touchmove', 'click', 'keydown', 'scroll', 'pointerdown', 'pointermove'];
            var markInteraction = function () { hadInteraction = true; };
            interactionEvents.forEach(function (evt) {
                window.addEventListener(evt, markInteraction, { once: true, passive: true });
            });
            setTimeout(function () {
                if (!hadInteraction) add(chk('sem_interacao'), 'sem_interacao');
                interactionEvents.forEach(function (evt) {
                    window.removeEventListener(evt, markInteraction);
                });
            }, 4000);
        }

        // 37. UNDETECTED-CHROMEDRIVER
        try {
            var descriptors = Object.getOwnPropertyDescriptors(navigator);
            if (descriptors.webdriver && descriptors.webdriver.get) add(chk('webdriver_patched'), 'webdriver_patched');
        } catch (e) {}

        // 38. WINDOW SIZE vs SCREEN
        try {
            var ratio = window.innerWidth / screen.width;
            if (ratio < 0.1 && screen.width > 0) add(chk('janela_minima'), 'janela_minima');
        } catch (e) {}

        // 39. WORKER SUPPORT
        try {
            if (typeof Worker === 'undefined' || typeof ServiceWorker === 'undefined') add(chk('sem_worker'), 'sem_worker');
        } catch (e) {}

        // 40. MATH FINGERPRINT
        try {
            var mathCheck = Math.tan(-1e300);
            if (mathCheck !== -1.4214488238747245) add(chk('math_diferente'), 'math_diferente');
        } catch (e) {}

        return {
            getScore: function () { return score; },
            getHits: function () { return hits; },
            addScore: function (label) {
                add(chk(label), label);
            }
        };
    }

    /* ───────── CORE ───────── */

    function start(cfg, AB_PATH) {
        var URL_404 = AB_PATH + '/templates/404.html';
        var TEMPO_MINIMO = cfg.tempoMinimo;
        var SCORE_MINIMO = cfg.scoreMinimo || 50;
        var regras = mergeRegras(cfg.regras);
        var inicio = Date.now();

        // Run client-side detection
        var detection = runDetection(regras);

        // Fetch server-side APIs
        Promise.all([
            fetch(AB_PATH + '/apis/proxycheckio.php').then(function (r) { return r.json(); }).catch(function () { return { status: 'erro' }; }),
            fetch(AB_PATH + '/apis/device_detector.php').then(function (r) { return r.json(); }).catch(function () { return { status: 'erro' }; })
        ]).then(function (results) {
            var proxyData = results[0];
            var deviceData = results[1];

            var bot = deviceData.status === 'success' ? deviceData.bot : null;
            var isocode = proxyData.status === 'success' ? proxyData.isocode : null;
            var proxy = proxyData.status === 'success' ? proxyData.proxy : null;
            var vpn = proxyData.status === 'success' ? proxyData.vpn : null;

            // 41. SERVER FLAGS
            var sFlags = proxyData.server_flags || [];
            if (sFlags.includes('tool_ua')) detection.addScore('ua_ferramenta');
            if (sFlags.includes('short_ua')) detection.addScore('ua_curto');
            if (sFlags.includes('no_accept_language')) detection.addScore('sem_accept_lang');
            if (sFlags.includes('no_accept_encoding')) detection.addScore('sem_accept_enc');
            if (sFlags.includes('generic_accept')) detection.addScore('accept_generico');
            if (sFlags.includes('connection_close')) detection.addScore('conn_close');

            var ehBot = detection.getScore() >= SCORE_MINIMO;

            // Motivos de bloqueio
            var motivos = [];
            if (ehBot) motivos.push('score=' + detection.getScore() + ' (' + detection.getHits().join(', ') + ')');
            if (cfg.bloquear_bot !== false && bot === 'true') motivos.push('bot');
            var paisesPermitidos = cfg.paises_permitidos || [];
            if (paisesPermitidos.length > 0 && isocode !== null && paisesPermitidos.indexOf(isocode) === -1) motivos.push('pais=' + isocode);
            if (cfg.bloquear_proxy !== false && proxy === 'yes') motivos.push('proxy');
            if (cfg.bloquear_vpn !== false && vpn === 'yes') motivos.push('vpn');

            var bloqueado = motivos.length > 0;

            var dados = {
                data_hora: new Date().toISOString(),
                ip: proxyData.ip ?? null,
                url: window.location.href,
                asn: proxyData.asn ?? null,
                hostname: proxyData.hostname ?? null,
                provider: proxyData.provider ?? null,
                organisation: proxyData.organisation ?? null,
                isocode: isocode,
                regioncode: proxyData.regioncode ?? null,
                city: proxyData.city ?? null,
                proxy: proxy,
                vpn: vpn,
                bot: bot,
                client_name: deviceData.client_name ?? null,
                client_type: deviceData.client_type ?? null,
                client_version: deviceData.client_version ?? null,
                device_brand: deviceData.device_brand ?? null,
                device_model: deviceData.device_model ?? null,
                device_type: deviceData.device_type ?? null,
                os_name: deviceData.os_name ?? null,
                os_platform: deviceData.os_platform ?? null,
                os_version: deviceData.os_version ?? null,
                os_family: deviceData.os_family ?? null,
                bloqueado: bloqueado ? 'true' : 'false',
                motivo_bloqueio: motivos.length > 0 ? motivos.join(', ') : null
            };

            function salvar() {
                fetch(AB_PATH + '/apis/salvar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dados)
                })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    var elapsed = Date.now() - inicio;
                    var restante = Math.max(0, TEMPO_MINIMO - elapsed);

                    setTimeout(function () {
                        if (bloqueado) {
                            registrarNavegacao();
                            fetch(URL_404)
                                .then(function (r) { return r.text(); })
                                .then(function (html) { document.open(); document.write(html); document.close(); })
                                .catch(function () { document.documentElement.innerHTML = '<h1>404</h1>'; });
                        } else {
                            sessionStorage.setItem('ab_id_verify', result.id);
                            registrarNavegacao();
                            if (cfg.redirectUrl) window.location.href = cfg.redirectUrl;
                            else liberarPagina();
                        }
                    }, restante);
                })
                .catch(function () {
                    setTimeout(salvar, 2000);
                });
            }

            salvar();
        });
    }

    /* ───────── BOOT ───────── */

    var AB_PATH = detectAbPath();

    var _abSpinner = null;

    // Esconde a página e mostra spinner até a verificação concluir
    function esconderPagina() {
        document.documentElement.style.visibility = 'hidden';
        document.documentElement.style.overflow = 'hidden';

        _abSpinner = document.createElement('div');
        _abSpinner.id = 'ab-spinner';
        _abSpinner.innerHTML =
            '<style>' +
            '#ab-spinner{position:fixed;inset:0;z-index:2147483647;background:#fff;display:flex;align-items:center;justify-content:center;visibility:visible!important}' +
            '#ab-spinner .sp{width:40px;height:40px;border:3px solid #e0e0e0;border-top-color:#888;border-radius:50%;animation:ab-spin .7s linear infinite}' +
            '@keyframes ab-spin{to{transform:rotate(360deg)}}' +
            '</style>' +
            '<div class="sp"></div>';
        document.documentElement.appendChild(_abSpinner);
    }

    // Libera a página após aprovação confirmada no banco
    function liberarPagina() {
        if (_abSpinner && _abSpinner.parentNode) {
            _abSpinner.parentNode.removeChild(_abSpinner);
        }
        document.documentElement.style.visibility = '';
        document.documentElement.style.overflow = '';
    }

    function boot() {
        esconderPagina();

        fetch(AB_PATH + '/config.php')
            .then(function (r) { return r.json(); })
            .then(function (apiConfig) {
                var cfg = merge(DEFAULTS, apiConfig);

                var paginaIni = cfg.paginaInicial || '/';
                var pathname = window.location.pathname;
                // Verifica se é a página inicial considerando subpastas e acesso sem filename
                // Ex: paginaInicial='/index.php' deve casar com '/antibot/index.php' e '/antibot/'
                var ehPaginaInicial = pathname === paginaIni || pathname.endsWith(paginaIni);
                if (!ehPaginaInicial && pathname.endsWith('/')) {
                    // Acesso ao diretório sem filename (ex: /antibot/ em vez de /antibot/index.php)
                    // Concatena o filename do paginaInicial e verifica se bate
                    var filename = paginaIni.split('/').pop(); // ex: 'index.php'
                    var tentativa = pathname + filename;       // ex: '/antibot/index.php'
                    ehPaginaInicial = tentativa === paginaIni || tentativa.endsWith(paginaIni);
                }

                // Monta fingerprint do navegador para consulta por IP
                function buildFingerprint() {
                    var ua = navigator.userAgent || '';
                    var fp = [];
                    if (/Edg\//i.test(ua)) fp.push('client_name=Edge');
                    else if (/OPR\//i.test(ua) || /Opera/i.test(ua)) fp.push('client_name=Opera');
                    else if (/Firefox\//i.test(ua)) fp.push('client_name=Firefox');
                    else if (/Chrome\//i.test(ua)) fp.push('client_name=Chrome');
                    else if (/Safari\//i.test(ua)) fp.push('client_name=Safari');
                    if (/Windows/i.test(ua)) fp.push('os_name=Windows');
                    else if (/Android/i.test(ua)) fp.push('os_name=Android');
                    else if (/iPhone|iPad|iPod/i.test(ua)) fp.push('os_name=iOS');
                    else if (/Mac OS/i.test(ua)) fp.push('os_name=Mac');
                    else if (/Linux/i.test(ua)) fp.push('os_name=GNU/Linux');
                    if (/Mobi/i.test(ua)) fp.push('device_type=smartphone');
                    else if (/Tablet|iPad/i.test(ua)) fp.push('device_type=tablet');
                    else fp.push('device_type=desktop');
                    return fp.length ? '?' + fp.join('&') : '';
                }

                // Bloqueia o visitante mostrando a página 404
                function bloquear() {
                    fetch(AB_PATH + '/templates/404.html')
                        .then(function (r) { return r.text(); })
                        .then(function (html) { document.open(); document.write(html); document.close(); })
                        .catch(function () { document.documentElement.innerHTML = '<h1>404</h1>'; });
                }

                var savedId = sessionStorage.getItem('ab_id_verify');

                if (savedId) {
                    // Tem id salvo — verifica no banco
                    fetch(AB_PATH + '/apis/sessao.php?id=' + encodeURIComponent(savedId))
                        .then(function (r) { return r.json(); })
                        .then(function (sessao) {
                            if (sessao.status === 'bloqueado') {
                                registrarNavegacao();
                                bloquear();
                            } else if (sessao.status === 'aprovado') {
                                registrarNavegacao();
                                if (ehPaginaInicial && cfg.redirectUrl) window.location.href = cfg.redirectUrl;
                                else liberarPagina();
                            } else {
                                sessionStorage.removeItem('ab_id_verify');
                                start(cfg, AB_PATH);
                            }
                        })
                        .catch(function () {
                            start(cfg, AB_PATH);
                        });
                } else {
                    // Sem id — verifica pelo IP + fingerprint
                    fetch(AB_PATH + '/apis/sessao.php' + buildFingerprint())
                        .then(function (r) { return r.json(); })
                        .then(function (sessao) {
                            if (sessao.status === 'bloqueado') {
                                registrarNavegacao();
                                bloquear();
                            } else if (sessao.status === 'aprovado') {
                                registrarNavegacao();
                                if (ehPaginaInicial && cfg.redirectUrl) window.location.href = cfg.redirectUrl;
                                else liberarPagina();
                            } else {
                                // Novo visitante — executa detecção completa
                                start(cfg, AB_PATH);
                            }
                        })
                        .catch(function () {
                            start(cfg, AB_PATH);
                        });
                }
            })
            .catch(function () {
                start(merge(DEFAULTS, {}), AB_PATH);
            });
    }

    // Registra a página visitada (todos os visitantes, inclusive bloqueados)
    function registrarNavegacao() {
        var acessoId = sessionStorage.getItem('ab_id_verify');
        fetch(AB_PATH + '/apis/navegacao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acesso_id: acessoId ? parseInt(acessoId, 10) : null,
                url: window.location.href,
                referrer: document.referrer || null
            })
        }).catch(function () {});
    }

    if (document.body) {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            boot();
        });
    }

    return { AB_PATH: AB_PATH };
})();
