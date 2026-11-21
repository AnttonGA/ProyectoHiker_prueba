/**
 * Componente de tarjeta meteorológica para Kahir.AI
 * 
 * Gestiona la visualización y actualización de información
 * meteorológica detallada en formato de tarjeta interactiva.
 */

class WeatherCard {
    constructor(config = {}) {
        this.config = {
            animate: true,
            updateInterval: 300000, // 5 minutos
            units: 'metric',
            language: 'es',
            ...config
        };

        this.lastUpdate = null;
        this.currentData = null;
    }

    /**
     * Renderiza una nueva tarjeta meteorológica
     * @param {Object} weatherData Datos meteorológicos
     * @param {HTMLElement} container Contenedor para la tarjeta
     */
    render(weatherData, container) {
        this.currentData = weatherData;
        this.lastUpdate = new Date();

        const card = document.createElement('div');
        card.className = 'weather-card';
        card.innerHTML = this.generateCardHTML(weatherData);

        // Añadir interactividad
        this.attachEventListeners(card);

        // Mostrar con animación si está configurado
        if (this.config.animate) {
            card.style.opacity = '0';
            container.appendChild(card);
            requestAnimationFrame(() => {
                card.style.opacity = '1';
                card.style.transition = 'opacity 0.3s ease-in-out';
            });
        } else {
            container.appendChild(card);
        }

        // Iniciar actualizaciones automáticas
        if (this.config.updateInterval > 0) {
            this.startAutoUpdate(container);
        }

        return card;
    }

    /**
     * Genera el HTML para la tarjeta meteorológica
     * @param {Object} data Datos meteorológicos
     * @returns {string} HTML generado
     */
    generateCardHTML(data) {
        const { current, forecast, mountain_conditions } = data;
        
        return `
            <div class="weather-card-header">
                <div class="current-conditions">
                    <div class="temperature">
                        <span class="temp-value">${Math.round(current.temp)}°C</span>
                        <span class="feels-like">Sensación térmica: ${Math.round(current.feels_like)}°C</span>
                    </div>
                    <div class="condition">
                        <img src="${this.getWeatherIcon(current.weather[0].icon)}" 
                             alt="${this.wcEsc(current.weather[0].description)}"
                             class="condition-icon">
                        <span class="condition-text">${this.wcEsc(current.weather[0].description)}</span>
                    </div>
                </div>
                <div class="update-time">
                    Actualizado: ${this.formatTime(this.lastUpdate)}
                </div>
            </div>

            <div class="weather-card-body">
                <div class="main-metrics">
                    <div class="metric">
                        <i class="fas fa-wind"></i>
                        <span>Viento</span>
                        <strong>${Math.round(current.wind_speed * 3.6)} km/h</strong>
                        ${this.getWindDirection(current.wind_deg)}
                    </div>
                    <div class="metric">
                        <i class="fas fa-tint"></i>
                        <span>Humedad</span>
                        <strong>${this.wcEsc(current.humidity)}%</strong>
                    </div>
                    <div class="metric">
                        <i class="fas fa-eye"></i>
                        <span>Visibilidad</span>
                        <strong>${this.wcEsc(this.formatVisibility(current.visibility))}</strong>
                    </div>
                </div>

                ${mountain_conditions ? this.generateMountainConditionsHTML(mountain_conditions) : ''}
                
                <div class="forecast-section">
                    <h4>Próximas horas</h4>
                    <div class="hourly-forecast">
                        ${this.generateHourlyForecastHTML(forecast.hourly.slice(0, 8))}
                    </div>
                </div>

                ${this.generateWarningsHTML(data.warnings)}
            </div>

            <div class="weather-card-footer">
                <button class="btn-details" onclick="this.toggleDetails()">
                    Más detalles <i class="fas fa-chevron-down"></i>
                </button>
            </div>
        `;
    }

    /**
     * Genera HTML para condiciones de montaña
     * @param {Object} conditions Condiciones de montaña
     * @returns {string} HTML generado
     */
    generateMountainConditionsHTML(conditions) {
        return `
            <div class="mountain-conditions">
                <h4>Condiciones de Montaña</h4>
                <div class="conditions-grid">
                    <div class="condition-item">
                        <span class="label">Cota de Nieve</span>
                        <strong>${this.wcEsc(conditions.snow_level || 'N/A')} m</strong>
                    </div>
                    <div class="condition-item">
                        <span class="label">ISO 0</span>
                        <strong>${this.wcEsc(conditions.iso_zero || 'N/A')} m</strong>
                    </div>
                    <div class="condition-item">
                        <span class="label">Riesgo de Aludes</span>
                        <strong class="risk-level-${this.wcEsc(conditions.avalanche_risk || 0)}">
                            ${this.formatAvalancheRisk(conditions.avalanche_risk)}
                        </strong>
                    </div>
                    <div class="condition-item">
                        <span class="label">Viento en Altura</span>
                        <strong>${this.wcEsc(conditions.altitude_wind || 'N/A')} km/h</strong>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Genera HTML para el pronóstico por horas
     * @param {Array} hourlyData Datos horarios
     * @returns {string} HTML generado
     */
    generateHourlyForecastHTML(hourlyData) {
        return hourlyData.map(hour => `
            <div class="hourly-item">
                <span class="time">${this.formatHour(hour.dt)}</span>
                <img src="${this.getWeatherIcon(hour.weather[0].icon)}" 
                     alt="${this.wcEsc(hour.weather[0].description)}"
                     class="small-icon">
                <span class="temp">${Math.round(hour.temp)}°</span>
                <span class="wind">${Math.round(hour.wind_speed * 3.6)} km/h</span>
            </div>
        `).join('');
    }

    /**
     * Genera HTML para advertencias meteorológicas
     * @param {Array} warnings Lista de advertencias
     * @returns {string} HTML generado
     */
    generateWarningsHTML(warnings) {
        if (!warnings || warnings.length === 0) return '';

        return `
            <div class="weather-warnings">
                <h4>Avisos Meteorológicos</h4>
                <div class="warnings-list">
                    ${warnings.map(warning => `
                        <div class="warning-item warning-level-${this.wcEsc(warning.level)}">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>${this.wcEsc(warning.description)}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    // Métodos de utilidad

    /**
     * Inicia la actualización automática
     * @param {HTMLElement} container Contenedor de la tarjeta
     */
    startAutoUpdate(container) {
        setInterval(() => {
            this.updateData()
                .then(newData => {
                    if (this.shouldUpdate(newData)) {
                        container.innerHTML = '';
                        this.render(newData, container);
                    }
                })
                .catch(console.error);
        }, this.config.updateInterval);
    }

    /**
     * Verifica si se debe actualizar la tarjeta
     * @param {Object} newData Nuevos datos meteorológicos
     * @returns {boolean}
     */
    shouldUpdate(newData) {
        if (!this.currentData) return true;
        
        // Comparar valores relevantes
        const currentTemp = this.currentData.current.temp;
        const newTemp = newData.current.temp;
        const tempDiff = Math.abs(currentTemp - newTemp);

        return tempDiff >= 0.5 || // Cambio significativo en temperatura
               this.lastUpdate < (Date.now() - 3600000); // Última actualización hace más de 1 hora
    }

    // Métodos de formateo y utilidad

    /** Escapa texto antes de insertarlo en HTML (nombre único: se carga junto a chat.js y chatcard.js) */
    wcEsc(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    formatTime(date) {
        return date.toLocaleTimeString(this.config.language, { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }

    formatHour(timestamp) {
        return new Date(timestamp * 1000).toLocaleTimeString(this.config.language, { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }

    formatVisibility(meters) {
        return meters >= 1000 ? 
            `${(meters / 1000).toFixed(1)} km` : 
            `${meters} m`;
    }

    formatAvalancheRisk(risk) {
        const levels = ['Bajo', 'Moderado', 'Notable', 'Alto', 'Muy Alto'];
        return levels[risk - 1] || 'Desconocido';
    }

    getWeatherIcon(iconCode) {
        // El código de icono solo puede ser alfanumérico (p. ej. '10d')
        const code = /^[a-z0-9]+$/i.test(String(iconCode ?? '')) ? iconCode : '01d';
        return `https://openweathermap.org/img/wn/${code}@2x.png`;
    }

    getWindDirection(degrees) {
        const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        const index = Math.round(degrees / 45) % 8;
        return `<span class="wind-direction">${directions[index]}</span>`;
    }
}

// Exportar para uso global
window.WeatherCard = WeatherCard;