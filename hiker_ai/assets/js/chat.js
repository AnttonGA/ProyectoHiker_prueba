const ChatApp = {
    config: {},
    context: [],
    isTyping: false,
    lastMessageTime: null,

    init(config) {
        this.config = config;
        this.setupEventListeners();
        this.showPromptExamples();
        this.checkPrefill();
    },

    /* Pre-fill the input if the user arrived from the onboarding card */
    checkPrefill() {
        const prefill = localStorage.getItem('kahir_chat_prefill');
        if (!prefill) return;
        localStorage.removeItem('kahir_chat_prefill');
        const input = document.getElementById('user-input');
        if (input) {
            input.value = prefill;
            input.dispatchEvent(new Event('input')); // update char counter
            input.focus();
        }
    },

    setupEventListeners() {
        // Form submission
        document.getElementById('chat-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleUserInput();
        });

        // Contador de caracteres
        const userInput = document.getElementById('user-input');
        userInput.addEventListener('input', () => {
            const count = userInput.value.length;
            document.getElementById('char-count').textContent = count;
            
            if (count > 450) {
                document.getElementById('char-counter').classList.add('text-danger');
            } else {
                document.getElementById('char-counter').classList.remove('text-danger');
            }
        });

        // Manejar sugerencias de prompts
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('prompt-example') || e.target.hasAttribute('data-prompt')) {
                const prompt = e.target.getAttribute('data-prompt');
                if (prompt) {
                    userInput.value = prompt;
                    this.handleUserInput();
                }
            }
        });
    },

    showPromptExamples() {
        const promptContainer = document.getElementById('prompt-container');
        if (!promptContainer) return;

        const examples = [
            {
                title: "Buscar rutas",
                icon: "bi bi-signpost-split",
                description: "La ruta adecuada para tu próxima salida",
                prompts: [
                    "Busco una ruta fácil cerca de Candanchú",
                    "Ruta de senderismo para principiantes",
                    "Rutas de ski touring en Formigal"
                ]
            },
            {
                title: "Consultar el tiempo",
                icon: "bi bi-cloud-sun",
                description: "Parte, viento y nieve antes de salir",
                prompts: [
                    "¿Qué tiempo hace hoy en Panticosa?",
                    "Previsión meteorológica para mañana en Astún",
                    "Condiciones de nieve en el Valle de Tena"
                ]
            },
            {
                title: "Equipo y seguridad",
                icon: "bi bi-backpack2",
                description: "Material, épocas y buenas prácticas",
                prompts: [
                    "¿Qué equipo necesito para ski de montaña?",
                    "Recomendaciones de seguridad para senderismo",
                    "Mejores épocas para hacer rutas en Pirineos"
                ]
            },
            {
                title: "Para ti",
                icon: "bi bi-compass",
                description: "Sugerencias según tu nivel y tus salidas",
                prompts: [
                    "Recomiéndame una ruta para mi nivel",
                    "Rutas similares a la última que hice",
                    "Sugerencias para este fin de semana"
                ]
            }
        ];

        const esc = (v) => this.chatEscapeHtml(v);

        promptContainer.innerHTML = `
            <section class="k-hero kchat-welcome">
                <img class="kchat-welcome-mark" src="assets/img/kahir-mark.svg" alt="" aria-hidden="true" width="52" height="52">
                <p class="k-eyebrow">Pirineos y Euskadi</p>
                <h1>¿A dónde quieres ir?</h1>
                <p class="lead">Pregúntame por rutas, el parte o qué llevar en la mochila.</p>
            </section>
            <div class="prompt-groups">
                ${examples.map(category => `
                    <div class="prompt-group">
                        <div class="prompt-group-head">
                            <span class="prompt-group-icon"><i class="${esc(category.icon)}" aria-hidden="true"></i></span>
                            <div>
                                <h2 class="prompt-group-title">${esc(category.title)}</h2>
                                <p class="prompt-group-desc">${esc(category.description)}</p>
                            </div>
                        </div>
                        <div class="prompt-examples">
                            ${category.prompts.map(prompt => `
                                <button type="button" class="prompt-example" data-prompt="${esc(prompt)}">${esc(prompt)}</button>
                            `).join('')}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    },

    async handleUserInput() {
        const input = document.getElementById('user-input');
        const message = input.value.trim();

        if (!message) return;

        input.value = '';
        document.getElementById('char-count').textContent = '0';
        
        this.addMessage('user', message);
        this.showChatArea();
        this.showTypingIndicator();

        try {
            const response = await this.sendMessage(message);
            this.hideTypingIndicator();
            this.processResponse(response);
        } catch (error) {
            this.hideTypingIndicator();
            this.handleError(error);
        }
    },

    async sendMessage(message) {
        try {
            // csrf.js añade la cabecera X-CSRF-Token a este POST
            const response = await fetch('hiker_ai/assets/js/chat_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    message,
                    context: this.context,
                    timestamp: Date.now()
                })
            });

            // El servidor responde JSON también en errores (401, 403, 429...):
            // se intenta leer siempre para mostrar su mensaje.
            let data = null;
            try {
                data = await response.json();
            } catch (parseError) {
                data = null;
            }

            if (!response.ok) {
                const fallback = response.status === 401
                    ? 'Debes iniciar sesión para usar el asistente.'
                    : 'Ha ocurrido un error. Por favor, inténtalo de nuevo.';
                const msg = (data && typeof data.message === 'string' && data.message) ? data.message : fallback;
                const err = new Error(msg);
                err.status = response.status;
                throw err;
            }
            if (!data) {
                throw new Error('Ha ocurrido un error. Por favor, inténtalo de nuevo.');
            }

            return data;
        } catch (error) {
            console.error('Error sending message:', error);
            throw error;
        }
    },

    processResponse(response) {
        this.context = response.context || this.context;

        switch (response.type) {
            case 'route_suggestions':
                // Intro corta (1 frase) + aviso meteorológico (si hay) + carousel
                if (response.message) {
                    this.addMessage('system', response.message);
                }
                if (response.weather_warning) {
                    this.showWeatherWarning(response.weather_warning);
                }
                if (response.routes && response.routes.length > 0) {
                    this.showRouteCarousel(response.routes);
                }
                break;

            case 'weather_info':
                // Claude ya da una explicación completa; el widget es complementario
                this.addMessage('system', response.message);
                if (response.weather) {
                    this.showWeatherCard(response.weather);
                }
                break;

            case 'route_detail':
                // Detalle de una ruta específica (followup describe)
                // Texto narrativo + tarjeta única sin navegación de carousel
                if (response.message) {
                    this.addMessage('system', response.message);
                }
                if (response.route) {
                    this.showRouteCarousel([response.route]);
                }
                break;

            case 'clarification_request':
                this.addMessage('system', response.message);
                if (response.questions && response.questions.length > 0) {
                    this.showClarificationPrompts(response.questions);
                }
                break;

            case 'error':
                this.handleError(response);
                break;

            default:
                this.addMessage('system', response.message);
        }

        if (response.suggestions) {
            this.showSuggestions(response.suggestions);
        }
    },

    showWeatherWarning(warnings) {
        // warnings puede ser un array de strings o un string
        const lines = Array.isArray(warnings) ? warnings : [warnings];
        if (!lines.length) return;

        const div = document.createElement('div');
        div.className = 'weather-warning-box';
        div.innerHTML = lines.map(w =>
            `<div class="weather-warning-item"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>${this.chatEscapeHtml(w)}</span></div>`
        ).join('');

        this.addMessage('system', div);
    },

    addMessage(role, content) {
        const container = document.getElementById('chat-container');
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message', `${role}-message`);

        // Avatar (hito de piedras) solo en los mensajes del asistente
        if (role !== 'user') {
            const avatar = document.createElement('img');
            avatar.className = 'message-avatar';
            avatar.src = 'assets/img/kahir-mark.svg';
            avatar.alt = '';
            avatar.setAttribute('aria-hidden', 'true');
            messageDiv.appendChild(avatar);
        }

        const body = document.createElement('div');
        body.className = 'message-body';

        if (typeof content === 'string') {
            body.innerHTML = this.formatMessage(content);
        } else {
            // Tarjetas, carrusel, avisos: sin burbuja de texto alrededor
            messageDiv.classList.add('message-rich');
            body.appendChild(content);
        }
        messageDiv.appendChild(body);

        container.appendChild(messageDiv);
        this.scrollToBottom();
        this.lastMessageTime = Date.now();
    },

    showRouteCarousel(routes) {
        const wrapper = document.createElement('div');
        wrapper.className = 'route-carousel';

        // Track con slides
        const track = document.createElement('div');
        track.className = 'carousel-track';

        routes.forEach((route, i) => {
            const slide = document.createElement('div');
            slide.className = 'carousel-slide' + (i === 0 ? ' active' : '');
            slide.innerHTML = this.createRouteCardHTML(route);
            track.appendChild(slide);
        });
        wrapper.appendChild(track);

        // Contador
        const counter = document.createElement('div');
        counter.className = 'carousel-counter';
        counter.textContent = routes.length > 1 ? `1 / ${routes.length}` : '';
        wrapper.appendChild(counter);

        // Navegación (solo si hay más de 1 ruta)
        if (routes.length > 1) {
            const nav = document.createElement('div');
            nav.className = 'carousel-nav';

            const dots = routes.map((_, i) => {
                const d = document.createElement('span');
                d.className = 'carousel-dot' + (i === 0 ? ' active' : '');
                d.dataset.idx = i;
                return d;
            });

            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'carousel-btn prev';
            prevBtn.innerHTML = '<i class="bi bi-chevron-left" aria-hidden="true"></i>';
            prevBtn.setAttribute('aria-label', 'Anterior');

            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'carousel-btn next';
            nextBtn.innerHTML = '<i class="bi bi-chevron-right" aria-hidden="true"></i>';
            nextBtn.setAttribute('aria-label', 'Siguiente');

            const dotsWrap = document.createElement('div');
            dotsWrap.className = 'carousel-dots';
            dots.forEach(d => dotsWrap.appendChild(d));

            nav.appendChild(prevBtn);
            nav.appendChild(dotsWrap);
            nav.appendChild(nextBtn);
            wrapper.appendChild(nav);

            let current = 0;
            const slides = track.querySelectorAll('.carousel-slide');

            const goTo = (idx) => {
                slides[current].classList.remove('active');
                dots[current].classList.remove('active');
                current = ((idx % routes.length) + routes.length) % routes.length;
                slides[current].classList.add('active');
                dots[current].classList.add('active');
                counter.textContent = `${current + 1} / ${routes.length}`;
            };

            prevBtn.addEventListener('click', () => goTo(current - 1));
            nextBtn.addEventListener('click', () => goTo(current + 1));
            dots.forEach(d => d.addEventListener('click', () => goTo(+d.dataset.idx)));

            // Swipe en móvil
            let touchStartX = 0;
            track.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].clientX; }, { passive: true });
            track.addEventListener('touchend', e => {
                const dx = e.changedTouches[0].clientX - touchStartX;
                if (Math.abs(dx) > 50) goTo(dx < 0 ? current + 1 : current - 1);
            }, { passive: true });
        }

        this.addMessage('system', wrapper);
    },

    // Alias mantenido por compatibilidad con código antiguo
    showRouteCards(routes) {
        this.showRouteCarousel(routes);
    },

    showWeatherCard(weather) {
        // No mostrar el widget si no hay datos reales (evita "null°C")
        if (!weather || weather.temperature === null || weather.temperature === undefined) return;
        const card = document.createElement('div');
        card.classList.add('weather-card');
        card.innerHTML = this.createWeatherCardHTML(weather);
        this.addMessage('system', card);
    },

    showSuggestions(suggestions) {
        const container = document.getElementById('suggestions-panel');
        container.innerHTML = '';
        
        suggestions.forEach(suggestion => {
            const button = document.createElement('button');
            button.type = 'button';
            button.classList.add('suggestion-btn');
            button.textContent = suggestion;
            button.addEventListener('click', () => {
                document.getElementById('user-input').value = suggestion;
                this.handleUserInput();
            });
            container.appendChild(button);
        });

        container.classList.remove('d-none');
    },

    handleError(error) {
        const message = error.message || 'Ha ocurrido un error. Por favor, inténtalo de nuevo.';
        const errorDiv = document.createElement('div');
        errorDiv.classList.add('error-message');
        errorDiv.setAttribute('role', 'alert');
        const errorIcon = document.createElement('i');
        errorIcon.className = 'bi bi-exclamation-circle';
        errorIcon.setAttribute('aria-hidden', 'true');
        const errorText = document.createElement('span');
        errorText.textContent = message;
        errorDiv.append(errorIcon, errorText);
        this.addMessage('system', errorDiv);
    },

    /** Escapa texto para insertarlo en HTML (nombre único: chatcard.js y weather_card.js se cargan a la vez) */
    chatEscapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    },

    /** Devuelve la URL si es http(s) o relativa; si no (javascript:, data:...), '' */
    chatSafeUrl(url) {
        const u = String(url ?? '').trim();
        if (!u) return '';
        try {
            const parsed = new URL(u, window.location.href);
            return (parsed.protocol === 'http:' || parsed.protocol === 'https:') ? u : '';
        } catch (e) {
            return '';
        }
    },

    /** Limpia el HTML generado por marked: solo href/src seguros y sin atributos on* */
    chatSanitizeRendered(html) {
        const tpl = document.createElement('template');
        tpl.innerHTML = html;
        tpl.content.querySelectorAll('script, iframe, object, embed, style, form').forEach(el => el.remove());
        tpl.content.querySelectorAll('*').forEach(el => {
            Array.from(el.attributes).forEach(attr => {
                if (/^on/i.test(attr.name)) el.removeAttribute(attr.name);
            });
        });
        tpl.content.querySelectorAll('a').forEach(a => {
            const href = this.chatSafeUrl(a.getAttribute('href'));
            if (href) {
                a.setAttribute('href', href);
                a.setAttribute('target', '_blank');
                a.setAttribute('rel', 'noopener noreferrer');
            } else {
                a.removeAttribute('href');
            }
        });
        tpl.content.querySelectorAll('img').forEach(img => {
            const src = this.chatSafeUrl(img.getAttribute('src'));
            if (src) img.setAttribute('src', src); else img.remove();
        });
        return tpl.innerHTML;
    },

    formatMessage(content) {
        if (typeof content !== 'string') return '';

        // Escapar el HTML crudo ANTES de marked: el Markdown sigue funcionando,
        // pero cualquier etiqueta inyectada se muestra como texto.
        const escaped = content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;');

        // Usar marked.js si está disponible (renderiza Markdown completo)
        if (typeof marked !== 'undefined') {
            marked.setOptions({
                breaks: true,      // \n → <br> dentro de párrafos
                gfm: true,         // GitHub Flavored Markdown
                headerIds: false,  // no generar ids en headers (más limpio)
                mangle: false,
            });

            // Renderizar Markdown → HTML y limpiar enlaces/imágenes
            return this.chatSanitizeRendered(marked.parse(escaped));
        }

        // Fallback sin marked.js
        return this.chatSanitizeRendered(escaped
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>')
            .replace(/(https?:\/\/[^\s<"]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'));
    },

    showTypingIndicator() {
        this.isTyping = true;
        document.getElementById('typing-indicator').classList.remove('d-none');
    },

    hideTypingIndicator() {
        this.isTyping = false;
        document.getElementById('typing-indicator').classList.add('d-none');
    },

    scrollToBottom() {
        const container = document.getElementById('chat-container');
        container.scrollTop = container.scrollHeight;
        // La conversación usa el scroll de la página (compositor sticky):
        // llevar el último mensaje a la vista
        const last = container.lastElementChild;
        if (last && typeof last.scrollIntoView === 'function') {
            last.scrollIntoView({ block: 'end', behavior: 'smooth' });
        }
    },

    showChatArea() {
        document.getElementById('prompt-container').style.display = 'none';
        document.getElementById('chat-area').classList.remove('d-none');
    },

    createRouteCardHTML(route) {
        const diffLabels = { beginner: 'Principiante', intermediate: 'Intermedio', advanced: 'Avanzado' };
        const diff      = route.difficulty || 'intermediate';
        const diffLabel = diffLabels[diff] || diff;
        // Clase de color (definida en chat.css); valores desconocidos → neutro
        const diffClass = diffLabels[diff] ? `route-diff-${diff}` : 'route-diff-other';

        const actIcons = {
            hiking: 'fa-hiking', ski_touring: 'fa-skiing', climbing: 'fa-mountain',
            cycle_touring: 'fa-bicycle', snowshoeing: 'fa-snowflake',
        };
        const actIcon = actIcons[route.tipo_ruta] || 'fa-route';

        const zoneLabels = {
            'Aragon':           'Pirineo Aragonés',
            'Western_Pyrenees': 'Pirineo Occidental',
            'Central_Pyrenees': 'Pirineo Central',
            'Euskadi':          'Euskadi',
            'Cantabria':        'Cantabria / Picos',
            'Galicia':          'Galicia',
            'Sistema_Central':  'Sistema Central',
            'Sistema_Iberico':  'Sistema Ibérico',
            'Sierra_Nevada':    'Sierra Nevada',
            'Andalucia':        'Andalucía',
            'Levante':          'Levante',
            'Canarias':         'Canarias',
            'Baleares':         'Baleares',
            'Francia_Sur':      'Sur de Francia',
        };

        // Limpiar título: guiones bajos → espacios, colapsar espacios múltiples
        const cleanTitle = (route.title || 'Ruta')
            .replace(/_/g, ' ')
            .replace(/\s{2,}/g, ' ')
            .trim();

        const locationLabel = zoneLabels[route.location] || route.location || '';

        // Formatear duración: 6.5 h → "6h 30min"
        const fmtDuration = (h) => {
            if (h == null || isNaN(h)) return '—';
            const hours = Math.floor(+h);
            const mins  = Math.round((+h - hours) * 60);
            return mins > 0 ? `${hours}h ${mins}min` : `${hours}h`;
        };

        const distance  = route.distance  != null ? parseFloat(route.distance).toFixed(1)  : '—';
        const elevation = route.elevation != null ? `+${Math.round(route.elevation)} m`     : '—';
        const duration  = fmtDuration(route.duration);

        // Esfuerzo MIDE (1–5), solo si viene en los datos de la ruta
        const effortNum = Number(route.esfuerzo);
        const effort = (route.esfuerzo != null && route.esfuerzo !== '' && effortNum >= 1 && effortNum <= 5)
            ? Math.round(effortNum) : null;

        const esc = (v) => this.chatEscapeHtml(v);

        // Badge de tiempo en coche — sobre la imagen (esquina inferior izquierda)
        const driveBadge = route.drive_time_text
            ? `<span class="route-drive-badge">
                   <i class="bi bi-car-front" aria-hidden="true"></i> ${esc(route.drive_time_text)}
               </span>`
            : '';

        // Ruta relativa a la página del chat (index.php en la raíz del proyecto)
        const routeUrl = `paginasespecializadas/skitouring/ruta.html?id=${encodeURIComponent(route.id ?? '')}`;
        const placeholder = 'hiker_ai/assets/img/route-placeholder.jpg';
        const imageUrl = this.chatSafeUrl(route.image) || placeholder;

        return `
            <article class="route-card-inner">
                <div class="route-card-img-wrap">
                    <img src="${esc(imageUrl)}"
                         alt="${esc(cleanTitle)}"
                         loading="lazy"
                         onerror="this.onerror=null;this.src='${placeholder}'">
                    <span class="route-diff-badge ${diffClass}">${esc(diffLabel)}</span>
                    <span class="route-act-badge"><i class="fas ${actIcon}" aria-hidden="true"></i></span>
                    ${driveBadge}
                </div>
                <div class="route-card-body">
                    ${locationLabel ? `<p class="route-card-location">${esc(locationLabel)}</p>` : ''}
                    <h3 class="route-card-title">${esc(cleanTitle)}</h3>
                    <div class="route-card-stats">
                        <span class="k-chip"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> ${esc(distance)} km</span>
                        <span class="k-chip"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> ${esc(elevation)}</span>
                        <span class="k-chip"><i class="bi bi-clock" aria-hidden="true"></i> ${esc(duration)}</span>
                        ${effort ? `<span class="k-chip k-chip-moss" title="Esfuerzo MIDE"><i class="bi bi-activity" aria-hidden="true"></i> Esfuerzo ${esc(effort)}/5</span>` : ''}
                    </div>
                    <a href="${esc(routeUrl)}" class="route-card-btn" target="_blank" rel="noopener noreferrer">
                        Ver ruta <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </article>
        `;
    },

    createWeatherCardHTML(weather) {
        // Solo renderizar un campo si tiene valor real (evita "nullm", "undefined%", etc.)
        const val = (v) => v != null && v !== '' && !isNaN(v);
        const esc = (v) => this.chatEscapeHtml(v);

        const item = (icon, label, value) => `
            <div class="weather-detail">
                <i class="bi ${icon}" aria-hidden="true"></i>
                <span class="weather-detail-label">${label}</span>
                <span class="weather-detail-value">${value}</span>
            </div>`;

        const wind = val(weather.wind_speed)
            ? `${esc(weather.wind_speed)} km/h${val(weather.wind_gusts) ? ` <small>rachas ${esc(weather.wind_gusts)}</small>` : ''}`
            : '';

        const detailItems = [
            wind                        ? item('bi-wind', 'Viento', wind) : '',
            // "humidity" trae la nubosidad (%), no la humedad: ver chat_handler.php
            val(weather.humidity)       ? item('bi-clouds', 'Nubosidad', `${esc(weather.humidity)}%`) : '',
            val(weather.visibility)     ? item('bi-eye', 'Visibilidad', `${esc(weather.visibility)} m`) : '',
            // cota_nieve_est = transición real del manto (multi-punto Open-Meteo o AEMET)
            val(weather.cota_nieve_est) ? item('bi-snow', 'Cota de nieve', `~${esc(weather.cota_nieve_est)} m`) : '',
            // snow_level = isocero (isoterma 0°C), no cota de nieve del manto
            val(weather.snow_level)     ? item('bi-thermometer-snow', 'Isocero', `${esc(weather.snow_level)} m`) : '',
        ].filter(Boolean).join('');

        return `
            <div class="weather-info">
                <p class="weather-eyebrow"><i class="bi bi-cloud-sun" aria-hidden="true"></i> Parte meteorológico</p>
                <div class="current-weather">
                    <span class="temperature">${esc(weather.temperature)}<span class="temperature-unit">°C</span></span>
                    <span class="conditions">${esc(weather.conditions || '')}</span>
                </div>
                ${detailItems ? `<div class="weather-details">${detailItems}</div>` : ''}
                ${weather.warnings && weather.warnings.length ? `
                    <div class="weather-warnings">
                        ${weather.warnings.map(w => `<div class="warning"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>${esc(w)}</span></div>`).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    },

    // Método añadido para manejar prompts de clarificación
    showClarificationPrompts(questions) {
        const container = document.createElement('div');
        container.classList.add('clarification-prompts');
        
        questions.forEach(question => {
            const button = document.createElement('button');
            button.type = 'button';
            button.classList.add('suggestion-btn');
            button.textContent = question;
            button.addEventListener('click', () => {
                document.getElementById('user-input').value = question;
                this.handleUserInput();
            });
            container.appendChild(button);
        });

        this.addMessage('system', container);
    }
};

window.ChatApp = ChatApp;