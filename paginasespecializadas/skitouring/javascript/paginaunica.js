document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const rutaId = urlParams.get('id');
    let viewStartTime;
    let mapInteractionStartTime;
    let totalMapInteractionTime = 0;
    let isInteractingWithMap = false;

    fetch('javascript/obtener_rutas.php')
        .then(response => response.json())
        .then(rutas => {
            const ruta = rutas.find(r => r.id === rutaId);
            if (ruta) {
                actualizarInformacionRuta(ruta);
                inicializarMapa(ruta);
                mostrarImagenes(ruta);
                agregarBotonDescarga(ruta);
                iniciarSeguimientoVista(ruta.id);
            } else {
                console.error('Ruta no encontrada con el ID proporcionado.');
            }
        })
        .catch(error => console.error('Error fetching route details:', error));

    function actualizarInformacionRuta(ruta) {
        document.getElementById('ruta-title').innerText = ruta.title;
        document.getElementById('ruta-elevation').innerText = `${ruta.elevation} m`;
        document.getElementById('ruta-duration').innerText = `${ruta.duration} hours`;
        document.getElementById('ruta-distance').innerText = `${ruta.length} km`;
        document.getElementById('ruta-difficulty').innerText = `${ruta.difficulty}`;
        document.getElementById('ruta-description').innerText = ruta.description;
        document.getElementById('ruta-author').innerText = `Autor: ${ruta.usuario || 'Desconocido'}`;
        document.getElementById('ruta-location').innerText = ruta.location || 'N/A';
        document.getElementById('ruta-activity').innerText = ruta.tipo_ruta || 'N/A';

        const preguntasDiv = document.getElementById('ruta-preguntas');
        preguntasDiv.innerHTML = '';
        for (let i = 1; i <= 4; i++) {
            if (ruta[`pregunta${i}`]) {
                const p = document.createElement('p');
                p.innerHTML = `<strong>Pregunta ${i}:</strong> ${ruta[`pregunta${i}`]}`;
                preguntasDiv.appendChild(p);
            }
        }
    }

    function inicializarMapa(ruta) {
        var map = L.map('map', {
            fullscreenControl: true,
            fullscreenControlOptions: {
                position: 'topleft'
            }
        }).setView([ruta.startLat, ruta.startLng], 10);

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
                controlElevation.resize({
                    width: controlElevation.options.width,
                    height: controlElevation.options.height
                });
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

        var gpxLayer = new L.GPX(`rutas_st/${ruta.ruta_carpeta}/${ruta.filename}`, {
            async: true,
            polyline_options: {
                color: getColorByDifficulty(ruta.difficulty),
                weight: 4,
                opacity: 0.75,
                lineCap: 'round'
            },
            marker_options: {
                startIcon: createBootstrapIcon('bi-geo-alt-fill'),
                endIcon: createBootstrapIcon('bi-flag-fill')
            }
        }).on('loaded', function(e) {
            map.fitBounds(e.target.getBounds());
            controlElevation.load(`rutas_st/${ruta.ruta_carpeta}/${ruta.filename}`);
        }).addTo(map);

        // Trackear interacciones con el mapa
        map.on('mousedown', startMapInteraction);
        map.on('mouseup', endMapInteraction);
        map.on('mouseleave', endMapInteraction);
        map.on('touchstart', startMapInteraction);
        map.on('touchend', endMapInteraction);
        map.on('zoomstart', startMapInteraction);
        map.on('zoomend', endMapInteraction);
        map.on('movestart', startMapInteraction);
        map.on('moveend', endMapInteraction);
    }

    function startMapInteraction() {
        if (!isInteractingWithMap) {
            isInteractingWithMap = true;
            mapInteractionStartTime = Date.now();
        }
    }

    function endMapInteraction() {
        if (isInteractingWithMap) {
            isInteractingWithMap = false;
            totalMapInteractionTime += Date.now() - mapInteractionStartTime;
            trackInteraction('map_interaction', rutaId, null, Math.round(totalMapInteractionTime / 1000));
        }
    }

    function mostrarImagenes(ruta) {
        if (ruta.imagenes && ruta.imagenes.length > 0) {
            var galleryDiv = document.getElementById('ruta-gallery');
            galleryDiv.innerHTML = '';
            ruta.imagenes.forEach((imagen, index) => {
                var imgElement = document.createElement('img');
                imgElement.src = imagen.replace(/paginasespecializadas\\\/skitouring\\\/rutas_st\\\//, 'paginasespecializadas/skitouring/rutas_st/');
                imgElement.classList.add('img-fluid', 'mb-3');
                imgElement.alt = `Image ${index + 1} of route`;
                imgElement.style.height = '200px';
                imgElement.style.objectFit = 'cover';
                imgElement.addEventListener('click', function() {
                    enlargeImage(imagen);
                });
                galleryDiv.appendChild(imgElement);
            });
        }
    }

    function agregarBotonDescarga(ruta) {
        var downloadDiv = document.getElementById('ruta-download');
        var downloadLink = document.createElement('a');
        downloadLink.href = `rutas_st/${ruta.ruta_carpeta}/${ruta.filename}`;
        downloadLink.innerText = 'Download GPX';
        downloadLink.classList.add('btn', 'btn-primary', 'mt-3');
        downloadLink.setAttribute('download', '');
        downloadLink.addEventListener('click', function(e) {
            e.preventDefault();
            trackInteraction('download', ruta.id);
            setTimeout(() => {
                window.location.href = downloadLink.href;
            }, 100);
        });
        downloadDiv.appendChild(downloadLink);
    }

    function getColorByDifficulty(difficulty) {
        switch (difficulty.toLowerCase()) {
            case "beginner": return 'green';
            case 'intermediate': return 'blue';
            case 'advanced': return 'yellow';
            default: return 'black';
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
        enlargedImg.alt = 'Enlarged Image';

        enlargeDiv.appendChild(enlargedImg);
        document.body.appendChild(enlargeDiv);

        enlargeDiv.addEventListener('click', function() {
            document.body.removeChild(enlargeDiv);
        });
    }

    function iniciarSeguimientoVista(rutaId) {
        viewStartTime = Date.now();
        trackInteraction('view', rutaId);
        window.addEventListener('beforeunload', function() {
            finalizarSeguimientoVista(rutaId);
        });
    }

    function finalizarSeguimientoVista(rutaId) {
        const viewDuration = Math.round((Date.now() - viewStartTime) / 1000);
        trackInteraction('view', rutaId, viewDuration);
    }

    function trackInteraction(type, routeId, viewDuration = null, mapInteractionTime = null) {
        const interaction = { routeId, type };
        if (viewDuration !== null) {
            interaction.viewDuration = viewDuration;
        }
        if (mapInteractionTime !== null) {
            interaction.mapInteractionTime = mapInteractionTime;
        }
        
        fetch('javascript/interaction_tracker.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify([interaction]),
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Interaction tracked successfully');
            } else {
                console.error('Error tracking interaction:', data.messages);
            }
        })
        .catch(error => {
            console.error('Error sending interaction:', error);
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