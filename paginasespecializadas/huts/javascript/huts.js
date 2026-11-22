document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('map').setView([42.5, 1.5], 8);

    L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: 'Datos del mapa &copy; <a href="https://www.opentopomap.org/copyright">OpenTopoMap</a>'
    }).addTo(map);

    // Escapa texto antes de meterlo en el HTML del popup
    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Solo enlaces http(s)
    function safeHttpUrl(url) {
        try {
            var parsed = new URL(String(url || ''), window.location.href);
            return (parsed.protocol === 'http:' || parsed.protocol === 'https:') ? parsed.href : '';
        } catch (e) {
            return '';
        }
    }

    var geojsonUrl = 'javascript/pyrenees.geojson';
    var geojsonLayer;
    var originalData; // Variable para guardar los datos originales

    function cargarDatosEnMapa(data) {
        geojsonLayer = L.geoJSON(data, {
            pointToLayer: function(feature, latlng) {
                var fillColor;
                switch (feature.properties.type_hebergement.toLowerCase()) {
                    case 'cabane ouverte':
                        fillColor = '#3F6B7A'; // Lago: cabane ouverte
                        break;
                    case 'cabane fermee':
                        fillColor = '#B5653A'; // Óxido: cabane fermee
                        break;
                    case 'cabane ouverte mais ocupee par le berger l ete':
                        fillColor = '#D49A3A'; // Ocre: ocupada por el pastor en verano
                        break;
                    case 'orri toue abri en pierre':
                        fillColor = '#C9B27C'; // Arena: orri, toue, abri en pierre
                        break;
                    case 'refuge garde l ete partie hivernal':
                        fillColor = '#7E9A55'; // Musgo claro: guardado en verano, parte invernal
                        break;
                    case 'refuge garde toute l annee':
                        fillColor = '#7A4E6E'; // Ciruela: guardado todo el año
                        break;
                    case 'ruine':
                        fillColor = '#9A968C'; // Piedra: ruine
                        break;
                    default:
                        fillColor = '#5F7444'; // Musgo por defecto
                        break;
                }
                return L.circleMarker(latlng, {
                    radius: 7,
                    fillColor: fillColor,
                    color: "#FFFDF8",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.95
                }).bindPopup(function(layer) {
                    var props = layer.feature.properties || {};
                    var url = safeHttpUrl(props.url);
                    var facts = [
                        props.altitude  ? `<dt>Altitud</dt><dd>${escapeHtml(isNaN(Number(props.altitude)) ? props.altitude : Number(props.altitude).toLocaleString('es-ES'))} m</dd>` : '',
                        props.cap_ete   ? `<dt>Plazas en verano</dt><dd>${escapeHtml(props.cap_ete)}</dd>` : '',
                        props.cap_hiver ? `<dt>Plazas en invierno</dt><dd>${escapeHtml(props.cap_hiver)}</dd>` : ''
                    ].join('');
                    var popupContent = `
                        <div class="k-popup">
                            <p class="k-popup__eyebrow"><span class="k-dot" style="--act:${fillColor};" aria-hidden="true"></span>Refugio</p>
                            <h3 class="k-popup__title">${escapeHtml(props.name || 'Refugio sin nombre')}</h3>
                            ${props.region ? `<p class="k-popup__place"><i class="bi bi-geo-alt" aria-hidden="true"></i>${escapeHtml(props.region)}</p>` : ''}
                            ${facts ? `<dl class="k-popup__facts">${facts}</dl>` : ''}
                            ${url ? `<a href="${escapeHtml(url)}" class="k-popup__link" target="_blank" rel="noopener noreferrer">Más información <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>` : ''}
                        </div>`;
                    return popupContent;
                });
            }
        }).addTo(map);
    }

    // Cargar datos originales al inicio
    fetch(geojsonUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al cargar el archivo GeoJSON');
            }
            return response.json();
        })
        .then(data => {
            originalData = data; // Guardar los datos originales
            cargarDatosEnMapa(data); // Cargar los datos en el mapa al inicio
        })
        .catch(error => {
            console.error('Error al cargar el archivo GeoJSON:', error);
            var errorMessage = document.createElement('div');
            errorMessage.className = 'alert alert-danger k-map-alert';
            errorMessage.setAttribute('role', 'alert');
            errorMessage.textContent = 'No se pudieron cargar los refugios. Recarga la página en un momento.';
            document.getElementById('map').appendChild(errorMessage);
        });

    // Aplicar filtro al hacer clic en el botón
    document.getElementById('aplicarFiltro').addEventListener('click', function() {
        var selectedType = document.getElementById('filtroRefugio').value;

        if (selectedType === 'all') {
            // Mostrar todos los datos originales
            map.eachLayer(function(layer) {
                if (layer instanceof L.Marker || layer instanceof L.CircleMarker) {
                    map.removeLayer(layer);
                }
            });
            cargarDatosEnMapa(originalData); // Restaurar datos originales
        } else {
            // Filtrar datos y mostrar solo el tipo seleccionado
            var filteredData = originalData.features.filter(function(feature) {
                return feature.properties.type_hebergement.toLowerCase() === selectedType.toLowerCase();
            });
            map.eachLayer(function(layer) {
                if (layer instanceof L.Marker || layer instanceof L.CircleMarker) {
                    map.removeLayer(layer);
                }
            });
            cargarDatosEnMapa({
                type: 'FeatureCollection',
                features: filteredData
            });
        }
    });
});
