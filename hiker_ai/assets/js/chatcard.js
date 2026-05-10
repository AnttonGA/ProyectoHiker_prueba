/**
 * Componente de tarjetas de respuesta para el chat de Kahir.AI
 * 
 * Maneja la creación y gestión de diferentes tipos de tarjetas
 * de respuesta: rutas, tiempo, recomendaciones, etc.
 */

class ChatCard {
    constructor(config = {}) {
        this.config = {
            animationDuration: 300,
            enableInteractions: true,
            maxHeight: '400px',
            ...config
        };
    }

    /**
     * Crea una tarjeta según el tipo de respuesta
     * @param {Object} data Datos de la respuesta
     * @param {string} type Tipo de tarjeta
     * @returns {HTMLElement}
     */
    createCard(data, type) {
        const card = document.createElement('div');
        card.className = `chat-card ${type}-card`;
        
        switch (type) {
            case 'route':
                card.innerHTML = this.createRouteCard(data);
                break;
            case 'recommendation':
                card.innerHTML = this.createRecommendationCard(data);
                break;
            case 'activity':
                card.innerHTML = this.createActivityCard(data);
                break;
            case 'safety':
                card.innerHTML = this.createSafetyCard(data);
                break;
            default:
                card.innerHTML = this.createDefaultCard(data);
        }

        if (this.config.enableInteractions) {
            this.addInteractions(card, type, data);
        }

        this.animateCard(card);
        return card;
    }

    /**
     * Crea una tarjeta de ruta
     * @param {Object} route Datos de la ruta
     * @returns {string} HTML de la tarjeta
     */
    createRouteCard(route) {
        return `
            <div class="route-card-content">
                <div class="route-image">
                    <img src="${route.image || 'default-route.jpg'}" 
                         alt="${route.title}"
                         onerror="this.src='default-route.jpg'">
                    <div class="difficulty-badge difficulty-${route.difficulty}">
                        ${this.formatDifficulty(route.difficulty)}
                    </div>
                </div>
                <div class="route-details">
                    <h3>${route.title}</h3>
                    <div class="route-stats">
                        <div class="stat">
                            <i class="fas fa-mountain"></i>
                            <span>${route.elevation}m</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-clock"></i>
                            <span>${this.formatDuration(route.duration)}</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-route"></i>
                            <span>${route.length}km</span>
                        </div>
                    </div>
                    <p class="route-description">${route.description}</p>
                    <div class="route-actions">
                        <button class="btn-details" data-route-id="${route.id}">
                            Ver Detalles
                        </button>
                        <button class="btn-save" data-route-id="${route.id}">
                            <i class="far fa-bookmark"></i>
                        </button>
                    </div>
                </div>
            </div>
            ${this.createWeatherPreview(route.weather)}
        `;
    }

    /**
     * Crea una tarjeta de recomendación
     * @param {Object} recommendation Datos de la recomendación
     * @returns {string} HTML de la tarjeta
     */
    createRecommendationCard(recommendation) {
        return `
            <div class="recommendation-card-content">
                <div class="recommendation-header">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Recomendación</h3>
                </div>
                <div class="recommendation-body">
                    <p class="recommendation-text">${recommendation.message}</p>
                    ${this.createRecommendationDetails(recommendation)}
                </div>
                <div class="recommendation-footer">
                    ${this.createRecommendationActions(recommendation)}
                </div>
            </div>
        `;
    }

    /**
     * Crea una tarjeta de actividad
     * @param {Object} activity Datos de la actividad
     * @returns {string} HTML de la tarjeta
     */
    createActivityCard(activity) {
        return `
            <div class="activity-card-content">
                <div class="activity-icon">
                    ${this.getActivityIcon(activity.type)}
                </div>
                <div class="activity-details">
                    <h3>${activity.name}</h3>
                    <div class="activity-info">
                        <div class="season-info">
                            <span>Mejor temporada:</span>
                            ${this.createSeasonIndicators(activity.bestSeasons)}
                        </div>
                        <div class="difficulty-info">
                            <span>Dificultad:</span>
                            ${this.createDifficultyIndicator(activity.difficulty)}
                        </div>
                    </div>
                    <p>${activity.description}</p>
                </div>
                <div class="activity-requirements">
                    <h4>Equipo necesario</h4>
                    <ul class="equipment-list">
                        ${activity.equipment.map(item => `
                            <li>
                                <i class="fas fa-check"></i>
                                ${item}
                            </li>
                        `).join('')}
                    </ul>
                </div>
            </div>
        `;
    }

    /**
     * Crea una tarjeta de seguridad
     * @param {Object} safety Datos de seguridad
     * @returns {string} HTML de la tarjeta
     */
    createSafetyCard(safety) {
        return `
            <div class="safety-card-content ${safety.level}-level">
                <div class="safety-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Información de Seguridad</h3>
                </div>
                <div class="safety-warnings">
                    ${safety.warnings.map(warning => `
                        <div class="warning-item">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>${warning}</span>
                        </div>
                    `).join('')}
                </div>
                <div class="safety-recommendations">
                    <h4>Recomendaciones</h4>
                    <ul>
                        ${safety.recommendations.map(rec => `
                            <li>${rec}</li>
                        `).join('')}
                    </ul>
                </div>
                ${safety.emergency ? this.createEmergencyInfo(safety.emergency) : ''}
            </div>
        `;
    }

    // Métodos de soporte

    /**
     * Añade interacciones a la tarjeta
     * @param {HTMLElement} card Elemento de la tarjeta
     * @param {string} type Tipo de tarjeta
     * @param {Object} data Datos asociados
     */
    addInteractions(card, type, data) {
        // Botones de acción
        card.querySelectorAll('[data-action]').forEach(button => {
            button.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                this.handleCardAction(action, data, e.target);
            });
        });

        // Expansión/colapso
        const expandableContent = card.querySelector('.expandable-content');
        if (expandableContent) {
            card.querySelector('.expand-trigger')?.addEventListener('click', () => {
                this.toggleExpand(expandableContent);
            });
        }

        // Interacciones específicas por tipo
        switch (type) {
            case 'route':
                this.addRouteInteractions(card, data);
                break;
            case 'safety':
                this.addSafetyInteractions(card, data);
                break;
        }
    }

    /**
     * Anima la entrada de la tarjeta
     * @param {HTMLElement} card Elemento de la tarjeta
     */
    animateCard(card) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = `all ${this.config.animationDuration}ms ease-out`;

        requestAnimationFrame(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        });
    }

    // Métodos de utilidad

    formatDifficulty(difficulty) {
        const labels = {
            'beginner': 'Principiante',
            'intermediate': 'Intermedio',
            'advanced': 'Avanzado'
        };
        return labels[difficulty] || difficulty;
    }

    formatDuration(hours) {
        const h = Math.floor(hours);
        const m = Math.round((hours - h) * 60);
        return m > 0 ? `${h}h ${m}min` : `${h}h`;
    }

    getActivityIcon(type) {
        const icons = {
            'ski_touring': 'fa-skiing',
            'hiking': 'fa-hiking',
            'climbing': 'fa-mountain',
            'cycling': 'fa-bicycle'
        };
        return `<i class="fas ${icons[type] || 'fa-question'}"></i>`;
    }
}

// Exportar para uso global
window.ChatCard = ChatCard;