/**
 * Protección CSRF para las peticiones del navegador.
 *
 * Cárgalo antes que los scripts de la página. Hace tres cosas:
 *  - fetch(): añade la cabecera X-CSRF-Token a las peticiones POST/PUT/DELETE del mismo sitio.
 *  - jQuery $.ajax: lo mismo, si jQuery está cargado.
 *  - Formularios POST: añade un campo oculto csrf_token.
 * Además expone window.kahirCsrfToken() para navigator.sendBeacon (que no admite cabeceras).
 *
 * El token sale de <meta name="csrf-token"> si la página lo trae (páginas PHP)
 * o de includes/csrf_token.php (páginas HTML estáticas).
 */
(function () {
    'use strict';

    const scriptUrl = document.currentScript ? document.currentScript.src : '';
    const baseUrl = scriptUrl ? scriptUrl.substring(0, scriptUrl.lastIndexOf('/') + 1) : '';
    const tokenUrl = baseUrl + 'includes/csrf_token.php';
    const originalFetch = window.fetch ? window.fetch.bind(window) : null;

    let token = '';
    let tokenPromise = null;

    function tokenFromMeta() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta && meta.content ? meta.content : '';
    }

    function loadToken() {
        if (token) {
            return Promise.resolve(token);
        }
        const fromMeta = tokenFromMeta();
        if (fromMeta) {
            token = fromMeta;
            return Promise.resolve(token);
        }
        if (!tokenPromise) {
            if (!originalFetch) {
                return Promise.resolve('');
            }
            tokenPromise = originalFetch(tokenUrl, { credentials: 'same-origin', cache: 'no-store' })
                .then(r => (r.ok ? r.json() : {}))
                .then(data => {
                    token = (data && data.token) || '';
                    return token;
                })
                .catch(() => {
                    tokenPromise = null; // reintentar en la próxima petición
                    return '';
                });
        }
        return tokenPromise;
    }

    function isSameOrigin(url) {
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function needsToken(method) {
        return !/^(GET|HEAD|OPTIONS)$/i.test(method || 'GET');
    }

    // --- fetch ---------------------------------------------------------------
    if (originalFetch) {
        window.fetch = function (input, init) {
            init = init || {};
            const url = typeof input === 'string' ? input : (input && input.url) || '';
            const method = init.method || (input && input.method) || 'GET';

            if (!needsToken(method) || !isSameOrigin(url)) {
                return originalFetch(input, init);
            }
            return loadToken().then(t => {
                const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
                if (t && !headers.has('X-CSRF-Token')) {
                    headers.set('X-CSRF-Token', t);
                }
                return originalFetch(input, Object.assign({}, init, { headers }));
            });
        };
    }

    // --- jQuery --------------------------------------------------------------
    function hookJQuery() {
        const $ = window.jQuery;
        if (!$ || !$.ajaxPrefilter || $.__kahirCsrf) {
            return;
        }
        $.__kahirCsrf = true;
        $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
            const method = options.type || options.method || 'GET';
            if (needsToken(method) && isSameOrigin(options.url)) {
                const t = token || tokenFromMeta();
                if (t) {
                    jqXHR.setRequestHeader('X-CSRF-Token', t);
                }
            }
        });
    }

    // --- Formularios ---------------------------------------------------------
    function addTokenToForm(form, t) {
        if (!t || (form.method || '').toUpperCase() !== 'POST' || !isSameOrigin(form.action || window.location.href)) {
            return;
        }
        let field = form.querySelector('input[name="csrf_token"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'csrf_token';
            form.appendChild(field);
        }
        if (!field.value) {
            field.value = t;
        }
    }

    function protectForms() {
        loadToken().then(t => {
            document.querySelectorAll('form').forEach(f => addTokenToForm(f, t));
        });
    }

    // Por si se envía un formulario antes de que llegue el token.
    // Se escucha en fase de burbuja: si la validación propia de la página cancela el envío
    // (preventDefault), no se hace nada.
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement)
            || (form.method || '').toUpperCase() !== 'POST') {
            return;
        }
        if (token) {
            addTokenToForm(form, token);
            return;
        }
        event.preventDefault();
        loadToken().then(t => {
            addTokenToForm(form, t);
            HTMLFormElement.prototype.submit.call(form);
        });
    });

    // --- API pública ---------------------------------------------------------
    window.kahirCsrfToken = function () {
        return token || tokenFromMeta();
    };
    window.kahirCsrfReady = loadToken;

    hookJQuery();
    loadToken();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            hookJQuery();
            protectForms();
        });
    } else {
        protectForms();
    }
})();
