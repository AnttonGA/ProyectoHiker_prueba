document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const rutaId = urlParams.get('id');
    let viewStartTime;
    let viewEndSent = false;

    fetch(`javascript/obtener_rutas.php?id=${encodeURIComponent(rutaId)}`)
        .then(response => { if (!response.ok) throw new Error(`HTTP ${response.status}`); return response.json(); })
        .then(ruta => {
            if (!ruta || ruta.error) throw new Error(ruta?.error || 'Route not found');
            actualizarInformacionRuta(ruta);
            inicializarMapa(ruta);
            mostrarImagenes(ruta);
            agregarBotonDescarga(ruta);
            iniciarSeguimientoVista(ruta.id);
        })
        .catch(err => {
            console.error('Error loading route:', err);
            const title = document.getElementById('ruta-title');
            if (title) title.textContent = 'No encontramos esta ruta';
        });

    const ACTIVITY_LABEL = {
        hiking: 'Senderismo', ski_touring: 'Esquí de travesía', btt: 'BTT',
        cycle_touring: 'Cicloturismo', snowshoeing: 'Raquetas',
        climbing: 'Escalada', via_ferrata: 'Vía ferrata', kayaking: 'Kayak',
    };
    const DIFFICULTY_LABEL = {
        beginner: 'Fácil', intermediate: 'Moderada', advanced: 'Difícil',
    };
    // Punto de color por dificultad (ver css/ruta.css -> .k-diff--*)
    const DIFFICULTY_CLASS = {
        beginner: 'k-diff--easy', intermediate: 'k-diff--moderate', advanced: 'k-diff--hard',
    };
    // Etiquetas de pregunta1..4 (mismo orden que en Usuario/subir_ruta.html)
    const QA_FIELDS = [
        { icon: 'bi-car-front',            label: 'Cómo llegar' },
        { icon: 'bi-backpack',             label: 'Material' },
        { icon: 'bi-exclamation-triangle', label: 'Peligros y notas técnicas' },
        { icon: 'bi-sun',                  label: 'Mejor época' },
    ];
    const LOCATION_LABEL = {
        Western_Pyrenees:  'Pirineo occidental',
        Central_Pyrenees:  'Pirineo central',
        Eastern_Pyrenees:  'Pirineo oriental',
        Pirineo_Occidental:'Pirineo occidental',
        Pirineo_Central:   'Pirineo central',
        Pirineo_Oriental:  'Pirineo oriental',
        navarra:   'Navarra',     euskadi:    'Euskadi',
        la_rioja:  'La Rioja',    aragon:     'Aragón',
        cataluna:  'Cataluña',    galicia:    'Galicia',
        cantabria: 'Cantabria',   asturias:   'Asturias',
        castilla:  'Castilla',    madrid:     'Madrid',
        andalucia: 'Andalucía',   valencia:   'Valencia',
        murcia:    'Murcia',      baleares:   'Baleares',
        canarias:  'Canarias',  Other:      null,
    };

    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text ?? '—';
    }

    function actualizarInformacionRuta(ruta) {
        // Title
        const titulo = ruta.titulo || 'Ruta';
        setText('ruta-title', titulo);
        setText('breadcrumb-title', titulo);
        document.title = `${titulo} — Kahir`;

        // Stats
        const elev = ruta.elevacion_ganada ? `+${Math.round(ruta.elevacion_ganada).toLocaleString('es-ES')} m` : '—';
        const dist = ruta.distancia_km     ? `${parseFloat(ruta.distancia_km).toLocaleString('es-ES', { maximumFractionDigits: 1 })} km`                  : '—';
        const dur  = ruta.duracion_horas   ? `${parseFloat(ruta.duracion_horas).toLocaleString('es-ES', { maximumFractionDigits: 1 })} h`                  : '—';

        ['ruta-elevation', 'ruta-elevation-2'].forEach(id => setText(id, elev));
        ['ruta-distance',  'ruta-distance-2' ].forEach(id => setText(id, dist));
        ['ruta-duration',  'ruta-duration-2' ].forEach(id => setText(id, dur));

        // Esfuerzo tipo MIDE (columna calculada en la BD: 1–5 + horas estimadas)
        const esf = Number(ruta.esfuerzo);
        const hm  = parseFloat(ruta.horas_mide);
        const validEsf = esf >= 1 && esf <= 5;
        const hmText   = Number.isFinite(hm) ? `~${hm < 10 ? hm.toLocaleString('es-ES', { maximumFractionDigits: 1 }) : Math.round(hm).toLocaleString('es-ES')} h` : '';
        const effort   = validEsf ? `${esf}/5` + (hmText ? ` · ${hmText}` : '') : '—';
        setText('ruta-effort-2', effort);

        // Tile de datos: "2/5" grande + "~2.4 h" pequeño
        const effortEl = document.getElementById('ruta-effort');
        if (effortEl) {
            effortEl.textContent = '';
            if (validEsf) {
                const big = document.createElement('span');
                big.className = 'k-stat__main';
                big.textContent = `${esf}/5`;
                effortEl.appendChild(big);
                if (hmText) {
                    const sub = document.createElement('span');
                    sub.className = 'k-stat__sub';
                    sub.textContent = hmText;
                    effortEl.appendChild(sub);
                }
            } else {
                effortEl.textContent = '—';
            }
        }

        // Difficulty
        const diffKey   = (ruta.dificultad || '').toLowerCase();
        const diffLabel = DIFFICULTY_LABEL[diffKey] || ruta.dificultad || '—';
        const diffCls   = DIFFICULTY_CLASS[diffKey] || '';

        // Tile de datos: texto + punto de color (sin texto coloreado)
        const diffStatEl = document.getElementById('ruta-difficulty');
        if (diffStatEl) {
            diffStatEl.textContent = diffLabel;
            diffStatEl.className = `k-stat__value k-diff ${diffCls}`.trim();
        }

        // Ficha lateral
        const diffBadge = document.getElementById('ruta-difficulty-2');
        if (diffBadge) {
            diffBadge.textContent = diffLabel;
            diffBadge.className   = `k-diff ${diffCls}`.trim();
        }

        // Chip de la cabecera
        const heroDiff = document.getElementById('hero-difficulty-badge');
        if (heroDiff) {
            heroDiff.textContent = diffLabel;
            heroDiff.className   = `k-chip k-diff ${diffCls}`.trim();
            heroDiff.hidden      = diffLabel === '—';
        }

        // Activity
        const actLabel = ACTIVITY_LABEL[ruta.tipo_ruta] || ruta.tipo_ruta || '—';
        ['ruta-activity', 'ruta-activity-2'].forEach(id => setText(id, actLabel));

        const heroAct = document.getElementById('hero-activity-badge');
        if (heroAct) {
            heroAct.textContent = actLabel;
        }

        // Location — formatted
        const locRaw   = ruta.ubicacion || '';
        const locLabel = LOCATION_LABEL.hasOwnProperty(locRaw)
            ? (LOCATION_LABEL[locRaw] ?? locRaw)
            : locRaw.replace(/_/g, ' ') || '—';
        ['ruta-location', 'ruta-location-2', 'hero-location'].forEach(id => setText(id, locLabel));

        // Author
        const authorEl = document.getElementById('ruta-author');
        if (authorEl) {
            const span = authorEl.querySelector('span');
            if (span) span.textContent = ruta.username || 'alguien de Kahir';
        }

        // Description
        const descEl = document.getElementById('ruta-description');
        if (descEl) descEl.textContent = ruta.descripcion || '';

        // Extra Q&A
        const preguntasDiv = document.getElementById('ruta-preguntas');
        const preguntasWrap = document.getElementById('ruta-preguntas-wrap');
        if (preguntasDiv) {
            preguntasDiv.innerHTML = '';
            let hasExtra = false;
            for (let i = 1; i <= 4; i++) {
                if (ruta[`pregunta${i}`]) {
                    hasExtra = true;
                    const field = QA_FIELDS[i - 1];
                    const item = document.createElement('div');
                    item.className = 'k-qa__item';
                    const dt = document.createElement('dt');
                    const icon = document.createElement('i');
                    icon.className = `bi ${field.icon}`;
                    icon.setAttribute('aria-hidden', 'true');
                    dt.appendChild(icon);
                    dt.appendChild(document.createTextNode(field.label));
                    const dd = document.createElement('dd');
                    dd.textContent = String(ruta[`pregunta${i}`]);
                    item.appendChild(dt);
                    item.appendChild(dd);
                    preguntasDiv.appendChild(item);
                }
            }
            if (preguntasWrap) preguntasWrap.style.display = hasExtra ? '' : 'none';
        }
    }

    function inicializarMapa(ruta) {
        var map = L.map('map', {
            fullscreenControl: true,
            fullscreenControlOptions: {
                position: 'topleft'
            }
        }).setView([ruta.start_lat, ruta.start_lng], 10);

        L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.opentopomap.org/copyright">OpenTopoMap</a> contributors'
        }).addTo(map);

        var elevationDiv;
        var controlElevation;

        controlElevation = L.control.elevation({
            position: "bottomright",
            theme: "steelblue-theme",
            width: 600,
            height: 125,
            margins: {
                top: 10,
                right: 20,
                bottom: 30,
                left: 50
            },
            useHeightIndicator: true,
            interpolation: "linear",
            collapsed: false,
            detached: true,
            summary: true,
            ruler: true,
            legend: true,
            autohide: false,
            elevationDiv: "#elevation-profile",
            downloadLink: false,
            imperial: false,
            reverseCoords: false,
            reverseHeight: false
        }).addTo(map);

        elevationDiv = controlElevation.getContainer();

        function resizeElevationChart() {
            if (controlElevation) {
                if (typeof controlElevation.resize === 'function') {
                    controlElevation.resize({
                        width: controlElevation.options.width,
                        height: controlElevation.options.height
                    });
                } else if (typeof controlElevation.redraw === 'function') {
                    controlElevation.redraw();
                }
            }
        }

        map.on('fullscreenchange', function () {
            if (map.isFullscreen()) {
                map.getContainer().appendChild(elevationDiv);
                elevationDiv.style.position = 'absolute';
                elevationDiv.style.bottom = '10px';
                elevationDiv.style.left = '50%';
                elevationDiv.style.transform = 'translateX(-50%)';
                elevationDiv.style.zIndex = '1000';
                elevationDiv.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
                elevationDiv.style.padding = '10px';
                elevationDiv.style.borderRadius = '10px';
                controlElevation.expand();
                
                var mapWidth = map.getContainer().offsetWidth;
                var mapHeight = map.getContainer().offsetHeight;
                controlElevation.options.width = Math.min(600, mapWidth * 0.6);
                controlElevation.options.height = Math.min(400, mapHeight * 0.5);
            } else {
                document.getElementById('elevation-profile').appendChild(elevationDiv);
                elevationDiv.style.position = 'relative';
                elevationDiv.style.bottom = 'auto';
                elevationDiv.style.left = 'auto';
                elevationDiv.style.transform = 'none';
                elevationDiv.style.backgroundColor = 'transparent';
                elevationDiv.style.padding = '0';
                elevationDiv.style.borderRadius = '0';
                controlElevation.collapse();
                
                controlElevation.options.width = 600;
                controlElevation.options.height = 125;
                
                setTimeout(function() {
                    map.invalidateSize();
                    resizeElevationChart();
                }, 100);
            }
            setTimeout(resizeElevationChart, 100);
        });

        window.addEventListener('resize', resizeElevationChart);

        var gpxLayer = new L.GPX(`rutas_st/${ruta.ruta_carpeta}/${ruta.gpx_path}`, {
            async: true,
            polyline_options: {
                color: getColorByDifficulty(ruta.dificultad),
                weight: 4,
                opacity: 0.75,
                lineCap: 'round'
            },
            marker_options: {
                startIcon: createBootstrapIcon('bi-geo-alt-fill'),
                endIcon: createBootstrapIcon('bi-flag-fill')
            }
        }).on('loaded', function(e) {
            // El mapa vive en una columna lateral: recalcular su tamaño antes de encuadrar la traza
            map.invalidateSize();
            map.fitBounds(e.target.getBounds(), { padding: [24, 24], maxZoom: 15 });
            controlElevation.load(`rutas_st/${ruta.ruta_carpeta}/${ruta.gpx_path}`);
        }).addTo(map);
        // Ya no se envía 'map_interaction': el ENUM de user_interactions no lo admite
        // y hacía fallar (rollback) todo el lote.
    }

    function getDefaultImage(tipoRuta) {
        const defaults = {
            'ski_touring':   'rutas_st/default_images/skitouring.jpg',
            'hiking':        'rutas_st/default_images/hiking.jpg',
            'cycle_touring': 'rutas_st/default_images/cyclotouring.jpg',
            'climbing':      'rutas_st/default_images/hiking.jpg',
            'snowshoeing':   'rutas_st/default_images/skitouring.jpg',
            'via_ferrata':   'rutas_st/default_images/hiking.jpg',
            'btt':           'rutas_st/default_images/btt.jpg',
            'kayaking':      'rutas_st/default_images/kayaking.jpg'
        };
        return defaults[tipoRuta] || 'rutas_st/default_images/hiking.jpg';
    }

    function fixImgPath(src) {
        return (src || '').replace(/paginasespecializadas[\\\/]+skitouring[\\\/]+rutas_st[\\\/]+/g,
                                   'paginasespecializadas/skitouring/rutas_st/');
    }

    function mostrarImagenes(ruta) {
        const galleryDiv = document.getElementById('ruta-gallery');
        if (!galleryDiv) return;
        galleryDiv.innerHTML = '';

        const imagenes = (ruta.imagenes && ruta.imagenes.length > 0)
            ? ruta.imagenes
            : [getDefaultImage(ruta.tipo_ruta)];

        const fallback = getDefaultImage(ruta.tipo_ruta);
        imagenes.forEach((imagen, index) => {
            const src = safeUrl(fixImgPath(imagen), fallback);
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'gallery-item';
            item.setAttribute('aria-label', `Abrir foto ${index + 1}`);
            const img = document.createElement('img');
            img.src = src;
            img.alt = `Foto ${index + 1}`;
            img.loading = 'lazy';
            img.addEventListener('error', function onErr() {
                img.removeEventListener('error', onErr);
                img.src = fallback;
            });
            const zoom = document.createElement('div');
            zoom.className = 'gallery-item__zoom';
            zoom.innerHTML = '<i class="bi bi-arrows-angle-expand" aria-hidden="true"></i>';
            item.appendChild(img);
            item.appendChild(zoom);
            item.addEventListener('click', () => openLightbox(src));
            galleryDiv.appendChild(item);
        });
    }

    // Solo URLs http(s) o relativas (evita javascript:, data:, etc.)
    function safeUrl(url, fallback) {
        const u = String(url || '').trim();
        if (!u) return fallback;
        try {
            const parsed = new URL(u, window.location.href);
            return (parsed.protocol === 'http:' || parsed.protocol === 'https:') ? u : fallback;
        } catch (e) {
            return fallback;
        }
    }

    // Lightbox
    function openLightbox(src) {
        const overlay = document.getElementById('lightbox-overlay');
        const img     = document.getElementById('lightbox-img');
        if (!overlay || !img) return;
        img.src = src;
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        const overlay = document.getElementById('lightbox-overlay');
        if (overlay) overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.getElementById('lightbox-overlay')?.addEventListener('click', closeLightbox);
    document.getElementById('lightbox-close')?.addEventListener('click', closeLightbox);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

    function agregarBotonDescarga(ruta) {
        const downloadDiv = document.getElementById('ruta-download');
        if (!downloadDiv) return;
        const downloadLink = document.createElement('a');
        downloadLink.href = `rutas_st/${encodeURIComponent(ruta.ruta_carpeta || '')}/${encodeURIComponent(ruta.gpx_path || '')}`;
        downloadLink.innerHTML = '<i class="bi bi-download" aria-hidden="true"></i><span class="k-act__label">Descargar GPX</span>';
        downloadLink.classList.add('btn', 'btn-outline-secondary', 'k-act', 'k-act--gpx');
        downloadLink.setAttribute('download', '');
        downloadLink.addEventListener('click', function(e) {
            e.preventDefault();
            trackInteraction('download', ruta.id);
            setTimeout(() => { window.location.href = downloadLink.href; }, 100);
        });
        downloadDiv.appendChild(downloadLink);
    }

    function getColorByDifficulty(difficulty) {
        switch ((difficulty || '').toLowerCase()) {
            case 'beginner':     return '#28a745';
            case 'intermediate': return '#fd7e14';
            case 'advanced':     return '#dc3545';
            default:             return '#0d6efd';
        }
    }

    function createBootstrapIcon(iconClass) {
        return L.divIcon({
            className: 'leaflet-bootstrap-icon',
            html: `<i class="bi ${iconClass}" style="font-size: 24px;"></i>`,
            iconSize: [12, 12],
            iconAnchor: [6, 6],
            popupAnchor: [0, -12]
        });
    }

    function enlargeImage(imageUrl) {
        var enlargeDiv = document.createElement('div');
        enlargeDiv.classList.add('enlarged-image-overlay');

        var enlargedImg = document.createElement('img');
        enlargedImg.src = imageUrl.replace(/paginasespecializadas\\\/skitouring\\\/rutas_st\\\//g, 'paginasespecializadas/skitouring/rutas_st/');
        enlargedImg.alt = 'Foto ampliada';

        enlargeDiv.appendChild(enlargedImg);
        document.body.appendChild(enlargeDiv);

        enlargeDiv.addEventListener('click', function() {
            document.body.removeChild(enlargeDiv);
        });
    }

    // Una sola vista por visita: 'view' al cargar y, al salir, 'view_end' con la duración
    // (el servidor actualiza la fila de la vista en lugar de insertar otra).
    function iniciarSeguimientoVista(rutaId) {
        viewStartTime = Date.now();
        trackInteraction('view', rutaId);
        window.addEventListener('pagehide', function() {
            finalizarSeguimientoVista(rutaId);
        });
    }

    function finalizarSeguimientoVista(rutaId) {
        if (viewEndSent) return;
        viewEndSent = true;
        const viewDuration = Math.round((Date.now() - viewStartTime) / 1000);
        trackInteraction('view_end', rutaId, viewDuration, true);
    }

    function trackInteraction(type, routeId, viewDuration = null, isUnload = false) {
        const interaction = { routeId, type };
        if (viewDuration !== null) {
            interaction.viewDuration = viewDuration;
        }

        const csrfToken = typeof window.kahirCsrfToken === 'function' ? window.kahirCsrfToken() : '';
        const headers = { 'Content-Type': 'application/json' };
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

        fetch('javascript/interaction_tracker.php', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({ interactions: [interaction], csrf_token: csrfToken }),
            credentials: 'include',
            keepalive: isUnload
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Interaction tracked successfully');
            }
        })
        .catch(error => {
            
        });
    }

    // Agregar event listeners para otras interacciones
    const favoriteButton = document.getElementById('favorite-button');
    if (favoriteButton) {
        favoriteButton.addEventListener('click', function() {
            trackInteraction('favorite', rutaId);
        });
    }

    const completeButton = document.getElementById('complete-button');
    if (completeButton) {
        completeButton.addEventListener('click', function() {
            trackInteraction('complete', rutaId);
        });
    }
});