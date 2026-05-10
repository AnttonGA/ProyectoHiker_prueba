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
                title: "Buscar Rutas",
                icon: "fas fa-route",
                description: "Encuentra la ruta perfecta para tu próxima aventura",
                prompts: [
                    "Busco una ruta fácil cerca de Candanchú",
                    "Ruta de senderismo para principiantes",
                    "Rutas de ski touring en Formigal"
                ]
            },
            {
                title: "Consultar Tiempo",
                icon: "fas fa-cloud",
                description: "Obtén información meteorológica detallada",
                prompts: [
                    "¿Qué tiempo hace hoy en Panticosa?",
                    "Previsión meteorológica para mañana en Astún",
                    "Condiciones de nieve en el Valle de Tena"
                ]
            },
            {
                title: "Información General",
                icon: "fas fa-info-circle",
                description: "Aprende sobre actividades y seguridad en montaña",
                prompts: [
                    "¿Qué equipo necesito para ski de montaña?",
                    "Recomendaciones de seguridad para senderismo",
                    "Mejores épocas para hacer rutas en Pirineos"
                ]
            },
            {
                title: "Recomendaciones Personalizadas",
                icon: "fas fa-compass",
                description: "Obtén sugerencias basadas en tus preferencias",
                prompts: [
                    "Recomiéndame una ruta para mi nivel",
                    "Rutas similares a la última que hice",
                    "Sugerencias para este fin de semana"
                ]
            }
        ];

        promptContainer.innerHTML = `
            <div class="row prompt-container">
                ${examples.map(category => `
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="prompt-box card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="text-center mb-3">
                                    <i class="${category.icon} fa-2x"></i>
                                </div>
                                <h5 class="card-title text-center">${category.title}</h5>
                                <p class="card-text text-muted small text-center mb-3">
                                    ${category.description}
                                </p>
                                <div class="prompt-examples mt-auto">
                                    ${category.prompts.map(prompt => `
                                        <button class="btn btn-link prompt-example w-100 text-start mb-2"
                                                data-prompt="${prompt}">
                                            ${prompt}
                                        </button>
                                    `).join('')}
                                </div>
                            </div>
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
            const response = await fetch('hiker_ai/assets/js/chat_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message,
                    context: this.context,
                    timestamp: Date.now()
                })
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
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
            `<div class="weather-warning-item">⚠️ ${w}</div>`
        ).join('');

        this.addMessage('system', div);
    },

    addMessage(role, content) {
        const container = document.getElementById('chat-container');
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message', `${role}-message`);
        
        if (typeof content === 'string') {
            messageDiv.innerHTML = this.formatMessage(content);
        } else {
            messageDiv.appendChild(content);
        }

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
            prevBtn.className = 'carousel-btn prev';
            prevBtn.innerHTML = '&#8249;';
            prevBtn.setAttribute('aria-label', 'Anterior');

            const nextBtn = document.createElement('button');
            nextBtn.className = 'carousel-btn next';
            nextBtn.innerHTML = '&#8250;';
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
        errorDiv.textContent = message;
        this.addMessage('system', errorDiv);
    },

    formatMessage(content) {
        if (typeof content !== 'string') return '';

        // Usar marked.js si está disponible (renderiza Markdown completo)
        if (typeof marked !== 'undefined') {
            // Configurar marked: sanitizar HTML, abrir links en nueva pestaña
            marked.setOptions({
                breaks: true,      // \n → <br> dentro de párrafos
                gfm: true,         // GitHub Flavored Markdown
                headerIds: false,  // no generar ids en headers (más limpio)
                mangle: false,
            });

            // Renderizar Markdown → HTML
            let html = marked.parse(content);

            // Añadir target="_blank" a todos los enlaces generados
            html = html.replace(/<a href="/g, '<a target="_blank" rel="noopener noreferrer" href="');

            return html;
        }

        // Fallback sin marked.js
        return content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>')
            .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
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
    },

    showChatArea() {
        document.getElementById('prompt-container').style.display = 'none';
        document.getElementById('chat-area').classList.remove('d-none');
    },

    createRouteCardHTML(route) {
        const diffLabels = { beginner: 'Principiante', intermediate: 'Intermedio', advanced: 'Avanzado' };
        const diffColors = { beginner: '#22c55e',      intermediate: '#f59e0b',    advanced: '#ef4444'  };
        const diff      = route.difficulty || 'intermediate';
        const diffLabel = diffLabels[diff] || diff;
        const diffColor = diffColors[diff] || '#6b7280';

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

        // Badge de tiempo en coche — sobre la imagen (esquina inferior izquierda)
        const driveBadge = route.drive_time_text
            ? `<span class="route-drive-badge">
                   <i class="fas fa-car"></i> ${route.drive_time_text}
               </span>`
            : '';

        const routeUrl = `http://localhost/misproyectos/ProyectoHiker_seguridad/paginasespecializadas/skitouring/ruta.html?id=${route.id}`;

        return `
            <div class="route-card-inner">
                <div class="route-card-img-wrap">
                    <img src="${route.image}"
                         alt="${cleanTitle}"
                         onerror="this.src='hiker_ai/assets/img/route-placeholder.jpg'">
                    <span class="route-diff-badge" style="background:${diffColor}">${diffLabel}</span>
                    <span class="route-act-badge"><i class="fas ${actIcon}"></i></span>
                    ${driveBadge}
                </div>
                <div class="route-card-body">
                    ${locationLabel ? `<p class="route-card-location">${locationLabel}</p>` : ''}
                    <h5 class="route-card-title">${cleanTitle}</h5>
                    <div class="route-card-stats">
                        <span><i class="fas fa-ruler-horizontal"></i> ${distance} km</span>
                        <span><i class="fas fa-clock"></i> ${duration}</span>
                        <span><i class="fas fa-arrow-up"></i> ${elevation}</span>
                    </div>
                    <a href="${routeUrl}" class="btn btn-primary btn-sm route-card-btn" target="_blank">
                        <i class="fas fa-map-marked-alt"></i> Ver ficha
                    </a>
                </div>
            </div>
        `;
    },

    createWeatherCardHTML(weather) {
        // Solo renderizar un campo si tiene valor real (evita "nullm", "undefined%", etc.)
        const val = (v) => v != null && v !== '' && !isNaN(v);

        const detailItems = [
            val(weather.wind_speed)    ? `<div><i class="fas fa-wind"></i> ${weather.wind_speed} km/h</div>` : '',
            val(weather.humidity)      ? `<div><i class="fas fa-cloud"></i> ${weather.humidity}% nubosidad</div>` : '',
            val(weather.visibility)    ? `<div><i class="fas fa-eye"></i> ${weather.visibility} m visibilidad</div>` : '',
            // cota_nieve_est = transición real del manto (multi-punto Open-Meteo o AEMET)
            val(weather.cota_nieve_est) ? `<div><i class="fas fa-snowflake"></i> cota nieve ~${weather.cota_nieve_est} m</div>` : '',
            // snow_level = isocero (isoterma 0°C), no cota de nieve del manto
            val(weather.snow_level)    ? `<div><i class="fas fa-thermometer-half"></i> isocero ${weather.snow_level} m</div>` : '',
        ].filter(Boolean).join('');

        return `
            <div class="weather-info">
                <div class="current-weather">
                    <span class="temperature">${weather.temperature}°C</span>
                    <span class="conditions">${weather.conditions || ''}</span>
                </div>
                ${detailItems ? `<div class="weather-details">${detailItems}</div>` : ''}
                ${weather.warnings && weather.warnings.length ? `
                    <div class="weather-warnings">
                        ${weather.warnings.map(w => `<div class="warning">${w}</div>`).join('')}
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
            button.classList.add('btn', 'btn-outline-primary', 'me-2', 'mb-2');
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